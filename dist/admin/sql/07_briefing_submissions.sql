-- Briefings enviados via criar.linkbio.api.br
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `briefing_submissions` (
    `id`                  INT          NOT NULL AUTO_INCREMENT,
    `nome`                VARCHAR(255) DEFAULT NULL,
    `email`               VARCHAR(255) DEFAULT NULL,
    `telefone`            VARCHAR(100) DEFAULT NULL,
    `subdominio_desejado` VARCHAR(80)  DEFAULT NULL,
    `origem`              VARCHAR(500) DEFAULT NULL,
    `payload_json`        JSON         NOT NULL,
    `created_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_created` (`created_at`),
    KEY `idx_subdominio` (`subdominio_desejado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
