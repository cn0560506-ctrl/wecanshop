<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$_SESSION['started'] = true;

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

function cartResponse(bool $success, ?int $count = null, ?string $error = null): void {
    echo json_encode(array_filter([
        'success' => $success,
        'cart_count' => $count,
        'error' => $error,
    ], fn($v) => $v !== null));
    exit;
}

function getCount(): int {
    global $pdo;
    if (isset($_SESSION['user_id'])) {
        $s = $pdo->prepare("SELECT COALESCE(SUM(quantity),0) FROM cart WHERE user_id = ?");
        $s->execute([$_SESSION['user_id']]);
    } else {
        $s = $pdo->prepare("SELECT COALESCE(SUM(quantity),0) FROM cart WHERE session_id = ?");
        $s->execute([session_id()]);
    }
    return (int)$s->fetchColumn();
}

match($action) {
    'add' => (function() use ($input, $pdo) {
        $productId = (int)($input['product_id'] ?? 0);
        $qty = max(1, (int)($input['quantity'] ?? 1));

        $stmt = $pdo->prepare("SELECT id, stock FROM products WHERE id = ? AND status = 'active'");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();

        if (!$product) { cartResponse(false, null, 'Produit introuvable.'); }
        if ($product['stock'] < $qty) { cartResponse(false, null, 'Stock insuffisant.'); }

        if (isset($_SESSION['user_id'])) {
            $existing = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
            $existing->execute([$_SESSION['user_id'], $productId]);
            $row = $existing->fetch();
            if ($row) {
                $pdo->prepare("UPDATE cart SET quantity = quantity + ? WHERE id = ?")
                    ->execute([$qty, $row['id']]);
            } else {
                $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?,?,?)")
                    ->execute([$_SESSION['user_id'], $productId, $qty]);
            }
        } else {
            $sid = session_id();
            $existing = $pdo->prepare("SELECT id, quantity FROM cart WHERE session_id = ? AND product_id = ?");
            $existing->execute([$sid, $productId]);
            $row = $existing->fetch();
            if ($row) {
                $pdo->prepare("UPDATE cart SET quantity = quantity + ? WHERE id = ?")
                    ->execute([$qty, $row['id']]);
            } else {
                $pdo->prepare("INSERT INTO cart (session_id, product_id, quantity) VALUES (?,?,?)")
                    ->execute([$sid, $productId, $qty]);
            }
        }
        cartResponse(true, getCount());
    })(),

    'update' => (function() use ($input, $pdo) {
        $cartId = (int)($input['cart_id'] ?? 0);
        $qty = max(1, (int)($input['quantity'] ?? 1));
        $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?")->execute([$qty, $cartId]);
        cartResponse(true, getCount());
    })(),

    'remove' => (function() use ($input, $pdo) {
        $cartId = (int)($input['cart_id'] ?? 0);
        $pdo->prepare("DELETE FROM cart WHERE id = ?")->execute([$cartId]);
        cartResponse(true, getCount());
    })(),

    'clear' => (function() use ($pdo) {
        if (isset($_SESSION['user_id'])) {
            $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$_SESSION['user_id']]);
        } else {
            $pdo->prepare("DELETE FROM cart WHERE session_id = ?")->execute([session_id()]);
        }
        cartResponse(true, 0);
    })(),

    default => cartResponse(false, null, 'Action inconnue.')
};
