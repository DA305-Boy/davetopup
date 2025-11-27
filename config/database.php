<?php
/**
 * Database Configuration
 * Secure connection with prepared statements
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'davetopup_user');
define('DB_PASS', 'SECURE_PASSWORD_HERE'); // Change to secure password
define('DB_NAME', 'davetopup_checkout');
define('DB_PORT', 3306);

// Establish connection
$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Check connection
if ($db->connect_error) {
    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]));
}

// Set charset to UTF-8
if (!$db->set_charset("utf8mb4")) {
    die(json_encode([
        'success' => false,
        'message' => 'Charset setting failed'
    ]));
}

// Enable SSL for database connections (recommended)
// $db->ssl_set('/path/to/ca.pem', '/path/to/client-cert.pem', '/path/to/client-key.pem', null, null);

// Set strict mode for better error reporting
$db->query("SET SESSION sql_mode='STRICT_TRANS_TABLES'");

// Store connection for global use
define('DB_CONNECTION_ACTIVE', true);
?>
