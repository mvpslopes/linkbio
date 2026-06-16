-- Clientes e termos de aceite — painel Thayna Freire
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `thayna_clientes` (
    `id`                 INT          NOT NULL AUTO_INCREMENT,
    `nome_completo`      VARCHAR(255) NOT NULL,
    `idade`              SMALLINT     DEFAULT NULL,
    `whatsapp`           VARCHAR(40)  DEFAULT NULL,
    `instagram`          VARCHAR(120) DEFAULT NULL,
    `cidade_estado`      VARCHAR(120) DEFAULT NULL,
    `observacoes`        TEXT         DEFAULT NULL,
    `token`              VARCHAR(64)  NOT NULL,
    `questionario_json`  JSON         DEFAULT NULL,
    `assinatura_nome`    VARCHAR(255) DEFAULT NULL,
    `assinado_em`        DATETIME     DEFAULT NULL,
    `assinatura_ip_hash` VARCHAR(64)  DEFAULT NULL,
    `created_by`         INT          DEFAULT NULL,
    `created_at`         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_token` (`token`),
    KEY `idx_nome` (`nome_completo`),
    KEY `idx_assinado` (`assinado_em`),
    CONSTRAINT `fk_thayna_clientes_user`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vincular relatórios ao cadastro de clientes (ignore erro se coluna já existir)
ALTER TABLE `thayna_relatorios`
    ADD COLUMN `cliente_id` INT DEFAULT NULL AFTER `cliente_nome`;

ALTER TABLE `thayna_relatorios`
    ADD KEY `idx_thayna_rel_cliente` (`cliente_id`);

ALTER TABLE `thayna_relatorios`
    ADD CONSTRAINT `fk_thayna_relatorio_cliente`
        FOREIGN KEY (`cliente_id`) REFERENCES `thayna_clientes` (`id`) ON DELETE SET NULL;
