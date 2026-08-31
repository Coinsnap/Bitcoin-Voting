-- Security Patch for Coinsnap Bitcoin Voting Plugin
-- Database changes required to prevent vote double-counting

-- Add UNIQUE index on payment_id to prevent duplicate webhooks from creating multiple votes
-- This prevents the same Coinsnap invoice from being counted twice
ALTER TABLE wp_voting_payments 
ADD UNIQUE KEY unique_payment_id (payment_id);

-- Optional: Add index on poll_id and created_at for query performance
ALTER TABLE wp_voting_payments 
ADD INDEX idx_poll_id_created (poll_id, created_at);

-- Optional: Add NOT NULL constraint to enforce data integrity
ALTER TABLE wp_voting_payments 
MODIFY payment_id VARCHAR(255) NOT NULL;

ALTER TABLE wp_voting_payments 
MODIFY poll_id VARCHAR(255) NOT NULL;

ALTER TABLE wp_voting_payments 
MODIFY option_id INT(4) NOT NULL;

ALTER TABLE wp_voting_payments 
MODIFY status VARCHAR(50) NOT NULL;

-- Verify changes were applied
SHOW CREATE TABLE wp_voting_payments;
