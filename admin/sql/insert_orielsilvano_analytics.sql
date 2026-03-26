-- ============================================================
--  LinkBio — Registrar página "orielsilvano" no painel de analytics
--
--  O tracker da página deve usar:
--    <script src="https://linkbio.api.br/tracker.js" data-slug="orielsilvano"></script>
--
--  O campo `page_slug` em `users` deve ser IGUAL ao data-slug.
--  Visitas e cliques são gravados automaticamente em `page_views` e
--  `click_events` quando houver tráfego.
--
--  Execute no MySQL / phpMyAdmin no banco do LinkBio.
-- ============================================================

SET NAMES utf8mb4;

-- Cadastro do cliente no painel (login + filtro por slug nos relatórios)
-- Se o usuário já existir, o INSERT é ignorado.
INSERT IGNORE INTO `users` (`username`, `password_hash`, `role`, `page_slug`, `name`) VALUES (
    'orielsilvano',
    '$2y$12$XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXPLACEHOLDERHASHXXXXXX',
    'client',
    'orielsilvano',
    'Oriel Silvano'
);

-- Se precisar corrigir nome/slug de um usuário já existente:
-- UPDATE `users`
-- SET `name` = 'Oriel Silvano', `page_slug` = 'orielsilvano'
-- WHERE `username` = 'orielsilvano';

-- Substitua `password_hash` por um bcrypt válido (custo 12), por exemplo:
--   • Painel Admin -> Usuários -> criar/editar usuário
--   • https://bcrypt-generator.com
--   • PHP: php -r "echo password_hash('SUA_SENHA', PASSWORD_BCRYPT);"
