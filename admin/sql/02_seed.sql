-- ============================================================
--  LinkBio — Dados iniciais (seed)
--  Execute APÓS o 01_schema.sql
--  Senhas em bcrypt (custo 12):
--    marcus.lopes → *.Admin14!
--    paty         → paty2026
--    marcos       → marcos2026
-- ============================================================

INSERT IGNORE INTO `users` (`username`, `password_hash`, `role`, `page_slug`, `name`) VALUES

-- Perfil root (administrador)
(
    'marcus.lopes',
    '$2a$12$DmrbMIUzvUUd9AqKvxolS.OGq7yV2TzgpB/1Cwb8q3FW2pb2qgtCy',
    'root',
    NULL,
    'Marcus Lopes'
),

-- Cliente: Paty Silva
(
    'paty',
    '$2y$12$Kw4OZQb7xMvL9nPq3Rs5uO8tYkGhJdXfVaNeIcWmBiA1eS6TpHjD.',
    'client',
    'paty',
    'Paty Silva'
),

-- Cliente: Marcos Bléa
(
    'marcos',
    '$2y$12$Lx5PABc8yNwM0oQr4St6vP9uZlHiKeYgWbOfJdXnCjB2fT7UqIkE.',
    'client',
    'marcosblea',
    'Marcos Bléa'
);

-- ATENÇÃO: os hashes dos clientes acima são placeholders.
-- Use o setup.php para inserção com hash gerado corretamente pelo PHP,
-- ou gere os hashes em: https://bcrypt-generator.com (cost=12)
-- e substitua os valores acima.
