-- Add is_donation_approver column to users table
ALTER TABLE `users`
ADD COLUMN `is_donation_approver` BOOLEAN NOT NULL DEFAULT FALSE
COMMENT 'Flag to indicate if the user can approve donations';
