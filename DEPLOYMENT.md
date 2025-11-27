# Deployment & Configuration Guide

## Server Requirements

- **OS:** Ubuntu 20.04+ / Debian 10+
- **PHP:** 7.4 or higher
- **MySQL:** 5.7+ or MariaDB 10.2+
- **Web Server:** Nginx or Apache
- **SSL:** Let's Encrypt (free) or commercial certificate
- **Memory:** 2GB+ RAM
- **Disk:** 10GB+ free space

## Installation Script

```bash
#!/bin/bash
set -e

echo "=== Dave TopUp Payment System Deployment ==="

# Update system
sudo apt update && sudo apt upgrade -y

# Install required packages
sudo apt install -y php7.4 php7.4-cli php7.4-fpm php7.4-mysql php7.4-curl php7.4-openssl php7.4-json
sudo apt install -y mysql-server
sudo apt install -y nginx certbot python3-certbot-nginx

# Create web root
sudo mkdir -p /var/www/davetopup
sudo chown -R www-data:www-data /var/www/davetopup

# Clone repository
cd /var/www/davetopup
git clone https://github.com/yourusername/davetopup.git .

# Install Composer
curl -fsSL https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Create necessary directories
mkdir -p logs
mkdir -p uploads
chmod 755 logs uploads config utils api

# Create database
sudo mysql -u root <<EOF
CREATE DATABASE davetopup_checkout;
CREATE USER 'davetopup_user'@'localhost' IDENTIFIED BY 'SECURE_PASSWORD';
GRANT ALL PRIVILEGES ON davetopup_checkout.* TO 'davetopup_user'@'localhost';
FLUSH PRIVILEGES;
EOF

# Import schema
sudo mysql -u davetopup_user -p davetopup_checkout < database/schema.sql

# Configure SSL
sudo certbot certonly --webroot -w /var/www/davetopup -d www.davetopup.com

echo "=== Installation Complete ==="
echo "Next steps:"
echo "1. Update config/database.php with database credentials"
echo "2. Update config/payments.php with payment gateway API keys"
echo "3. Restart web server: sudo systemctl restart nginx"
```

## Nginx Configuration

Create `/etc/nginx/sites-available/davetopup`:

```nginx
server {
    listen 80;
    server_name www.davetopup.com davetopup.com;
    
    # Redirect to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name www.davetopup.com davetopup.com;
    root /var/www/davetopup;
    index index.html index.php;
    
    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/www.davetopup.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/www.davetopup.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;
    
    # Security Headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "DENY" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;
    
    # Rate Limiting
    limit_req_zone $binary_remote_addr zone=checkout:10m rate=5r/m;
    limit_req_zone $binary_remote_addr zone=api:10m rate=10r/m;
    
    # Gzip Compression
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml text/javascript 
               application/json application/javascript application/xml+rss;
    
    # PHP Configuration
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php7.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
    
    # API Rate Limiting
    location /api/ {
        limit_req zone=api burst=20 nodelay;
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php7.4-fpm.sock;
    }
    
    # Checkout Rate Limiting
    location /public/checkout.html {
        limit_req zone=checkout burst=10 nodelay;
    }
    
    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }
    
    location ~ ~$ {
        deny all;
    }
    
    # Static assets caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
    
    # Redirect old domain
    location / {
        try_files $uri $uri/ /index.html;
    }
}
```

Enable site:
```bash
sudo ln -s /etc/nginx/sites-available/davetopup /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

## Apache Configuration

Create `/etc/apache2/sites-available/davetopup.conf`:

```apache
<VirtualHost *:80>
    ServerName www.davetopup.com
    ServerAlias davetopup.com
    Redirect permanent / https://www.davetopup.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName www.davetopup.com
    ServerAlias davetopup.com
    DocumentRoot /var/www/davetopup
    
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/www.davetopup.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/www.davetopup.com/privkey.pem
    
    # Security Headers
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "DENY"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    
    # PHP Configuration
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php7.4-fpm.sock|fcgi://localhost"
    </FilesMatch>
    
    # Rate Limiting
    <Location /api/>
        Require all granted
        # Use mod_ratelimit for rate limiting
    </Location>
    
    # Deny sensitive files
    <FilesMatch "^\.">
        Deny from all
    </FilesMatch>
    
    <Directory /var/www/davetopup>
        Options -Indexes
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Enable site:
```bash
sudo a2enmod rewrite ssl proxy_fcgi setenvif headers
sudo a2ensite davetopup
sudo apache2ctl configtest
sudo systemctl restart apache2
```

