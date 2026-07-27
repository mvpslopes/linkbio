<?php
declare(strict_types=1);

const RAQUEL_BA_MAX_BYTES = 5 * 1024 * 1024;

function raquel_antes_depois_dir(): string
{
    $base = dirname(__DIR__, 3) . '/antes-depois';
    if (!is_dir($base)) {
        mkdir($base, 0755, true);
    }
    return $base;
}

function raquel_ba_allowed_mimes(): array
{
    return [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'gif'  => 'image/gif',
    ];
}

/** Caminho relativo público válido: antes-depois/arquivo.ext */
function raquel_ba_normalize_path(?string $path): ?string
{
    if (!$path) {
        return null;
    }
    $path = str_replace('\\', '/', $path);
    if (!preg_match('#^antes-depois/[a-zA-Z0-9._-]+$#', $path)) {
        return null;
    }
    return $path;
}

function raquel_ba_public_url(?string $path): ?string
{
    $path = raquel_ba_normalize_path($path);
    return $path ? '/' . $path : null;
}

/**
 * @param array<string, mixed> $file $_FILES['image']
 */
function raquel_ba_store_image(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Selecione uma imagem de antes e depois.');
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Falha no envio da imagem. Tente outra foto.');
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Arquivo de imagem inválido.');
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > RAQUEL_BA_MAX_BYTES) {
        throw new RuntimeException('A imagem deve ter no máximo 5 MB.');
    }

    $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    $allowed = raquel_ba_allowed_mimes();
    if (!isset($allowed[$ext])) {
        throw new RuntimeException('Formato não permitido. Use JPG, PNG, WEBP ou GIF.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detected = $finfo->file($tmp) ?: '';
    if (!in_array($detected, $allowed, true)) {
        throw new RuntimeException('O arquivo enviado não é uma imagem válida.');
    }

    $stored = 't' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = raquel_antes_depois_dir() . '/' . $stored;
    if (!move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException('Não foi possível salvar a imagem no servidor.');
    }

    return 'antes-depois/' . $stored;
}

function raquel_ba_delete_image_file(?string $path): void
{
    $path = raquel_ba_normalize_path($path);
    if (!$path) {
        return;
    }
    // Não apaga seeds manuais com nomes curtos tipo 01.PNG — só uploads gerados (t...)
    $base = basename($path);
    if (!preg_match('/^t\d{14}_[a-f0-9]+\.(jpe?g|png|webp|gif)$/i', $base)) {
        return;
    }
    $full = dirname(__DIR__, 3) . '/' . $path;
    if (is_file($full)) {
        @unlink($full);
    }
}
