-- Relatórios comportamentais — painel Thayna Freire
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `thayna_relatorios` (
    `id`                  INT          NOT NULL AUTO_INCREMENT,
    `codigo_caso`         VARCHAR(32)  NOT NULL,
    `cliente_nome`        VARCHAR(255) NOT NULL,
    `data_analise`        DATE         DEFAULT NULL,
    `periodo_inicio`      DATE         DEFAULT NULL,
    `periodo_termino`     DATE         DEFAULT NULL,
    `payload_json`        JSON         NOT NULL,
    `created_by`          INT          DEFAULT NULL,
    `created_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_codigo_caso` (`codigo_caso`),
    KEY `idx_cliente` (`cliente_nome`),
    KEY `idx_data_analise` (`data_analise`),
    KEY `idx_created` (`created_at`),
    CONSTRAINT `fk_thayna_relatorios_user`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
