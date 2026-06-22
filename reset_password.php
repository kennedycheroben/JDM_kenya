<?php
/**
 * Password Reset Root Shim
 * Location: /reset_password.php (Project Root)
 * 
 * This is a clean "router" file that keeps the root directory simple
 * while delegating the actual application logic to the modules directory.
 * 
 * Purpose: Users receive recovery links pointing to /reset_password.php?token=...
 * The actual implementation details remain organized in /modules/auth/
 */

require_once __DIR__ . '/modules/auth/reset_password_view.php';
