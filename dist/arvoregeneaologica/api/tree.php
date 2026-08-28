<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

require_once __DIR__ . '/../painel/includes/db.php';
require_once __DIR__ . '/../painel/includes/people.php';

try {
    $pdo = db();
    if (!genealogy_table_ok($pdo)) {
        http_response_code(503);
        echo json_encode(['error' => 'Tabelas não configuradas.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(genealogy_export_tree_data($pdo), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao carregar árvore.'], JSON_UNESCAPED_UNICODE);
}
