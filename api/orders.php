<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

function jsonResponse(bool $success, ?string $error = null): void {
    echo json_encode(['success' => $success, 'error' => $error]);
    exit;
}

if (!isLoggedIn() || !isSeller()) jsonResponse(false, 'Non autorisé.');

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

$storeStmt = $pdo->prepare("SELECT id FROM stores WHERE user_id = ? LIMIT 1");
$storeStmt->execute([$_SESSION['user_id']]);
$store = $storeStmt->fetch();
if (!$store) jsonResponse(false, 'Boutique introuvable.');

if ($action === 'update_status') {
    $orderId = (int)($input['order_id'] ?? 0);
    $status = $input['status'] ?? '';
    $validStatuses = ['pending', 'confirmed', 'shipping', 'delivered', 'cancelled'];

    if (!in_array($status, $validStatuses)) jsonResponse(false, 'Statut invalide.');

    // Verify order belongs to this store
    $check = $pdo->prepare("
        SELECT COUNT(*) FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE o.id = ? AND p.store_id = ?
    ");
    $check->execute([$orderId, $store['id']]);
    if ((int)$check->fetchColumn() === 0) jsonResponse(false, 'Commande introuvable.');

    $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$status, $orderId]);
    jsonResponse(true);
}

jsonResponse(false, 'Action inconnue.');
