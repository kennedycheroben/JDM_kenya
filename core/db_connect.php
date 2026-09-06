<?php
/**
 * JDM Kenya - Core Database Connection & Performance Engine
 */

if (session_status() === PHP_SESSION_NONE) {
    $sessionCookieName = getenv('JDM_SESSION_COOKIE') ?: 'jdm_kenya_session';
    if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $sessionCookieName)) {
        $sessionCookieName = 'jdm_kenya_session';
    }
    session_name($sessionCookieName);
    $sessionPath = session_save_path();
    if ($sessionPath === '' || !is_writable($sessionPath)) {
        $fallbackSessionPath = dirname(__DIR__) . '/tmp/sessions';
        if (!is_dir($fallbackSessionPath)) {
            mkdir($fallbackSessionPath, 0775, true);
        }
        session_save_path($fallbackSessionPath);
    }
    // Secure session cookie settings
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || (($_SERVER['SERVER_PORT'] ?? 80) == 443);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Define base URL path dynamically:
// - On localhost/XAMPP: project is at /JDM_kenya under the htdocs root → BASE_PATH = "/JDM_kenya"
// - On live cPanel: project IS the document root (public_html/) → BASE_PATH = ""
$docRoot    = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$projectDir = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');

if (!defined('BASE_PATH')) {
    if (!empty($docRoot) && strpos($projectDir, $docRoot) === 0) {
        $computed = substr($projectDir, strlen($docRoot));
        // Normalize: if the project dir IS the doc root, computed will be "" — keep it empty
        define('BASE_PATH', rtrim($computed, '/'));
    } else {
        define('BASE_PATH', '');
    }
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

// Fallback safe defaults if config.php is missing.
// Credentials must be set in config.php (excluded from VCS via .gitignore).
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', '');
if (!defined('DB_USER')) define('DB_USER', '');
if (!defined('DB_PASS')) define('DB_PASS', '');

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
            PDO::ATTR_PERSISTENT => false,        
        ]
    );
} catch (PDOException $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    die('A critical error occurred. Please try again later.');
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
        if (strlen($digits) <= 4) return $digits;
        $last = substr($digits, -4);
        return str_repeat('*', strlen($digits) - 4) . $last;
    }
}

if (!function_exists('maskEmail')) {
    function maskEmail(string $email): string {
        $parts = explode('@', $email);
        if (count($parts) !== 2) return $email;
        $name = $parts[0];
        $domain = $parts[1];
        if ($name === '') return $email;
        $first = substr($name, 0, 1);
        return $first . str_repeat('*', strlen($name) - 1) . '@' . $domain;
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
    function require_csrf(): bool {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? '';
            if (!verify_csrf($token)) {
                $_SESSION['csrf_error'] = 'Session expired. Please reload the page and try again.';
                return false;
            }
        }
        return true;
    }
}

/**
 * Rate limiter using session storage.
 * Limits to $maxAttempts per $windowSeconds.
 * For high-traffic sites, consider migrating to database-backed or Redis rate limiting.
 */
if (!function_exists('check_rate_limit')) {
    function check_rate_limit(string $action, int $maxAttempts = 5, int $windowSeconds = 300): bool {
        $key = 'rate_limit_' . $action;
        $now = time();
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [];
        }
        $_SESSION[$key] = array_filter($_SESSION[$key], function($ts) use ($now, $windowSeconds) {
            return $ts > ($now - $windowSeconds);
        });
        if (count($_SESSION[$key]) >= $maxAttempts) {
            return false;
        }
        $_SESSION[$key][] = $now;
        return true;
    }
}

/**
 * IP-based rate limiter using a flat file (for high-traffic, replace with DB/Redis).
 * Limits to $maxRequests per $windowSeconds per IP.
 */
if (!function_exists('check_ip_rate_limit')) {
    function check_ip_rate_limit(int $maxRequests = 60, int $windowSeconds = 60): bool {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $file = sys_get_temp_dir() . '/ratelimit_' . md5($ip) . '.tmp';
        $now = time();
        $requests = [];
        if (file_exists($file)) {
            $data = @file_get_contents($file);
            if ($data !== false) {
                $requests = json_decode($data, true) ?? [];
            }
        }
        $requests = array_filter($requests, function($ts) use ($now, $windowSeconds) {
            return $ts > ($now - $windowSeconds);
        });
        if (count($requests) >= $maxRequests) {
            return false;
        }
        $requests[] = $now;
        @file_put_contents($file, json_encode($requests), LOCK_EX);
        return true;
    }
}

/**
 * Validate rate limit for all incoming requests (lightweight).
 * Call this at the top of sensitive pages.
 */
if (!function_exists('enforce_rate_limit')) {
    function enforce_rate_limit(): void {
        if (!check_ip_rate_limit(120, 60)) {
            http_response_code(429);
            die('Too many requests. Please slow down.');
        }
    }
}

// Enforce global rate limit on every page
enforce_rate_limit();

// Central restriction for authenticated Sports Ministry applicants. The helper
// is migration-aware and becomes a no-op until the sports tables exist.
require_once __DIR__ . '/sports_authorization.php';
sports_apply_central_access_gate($pdo);
