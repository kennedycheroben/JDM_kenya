<?php
/**
 * JDM Kenya - Core Database Connection & Performance Engine
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

// Define base URL path dynamically (e.g. "/JDM_kenya" on local or "" on live production)
$docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
$projectDir = str_replace('\\', '/', dirname(__DIR__));
$basePath = '';
if (!empty($docRoot) && strpos($projectDir, $docRoot) === 0) {
    $basePath = substr($projectDir, strlen($docRoot));
}
$basePath = '/' . ltrim(str_replace('\\', '/', $basePath), '/');
$basePath = rtrim($basePath, '/');
if (!defined('BASE_PATH')) {
    define('BASE_PATH', $basePath);
}

// Load local overrides first (not checked into VCS): create `config.local.php` in project root
$localConfig = dirname(__DIR__) . '/config.local.php';
if (file_exists($localConfig)) {
    require_once $localConfig;
}

// Load central configuration from the project root (parent of core)
$configFile = dirname(__DIR__) . '/config.php';
if (file_exists($configFile)) {
    require_once $configFile;
}

// Fallback defaults if config.php is missing
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'xqtrqexj_jdm_kenya');
if (!defined('DB_USER')) define('DB_USER', 'xqtrqexj_kennedycheroben');
if (!defined('DB_PASS')) define('DB_PASS', 'ftS_p-X2WvjeTn&');

try {
    // Establishing a high-performance PDO connection for live production
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false, 
            PDO::ATTR_PERSISTENT => true,        
        ]
    );
} catch (PDOException $e) {
    die('Critical Error: Database connection failed. Details: ' . $e->getMessage());
}

// Resource Cleanup
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

if (!function_exists('waLink')) {
    function waLink(string $phone): string {
        $digits = preg_replace('/\D+/', '', $phone);
        return $digits ? 'https://wa.me/' . $digits : '#';
    }
}

if (!function_exists('maskPhone')) {
    function maskPhone(string $phone): string {
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '') return '';
        if (strlen($digits) <= 6) return $digits;
        $first = substr($digits, 0, 4);
        $last = substr($digits, -2);
        return $first . str_repeat('*', max(0, strlen($digits) - 6)) . $last;
    }
}

/**
 * Simple Request-Level Cache
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

/**
 * CSRF Protection
 */
if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string {
        return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
    }
}

if (!function_exists('verify_csrf')) {
    function verify_csrf(?string $token): bool {
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('require_csrf')) {
    function require_csrf(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? '';
            if (!verify_csrf($token)) {
                die('Invalid or missing CSRF token. Please reload the page and try again.');
            }
        }
    }
}