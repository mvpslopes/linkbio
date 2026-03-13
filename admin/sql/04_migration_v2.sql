-- ============================================================
--  LinkBio — Migration v2
--  Execute cada bloco separadamente no phpMyAdmin.
--  Se aparecer "Duplicate column", a coluna já existe — pule ela.
-- ============================================================

-- 1. Navegador do visitante
ALTER TABLE `page_views` ADD COLUMN `browser` VARCHAR(50) DEFAULT NULL AFTER `device`;

-- 2. Sistema operacional
ALTER TABLE `page_views` ADD COLUMN `os` VARCHAR(50) DEFAULT NULL AFTER `browser`;

-- 3. País (pode já existir — pule se der erro)
ALTER TABLE `page_views` ADD COLUMN `country` VARCHAR(100) DEFAULT NULL AFTER `os`;

-- 4. Cidade (pode já existir — pule se der erro)
ALTER TABLE `page_views` ADD COLUMN `city` VARCHAR(100) DEFAULT NULL AFTER `country`;
