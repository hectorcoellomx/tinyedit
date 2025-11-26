<?php
// API simple para TinyEdit
// Endpoints (GET):
// - /api.php?id=123      -> devuelve el item con id=123 (objeto JSON) (404 si no existe)
// - /api.php?all=1       -> devuelve todos los items como árbol recursivo (array JSON)

require_once __DIR__ . '/model.php';

header('Content-Type: application/json; charset=utf-8');

$itemModel = new Item();

// Helper de salida
function api_json($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// GET /api.php?id=123 -> item individual
if (isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    if ($id <= 0) {
        api_json(['error' => 'ID inválido'], 400);
    }

    $item = $itemModel->getById($id);
    if (!$item) {
        api_json(['error' => 'Item no encontrado'], 404);
    }

    api_json(['item' => $item]);
}

// GET /api.php?all=1 -> árbol completo
if (isset($_GET['all'])) {
    // getTree() ya devuelve recursivamente los children
    $tree = $itemModel->getTree(null);
    api_json(['items' => $tree]);
}

// Si no se reconoce el endpoint
api_json(['error' => 'Endpoint no válido. Usa ?id=ID o ?all=1'], 400);
