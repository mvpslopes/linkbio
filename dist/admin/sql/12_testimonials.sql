-- ============================================================
--  LinkBio — Depoimentos com login Google
--  (Personal Raquel Eduarda e reutilizável por page_slug)
--
--  Execute no phpMyAdmin — banco: u179630068_linkbio_bd
-- ============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `testimonials` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_slug`  VARCHAR(64)  NOT NULL,
  `google_sub` VARCHAR(64)  NOT NULL,
  `name`       VARCHAR(120) NOT NULL,
  `email`      VARCHAR(190) DEFAULT NULL,
  `photo_url`  VARCHAR(500) DEFAULT NULL,
  `rating`     TINYINT UNSIGNED NOT NULL DEFAULT 5,
  `comment`    VARCHAR(800) NOT NULL,
  `approved`   TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_slug_approved` (`page_slug`, `approved`, `created_at`),
  KEY `idx_google_day` (`page_slug`, `google_sub`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
