-- Add privacy level support to prayer_requests table
-- Allows users to choose: public, anonymous, or private (admin only)

ALTER TABLE prayer_requests 
ADD COLUMN privacy_level ENUM('public', 'anonymous', 'private') NOT NULL DEFAULT 'public' AFTER is_private;

-- Migrate existing is_private data to privacy_level
-- is_private = 1 becomes privacy_level = 'private'
-- is_private = 0 becomes privacy_level = 'public'
UPDATE prayer_requests 
SET privacy_level = CASE 
    WHEN is_private = 1 THEN 'private'
    ELSE 'public'
END
WHERE privacy_level = 'public';

-- Add index for performance on privacy level queries
ALTER TABLE prayer_requests 
ADD INDEX idx_prayer_requests_privacy_level (privacy_level);
