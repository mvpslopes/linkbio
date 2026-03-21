-- ============================================================
--  LinkBio — Registrar página "topmarchador" no painel de analytics
--
--  O relatório agrupa visitas e cliques pelo campo `page_slug`, que
--  deve ser IGUAL ao usado no tracker da página:
--    <script src="https://linkbio.api.br/tracker.js" data-slug="topmarchador"></script>
--
--  Não é necessário inserir linhas em `page_views` ou `click_events`
--  manualmente: elas são criadas automaticamente quando há tráfego.
--
--  Execute no MySQL / phpMyAdmin (após o 01_schema.sql).
-- ============================================================

SET NAMES utf8mb4;

-- Cadastro do cliente no painel (login + filtro por slug nos relatórios)
-- Se o usuário "topmarchador" já existir, este INSERT é ignorado (INSERT IGNORE).
INSERT IGNORE INTO `users` (`username`, `password_hash`, `role`, `page_slug`, `name`) VALUES (
    'topmarchador',
    '$2y$12$XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXPLACEHOLDERHASHXXXXXX',
    'client',
    'topmarchador',
    'Top Marchador'
);

-- Se você PRECISA atualizar nome/slug de um registro já existente:
-- UPDATE `users` SET `name` = 'Top Marchador', `page_slug` = 'topmarchador'
-- WHERE `username` = 'topmarchador';

-- Substitua o password_hash por um bcrypt válido (custo 12), por exemplo gerado em:
--   https://bcrypt-generator.com
-- ou pelo setup interno do admin.
