<?php
/**
 * JDM Kenya — Central Configuration
 *
 * Credentials are loaded from the .env file in the project root via phpdotenv.
 * Never hardcode secrets in this file.
 * Copy .env.example → .env and fill in your values.
 *
 * On the live server: upload .env manually (it is gitignored) and run
 *   php composer.phar install --no-dev
 * in public_html, OR set PHP env vars via cPanel → Software → PHP Configuration.
 */

// Load .env via vlucas/phpdotenv only if the library is installed.
// safeLoad() is used so a missing .env does not throw — it just skips.
$_jdm_autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($_jdm_autoload)) {
    require_once $_jdm_autoload;
    if (class_exists('Dotenv\\Dotenv')) {
        Dotenv\Dotenv::createImmutable(__DIR__)->safeLoad();
    }
}
unset($_jdm_autoload);

/**
 * Read a value from $_ENV (phpdotenv) → getenv() (system/cPanel) → default.
 * Using a prefixed name to avoid conflicts with any global function called _env().
 */
if (!function_exists('jdm_env')) {
    function jdm_env(string $key, string $default = ''): string {
        $val = $_ENV[$key] ?? getenv($key);
        return ($val !== false && $val !== null && $val !== '') ? (string)$val : $default;
    }
}

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
