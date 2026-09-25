<?php
/**
 * JDM Kenya — Central Configuration
 *
 * Credentials are loaded from the .env file in the project root.
 * No external library required — uses a built-in lightweight .env parser.
 *
 * To update credentials: edit .env (never commit .env to git).
 * Copy .env.example → .env and fill in your values on each server.
 */

/**
 * Lightweight .env parser — no composer/library required.
 * Supports: KEY=value, KEY="quoted value", KEY='quoted value', # comments.
 * Loads values into $_ENV and putenv() so getenv() works too.
 */
if (!function_exists('jdm_load_env')) {
    function jdm_load_env(string $filePath): void {
        if (!is_file($filePath) || !is_readable($filePath)) {
            return;
        }
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            // Skip comments and blank lines
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            // Must contain =
            $eqPos = strpos($line, '=');
            if ($eqPos === false) {
                continue;
            }
            $key   = trim(substr($line, 0, $eqPos));
            $value = trim(substr($line, $eqPos + 1));

            // Strip surrounding quotes (single or double)
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last  = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            // Strip inline comment (only outside quotes — simple heuristic for our format)
            // e.g. KEY=value # comment → value
            if (strpos($value, ' #') !== false && $value[0] !== '"' && $value[0] !== "'") {
                $value = trim(explode(' #', $value, 2)[0]);
            }

            if ($key === '') {
                continue;
            }

            // Only set if not already defined in the environment (don't override server env vars)
            if (!isset($_ENV[$key]) && getenv($key) === false) {
                $_ENV[$key] = $value;
                putenv("{$key}={$value}");
            }
        }
    }
}

/**
 * Read a value: $_ENV (from .env) → getenv() (system/cPanel env) → default.
 */
if (!function_exists('jdm_env')) {
    function jdm_env(string $key, string $default = ''): string {
        $val = $_ENV[$key] ?? getenv($key);
        return ($val !== false && $val !== '') ? (string)$val : $default;
    }
}

// Load .env from project root (this file lives in the project root)
jdm_load_env(__DIR__ . '/.env');

// --- Database ---
if (!defined('DB_HOST')) define('DB_HOST', jdm_env('DB_HOST', 'localhost'));
if (!defined('DB_NAME')) define('DB_NAME', jdm_env('DB_NAME'));
if (!defined('DB_USER')) define('DB_USER', jdm_env('DB_USER'));
if (!defined('DB_PASS')) define('DB_PASS', jdm_env('DB_PASS'));

// --- Upload Directory ---
if (!defined('UPLOAD_DIR')) define('UPLOAD_DIR', __DIR__ . '/uploads/');

// --- Application Settings ---
if (!defined('SITE_NAME')) define('SITE_NAME', jdm_env('SITE_NAME', 'JDM Kenya'));
if (!defined('SITE_URL'))  define('SITE_URL',  jdm_env('SITE_URL',  'https://jdmkenya.com'));

// --- Email (From Address) ---
if (!defined('MAIL_FROM_EMAIL')) define('MAIL_FROM_EMAIL', jdm_env('MAIL_FROM_EMAIL', 'noreply@jdmkenya.com'));
if (!defined('MAIL_FROM_NAME'))  define('MAIL_FROM_NAME',  jdm_env('MAIL_FROM_NAME',  'JDM Kenya'));

// --- SMTP Settings ---
if (!defined('SMTP_HOST'))     define('SMTP_HOST',     jdm_env('SMTP_HOST'));
if (!defined('SMTP_PORT'))     define('SMTP_PORT',     (int)(jdm_env('SMTP_PORT', '465')));
if (!defined('SMTP_SECURE'))   define('SMTP_SECURE',   jdm_env('SMTP_SECURE',   'ssl'));
if (!defined('SMTP_USERNAME')) define('SMTP_USERNAME', jdm_env('SMTP_USERNAME'));
if (!defined('SMTP_PASSWORD')) define('SMTP_PASSWORD', jdm_env('SMTP_PASSWORD'));
if (!defined('SMTP_TIMEOUT'))  define('SMTP_TIMEOUT',  (int)(jdm_env('SMTP_TIMEOUT', '15')));
