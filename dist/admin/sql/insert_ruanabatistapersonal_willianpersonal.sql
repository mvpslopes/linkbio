-- ============================================================
--  LinkBio — Cadastro Ruana Batista Personal e Willian Personal
--
--  Executar no banco do painel (ex.: phpMyAdmin -> SQL).
--  Obs.: substitua os hashes placeholders abaixo antes de executar.
-- ============================================================

SET NAMES utf8mb4;

INSERT IGNORE INTO `users` (`username`, `password_hash`, `role`, `page_slug`, `name`) VALUES
(
    'ruanabatistapersonal',
    '$2y$12$XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXPLACEHOLDERHASHXXXXXX',
    'client',
    'ruanabatistapersonal',
    'Ruana Batista Personal'
),
(
    'willianpersonal',
    '$2y$12$XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXPLACEHOLDERHASHXXXXXX',
    'client',
    'willianpersonal',
    'Willian Personal'
);

-- Como gerar hash bcrypt (cost=12):
-- 1) Via PHP:
--    <?php echo password_hash('SUA_SENHA_AQUI', PASSWORD_BCRYPT, ['cost' => 12]); ?>
--
-- 2) Ou use o setup.php do painel para cadastrar com hash automático.
