-- ============================================================
--  LinkBio — Árvore Genealógica (subdomínio arvoregeneaologica)
--  Execute uma vez no phpMyAdmin
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '-03:00';

CREATE TABLE IF NOT EXISTS `genealogy_people` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `full_name`       VARCHAR(200) NOT NULL,
    `birth_date`      DATE         DEFAULT NULL,
    `death_date`      DATE         DEFAULT NULL,
    `birth_year_only` SMALLINT UNSIGNED DEFAULT NULL COMMENT 'quando só se sabe o ano',
    `gender`          ENUM('M','F','O') DEFAULT NULL,
    `notes`           TEXT         DEFAULT NULL,
    `photo_path`      VARCHAR(255) DEFAULT NULL,
    `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_name` (`full_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `genealogy_relations` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `person_id`     INT UNSIGNED NOT NULL,
    `related_id`    INT UNSIGNED NOT NULL,
    `relation_type` ENUM('father','mother','spouse','child') NOT NULL,
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_relation` (`person_id`, `related_id`, `relation_type`),
    KEY `idx_person` (`person_id`),
    KEY `idx_related` (`related_id`),
    CONSTRAINT `fk_genealogy_rel_person` FOREIGN KEY (`person_id`) REFERENCES `genealogy_people` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_genealogy_rel_related` FOREIGN KEY (`related_id`) REFERENCES `genealogy_people` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crie um usuário no painel Admin → Usuários com slug: arvoregeneaologica
-- Ou use um usuário root existente para acessar /painel/
