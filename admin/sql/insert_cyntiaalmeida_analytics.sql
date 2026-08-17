-- ============================================================
--  LinkBio — Cadastro cliente Dr.ª Cyntia Almeida (cyntiaalmeida)
--
--  Tracker no site (deve coincidir com page_slug):
--    <script src="https://linkbio.api.br/tracker.js" data-slug="cyntiaalmeida"></script>
--
--  Credenciais de LOGIN NO PAINEL, após executar o script:
--    usuário: cyntiaalmeida
--    senha:   cyntiaalmeida2026
--  (troque em Admin → Usuários ou por UPDATE no password_hash)
--
--  Execute no MySQL / phpMyAdmin no banco: u179630068_linkbio_bd
-- ============================================================

SET NAMES utf8mb4;

INSERT IGNORE INTO `users` (`username`, `password_hash`, `role`, `page_slug`, `name`) VALUES (
    'cyntiaalmeida',
    '$2y$12$GdQZ6arZi.a9miL7M/sHYelDX4d5zv4nnuO0BAQ2Q.GVtQkRiZVIq',
    'client',
    'cyntiaalmeida',
    'Dr.ª Cyntia Almeida'
);

-- Se o usuário já existir e precisar só atualizar nome/slug/senha:
-- UPDATE `users`
-- SET `name` = 'Dr.ª Cyntia Almeida',
--     `page_slug` = 'cyntiaalmeida',
--     `password_hash` = '$2y$12$GdQZ6arZi.a9miL7M/sHYelDX4d5zv4nnuO0BAQ2Q.GVtQkRiZVIq',
--     `role` = 'client'
-- WHERE `username` = 'cyntiaalmeida';
