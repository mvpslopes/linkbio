<?php
/**
 * LinkBio — Validação das tabelas do banco
 * Acesso: apenas root. URL: /admin/validate_db.php
 */
require_once __DIR__ . '/includes/auth.php';
$user = require_root();
$pdo  = db();

header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: DENY');

$expected = [
    'users' => [
        'id', 'username', 'password_hash', 'role', 'page_slug', 'name', 'created_at',
    ],
    'page_views' => [
        'id', 'page_slug', 'ip_hash', 'referrer', 'device',
        'browser', 'os', 'country', 'city',  // migration v2
        'created_at',
    ],
    'click_events' => [
        'id', 'page_slug', 'element_text', 'element_type', 'target_url', 'created_at',
    ],
];

function getTableColumns(PDO $pdo, string $table): array {
    $stmt = $pdo->query("SHOW COLUMNS FROM `" . preg_replace('/[^a-z0-9_]/', '', $table) . "`");
    return $stmt ? array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field') : [];
}

function getRowCount(PDO $pdo, string $table): int {
    $t = preg_replace('/[^a-z0-9_]/', '', $table);
    $stmt = $pdo->query("SELECT COUNT(*) FROM `$t`");
    return (int) ($stmt ? $stmt->fetchColumn() : 0);
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Validar banco — LinkBio</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet"/>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>body{font-family:Inter,sans-serif;background:#0f172a;color:#e2e8f0}</style>
</head>
<body class="p-6 max-w-3xl mx-auto">

  <div class="flex items-center justify-between mb-6">
    <h1 class="text-xl font-bold text-white">Validação do banco de dados</h1>
    <a href="/admin/dashboard.php" class="text-sm text-slate-400 hover:text-white">← Voltar ao painel</a>
  </div>

  <?php
  $allOk = true;
  foreach ($expected as $table => $columns) {
      $exists = in_array($table, array_column($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM), 0), true);
      if (!$exists) {
          echo "<div class='rounded-xl border border-red-500/40 bg-red-500/10 p-4 mb-4'>";
          echo "<p class='font-semibold text-red-400'>Tabela <code class='bg-black/30 px-1 rounded'>$table</code> não existe.</p>";
          echo "<p class='text-sm text-slate-400 mt-1'>Execute o script <code>admin/sql/01_schema.sql</code> no phpMyAdmin.</p>";
          echo "</div>";
          $allOk = false;
          continue;
      }

      $actual = getTableColumns($pdo, $table);
      $missing = array_diff($columns, $actual);
      $count  = getRowCount($pdo, $table);

      if (!empty($missing)) {
          $allOk = false;
          echo "<div class='rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 mb-4'>";
          echo "<p class='font-semibold text-amber-400'>Tabela <code class='bg-black/30 px-1 rounded'>$table</code></p>";
          echo "<p class='text-sm text-slate-400 mt-1'>Colunas faltando: <code class='text-amber-300'>" . implode(', ', $missing) . "</code></p>";
          echo "<p class='text-xs text-slate-500 mt-2'>Adicione com o script <code>admin/sql/04_migration_v2.sql</code> (para page_views).</p>";
          echo "<p class='text-sm text-slate-400 mt-2'>Registros atuais: <strong class='text-white'>$count</strong></p>";
          echo "</div>";
      } else {
          echo "<div class='rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 mb-4'>";
          echo "<p class='font-semibold text-emerald-400'>Tabela <code class='bg-black/30 px-1 rounded'>$table</code> — OK</p>";
          echo "<p class='text-sm text-slate-400 mt-1'>Colunas esperadas presentes. Registros: <strong class='text-white'>$count</strong></p>";
          echo "</div>";
      }
  }
  ?>

  <?php if ($allOk): ?>
  <div class="rounded-xl border border-slate-600 bg-slate-800/50 p-4 mt-4">
    <p class="text-slate-300 text-sm">Todas as tabelas e colunas estão corretas. O painel de analytics pode usar o banco normalmente.</p>
  </div>
  <?php endif; ?>

  <div class="mt-6 pt-4 border-t border-slate-700">
    <p class="text-xs text-slate-500">Banco: <?= htmlspecialchars(DB_NAME) ?> · Usuário: <?= htmlspecialchars($user['username']) ?></p>
  </div>

</body>
</html>
