<?php
/**
 * LinkBio — Importar novos usuários no banco
 *
 * 1. Edite o array $novosUsuarios abaixo
 * 2. Execute:
 *      • Navegador: https://linkbio.api.br/admin/importar_novos_usuarios.php
 *      • Terminal:  php admin/importar_novos_usuarios.php
 * 3. Apague este arquivo após usar (segurança)
 *
 * Regras:
 *   - page_slug deve ser IGUAL ao data-slug do tracker na página HTML
 *   - username: login do painel (sem espaços)
 *   - role: 'client' para clientes | 'root' apenas para administrador
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';

// ── Cadastre os novos clientes aqui ───────────────────────────
$novosUsuarios = [
    [
        'username'  => 'cyntiaalmeida',
        'password'  => 'cyntiaalmeida2026',   // senha inicial — altere depois no painel
        'role'      => 'client',
        'page_slug' => 'cyntiaalmeida',       // = data-slug em cyntiaalmeida/index.html
        'name'      => 'Dr.ª Cyntia Almeida',
    ],
    [
        'username'  => 'priscilaramos',
        'password'  => 'priscilaramos2026',
        'role'      => 'client',
        'page_slug' => 'priscilaramos',
        'name'      => 'Priscila Ramos',
    ],

    // Exemplo para mais clientes (descomente e ajuste):
    // [
    //     'username'  => 'giuliadias',
    //     'password'  => 'giuliadias2026',
    //     'role'      => 'client',
    //     'page_slug' => 'giuliadias',
    //     'name'      => 'Giulia Dias',
    // ],
];

// ── Importação ────────────────────────────────────────────────
$log = [];
$sqlGerado = [];

try {
    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO users (username, password_hash, role, page_slug, name) VALUES (?,?,?,?,?)'
    );

    foreach ($novosUsuarios as $u) {
        $username  = trim($u['username'] ?? '');
        $password  = $u['password'] ?? '';
        $role      = ($u['role'] ?? 'client') === 'root' ? 'root' : 'client';
        $page_slug = trim($u['page_slug'] ?? '') ?: null;
        $name      = trim($u['name'] ?? '') ?: null;

        if ($username === '' || $password === '') {
            $log[] = ['type' => 'error', 'msg' => "Ignorado: username/senha vazios."];
            continue;
        }
        if (strlen($password) < 6) {
            $log[] = ['type' => 'error', 'msg' => "❌ {$username}: senha com menos de 6 caracteres."];
            continue;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt->execute([$username, $hash, $role, $page_slug, $name]);

        if ($stmt->rowCount() > 0) {
            $log[] = ['type' => 'ok', 'msg' => "✅ Criado: <b>{$username}</b> — slug <code>{$page_slug}</code> — {$name}"];
        } else {
            $log[] = ['type' => 'skip', 'msg' => "⏭️ Já existia (ignorado): <b>{$username}</b>"];
        }

        $hashEsc = addslashes($hash);
        $slugSql = $page_slug !== null ? "'" . addslashes($page_slug) . "'" : 'NULL';
        $nameSql = $name !== null ? "'" . addslashes($name) . "'" : 'NULL';
        $sqlGerado[] = "INSERT IGNORE INTO `users` (`username`, `password_hash`, `role`, `page_slug`, `name`) VALUES\n"
            . "('{$username}', '{$hashEsc}', '{$role}', {$slugSql}, {$nameSql});";
    }

    $log[] = ['type' => 'ok', 'msg' => '<br><strong style="color:#4ade80">Importação concluída.</strong> Apague <code>importar_novos_usuarios.php</code> do servidor.'];
} catch (Throwable $e) {
    $log[] = ['type' => 'error', 'msg' => '❌ Erro: ' . htmlspecialchars($e->getMessage())];
}

$isCli = PHP_SAPI === 'cli';
if ($isCli) {
    foreach ($log as $entry) {
        echo strip_tags($entry['msg']) . PHP_EOL;
    }
    if ($sqlGerado) {
        echo PHP_EOL . "-- SQL gerado (backup / phpMyAdmin):" . PHP_EOL;
        echo implode(PHP_EOL . PHP_EOL, $sqlGerado) . PHP_EOL;
    }
    exit(0);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Importar usuários — LinkBio</title>
  <style>
    body { background:#0a0a0a; color:#e2e8f0; font-family:system-ui,sans-serif; padding:2rem; max-width:720px; margin:0 auto; line-height:1.6; }
    h1 { font-size:1.25rem; margin-bottom:1rem; }
    .log p { margin:.5rem 0; padding:.75rem 1rem; border-radius:8px; background:#181818; font-size:14px; }
    .log p.error { border-left:3px solid #f87171; }
  pre { background:#111; padding:1rem; border-radius:8px; overflow:auto; font-size:12px; margin-top:1.5rem; white-space:pre-wrap; word-break:break-all; }
  code { background:#222; padding:2px 6px; border-radius:4px; font-size:13px; }
  </style>
</head>
<body>
  <h1>Importar novos usuários</h1>
  <div class="log">
    <?php foreach ($log as $entry): ?>
      <p class="<?= htmlspecialchars($entry['type']) ?>"><?= $entry['msg'] ?></p>
    <?php endforeach; ?>
  </div>
  <?php if ($sqlGerado): ?>
    <p style="font-size:13px;color:#94a3b8;margin-top:1.5rem;">SQL gerado (copie para backup ou phpMyAdmin):</p>
    <pre><?= htmlspecialchars(implode("\n\n", $sqlGerado)) ?></pre>
  <?php endif; ?>
</body>
</html>
