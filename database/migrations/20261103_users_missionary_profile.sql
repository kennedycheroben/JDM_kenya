-- JDM Kenya Migration: Missionary Profile Fields
-- Ensures missionary_type and country columns exist on the users table

ALTER TABLE users 
  ADD COLUMN missionary_type VARCHAR(100) NULL DEFAULT NULL AFTER contribution_phone;

ALTER TABLE users 
  ADD COLUMN country VARCHAR(100) NULL DEFAULT NULL AFTER missionary_type;
