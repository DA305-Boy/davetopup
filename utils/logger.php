<?php
/**
 * Logger Utility
 * Logs payment events and errors
 */

class Logger {
    private static $logFile = null;
    private static $logLevel = 'INFO';

    public static function init($logDir = null, $level = 'INFO') {
        self::$logLevel = $level;
        if ($logDir) {
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            self::$logFile = $logDir . '/payment_' . date('Y-m-d') . '.log';
        }
    }

    /**
     * Log debug message
     */
    public static function debug($message) {
        self::log('DEBUG', $message);
    }

    /**
     * Log info message
     */
    public static function info($message) {
        self::log('INFO', $message);
    }

    /**
     * Log warning message
     */
    public static function warning($message) {
        self::log('WARNING', $message);
    }

    /**
     * Log error message
     */
    public static function error($message) {
        self::log('ERROR', $message);
    }

    /**
     * Generic log function
     */
    private static function log($level, $message) {
        $logLevels = ['DEBUG' => 0, 'INFO' => 1, 'WARNING' => 2, 'ERROR' => 3];
        
        if ($logLevels[$level] < $logLevels[self::$logLevel]) {
            return; // Skip logging if below threshold
        }

        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;

        // Log to file
        if (self::$logFile) {
            error_log($logMessage, 3, self::$logFile);
        }

        // Also log to system error log if configured
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log($logMessage);
        }
    }

    /**
     * Log transaction
     */
    public static function logTransaction($orderId, $amount, $paymentMethod, $status) {
        self::info("Transaction | OrderID: $orderId | Amount: $amount | Method: $paymentMethod | Status: $status");
    }

    /**
     * Log API call
     */
    public static function logApiCall($apiName, $endpoint, $statusCode, $responseTime) {
        self::info("API Call | Service: $apiName | Endpoint: $endpoint | Status: $statusCode | Time: ${responseTime}ms");
    }

    /**
     * Log security event
     */
    public static function logSecurityEvent($event, $details) {
        self::warning("Security | Event: $event | Details: $details");
    }
}

// Initialize logger
if (defined('LOG_DIR')) {
    Logger::init(LOG_DIR, LOG_LEVEL);
} else {
    Logger::init(sys_get_temp_dir() . '/davetopup', 'INFO');
}
?>
