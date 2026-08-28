-- ============================================================
--  LinkBio — Árvore Genealógica (arvoregeneaologica)
--  Analytics + acesso ao painel
--
--  Banco: u179630068_linkbio_bd
--  Execute no phpMyAdmin (aba SQL)
--
--  Após executar:
--    • Login painel: arvoregeneaologica.linkbio.api.br/painel/login.php
--    • Usuário: arvoregeneaologica
--    • Senha: arvoregeneaologica2026  (altere depois no Admin → Usuários)
--    • Acessos: linkbio.api.br/admin/dashboard.php → página arvoregeneaologica
--
--  Tracker já incluso no index.html:
--    data-slug="arvoregeneaologica"
-- ============================================================

SET NAMES utf8mb4;

-- 1) Criar usuário (ignora se username já existir)
INSERT IGNORE INTO `users` (`username`, `password_hash`, `role`, `page_slug`, `name`) VALUES (
    'arvoregeneaologica',
    '$2y$12$6149dqPrPOAfKRNdxO1yUeVUtz1JMQG8YAW6Pj36sOZ1uBDVPNT7y',
    'client',
    'arvoregeneaologica',
    'Árvore Genealógica'
);

-- 2) Se o usuário já existia, garante slug e nome corretos para analytics
UPDATE `users`
SET `page_slug` = 'arvoregeneaologica',
    `name` = 'Árvore Genealógica'
WHERE `username` = 'arvoregeneaologica';

-- 3) Se você usa OUTRO username no painel, descomente e ajuste:
-- UPDATE `users`
-- SET `page_slug` = 'arvoregeneaologica',
--     `name` = 'Árvore Genealógica'
-- WHERE `username` = 'SEU_USUARIO_AQUI';

-- 4) Redefinir senha para arvoregeneaologica2026 (se o usuário já existia antes):
-- UPDATE `users`
-- SET `password_hash` = '$2y$12$6149dqPrPOAfKRNdxO1yUeVUtz1JMQG8YAW6Pj36sOZ1uBDVPNT7y'
-- WHERE `username` = 'arvoregeneaologica';

-- 5) Conferir
SELECT id, username, role, page_slug, name, created_at
FROM `users`
WHERE `page_slug` = 'arvoregeneaologica';
