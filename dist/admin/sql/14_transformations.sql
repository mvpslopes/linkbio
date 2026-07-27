-- ============================================================
--  LinkBio — Transformações (antes e depois)
--  Personal Raquel Eduarda
--
--  Execute no phpMyAdmin — banco: u179630068_linkbio_bd
-- ============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `transformations` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_slug`     VARCHAR(64)  NOT NULL,
  `image_path`    VARCHAR(255) NOT NULL,
  `objetivo`      VARCHAR(120) NOT NULL,
  `perfil_label`  VARCHAR(80)  NOT NULL DEFAULT 'Perfil',
  `perfil`        VARCHAR(120) NOT NULL,
  `resultado_em`  VARCHAR(80)  NOT NULL,
  `sort_order`    INT NOT NULL DEFAULT 0,
  `published`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_slug_published_order` (`page_slug`, `published`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `transformations`
  (`page_slug`, `image_path`, `objetivo`, `perfil_label`, `perfil`, `resultado_em`, `sort_order`, `published`)
SELECT 'personalraqueleduarda', 'antes-depois/01.PNG', 'Emagrecimento', 'Perfil da Aluna', 'Lipedema', '4 meses', 1, 1
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `transformations`
  WHERE `page_slug` = 'personalraqueleduarda' AND `image_path` = 'antes-depois/01.PNG'
);

INSERT INTO `transformations`
  (`page_slug`, `image_path`, `objetivo`, `perfil_label`, `perfil`, `resultado_em`, `sort_order`, `published`)
SELECT 'personalraqueleduarda', 'antes-depois/03.PNG', 'Definição muscular', 'Perfil do Aluno', 'Atleta', '2 meses', 2, 1
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `transformations`
  WHERE `page_slug` = 'personalraqueleduarda' AND `image_path` = 'antes-depois/03.PNG'
);

INSERT INTO `transformations`
  (`page_slug`, `image_path`, `objetivo`, `perfil_label`, `perfil`, `resultado_em`, `sort_order`, `published`)
SELECT 'personalraqueleduarda', 'antes-depois/04.PNG', 'Hipertrofia', 'Perfil do Aluno', 'Aluna Iniciante', '4 meses', 3, 1
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `transformations`
  WHERE `page_slug` = 'personalraqueleduarda' AND `image_path` = 'antes-depois/04.PNG'
);
