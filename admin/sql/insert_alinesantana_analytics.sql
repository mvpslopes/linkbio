-- ============================================================
--  LinkBio — Registrar página "alinesantana" no painel de analytics
--
--  O tracker da página usa:
--    <script src="https://linkbio.api.br/tracker.js" data-slug="alinesantana"></script>
--
--  O campo `page_slug` em `users` deve ser IGUAL a esse data-slug.
--  Visitas e cliques são gravados automaticamente em `page_views` e
--  `click_events` quando há tráfego (não é preciso INSERT manual nelas).
--
--  Execute no MySQL / phpMyAdmin no banco do LinkBio (ex.: u179630068_linkbio_bd).
-- ============================================================

SET NAMES utf8mb4;

-- Cadastro do cliente (aparece no dashboard para filtrar relatórios)
-- Se o usuário já existir, o INSERT é ignorado.
INSERT IGNORE INTO `users` (`username`, `password_hash`, `role`, `page_slug`, `name`) VALUES (
    'alinesantana',
    '$2y$12$XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXPLACEHOLDERHASHXXXXXX',
    'client',
    'alinesantana',
    'Aline Santana'
);

-- ── Ajustar registro já existente (se o INSERT foi ignorado mas slug/nome estavam errados)
-- UPDATE `users`
-- SET `name` = 'Aline Santana', `page_slug` = 'alinesantana'
-- WHERE `username` = 'alinesantana';

-- ── Senha: substitua `password_hash` por um bcrypt válido (custo 12), por exemplo:
--   • Painel Admin → Usuários → criar/editar usuário (gera o hash)
--   • https://bcrypt-generator.com  (custo 12)
--   • PHP:  php -r "echo password_hash('SUA_SENHA', PASSWORD_BCRYPT);"
