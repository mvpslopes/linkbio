<?php

const GENEALOGY_PHOTO_MAX_BYTES = 5 * 1024 * 1024;

function genealogy_fotos_dir(): string
{
    $base = dirname(__DIR__, 2) . '/fotos';
    if (!is_dir($base)) {
        mkdir($base, 0755, true);
    }
    return $base;
}

function genealogy_photo_allowed_mimes(): array
{
    return [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'gif'  => 'image/gif',
    ];
}

function genealogy_photo_url(?string $path): ?string
{
    if (!$path) {
        return null;
    }
    $path = str_replace('\\', '/', $path);
    if (!preg_match('#^fotos/[a-zA-Z0-9._-]+$#', $path)) {
        return null;
    }
    return '/' . $path;
}

/**
 * @param array<string, mixed> $file $_FILES['photo']
 */
function genealogy_store_photo(int $personId, array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Falha no envio da foto. Tente outra imagem.');
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Arquivo de foto inválido.');
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > GENEALOGY_PHOTO_MAX_BYTES) {
        throw new RuntimeException('A foto deve ter no máximo 5 MB.');
    }

    $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    $allowed = genealogy_photo_allowed_mimes();
    if (!isset($allowed[$ext])) {
        throw new RuntimeException('Formato não permitido. Use JPG, PNG, WEBP ou GIF.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detected = $finfo->file($tmp) ?: '';
    if (!in_array($detected, $allowed, true)) {
        throw new RuntimeException('O arquivo enviado não é uma imagem válida.');
    }

    $stored = 'p' . $personId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dest = genealogy_fotos_dir() . '/' . $stored;
    if (!move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException('Não foi possível salvar a foto no servidor.');
    }

    return 'fotos/' . $stored;
}

function genealogy_delete_photo_file(?string $path): void
{
    if (!$path || !preg_match('#^fotos/[a-zA-Z0-9._-]+$#', str_replace('\\', '/', $path))) {
        return;
    }
    $full = dirname(__DIR__, 2) . '/' . str_replace('\\', '/', $path);
    if (is_file($full)) {
        @unlink($full);
    }
}

function genealogy_set_person_photo(PDO $pdo, int $personId, ?string $newPath): void
{
    $st = $pdo->prepare('SELECT photo_path FROM genealogy_people WHERE id = ?');
    $st->execute([$personId]);
    $old = $st->fetchColumn();
    if ($old && $old !== $newPath) {
        genealogy_delete_photo_file((string) $old);
    }
    $pdo->prepare('UPDATE genealogy_people SET photo_path = ? WHERE id = ?')
        ->execute([$newPath, $personId]);
}

function genealogy_remove_person_photo(PDO $pdo, int $personId): void
{
    $st = $pdo->prepare('SELECT photo_path FROM genealogy_people WHERE id = ?');
    $st->execute([$personId]);
    $old = $st->fetchColumn();
    if ($old) {
        genealogy_delete_photo_file((string) $old);
    }
    $pdo->prepare('UPDATE genealogy_people SET photo_path = NULL WHERE id = ?')->execute([$personId]);
}
