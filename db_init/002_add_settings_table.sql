-- Settings table
CREATE TABLE IF NOT EXISTS `settings` (
    `config_id` INT PRIMARY KEY DEFAULT 1, -- Fixed ID for the single row of settings
    `unidade_nome` VARCHAR(255) DEFAULT NULL,
    `cnpj` VARCHAR(20) DEFAULT NULL,
    `endereco_rua` VARCHAR(255) DEFAULT NULL,
    `endereco_numero` VARCHAR(50) DEFAULT NULL,
    `endereco_bairro` VARCHAR(100) DEFAULT NULL,
    `endereco_cidade` VARCHAR(100) DEFAULT NULL,
    `endereco_estado` VARCHAR(50) DEFAULT NULL,
    `endereco_cep` VARCHAR(10) DEFAULT NULL,
    `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT check_single_row CHECK (config_id = 1) -- Ensures only config_id = 1 can be inserted
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Insert the initial settings row if it doesn't exist.
-- The application will then only perform UPDATE operations on this row.
INSERT IGNORE INTO `settings` (`config_id`) VALUES (1);
