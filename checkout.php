<?php
ob_start();
$pageTitle = 'Commander';
require_once __DIR__ . '/includes/header.php';

// Gestion du bouton "Acheter maintenant" (buy_now=id)
$buyNowId = (int)($_GET['buy_now'] ?? $_POST['buy_now_id'] ?? 0);
$isBuyNow = $buyNowId > 0;

if ($isBuyNow) {
    $stmt = $pdo->prepare("SELECT p.*, s.name as store_name FROM products p JOIN stores s ON p.store_id = s.id WHERE p.id = ? AND p.status = 'active'");
    $stmt->execute([$buyNowId]);
    $buyNowProduct = $stmt->fetch();
    if (!$buyNowProduct || $buyNowProduct['stock'] < 1) {
        setFlash('error', 'Ce produit n\'est plus disponible.');
        redirect(SITE_URL . '/shop.php');
    }
    $qty = max(1, (int)($_GET['qty'] ?? 1));
    $items = [[
        'product_id'   => $buyNowProduct['id'],
        'name'         => $buyNowProduct['name'],
        'price'        => $buyNowProduct['price'],
        'delivery_fee' => $buyNowProduct['delivery_fee'] ?? 0,
        'quantity'     => $qty,
        'stock'        => $buyNowProduct['stock'],
        'image'        => $buyNowProduct['image'],
        'store_name'   => $buyNowProduct['store_name'],
    ]];
} else {
    $items = getCartItems();
    if (empty($items)) {
        setFlash('info', 'Votre panier est vide.');
        redirect(SITE_URL . '/shop.php');
    }
}

$subtotal = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $items));
$deliveryFee = array_sum(array_map(fn($i) => ($i['delivery_fee'] ?? 0) * $i['quantity'], $items));
$total = $subtotal + $deliveryFee;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['customer_name'] ?? '');
    $phone   = trim($_POST['customer_phone'] ?? '');
    $address = trim($_POST['delivery_address'] ?? '');
    $payment = $_POST['payment_method'] ?? 'cash';
    $notes   = trim($_POST['notes'] ?? '');

    if (!$name || !$phone || !$address) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } else {
        $userId = $_SESSION['user_id'] ?? null;

        $pdo->prepare("
            INSERT INTO orders (user_id, customer_name, customer_phone,
                                delivery_address, total, delivery_fee, payment_method, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([$userId, $name, $phone, $address, $total, $deliveryFee, $payment, $notes]);

        $orderId = $pdo->lastInsertId();

        foreach ($items as $item) {
            $pdo->prepare("
                INSERT INTO order_items (order_id, product_id, product_name, quantity, price)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([$orderId, $item['product_id'], $item['name'], $item['quantity'], $item['price']]);

            $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?")
                ->execute([$item['quantity'], $item['product_id'], $item['quantity']]);
        }

        // Vider le panier seulement si ce n'est pas un achat direct
        if (!$isBuyNow) {
            if (isLoggedIn()) {
                $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$_SESSION['user_id']]);
            } else {
                $pdo->prepare("DELETE FROM cart WHERE session_id = ?")->execute([session_id()]);
            }
        }

        setFlash('success', "Commande #$orderId confirmée ! Vous recevrez une confirmation par email.");
        redirect(SITE_URL . '/order-success.php?order=' . $orderId);
    }
}

$userEmail = $_SESSION['user_email'] ?? '';
$userName = $_SESSION['user_name'] ?? '';
?>

<style>html,body{overflow-x:hidden;}</style>
<div class="page-header">
    <div class="container">
        <div class="breadcrumb">
            <a href="<?= SITE_URL ?>">Accueil</a> ›
            <a href="<?= SITE_URL ?>/cart.php">Panier</a> ›
            <span>Commander</span>
        </div>
        <h1>🔒 Finaliser la commande</h1>
    </div>
</div>

