-- Inscrições do formulário "Planeje seu espaço" (ex.: cristianoladeira)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `planeje_submissions` (
    `id`           INT          NOT NULL AUTO_INCREMENT,
    `page_slug`    VARCHAR(50)  NOT NULL,
    `nome`         VARCHAR(255) DEFAULT NULL,
    `email`        VARCHAR(255) DEFAULT NULL,
    `telefone`     VARCHAR(100) DEFAULT NULL,
    `origem`       VARCHAR(500) DEFAULT NULL,
    `payload_json` JSON         NOT NULL,
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_slug_created` (`page_slug`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
