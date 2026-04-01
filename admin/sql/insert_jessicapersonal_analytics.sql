-- ============================================================
--  LinkBio — Registrar página "jessicapersonal" no painel de analytics
--
--  O tracker da página usa:
--    <script src="https://linkbio.api.br/tracker.js" data-slug="jessicapersonal"></script>
--
--  O campo `page_slug` em `users` deve ser IGUAL ao data-slug.
--  Execute no MySQL / phpMyAdmin no banco do LinkBio.
-- ============================================================

SET NAMES utf8mb4;

INSERT IGNORE INTO `users` (`username`, `password_hash`, `role`, `page_slug`, `name`) VALUES (
    'jessicapersonal',
    '$2y$12$XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXPLACEHOLDERHASHXXXXXX',
    'client',
    'jessicapersonal',
    'Jéssica Personal'
);

-- UPDATE `users`
-- SET `name` = 'Jéssica Personal', `page_slug` = 'jessicapersonal'
-- WHERE `username` = 'jessicapersonal';