<div class="container" style="padding-bottom:3rem">
    <!-- Steps -->
    <div class="checkout-steps" style="margin:1.5rem 0">
        <div class="checkout-step done">
            <div class="step-dot">✓</div> Panier
        </div>
        <div class="checkout-step active">
            <div class="step-dot">2</div> Livraison & Paiement
        </div>
        <div class="checkout-step">
            <div class="step-dot">3</div> Confirmation
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= escape($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= $isBuyNow ? '?buy_now=' . $buyNowId : '' ?>" id="checkoutForm">
        <div class="checkout-layout">
            <!-- Form -->
            <div>
                <!-- Customer Info -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">📍 Informations de livraison</div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Nom complet *</label>
                            <input type="text" name="customer_name" class="form-control"
                                   value="<?= escape($_POST['customer_name'] ?? '') ?>"
                                   placeholder="Prénom et Nom" required>
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Téléphone *</label>
                            <input type="tel" name="customer_phone" class="form-control"
                                   value="<?= escape($_POST['customer_phone'] ?? '') ?>"
                                   placeholder="+221 77 000 0000" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Adresse complète *</label>
                        <textarea name="delivery_address" class="form-control" rows="2"
                                  placeholder="Quartier, rue, numéro..." required><?= escape($_POST['delivery_address'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Instructions de livraison</label>
                        <textarea name="notes" class="form-control" rows="2"
                                  placeholder="Indications supplémentaires pour le livreur..."><?= escape($_POST['notes'] ?? '') ?></textarea>
                    </div>
                </div>

                <input type="hidden" name="payment_method" value="cash">
            </div>

            <!-- Order Summary -->
            <div>
                <div class="cart-summary">
                    <h3>Récapitulatif</h3>

                    <?php foreach ($items as $item): ?>
                    <div style="display:flex;align-items:center;gap:.75rem;padding:.75rem 0;border-bottom:1px solid var(--gray-100)">
                        <img src="<?= getProductImageUrl($item['image']) ?>"
                             style="width:50px;height:50px;border-radius:8px;object-fit:cover">
                        <div style="flex:1">
                            <div style="font-size:.88rem;font-weight:600;color:var(--dark)"><?= escape($item['name']) ?></div>
                            <div style="font-size:.8rem;color:var(--gray-500)">Qté: <?= $item['quantity'] ?></div>
                        </div>
                        <div style="font-weight:700;font-size:.9rem"><?= formatPrice($item['price'] * $item['quantity']) ?></div>
                    </div>
                    <?php endforeach; ?>

                    <div class="summary-row">
                        <span>Sous-total</span>
                        <span><?= formatPrice($subtotal) ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Livraison</span>
                        <span><?= formatPrice($deliveryFee) ?></span>
                    </div>
                    <div class="summary-row total">
                        <span>Total à payer</span>
                        <span><?= formatPrice($total) ?></span>
                    </div>

                    <p style="font-size:.78rem;color:var(--gray-500);text-align:center;margin-top:.75rem">
                        En cliquant, vous acceptez nos conditions générales de vente.
                    </p>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Bouton Commander flottant -->
<div style="position:fixed;bottom:0;left:0;right:0;width:100%;z-index:999;background:white;border-top:1px solid #E2E8F0;padding:.85rem 1.25rem;box-shadow:0 -4px 20px rgba(0,0,0,.1);-webkit-transform:translateZ(0);transform:translateZ(0);box-sizing:border-box;">
    <div style="max-width:900px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;gap:1rem">
        <div style="font-size:.92rem;color:var(--gray-500)">
            Total : <strong style="color:var(--primary);font-size:1.05rem"><?= formatPrice($total) ?></strong>
        </div>
        <button type="submit" form="checkoutForm"
                style="background:linear-gradient(135deg,#7C3AED,#EC4899);color:white;border:none;padding:.85rem 2rem;border-radius:50px;font-weight:800;font-size:1rem;cursor:pointer;box-shadow:0 4px 18px rgba(124,58,237,.35);white-space:nowrap">
            🔒 Confirmer la commande
        </button>
    </div>
</div>
<div style="height:80px"></div>

<script>const siteUrl = "<?= SITE_URL ?>";</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
