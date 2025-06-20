CREATE DATABASE IF NOT EXISTS lost_and_found_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lost_and_found_db;
SET NAMES 'utf8mb4';

-- Users Table
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('common', 'admin', 'admin-aprovador', 'superAdmin') NOT NULL DEFAULT 'common'
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Categories Table
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL UNIQUE,
    `code` VARCHAR(10) NOT NULL UNIQUE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Locations Table
CREATE TABLE IF NOT EXISTS `locations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL UNIQUE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Items Table
CREATE TABLE IF NOT EXISTS `items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `category_id` INT NOT NULL,
    `location_id` INT NOT NULL,
    `found_date` DATE NOT NULL,
    `description` TEXT NULL,
    `barcode` VARCHAR(255) NULL UNIQUE,
    `user_id` INT NULL,
    `status` ENUM('Pendente', 'Aguardando Aprovação', 'Devolvido', 'Doado') NOT NULL DEFAULT 'Pendente',
    `registered_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FOREIGN KEY (location_id) REFERENCES locations(id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Devolution Documents Table
CREATE TABLE IF NOT EXISTS `devolution_documents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `item_id` INT NOT NULL,
    `returned_by_user_id` INT NOT NULL,
    `devolution_timestamp` DATETIME NOT NULL,
    `owner_name` VARCHAR(255) NOT NULL,
    `owner_address` TEXT NULL,
    `owner_phone` VARCHAR(50) NULL,
    `owner_credential_number` VARCHAR(100) NULL,
    `signature_image_path` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`item_id`) REFERENCES `items`(`id`),
    FOREIGN KEY (`returned_by_user_id`) REFERENCES `users`(`id`)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Settings Table
CREATE TABLE IF NOT EXISTS `settings` (
    `config_id` INT PRIMARY KEY DEFAULT 1,
    `unidade_nome` VARCHAR(255) DEFAULT NULL,
    `cnpj` VARCHAR(20) DEFAULT NULL,
    `endereco_rua` VARCHAR(255) DEFAULT NULL,
    `endereco_numero` VARCHAR(50) DEFAULT NULL,
    `endereco_bairro` VARCHAR(100) DEFAULT NULL,
    `endereco_cidade` VARCHAR(100) DEFAULT NULL,
    `endereco_estado` VARCHAR(50) DEFAULT NULL,
    `endereco_cep` VARCHAR(10) DEFAULT NULL,
    `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT check_single_row CHECK (config_id = 1)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Donation Terms Table
CREATE TABLE IF NOT EXISTS `donation_terms` (
    `term_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `responsible_donation` VARCHAR(255) NOT NULL,
    `donation_date` DATE NOT NULL,
    `donation_time` TIME NOT NULL,
    `institution_name` VARCHAR(255) NOT NULL,
    `institution_cnpj` VARCHAR(20) NULL,
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
    `reproval_reason` TEXT NULL DEFAULT NULL COMMENT 'Reason why the donation term was reproved',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Donation Term Items Table
CREATE TABLE IF NOT EXISTS `donation_term_items` (
    `term_item_id` INT AUTO_INCREMENT PRIMARY KEY,
    `term_id` INT NOT NULL,
    `item_id` INT NOT NULL,
    FOREIGN KEY (`term_id`) REFERENCES `donation_terms`(`term_id`) ON DELETE CASCADE,
    FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE RESTRICT
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ENSURE items.status ENUM is updated if table already exists
ALTER TABLE `items` MODIFY COLUMN `status` ENUM('Pendente', 'Aguardando Aprovação', 'Devolvido', 'Doado') NOT NULL DEFAULT 'Pendente';

-- Insert Default Admin User
INSERT INTO `users` (`username`, `password`, `role`) VALUES 
('admin', '$2y$10$DHK9TkhqOrEiZfAl8mqEVeBHUoUt7xDSy.ocHCrYud6.kbYOjJeyK', 'superAdmin');

-- Insert Initial Settings Row
INSERT IGNORE INTO `settings` (`config_id`) VALUES (1);

-- Populate Categories
INSERT INTO `categories` (`name`, `code`) VALUES
('Roupa', 'ROP'),
('Medicamento', 'MED'),
('Acessórios', 'ACS'),
('Eletrônicos', 'ELE'),
('Documentos', 'DOC'),
('Outros', 'OUT');

-- Populate Locations
INSERT INTO `locations` (`name`) VALUES
('Teatro Paulo Autran'),
('Auditório'),
('Espaço de Brincar');
