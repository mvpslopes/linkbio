<?php
declare(strict_types=1);

session_name('priscilaramos_fidelidade');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/painel/includes/loyalty.php';

if (!headers_sent()) {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}

function loyalty_lookup_allowed(): bool
{
    $now = time();
    $hits = $_SESSION['loyalty_lookups'] ?? [];
    if (!is_array($hits)) {
        $hits = [];
    }
    $hits = array_values(array_filter($hits, static fn($t) => is_int($t) && $t > $now - 600));
    if (count($hits) >= 8) {
        $_SESSION['loyalty_lookups'] = $hits;
        return false;
    }
    $hits[] = $now;
    $_SESSION['loyalty_lookups'] = $hits;
    return true;
}

$pdo = db();
$tableOk = loyalty_table_ok($pdo);
$error = '';
$client = null;
$looked = false;

if ($tableOk && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $looked = true;
    if (!loyalty_lookup_allowed()) {
        $error = 'Muitas consultas em pouco tempo. Aguarde alguns minutos.';
    } else {
        $phone = loyalty_normalize_phone((string) ($_POST['phone'] ?? ''));
        if (!loyalty_phone_valid($phone)) {
            $error = 'Informe o WhatsApp com DDD, o mesmo usado no atendimento.';
        } else {
            $client = loyalty_find_by_phone($pdo, $phone);
        }
    }
}

$n = $client ? (int) $client['stamps_count'] : 0;
$ready = $client && !empty($client['reward_available']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"/>
  <meta name="theme-color" content="#FBF3EF"/>
  <meta name="robots" content="noindex,nofollow"/>
  <title>Meu cartão fidelidade | Priscila Ramos</title>
  <link rel="canonical" href="https://priscilaramos.linkbio.api.br/fidelidade/"/>
  <link rel="icon" href="/favicon.png"/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    :root {
      --paper: #FBF3EF; --white: #FFFDFB; --rose: #C58C85; --rose-d: #A86F68;
      --gold: #C4A07A; --wine: #6E3340; --wine-d: #522530; --muted: #7A6561;
      --line: rgba(197,140,133,.22); --font: 'Outfit', system-ui, sans-serif;
      --display: 'Cormorant Garamond', Georgia, serif;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: var(--font); background: var(--paper); color: #2E2422;
      min-height: 100vh; padding: 28px 20px 48px;
      padding-top: max(28px, env(safe-area-inset-top));
    }
    .wrap { width: min(440px, 100%); margin: 0 auto; }
    .logo { height: 36px; width: auto; margin: 0 auto 1.4rem; display: block; }
    h1 { font-family: var(--display); font-size: 2rem; font-weight: 600; color: var(--wine-d); text-align: center; line-height: 1.15; }
    .lede { text-align: center; color: var(--muted); font-size: .92rem; margin: .6rem 0 1.5rem; line-height: 1.6; }
    .card {
      background: var(--white); border: 1px solid var(--line); border-radius: 22px;
      padding: 1.3rem 1.2rem; box-shadow: 0 16px 40px rgba(110,51,64,.08);
    }
    label { display: block; font-size: .75rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: var(--muted); margin-bottom: 6px; }
    input {
      width: 100%; min-height: 50px; border: 1px solid var(--line); background: var(--paper);
      border-radius: 12px; padding: 12px 14px; font-size: 1rem; margin-bottom: 12px;
    }
    input:focus { outline: none; border-color: var(--rose); box-shadow: 0 0 0 3px rgba(197,140,133,.2); background: #fff; }
    button, .btn {
      display: flex; align-items: center; justify-content: center;
      width: 100%; min-height: 50px; border: none; border-radius: 12px;
      background: var(--wine); color: #fff; font-weight: 600; font-size: .95rem; cursor: pointer;
    }
    .visual {
      background: linear-gradient(165deg, #7A3A48 0%, #522530 100%);
      color: #fff; border-radius: 24px; padding: 1.5rem 1.2rem; margin-bottom: 16px;
    }
    .visual p.k { font-size: .68rem; letter-spacing: .18em; text-transform: uppercase; opacity: .75; }
    .visual h2 { font-family: var(--display); font-size: 1.7rem; margin: .2rem 0 .35rem; }
    .visual .sub { opacity: .8; font-size: .88rem; margin-bottom: 1rem; }
    .dots { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; }
    .dots span { aspect-ratio: 1; border-radius: 50%; border: 2px solid rgba(255,255,255,.35); }
    .dots span.on { background: #F3D9CE; border-color: #F3D9CE; }
    .err { background: #fdecee; color: #9B2C3A; border-radius: 14px; padding: 12px 14px; margin-bottom: 14px; font-size: .88rem; }
    .empty { text-align: center; color: var(--muted); font-size: .92rem; line-height: 1.6; }
    .back { display: block; text-align: center; margin-top: 18px; color: var(--rose-d); font-weight: 600; font-size: .88rem; text-decoration: none; }
  </style>
</head>
<body>
  <div class="wrap">
    <a href="/"><img class="logo" src="/logo/logo-nav.png" alt="Priscila Ramos"/></a>
    <h1>Seu cartão fidelidade</h1>
    <p class="lede">A cada 10 atendimentos express, o próximo é por nossa conta. Informe o WhatsApp usado no agendamento.</p>

    <?php if (!$tableOk): ?>
      <p class="empty">O cartão digital entra no ar em breve.</p>
    <?php else: ?>
      <?php if ($error): ?><div class="err"><?= loyalty_h($error) ?></div><?php endif; ?>

      <?php if ($client): ?>
        <div class="visual">
          <p class="k">Cartão digital</p>
          <h2><?= loyalty_h((string) $client['name']) ?></h2>
          <p class="sub">
            <?php if ($ready): ?>
              Você ganhou um atendimento express. Combine o resgate no WhatsApp.
            <?php else: ?>
              <?= $n ?>/10 selos · faltam <?= (int) loyalty_remaining($client) ?>
            <?php endif; ?>
          </p>
          <div class="dots" aria-label="<?= $n ?> de 10 selos">
            <?php for ($i = 1; $i <= 10; $i++): ?>
              <span class="<?= $i <= $n ? 'on' : '' ?>"></span>
            <?php endfor; ?>
          </div>
        </div>
        <a class="btn" href="/">Voltar ao site</a>
      <?php else: ?>
        <?php if ($looked && !$error): ?>
          <p class="empty" style="margin-bottom:16px">Não encontramos um cartão com este WhatsApp. Se você já veio ao estúdio, peça para a Priscila cadastrar seu selo.</p>
        <?php endif; ?>
        <form class="card" method="POST">
          <label for="phone">WhatsApp</label>
          <input type="tel" id="phone" name="phone" required inputmode="tel" autocomplete="tel" placeholder="47 99999-0000" value="<?= loyalty_h((string) ($_POST['phone'] ?? '')) ?>"/>
          <button type="submit">Ver meu cartão</button>
        </form>
      <?php endif; ?>
    <?php endif; ?>

    <a class="back" href="/">← Priscila Ramos</a>
  </div>
</body>
</html>
