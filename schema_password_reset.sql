-- =========================================================================
-- JDM Kenya - Password Reset & Recovery System Schema Migration
-- =========================================================================
-- This migration adds secure password recovery token infrastructure
-- to the users table, allowing users to safely reset forgotten passwords.
--
-- Security Considerations:
-- 1. Tokens are 32-byte (256-bit) cryptographic values (bin2hex = 64 char hex string)
-- 2. Tokens are hashed before storage in the database
-- 3. Tokens expire after 15 minutes (token_expires_at timestamp)
-- 4. Tokens are cleared after successful password reset (prevents reuse)
-- 5. Only one active token per user at any given time
-- =========================================================================

ALTER TABLE users 
ADD COLUMN reset_token VARCHAR(64) NULL DEFAULT NULL COMMENT 'SHA-256 hash of password recovery token (64 hex chars)',
ADD COLUMN token_expires_at DATETIME NULL DEFAULT NULL COMMENT 'Token expiration time (UTC). Token becomes invalid after this timestamp.';

-- Optional: Create index on reset_token for faster lookups during validation
CREATE INDEX idx_users_reset_token ON users (reset_token);

-- =========================================================================
-- OPTIONAL: Maintenance Query - Delete expired tokens regularly
-- Run this periodically (e.g., via cron job) to clean up expired recovery attempts
-- =========================================================================
-- DELETE FROM users 
-- WHERE reset_token IS NOT NULL 
-- AND token_expires_at < NOW();
