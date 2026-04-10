-- ============================================================
--  LinkBio — Cadastro cliente Sheila Domingues (nutrisheiladomingues)
--
--  Tracker no site (deve coincidir com page_slug):
--    data-slug="nutrisheiladomingues"
--
--  Credenciais MySQL da aplicação (PHP): admin/includes/db.php
--    DB_HOST, DB_NAME, DB_USER, DB_PASS
--
--  Senha de LOGIN NO PAINEL (este usuário client), após executar o script:
--    usuário: nutrisheiladomingues
--    senha:   NutriSheila2026!
--  (troque em Admin → Usuários ou por UPDATE no password_hash)
--
--  Executar no mesmo banco usado pelo site (ex.: phpMyAdmin → SQL).
-- ============================================================

SET NAMES utf8mb4;

INSERT IGNORE INTO `users` (`username`, `password_hash`, `role`, `page_slug`, `name`) VALUES (
    'nutrisheiladomingues',
    '$2y$12$/Wwf8kTXN72lIbNyphpi0ujgqrKC8kR5H1SxVgNcpmINn5gNfeEDa',
    'client',
    'nutrisheiladomingues',
    'Sheila Domingues'
);

-- Se o usuário já existir e precisar só atualizar nome/slug/senha:
-- UPDATE `users`
-- SET `name` = 'Sheila Domingues',
--     `page_slug` = 'nutrisheiladomingues',
--     `password_hash` = '$2y$12$/Wwf8kTXN72lIbNyphpi0ujgqrKC8kR5H1SxVgNcpmINn5gNfeEDa',
--     `role` = 'client'
-- WHERE `username` = 'nutrisheiladomingues';
