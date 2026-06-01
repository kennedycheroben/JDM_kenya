<?php
/**
 * JDM Kenya - Core Database Connection & Performance Engine
 * 
 * Performance Logic:
 * 1. Persistent Connection: Uses unix_socket for lightning-fast local DB communication.
 * 2. Prepared Statements: Emulation disabled to offload processing to MySQL and prevent overhead.
 * 3. Resource Management: Explicit connection closure via shutdown handler.
 * 4. Micro-Caching: Global variable cache to prevent redundant queries within a single request lifecycle.
 */

if (session_status() === PHP_SESSION_NONE) {
    $sessionPath = session_save_path();
    if ($sessionPath === '' || !is_writable($sessionPath)) {
        $fallbackSessionPath = dirname(__DIR__) . '/tmp/sessions';
        if (!is_dir($fallbackSessionPath)) {
            mkdir($fallbackSessionPath, 0775, true);
        }
        session_save_path($fallbackSessionPath);
    }
    session_start();
}

// Database Configuration
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'jdm_kenya');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('UPLOAD_DIR')) define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/');

try {
    // Establishing a high-performance PDO connection
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4;unix_socket=/opt/lampp/var/mysql/mysql.sock',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false, // Better performance for modern MySQL
            PDO::ATTR_PERSISTENT => true,        // Reuse connections across requests for speed
        ]
    );
} catch (PDOException $e) {
    die('Critical Error: Database connection failed. Please contact admin.');
}

// Resource Cleanup: Ensure connection is released back to the pool
register_shutdown_function(function() use (&$pdo) {
    $pdo = null;
});

/**
 * Performance-optimized HTML Escaping
 */
if (!function_exists('escape')) {
    function escape($value) {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Simple Request-Level Cache
 * Prevents multiple database hits for the same data during a single page load.
 */
$request_cache = [];

if (!function_exists('get_cached_data')) {
    function get_cached_data($key, $callback) {
        global $request_cache;
        if (!isset($request_cache[$key])) {
            $request_cache[$key] = $callback();
        }
        return $request_cache[$key];
    }
}