## Database Backup

Create `/usr/local/bin/backup-davetopup.sh`:

```bash
#!/bin/bash
BACKUP_DIR="/backups/davetopup"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
DB_USER="davetopup_user"
DB_PASS="SECURE_PASSWORD"
DB_NAME="davetopup_checkout"

mkdir -p $BACKUP_DIR

mysqldump -u $DB_USER -p$DB_PASS $DB_NAME > \
    $BACKUP_DIR/db_backup_$TIMESTAMP.sql

# Keep only last 30 days
find $BACKUP_DIR -name "*.sql" -mtime +30 -delete

echo "Backup completed: $BACKUP_DIR/db_backup_$TIMESTAMP.sql"
```

Schedule with cron:
```bash
0 2 * * * /usr/local/bin/backup-davetopup.sh
```

## Monitoring & Logs

### Check PHP-FPM Status
```bash
systemctl status php7.4-fpm
```

### Check Nginx Status
```bash
systemctl status nginx
```

### View Error Logs
```bash
tail -f /var/log/nginx/error.log
tail -f /var/log/php7.4-fpm.log
tail -f /var/www/davetopup/logs/payment_*.log
```

### Monitor Real-time Traffic
```bash
watch -n 1 'tail -20 /var/log/nginx/access.log'
```

## SSL/TLS Auto-Renewal

Let's Encrypt certificates expire after 90 days. Set up auto-renewal:

```bash
# Test renewal
sudo certbot renew --dry-run

# Enable auto-renewal
sudo systemctl enable certbot.timer
sudo systemctl start certbot.timer

# Check renewal schedule
sudo systemctl list-timers certbot.timer
```

## Firewall Configuration

```bash
# UFW Firewall
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable

# Or with iptables
sudo iptables -A INPUT -p tcp --dport 22 -j ACCEPT
sudo iptables -A INPUT -p tcp --dport 80 -j ACCEPT
sudo iptables -A INPUT -p tcp --dport 443 -j ACCEPT
sudo iptables -P INPUT DROP
```

## Performance Tuning

### PHP Configuration
Edit `/etc/php/7.4/fpm/php.ini`:

```ini
memory_limit = 256M
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 60
default_socket_timeout = 60
```

### MySQL Configuration
Edit `/etc/mysql/mysql.conf.d/mysqld.cnf`:

```ini
[mysqld]
innodb_buffer_pool_size = 512M
innodb_log_file_size = 256M
max_connections = 500
query_cache_size = 128M
```

### Nginx Worker Processes
Edit `/etc/nginx/nginx.conf`:

```nginx
worker_processes auto;
worker_connections 2048;
keepalive_timeout 65;
```

Restart services:
```bash
sudo systemctl restart php7.4-fpm mysql nginx
```

## Health Check

```bash
#!/bin/bash
echo "=== Dave TopUp Health Check ==="

# Check HTTPS
curl -s -o /dev/null -w "%{http_code}" https://www.davetopup.com/public/checkout.html
echo "Checkout page: $([ $? -eq 0 ] && echo 'OK' || echo 'FAILED')"

# Check API
curl -s https://www.davetopup.com/api/checkout.php 2>/dev/null | grep -q "success"
echo "API: $([ $? -eq 0 ] && echo 'OK' || echo 'FAILED')"

# Check database connection
mysqladmin -u davetopup_user -p ping 2>/dev/null | grep -q "mysqld is alive"
echo "Database: $([ $? -eq 0 ] && echo 'OK' || echo 'FAILED')"

# Check SSL
echo | openssl s_client -servername www.davetopup.com -connect www.davetopup.com:443 2>/dev/null | grep -q "Verify return code"
echo "SSL: $([ $? -eq 0 ] && echo 'OK' || echo 'FAILED')"
```

Save and run:
```bash
chmod +x health-check.sh
./health-check.sh
```
