<?php
$pageTitle = 'Commande confirmée';
require_once __DIR__ . '/includes/header.php';

$orderId = (int)($_GET['order'] ?? 0);
$order = null;
$orderPixelId = null;

if ($orderId) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    // Récupère le pixel du premier store lié à cette commande
    $pixelStmt = $pdo->prepare("
        SELECT s.facebook_pixel_id FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN stores s ON p.store_id = s.id
        WHERE oi.order_id = ? AND s.facebook_pixel_id IS NOT NULL
        LIMIT 1
    ");
    $pixelStmt->execute([$orderId]);
    $orderPixelId = $pixelStmt->fetchColumn() ?: null;
}
?>

<div class="container" style="padding:4rem 0;max-width:600px">
    <div style="text-align:center;background:white;border-radius:var(--radius-lg);padding:3rem;border:1px solid var(--gray-100);box-shadow:var(--shadow)">
        <div style="width:80px;height:80px;background:#D1FAE5;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:2.5rem;margin:0 auto 1.5rem">✅</div>
        <h1 style="font-size:1.8rem;font-weight:800;color:var(--dark);margin-bottom:.5rem">Commande confirmée !</h1>
        <p style="color:var(--gray-500);margin-bottom:2rem">
            Merci pour votre achat ! Votre commande <strong>#<?= $orderId ?></strong> a été reçue et sera traitée dans les plus brefs délais.
        </p>

        <?php if ($order): ?>
        <div style="background:var(--gray-100);border-radius:var(--radius);padding:1.25rem;text-align:left;margin-bottom:2rem">
            <div style="display:flex;justify-content:space-between;padding:.5rem 0;font-size:.9rem;border-bottom:1px solid var(--gray-300)">
                <span style="color:var(--gray-500)">Commande</span>
                <strong>#<?= $order['id'] ?></strong>
            </div>
            <div style="display:flex;justify-content:space-between;padding:.5rem 0;font-size:.9rem;border-bottom:1px solid var(--gray-300)">
                <span style="color:var(--gray-500)">Total</span>
                <strong><?= formatPrice($order['total']) ?></strong>
            </div>
            <div style="display:flex;justify-content:space-between;padding:.5rem 0;font-size:.9rem;border-bottom:1px solid var(--gray-300)">
                <span style="color:var(--gray-500)">Paiement</span>
                <strong><?= ucfirst(str_replace('_', ' ', $order['payment_method'])) ?></strong>
            </div>
            <div style="display:flex;justify-content:space-between;padding:.5rem 0;font-size:.9rem">
                <span style="color:var(--gray-500)">Livraison</span>
                <strong style="text-align:right;max-width:60%"><?= escape($order['delivery_address'] ?? '') ?></strong>
            </div>
        </div>
        <?php endif; ?>

        <div style="display:flex;flex-direction:column;gap:.75rem">
            <a href="<?= SITE_URL ?>/index.php" class="btn btn-primary btn-lg">Retour à l'accueil</a>
            <a href="<?= SITE_URL ?>/orders.php" class="btn btn-outline">Voir mes commandes</a>
        </div>
    </div>
</div>

<script>const siteUrl = "<?= SITE_URL ?>";</script>
<?php if ($orderPixelId): ?>
<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '<?= escape($orderPixelId) ?>');
fbq('track', 'PageView');
fbq('track', 'Purchase', {
    value: <?= $order ? (float)$order['total'] : 0 ?>,
    currency: 'XOF',
    order_id: '<?= $orderId ?>'
});
</script>
<noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=<?= escape($orderPixelId) ?>&ev=Purchase&noscript=1"/></noscript>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
