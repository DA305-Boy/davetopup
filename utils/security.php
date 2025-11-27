<?php
/**
 * Security Utilities
 * Input sanitization, validation, and protection functions
 */

class Security {
    /**
     * Sanitize email input
     */
    public static function sanitizeEmail($email) {
        return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    }

    /**
     * Sanitize general input (removes special characters)
     */
    public static function sanitizeInput($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitize numeric input
     */
    public static function sanitizeNumeric($input) {
        return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }

    /**
     * Generate secure CSRF token
     */
    public static function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Verify CSRF token
     */
    public static function verifyCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Hash password securely
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Verify password
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    /**
     * Generate secure random token
     */
    public static function generateSecureToken($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Validate credit card number (Luhn algorithm)
     */
    public static function validateCreditCard($cardNumber) {
        $cardNumber = preg_replace('/\D/', '', $cardNumber);
        if (strlen($cardNumber) < 13 || strlen($cardNumber) > 19) {
            return false;
        }

        $sum = 0;
        $digit = 0;
        $addend = 0;
        $timesTwo = false;

        for ($i = strlen($cardNumber) - 1; $i >= 0; $i--) {
            $digit = (int) $cardNumber[$i];

            if ($timesTwo) {
                $addend = $digit * 2;
                if ($addend > 9) {
                    $addend -= 9;
                }
            } else {
                $addend = $digit;
            }

            $sum += $addend;
            $timesTwo = !$timesTwo;
        }

        $modulus = $sum % 10;
        return $modulus === 0;
    }

    /**
     * Validate IBAN
     */
    public static function validateIBAN($iban) {
        $iban = strtoupper(trim($iban));
        if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/', $iban)) {
            return false;
        }
        return strlen($iban) >= 15 && strlen($iban) <= 34;
    }

    /**
     * Prevent SQL injection with prepared statements
     */
    public static function prepareSQLStatement($db, $query, $params) {
        $stmt = $db->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $db->error);
        }

        if (!empty($params)) {
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
        }

        return $stmt;
    }

    /**
     * Rate limiting helper
     */
    public static function checkRateLimit($identifier, $maxAttempts = 5, $windowSeconds = 300) {
        $key = "ratelimit_" . md5($identifier);
        $attempts = isset($_SESSION[$key]) ? $_SESSION[$key]['attempts'] : 0;
        $timestamp = isset($_SESSION[$key]) ? $_SESSION[$key]['timestamp'] : time();

        if (time() - $timestamp > $windowSeconds) {
            // Window expired
            $_SESSION[$key] = [
                'attempts' => 1,
                'timestamp' => time()
            ];
            return true;
        }

        if ($attempts >= $maxAttempts) {
            return false; // Rate limit exceeded
        }

        $_SESSION[$key] = [
            'attempts' => $attempts + 1,
            'timestamp' => $timestamp
        ];

        return true;
    }

    /**
     * Validate country code
     */
    public static function validateCountryCode($code) {
        $validCodes = [
            'US', 'GB', 'CA', 'AU', 'DE', 'FR', 'JP', 'BR', 'IN', 'ZA',
            'MX', 'SG', 'HK', 'TH', 'PH', 'ID', 'VN', 'RU', 'IT', 'ES'
        ];
        return in_array(strtoupper($code), $validCodes);
    }

    /**
     * Validate phone number (basic)
     */
    public static function validatePhoneNumber($phone) {
        $phone = preg_replace('/\D/', '', $phone);
        return strlen($phone) >= 10 && strlen($phone) <= 15;
    }

    /**
     * Generate payment signature
     */
    public static function generatePaymentSignature($params, $secret) {
        ksort($params);
        $paramString = http_build_query($params);
        return hash_hmac('sha256', $paramString, $secret);
    }

    /**
     * Encrypt sensitive data
     */
    public static function encrypt($data, $key) {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt sensitive data
     */
    public static function decrypt($data, $key) {
        $data = base64_decode($data);
        $iv = substr($data, 0, openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = substr($data, openssl_cipher_iv_length('aes-256-cbc'));
        return openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
    }
}

// Shorthand functions
function sanitizeInput($input) {
    return Security::sanitizeInput($input);
}

function sanitizeEmail($email) {
    return Security::sanitizeEmail($email);
}

function sanitizeNumeric($input) {
    return Security::sanitizeNumeric($input);
}
?>
