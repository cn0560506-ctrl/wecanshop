<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

function jsonResponse(bool $success, ?string $error = null, array $data = []): void {
    echo json_encode(array_merge(['success' => $success, 'error' => $error], $data));
    exit;
}

if (!isLoggedIn() || !isSeller()) {
    jsonResponse(false, 'Non autorisé.');
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

// Get seller's store
$storeStmt = $pdo->prepare("SELECT id FROM stores WHERE user_id = ? LIMIT 1");
$storeStmt->execute([$_SESSION['user_id']]);
$store = $storeStmt->fetch();

if (!$store) jsonResponse(false, 'Boutique introuvable.');

match($action) {
    'delete' => (function() use ($input, $pdo, $store) {
        $id = (int)($input['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ? AND store_id = ?");
        $stmt->execute([$id, $store['id']]);
        if ($stmt->rowCount() > 0) {
            jsonResponse(true);
        } else {
            jsonResponse(false, 'Produit introuvable ou non autorisé.');
        }
    })(),

    'toggle_status' => (function() use ($input, $pdo, $store) {
        $id = (int)($input['id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE products SET status = IF(status='active','inactive','active') WHERE id = ? AND store_id = ?");
        $stmt->execute([$id, $store['id']]);
        jsonResponse(true);
    })(),

    default => jsonResponse(false, 'Action inconnue.')
};
