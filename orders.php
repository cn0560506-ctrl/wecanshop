<?php
$pageTitle = 'Mes Commandes';
require_once __DIR__ . '/includes/header.php';
requireLogin();

$stmt = $pdo->prepare("
    SELECT o.*, GROUP_CONCAT(oi.product_name SEPARATOR ', ') as products
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    WHERE o.user_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$orders = $stmt->fetchAll();
?>

<div class="page-header">
    <div class="container">
        <div class="breadcrumb"><a href="<?= SITE_URL ?>">Accueil</a> › <span>Mes commandes</span></div>
        <h1>📦 Mes commandes</h1>
        <p><?= count($orders) ?> commande<?= count($orders) > 1 ? 's' : '' ?></p>
    </div>
</div>

<div class="container" style="padding:2rem 0">
    <?php if (empty($orders)): ?>
    <div class="empty-state" style="padding:5rem 0">
        <div class="empty-state-icon">📦</div>
        <h3>Aucune commande</h3>
        <p>Vous n'avez pas encore passé de commandes.</p>
        <a href="<?= SITE_URL ?>/index.php" class="btn btn-primary btn-lg">Retour à l'accueil</a>
    </div>
    <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:1rem">
        <?php foreach ($orders as $order): ?>
        <div class="card" style="margin:0">
            <div style="display:grid;grid-template-columns:1fr auto;gap:1rem;align-items:start">
                <div>
                    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:.75rem;flex-wrap:wrap">
                        <strong style="font-size:1rem">Commande #<?= $order['id'] ?></strong>
                        <span class="badge badge-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span>
                        <span style="font-size:.82rem;color:var(--gray-500)"><?= date('d/m/Y à H:i', strtotime($order['created_at'])) ?></span>
                    </div>
                    <p style="color:var(--gray-500);font-size:.9rem;margin-bottom:.5rem"><?= escape($order['products']) ?></p>
                    <div style="display:flex;gap:1.5rem;font-size:.85rem;color:var(--gray-500)">
                        <span>📍 <?= escape($order['city']) ?></span>
                        <span>💳 <?= ucfirst(str_replace('_', ' ', $order['payment_method'])) ?></span>
                        <span>🚚 Livraison: <?= formatPrice($order['delivery_fee']) ?></span>
                    </div>
                </div>
                <div style="text-align:right">
                    <div style="font-size:1.3rem;font-weight:800;color:var(--primary)"><?= formatPrice($order['total']) ?></div>
                    <div style="font-size:.8rem;color:var(--gray-500);margin-top:.25rem">Total TTC</div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>const siteUrl = "<?= SITE_URL ?>";</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
