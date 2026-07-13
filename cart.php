<?php
$pageTitle = 'Panier';
require_once __DIR__ . '/includes/header.php';

$items = getCartItems();
$subtotal = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $items));
$deliveryFee = $subtotal > 0 ? 2500 : 0;
$total = $subtotal + $deliveryFee;
?>

<div class="page-header">
    <div class="container">
        <div class="breadcrumb">
            <a href="<?= SITE_URL ?>">Accueil</a> ›
            <a href="<?= SITE_URL ?>/index.php">Accueil</a> ›
            <span>Panier</span>
        </div>
        <h1>🛒 Mon Panier</h1>
        <p><?= count($items) ?> article<?= count($items) > 1 ? 's' : '' ?></p>
    </div>
</div>

<div class="container">
    <?= renderFlash() ?>

    <?php if (empty($items)): ?>
    <div class="empty-state" style="padding:6rem 0">
        <div class="empty-state-icon">🛒</div>
        <h3>Votre panier est vide</h3>
        <p>Parcourez notre marketplace et ajoutez des produits à votre panier.</p>
        <a href="<?= SITE_URL ?>/index.php" class="btn btn-primary btn-lg">Découvrir les produits</a>
    </div>
    <?php else: ?>
    <div class="cart-layout">
        <!-- Cart Items -->
        <div class="cart-items-wrap">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem">
                <h2 style="font-size:1.1rem;font-weight:700">Articles (<?= count($items) ?>)</h2>
                <button onclick="if(confirm('Vider tout le panier ?')) clearCart()" style="color:var(--danger);font-size:.88rem;font-weight:500">
                    Vider le panier
                </button>
            </div>

            <?php foreach ($items as $item): ?>
            <div class="cart-item" id="cart-item-<?= $item['id'] ?>">
                <a href="<?= SITE_URL ?>/product.php?id=<?= $item['product_id'] ?>">
                    <img class="cart-item-img"
                         src="<?= getProductImageUrl($item['image']) ?>"
                         alt="<?= escape($item['name']) ?>">
                </a>
                <div>
                    <a href="<?= SITE_URL ?>/product.php?id=<?= $item['product_id'] ?>">
                        <div class="cart-item-name"><?= escape($item['name']) ?></div>
                    </a>
                    <div class="cart-item-store"><?= escape($item['store_name']) ?></div>
                    <div class="cart-item-price"><?= formatPrice($item['price']) ?> / unité</div>
                    <?php if ($item['stock'] < $item['quantity']): ?>
                        <div style="color:var(--warning);font-size:.8rem;margin-top:.25rem">
                            ⚠️ Stock limité (<?= $item['stock'] ?> disponibles)
                        </div>
                    <?php endif; ?>
                </div>
                <div class="cart-item-controls">
                    <div class="cart-item-total"><?= formatPrice($item['price'] * $item['quantity']) ?></div>
                    <div class="qty-controls" style="transform:scale(.9)">
                        <button onclick="changeQty(<?= $item['id'] ?>, <?= $item['quantity'] - 1 ?>)">−</button>
                        <input type="number" value="<?= $item['quantity'] ?>" min="1" max="<?= $item['stock'] ?>"
                               onchange="changeQty(<?= $item['id'] ?>, parseInt(this.value))"
                               style="width:40px;height:34px">
                        <button onclick="changeQty(<?= $item['id'] ?>, <?= $item['quantity'] + 1 ?>)">+</button>
                    </div>
                    <button class="remove-btn" onclick="removeFromCart(<?= $item['id'] ?>)">
                        × Supprimer
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Order Summary -->
        <div>
            <div class="cart-summary">
                <h3>Résumé de la commande</h3>

                <div class="summary-row">
                    <span>Sous-total</span>
                    <span><?= formatPrice($subtotal) ?></span>
                </div>
                <div class="summary-row">
                    <span>Frais de livraison</span>
                    <span><?= $deliveryFee > 0 ? formatPrice($deliveryFee) : 'Gratuit' ?></span>
                </div>
                <div class="summary-row" style="padding:.4rem 0">
                    <input type="text" placeholder="Code promo" class="form-control" style="font-size:.85rem;height:36px">
                    <button class="btn btn-secondary btn-sm" style="margin-left:.5rem;white-space:nowrap">Appliquer</button>
                </div>
                <div class="summary-row total">
                    <span>Total</span>
                    <span><?= formatPrice($total) ?></span>
                </div>

                <a href="<?= SITE_URL ?>/checkout.php" class="btn btn-primary" style="width:100%;padding:.85rem;font-size:1rem;margin-top:1rem">
                    Passer la commande →
                </a>

                <a href="<?= SITE_URL ?>/index.php" class="btn btn-secondary" style="width:100%;padding:.7rem;margin-top:.75rem">
                    ← Continuer les achats
                </a>

                <div style="margin-top:1.5rem;display:flex;flex-direction:column;gap:.5rem">
                    <div style="display:flex;align-items:center;gap:.5rem;font-size:.82rem;color:var(--gray-500)">
                        <span>🔒</span> Paiement 100% sécurisé
                    </div>
                    <div style="display:flex;align-items:center;gap:.5rem;font-size:.82rem;color:var(--gray-500)">
                        <span>🚚</span> Livraison rapide 2-3 jours
                    </div>
                    <div style="display:flex;align-items:center;gap:.5rem;font-size:.82rem;color:var(--gray-500)">
                        <span>↩️</span> Retour sous 7 jours
                    </div>
                </div>
            </div>

            <div style="margin-top:1rem;background:var(--gray-100);border-radius:var(--radius);padding:1rem;font-size:.85rem;color:var(--gray-500)">
                <strong>Modes de paiement acceptés :</strong>
                <div style="display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.5rem">
                    <span class="badge badge-active">Wave</span>
                    <span class="badge badge-active">Orange Money</span>
                    <span class="badge badge-active">Stripe</span>
                    <span class="badge badge-active">PayPal</span>
                    <span class="badge badge-active">Cash</span>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
const siteUrl = "<?= SITE_URL ?>";

function clearCart() {
    fetch(`${siteUrl}/api/cart.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'clear' })
    }).then(r => r.json()).then(data => {
        if (data.success) location.reload();
    });
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
