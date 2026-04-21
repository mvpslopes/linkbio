-- ============================================================
--  LinkBio — Cadastro Aliancce, Master Agro e Haras Pariz
--
--  Trackers nas páginas (data-slug):
--    aliancce, masteragro, haraspariz
--
--  Executar no banco do painel (ex.: phpMyAdmin → SQL).
-- ============================================================

SET NAMES utf8mb4;

INSERT IGNORE INTO `users` (`username`, `password_hash`, `role`, `page_slug`, `name`) VALUES
(
    'aliancce',
    '$2y$12$mUOCJ6HqEbsU.eBvM41umOgqBKI9NaICIigMnsSiyqe847HdXZI7C',
    'client',
    'aliancce',
    'Aliancce — Gestão de Eventos Equestres'
),
(
    'masteragro',
    '$2y$12$gS0hOVn/N5uxBxFQ9.rqdO.A83u1WFmB7ijrjZ8UklpNq86C40Lb.',
    'client',
    'masteragro',
    'Master Agro — Tecnologia Equestre'
),
(
    'haraspariz',
    '$2y$12$UcjkM5cEcbIou8mHFW0Csen8OyosTtoVW9NHEB3UATWl43TaG03YC',
    'client',
    'haraspariz',
    'Haras Pariz — Mangalarga Marchador'
);

-- Credenciais iniciais do painel (troque após o primeiro acesso):
--   aliancce    → senha: Aliancce2026!
--   masteragro  → senha: MasterAgro2026!
--   haraspariz  → senha: HarasPariz2026!
