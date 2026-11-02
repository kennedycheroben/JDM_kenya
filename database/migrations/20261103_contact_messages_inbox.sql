-- JDM Kenya Migration: Contact Messages Inbox Management
-- Adds support for archiving and trash (soft-delete/restore) to contact_messages

ALTER TABLE contact_messages 
  MODIFY COLUMN status VARCHAR(30) NOT NULL DEFAULT 'unread';

ALTER TABLE contact_messages 
  ADD COLUMN archived_at TIMESTAMP NULL DEFAULT NULL AFTER read_at;

ALTER TABLE contact_messages 
  ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER archived_at;

ALTER TABLE contact_messages 
  ADD KEY idx_contact_messages_deleted_at (deleted_at);
