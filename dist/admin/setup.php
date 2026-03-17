<?php
/**
 * Setup inicial — execute UMA VEZ e depois delete este arquivo.
 * Acesse: https://linkbio.api.br/admin/setup.php
 */
require_once __DIR__ . '/includes/db.php';

$pdo = db();
$log = [];

try {
    // ── Tabela de usuários ──────────────────────────────────────────
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            username     VARCHAR(50) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role         ENUM('root','client') NOT NULL DEFAULT 'client',
            page_slug    VARCHAR(50) DEFAULT NULL,
            name         VARCHAR(100) DEFAULT NULL,
            created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    $log[] = '✅ Tabela <b>users</b> criada (ou já existia).';

    // ── Tabela de visualizações de página ───────────────────────────
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS page_views (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            page_slug  VARCHAR(50) NOT NULL,
            ip_hash    VARCHAR(64) DEFAULT NULL,
            referrer   VARCHAR(500) DEFAULT NULL,
            device     VARCHAR(20) DEFAULT NULL,
            country    VARCHAR(50) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_slug (page_slug),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    $log[] = '✅ Tabela <b>page_views</b> criada (ou já existia).';

    // ── Tabela de eventos de clique ─────────────────────────────────
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS click_events (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            page_slug    VARCHAR(50) NOT NULL,
            element_text VARCHAR(200) DEFAULT NULL,
            element_type VARCHAR(50) DEFAULT NULL,
            target_url   VARCHAR(500) DEFAULT NULL,
            created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_slug (page_slug),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    $log[] = '✅ Tabela <b>click_events</b> criada (ou já existia).';

    // ── Inserir usuários ────────────────────────────────────────────
    $users = [
        [
            'username'  => 'marcus.lopes',
            'hash'      => '$2a$12$DmrbMIUzvUUd9AqKvxolS.OGq7yV2TzgpB/1Cwb8q3FW2pb2qgtCy',
            'role'      => 'root',
            'page_slug' => null,
            'name'      => 'Marcus Lopes',
        ],
        [
            'username'  => 'paty',
            'hash'      => password_hash('paty2026', PASSWORD_BCRYPT),
            'role'      => 'client',
            'page_slug' => 'paty',
            'name'      => 'Paty Silva',
        ],
        [
            'username'  => 'marcos',
            'hash'      => password_hash('marcos2026', PASSWORD_BCRYPT),
            'role'      => 'client',
            'page_slug' => 'marcosblea',
            'name'      => 'Marcos Bléa',
        ],
    ];

    $stmt = $pdo->prepare("
        INSERT IGNORE INTO users (username, password_hash, role, page_slug, name)
        VALUES (:username, :hash, :role, :page_slug, :name)
    ");

    foreach ($users as $u) {
        $stmt->execute($u);
        $log[] = "✅ Usuário <b>{$u['username']}</b> ({$u['role']}) inserido (ou já existia).";
    }

    $log[] = '<br><strong style="color:#4ade80">Setup concluído! Delete este arquivo agora.</strong>';

} catch (Exception $e) {
    $log[] = '❌ Erro: ' . htmlspecialchars($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <title>Setup – LinkBio Admin</title>
  <style>
    body { background:#0a0a0a; color:#e2e8f0; font-family:monospace; padding:2rem; }
    h1   { color:#C9A84C; margin-bottom:1.5rem; }
    li   { margin:.4rem 0; line-height:1.6; }
    .warn{ color:#fbbf24; margin-top:1.5rem; font-size:.85rem; }
  </style>
</head>
<body>
  <h1>⚙️ LinkBio — Setup</h1>
  <ul>
    <?php foreach ($log as $line): ?>
      <li><?= $line ?></li>
    <?php endforeach; ?>
  </ul>
  <p class="warn">⚠️ <strong>DELETE este arquivo (setup.php) imediatamente após o setup!</strong></p>
</body>
</html>
