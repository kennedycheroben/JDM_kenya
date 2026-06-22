-- =========================================================================
-- JDM Kenya - Office Bearer (Partner) Approval System Schema Migration
-- =========================================================================
-- This migration adds approval workflow for users registering as "Partner" 
-- (Office Bearers), requiring JDM Super Admin approval before they can 
-- access the full dashboard and portal features.
--
-- Workflow:
-- 1. User signs up as "Partner" → is_approved defaults to 0 (pending)
-- 2. User login redirects to pending approval page (cannot access dashboard)
-- 3. Super Admin reviews pending partners in dashboard
-- 4. Super Admin approves/rejects partners
-- 5. On approval, is_approved = 1, user can login normally
-- =========================================================================

ALTER TABLE users 
ADD COLUMN is_approved TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Approval status. For partners: 0=pending approval, 1=approved. For others: always 1.';

-- Index for faster queries filtering by approval status
CREATE INDEX idx_users_is_approved ON users (is_approved, category);
