-- ============================================================
--  LinkBio — Schema do banco de dados
--  Banco: u179630068_linkbio_bd
--  Execute este script uma vez via phpMyAdmin ou MySQL CLI
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '-03:00';

-- ── Tabela de usuários ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
    `id`            INT          NOT NULL AUTO_INCREMENT,
    `username`      VARCHAR(50)  NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role`          ENUM('root','client') NOT NULL DEFAULT 'client',
    `page_slug`     VARCHAR(50)  DEFAULT NULL  COMMENT 'slug da página do cliente (ex: paty)',
    `name`          VARCHAR(100) DEFAULT NULL,
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Tabela de visualizações de página ───────────────────────
CREATE TABLE IF NOT EXISTS `page_views` (
    `id`         INT          NOT NULL AUTO_INCREMENT,
    `page_slug`  VARCHAR(50)  NOT NULL            COMMENT 'identifica qual página foi visitada',
    `ip_hash`    VARCHAR(64)  DEFAULT NULL        COMMENT 'SHA-256 do IP (sem armazenar IP real)',
    `referrer`   VARCHAR(500) DEFAULT NULL        COMMENT 'URL de origem',
    `device`     VARCHAR(20)  DEFAULT NULL        COMMENT 'mobile | desktop | tablet',
    `country`    VARCHAR(50)  DEFAULT NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_slug`    (`page_slug`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Tabela de eventos de clique ─────────────────────────────
CREATE TABLE IF NOT EXISTS `click_events` (
    `id`           INT          NOT NULL AUTO_INCREMENT,
    `page_slug`    VARCHAR(50)  NOT NULL           COMMENT 'identifica a página onde ocorreu o clique',
    `element_text` VARCHAR(200) DEFAULT NULL       COMMENT 'texto visível do elemento clicado',
    `element_type` VARCHAR(50)  DEFAULT NULL       COMMENT 'a | button | etc.',
    `target_url`   VARCHAR(500) DEFAULT NULL       COMMENT 'href do link (quando aplicável)',
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_slug`    (`page_slug`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
