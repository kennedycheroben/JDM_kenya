<?php
// Database Configuration - From cPanel -> MySQL Databases
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'xqtrqexj_jdm_kenya');
if (!defined('DB_USER')) define('DB_USER', 'xqtrqexj_jdmkenya');
if (!defined('DB_PASS')) define('DB_PASS', 'j&YsQWp(BM!&su;-');

// Upload Directory
if (!defined('UPLOAD_DIR')) define('UPLOAD_DIR', __DIR__ . '/uploads/');

// Application Settings
if (!defined('SITE_NAME')) define('SITE_NAME', 'JDM Kenya');
if (!defined('SITE_URL')) define('SITE_URL', 'https://jdmkenya.com');

// Email Configurations
if (!defined('MAIL_FROM_EMAIL')) define('MAIL_FROM_EMAIL', 'kennedycheroben@jdmkenya.com');
if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', 'JDM Kenya');

// SMTP Email Settings - cPanel Secure SSL/TLS Parameters
if (!defined('SMTP_HOST')) define('SMTP_HOST', 'mail.jdmkenya.com');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 465);
if (!defined('SMTP_SECURE')) define('SMTP_SECURE', 'ssl'); // tls for port 587, ssl for port 465

if (!defined('SMTP_USERNAME')) define('SMTP_USERNAME', 'kennedycheroben@jdmkenya.com');
if (!defined('SMTP_PASSWORD')) define('SMTP_PASSWORD', 'Bb*9w{WoK2L5PFv{');
if (!defined('SMTP_TIMEOUT')) define('SMTP_TIMEOUT', 15);
?>