<?php
/**
 * Upload de arquivos do briefing (logo, fotos, etc.)
 */
declare(strict_types=1);

const BRIEFING_UPLOAD_MAX_BYTES = 10 * 1024 * 1024; // 10 MB por arquivo
const BRIEFING_UPLOAD_MAX_FILES = 12;

/** @return array<string, string> ext => mime */
function briefing_allowed_mimes(): array
{
    return [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'svg'  => 'image/svg+xml',
        'pdf'  => 'application/pdf',
        'zip'  => 'application/zip',
    ];
}

function briefing_upload_dir(int $submissionId): string
{
    $base = dirname(__DIR__) . '/uploads/briefing/' . $submissionId;
    if (!is_dir($base)) {
        mkdir($base, 0755, true);
    }

    return $base;
}

/**
 * @param array<string, mixed> $file Entrada de $_FILES['arquivos'] (single ou do loop)
 * @return array{name: string, stored: string, size: int, mime: string}|null
 */
function briefing_store_uploaded_file(int $submissionId, array $file): ?array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return null;
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return null;
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > BRIEFING_UPLOAD_MAX_BYTES) {
        return null;
    }

    $origName = (string) ($file['name'] ?? 'arquivo');
    $origName = basename(str_replace(["\0", '\\', '/'], '', $origName));
    if ($origName === '' || $origName === '.' || $origName === '..') {
        $origName = 'arquivo';
    }

    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    $allowed = briefing_allowed_mimes();
    if (!isset($allowed[$ext])) {
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detected = $finfo->file($tmp) ?: '';
    $okMime = in_array($detected, $allowed, true);
    if ($ext === 'svg' && str_contains($detected, 'svg')) {
        $okMime = true;
        $detected = 'image/svg+xml';
    }
    if (!$okMime) {
        return null;
    }

    $stored = bin2hex(random_bytes(8)) . '.' . $ext;
    $destDir = briefing_upload_dir($submissionId);
    $destPath = $destDir . '/' . $stored;

    if (!move_uploaded_file($tmp, $destPath)) {
        return null;
    }

    return [
        'name'   => $origName,
        'stored' => $stored,
        'size'   => $size,
        'mime'   => $detected,
    ];
}

/**
 * @param array<string, mixed>|null $filesInput $_FILES['arquivos']
 * @return list<array{name: string, stored: string, size: int, mime: string}>
 */
function briefing_process_uploads(int $submissionId, ?array $filesInput): array
{
    if ($filesInput === null) {
        return [];
    }

    $saved = [];
    $count = 0;

    // Input único ou múltiplo
    if (is_array($filesInput['name'] ?? null)) {
        $names = $filesInput['name'];
        $tmpNames = $filesInput['tmp_name'];
        $errors = $filesInput['error'];
        $sizes = $filesInput['size'];
        foreach ($names as $i => $name) {
            if ($count >= BRIEFING_UPLOAD_MAX_FILES) {
                break;
            }
            $one = [
                'name'     => $names[$i] ?? '',
                'type'     => $filesInput['type'][$i] ?? '',
                'tmp_name' => $tmpNames[$i] ?? '',
                'error'    => $errors[$i] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $sizes[$i] ?? 0,
            ];
            if (($one['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $meta = briefing_store_uploaded_file($submissionId, $one);
            if ($meta !== null) {
                $saved[] = $meta;
                $count++;
            }
        }
    } else {
        $meta = briefing_store_uploaded_file($submissionId, $filesInput);
        if ($meta !== null) {
            $saved[] = $meta;
        }
    }

    return $saved;
}

function briefing_delete_submission_files(int $submissionId): void
{
    $dir = dirname(__DIR__) . '/uploads/briefing/' . $submissionId;
    if (!is_dir($dir)) {
        return;
    }
    foreach (glob($dir . '/*') ?: [] as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
    @rmdir($dir);
}
