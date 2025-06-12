CREATE DATABASE IF NOT EXISTS lost_and_found_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lost_and_found_db;

-- Users Table
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('common', 'admin', 'superAdmin') NOT NULL DEFAULT 'common'
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
    `user_id` INT NULL,  -- << MODIFICADO para permitir NULL
    `status` ENUM('Pendente', 'Devolvido', 'Doado') NOT NULL DEFAULT 'Pendente',
    `registered_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FOREIGN KEY (location_id) REFERENCES locations(id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL  -- << MODIFICADO para ON DELETE SET NULL
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

-- Insert Default Admin User
INSERT INTO `users` (`username`, `password`, `role`) VALUES ('admin', '\$2y\$10\$DHK9TkhqOrEiZfAl8mqEVeBHUoUt7xDSy.ocHCrYud6.kbYOjJeyK', 'superAdmin');

-- Populate Categories (Caracteres Corrigidos)
INSERT INTO `categories` (`name`, `code`) VALUES
('Roupa', 'ROP'),
('Medicamento', 'MED'),
('Acessórios', 'ACS'),   -- Corrigido
('Eletrônicos', 'ELE'),  -- Corrigido
('Documentos', 'DOC'),
('Outros', 'OUT');

-- Populate Example Locations (Caracteres Corrigidos)
INSERT INTO `locations` (`name`) VALUES
('Teatro Paulo Autran'),
('Auditório'),
('Comedoria');