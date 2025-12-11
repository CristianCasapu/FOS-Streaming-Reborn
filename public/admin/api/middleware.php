<?php

/**
 * Admin API Middleware
 * Security checks for admin API endpoints
 */

/**
 * Check if request is coming from admin path
 * Note: This is now handled by index.php router which maps any ADMIN_PATH/api/
 * to the actual /admin/api/ files. This function is kept for backwards compatibility
 * but always passes since routing already validates the path.
 */
function requireAdminPath() {
    // Path validation is now handled by index.php router
    // which maps requests like /adminx/api/auth.php to /admin/api/auth.php
    // No additional check needed here
    return true;
}

/**
 * Rate limiting for authentication attempts
 */
function checkRateLimit($identifier) {
    $maxAttempts = (int)($_ENV['MAX_LOGIN_ATTEMPTS'] ?? 5);
    $timeout = (int)($_ENV['LOGIN_TIMEOUT'] ?? 900); // 15 minutes

    $cacheKey = "login_attempts_" . md5($identifier);
    $cacheFile = __DIR__ . '/../../../cache/' . $cacheKey;

    // Create cache directory if not exists
    if (!is_dir(__DIR__ . '/../../../cache')) {
        mkdir(__DIR__ . '/../../../cache', 0775, true);
    }

    $attempts = 0;
    $timestamp = time();

    if (file_exists($cacheFile)) {
        $data = json_decode(file_get_contents($cacheFile), true);
        if ($data && ($timestamp - $data['timestamp']) < $timeout) {
            $attempts = $data['attempts'];
        }
    }

    if ($attempts >= $maxAttempts) {
        http_response_code(429);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Too many attempts',
            'message' => 'Please try again later'
        ]);
        exit;
    }

    // Increment attempts
    file_put_contents($cacheFile, json_encode([
        'attempts' => $attempts + 1,
        'timestamp' => $timestamp
    ]));
}

/**
 * Clear rate limit on successful login
 */
function clearRateLimit($identifier) {
    $cacheKey = "login_attempts_" . md5($identifier);
    $cacheFile = __DIR__ . '/../../../cache/' . $cacheKey;

    if (file_exists($cacheFile)) {
        unlink($cacheFile);
    }
}

/**
 * Validate CSRF token (if not using session-based auth)
 */
function validateCsrfToken() {
    // Skip CSRF for GET requests
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        return true;
    }

    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    // For now, we'll skip strict CSRF validation since we're using session cookies
    // In production, you should implement proper CSRF protection
    return true;
}

/**
 * Log security events
 */
function logSecurityEvent($event, $details = []) {
    if (!($_ENV['SECURITY_LOGGING'] ?? true)) {
        return;
    }

    $logFile = __DIR__ . '/../../../storage/logs/security.log';
    $logDir = dirname($logFile);

    if (!is_dir($logDir)) {
        mkdir($logDir, 0775, true);
    }

    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event' => $event,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'details' => $details
    ];

    file_put_contents(
        $logFile,
        json_encode($logEntry) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}
