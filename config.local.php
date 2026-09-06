<?php
// Local Development Database Overrides
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'jdm_kenya'); // Your local database name in phpMyAdmin
if (!defined('DB_USER')) define('DB_USER', 'root');      // Default XAMPP/LAMPP MySQL user
if (!defined('DB_PASS')) define('DB_PASS', '');          // Default XAMPP/LAMPP has no password

// JDM OAuth Local Configuration
putenv('JDM_OAUTH_ENABLED=true');
putenv('JDM_OAUTH_ISSUER=http://127.0.0.1/JDM_kenya');
putenv('JDM_OAUTH_PRIVATE_KEY_PATH=/home/cheroben/jdm-oauth-keys/private.key');
putenv('JDM_OAUTH_PUBLIC_KEY_PATH=/home/cheroben/jdm-oauth-keys/public.key');
putenv('JDM_OAUTH_ENCRYPTION_KEY=4fFCFHDqU3A9RiknaVgKoBXbCNrheLMHr53Z+Yr4Cxk=');
putenv('JDM_OAUTH_AUDIT_SALT=sjJUBq+ZGnmQE5HhQ0L5q+MNQXP1/r7ozKLfjhfVzAc=');
putenv('JDM_OAUTH_ALLOW_INSECURE_LOCAL=true');
putenv('JDM_SESSION_COOKIE=jdm_kenya_session');