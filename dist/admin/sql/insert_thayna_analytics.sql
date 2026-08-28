-- ============================================================
--  LinkBio — Cadastro cliente Thayna Freire (thayna)
--
--  Tracker na página:
--    <script src="https://linkbio.api.br/tracker.js" data-slug="thayna"></script>
--
--  RECOMENDADO: use o script PHP (gera o hash da senha automaticamente):
--    php admin/importar_novos_usuarios.php
--
--  Execute no MySQL / phpMyAdmin no banco: u179630068_linkbio_bd
-- ============================================================

SET NAMES utf8mb4;

INSERT IGNORE INTO `users` (`username`, `password_hash`, `role`, `page_slug`, `name`) VALUES (
    'thayna',
    '$2y$12$SUBSTITUA_PELO_HASH_BCRYPT_GERADO',
    'client',
    'thayna',
    'Thayna Freire'
);

-- UPDATE `users`
-- SET `name` = 'Thayna Freire', `page_slug` = 'thayna'
-- WHERE `username` = 'thayna';
