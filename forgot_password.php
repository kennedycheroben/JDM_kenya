<?php
/**
 * Password Recovery Root Shim
 * Location: /forgot_password.php (Project Root)
 * 
 * This is a clean "router" file that keeps the root directory simple
 * while delegating the actual application logic to the modules directory.
 * 
 * Purpose: Users bookmark/link to /forgot_password.php which is clean and memorable.
 * The actual implementation details remain organized in /modules/auth/
 */

require_once __DIR__ . '/modules/auth/forgot_password_view.php';
