-- ============================================================
--  LinkBio — Queries úteis para consulta manual
-- ============================================================

-- ── Ver todos os usuários ───────────────────────────────────
SELECT id, username, role, page_slug, name, created_at
FROM users
ORDER BY role, name;

-- ── Total de visitas por página ─────────────────────────────
SELECT page_slug, COUNT(*) AS total_visitas
FROM page_views
GROUP BY page_slug
ORDER BY total_visitas DESC;

-- ── Visitas por dia (últimos 30 dias) ───────────────────────
SELECT
    page_slug,
    DATE(created_at) AS dia,
    COUNT(*)         AS visitas
FROM page_views
WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY page_slug, dia
ORDER BY dia DESC, page_slug;

-- ── Visitas de hoje ─────────────────────────────────────────
SELECT page_slug, COUNT(*) AS visitas_hoje
FROM page_views
WHERE DATE(created_at) = CURDATE()
GROUP BY page_slug;

-- ── Top 10 cliques por página ───────────────────────────────
SELECT
    page_slug,
    element_text,
    element_type,
    COUNT(*) AS total_cliques
FROM click_events
GROUP BY page_slug, element_text, element_type
ORDER BY total_cliques DESC
LIMIT 10;

-- ── Distribuição por dispositivo ────────────────────────────
SELECT
    page_slug,
    device,
    COUNT(*) AS total,
    ROUND(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER (PARTITION BY page_slug), 1) AS percentual
FROM page_views
GROUP BY page_slug, device
ORDER BY page_slug, total DESC;

-- ── Atividade recente (visitas + cliques) ───────────────────
SELECT 'visita' AS tipo, page_slug, device AS detalhe, created_at
FROM page_views
UNION ALL
SELECT 'clique', page_slug, element_text, created_at
FROM click_events
ORDER BY created_at DESC
LIMIT 50;

-- ── Adicionar novo cliente ──────────────────────────────────
-- Substitua os valores antes de executar:
-- INSERT INTO users (username, password_hash, role, page_slug, name)
-- VALUES ('novo_usuario', '$2y$12$...hash...', 'client', 'slug-da-pagina', 'Nome do Cliente');

-- ── Resetar senha de um usuário ────────────────────────────
-- Gere o hash em https://bcrypt-generator.com (cost=12) e substitua:
-- UPDATE users SET password_hash = '$2y$12$...novo_hash...' WHERE username = 'paty';
