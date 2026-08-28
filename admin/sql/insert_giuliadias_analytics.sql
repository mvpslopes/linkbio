-- ============================================================
--  LinkBio — Registrar página "giuliadias" no painel
--
--  Tracker na página:
--    <script src="https://linkbio.api.br/tracker.js" data-slug="giuliadias"></script>
--
--  O campo `page_slug` em `users` deve ser IGUAL ao data-slug.
--  Execute no MySQL / phpMyAdmin no banco: u179630068_linkbio_bd
--
--  Login inicial:
--    URL:      https://linkbio.api.br/admin/login.php
--    usuário:  giuliadias
--    senha:    giuliadias2026
-- ============================================================

SET NAMES utf8mb4;

INSERT IGNORE INTO `users` (`username`, `password_hash`, `role`, `page_slug`, `name`) VALUES (
    'giuliadias',
    '$2y$12$6sG9jLC4rINOySLXrIkVj.u.JoGl4bBHNi83kKhtQW7YIxYdnpXR2',
    'client',
    'giuliadias',
    'Giulia Dias — Revendedora Multimarcas'
);

-- Gerar novo hash: php -r "echo password_hash('SUA_SENHA', PASSWORD_BCRYPT, ['cost'=>12]);"

-- Corrigir se o usuário já existir:
-- UPDATE `users`
-- SET `name` = 'Giulia Dias — Revendedora Multimarcas',
--     `page_slug` = 'giuliadias',
--     `password_hash` = '$2y$12$6sG9jLC4rINOySLXrIkVj.u.JoGl4bBHNi83kKhtQW7YIxYdnpXR2',
--     `role` = 'client'
-- WHERE `username` = 'giuliadias';
