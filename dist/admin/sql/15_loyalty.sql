-- ============================================================
--  LinkBio — Cartão fidelidade (Priscila Ramos)
--  Banco: u179630068_linkbio_bd
--  Execute uma vez no phpMyAdmin
--
--  Painel: https://priscilaramos.linkbio.api.br/painel/
--  Consulta da cliente: https://priscilaramos.linkbio.api.br/fidelidade/
--  Login: usuário com page_slug = priscilaramos (ou root)
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '-03:00';

CREATE TABLE IF NOT EXISTS `loyalty_clients` (
    `id`                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `page_slug`         VARCHAR(50)     NOT NULL,
    `name`              VARCHAR(120)    NOT NULL,
    `phone`             VARCHAR(20)     NOT NULL COMMENT 'DDI+DDD+número, só dígitos',
    `stamps_count`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `rewards_earned`    INT UNSIGNED    NOT NULL DEFAULT 0,
    `reward_available`  TINYINT(1)      NOT NULL DEFAULT 0,
    `reward_expires_at` DATETIME        DEFAULT NULL,
    `notes`             VARCHAR(255)    DEFAULT NULL,
    `created_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_loyalty_slug_phone` (`page_slug`, `phone`),
    KEY `idx_loyalty_slug_name` (`page_slug`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `loyalty_stamps` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `client_id`  INT UNSIGNED NOT NULL,
    `kind`       ENUM('stamp','reward','undo') NOT NULL DEFAULT 'stamp',
    `service`    VARCHAR(80)  DEFAULT NULL,
    `created_by` INT          DEFAULT NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_loyalty_stamps_client` (`client_id`),
    CONSTRAINT `fk_loyalty_stamps_client`
        FOREIGN KEY (`client_id`) REFERENCES `loyalty_clients` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_loyalty_stamps_user`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
