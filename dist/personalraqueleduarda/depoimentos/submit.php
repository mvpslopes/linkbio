<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . base_url() . '/');
    exit;
}

$token = $_POST['csrf'] ?? '';
if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
    flash_set('error', 'Formulário expirado. Tente novamente.');
    header('Location: ' . base_url() . '/');
    exit;
}

$user = current_user();
$slug = (string) cfg('page_slug', 'personalraqueleduarda');
$rating = (int) ($_POST['rating'] ?? 5);
$comment = trim((string) ($_POST['comment'] ?? ''));

if ($rating < 1 || $rating > 5) {
    flash_set('error', 'Selecione de 1 a 5 estrelas.');
    header('Location: ' . base_url() . '/');
    exit;
}
if (mb_strlen($comment) < 10 || mb_strlen($comment) > 800) {
    flash_set('error', 'O comentário deve ter entre 10 e 800 caracteres.');
    header('Location: ' . base_url() . '/');
    exit;
}

try {
    $maxPerDay = (int) cfg('max_comments_per_day', 2);
    $lim = db()->prepare(
        'SELECT COUNT(*) FROM testimonials
         WHERE page_slug = ? AND google_sub = ? AND created_at >= (NOW() - INTERVAL 1 DAY)'
    );
    $lim->execute([$slug, $user['sub']]);
    if ((int) $lim->fetchColumn() >= $maxPerDay) {
        flash_set('error', 'Você já publicou o máximo de depoimentos por hoje.');
        header('Location: ' . base_url() . '/');
        exit;
    }

    $ins = db()->prepare(
        'INSERT INTO testimonials
          (page_slug, google_sub, name, email, photo_url, rating, comment, approved)
         VALUES (?,?,?,?,?,?,?,1)'
    );
    $ins->execute([
        $slug,
        $user['sub'],
        $user['name'],
        $user['email'] ?? null,
        $user['picture'] ?? null,
        $rating,
        $comment,
    ]);

    flash_set('ok', 'Depoimento publicado! Ele já aparece no site.');
    header('Location: ' . rtrim((string) cfg('main_site_url', '/'), '/') . '/#depoimentos');
    exit;
} catch (Throwable $e) {
    flash_set('error', 'Não foi possível salvar. Confirme se a tabela testimonials existe no banco.');
    header('Location: ' . base_url() . '/');
    exit;
}
