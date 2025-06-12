-- Alter items table to add 'Aguardando Aprovação' to status ENUM
ALTER TABLE `items`
MODIFY COLUMN `status` ENUM('Pendente', 'Aguardando Aprovação', 'Devolvido', 'Doado') NOT NULL DEFAULT 'Pendente';

-- Create donation_terms table
CREATE TABLE IF NOT EXISTS `donation_terms` (
    `term_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL, -- User who created the term (can be NULL if system generated or if user is deleted)
    `responsible_donation` VARCHAR(255) NOT NULL,
    `donation_date` DATE NOT NULL,
    `donation_time` TIME NOT NULL,
    `institution_name` VARCHAR(255) NOT NULL,
    `institution_cnpj` VARCHAR(20) NULL, -- CNPJ might be optional for some institutions
    `institution_ie` VARCHAR(50) NULL,
    `institution_responsible_name` VARCHAR(255) NOT NULL,
    `institution_phone` VARCHAR(30) NULL,
    `institution_address_street` VARCHAR(255) NULL,
    `institution_address_number` VARCHAR(30) NULL,
    `institution_address_bairro` VARCHAR(100) NULL,
    `institution_address_cidade` VARCHAR(100) NULL,
    `institution_address_estado` VARCHAR(2) NULL,
    `institution_address_cep` VARCHAR(15) NULL,
    `signature_image_path` VARCHAR(255) NOT NULL,
    `status` VARCHAR(30) NOT NULL COMMENT 'e.g., Aguardando Aprovação, Doado, Declinado',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create donation_term_items table
CREATE TABLE IF NOT EXISTS `donation_term_items` (
    `term_item_id` INT AUTO_INCREMENT PRIMARY KEY,
    `term_id` INT NOT NULL,
    `item_id` INT NOT NULL,
    FOREIGN KEY (`term_id`) REFERENCES `donation_terms`(`term_id`) ON DELETE CASCADE,
    FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE RESTRICT -- Prevent item deletion if part of a term
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
