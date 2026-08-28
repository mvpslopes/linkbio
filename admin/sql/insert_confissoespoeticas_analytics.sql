-- ============================================================
--  LinkBio — Registrar página "confissoespoeticas" no painel
--
--  Tracker na página:
--    <script src="https://linkbio.api.br/tracker.js" data-slug="confissoespoeticas"></script>
--
--  RECOMENDADO: usar importar_novos_usuarios.php para gerar o hash.
--  Execute no banco: u179630068_linkbio_bd
-- ============================================================

SET NAMES utf8mb4;

INSERT IGNORE INTO `users` (`username`, `password_hash`, `role`, `page_slug`, `name`) VALUES (
    'confissoespoeticas',
    '$2y$12$SUBSTITUA_PELO_HASH_BCRYPT_GERADO',
    'client',
    'confissoespoeticas',
    'Vitória Silva – Confissões Poéticas'
);

-- Gerar hash: php -r "echo password_hash('confissoes2026', PASSWORD_BCRYPT, ['cost'=>12]);"

-- Corrigir se o usuário já existir:
-- UPDATE `users`
-- SET `name` = 'Vitória Silva – Confissões Poéticas', `page_slug` = 'confissoespoeticas'
-- WHERE `username` = 'confissoespoeticas';
