-- ============================================================
--  LinkBio — Registrar página "priscilaramos" no painel
--
--  Tracker na página:
--    <script src="https://linkbio.api.br/tracker.js" data-slug="priscilaramos"></script>
--
--  O campo `page_slug` em `users` deve ser IGUAL ao data-slug.
--  Execute no MySQL / phpMyAdmin no banco: u179630068_linkbio_bd
--
--  Login inicial:
--    URL:      https://priscilaramos.linkbio.api.br/painel/
--    (analytics: https://linkbio.api.br/admin/login.php)
--    usuário:  priscilaramos
--    senha:    priscilaramos2026
--
--  Cartão fidelidade: execute também admin/sql/15_loyalty.sql
-- ============================================================

SET NAMES utf8mb4;

INSERT IGNORE INTO `users` (`username`, `password_hash`, `role`, `page_slug`, `name`) VALUES (
    'priscilaramos',
    '$2y$12$W77Mec0h7GH/hyeJ3l9y1.lA1QWteFu/ARx9EORXu84E6hKdcS1hO',
    'client',
    'priscilaramos',
    'Priscila Ramos – Beleza & Cuidado'
);

-- Gerar novo hash: php -r "echo password_hash('SUA_SENHA', PASSWORD_BCRYPT, ['cost'=>12]);"

-- Corrigir se o usuário já existir:
-- UPDATE `users`
-- SET `name` = 'Priscila Ramos – Beleza & Cuidado',
--     `page_slug` = 'priscilaramos',
--     `password_hash` = '$2y$12$W77Mec0h7GH/hyeJ3l9y1.lA1QWteFu/ARx9EORXu84E6hKdcS1hO',
--     `role` = 'client'
-- WHERE `username` = 'priscilaramos';
