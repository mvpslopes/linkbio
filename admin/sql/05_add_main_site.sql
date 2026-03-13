-- ============================================================
--  LinkBio — Adiciona o site principal ao painel de analytics
--  Execute via phpMyAdmin no banco u179630068_linkbio_bd
-- ============================================================

INSERT INTO `users` (username, password_hash, role, page_slug, name)
VALUES (
  'linkbio.main',
  '',
  'client',
  'linkbio',
  'Site Principal'
);
