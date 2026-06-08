-- Controle de interessados (leads) — painel root
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `interessados_opcoes` (
    `id`         INT          NOT NULL AUTO_INCREMENT,
    `tipo`       ENUM('segmento','status','status_comissao') NOT NULL,
    `valor`      VARCHAR(120) NOT NULL,
    `ordem`      INT          NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_tipo_valor` (`tipo`, `valor`),
    KEY `idx_tipo_ordem` (`tipo`, `ordem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `interessados` (
    `id`               INT            NOT NULL AUTO_INCREMENT,
    `nome_cliente`     VARCHAR(255)   NOT NULL,
    `segmento`         VARCHAR(120)   DEFAULT NULL,
    `contato`          VARCHAR(255)   DEFAULT NULL,
    `status`           VARCHAR(120)   DEFAULT 'Novo',
    `atendente_id`     INT            DEFAULT NULL,
    `comissao`         DECIMAL(10,2)  DEFAULT NULL,
    `status_comissao`  VARCHAR(120)   DEFAULT NULL,
    `observacoes`      TEXT           DEFAULT NULL,
    `created_at`       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`),
    KEY `idx_segmento` (`segmento`),
    KEY `idx_atendente` (`atendente_id`),
    CONSTRAINT `fk_interessados_atendente`
        FOREIGN KEY (`atendente_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `interessados_opcoes` (`tipo`, `valor`, `ordem`) VALUES
('status', 'Novo', 1),
('status', 'Em contato', 2),
('status', 'Negociando', 3),
('status', 'Fechado', 4),
('status', 'Perdido', 5),
('status_comissao', 'Pendente', 1),
('status_comissao', 'Paga', 2),
('status_comissao', 'Cancelada', 3);
