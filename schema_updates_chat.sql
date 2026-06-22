-- Add status tracking for WhatsApp-like ticks
ALTER TABLE messages
MODIFY COLUMN status ENUM('sent', 'delivered', 'read') DEFAULT 'sent';

-- Add exact timestamps for read receipts
ALTER TABLE messages
ADD COLUMN delivered_at TIMESTAMP NULL DEFAULT NULL,
ADD COLUMN read_at TIMESTAMP NULL DEFAULT NULL;

-- High-performance composite index for real-time status fetching and updates
CREATE INDEX idx_messages_sender_receiver_status ON messages(sender_id, receiver_id, status);
