-- ============================================================
--  LinkBio — Registrar página "personalraqueleduarda" no painel
--
--  Tracker na página:
--    <script src="https://linkbio.api.br/tracker.js" data-slug="personalraqueleduarda"></script>
--
--  O campo `page_slug` em `users` deve ser IGUAL ao data-slug.
--  Execute no MySQL / phpMyAdmin no banco: u179630068_linkbio_bd
--
--  Login inicial:
--    usuário: personalraqueleduarda
--    senha:   personalraquel2026
-- ============================================================

SET NAMES utf8mb4;

INSERT IGNORE INTO `users` (`username`, `password_hash`, `role`, `page_slug`, `name`) VALUES (
    'personalraqueleduarda',
    '$2y$12$4dnZ8ZvTyBoO8XXUhUWBGeCTor0GAYJT8PpOJ./OsTkjpOV.I27A6',
    'client',
    'personalraqueleduarda',
    'Raquel Eduarda — Personal Trainer'
);

-- Gerar novo hash: php -r "echo password_hash('SUA_SENHA', PASSWORD_BCRYPT, ['cost'=>12]);"

-- Corrigir se o usuário já existir:
-- UPDATE `users`
-- SET `name` = 'Raquel Eduarda — Personal Trainer', `page_slug` = 'personalraqueleduarda'
-- WHERE `username` = 'personalraqueleduarda';

-- Redefinir senha depois:
-- UPDATE `users` SET `password_hash` = '$2y$12$...' WHERE `username` = 'personalraqueleduarda';
