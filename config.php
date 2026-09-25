<?php
/**
 * JDM Kenya — Central Configuration
 *
 * Credentials are loaded from the .env file in the project root.
 * Never hardcode secrets in this file.
 * Copy .env.example → .env and fill in your values.
 */

// Load .env via vlucas/phpdotenv (vendor/autoload.php is loaded by core/db_connect.php too,
// but we guard with file_exists so config.php stays safe to require independently).
$autoloadFile = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadFile)) {
    require_once $autoloadFile;
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad(); // safeLoad() does not throw if .env is missing
}

// Helper: read from $_ENV (phpdotenv) then fall back to getenv() then a default.
function _env(string $key, string $default = ''): string {
    return (string)($_ENV[$key] ?? getenv($key) ?: $default);
}

// --- Database ---
if (!defined('DB_HOST')) define('DB_HOST', _env('DB_HOST', 'localhost'));
if (!defined('DB_NAME')) define('DB_NAME', _env('DB_NAME'));
if (!defined('DB_USER')) define('DB_USER', _env('DB_USER'));
if (!defined('DB_PASS')) define('DB_PASS', _env('DB_PASS'));

// --- Upload Directory ---
if (!defined('UPLOAD_DIR')) define('UPLOAD_DIR', __DIR__ . '/uploads/');

// --- Application Settings ---
if (!defined('SITE_NAME')) define('SITE_NAME', _env('SITE_NAME', 'JDM Kenya'));
if (!defined('SITE_URL'))  define('SITE_URL',  _env('SITE_URL',  'https://jdmkenya.com'));

// --- Email (From Address) ---
if (!defined('MAIL_FROM_EMAIL')) define('MAIL_FROM_EMAIL', _env('MAIL_FROM_EMAIL', 'noreply@jdmkenya.com'));
if (!defined('MAIL_FROM_NAME'))  define('MAIL_FROM_NAME',  _env('MAIL_FROM_NAME',  'JDM Kenya'));

// --- SMTP Settings ---
if (!defined('SMTP_HOST'))     define('SMTP_HOST',     _env('SMTP_HOST'));
if (!defined('SMTP_PORT'))     define('SMTP_PORT',     (int)(_env('SMTP_PORT', '465')));
if (!defined('SMTP_SECURE'))   define('SMTP_SECURE',   _env('SMTP_SECURE',   'ssl'));
if (!defined('SMTP_USERNAME')) define('SMTP_USERNAME', _env('SMTP_USERNAME'));
if (!defined('SMTP_PASSWORD')) define('SMTP_PASSWORD', _env('SMTP_PASSWORD'));
if (!defined('SMTP_TIMEOUT'))  define('SMTP_TIMEOUT',  (int)(_env('SMTP_TIMEOUT', '15')));
