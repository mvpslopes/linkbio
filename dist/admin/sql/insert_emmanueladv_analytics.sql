-- ============================================================
--  LinkBio — Registrar página "emmanueladv" no painel
--
--  Tracker na página:
--    <script src="https://linkbio.api.br/tracker.js" data-slug="emmanueladv"></script>
--
--  O campo `page_slug` em `users` deve ser IGUAL ao data-slug.
--  Execute no MySQL / phpMyAdmin no banco: u179630068_linkbio_bd
--
--  Login inicial:
--    URL:      https://linkbio.api.br/admin/login.php
--    usuário:  emmanueladv
--    senha:    emmanueladv2026
-- ============================================================

SET NAMES utf8mb4;

INSERT IGNORE INTO `users` (`username`, `password_hash`, `role`, `page_slug`, `name`) VALUES (
    'emmanueladv',
    '$2y$12$cFXQ2s/KeYiuBOZTmJ3l2O5J7uyjUHp5T/0l0UNBxaqcYzGTfiwbW',
    'client',
    'emmanueladv',
    'Emmanuel Valle — Advogado'
);

-- Gerar novo hash: php -r "echo password_hash('SUA_SENHA', PASSWORD_BCRYPT, ['cost'=>12]);"

-- Corrigir se o usuário já existir:
-- UPDATE `users`
-- SET `name` = 'Emmanuel Valle — Advogado',
--     `page_slug` = 'emmanueladv',
--     `password_hash` = '$2y$12$cFXQ2s/KeYiuBOZTmJ3l2O5J7uyjUHp5T/0l0UNBxaqcYzGTfiwbW',
--     `role` = 'client'
-- WHERE `username` = 'emmanueladv';
