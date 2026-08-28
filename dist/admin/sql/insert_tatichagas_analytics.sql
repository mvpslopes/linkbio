-- ============================================================
--  LinkBio — Registrar página "tatichagas" no painel de analytics
--
--  Tracker na página:
--    <script src="https://linkbio.api.br/tracker.js" data-slug="tatichagas"></script>
--
--  O campo `page_slug` em `users` deve ser IGUAL ao data-slug.
--
--  RECOMENDADO: use o script PHP (gera o hash da senha automaticamente):
--    php admin/importar_novos_usuarios.php
--    ou acesse /admin/importar_novos_usuarios.php no navegador
--
--  Execute no MySQL / phpMyAdmin no banco: u179630068_linkbio_bd
-- ============================================================

SET NAMES utf8mb4;

-- Opção A — com hash gerado (substitua o hash antes de executar):
-- Gere com: php -r "echo password_hash('tatichagas2026', PASSWORD_BCRYPT, ['cost'=>12]);"

INSERT IGNORE INTO `users` (`username`, `password_hash`, `role`, `page_slug`, `name`) VALUES (
    'tatichagas',
    '$2y$12$SUBSTITUA_PELO_HASH_BCRYPT_GERADO',
    'client',
    'tatichagas',
    'Tatiana Chagas'
);

-- Opção B — se o usuário já existir, apenas corrigir slug/nome:
-- UPDATE `users`
-- SET `name` = 'Tatiana Chagas', `page_slug` = 'tatichagas'
-- WHERE `username` = 'tatichagas';

-- Redefinir senha depois (hash novo no password_hash):
-- UPDATE `users` SET `password_hash` = '$2y$12$...' WHERE `username` = 'tatichagas';
