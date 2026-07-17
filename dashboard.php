<?php
ob_start();
$pageTitle = 'Dashboard Vendeur';
$bodyClass = 'dashboard-page';
require_once __DIR__ . '/includes/header.php';
requireSeller();

// Get seller's store
$stmt = $pdo->prepare("SELECT * FROM stores WHERE user_id = ? LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$store = $stmt->fetch();

if (!$store) {
    setFlash('error', 'Boutique introuvable.');
    redirect(SITE_URL . '/index.php');
}

$storeId = $store['id'];

// Stats
$totalProducts = $pdo->prepare("SELECT COUNT(*) FROM products WHERE store_id = ?");
$totalProducts->execute([$storeId]);
$totalProducts = (int)$totalProducts->fetchColumn();

$totalOrders = $pdo->prepare("
    SELECT COUNT(DISTINCT oi.order_id) FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE p.store_id = ?
");
$totalOrders->execute([$storeId]);
$totalOrders = (int)$totalOrders->fetchColumn();

$revenue = $pdo->prepare("
    SELECT COALESCE(SUM(oi.price * oi.quantity), 0)
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE p.store_id = ? AND o.status != 'cancelled'
");
$revenue->execute([$storeId]);
$revenue = (float)$revenue->fetchColumn();

$pendingOrders = $pdo->prepare("
    SELECT COUNT(DISTINCT oi.order_id) FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE p.store_id = ? AND o.status = 'pending'
");
$pendingOrders->execute([$storeId]);
$pendingOrders = (int)$pendingOrders->fetchColumn();

// Recent Orders
$recentOrders = $pdo->prepare("
    SELECT DISTINCT o.*, GROUP_CONCAT(oi.product_name SEPARATOR ', ') as products
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.store_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT 10
");
$recentOrders->execute([$storeId]);
$recentOrders = $recentOrders->fetchAll();

// Products
$products = $pdo->prepare("
    SELECT p.*, c.name as category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.store_id = ?
    ORDER BY p.created_at DESC
    LIMIT 10
");
$products->execute([$storeId]);
$products = $products->fetchAll();

// Categories for add product form
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

$section = $_GET['section'] ?? 'overview';

// Vérification abonnement
$sub = getStoreSubscription($storeId);

// Admin : confirmation de paiement
if (isAdmin() && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_sub'])) {
    $subId    = (int)$_POST['sub_id'];
    $action   = $_POST['confirm_sub'];
    $subStmt  = $pdo->prepare("SELECT * FROM subscriptions WHERE id = ?");
    $subStmt->execute([$subId]);
    $subRow   = $subStmt->fetch();
    if ($subRow) {
        if ($action === 'confirm') {
            $start = date('Y-m-d');
            $end   = date('Y-m-d', strtotime('+30 days'));
            $pdo->prepare("UPDATE subscriptions SET status='confirmed', period_start=?, period_end=?, confirmed_at=NOW() WHERE id=?")
                ->execute([$start, $end, $subId]);
            $pdo->prepare("UPDATE stores SET subscription_status='active', subscription_end_date=? WHERE id=?")
                ->execute([$end, $subRow['store_id']]);
            setFlash('success', 'Abonnement activé avec succès.');
        } else {
            $pdo->prepare("UPDATE subscriptions SET status='rejected', admin_note=? WHERE id=?")->execute([$_POST['admin_note'] ?? '', $subId]);
            setFlash('error', 'Paiement rejeté.');
        }
        redirect(SITE_URL . '/dashboard.php?section=admin_subs');
    }
}

// Expiration automatique
if ($sub['sub_status'] === 'active' && $sub['sub_end_date'] && $sub['sub_end_date'] < date('Y-m-d')) {
    $pdo->prepare("UPDATE stores SET subscription_status='expired' WHERE id=?")->execute([$storeId]);
    $sub['sub_status']  = 'expired';
    $sub['is_subscribed'] = false;
    $sub['needs_payment'] = $sub['order_count'] >= FREE_ORDER_LIMIT;
}
?>

<div class="dashboard-layout">
    <!-- Sidebar -->
    <aside class="dashboard-sidebar">
        <div style="padding:1.25rem 1.25rem .5rem;border-bottom:1px solid rgba(255,255,255,.1);margin-bottom:.5rem">
            <div style="font-weight:700;color:white;font-size:.95rem"><?= escape($store['name']) ?></div>
            <div style="font-size:.78rem;color:rgba(255,255,255,.4)">Tableau de bord vendeur</div>
        </div>

        <span class="sidebar-section-label">Navigation</span>
        <a href="?section=overview" class="sidebar-link <?= $section === 'overview' ? 'active' : '' ?>">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            Vue d'ensemble
        </a>
        <a href="?section=products" class="sidebar-link <?= $section === 'products' ? 'active' : '' ?>">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>
            Mes produits
            <span style="margin-left:auto;background:rgba(255,255,255,.15);padding:.1rem .5rem;border-radius:20px;font-size:.75rem"><?= $totalProducts ?></span>
        </a>
        <a href="?section=orders" class="sidebar-link <?= $section === 'orders' ? 'active' : '' ?>">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
            Commandes
            <?php if ($pendingOrders > 0): ?>
                <span style="margin-left:auto;background:#EF4444;padding:.1rem .5rem;border-radius:20px;font-size:.75rem;color:white"><?= $pendingOrders ?></span>
            <?php endif; ?>
        </a>
        <a href="?section=add_product" class="sidebar-link <?= $section === 'add_product' ? 'active' : '' ?>">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
            Ajouter un produit
        </a>

        <a href="?section=abonnement" class="sidebar-link <?= $section === 'abonnement' ? 'active' : '' ?>"
           style="<?= $sub['needs_payment'] ? 'background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);' : '' ?>">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
            Abonnement
            <?php if ($sub['needs_payment']): ?>
                <span style="margin-left:auto;background:#EF4444;padding:.1rem .5rem;border-radius:20px;font-size:.7rem;color:white">!</span>
            <?php elseif ($sub['is_subscribed']): ?>
                <span style="margin-left:auto;background:#10B981;padding:.1rem .5rem;border-radius:20px;font-size:.7rem;color:white">✓</span>
            <?php endif; ?>
        </a>

        <a href="?section=livraison" class="sidebar-link <?= $section === 'livraison' ? 'active' : '' ?>">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 5v3h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
            Livraison
        </a>
        <a href="?section=pixel" class="sidebar-link <?= $section === 'pixel' ? 'active' : '' ?>">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 3a3 3 0 00-3 3v12a3 3 0 003 3 3 3 0 003-3 3 3 0 00-3-3H6a3 3 0 00-3 3 3 3 0 003 3 3 3 0 003-3V6a3 3 0 00-3-3 3 3 0 00-3 3 3 3 0 003 3h12a3 3 0 003-3 3 3 0 00-3-3z"/></svg>
            Pixel Facebook
        </a>

        <span class="sidebar-section-label">Boutique</span>
        <a href="?section=store_settings" class="sidebar-link <?= $section === 'store_settings' ? 'active' : '' ?>">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
            Paramètres boutique
        </a>
        <a href="<?= SITE_URL ?>/index.php" class="sidebar-link">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
            Accueil
        </a>
        <a href="<?= SITE_URL ?>/logout.php" class="sidebar-link" style="margin-top:.5rem;color:#F87171;border-top:1px solid rgba(255,255,255,.08);padding-top:.75rem">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Déconnexion
        </a>

        <?php if (isAdmin()): ?>
        <span class="sidebar-section-label">Admin</span>
        <a href="?section=admin_subs" class="sidebar-link <?= $section === 'admin_subs' ? 'active' : '' ?>">
            💳 Abonnements
            <?php
            $pendingCount = $pdo->query("SELECT COUNT(*) FROM subscriptions WHERE status='pending'")->fetchColumn();
            if ($pendingCount > 0): ?>
            <span style="margin-left:auto;background:#EF4444;padding:.1rem .5rem;border-radius:20px;font-size:.75rem;color:white"><?= $pendingCount ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>

        <!-- Statut abonnement dans sidebar -->
        <div style="margin:1rem .75rem .5rem;padding:.85rem;border-radius:var(--radius-sm);background:<?= $sub['needs_payment'] ? '#7F1D1D' : ($sub['in_trial'] ? 'rgba(124,58,237,.3)' : 'rgba(16,185,129,.2)') ?>">
            <div style="font-size:.72rem;font-weight:700;color:rgba(255,255,255,.6);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.3rem">Abonnement</div>
            <?php if ($sub['needs_payment']): ?>
                <div style="font-size:.82rem;color:#FCA5A5;font-weight:600">⚠️ Paiement requis</div>
                <a href="<?= SITE_URL ?>/subscribe.php" style="display:block;margin-top:.5rem;text-align:center;padding:.4rem;background:#EF4444;color:white;border-radius:6px;font-size:.75rem;font-weight:700;text-decoration:none">Souscrire — 3 000 FCFA</a>
            <?php elseif ($sub['is_subscribed']): ?>
                <div style="font-size:.82rem;color:#6EE7B7;font-weight:600">✅ Actif jusqu'au</div>
                <div style="font-size:.78rem;color:rgba(255,255,255,.8)"><?= date('d/m/Y', strtotime($sub['sub_end_date'])) ?></div>
            <?php else: ?>
                <div style="font-size:.82rem;color:rgba(255,255,255,.8);font-weight:600">Essai gratuit</div>
                <div style="font-size:.75rem;color:rgba(255,255,255,.55)"><?= $sub['order_count'] ?>/<?= FREE_ORDER_LIMIT ?> commandes</div>
                <div style="margin-top:.4rem;background:rgba(255,255,255,.15);border-radius:20px;height:4px;overflow:hidden">
                    <div style="height:100%;width:<?= min(100, round($sub['order_count']/FREE_ORDER_LIMIT*100)) ?>%;background:#A78BFA;border-radius:20px"></div>
                </div>
            <?php endif; ?>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="dashboard-main">
        <?= renderFlash() ?>

        <?php if ($sub['needs_payment']): ?>
        <!-- MURO : abonnement requis -->
        <div style="background:linear-gradient(135deg,#1E1B4B,#7C3AED,#EC4899);border-radius:var(--radius-lg);padding:2.5rem;text-align:center;margin-bottom:2rem;color:white">
            <div style="font-size:3.5rem;margin-bottom:.75rem">🔒</div>
            <h2 style="font-size:1.5rem;font-weight:900;margin-bottom:.5rem">Votre essai gratuit est terminé</h2>
            <p style="opacity:.8;margin-bottom:1.5rem;max-width:500px;margin-left:auto;margin-right:auto;font-size:.92rem">
                Vous avez atteint les <strong><?= FREE_ORDER_LIMIT ?> commandes gratuites</strong>. Pour continuer à vendre et gérer votre boutique, souscrivez à l'abonnement mensuel.
            </p>
            <a href="<?= SITE_URL ?>/subscribe.php" style="display:inline-block;background:white;color:#7C3AED;padding:.85rem 2.5rem;border-radius:50px;font-weight:800;font-size:1rem;text-decoration:none;box-shadow:0 4px 20px rgba(0,0,0,.2)">
                Souscrire maintenant — 3 000 FCFA/mois
            </a>
            <div style="font-size:.78rem;opacity:.6;margin-top:1rem">Wave · Orange Money · Carte bancaire</div>
        </div>
        <?php elseif (!$sub['in_trial'] && $sub['is_subscribed']): ?>
        <!-- Abonnement actif — rappel de renouvellement si proche -->
        <?php $daysLeft = (int)((strtotime($sub['sub_end_date']) - time()) / 86400); ?>
        <?php if ($daysLeft <= 5): ?>
        <div style="background:#FFF7ED;border:1.5px solid #FED7AA;border-radius:var(--radius);padding:1rem 1.5rem;margin-bottom:1.5rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem">
            <div style="display:flex;align-items:center;gap:.75rem">
                <span style="font-size:1.5rem">⏰</span>
                <div>
                    <strong style="color:#92400E">Abonnement expire dans <?= $daysLeft ?> jour<?= $daysLeft > 1 ? 's' : '' ?></strong>
                    <div style="font-size:.8rem;color:#B45309">Renouvelez maintenant pour éviter toute interruption de service.</div>
                </div>
            </div>
            <a href="<?= SITE_URL ?>/subscribe.php" class="btn btn-primary" style="padding:.5rem 1.25rem;font-size:.85rem">Renouveler</a>
        </div>
        <?php endif; ?>
        <?php elseif ($sub['in_trial'] && $sub['trial_remaining'] <= 3 && $sub['trial_remaining'] > 0): ?>
        <!-- Essai presque épuisé -->
        <div style="background:#EDE9FE;border:1.5px solid #C4B5FD;border-radius:var(--radius);padding:1rem 1.5rem;margin-bottom:1.5rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem">
            <div style="display:flex;align-items:center;gap:.75rem">
                <span style="font-size:1.5rem">💡</span>
                <div>
                    <strong style="color:#5B21B6">Plus que <?= $sub['trial_remaining'] ?> commande<?= $sub['trial_remaining'] > 1 ? 's' : '' ?> gratuites</strong>
                    <div style="font-size:.8rem;color:#7C3AED">Préparez votre abonnement pour continuer à vendre sans interruption.</div>
                </div>
            </div>
            <a href="<?= SITE_URL ?>/dashboard.php?section=abonnement" class="btn btn-primary" style="padding:.5rem 1.25rem;font-size:.85rem">Souscrire</a>
        </div>
        <?php endif; ?>

        <!-- OVERVIEW -->
        <?php if ($section === 'overview'): ?>
        <div class="dashboard-header">
            <div>
                <div class="dashboard-title">Bonjour, <?= escape(explode(' ', $_SESSION['user_name'])[0]) ?> 👋</div>
                <div style="color:var(--gray-500);font-size:.9rem">Voici le résumé de votre boutique</div>
            </div>
            <a href="?section=add_product" class="btn btn-primary">+ Ajouter un produit</a>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon purple">📦</div>
                <div>
                    <div class="stat-num"><?= $totalProducts ?></div>
                    <div class="stat-label">Produits</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">🛒</div>
                <div>
                    <div class="stat-num"><?= $totalOrders ?></div>
                    <div class="stat-label">Commandes totales</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange">💰</div>
                <div>
                    <div class="stat-num" style="font-size:1.2rem"><?= formatPrice($revenue) ?></div>
                    <div class="stat-label">Chiffre d'affaires</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon pink">⏳</div>
                <div>
                    <div class="stat-num"><?= $pendingOrders ?></div>
                    <div class="stat-label">Commandes en attente</div>
                </div>
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">Commandes récentes</div>
                <a href="?section=orders" class="btn btn-secondary btn-sm">Voir tout</a>
            </div>
            <?php if (empty($recentOrders)): ?>
            <div class="empty-state" style="padding:2rem">
                <div class="empty-state-icon" style="font-size:2.5rem">📦</div>
                <p>Aucune commande pour l'instant</p>
            </div>
            <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th><th>Client</th><th>Produits</th><th>Total</th><th>Statut</th><th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($recentOrders, 0, 5) as $order): ?>
                    <tr>
                        <td><strong>#<?= $order['id'] ?></strong></td>
                        <td><?= escape($order['customer_name']) ?></td>
                        <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= escape($order['products']) ?>"><?= escape(substr($order['products'], 0, 40)) ?>...</td>
                        <td><strong><?= formatPrice($order['total']) ?></strong></td>
                        <td><span class="badge badge-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span></td>
                        <td><?= date('d/m/Y', strtotime($order['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Recent Products -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">Mes produits récents</div>
                <a href="?section=products" class="btn btn-secondary btn-sm">Voir tout</a>
            </div>
            <?php if (empty($products)): ?>
            <div class="empty-state" style="padding:2rem">
                <div class="empty-state-icon" style="font-size:2.5rem">🛍️</div>
                <p>Aucun produit ajouté</p>
                <a href="?section=add_product" class="btn btn-primary btn-sm">Ajouter mon premier produit</a>
            </div>
            <?php else: ?>
            <table class="table">
                <thead>
                    <tr><th>Produit</th><th>Prix</th><th>Stock</th><th>Statut</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($products, 0, 5) as $p): ?>
                    <tr id="product-row-<?= $p['id'] ?>">
                        <td><strong><?= escape($p['name']) ?></strong></td>
                        <td><?= formatPrice($p['price']) ?></td>
                        <td>
                            <span style="color:<?= $p['stock'] < 5 ? 'var(--danger)' : 'var(--success)' ?>;font-weight:600">
                                <?= $p['stock'] ?>
                            </span>
                        </td>
                        <td><span class="badge badge-<?= $p['status'] ?>"><?= $p['status'] === 'active' ? 'Actif' : 'Inactif' ?></span></td>
                        <td>
                            <div style="display:flex;gap:.5rem">
                                <a href="<?= SITE_URL ?>/product.php?id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm">Voir</a>
                                <a href="?section=edit_product&id=<?= $p['id'] ?>" class="btn btn-outline btn-sm">Modifier</a>
                                <button onclick="deleteProduct(<?= $p['id'] ?>)" class="btn btn-sm" style="background:#FEE2E2;color:var(--danger)">Supprimer</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- PRODUCTS -->
        <?php elseif ($section === 'products'): ?>
        <div class="dashboard-header">
            <div class="dashboard-title">Mes produits</div>
            <a href="?section=add_product" class="btn btn-primary">+ Nouveau produit</a>
        </div>

        <div class="card">
            <?php
            $allProducts = $pdo->prepare("
                SELECT p.*, c.name as category_name
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.store_id = ?
                ORDER BY p.created_at DESC
            ");
            $allProducts->execute([$storeId]);
            $allProducts = $allProducts->fetchAll();
            ?>
            <?php if (empty($allProducts)): ?>
            <div class="empty-state" style="padding:3rem">
                <div class="empty-state-icon">📦</div>
                <h3>Aucun produit</h3>
                <p>Commencez par ajouter votre premier produit.</p>
                <a href="?section=add_product" class="btn btn-primary">Ajouter un produit</a>
            </div>
            <?php else: ?>
            <table class="table">
                <thead>
                    <tr><th>Produit</th><th>Catégorie</th><th>Prix</th><th>Stock</th><th>Vues</th><th>Statut</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($allProducts as $p): ?>
                    <tr id="product-row-<?= $p['id'] ?>">
                        <td>
                            <div style="font-weight:600"><?= escape($p['name']) ?></div>
                            <div style="font-size:.78rem;color:var(--gray-500)"><?= escape(substr($p['description'] ?? '', 0, 40)) ?>...</div>
                        </td>
                        <td><?= escape($p['category_name'] ?? '—') ?></td>
                        <td><strong><?= formatPrice($p['price']) ?></strong></td>
                        <td>
                            <span class="badge <?= $p['stock'] === 0 ? 'badge-cancelled' : ($p['stock'] < 5 ? 'badge-pending' : 'badge-active') ?>">
                                <?= $p['stock'] ?>
                            </span>
                        </td>
                        <td><?= number_format($p['views']) ?></td>
                        <td><span class="badge badge-<?= $p['status'] ?>"><?= $p['status'] === 'active' ? 'Actif' : 'Inactif' ?></span></td>
                        <td>
                            <div style="display:flex;gap:.4rem">
                                <a href="<?= SITE_URL ?>/product.php?id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm">Voir</a>
                                <a href="?section=edit_product&id=<?= $p['id'] ?>" class="btn btn-outline btn-sm">Éditer</a>
                                <button onclick="deleteProduct(<?= $p['id'] ?>)" class="btn btn-sm" style="background:#FEE2E2;color:var(--danger)">✕</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- ORDERS -->
        <?php elseif ($section === 'orders'): ?>
        <div class="dashboard-header">
            <div class="dashboard-title">Commandes</div>
        </div>

        <div class="card">
            <?php
            $allOrders = $pdo->prepare("
                SELECT DISTINCT o.*, GROUP_CONCAT(oi.product_name SEPARATOR ', ') as products,
                       SUM(oi.quantity) as total_items
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                JOIN products p ON oi.product_id = p.id
                WHERE p.store_id = ?
                GROUP BY o.id
                ORDER BY o.created_at DESC
            ");
            $allOrders->execute([$storeId]);
            $allOrders = $allOrders->fetchAll();
            ?>
            <?php if (empty($allOrders)): ?>
            <div class="empty-state" style="padding:3rem">
                <div class="empty-state-icon">🛒</div>
                <h3>Aucune commande</h3>
                <p>Vous n'avez pas encore reçu de commandes.</p>
            </div>
            <?php else: ?>
            <table class="table">
                <thead>
                    <tr><th>#</th><th>Client</th><th>Produits</th><th>Total</th><th>Paiement</th><th>Statut</th><th>Date</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($allOrders as $order): ?>
                    <tr>
                        <td><strong>#<?= $order['id'] ?></strong></td>
                        <td>
                            <div style="font-weight:600"><?= escape($order['customer_name']) ?></div>
                            <div style="font-size:.78rem;color:var(--gray-500)"><?= escape($order['customer_phone'] ?? '') ?></div>
                        </td>
                        <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= escape($order['products']) ?></td>
                        <td><strong><?= formatPrice($order['total']) ?></strong></td>
                        <td><?= ucfirst(str_replace('_', ' ', $order['payment_method'])) ?></td>
                        <td>
                            <select onchange="updateOrderStatus(<?= $order['id'] ?>, this.value)" class="form-select" style="padding:.3rem .6rem;font-size:.8rem;width:auto">
                                <?php foreach (['pending','confirmed','shipping','delivered','cancelled'] as $s): ?>
                                <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
                        <td>
                            <div style="font-size:.78rem;color:var(--gray-500)">
                                📍 <?= escape($order['delivery_address'] ?? '—') ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- ADD PRODUCT -->
        <?php elseif ($section === 'add_product'): ?>
        <div class="dashboard-header">
            <div class="dashboard-title">Ajouter un produit</div>
            <a href="?section=products" class="btn btn-secondary">← Retour</a>
        </div>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
            $pName       = trim($_POST['name'] ?? '');
            $pDesc       = trim($_POST['description'] ?? '');
            $pProblems   = trim($_POST['patient_problems'] ?? '');
            $pAdvantages = trim($_POST['advantages'] ?? '');
            $pPosologie  = trim($_POST['posologie'] ?? '');
            $pPrice      = (float)($_POST['price'] ?? 0);
            $pOrigPrice  = $_POST['original_price'] !== '' ? (float)$_POST['original_price'] : null;
            $pDelivery   = (float)($_POST['delivery_fee'] ?? 0);
            $pProbW      = min(100, max(10, (int)($_POST['problems_image_width']  ?? 100)));
            $pAdvW       = min(100, max(10, (int)($_POST['advantages_image_width'] ?? 100)));
            $pPosW       = min(100, max(10, (int)($_POST['posologie_image_width']  ?? 100)));
            $pStock      = (int)($_POST['stock'] ?? 0);
            $pCatId      = (int)($_POST['category_id'] ?? 0) ?: null;
            $pStatus     = $_POST['status'] ?? 'active';

            if ($pName && $pPrice > 0) {
                $imageFile = null;
                if (!empty($_FILES['image']['name'])) {
                    $uploaded = uploadProductImage($_FILES['image']);
                    if ($uploaded === false) {
                        $addError = 'Photo principale invalide. Formats acceptés : JPG, PNG, WEBP (max 5 Mo).';
                    } else {
                        $imageFile = $uploaded;
                    }
                }

                // Images des sections santé
                $probImg = null; $advImg = null; $posImg = null;
                if (!empty($_FILES['problems_image']['name']))  $probImg = uploadProductImage($_FILES['problems_image'])  ?: null;
                if (!empty($_FILES['advantages_image']['name'])) $advImg = uploadProductImage($_FILES['advantages_image']) ?: null;
                if (!empty($_FILES['posologie_image']['name']))  $posImg = uploadProductImage($_FILES['posologie_image'])  ?: null;

                if (!isset($addError)) {
                    $slug = slugify($pName);
                    $pdo->prepare("
                        INSERT INTO products
                            (store_id, category_id, name, slug, description, patient_problems,
                             advantages, posologie, problems_image, advantages_image, posologie_image,
                             price, original_price, delivery_fee, stock, image, status,
                             problems_image_width, advantages_image_width, posologie_image_width)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ")->execute([$storeId, $pCatId, $pName, $slug, $pDesc, $pProblems,
                                 $pAdvantages, $pPosologie, $probImg, $advImg, $posImg,
                                 $pPrice, $pOrigPrice, $pDelivery, $pStock, $imageFile, $pStatus,
                                 $pProbW, $pAdvW, $pPosW]);
                    $newProductId = (int)$pdo->lastInsertId();

                    // Témoignages (uploadés via AJAX avant la soumission)
                    $uploadedFiles    = $_POST['uploaded_testimonials'] ?? [];
                    $uploadedCaptions = $_POST['uploaded_captions']     ?? [];
                    foreach ($uploadedFiles as $i => $tFile) {
                        $tFile = basename(trim($tFile)); // sécurité : pas de path traversal
                        $tPath = __DIR__ . '/uploads/testimonials/' . $tFile;
                        if (!$tFile || !file_exists($tPath)) continue;
                        $tType    = getTestimonialType($tFile);
                        $tCaption = trim($uploadedCaptions[$i] ?? '');
                        $pdo->prepare("INSERT INTO product_testimonials (product_id, file_name, file_type, caption, sort_order) VALUES (?,?,?,?,?)")
                            ->execute([$newProductId, $tFile, $tType, $tCaption, $i]);
                    }

                    // Images galerie (uploadées via AJAX)
                    $galleryFiles = $_POST['uploaded_gallery_images'] ?? [];
                    foreach ($galleryFiles as $idx => $gFile) {
                        $gFile = basename(trim($gFile));
                        if (!$gFile || !file_exists(__DIR__ . '/uploads/products/' . $gFile)) continue;
                        $pdo->prepare("INSERT INTO product_images (product_id, file_name, sort_order) VALUES (?,?,?)")
                            ->execute([$newProductId, $gFile, $idx]);
                    }

                    setFlash('success', 'Produit "' . $pName . '" ajouté avec succès !');
                    redirect(SITE_URL . '/dashboard.php?section=products');
                }
            } else {
                $addError = 'Nom et prix sont obligatoires.';
            }
        }
        ?>

        <div class="card" style="padding:0;overflow:hidden">
            <!-- Onglets -->
            <div class="form-tabs">
                <button type="button" class="form-tab active" onclick="switchTab('tab-general', this)">
                    📦 Informations
                </button>
                <button type="button" class="form-tab" onclick="switchTab('tab-health', this)">
                    🩺 Description santé
                </button>
                <button type="button" class="form-tab" onclick="switchTab('tab-testimonials', this)">
                    🎥 Témoignages
                </button>
            </div>

            <?php if (isset($addError)): ?>
                <div class="alert alert-error" style="margin:1.5rem 1.5rem 0"><?= escape($addError) ?></div>
            <?php endif; ?>

            <form method="POST" action="?section=add_product" enctype="multipart/form-data" id="productForm">
                <input type="hidden" name="add_product" value="1">
                <div style="padding:1.5rem">

                    <!-- TAB 1 : Informations générales -->
                    <div id="tab-general" class="tab-panel">
                        <div style="display:grid;grid-template-columns:1fr 280px;gap:2rem;align-items:start">
                            <div class="form-grid-2">
                                <div class="form-group" style="grid-column:1/-1">
                                    <label class="form-label">Nom du produit *</label>
                                    <div style="display:flex;gap:.6rem;align-items:center">
                                        <input type="text" name="name" id="addProductName" class="form-control" placeholder="Ex: Tisane Détox Bio" required style="flex:1">
                                        <button type="button" onclick="openAiPanel()" id="aiBtn"
                                                style="flex-shrink:0;display:flex;align-items:center;gap:.45rem;padding:.55rem 1rem;background:linear-gradient(135deg,#7C3AED,#EC4899);color:white;border:none;border-radius:var(--radius-sm);font-size:.82rem;font-weight:700;cursor:pointer;white-space:nowrap;box-shadow:0 3px 12px rgba(124,58,237,.35);transition:opacity .2s"
                                                onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                                            ✨ Rédiger avec l'IA
                                        </button>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Prix (FCFA) *</label>
                                    <input type="number" name="price" class="form-control" placeholder="0" min="0" step="100" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Prix barré (FCFA)</label>
                                    <input type="number" name="original_price" class="form-control" placeholder="Prix avant remise" min="0" step="100">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Frais de livraison (FCFA)</label>
                                    <input type="number" name="delivery_fee" class="form-control" placeholder="Ex: 2000" min="0" step="100" value="0">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Stock disponible</label>
                                    <input type="number" name="stock" class="form-control" value="10" min="0">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Catégorie</label>
                                    <select name="category_id" class="form-select">
                                        <option value="">Sélectionner</option>
                                        <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>"><?= escape($cat['icon'] . ' ' . $cat['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Statut</label>
                                    <select name="status" class="form-select">
                                        <option value="active">✅ Actif (visible)</option>
                                        <option value="inactive">🔒 Inactif (masqué)</option>
                                    </select>
                                </div>
                                <div class="form-group" style="grid-column:1/-1">
                                    <label class="form-label">Description générale</label>
                                    <textarea name="description" class="form-control" rows="3"
                                              placeholder="Présentation courte et générale du produit..."></textarea>
                                </div>
                            </div>
                            <!-- Photo principale -->
                            <div>
                                <label class="form-label">Photo principale</label>
                                <div class="upload-zone" id="uploadZone" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)" ondrop="handleDrop(event)">
                                    <input type="file" name="image" id="imageInput" accept="image/jpeg,image/png,image/webp" onchange="previewImage(this)">
                                    <div id="uploadPlaceholder">
                                        <div class="upload-zone-icon">🖼️</div>
                                        <div class="upload-zone-text">Cliquer ou glisser</div>
                                        <div class="upload-zone-hint">JPG, PNG, WEBP — max 5 Mo</div>
                                    </div>
                                    <div class="upload-preview" id="uploadPreview" style="display:none">
                                        <img id="previewImg" src="" alt="Aperçu">
                                        <span class="upload-preview-remove" onclick="removePreview(event)">×</span>
                                    </div>
                                </div>
                                <p style="font-size:.75rem;color:var(--gray-500);margin-top:.5rem">Recommandé : carré 800×800 px minimum.</p>

                                <!-- Galerie d'images -->
                                <label class="form-label" style="margin-top:1rem">Photos galerie <span style="font-size:.75rem;color:var(--gray-500)">(défilent en slider)</span></label>
                                <div id="galleryDropZone"
                                     style="border:2px dashed var(--gray-200);border-radius:var(--radius-sm);padding:1rem;text-align:center;cursor:pointer;background:var(--gray-50);transition:border-color .2s"
                                     onclick="document.getElementById('galleryFileInput').click()"
                                     ondragover="event.preventDefault();this.style.borderColor='var(--primary)'"
                                     ondragleave="this.style.borderColor='var(--gray-200)'"
                                     ondrop="handleGalleryDrop(event)">
                                    <input type="file" id="galleryFileInput" accept="image/jpeg,image/png,image/webp" multiple style="display:none" onchange="handleGalleryFiles(this.files)">
                                    <div style="font-size:1.5rem;margin-bottom:.3rem">🖼️</div>
                                    <div style="font-size:.8rem;color:var(--gray-500)">Ajouter des photos (plusieurs à la fois)</div>
                                </div>
                                <div id="galleryPreviewGrid" style="display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.6rem"></div>
                                <div id="galleryHiddenInputs"></div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2 : Description santé -->
                    <div id="tab-health" class="tab-panel" style="display:none">
                        <div style="display:flex;flex-direction:column;gap:1.5rem">

                            <div class="health-section-card" style="border-left-color:#EF4444">
                                <div class="health-section-header">
                                    <span class="health-section-icon" style="background:#FEE2E2">😣</span>
                                    <div>
                                        <div class="health-section-title">Problèmes du patient</div>
                                        <div class="health-section-subtitle">Quels maux ou symptômes ce produit traite-t-il ?</div>
                                    </div>
                                </div>
                                <textarea name="patient_problems" class="form-control" rows="5"
                                          placeholder="Ex:&#10;• Douleurs articulaires&#10;• Fatigue chronique&#10;• Troubles digestifs&#10;• Insomnie&#10;Décrivez les problèmes de santé que ce produit aide à résoudre."></textarea>
                                <?= healthImageUpload('problems_image', 'prob', '#FEE2E2', '#B91C1C') ?>
                                <div style="margin-top:.6rem">
                                    <label class="form-label" style="font-size:.78rem">Taille d'affichage : <span id="prob_w_val">100</span>%</label>
                                    <input type="range" name="problems_image_width" min="10" max="100" value="100" step="5"
                                           style="width:100%;accent-color:#B91C1C" oninput="document.getElementById('prob_w_val').textContent=this.value">
                                </div>
                            </div>

                            <div class="health-section-card" style="border-left-color:#10B981">
                                <div class="health-section-header">
                                    <span class="health-section-icon" style="background:#D1FAE5">✅</span>
                                    <div>
                                        <div class="health-section-title">Avantages du produit</div>
                                        <div class="health-section-subtitle">Quels sont les bienfaits et effets bénéfiques ?</div>
                                    </div>
                                </div>
                                <textarea name="advantages" class="form-control" rows="5"
                                          placeholder="Ex:&#10;• Soulage les douleurs rapidement&#10;• 100% naturel, sans effets secondaires&#10;• Améliore le sommeil en 7 jours&#10;• Réduit l'inflammation&#10;Listez les avantages clés de votre produit."></textarea>
                                <?= healthImageUpload('advantages_image', 'adv', '#D1FAE5', '#065F46') ?>
                                <div style="margin-top:.6rem">
                                    <label class="form-label" style="font-size:.78rem">Taille d'affichage : <span id="adv_w_val">100</span>%</label>
                                    <input type="range" name="advantages_image_width" min="10" max="100" value="100" step="5"
                                           style="width:100%;accent-color:#065F46" oninput="document.getElementById('adv_w_val').textContent=this.value">
                                </div>
                            </div>

                            <div class="health-section-card" style="border-left-color:#7C3AED">
                                <div class="health-section-header">
                                    <span class="health-section-icon" style="background:#EDE9FE">💊</span>
                                    <div>
                                        <div class="health-section-title">Posologie / Mode d'emploi</div>
                                        <div class="health-section-subtitle">Comment et quand utiliser ce produit ?</div>
                                    </div>
                                </div>
                                <textarea name="posologie" class="form-control" rows="5"
                                          placeholder="Ex:&#10;• Adultes : 2 gélules matin et soir&#10;• Prendre avec un grand verre d'eau&#10;• Cure de 30 jours recommandée&#10;• Déconseillé aux femmes enceintes&#10;Précisez les doses, fréquences et précautions d'usage."></textarea>
                                <?= healthImageUpload('posologie_image', 'pos', '#EDE9FE', '#5B21B6') ?>
                                <div style="margin-top:.6rem">
                                    <label class="form-label" style="font-size:.78rem">Taille d'affichage : <span id="pos_w_val">100</span>%</label>
                                    <input type="range" name="posologie_image_width" min="10" max="100" value="100" step="5"
                                           style="width:100%;accent-color:#5B21B6" oninput="document.getElementById('pos_w_val').textContent=this.value">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 3 : Témoignages -->
                    <div id="tab-testimonials" class="tab-panel" style="display:none">
                        <div style="font-size:1rem;font-weight:700;color:var(--dark);margin-bottom:.25rem">Photos & Vidéos de témoignage</div>
                        <div style="font-size:.85rem;color:var(--gray-500);margin-bottom:1rem">Ajoutez des preuves visuelles de l'efficacité de votre produit.</div>

                        <!-- Zone de dépôt -->
                        <div class="testimonial-upload-zone" id="testimonialsZone"
                             onclick="document.getElementById('testimonialsFileInput').click()"
                             ondragover="handleTestimonialDragOver(event)"
                             ondragleave="handleTestimonialDragLeave(event)"
                             ondrop="handleTestimonialDrop(event)">
                            <input type="file" id="testimonialsFileInput"
                                   accept="image/*,video/*,.mov,.avi,.mkv"
                                   multiple style="display:none"
                                   onchange="handleTestimonialsSelected(this.files)">
                            <div>
                                <div style="font-size:3rem;margin-bottom:.75rem">📸</div>
                                <div style="font-weight:700;color:var(--gray-700);margin-bottom:.25rem">Cliquer ou glisser des fichiers ici</div>
                                <div style="font-size:.82rem;color:var(--gray-500)">Images (JPG, PNG, WEBP) et Vidéos (MP4, WEBM, MOV, AVI) — max 50 Mo par fichier</div>
                            </div>
                        </div>

                        <!-- Grille de prévisualisations -->
                        <div id="testimonialsPreviewGrid" class="testimonials-preview-grid"></div>

                        <!-- Champs cachés ajoutés dynamiquement -->
                        <div id="testimonialHiddenInputs"></div>

                        <div style="margin-top:1.25rem;background:var(--gray-100);border-radius:var(--radius-sm);padding:1rem;font-size:.82rem;color:var(--gray-500)">
                            <strong>💡 Conseils :</strong> Les photos avant/après et les vidéos de témoignages augmentent la confiance des acheteurs et boostent vos ventes.
                        </div>
                    </div>

                </div><!-- end padding -->

                <div style="display:flex;gap:1rem;justify-content:flex-end;padding:1rem 1.5rem;border-top:1px solid var(--gray-100);background:var(--gray-100)">
                    <a href="?section=products" class="btn btn-secondary">Annuler</a>
                    <button type="submit" class="btn btn-primary" id="submitProductBtn">📤 Publier le produit</button>
                </div>
            </form>
        </div>

        <!-- EDIT PRODUCT -->
        <?php elseif ($section === 'edit_product'): ?>
        <?php
        $editId = (int)($_GET['id'] ?? 0);
        $editStmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND store_id = ?");
        $editStmt->execute([$editId, $storeId]);
        $editProduct = $editStmt->fetch();

        if (!$editProduct) {
            setFlash('error', 'Produit introuvable.');
            redirect(SITE_URL . '/dashboard.php?section=products');
        }

        // Charger les témoignages existants
        $existingTestimonials = $pdo->prepare("SELECT * FROM product_testimonials WHERE product_id = ? ORDER BY sort_order");
        $existingTestimonials->execute([$editId]);
        $existingTestimonials = $existingTestimonials->fetchAll();

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_product'])) {
            $pName       = trim($_POST['name'] ?? '');
            $pDesc       = trim($_POST['description'] ?? '');
            $pProblems   = trim($_POST['patient_problems'] ?? '');
            $pAdvantages = trim($_POST['advantages'] ?? '');
            $pPosologie  = trim($_POST['posologie'] ?? '');
            $pPrice      = (float)($_POST['price'] ?? 0);
            $pOrigPrice  = $_POST['original_price'] !== '' ? (float)$_POST['original_price'] : null;
            $pDelivery   = (float)($_POST['delivery_fee'] ?? 0);
            $pProbW      = min(100, max(10, (int)($_POST['problems_image_width']  ?? 100)));
            $pAdvW       = min(100, max(10, (int)($_POST['advantages_image_width'] ?? 100)));
            $pPosW       = min(100, max(10, (int)($_POST['posologie_image_width']  ?? 100)));
            $pStock      = (int)($_POST['stock'] ?? 0);
            $pCatId      = (int)($_POST['category_id'] ?? 0) ?: null;
            $pStatus     = $_POST['status'] ?? 'active';
            $imageFile   = $editProduct['image'];

            if ($pName && $pPrice > 0) {
                if (!empty($_FILES['image']['name'])) {
                    $uploaded = uploadProductImage($_FILES['image'], $editProduct['image']);
                    if ($uploaded === false) {
                        $editError = 'Photo invalide. Formats acceptés : JPG, PNG, WEBP (max 5 Mo).';
                    } else {
                        $imageFile = $uploaded;
                    }
                }

                // Images sections santé (conserver l'ancienne si aucune nouvelle)
                $probImg = $editProduct['problems_image'];
                $advImg  = $editProduct['advantages_image'];
                $posImg  = $editProduct['posologie_image'];
                if (!empty($_FILES['problems_image']['name']))  $probImg = uploadProductImage($_FILES['problems_image'],  $probImg) ?: $probImg;
                if (!empty($_FILES['advantages_image']['name'])) $advImg = uploadProductImage($_FILES['advantages_image'], $advImg)  ?: $advImg;
                if (!empty($_FILES['posologie_image']['name']))  $posImg = uploadProductImage($_FILES['posologie_image'],  $posImg)  ?: $posImg;
                // Supprimer si l'utilisateur a coché "supprimer l'image"
                if (isset($_POST['del_problems_image']))  { if ($probImg) @unlink(__DIR__.'/uploads/products/'.$probImg); $probImg = null; }
                if (isset($_POST['del_advantages_image'])){ if ($advImg)  @unlink(__DIR__.'/uploads/products/'.$advImg);  $advImg  = null; }
                if (isset($_POST['del_posologie_image'])) { if ($posImg)  @unlink(__DIR__.'/uploads/products/'.$posImg);  $posImg  = null; }

                // Supprimer les témoignages cochés
                $toDelete = $_POST['delete_testimonials'] ?? [];
                foreach ($toDelete as $tid) {
                    $tRow = $pdo->prepare("SELECT file_name FROM product_testimonials WHERE id = ? AND product_id = ?");
                    $tRow->execute([(int)$tid, $editId]);
                    $tData = $tRow->fetch();
                    if ($tData) {
                        $tPath = __DIR__ . '/uploads/testimonials/' . $tData['file_name'];
                        if (file_exists($tPath)) unlink($tPath);
                        $pdo->prepare("DELETE FROM product_testimonials WHERE id = ?")->execute([(int)$tid]);
                    }
                }

                if (!isset($editError)) {
                    $pdo->prepare("
                        UPDATE products SET name=?, description=?, patient_problems=?, advantages=?,
                        posologie=?, problems_image=?, advantages_image=?, posologie_image=?,
                        price=?, original_price=?, delivery_fee=?, stock=?, category_id=?, image=?, status=?,
                        problems_image_width=?, advantages_image_width=?, posologie_image_width=?
                        WHERE id=? AND store_id=?
                    ")->execute([$pName, $pDesc, $pProblems, $pAdvantages, $pPosologie,
                                 $probImg, $advImg, $posImg,
                                 $pPrice, $pOrigPrice, $pDelivery, $pStock, $pCatId, $imageFile, $pStatus,
                                 $pProbW, $pAdvW, $pPosW, $editId, $storeId]);

                    // Nouveaux témoignages (uploadés via AJAX)
                    $uploadedFiles    = $_POST['uploaded_testimonials'] ?? [];
                    $uploadedCaptions = $_POST['uploaded_captions']     ?? [];
                    $order = count($existingTestimonials);
                    foreach ($uploadedFiles as $i => $tFile) {
                        $tFile = basename(trim($tFile));
                        $tPath = __DIR__ . '/uploads/testimonials/' . $tFile;
                        if (!$tFile || !file_exists($tPath)) continue;
                        $tType    = getTestimonialType($tFile);
                        $tCaption = trim($uploadedCaptions[$i] ?? '');
                        $pdo->prepare("INSERT INTO product_testimonials (product_id, file_name, file_type, caption, sort_order) VALUES (?,?,?,?,?)")
                            ->execute([$editId, $tFile, $tType, $tCaption, $order++]);
                    }

                    // Supprimer images galerie cochées
                    $delGallery = $_POST['delete_gallery_images'] ?? [];
                    foreach ($delGallery as $gid) {
                        $gRow = $pdo->prepare("SELECT file_name FROM product_images WHERE id = ? AND product_id = ?");
                        $gRow->execute([(int)$gid, $editId]);
                        $gData = $gRow->fetch();
                        if ($gData) {
                            @unlink(__DIR__ . '/uploads/products/' . $gData['file_name']);
                            $pdo->prepare("DELETE FROM product_images WHERE id = ?")->execute([(int)$gid]);
                        }
                    }

                    // Nouvelles images galerie
                    $galleryFiles = $_POST['uploaded_gallery_images'] ?? [];
                    $gOrder = count($existingGallery ?? []);
                    foreach ($galleryFiles as $idx => $gFile) {
                        $gFile = basename(trim($gFile));
                        if (!$gFile || !file_exists(__DIR__ . '/uploads/products/' . $gFile)) continue;
                        $pdo->prepare("INSERT INTO product_images (product_id, file_name, sort_order) VALUES (?,?,?)")
                            ->execute([$editId, $gFile, $gOrder + $idx]);
                    }

                    setFlash('success', 'Produit mis à jour !');
                    redirect(SITE_URL . '/dashboard.php?section=products');
                }
            } else {
                $editError = 'Nom et prix sont obligatoires.';
            }
        }
        ?>

        <div class="dashboard-header">
            <div class="dashboard-title">Modifier le produit</div>
            <a href="?section=products" class="btn btn-secondary">← Retour</a>
        </div>

        <div class="card" style="padding:0;overflow:hidden">
            <div class="form-tabs">
                <button type="button" class="form-tab active" onclick="switchTab('tab-general', this)">📦 Informations</button>
                <button type="button" class="form-tab" onclick="switchTab('tab-health', this)">🩺 Description santé</button>
                <button type="button" class="form-tab" onclick="switchTab('tab-testimonials', this)">
                    🎥 Témoignages
                    <?php if (count($existingTestimonials) > 0): ?>
                        <span style="background:var(--primary);color:white;border-radius:20px;padding:.1rem .5rem;font-size:.72rem;margin-left:.3rem"><?= count($existingTestimonials) ?></span>
                    <?php endif; ?>
                </button>
            </div>

            <?php if (isset($editError)): ?>
                <div class="alert alert-error" style="margin:1.5rem 1.5rem 0"><?= escape($editError) ?></div>
            <?php endif; ?>

            <form method="POST" action="?section=edit_product&id=<?= $editId ?>" enctype="multipart/form-data">
                <input type="hidden" name="edit_product" value="1">
                <div style="padding:1.5rem">

                    <!-- TAB 1 : Informations -->
                    <div id="tab-general" class="tab-panel">
                        <div style="display:grid;grid-template-columns:1fr 280px;gap:2rem;align-items:start">
                            <div class="form-grid-2">
                                <div class="form-group" style="grid-column:1/-1">
                                    <label class="form-label">Nom du produit *</label>
                                    <input type="text" name="name" class="form-control" value="<?= escape($editProduct['name']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Prix (FCFA) *</label>
                                    <input type="number" name="price" class="form-control" value="<?= $editProduct['price'] ?>" min="0" step="100" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Prix barré (FCFA)</label>
                                    <input type="number" name="original_price" class="form-control" value="<?= $editProduct['original_price'] ?? '' ?>" min="0" step="100">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Frais de livraison (FCFA)</label>
                                    <input type="number" name="delivery_fee" class="form-control" value="<?= $editProduct['delivery_fee'] ?? 0 ?>" min="0" step="100">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Stock</label>
                                    <input type="number" name="stock" class="form-control" value="<?= $editProduct['stock'] ?>" min="0">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Catégorie</label>
                                    <select name="category_id" class="form-select">
                                        <option value="">Sélectionner</option>
                                        <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= $editProduct['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                                            <?= escape($cat['icon'] . ' ' . $cat['name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Statut</label>
                                    <select name="status" class="form-select">
                                        <option value="active" <?= $editProduct['status'] === 'active' ? 'selected' : '' ?>>✅ Actif (visible)</option>
                                        <option value="inactive" <?= $editProduct['status'] === 'inactive' ? 'selected' : '' ?>>🔒 Inactif (masqué)</option>
                                    </select>
                                </div>
                                <div class="form-group" style="grid-column:1/-1">
                                    <label class="form-label">Description générale</label>
                                    <textarea name="description" class="form-control" rows="3"><?= escape($editProduct['description'] ?? '') ?></textarea>
                                </div>
                            </div>
                            <div>
                                <label class="form-label">Photo principale</label>
                                <div class="upload-zone" id="uploadZone" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)" ondrop="handleDrop(event)">
                                    <input type="file" name="image" id="imageInput" accept="image/jpeg,image/png,image/webp" onchange="previewImage(this)">
                                    <?php if ($editProduct['image']): ?>
                                    <div class="upload-preview" id="uploadPreview">
                                        <img id="previewImg" src="<?= getProductImageUrl($editProduct['image']) ?>" alt="Photo actuelle">
                                        <span class="upload-preview-remove" onclick="removePreview(event)">×</span>
                                    </div>
                                    <div id="uploadPlaceholder" style="display:none">
                                    <?php else: ?>
                                    <div id="uploadPreview" style="display:none">
                                        <img id="previewImg" src="" alt="Aperçu">
                                        <span class="upload-preview-remove" onclick="removePreview(event)">×</span>
                                    </div>
                                    <div id="uploadPlaceholder">
                                    <?php endif; ?>
                                        <div class="upload-zone-icon">🖼️</div>
                                        <div class="upload-zone-text">Changer la photo</div>
                                        <div class="upload-zone-hint">JPG, PNG, WEBP — max 5 Mo</div>
                                    </div>
                                </div>
                                <p style="font-size:.75rem;color:var(--gray-500);margin-top:.5rem">Laisser vide pour conserver l'actuelle.</p>

                                <!-- Galerie d'images (edit) -->
                                <?php
                                $existingGallery = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order");
                                $existingGallery->execute([$editId]);
                                $existingGallery = $existingGallery->fetchAll();
                                ?>
                                <label class="form-label" style="margin-top:1rem">Photos galerie <span style="font-size:.75rem;color:var(--gray-500)">(défilent en slider)</span></label>
                                <?php if (!empty($existingGallery)): ?>
                                <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:.6rem">
                                    <?php foreach ($existingGallery as $gi): ?>
                                    <div style="position:relative;width:70px;height:70px">
                                        <img src="<?= getProductImageUrl($gi['file_name']) ?>" style="width:70px;height:70px;object-fit:cover;border-radius:8px;border:1.5px solid var(--gray-200)">
                                        <label style="position:absolute;top:-5px;right:-5px;background:#EF4444;color:white;border-radius:50%;width:18px;height:18px;display:flex;align-items:center;justify-content:center;font-size:.65rem;cursor:pointer;line-height:1">
                                            <input type="checkbox" name="delete_gallery_images[]" value="<?= $gi['id'] ?>" style="display:none">×
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                    <div style="font-size:.72rem;color:var(--gray-400);align-self:flex-end;padding-bottom:.3rem">Cocher × pour supprimer</div>
                                </div>
                                <?php endif; ?>
                                <div id="galleryDropZone"
                                     style="border:2px dashed var(--gray-200);border-radius:var(--radius-sm);padding:1rem;text-align:center;cursor:pointer;background:var(--gray-50);transition:border-color .2s"
                                     onclick="document.getElementById('galleryFileInput').click()"
                                     ondragover="event.preventDefault();this.style.borderColor='var(--primary)'"
                                     ondragleave="this.style.borderColor='var(--gray-200)'"
                                     ondrop="handleGalleryDrop(event)">
                                    <input type="file" id="galleryFileInput" accept="image/jpeg,image/png,image/webp" multiple style="display:none" onchange="handleGalleryFiles(this.files)">
                                    <div style="font-size:1.5rem;margin-bottom:.3rem">🖼️</div>
                                    <div style="font-size:.8rem;color:var(--gray-500)">Ajouter des photos (plusieurs à la fois)</div>
                                </div>
                                <div id="galleryPreviewGrid" style="display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.6rem"></div>
                                <div id="galleryHiddenInputs"></div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2 : Description santé -->
                    <div id="tab-health" class="tab-panel" style="display:none">
                        <div style="display:flex;flex-direction:column;gap:1.5rem">
                            <div class="health-section-card" style="border-left-color:#EF4444">
                                <div class="health-section-header">
                                    <span class="health-section-icon" style="background:#FEE2E2">😣</span>
                                    <div>
                                        <div class="health-section-title">Problèmes du patient</div>
                                        <div class="health-section-subtitle">Symptômes et maux que ce produit traite</div>
                                    </div>
                                </div>
                                <textarea name="patient_problems" class="form-control" rows="5"
                                          placeholder="Ex:&#10;• Douleurs articulaires&#10;• Fatigue chronique&#10;• Troubles digestifs"><?= escape($editProduct['patient_problems'] ?? '') ?></textarea>
                                <?= healthImageUpload('problems_image', 'prob', '#FEE2E2', '#B91C1C', $editProduct['problems_image'] ?? null) ?>
                                <?php if (!empty($editProduct['problems_image'])): ?>
                                <label style="font-size:.75rem;color:var(--danger);margin-top:.25rem;display:flex;align-items:center;gap:.4rem">
                                    <input type="checkbox" name="del_problems_image"> Supprimer cette image
                                </label>
                                <?php endif; ?>
                                <div style="margin-top:.6rem">
                                    <label class="form-label" style="font-size:.78rem">Taille d'affichage : <span id="eprob_w_val"><?= $editProduct['problems_image_width'] ?? 100 ?></span>%</label>
                                    <input type="range" name="problems_image_width" min="10" max="100" step="5"
                                           value="<?= $editProduct['problems_image_width'] ?? 100 ?>"
                                           style="width:100%;accent-color:#B91C1C" oninput="document.getElementById('eprob_w_val').textContent=this.value">
                                </div>
                            </div>
                            <div class="health-section-card" style="border-left-color:#10B981">
                                <div class="health-section-header">
                                    <span class="health-section-icon" style="background:#D1FAE5">✅</span>
                                    <div>
                                        <div class="health-section-title">Avantages du produit</div>
                                        <div class="health-section-subtitle">Bienfaits et effets bénéfiques</div>
                                    </div>
                                </div>
                                <textarea name="advantages" class="form-control" rows="5"
                                          placeholder="Ex:&#10;• Soulage rapidement&#10;• 100% naturel&#10;• Sans effets secondaires"><?= escape($editProduct['advantages'] ?? '') ?></textarea>
                                <?= healthImageUpload('advantages_image', 'adv', '#D1FAE5', '#065F46', $editProduct['advantages_image'] ?? null) ?>
                                <?php if (!empty($editProduct['advantages_image'])): ?>
                                <label style="font-size:.75rem;color:var(--danger);margin-top:.25rem;display:flex;align-items:center;gap:.4rem">
                                    <input type="checkbox" name="del_advantages_image"> Supprimer cette image
                                </label>
                                <?php endif; ?>
                                <div style="margin-top:.6rem">
                                    <label class="form-label" style="font-size:.78rem">Taille d'affichage : <span id="eadv_w_val"><?= $editProduct['advantages_image_width'] ?? 100 ?></span>%</label>
                                    <input type="range" name="advantages_image_width" min="10" max="100" step="5"
                                           value="<?= $editProduct['advantages_image_width'] ?? 100 ?>"
                                           style="width:100%;accent-color:#065F46" oninput="document.getElementById('eadv_w_val').textContent=this.value">
                                </div>
                            </div>
                            <div class="health-section-card" style="border-left-color:#7C3AED">
                                <div class="health-section-header">
                                    <span class="health-section-icon" style="background:#EDE9FE">💊</span>
                                    <div>
                                        <div class="health-section-title">Posologie / Mode d'emploi</div>
                                        <div class="health-section-subtitle">Doses, fréquences et précautions</div>
                                    </div>
                                </div>
                                <textarea name="posologie" class="form-control" rows="5"
                                          placeholder="Ex:&#10;• 2 gélules matin et soir&#10;• Avec un grand verre d'eau&#10;• Cure de 30 jours"><?= escape($editProduct['posologie'] ?? '') ?></textarea>
                                <?= healthImageUpload('posologie_image', 'pos', '#EDE9FE', '#5B21B6', $editProduct['posologie_image'] ?? null) ?>
                                <?php if (!empty($editProduct['posologie_image'])): ?>
                                <label style="font-size:.75rem;color:var(--danger);margin-top:.25rem;display:flex;align-items:center;gap:.4rem">
                                    <input type="checkbox" name="del_posologie_image"> Supprimer cette image
                                </label>
                                <?php endif; ?>
                                <div style="margin-top:.6rem">
                                    <label class="form-label" style="font-size:.78rem">Taille d'affichage : <span id="epos_w_val"><?= $editProduct['posologie_image_width'] ?? 100 ?></span>%</label>
                                    <input type="range" name="posologie_image_width" min="10" max="100" step="5"
                                           value="<?= $editProduct['posologie_image_width'] ?? 100 ?>"
                                           style="width:100%;accent-color:#5B21B6" oninput="document.getElementById('epos_w_val').textContent=this.value">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 3 : Témoignages -->
                    <div id="tab-testimonials" class="tab-panel" style="display:none">
                        <!-- Témoignages existants -->
                        <?php if (!empty($existingTestimonials)): ?>
                        <div style="margin-bottom:1.5rem">
                            <div style="font-weight:700;color:var(--dark);margin-bottom:.75rem">Témoignages actuels</div>
                            <div class="testimonials-preview-grid">
                                <?php foreach ($existingTestimonials as $t): ?>
                                <div class="testimonial-item-existing" style="position:relative">
                                    <?php if ($t['file_type'] === 'video'): ?>
                                        <video src="<?= SITE_URL ?>/uploads/testimonials/<?= escape($t['file_name']) ?>"
                                               style="width:100%;height:130px;object-fit:cover;border-radius:var(--radius-sm)" controls></video>
                                    <?php else: ?>
                                        <img src="<?= SITE_URL ?>/uploads/testimonials/<?= escape($t['file_name']) ?>"
                                             style="width:100%;height:130px;object-fit:cover;border-radius:var(--radius-sm)" alt="">
                                    <?php endif; ?>
                                    <?php if ($t['caption']): ?>
                                        <div style="font-size:.75rem;color:var(--gray-500);margin-top:.3rem;text-align:center"><?= escape($t['caption']) ?></div>
                                    <?php endif; ?>
                                    <label style="position:absolute;top:.4rem;right:.4rem;background:rgba(239,68,68,.9);color:white;border-radius:6px;padding:.2rem .5rem;font-size:.72rem;cursor:pointer;display:flex;align-items:center;gap:.3rem">
                                        <input type="checkbox" name="delete_testimonials[]" value="<?= $t['id'] ?>" style="accent-color:white">
                                        Supprimer
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Ajouter de nouveaux -->
                        <div style="font-weight:700;color:var(--dark);margin-bottom:.75rem">Ajouter de nouveaux témoignages</div>
                        <div class="testimonial-upload-zone" id="testimonialsZone"
                             onclick="document.getElementById('testimonialsFileInput').click()"
                             ondragover="handleTestimonialDragOver(event)"
                             ondragleave="handleTestimonialDragLeave(event)"
                             ondrop="handleTestimonialDrop(event)">
                            <input type="file" id="testimonialsFileInput"
                                   accept="image/*,video/*,.mov,.avi,.mkv"
                                   multiple style="display:none"
                                   onchange="handleTestimonialsSelected(this.files)">
                            <div>
                                <div style="font-size:2.5rem;margin-bottom:.5rem">📸</div>
                                <div style="font-weight:700;color:var(--gray-700);margin-bottom:.25rem">Cliquer ou glisser</div>
                                <div style="font-size:.8rem;color:var(--gray-500)">Images & Vidéos — max 50 Mo</div>
                            </div>
                        </div>
                        <div id="testimonialsPreviewGrid" class="testimonials-preview-grid"></div>
                        <div id="testimonialHiddenInputs"></div>
                    </div>

                </div>

                <div style="display:flex;gap:1rem;justify-content:flex-end;padding:1rem 1.5rem;border-top:1px solid var(--gray-100);background:var(--gray-100)">
                    <a href="?section=products" class="btn btn-secondary">Annuler</a>
                    <button type="submit" class="btn btn-primary" id="submitProductBtn">💾 Enregistrer les modifications</button>
                </div>
            </form>
        </div>

        <!-- STORE SETTINGS -->
        <?php elseif ($section === 'livraison'): ?>
        <?php
        // Read-only: contacts gérés par l'admin depuis global_delivery_contacts
        $deliveries = $pdo->query("SELECT * FROM global_delivery_contacts WHERE is_active=1 ORDER BY zone, contact_name")->fetchAll();

        $byZone = [];
        foreach ($deliveries as $d) {
            $byZone[$d['zone']][] = $d;
        }
        ?>

        <div class="dashboard-header">
            <div>
                <div class="dashboard-title">🚚 Contacts de livraison</div>
                <div style="color:var(--gray-500);font-size:.88rem">Livreurs disponibles par zone — gérés par l'administrateur</div>
            </div>
        </div>

        <?php if (empty($deliveries)): ?>
        <div style="text-align:center;padding:4rem 1rem;background:white;border-radius:var(--radius);border:2px dashed var(--gray-200)">
            <div style="font-size:3.5rem;margin-bottom:.75rem">🚚</div>
            <h3 style="font-weight:700;color:var(--dark);margin-bottom:.4rem">Aucun livreur disponible</h3>
            <p style="color:var(--gray-500);font-size:.9rem">L'administrateur n'a pas encore ajouté de contacts de livraison.</p>
        </div>

        <?php else: ?>

        <!-- Cartes par zone -->
        <?php foreach ($byZone as $zoneName => $livreurs): ?>
        <div style="margin-bottom:1.5rem">
            <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.75rem">
                <span style="background:linear-gradient(135deg,#7C3AED,#EC4899);color:white;padding:.25rem .85rem;border-radius:20px;font-size:.8rem;font-weight:700">
                    📍 <?= escape($zoneName) ?>
                </span>
                <span style="font-size:.78rem;color:var(--gray-400)"><?= count($livreurs) ?> livreur<?= count($livreurs) > 1 ? 's' : '' ?></span>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem">
                <?php foreach ($livreurs as $d): ?>
                <div style="background:white;border-radius:var(--radius);border:1.5px solid var(--gray-100);padding:1.15rem;display:flex;flex-direction:column;gap:.6rem;transition:box-shadow .2s"
                     onmouseover="this.style.boxShadow='0 4px 16px rgba(0,0,0,.08)'" onmouseout="this.style.boxShadow='none'">
                    <div style="display:flex;align-items:center;gap:.75rem">
                        <div style="width:42px;height:42px;background:linear-gradient(135deg,#EDE9FE,#DDD6FE);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0">
                            🧑
                        </div>
                        <div>
                            <div style="font-weight:700;font-size:.95rem;color:var(--dark)"><?= escape($d['contact_name']) ?></div>
                            <a href="tel:<?= escape($d['phone']) ?>"
                               style="font-size:.88rem;color:var(--primary);font-weight:600;text-decoration:none;display:flex;align-items:center;gap:.3rem">
                                📞 <?= escape($d['phone']) ?>
                            </a>
                        </div>
                    </div>
                    <?php if ($d['note']): ?>
                    <div style="font-size:.78rem;color:var(--gray-500);background:var(--gray-100);border-radius:6px;padding:.4rem .65rem;line-height:1.5">
                        <?= escape($d['note']) ?>
                    </div>
                    <?php endif; ?>
                    <div style="display:flex;gap:.5rem;margin-top:.2rem">
                        <a href="tel:<?= escape($d['phone']) ?>"
                           style="flex:1;text-align:center;padding:.4rem;background:#EDE9FE;color:#7C3AED;border-radius:6px;font-size:.78rem;font-weight:700;text-decoration:none">
                            📞 Appeler
                        </a>
                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/','',$d['phone']) ?>" target="_blank"
                           style="flex:1;text-align:center;padding:.4rem;background:#DCFCE7;color:#16A34A;border-radius:6px;font-size:.78rem;font-weight:700;text-decoration:none">
                            💬 WhatsApp
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Tableau récapitulatif -->
        <div style="margin-top:2rem">
            <div style="font-weight:700;color:var(--dark);margin-bottom:.75rem;font-size:.95rem">📋 Récapitulatif complet</div>
            <div class="card" style="overflow:hidden">
                <table style="width:100%;border-collapse:collapse">
                    <thead>
                        <tr style="background:var(--gray-100)">
                            <th style="padding:.65rem 1rem;text-align:left;font-size:.78rem;color:var(--gray-500);font-weight:600">Zone</th>
                            <th style="padding:.65rem 1rem;text-align:left;font-size:.78rem;color:var(--gray-500);font-weight:600">Livreur</th>
                            <th style="padding:.65rem 1rem;text-align:left;font-size:.78rem;color:var(--gray-500);font-weight:600">Téléphone</th>
                            <th style="padding:.65rem 1rem;text-align:left;font-size:.78rem;color:var(--gray-500);font-weight:600">Note</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($deliveries as $d): ?>
                    <tr style="border-top:1px solid var(--gray-100)">
                        <td style="padding:.7rem 1rem">
                            <span style="background:#EDE9FE;color:#7C3AED;padding:.2rem .65rem;border-radius:20px;font-size:.75rem;font-weight:700">
                                <?= escape($d['zone']) ?>
                            </span>
                        </td>
                        <td style="padding:.7rem 1rem;font-weight:600;font-size:.87rem"><?= escape($d['contact_name']) ?></td>
                        <td style="padding:.7rem 1rem">
                            <a href="tel:<?= escape($d['phone']) ?>" style="color:var(--primary);font-weight:600;font-size:.87rem;text-decoration:none"><?= escape($d['phone']) ?></a>
                        </td>
                        <td style="padding:.7rem 1rem;font-size:.8rem;color:var(--gray-500)"><?= escape($d['note'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php elseif ($section === 'abonnement'): ?>
        <?php
        // Traitement "J'ai déjà payé"
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notify_payment'])) {
            // Vérifier qu'il n'y a pas déjà une demande en attente
            $alreadyPending = $pdo->prepare("SELECT id FROM subscriptions WHERE store_id = ? AND status = 'pending'");
            $alreadyPending->execute([$storeId]);
            if (!$alreadyPending->fetch()) {
                $pdo->prepare("INSERT INTO subscriptions (store_id, payment_method, amount, status, created_at) VALUES (?, 'wave', ?, 'pending', NOW())")
                    ->execute([$storeId, SUBSCRIPTION_PRICE]);
                setFlash('success', 'Votre paiement a été signalé. L\'admin va valider sous 24h.');
            } else {
                setFlash('info', 'Vous avez déjà une demande en attente de validation.');
            }
            redirect(SITE_URL . '/dashboard.php?section=abonnement');
        }

        $confirmedPays = $pdo->prepare("SELECT * FROM subscriptions WHERE store_id = ? AND status='confirmed' ORDER BY created_at DESC LIMIT 5");
        $confirmedPays->execute([$storeId]);
        $confirmedPays = $confirmedPays->fetchAll();

        // Vérifier si une demande est déjà en attente
        $pendingSub = $pdo->prepare("SELECT * FROM subscriptions WHERE store_id = ? AND status = 'pending' ORDER BY created_at DESC LIMIT 1");
        $pendingSub->execute([$storeId]);
        $pendingSub = $pendingSub->fetch();
        ?>

        <div class="dashboard-header">
            <div>
                <div class="dashboard-title">💳 Mon abonnement</div>
                <div style="color:var(--gray-500);font-size:.88rem">Gérez votre abonnement mensuel WecanShop</div>
            </div>
        </div>

        <!-- Statut actuel -->
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:2rem">
            <div style="background:white;border-radius:var(--radius);padding:1.25rem;border:1.5px solid var(--gray-100);text-align:center">
                <div style="font-size:1.8rem;font-weight:900;color:<?= $sub['needs_payment'] ? '#DC2626' : ($sub['in_trial'] ? '#7C3AED' : '#10B981') ?>">
                    <?= $sub['is_subscribed'] ? '✅ Actif' : ($sub['in_trial'] ? '🎁 Essai' : '⛔ Expiré') ?>
                </div>
                <div style="font-size:.78rem;color:var(--gray-500);margin-top:.3rem">Statut</div>
            </div>
            <div style="background:white;border-radius:var(--radius);padding:1.25rem;border:1.5px solid var(--gray-100);text-align:center">
                <div style="font-size:1.8rem;font-weight:900;color:var(--primary)"><?= $sub['order_count'] ?>/<?= FREE_ORDER_LIMIT ?></div>
                <div style="font-size:.78rem;color:var(--gray-500);margin-top:.3rem">Commandes gratuites</div>
            </div>
            <div style="background:white;border-radius:var(--radius);padding:1.25rem;border:1.5px solid var(--gray-100);text-align:center">
                <div style="font-size:1.8rem;font-weight:900;color:var(--dark)">
                    <?= $sub['is_subscribed'] ? date('d/m/Y', strtotime($sub['sub_end_date'])) : '—' ?>
                </div>
                <div style="font-size:.78rem;color:var(--gray-500);margin-top:.3rem">Expire le</div>
            </div>
        </div>


        <!-- Paiement -->
        <div style="max-width:520px">

            <!-- Paiement -->
            <div style="background:white;border-radius:var(--radius-lg);border:1.5px solid var(--gray-100);overflow:hidden">
                <div style="padding:1.25rem 1.5rem;border-bottom:1px solid var(--gray-100);display:flex;align-items:center;gap:.75rem">
                    <span style="background:#00B4E6;color:white;border-radius:8px;padding:.3rem .6rem;font-size:.95rem">📱</span>
                    <div>
                        <div style="font-weight:800;font-size:1rem;color:var(--dark)">Paiement par Wave</div>
                        <div style="font-size:.8rem;color:var(--gray-500);margin-top:.1rem">3 000 FCFA / mois</div>
                    </div>
                </div>

                <div style="padding:1.5rem">

                    <?php if ($pendingSub): ?>
                    <!-- Demande déjà envoyée -->
                    <div style="text-align:center;padding:2rem 1rem">
                        <div style="font-size:3rem;margin-bottom:1rem">⏳</div>
                        <div style="font-weight:800;font-size:1.1rem;color:var(--dark);margin-bottom:.5rem">Paiement en cours de validation</div>
                        <div style="font-size:.85rem;color:var(--gray-500);max-width:300px;margin:0 auto">
                            Votre paiement a été signalé le <?= date('d/m/Y à H:i', strtotime($pendingSub['created_at'])) ?>.<br>
                            L'admin validera votre abonnement sous 24h.
                        </div>
                    </div>

                    <?php else: ?>
                    <!-- Étape 1 : Bouton Payer -->
                    <div id="stepPay" style="text-align:center;padding:2rem 1rem">
                        <div style="font-size:3.5rem;margin-bottom:1rem">💳</div>
                        <div style="font-weight:800;font-size:1.1rem;color:var(--dark);margin-bottom:.5rem">Souscrire à l'abonnement</div>
                        <div style="font-size:.85rem;color:var(--gray-500);margin-bottom:1.75rem">
                            Cliquez sur le bouton pour afficher le QR code Wave et effectuer votre paiement.
                        </div>
                        <button onclick="showQR()" style="background:linear-gradient(135deg,#00B4E6,#0077C8);color:white;border:none;padding:.9rem 2.5rem;border-radius:50px;font-weight:800;font-size:1rem;cursor:pointer;box-shadow:0 4px 18px rgba(0,180,230,.35)">
                            💳 Payer maintenant — 3 000 FCFA
                        </button>
                    </div>

                    <!-- Étape 2 : QR Code + bouton confirmation -->
                    <div id="stepQR" style="display:none;text-align:center">
                        <div style="font-size:.85rem;font-weight:700;color:#00B4E6;margin-bottom:.75rem;text-transform:uppercase;letter-spacing:.05em">
                            📱 Scannez avec l'app Wave
                        </div>
                        <div style="border-radius:16px;overflow:hidden;width:260px;box-shadow:0 6px 24px rgba(0,180,230,.3);margin:0 auto">
                            <iframe src="<?= SITE_URL ?>/assets/images/wave_qr.pdf#toolbar=0&navpanes=0&scrollbar=0&view=FitH"
                                    style="width:260px;height:340px;border:none;display:block"
                                    title="QR Code Wave"></iframe>
                        </div>
                        <div style="margin-top:1.25rem;font-size:.82rem;color:var(--gray-500);margin-bottom:1.5rem">
                            Après le paiement, cliquez sur le bouton ci-dessous pour notifier l'admin.
                        </div>
                        <form method="POST" onsubmit="return confirm('Confirmez-vous avoir effectué le paiement de 3 000 FCFA par Wave ?')">
                            <input type="hidden" name="notify_payment" value="1">
                            <button type="submit" style="background:linear-gradient(135deg,#10B981,#059669);color:white;border:none;padding:.85rem 2rem;border-radius:50px;font-weight:800;font-size:.95rem;cursor:pointer;box-shadow:0 4px 16px rgba(16,185,129,.35)">
                                ✅ J'ai déjà payé — Notifier l'admin
                            </button>
                        </form>
                        <button onclick="hideQR()" style="background:none;border:none;color:var(--gray-400);font-size:.82rem;cursor:pointer;margin-top:.75rem;text-decoration:underline">
                            Annuler
                        </button>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>

        <!-- Historique des paiements -->
        <?php if (!empty($confirmedPays)): ?>
        <div style="margin-top:2rem">
            <div style="font-weight:700;color:var(--dark);margin-bottom:.75rem">Historique des paiements</div>
            <div class="card" style="overflow:hidden">
            <table style="width:100%;border-collapse:collapse">
                <thead><tr style="background:var(--gray-100)">
                    <th style="padding:.6rem 1rem;text-align:left;font-size:.78rem;color:var(--gray-500)">Date</th>
                    <th style="padding:.6rem 1rem;text-align:left;font-size:.78rem;color:var(--gray-500)">Méthode</th>
                    <th style="padding:.6rem 1rem;text-align:left;font-size:.78rem;color:var(--gray-500)">Référence</th>
                    <th style="padding:.6rem 1rem;text-align:left;font-size:.78rem;color:var(--gray-500)">Période</th>
                    <th style="padding:.6rem 1rem;text-align:left;font-size:.78rem;color:var(--gray-500)">Montant</th>
                </tr></thead>
                <tbody>
                <?php foreach ($confirmedPays as $cp): ?>
                <tr style="border-top:1px solid var(--gray-100)">
                    <td style="padding:.75rem 1rem;font-size:.83rem"><?= date('d/m/Y', strtotime($cp['created_at'])) ?></td>
                    <td style="padding:.75rem 1rem;font-size:.83rem"><?= match($cp['payment_method']){
                        'wave'=>'📱 Wave','orange_money'=>'🟠 Orange Money','card'=>'💳 Carte', default=>$cp['payment_method']
                    } ?></td>
                    <td style="padding:.75rem 1rem;font-size:.78rem;color:var(--gray-500)"><?= escape($cp['payment_reference'] ?? '—') ?></td>
                    <td style="padding:.75rem 1rem;font-size:.78rem;color:var(--gray-500)">
                        <?= date('d/m/Y', strtotime($cp['period_start'])) ?> → <?= date('d/m/Y', strtotime($cp['period_end'])) ?>
                    </td>
                    <td style="padding:.75rem 1rem;font-weight:700;font-size:.85rem;color:var(--primary)"><?= number_format($cp['amount'],0,',',' ') ?> FCFA</td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
        <?php endif; ?>

        <?php elseif ($section === 'admin_subs' && isAdmin()): ?>
        <div class="dashboard-header">
            <div>
                <div class="dashboard-title">💳 Gestion des abonnements</div>
                <div style="color:var(--gray-500);font-size:.88rem">Confirmez ou rejetez les paiements des vendeurs</div>
            </div>
        </div>
        <?php
        $allSubs = $pdo->query("
            SELECT s.*, st.name as store_name, u.email as user_email, u.name as user_name
            FROM subscriptions s
            JOIN stores st ON s.store_id = st.id
            JOIN users u ON st.user_id = u.id
            ORDER BY FIELD(s.status,'pending','confirmed','rejected'), s.created_at DESC
            LIMIT 100
        ")->fetchAll();
        ?>
        <?php if (empty($allSubs)): ?>
        <div style="text-align:center;padding:4rem;color:var(--gray-400)">Aucun abonnement soumis pour le moment.</div>
        <?php else: ?>
        <div class="card" style="overflow:hidden">
            <table style="width:100%;border-collapse:collapse">
                <thead>
                    <tr style="background:var(--gray-100)">
                        <th style="padding:.75rem 1rem;text-align:left;font-size:.8rem;color:var(--gray-500);font-weight:600">Boutique / Vendeur</th>
                        <th style="padding:.75rem 1rem;text-align:left;font-size:.8rem;color:var(--gray-500);font-weight:600">Méthode</th>
                        <th style="padding:.75rem 1rem;text-align:left;font-size:.8rem;color:var(--gray-500);font-weight:600">Référence / Téléphone</th>
                        <th style="padding:.75rem 1rem;text-align:left;font-size:.8rem;color:var(--gray-500);font-weight:600">Montant</th>
                        <th style="padding:.75rem 1rem;text-align:left;font-size:.8rem;color:var(--gray-500);font-weight:600">Soumis le</th>
                        <th style="padding:.75rem 1rem;text-align:left;font-size:.8rem;color:var(--gray-500);font-weight:600">Statut / Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($allSubs as $row): ?>
                <tr style="border-top:1px solid var(--gray-100)">
                    <td style="padding:.85rem 1rem">
                        <div style="font-weight:600;font-size:.88rem"><?= escape($row['store_name']) ?></div>
                        <div style="font-size:.75rem;color:var(--gray-500)"><?= escape($row['user_email']) ?></div>
                    </td>
                    <td style="padding:.85rem 1rem">
                        <span style="font-size:.82rem;font-weight:600">
                            <?= match($row['payment_method']) {
                                'wave'         => '📱 Wave',
                                'orange_money' => '🟠 Orange Money',
                                'card'         => '💳 Carte',
                                default        => $row['payment_method']
                            } ?>
                        </span>
                    </td>
                    <td style="padding:.85rem 1rem;font-size:.8rem">
                        <div><?= escape($row['payment_reference'] ?? '—') ?></div>
                        <div style="color:var(--gray-500)"><?= escape($row['payment_phone'] ?? '') ?></div>
                    </td>
                    <td style="padding:.85rem 1rem;font-weight:700;font-size:.88rem;color:var(--primary)"><?= number_format($row['amount'],0,',',' ') ?> FCFA</td>
                    <td style="padding:.85rem 1rem;font-size:.78rem;color:var(--gray-500)"><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                    <td style="padding:.85rem 1rem">
                        <?php if ($row['status'] === 'pending'): ?>
                        <form method="POST" style="display:flex;gap:.4rem;align-items:center;flex-wrap:wrap">
                            <input type="hidden" name="sub_id" value="<?= $row['id'] ?>">
                            <button name="confirm_sub" value="confirm" type="submit"
                                    style="padding:.35rem .85rem;background:#10B981;color:white;border:none;border-radius:6px;font-size:.78rem;font-weight:700;cursor:pointer"
                                    onclick="return confirm('Confirmer et activer cet abonnement ?')">✓ Confirmer</button>
                            <button name="confirm_sub" value="reject" type="submit"
                                    style="padding:.35rem .85rem;background:#EF4444;color:white;border:none;border-radius:6px;font-size:.78rem;font-weight:700;cursor:pointer"
                                    onclick="return confirm('Rejeter ce paiement ?')">✗ Rejeter</button>
                        </form>
                        <?php elseif ($row['status'] === 'confirmed'): ?>
                        <span style="background:#D1FAE5;color:#065F46;padding:.25rem .75rem;border-radius:20px;font-size:.75rem;font-weight:700">✓ Confirmé</span>
                        <div style="font-size:.72rem;color:var(--gray-400);margin-top:.2rem">
                            <?= date('d/m/Y', strtotime($row['period_start'])) ?> → <?= date('d/m/Y', strtotime($row['period_end'])) ?>
                        </div>
                        <?php else: ?>
                        <span style="background:#FEE2E2;color:#991B1B;padding:.25rem .75rem;border-radius:20px;font-size:.75rem;font-weight:700">✗ Rejeté</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php elseif ($section === 'pixel'): ?>
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_pixel'])) {
            $pixelId = trim($_POST['facebook_pixel_id'] ?? '');
            $pixelId = preg_replace('/[^0-9]/', '', $pixelId); // seulement les chiffres
            $pdo->prepare("UPDATE stores SET facebook_pixel_id = ? WHERE id = ?")
                ->execute([$pixelId ?: null, $storeId]);
            setFlash('success', 'Pixel Facebook enregistré !');
            redirect(SITE_URL . '/dashboard.php?section=pixel');
        }
        // Recharger la boutique pour avoir la valeur à jour
        $storePixel = $pdo->prepare("SELECT facebook_pixel_id FROM stores WHERE id = ?");
        $storePixel->execute([$storeId]);
        $currentPixelId = $storePixel->fetchColumn();
        ?>
        <div class="dashboard-header">
            <div class="dashboard-title">Pixel Facebook</div>
        </div>

        <div class="card" style="margin-bottom:1.5rem">
            <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;padding-bottom:1.25rem;border-bottom:1px solid var(--gray-100)">
                <div style="width:52px;height:52px;background:#1877F2;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="white"><path d="M18 3a3 3 0 00-3 3v12a3 3 0 003 3 3 3 0 003-3 3 3 0 00-3-3H6a3 3 0 00-3 3 3 3 0 003 3 3 3 0 003-3V6a3 3 0 00-3-3 3 3 0 00-3 3 3 3 0 003 3h12a3 3 0 003-3 3 3 0 00-3-3z"/></svg>
                </div>
                <div>
                    <div style="font-weight:700;font-size:1.05rem;color:var(--dark)">Connecter votre Pixel Meta (Facebook)</div>
                    <div style="font-size:.85rem;color:var(--gray-500);margin-top:.2rem">Suivez vos visiteurs et mesurez vos conversions publicitaires</div>
                </div>
            </div>

            <form method="POST" action="?section=pixel">
                <input type="hidden" name="save_pixel" value="1">

                <div class="form-group">
                    <label class="form-label" style="font-weight:700">ID du Pixel Facebook</label>
                    <input type="text" name="facebook_pixel_id" class="form-control"
                           placeholder="Ex : 1234567890123456"
                           value="<?= escape($currentPixelId ?? '') ?>"
                           style="font-size:1.05rem;letter-spacing:.05em">
                    <div style="font-size:.8rem;color:var(--gray-500);margin-top:.5rem">
                        Saisissez uniquement les chiffres de votre Pixel ID (15–16 chiffres)
                    </div>
                </div>

                <div style="background:#EFF6FF;border:1px solid #BFDBFE;border-radius:var(--radius-sm);padding:1rem;margin-bottom:1.5rem">
                    <div style="font-weight:700;font-size:.88rem;color:#1D4ED8;margin-bottom:.6rem">📍 Comment trouver votre Pixel ID ?</div>
                    <ol style="font-size:.82rem;color:#1E40AF;margin:0;padding-left:1.2rem;line-height:2">
                        <li>Connectez-vous à <strong>business.facebook.com</strong></li>
                        <li>Allez dans <strong>Gestionnaire d'événements</strong></li>
                        <li>Sélectionnez votre Pixel dans la liste</li>
                        <li>Copiez l'<strong>ID du Pixel</strong> affiché en haut</li>
                    </ol>
                </div>

                <?php if ($currentPixelId): ?>
                <div style="background:#F0FDF4;border:1px solid #BBF7D0;border-radius:var(--radius-sm);padding:.85rem 1rem;margin-bottom:1.25rem;display:flex;align-items:center;gap:.75rem">
                    <span style="font-size:1.2rem">✅</span>
                    <div>
                        <div style="font-weight:700;font-size:.85rem;color:#15803D">Pixel actif</div>
                        <div style="font-size:.8rem;color:#166534">ID : <strong><?= escape($currentPixelId) ?></strong> — le code se déclenche sur chaque page produit</div>
                    </div>
                </div>
                <?php endif; ?>

                <div style="display:flex;gap:.75rem;justify-content:flex-end">
                    <?php if ($currentPixelId): ?>
                    <button type="button" class="btn btn-outline"
                            onclick="if(confirm('Supprimer le pixel ?')){document.getElementById('pixelIdInput').value='';document.querySelector('[name=save_pixel]').closest('form').submit()}"
                            style="color:#EF4444;border-color:#EF4444">
                        Supprimer le pixel
                    </button>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary">
                        💾 Enregistrer
                    </button>
                </div>
            </form>
        </div>

        <!-- Événements trackés -->
        <div class="card">
            <div style="font-weight:700;font-size:.95rem;color:var(--dark);margin-bottom:1rem">📊 Événements suivis automatiquement</div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:.75rem">
                <div style="background:var(--gray-50);border-radius:var(--radius-sm);padding:.85rem;border-left:3px solid #1877F2">
                    <div style="font-weight:700;font-size:.88rem;color:var(--dark)">PageView</div>
                    <div style="font-size:.78rem;color:var(--gray-500);margin-top:.2rem">Chaque visite sur une page produit</div>
                </div>
                <div style="background:var(--gray-50);border-radius:var(--radius-sm);padding:.85rem;border-left:3px solid #10B981">
                    <div style="font-weight:700;font-size:.88rem;color:var(--dark)">ViewContent</div>
                    <div style="font-size:.78rem;color:var(--gray-500);margin-top:.2rem">Consultation d'un produit</div>
                </div>
                <div style="background:var(--gray-50);border-radius:var(--radius-sm);padding:.85rem;border-left:3px solid #F59E0B">
                    <div style="font-weight:700;font-size:.88rem;color:var(--dark)">Purchase</div>
                    <div style="font-size:.78rem;color:var(--gray-500);margin-top:.2rem">Confirmation de commande</div>
                </div>
            </div>
        </div>

        <?php elseif ($section === 'store_settings'): ?>
        <div class="dashboard-header">
            <div class="dashboard-title">Paramètres de la boutique</div>
        </div>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_store'])) {
            $sName = trim($_POST['store_name'] ?? '');
            $sDesc = trim($_POST['store_description'] ?? '');
            if ($sName) {
                $pdo->prepare("UPDATE stores SET name = ?, description = ? WHERE id = ?")
                    ->execute([$sName, $sDesc, $storeId]);
                setFlash('success', 'Boutique mise à jour !');
                redirect(SITE_URL . '/dashboard.php?section=store_settings');
            }
        }
        ?>

        <div class="card">
            <form method="POST" action="?section=store_settings">
                <input type="hidden" name="update_store" value="1">
                <div class="form-group">
                    <label class="form-label">Nom de la boutique *</label>
                    <input type="text" name="store_name" class="form-control"
                           value="<?= escape($store['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="store_description" class="form-control" rows="4"><?= escape($store['description'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">URL de votre boutique</label>
                    <div style="display:flex;align-items:center;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);overflow:hidden">
                        <input type="text" id="storeUrlInput"
                               value="<?= escape(SITE_URL . '/shop.php?store=' . $store['slug']) ?>"
                               class="form-control" style="border:none;color:var(--primary);font-size:.88rem" readonly>
                        <button type="button" onclick="copyStoreUrl()" id="copyUrlBtn"
                                style="flex-shrink:0;padding:.7rem 1rem;background:var(--primary);color:white;border:none;cursor:pointer;font-size:.82rem;font-weight:700;white-space:nowrap;transition:background .2s">
                            📋 Copier
                        </button>
                    </div>
                </div>
                <div style="display:flex;justify-content:flex-end">
                    <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </main>
</div>

<script>
const siteUrl = "<?= SITE_URL ?>";

// ---- Copier l'URL de la boutique ----
function copyStoreUrl() {
    const input = document.getElementById('storeUrlInput');
    navigator.clipboard.writeText(input.value).then(() => {
        const btn = document.getElementById('copyUrlBtn');
        btn.textContent = '✅ Copié !';
        btn.style.background = '#10B981';
        setTimeout(() => { btn.textContent = '📋 Copier'; btn.style.background = ''; }, 2000);
    });
}

// ---- Abonnement : QR Code ----
function showQR() {
    document.getElementById('stepPay').style.display = 'none';
    document.getElementById('stepQR').style.display  = 'block';
}
function hideQR() {
    document.getElementById('stepQR').style.display  = 'none';
    document.getElementById('stepPay').style.display = 'block';
}

// ---- Onglets ----
function switchTab(tabId, btn) {
    document.querySelectorAll('.tab-panel').forEach(p => p.style.display = 'none');
    document.querySelectorAll('.form-tab').forEach(b => b.classList.remove('active'));
    document.getElementById(tabId).style.display = 'block';
    btn.classList.add('active');
}

// ---- Témoignages (upload AJAX) ----
const T_UPLOAD_URL = siteUrl + '/api/testimonials_upload.php';
const T_VIDEO_EXTS = ['mp4','webm','mov','avi','mkv'];
let tUploadCount = 0; // pour les IDs uniques

function isVideoFile(file) {
    if (file.type && file.type.startsWith('video/')) return true;
    const ext = file.name.split('.').pop().toLowerCase();
    return T_VIDEO_EXTS.includes(ext);
}

function handleTestimonialsSelected(files) {
    Array.from(files).forEach(f => uploadOneTestimonial(f));
    // Réinitialiser l'input pour permettre de re-sélectionner le même fichier
    const inp = document.getElementById('testimonialsFileInput');
    if (inp) inp.value = '';
}

function uploadOneTestimonial(file) {
    const id  = 'titem_' + (++tUploadCount);
    const isVid = isVideoFile(file);

    // Créer l'item de prévisualisation immédiatement
    const grid = document.getElementById('testimonialsPreviewGrid');
    if (!grid) return;

    const item = document.createElement('div');
    item.className = 'testimonial-preview-item';
    item.id = id;

    // Spinner de chargement (remplacé une fois uploadé)
    item.innerHTML = `
        <div style="height:130px;display:flex;align-items:center;justify-content:center;background:var(--gray-100)">
            <div style="text-align:center">
                <div style="font-size:1.5rem;margin-bottom:.25rem">${isVid ? '🎥' : '🖼️'}</div>
                <div style="font-size:.75rem;color:var(--gray-500)">Envoi en cours…</div>
            </div>
        </div>`;
    grid.appendChild(item);

    const formData = new FormData();
    formData.append('file', file);

    fetch(T_UPLOAD_URL, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                item.innerHTML = `
                    <div style="height:130px;display:flex;align-items:center;justify-content:center;background:#FEF2F2;padding:.5rem;text-align:center">
                        <div>
                            <div style="font-size:1.2rem">⚠️</div>
                            <div style="font-size:.72rem;color:var(--danger)">${data.error || 'Erreur upload'}</div>
                        </div>
                    </div>
                    <span class="testimonial-remove" onclick="removeTestimonialItem('${id}')">×</span>`;
                return;
            }
            // Succès : afficher la prévisualisation
            renderUploadedTestimonial(item, data, id);
        })
        .catch(() => {
            item.innerHTML = `
                <div style="height:130px;display:flex;align-items:center;justify-content:center;background:#FEF2F2">
                    <div style="font-size:.72rem;color:var(--danger);text-align:center;padding:.5rem">Erreur réseau</div>
                </div>
                <span class="testimonial-remove" onclick="removeTestimonialItem('${id}')">×</span>`;
        });
}

function renderUploadedTestimonial(item, data, id) {
    const isVid = data.type === 'video';

    // Ajouter un champ caché pour stocker le nom de fichier
    const hiddenContainer = document.getElementById('testimonialHiddenInputs');
    if (hiddenContainer) {
        const hFile    = document.createElement('input');
        hFile.type     = 'hidden';
        hFile.name     = 'uploaded_testimonials[]';
        hFile.value    = data.filename;
        hFile.id       = 'hfile_' + id;

        const hCaption = document.createElement('input');
        hCaption.type  = 'hidden';
        hCaption.name  = 'uploaded_captions[]';
        hCaption.value = '';
        hCaption.id    = 'hcap_' + id;

        hiddenContainer.appendChild(hFile);
        hiddenContainer.appendChild(hCaption);
    }

    let mediaHtml = '';
    if (isVid) {
        mediaHtml = `<video src="${data.url}" controls muted preload="metadata"
            style="width:100%;height:130px;object-fit:cover;display:block;background:#000"></video>`;
    } else {
        mediaHtml = `<img src="${data.url}" alt=""
            style="width:100%;height:130px;object-fit:cover;display:block">`;
    }

    item.innerHTML = `
        ${mediaHtml}
        <span class="testimonial-type-badge">${isVid ? '🎥 Vidéo' : '🖼️ Photo'}</span>
        <span class="testimonial-remove" onclick="removeTestimonialItem('${id}')">×</span>
        <input type="text" placeholder="Légende (optionnel)"
               oninput="document.getElementById('hcap_${id}').value=this.value"
               style="width:100%;border:none;border-top:1px solid var(--gray-100);padding:.4rem .6rem;font-size:.75rem;font-family:inherit;outline:none;background:white">`;
}

function removeTestimonialItem(id) {
    document.getElementById(id)?.remove();
    document.getElementById('hfile_' + id)?.remove();
    document.getElementById('hcap_'  + id)?.remove();
}

function handleTestimonialDragOver(e) {
    e.preventDefault();
    document.getElementById('testimonialsZone')?.classList.add('drag-over');
}
function handleTestimonialDragLeave() {
    document.getElementById('testimonialsZone')?.classList.remove('drag-over');
}
function handleTestimonialDrop(e) {
    e.preventDefault();
    document.getElementById('testimonialsZone')?.classList.remove('drag-over');
    handleTestimonialsSelected(e.dataTransfer.files);
}

// ---- Images sections santé ----
function previewHealthImg(input, uid) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        const prev  = document.getElementById('hprev_' + uid);
        const img   = prev.querySelector('img') || document.getElementById('himg_' + uid);
        const label = document.getElementById('hlabel_' + uid);
        if (img) img.src = e.target.result;
        if (prev) prev.style.display = 'flex';
        if (label) label.style.display = 'none';
    };
    reader.readAsDataURL(input.files[0]);
}

function removeHealthImg(uid) {
    const prev  = document.getElementById('hprev_' + uid);
    const label = document.getElementById('hlabel_' + uid);
    const file  = document.getElementById('hfile_' + uid);
    if (prev)  { prev.style.display = 'none'; const img = prev.querySelector('img'); if(img) img.src=''; }
    if (label) label.style.display = '';
    if (file)  file.value = '';
}

// ---- Upload image preview ----
function previewImage(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('previewImg').src = e.target.result;
        document.getElementById('uploadPreview').style.display = 'block';
        document.getElementById('uploadPlaceholder').style.display = 'none';
    };
    reader.readAsDataURL(input.files[0]);
}

function removePreview(e) {
    e.stopPropagation();
    document.getElementById('previewImg').src = '';
    document.getElementById('uploadPreview').style.display = 'none';
    document.getElementById('uploadPlaceholder').style.display = 'block';
    document.getElementById('imageInput').value = '';
}

function handleDragOver(e) {
    e.preventDefault();
    document.getElementById('uploadZone').classList.add('drag-over');
}

function handleDragLeave(e) {
    document.getElementById('uploadZone').classList.remove('drag-over');
}

function handleDrop(e) {
    e.preventDefault();
    document.getElementById('uploadZone').classList.remove('drag-over');
    const file = e.dataTransfer.files[0];
    if (file && file.type.startsWith('image/')) {
        const input = document.getElementById('imageInput');
        const dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
        previewImage(input);
    }
}

// ---- Orders ----
function updateOrderStatus(orderId, status) {
    fetch(`${siteUrl}/api/orders.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'update_status', order_id: orderId, status })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) showToast('Statut mis à jour');
        else showToast('Erreur', 'error');
    });
}
</script>


<!-- ===================== PANNEAU ASSISTANT IA ===================== -->
<div id="aiOverlay" onclick="closeAiPanel()"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9000;backdrop-filter:blur(2px)"></div>

<div id="aiPanel"
     style="display:none;position:fixed;top:0;right:0;width:420px;max-width:100vw;height:100vh;
            background:white;z-index:9001;box-shadow:-8px 0 40px rgba(0,0,0,.2);
            display:flex;flex-direction:column;transform:translateX(100%);transition:transform .3s cubic-bezier(.4,0,.2,1)">

    <!-- Header -->
    <div style="padding:1.25rem 1.5rem;background:linear-gradient(135deg,#7C3AED,#EC4899);color:white;display:flex;align-items:center;justify-content:space-between;flex-shrink:0">
        <div>
            <div style="font-weight:800;font-size:1.05rem">✨ Assistant IA</div>
            <div style="font-size:.78rem;opacity:.8;margin-top:.1rem">Génération automatique de fiche produit</div>
        </div>
        <button onclick="closeAiPanel()" style="background:rgba(255,255,255,.2);border:none;color:white;border-radius:50%;width:32px;height:32px;font-size:1.1rem;cursor:pointer;display:flex;align-items:center;justify-content:center">×</button>
    </div>

    <!-- Input zone -->
    <div style="padding:1.25rem 1.5rem;border-bottom:1px solid #F3F4F6;flex-shrink:0">
        <label style="font-size:.82rem;font-weight:600;color:#374151;display:block;margin-bottom:.4rem">Nom du produit</label>
        <div style="display:flex;gap:.5rem">
            <input type="text" id="aiProductInput" placeholder="Ex: Tisane Détox Bio, Café Minceur..."
                   style="flex:1;border:1.5px solid #E5E7EB;border-radius:8px;padding:.55rem .85rem;font-size:.9rem;outline:none"
                   onfocus="this.style.borderColor='#7C3AED'" onblur="this.style.borderColor='#E5E7EB'"
                   onkeydown="if(event.key==='Enter')generateAiContent()">
            <button onclick="generateAiContent()" id="aiGenerateBtn"
                    style="padding:.55rem 1.1rem;background:linear-gradient(135deg,#7C3AED,#EC4899);color:white;border:none;border-radius:8px;font-size:.85rem;font-weight:700;cursor:pointer;white-space:nowrap">
                Générer
            </button>
        </div>
        <div id="aiError" style="display:none;margin-top:.5rem;font-size:.78rem;color:#DC2626;background:#FEF2F2;border-radius:6px;padding:.4rem .7rem"></div>
    </div>

    <!-- Résultats -->
    <div id="aiResults" style="flex:1;overflow-y:auto;padding:1.25rem 1.5rem;display:none">

        <div id="aiBlock_description" class="ai-block">
            <div class="ai-block-header">
                <span>📝 Description générale</span>
                <button onclick="aiCopyTo('description','aiVal_description')" class="ai-copy-btn">Copier → formulaire</button>
            </div>
            <div id="aiVal_description" class="ai-block-content" contenteditable="true" spellcheck="false"></div>
        </div>

        <div id="aiBlock_problems" class="ai-block">
            <div class="ai-block-header">
                <span>😣 Problèmes traités</span>
                <button onclick="aiCopyTo('problems','aiVal_problems')" class="ai-copy-btn">Copier → formulaire</button>
            </div>
            <div id="aiVal_problems" class="ai-block-content" contenteditable="true" spellcheck="false"></div>
        </div>

        <div id="aiBlock_advantages" class="ai-block">
            <div class="ai-block-header">
                <span>✅ Avantages</span>
                <button onclick="aiCopyTo('advantages','aiVal_advantages')" class="ai-copy-btn">Copier → formulaire</button>
            </div>
            <div id="aiVal_advantages" class="ai-block-content" contenteditable="true" spellcheck="false"></div>
        </div>

        <div id="aiBlock_posologie" class="ai-block">
            <div class="ai-block-header">
                <span>💊 Posologie / Mode d'emploi</span>
                <button onclick="aiCopyTo('posologie','aiVal_posologie')" class="ai-copy-btn">Copier → formulaire</button>
            </div>
            <div id="aiVal_posologie" class="ai-block-content" contenteditable="true" spellcheck="false"></div>
        </div>

        <button onclick="aiApplyAll()"
                style="width:100%;margin-top:.5rem;padding:.75rem;background:linear-gradient(135deg,#7C3AED,#EC4899);color:white;border:none;border-radius:10px;font-size:.9rem;font-weight:800;cursor:pointer;letter-spacing:.01em">
            ✅ Tout appliquer dans le formulaire
        </button>
    </div>

    <!-- Loader -->
    <div id="aiLoader" style="display:none;flex:1;flex-direction:column;align-items:center;justify-content:center;gap:1rem;padding:2rem">
        <div style="width:48px;height:48px;border:4px solid #EDE9FE;border-top-color:#7C3AED;border-radius:50%;animation:spin 1s linear infinite"></div>
        <div style="font-size:.92rem;font-weight:600;color:#6B7280;text-align:center">
            L'IA rédige votre fiche produit…<br>
            <span style="font-size:.78rem;font-weight:400">Cela prend ~5 secondes</span>
        </div>
    </div>
</div>

<style>
.ai-block { margin-bottom:1rem;border:1.5px solid #E5E7EB;border-radius:10px;overflow:hidden; }
.ai-block-header {
    display:flex;align-items:center;justify-content:space-between;
    padding:.5rem .85rem;background:#F9FAFB;border-bottom:1px solid #E5E7EB;
    font-size:.8rem;font-weight:700;color:#374151;
}
.ai-copy-btn {
    padding:.28rem .75rem;background:#7C3AED;color:white;border:none;border-radius:20px;
    font-size:.73rem;font-weight:700;cursor:pointer;transition:background .15s;
}
.ai-copy-btn:hover { background:#6D28D9; }
.ai-block-content {
    padding:.75rem .85rem;font-size:.84rem;line-height:1.65;color:#1F2937;
    min-height:60px;white-space:pre-wrap;outline:none;
}
.ai-block-content:focus { background:#FAFAFA; }
</style>

<script>
const AI_URL = siteUrl + '/api/ai_product.php';

function openAiPanel() {
    const nameVal = document.getElementById('addProductName')?.value?.trim() || '';
    document.getElementById('aiProductInput').value = nameVal;
    document.getElementById('aiOverlay').style.display = 'block';
    const panel = document.getElementById('aiPanel');
    panel.style.display = 'flex';
    requestAnimationFrame(() => { panel.style.transform = 'translateX(0)'; });
    document.getElementById('aiProductInput').focus();
}

function closeAiPanel() {
    const panel = document.getElementById('aiPanel');
    panel.style.transform = 'translateX(100%)';
    setTimeout(() => {
        panel.style.display = 'none';
        document.getElementById('aiOverlay').style.display = 'none';
    }, 300);
}

async function generateAiContent() {
    const name = document.getElementById('aiProductInput').value.trim();
    if (!name) { document.getElementById('aiProductInput').focus(); return; }

    document.getElementById('aiError').style.display = 'none';
    document.getElementById('aiResults').style.display = 'none';
    document.getElementById('aiLoader').style.display = 'flex';
    document.getElementById('aiGenerateBtn').disabled = true;
    document.getElementById('aiGenerateBtn').textContent = '…';

    try {
        const fd = new FormData();
        fd.append('product_name', name);
        const res = await fetch(AI_URL, { method: 'POST', body: fd });
        const json = await res.json();

        if (!json.success) throw new Error(json.error || 'Erreur inconnue');

        const d = json.data;
        document.getElementById('aiVal_description').textContent = d.description || '';
        document.getElementById('aiVal_problems').textContent   = d.problems    || '';
        document.getElementById('aiVal_advantages').textContent = d.advantages  || '';
        document.getElementById('aiVal_posologie').textContent  = d.posologie   || '';

        document.getElementById('aiLoader').style.display = 'none';
        document.getElementById('aiResults').style.display = 'block';

    } catch(e) {
        document.getElementById('aiLoader').style.display = 'none';
        const errEl = document.getElementById('aiError');
        errEl.textContent = e.message;
        errEl.style.display = 'block';
    } finally {
        document.getElementById('aiGenerateBtn').disabled = false;
        document.getElementById('aiGenerateBtn').textContent = 'Générer';
    }
}

function aiCopyTo(field, srcId) {
    const text = document.getElementById(srcId)?.textContent?.trim() || '';
    const targets = {
        description : () => document.querySelector('[name="description"]'),
        problems    : () => document.querySelector('[name="patient_problems"]'),
        advantages  : () => document.querySelector('[name="advantages"]'),
        posologie   : () => document.querySelector('[name="posologie"]'),
    };
    const el = targets[field]?.();
    if (el) {
        el.value = text;
        showToast('Copié dans le formulaire ✓');
        // Switch to health tab if needed
        if (['problems','advantages','posologie'].includes(field)) {
            const healthBtn = [...document.querySelectorAll('.form-tab')]
                .find(b => b.getAttribute('onclick')?.includes('tab-health'));
            if (healthBtn) switchTab('tab-health', healthBtn);
        }
    }
}

function aiApplyAll() {
    aiCopyTo('description','aiVal_description');
    aiCopyTo('problems','aiVal_problems');
    aiCopyTo('advantages','aiVal_advantages');
    aiCopyTo('posologie','aiVal_posologie');
    // Also copy name
    const aiName = document.getElementById('aiProductInput').value.trim();
    const nameEl = document.getElementById('addProductName');
    if (aiName && nameEl && !nameEl.value) nameEl.value = aiName;
    closeAiPanel();
    showToast('Tout le contenu a été appliqué ✅');
}

// ===== GALERIE D'IMAGES =====
function handleGalleryFiles(files) {
    Array.from(files).forEach(file => uploadGalleryImage(file));
    document.getElementById('galleryFileInput').value = '';
}
function handleGalleryDrop(e) {
    e.preventDefault();
    document.getElementById('galleryDropZone').style.borderColor = 'var(--gray-200)';
    handleGalleryFiles(e.dataTransfer.files);
}
async function uploadGalleryImage(file) {
    const fd = new FormData();
    fd.append('file', file);
    const previewGrid = document.getElementById('galleryPreviewGrid');
    // Placeholder
    const placeholder = document.createElement('div');
    placeholder.style.cssText = 'width:64px;height:64px;border-radius:8px;background:var(--gray-100);display:flex;align-items:center;justify-content:center;font-size:1.2rem;border:1.5px dashed var(--gray-300)';
    placeholder.textContent = '⏳';
    previewGrid.appendChild(placeholder);

    try {
        const res = await fetch('<?= SITE_URL ?>/api/upload_gallery_image.php', { method:'POST', body:fd });
        const data = await res.json();
        if (!data.success) { placeholder.textContent = '❌'; return; }

        // Remplacer placeholder par aperçu
        placeholder.innerHTML = `<img src="${data.url}" style="width:64px;height:64px;object-fit:cover;border-radius:8px;border:1.5px solid var(--gray-200)">
            <span onclick="removeGalleryItem(this,'${data.filename}')" style="position:absolute;top:-5px;right:-5px;background:#EF4444;color:white;border-radius:50%;width:18px;height:18px;display:flex;align-items:center;justify-content:center;font-size:.65rem;cursor:pointer;line-height:1">×</span>`;
        placeholder.style.cssText = 'width:64px;height:64px;border-radius:8px;position:relative';

        // Champ caché
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'uploaded_gallery_images[]';
        hidden.value = data.filename;
        hidden.dataset.filename = data.filename;
        document.getElementById('galleryHiddenInputs').appendChild(hidden);
    } catch(e) { placeholder.textContent = '❌'; }
}
function removeGalleryItem(btn, filename) {
    btn.closest('div').remove();
    document.querySelectorAll('[name="uploaded_gallery_images[]"]').forEach(inp => {
        if (inp.dataset.filename === filename) inp.remove();
    });
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
ob_start();
$pageTitle = 'Dashboard Vendeur';
require_once __DIR__ . '/includes/header.php';
requireSeller();

// Get seller's store
$stmt = $pdo->prepare("SELECT * FROM stores WHERE user_id = ? LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$store = $stmt->fetch();

if (!$store) {
    setFlash('error', 'Boutique introuvable.');
    redirect(SITE_URL . '/index.php');
}

$storeId = $store['id'];

// Stats
$totalProducts = $pdo->prepare("SELECT COUNT(*) FROM products WHERE store_id = ?");
$totalProducts->execute([$storeId]);
$totalProducts = (int)$totalProducts->fetchColumn();

$totalOrders = $pdo->prepare("
    SELECT COUNT(DISTINCT oi.order_id) FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE p.store_id = ?
");
$totalOrders->execute([$storeId]);
$totalOrders = (int)$totalOrders->fetchColumn();

$revenue = $pdo->prepare("
    SELECT COALESCE(SUM(oi.price * oi.quantity), 0)
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE p.store_id = ? AND o.status != 'cancelled'
");
$revenue->execute([$storeId]);
$revenue = (float)$revenue->fetchColumn();

$pendingOrders = $pdo->prepare("
    SELECT COUNT(DISTINCT oi.order_id) FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE p.store_id = ? AND o.status = 'pending'
");
$pendingOrders->execute([$storeId]);
$pendingOrders = (int)$pendingOrders->fetchColumn();

// Recent Orders
$recentOrders = $pdo->prepare("
    SELECT DISTINCT o.*, GROUP_CONCAT(oi.product_name SEPARATOR ', ') as products
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.store_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT 10
");
$recentOrders->execute([$storeId]);
$recentOrders = $recentOrders->fetchAll();

// Products
$products = $pdo->prepare("
    SELECT p.*, c.name as category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.store_id = ?
    ORDER BY p.created_at DESC
    LIMIT 10
");
$products->execute([$storeId]);
$products = $products->fetchAll();

// Categories for add product form
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

$section = $_GET['section'] ?? 'overview';

// Vérification abonnement
$sub = getStoreSubscription($storeId);

// Admin : confirmation de paiement
if (isAdmin() && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_sub'])) {
    $subId    = (int)$_POST['sub_id'];
    $action   = $_POST['confirm_sub'];
    $subStmt  = $pdo->prepare("SELECT * FROM subscriptions WHERE id = ?");
    $subStmt->execute([$subId]);
    $subRow   = $subStmt->fetch();
    if ($subRow) {
        if ($action === 'confirm') {
            $start = date('Y-m-d');
            $end   = date('Y-m-d', strtotime('+30 days'));
            $pdo->prepare("UPDATE subscriptions SET status='confirmed', period_start=?, period_end=?, confirmed_at=NOW() WHERE id=?")
                ->execute([$start, $end, $subId]);
            $pdo->prepare("UPDATE stores SET subscription_status='active', subscription_end_date=? WHERE id=?")
                ->execute([$end, $subRow['store_id']]);
            setFlash('success', 'Abonnement activé avec succès.');
        } else {
            $pdo->prepare("UPDATE subscriptions SET status='rejected', admin_note=? WHERE id=?")->execute([$_POST['admin_note'] ?? '', $subId]);
            setFlash('error', 'Paiement rejeté.');
        }
        redirect(SITE_URL . '/dashboard.php?section=admin_subs');
    }
}

// Expiration automatique
if ($sub['sub_status'] === 'active' && $sub['sub_end_date'] && $sub['sub_end_date'] < date('Y-m-d')) {
    $pdo->prepare("UPDATE stores SET subscription_status='expired' WHERE id=?")->execute([$storeId]);
    $sub['sub_status']  = 'expired';
    $sub['is_subscribed'] = false;
    $sub['needs_payment'] = $sub['order_count'] >= FREE_ORDER_LIMIT;
}
?>

<div class="dashboard-layout">
    <!-- Sidebar -->
    <aside class="dashboard-sidebar">
        <div style="padding:1.25rem 1.25rem .5rem;border-bottom:1px solid rgba(255,255,255,.1);margin-bottom:.5rem">
            <div style="font-weight:700;color:white;font-size:.95rem"><?= escape($store['name']) ?></div>
            <div style="font-size:.78rem;color:rgba(255,255,255,.4)">Tableau de bord vendeur</div>
        </div>

        <span class="sidebar-section-label">Navigation</span>
        <a href="?section=overview" class="sidebar-link <?= $section === 'overview' ? 'active' : '' ?>">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            Vue d'ensemble
        </a>
        <a href="?section=products" class="sidebar-link <?= $section === 'products' ? 'active' : '' ?>">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>
            Mes produits
            <span style="margin-left:auto;background:rgba(255,255,255,.15);padding:.1rem .5rem;border-radius:20px;font-size:.75rem"><?= $totalProducts ?></span>
        </a>
        <a href="?section=orders" class="sidebar-link <?= $section === 'orders' ? 'active' : '' ?>">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
            Commandes
            <?php if ($pendingOrders > 0): ?>
                <span style="margin-left:auto;background:#EF4444;padding:.1rem .5rem;border-radius:20px;font-size:.75rem;color:white"><?= $pendingOrders ?></span>
            <?php endif; ?>
        </a>
        <a href="?section=add_product" class="sidebar-link <?= $section === 'add_product' ? 'active' : '' ?>">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
            Ajouter un produit
        </a>

        <a href="?section=abonnement" class="sidebar-link <?= $section === 'abonnement' ? 'active' : '' ?>"
           style="<?= $sub['needs_payment'] ? 'background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);' : '' ?>">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
            Abonnement
            <?php if ($sub['needs_payment']): ?>
                <span style="margin-left:auto;background:#EF4444;padding:.1rem .5rem;border-radius:20px;font-size:.7rem;color:white">!</span>
            <?php elseif ($sub['is_subscribed']): ?>
                <span style="margin-left:auto;background:#10B981;padding:.1rem .5rem;border-radius:20px;font-size:.7rem;color:white">✓</span>
            <?php endif; ?>
        </a>

        <a href="?section=livraison" class="sidebar-link <?= $section === 'livraison' ? 'active' : '' ?>">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 5v3h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
            Livraison
        </a>
        <a href="?section=pixel" class="sidebar-link <?= $section === 'pixel' ? 'active' : '' ?>">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 3a3 3 0 00-3 3v12a3 3 0 003 3 3 3 0 003-3 3 3 0 00-3-3H6a3 3 0 00-3 3 3 3 0 003 3 3 3 0 003-3V6a3 3 0 00-3-3 3 3 0 00-3 3 3 3 0 003 3h12a3 3 0 003-3 3 3 0 00-3-3z"/></svg>
            Pixel Facebook
        </a>

        <span class="sidebar-section-label">Boutique</span>
        <a href="?section=store_settings" class="sidebar-link <?= $section === 'store_settings' ? 'active' : '' ?>">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
            Paramètres boutique
        </a>
        <a href="<?= SITE_URL ?>/index.php" class="sidebar-link">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
            Accueil
        </a>

        <?php if (isAdmin()): ?>
        <span class="sidebar-section-label">Admin</span>
        <a href="?section=admin_subs" class="sidebar-link <?= $section === 'admin_subs' ? 'active' : '' ?>">
            💳 Abonnements
            <?php
            $pendingCount = $pdo->query("SELECT COUNT(*) FROM subscriptions WHERE status='pending'")->fetchColumn();
            if ($pendingCount > 0): ?>
            <span style="margin-left:auto;background:#EF4444;padding:.1rem .5rem;border-radius:20px;font-size:.75rem;color:white"><?= $pendingCount ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>

        <!-- Statut abonnement dans sidebar -->
        <div style="margin:1rem .75rem .5rem;padding:.85rem;border-radius:var(--radius-sm);background:<?= $sub['needs_payment'] ? '#7F1D1D' : ($sub['in_trial'] ? 'rgba(124,58,237,.3)' : 'rgba(16,185,129,.2)') ?>">
            <div style="font-size:.72rem;font-weight:700;color:rgba(255,255,255,.6);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.3rem">Abonnement</div>
            <?php if ($sub['needs_payment']): ?>
                <div style="font-size:.82rem;color:#FCA5A5;font-weight:600">⚠️ Paiement requis</div>
                <a href="<?= SITE_URL ?>/subscribe.php" style="display:block;margin-top:.5rem;text-align:center;padding:.4rem;background:#EF4444;color:white;border-radius:6px;font-size:.75rem;font-weight:700;text-decoration:none">Souscrire — 3 000 FCFA</a>
            <?php elseif ($sub['is_subscribed']): ?>
                <div style="font-size:.82rem;color:#6EE7B7;font-weight:600">✅ Actif jusqu'au</div>
                <div style="font-size:.78rem;color:rgba(255,255,255,.8)"><?= date('d/m/Y', strtotime($sub['sub_end_date'])) ?></div>
            <?php else: ?>
                <div style="font-size:.82rem;color:rgba(255,255,255,.8);font-weight:600">Essai gratuit</div>
                <div style="font-size:.75rem;color:rgba(255,255,255,.55)"><?= $sub['order_count'] ?>/<?= FREE_ORDER_LIMIT ?> commandes</div>
                <div style="margin-top:.4rem;background:rgba(255,255,255,.15);border-radius:20px;height:4px;overflow:hidden">
                    <div style="height:100%;width:<?= min(100, round($sub['order_count']/FREE_ORDER_LIMIT*100)) ?>%;background:#A78BFA;border-radius:20px"></div>
                </div>
            <?php endif; ?>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="dashboard-main">
        <?= renderFlash() ?>

        <?php if ($sub['needs_payment']): ?>
        <!-- MURO : abonnement requis -->
        <div style="background:linear-gradient(135deg,#1E1B4B,#7C3AED,#EC4899);border-radius:var(--radius-lg);padding:2.5rem;text-align:center;margin-bottom:2rem;color:white">
            <div style="font-size:3.5rem;margin-bottom:.75rem">🔒</div>
            <h2 style="font-size:1.5rem;font-weight:900;margin-bottom:.5rem">Votre essai gratuit est terminé</h2>
            <p style="opacity:.8;margin-bottom:1.5rem;max-width:500px;margin-left:auto;margin-right:auto;font-size:.92rem">
                Vous avez atteint les <strong><?= FREE_ORDER_LIMIT ?> commandes gratuites</strong>. Pour continuer à vendre et gérer votre boutique, souscrivez à l'abonnement mensuel.
            </p>
            <a href="<?= SITE_URL ?>/subscribe.php" style="display:inline-block;background:white;color:#7C3AED;padding:.85rem 2.5rem;border-radius:50px;font-weight:800;font-size:1rem;text-decoration:none;box-shadow:0 4px 20px rgba(0,0,0,.2)">
                Souscrire maintenant — 3 000 FCFA/mois
            </a>
            <div style="font-size:.78rem;opacity:.6;margin-top:1rem">Wave · Orange Money · Carte bancaire</div>
        </div>
        <?php elseif (!$sub['in_trial'] && $sub['is_subscribed']): ?>
        <!-- Abonnement actif — rappel de renouvellement si proche -->
        <?php $daysLeft = (int)((strtotime($sub['sub_end_date']) - time()) / 86400); ?>
        <?php if ($daysLeft <= 5): ?>
        <div style="background:#FFF7ED;border:1.5px solid #FED7AA;border-radius:var(--radius);padding:1rem 1.5rem;margin-bottom:1.5rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem">
            <div style="display:flex;align-items:center;gap:.75rem">
                <span style="font-size:1.5rem">⏰</span>
                <div>
                    <strong style="color:#92400E">Abonnement expire dans <?= $daysLeft ?> jour<?= $daysLeft > 1 ? 's' : '' ?></strong>
                    <div style="font-size:.8rem;color:#B45309">Renouvelez maintenant pour éviter toute interruption de service.</div>
                </div>
            </div>
            <a href="<?= SITE_URL ?>/subscribe.php" class="btn btn-primary" style="padding:.5rem 1.25rem;font-size:.85rem">Renouveler</a>
        </div>
        <?php endif; ?>
        <?php elseif ($sub['in_trial'] && $sub['trial_remaining'] <= 3 && $sub['trial_remaining'] > 0): ?>
        <!-- Essai presque épuisé -->
        <div style="background:#EDE9FE;border:1.5px solid #C4B5FD;border-radius:var(--radius);padding:1rem 1.5rem;margin-bottom:1.5rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem">
            <div style="display:flex;align-items:center;gap:.75rem">
                <span style="font-size:1.5rem">💡</span>
                <div>
                    <strong style="color:#5B21B6">Plus que <?= $sub['trial_remaining'] ?> commande<?= $sub['trial_remaining'] > 1 ? 's' : '' ?> gratuites</strong>
                    <div style="font-size:.8rem;color:#7C3AED">Préparez votre abonnement pour continuer à vendre sans interruption.</div>
                </div>
            </div>
            <a href="<?= SITE_URL ?>/subscribe.php" class="btn btn-primary" style="padding:.5rem 1.25rem;font-size:.85rem">Voir les plans</a>
        </div>
        <?php endif; ?>

        <!-- OVERVIEW -->
        <?php if ($section === 'overview'): ?>
        <div class="dashboard-header">
            <div>
                <div class="dashboard-title">Bonjour, <?= escape(explode(' ', $_SESSION['user_name'])[0]) ?> 👋</div>
                <div style="color:var(--gray-500);font-size:.9rem">Voici le résumé de votre boutique</div>
            </div>
            <a href="?section=add_product" class="btn btn-primary">+ Ajouter un produit</a>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon purple">📦</div>
                <div>
                    <div class="stat-num"><?= $totalProducts ?></div>
                    <div class="stat-label">Produits</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">🛒</div>
                <div>
                    <div class="stat-num"><?= $totalOrders ?></div>
                    <div class="stat-label">Commandes totales</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange">💰</div>
                <div>
                    <div class="stat-num" style="font-size:1.2rem"><?= formatPrice($revenue) ?></div>
                    <div class="stat-label">Chiffre d'affaires</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon pink">⏳</div>
                <div>
                    <div class="stat-num"><?= $pendingOrders ?></div>
                    <div class="stat-label">Commandes en attente</div>
                </div>
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">Commandes récentes</div>
                <a href="?section=orders" class="btn btn-secondary btn-sm">Voir tout</a>
            </div>
            <?php if (empty($recentOrders)): ?>
            <div class="empty-state" style="padding:2rem">
                <div class="empty-state-icon" style="font-size:2.5rem">📦</div>
                <p>Aucune commande pour l'instant</p>
            </div>
            <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th><th>Client</th><th>Produits</th><th>Total</th><th>Statut</th><th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($recentOrders, 0, 5) as $order): ?>
                    <tr>
                        <td><strong>#<?= $order['id'] ?></strong></td>
                        <td><?= escape($order['customer_name']) ?></td>
                        <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= escape($order['products']) ?>"><?= escape(substr($order['products'], 0, 40)) ?>...</td>
                        <td><strong><?= formatPrice($order['total']) ?></strong></td>
                        <td><span class="badge badge-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span></td>
                        <td><?= date('d/m/Y', strtotime($order['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Recent Products -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">Mes produits récents</div>
                <a href="?section=products" class="btn btn-secondary btn-sm">Voir tout</a>
            </div>
            <?php if (empty($products)): ?>
            <div class="empty-state" style="padding:2rem">
                <div class="empty-state-icon" style="font-size:2.5rem">🛍️</div>
                <p>Aucun produit ajouté</p>
                <a href="?section=add_product" class="btn btn-primary btn-sm">Ajouter mon premier produit</a>
            </div>
            <?php else: ?>
            <table class="table">
                <thead>
                    <tr><th>Produit</th><th>Prix</th><th>Stock</th><th>Statut</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($products, 0, 5) as $p): ?>
                    <tr id="product-row-<?= $p['id'] ?>">
                        <td><strong><?= escape($p['name']) ?></strong></td>
                        <td><?= formatPrice($p['price']) ?></td>
                        <td>
                            <span style="color:<?= $p['stock'] < 5 ? 'var(--danger)' : 'var(--success)' ?>;font-weight:600">
                                <?= $p['stock'] ?>
                            </span>
                        </td>
                        <td><span class="badge badge-<?= $p['status'] ?>"><?= $p['status'] === 'active' ? 'Actif' : 'Inactif' ?></span></td>
                        <td>
                            <div style="display:flex;gap:.5rem">
                                <a href="<?= SITE_URL ?>/product.php?id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm">Voir</a>
                                <a href="?section=edit_product&id=<?= $p['id'] ?>" class="btn btn-outline btn-sm">Modifier</a>
                                <button onclick="deleteProduct(<?= $p['id'] ?>)" class="btn btn-sm" style="background:#FEE2E2;color:var(--danger)">Supprimer</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- PRODUCTS -->
        <?php elseif ($section === 'products'): ?>
        <div class="dashboard-header">
            <div class="dashboard-title">Mes produits</div>
            <a href="?section=add_product" class="btn btn-primary">+ Nouveau produit</a>
        </div>

        <div class="card">
            <?php
            $allProducts = $pdo->prepare("
                SELECT p.*, c.name as category_name
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.store_id = ?
                ORDER BY p.created_at DESC
            ");
            $allProducts->execute([$storeId]);
            $allProducts = $allProducts->fetchAll();
            ?>
            <?php if (empty($allProducts)): ?>
            <div class="empty-state" style="padding:3rem">
                <div class="empty-state-icon">📦</div>
                <h3>Aucun produit</h3>
                <p>Commencez par ajouter votre premier produit.</p>
                <a href="?section=add_product" class="btn btn-primary">Ajouter un produit</a>
            </div>
            <?php else: ?>
            <table class="table">
                <thead>
                    <tr><th>Produit</th><th>Catégorie</th><th>Prix</th><th>Stock</th><th>Vues</th><th>Statut</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($allProducts as $p): ?>
                    <tr id="product-row-<?= $p['id'] ?>">
                        <td>
                            <div style="font-weight:600"><?= escape($p['name']) ?></div>
                            <div style="font-size:.78rem;color:var(--gray-500)"><?= escape(substr($p['description'] ?? '', 0, 40)) ?>...</div>
                        </td>
                        <td><?= escape($p['category_name'] ?? '—') ?></td>
                        <td><strong><?= formatPrice($p['price']) ?></strong></td>
                        <td>
                            <span class="badge <?= $p['stock'] === 0 ? 'badge-cancelled' : ($p['stock'] < 5 ? 'badge-pending' : 'badge-active') ?>">
                                <?= $p['stock'] ?>
                            </span>
                        </td>
                        <td><?= number_format($p['views']) ?></td>
                        <td><span class="badge badge-<?= $p['status'] ?>"><?= $p['status'] === 'active' ? 'Actif' : 'Inactif' ?></span></td>
                        <td>
                            <div style="display:flex;gap:.4rem">
                                <a href="<?= SITE_URL ?>/product.php?id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm">Voir</a>
                                <a href="?section=edit_product&id=<?= $p['id'] ?>" class="btn btn-outline btn-sm">Éditer</a>
                                <button onclick="deleteProduct(<?= $p['id'] ?>)" class="btn btn-sm" style="background:#FEE2E2;color:var(--danger)">✕</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- ORDERS -->
        <?php elseif ($section === 'orders'): ?>
        <div class="dashboard-header">
            <div class="dashboard-title">Commandes</div>
        </div>

        <div class="card">
            <?php
            $allOrders = $pdo->prepare("
                SELECT DISTINCT o.*, GROUP_CONCAT(oi.product_name SEPARATOR ', ') as products,
                       SUM(oi.quantity) as total_items
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                JOIN products p ON oi.product_id = p.id
                WHERE p.store_id = ?
                GROUP BY o.id
                ORDER BY o.created_at DESC
            ");
            $allOrders->execute([$storeId]);
            $allOrders = $allOrders->fetchAll();
            ?>
            <?php if (empty($allOrders)): ?>
            <div class="empty-state" style="padding:3rem">
                <div class="empty-state-icon">🛒</div>
                <h3>Aucune commande</h3>
                <p>Vous n'avez pas encore reçu de commandes.</p>
            </div>
            <?php else: ?>
            <table class="table">
                <thead>
                    <tr><th>#</th><th>Client</th><th>Produits</th><th>Total</th><th>Paiement</th><th>Statut</th><th>Date</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($allOrders as $order): ?>
                    <tr>
                        <td><strong>#<?= $order['id'] ?></strong></td>
                        <td>
                            <div style="font-weight:600"><?= escape($order['customer_name']) ?></div>
                            <div style="font-size:.78rem;color:var(--gray-500)"><?= escape($order['customer_phone'] ?? '') ?></div>
                        </td>
                        <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= escape($order['products']) ?></td>
                        <td><strong><?= formatPrice($order['total']) ?></strong></td>
                        <td><?= ucfirst(str_replace('_', ' ', $order['payment_method'])) ?></td>
                        <td>
                            <select onchange="updateOrderStatus(<?= $order['id'] ?>, this.value)" class="form-select" style="padding:.3rem .6rem;font-size:.8rem;width:auto">
                                <?php foreach (['pending','confirmed','shipping','delivered','cancelled'] as $s): ?>
                                <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
                        <td>
                            <div style="font-size:.78rem;color:var(--gray-500)">
                                📍 <?= escape($order['city']) ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- ADD PRODUCT -->
        <?php elseif ($section === 'add_product'): ?>
        <div class="dashboard-header">
            <div class="dashboard-title">Ajouter un produit</div>
            <a href="?section=products" class="btn btn-secondary">← Retour</a>
        </div>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
            $pName       = trim($_POST['name'] ?? '');
            $pDesc       = trim($_POST['description'] ?? '');
            $pProblems   = trim($_POST['patient_problems'] ?? '');
            $pAdvantages = trim($_POST['advantages'] ?? '');
            $pPosologie  = trim($_POST['posologie'] ?? '');
            $pPrice      = (float)($_POST['price'] ?? 0);
            $pOrigPrice  = $_POST['original_price'] !== '' ? (float)$_POST['original_price'] : null;
            $pDelivery   = (float)($_POST['delivery_fee'] ?? 0);
            $pProbW      = min(100, max(10, (int)($_POST['problems_image_width']  ?? 100)));
            $pAdvW       = min(100, max(10, (int)($_POST['advantages_image_width'] ?? 100)));
            $pPosW       = min(100, max(10, (int)($_POST['posologie_image_width']  ?? 100)));
            $pStock      = (int)($_POST['stock'] ?? 0);
            $pCatId      = (int)($_POST['category_id'] ?? 0) ?: null;
            $pStatus     = $_POST['status'] ?? 'active';

            if ($pName && $pPrice > 0) {
                $imageFile = null;
                if (!empty($_FILES['image']['name'])) {
                    $uploaded = uploadProductImage($_FILES['image']);
                    if ($uploaded === false) {
                        $addError = 'Photo principale invalide. Formats acceptés : JPG, PNG, WEBP (max 5 Mo).';
                    } else {
                        $imageFile = $uploaded;
                    }
                }

                // Images des sections santé
                $probImg = null; $advImg = null; $posImg = null;
                if (!empty($_FILES['problems_image']['name']))  $probImg = uploadProductImage($_FILES['problems_image'])  ?: null;
                if (!empty($_FILES['advantages_image']['name'])) $advImg = uploadProductImage($_FILES['advantages_image']) ?: null;
                if (!empty($_FILES['posologie_image']['name']))  $posImg = uploadProductImage($_FILES['posologie_image'])  ?: null;

                if (!isset($addError)) {
                    $slug = slugify($pName);
                    $pdo->prepare("
                        INSERT INTO products
                            (store_id, category_id, name, slug, description, patient_problems,
                             advantages, posologie, problems_image, advantages_image, posologie_image,
                             price, original_price, delivery_fee, stock, image, status,
                             problems_image_width, advantages_image_width, posologie_image_width)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ")->execute([$storeId, $pCatId, $pName, $slug, $pDesc, $pProblems,
                                 $pAdvantages, $pPosologie, $probImg, $advImg, $posImg,
                                 $pPrice, $pOrigPrice, $pDelivery, $pStock, $imageFile, $pStatus,
                                 $pProbW, $pAdvW, $pPosW]);
                    $newProductId = (int)$pdo->lastInsertId();

                    // Témoignages (uploadés via AJAX avant la soumission)
                    $uploadedFiles    = $_POST['uploaded_testimonials'] ?? [];
                    $uploadedCaptions = $_POST['uploaded_captions']     ?? [];
                    foreach ($uploadedFiles as $i => $tFile) {
                        $tFile = basename(trim($tFile)); // sécurité : pas de path traversal
                        $tPath = __DIR__ . '/uploads/testimonials/' . $tFile;
                        if (!$tFile || !file_exists($tPath)) continue;
                        $tType    = getTestimonialType($tFile);
                        $tCaption = trim($uploadedCaptions[$i] ?? '');
                        $pdo->prepare("INSERT INTO product_testimonials (product_id, file_name, file_type, caption, sort_order) VALUES (?,?,?,?,?)")
                            ->execute([$newProductId, $tFile, $tType, $tCaption, $i]);
                    }

                    setFlash('success', 'Produit "' . $pName . '" ajouté avec succès !');
                    redirect(SITE_URL . '/dashboard.php?section=products');
                }
            } else {
                $addError = 'Nom et prix sont obligatoires.';
            }
        }
        ?>

        <div class="card" style="padding:0;overflow:hidden">
            <!-- Onglets -->
            <div class="form-tabs">
                <button type="button" class="form-tab active" onclick="switchTab('tab-general', this)">
                    📦 Informations
                </button>
                <button type="button" class="form-tab" onclick="switchTab('tab-health', this)">
                    🩺 Description santé
                </button>
                <button type="button" class="form-tab" onclick="switchTab('tab-testimonials', this)">
                    🎥 Témoignages
                </button>
            </div>

            <?php if (isset($addError)): ?>
                <div class="alert alert-error" style="margin:1.5rem 1.5rem 0"><?= escape($addError) ?></div>
            <?php endif; ?>

            <form method="POST" action="?section=add_product" enctype="multipart/form-data" id="productForm">
                <input type="hidden" name="add_product" value="1">
                <div style="padding:1.5rem">

                    <!-- TAB 1 : Informations générales -->
                    <div id="tab-general" class="tab-panel">
                        <div style="display:grid;grid-template-columns:1fr 280px;gap:2rem;align-items:start">
                            <div class="form-grid-2">
                                <div class="form-group" style="grid-column:1/-1">
                                    <label class="form-label">Nom du produit *</label>
                                    <div style="display:flex;gap:.6rem;align-items:center">
                                        <input type="text" name="name" id="addProductName" class="form-control" placeholder="Ex: Tisane Détox Bio" required style="flex:1">
                                        <button type="button" onclick="openAiPanel()" id="aiBtn"
                                                style="flex-shrink:0;display:flex;align-items:center;gap:.45rem;padding:.55rem 1rem;background:linear-gradient(135deg,#7C3AED,#EC4899);color:white;border:none;border-radius:var(--radius-sm);font-size:.82rem;font-weight:700;cursor:pointer;white-space:nowrap;box-shadow:0 3px 12px rgba(124,58,237,.35);transition:opacity .2s"
                                                onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                                            ✨ Rédiger avec l'IA
                                        </button>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Prix (FCFA) *</label>
                                    <input type="number" name="price" class="form-control" placeholder="0" min="0" step="100" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Prix barré (FCFA)</label>
                                    <input type="number" name="original_price" class="form-control" placeholder="Prix avant remise" min="0" step="100">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Frais de livraison (FCFA)</label>
                                    <input type="number" name="delivery_fee" class="form-control" placeholder="Ex: 2000" min="0" step="100" value="0">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Stock disponible</label>
                                    <input type="number" name="stock" class="form-control" value="10" min="0">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Catégorie</label>
                                    <select name="category_id" class="form-select">
                                        <option value="">Sélectionner</option>
                                        <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>"><?= escape($cat['icon'] . ' ' . $cat['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Statut</label>
                                    <select name="status" class="form-select">
                                        <option value="active">✅ Actif (visible)</option>
                                        <option value="inactive">🔒 Inactif (masqué)</option>
                                    </select>
                                </div>
                                <div class="form-group" style="grid-column:1/-1">
                                    <label class="form-label">Description générale</label>
                                    <textarea name="description" class="form-control" rows="3"
                                              placeholder="Présentation courte et générale du produit..."></textarea>
                                </div>
                            </div>
                            <!-- Photo principale -->
                            <div>
                                <label class="form-label">Photo principale</label>
                                <div class="upload-zone" id="uploadZone" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)" ondrop="handleDrop(event)">
                                    <input type="file" name="image" id="imageInput" accept="image/jpeg,image/png,image/webp" onchange="previewImage(this)">
                                    <div id="uploadPlaceholder">
                                        <div class="upload-zone-icon">🖼️</div>
                                        <div class="upload-zone-text">Cliquer ou glisser</div>
                                        <div class="upload-zone-hint">JPG, PNG, WEBP — max 5 Mo</div>
                                    </div>
                                    <div class="upload-preview" id="uploadPreview" style="display:none">
                                        <img id="previewImg" src="" alt="Aperçu">
                                        <span class="upload-preview-remove" onclick="removePreview(event)">×</span>
                                    </div>
                                </div>
                                <p style="font-size:.75rem;color:var(--gray-500);margin-top:.5rem">Recommandé : carré 800×800 px minimum.</p>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2 : Description santé -->
                    <div id="tab-health" class="tab-panel" style="display:none">
                        <div style="display:flex;flex-direction:column;gap:1.5rem">

                            <div class="health-section-card" style="border-left-color:#EF4444">
                                <div class="health-section-header">
                                    <span class="health-section-icon" style="background:#FEE2E2">😣</span>
                                    <div>
                                        <div class="health-section-title">Problèmes du patient</div>
                                        <div class="health-section-subtitle">Quels maux ou symptômes ce produit traite-t-il ?</div>
                                    </div>
                                </div>
                                <textarea name="patient_problems" class="form-control" rows="5"
                                          placeholder="Ex:&#10;• Douleurs articulaires&#10;• Fatigue chronique&#10;• Troubles digestifs&#10;• Insomnie&#10;Décrivez les problèmes de santé que ce produit aide à résoudre."></textarea>
                                <?= healthImageUpload('problems_image', 'prob', '#FEE2E2', '#B91C1C') ?>
                                <div style="margin-top:.6rem">
                                    <label class="form-label" style="font-size:.78rem">Taille d'affichage : <span id="prob_w_val">100</span>%</label>
                                    <input type="range" name="problems_image_width" min="10" max="100" value="100" step="5"
                                           style="width:100%;accent-color:#B91C1C" oninput="document.getElementById('prob_w_val').textContent=this.value">
                                </div>
                            </div>

                            <div class="health-section-card" style="border-left-color:#10B981">
                                <div class="health-section-header">
                                    <span class="health-section-icon" style="background:#D1FAE5">✅</span>
                                    <div>
                                        <div class="health-section-title">Avantages du produit</div>
                                        <div class="health-section-subtitle">Quels sont les bienfaits et effets bénéfiques ?</div>
                                    </div>
                                </div>
                                <textarea name="advantages" class="form-control" rows="5"
                                          placeholder="Ex:&#10;• Soulage les douleurs rapidement&#10;• 100% naturel, sans effets secondaires&#10;• Améliore le sommeil en 7 jours&#10;• Réduit l'inflammation&#10;Listez les avantages clés de votre produit."></textarea>
                                <?= healthImageUpload('advantages_image', 'adv', '#D1FAE5', '#065F46') ?>
                                <div style="margin-top:.6rem">
                                    <label class="form-label" style="font-size:.78rem">Taille d'affichage : <span id="adv_w_val">100</span>%</label>
                                    <input type="range" name="advantages_image_width" min="10" max="100" value="100" step="5"
                                           style="width:100%;accent-color:#065F46" oninput="document.getElementById('adv_w_val').textContent=this.value">
                                </div>
                            </div>

                            <div class="health-section-card" style="border-left-color:#7C3AED">
                                <div class="health-section-header">
                                    <span class="health-section-icon" style="background:#EDE9FE">💊</span>
                                    <div>
                                        <div class="health-section-title">Posologie / Mode d'emploi</div>
                                        <div class="health-section-subtitle">Comment et quand utiliser ce produit ?</div>
                                    </div>
                                </div>
                                <textarea name="posologie" class="form-control" rows="5"
                                          placeholder="Ex:&#10;• Adultes : 2 gélules matin et soir&#10;• Prendre avec un grand verre d'eau&#10;• Cure de 30 jours recommandée&#10;• Déconseillé aux femmes enceintes&#10;Précisez les doses, fréquences et précautions d'usage."></textarea>
                                <?= healthImageUpload('posologie_image', 'pos', '#EDE9FE', '#5B21B6') ?>
                                <div style="margin-top:.6rem">
                                    <label class="form-label" style="font-size:.78rem">Taille d'affichage : <span id="pos_w_val">100</span>%</label>
                                    <input type="range" name="posologie_image_width" min="10" max="100" value="100" step="5"
                                           style="width:100%;accent-color:#5B21B6" oninput="document.getElementById('pos_w_val').textContent=this.value">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 3 : Témoignages -->
                    <div id="tab-testimonials" class="tab-panel" style="display:none">
                        <div style="font-size:1rem;font-weight:700;color:var(--dark);margin-bottom:.25rem">Photos & Vidéos de témoignage</div>
                        <div style="font-size:.85rem;color:var(--gray-500);margin-bottom:1rem">Ajoutez des preuves visuelles de l'efficacité de votre produit.</div>

                        <!-- Zone de dépôt -->
                        <div class="testimonial-upload-zone" id="testimonialsZone"
                             onclick="document.getElementById('testimonialsFileInput').click()"
                             ondragover="handleTestimonialDragOver(event)"
                             ondragleave="handleTestimonialDragLeave(event)"
                             ondrop="handleTestimonialDrop(event)">
                            <input type="file" id="testimonialsFileInput"
                                   accept="image/*,video/*,.mov,.avi,.mkv"
                                   multiple style="display:none"
                                   onchange="handleTestimonialsSelected(this.files)">
                            <div>
                                <div style="font-size:3rem;margin-bottom:.75rem">📸</div>
                                <div style="font-weight:700;color:var(--gray-700);margin-bottom:.25rem">Cliquer ou glisser des fichiers ici</div>
                                <div style="font-size:.82rem;color:var(--gray-500)">Images (JPG, PNG, WEBP) et Vidéos (MP4, WEBM, MOV, AVI) — max 50 Mo par fichier</div>
                            </div>
                        </div>

                        <!-- Grille de prévisualisations -->
                        <div id="testimonialsPreviewGrid" class="testimonials-preview-grid"></div>

                        <!-- Champs cachés ajoutés dynamiquement -->
                        <div id="testimonialHiddenInputs"></div>

                        <div style="margin-top:1.25rem;background:var(--gray-100);border-radius:var(--radius-sm);padding:1rem;font-size:.82rem;color:var(--gray-500)">
                            <strong>💡 Conseils :</strong> Les photos avant/après et les vidéos de témoignages augmentent la confiance des acheteurs et boostent vos ventes.
                        </div>
                    </div>

                </div><!-- end padding -->

                <div style="display:flex;gap:1rem;justify-content:flex-end;padding:1rem 1.5rem;border-top:1px solid var(--gray-100);background:var(--gray-100)">
                    <a href="?section=products" class="btn btn-secondary">Annuler</a>
                    <button type="submit" class="btn btn-primary" id="submitProductBtn">📤 Publier le produit</button>
                </div>
            </form>
        </div>

        <!-- EDIT PRODUCT -->
        <?php elseif ($section === 'edit_product'): ?>
        <?php
        $editId = (int)($_GET['id'] ?? 0);
        $editStmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND store_id = ?");
        $editStmt->execute([$editId, $storeId]);
        $editProduct = $editStmt->fetch();

        if (!$editProduct) {
            setFlash('error', 'Produit introuvable.');
            redirect(SITE_URL . '/dashboard.php?section=products');
        }

        // Charger les témoignages existants
        $existingTestimonials = $pdo->prepare("SELECT * FROM product_testimonials WHERE product_id = ? ORDER BY sort_order");
        $existingTestimonials->execute([$editId]);
        $existingTestimonials = $existingTestimonials->fetchAll();

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_product'])) {
            $pName       = trim($_POST['name'] ?? '');
            $pDesc       = trim($_POST['description'] ?? '');
            $pProblems   = trim($_POST['patient_problems'] ?? '');
            $pAdvantages = trim($_POST['advantages'] ?? '');
            $pPosologie  = trim($_POST['posologie'] ?? '');
            $pPrice      = (float)($_POST['price'] ?? 0);
            $pOrigPrice  = $_POST['original_price'] !== '' ? (float)$_POST['original_price'] : null;
            $pDelivery   = (float)($_POST['delivery_fee'] ?? 0);
            $pProbW      = min(100, max(10, (int)($_POST['problems_image_width']  ?? 100)));
            $pAdvW       = min(100, max(10, (int)($_POST['advantages_image_width'] ?? 100)));
            $pPosW       = min(100, max(10, (int)($_POST['posologie_image_width']  ?? 100)));
            $pStock      = (int)($_POST['stock'] ?? 0);
            $pCatId      = (int)($_POST['category_id'] ?? 0) ?: null;
            $pStatus     = $_POST['status'] ?? 'active';
            $imageFile   = $editProduct['image'];

            if ($pName && $pPrice > 0) {
                if (!empty($_FILES['image']['name'])) {
                    $uploaded = uploadProductImage($_FILES['image'], $editProduct['image']);
                    if ($uploaded === false) {
                        $editError = 'Photo invalide. Formats acceptés : JPG, PNG, WEBP (max 5 Mo).';
                    } else {
                        $imageFile = $uploaded;
                    }
                }

                // Images sections santé (conserver l'ancienne si aucune nouvelle)
                $probImg = $editProduct['problems_image'];
                $advImg  = $editProduct['advantages_image'];
                $posImg  = $editProduct['posologie_image'];
                if (!empty($_FILES['problems_image']['name']))  $probImg = uploadProductImage($_FILES['problems_image'],  $probImg) ?: $probImg;
                if (!empty($_FILES['advantages_image']['name'])) $advImg = uploadProductImage($_FILES['advantages_image'], $advImg)  ?: $advImg;
                if (!empty($_FILES['posologie_image']['name']))  $posImg = uploadProductImage($_FILES['posologie_image'],  $posImg)  ?: $posImg;
                // Supprimer si l'utilisateur a coché "supprimer l'image"
                if (isset($_POST['del_problems_image']))  { if ($probImg) @unlink(__DIR__.'/uploads/products/'.$probImg); $probImg = null; }
                if (isset($_POST['del_advantages_image'])){ if ($advImg)  @unlink(__DIR__.'/uploads/products/'.$advImg);  $advImg  = null; }
                if (isset($_POST['del_posologie_image'])) { if ($posImg)  @unlink(__DIR__.'/uploads/products/'.$posImg);  $posImg  = null; }

                // Supprimer les témoignages cochés
                $toDelete = $_POST['delete_testimonials'] ?? [];
                foreach ($toDelete as $tid) {
                    $tRow = $pdo->prepare("SELECT file_name FROM product_testimonials WHERE id = ? AND product_id = ?");
                    $tRow->execute([(int)$tid, $editId]);
                    $tData = $tRow->fetch();
                    if ($tData) {
                        $tPath = __DIR__ . '/uploads/testimonials/' . $tData['file_name'];
                        if (file_exists($tPath)) unlink($tPath);
                        $pdo->prepare("DELETE FROM product_testimonials WHERE id = ?")->execute([(int)$tid]);
                    }
                }

                if (!isset($editError)) {
                    $pdo->prepare("
                        UPDATE products SET name=?, description=?, patient_problems=?, advantages=?,
                        posologie=?, problems_image=?, advantages_image=?, posologie_image=?,
                        price=?, original_price=?, delivery_fee=?, stock=?, category_id=?, image=?, status=?,
                        problems_image_width=?, advantages_image_width=?, posologie_image_width=?
                        WHERE id=? AND store_id=?
                    ")->execute([$pName, $pDesc, $pProblems, $pAdvantages, $pPosologie,
                                 $probImg, $advImg, $posImg,
                                 $pPrice, $pOrigPrice, $pDelivery, $pStock, $pCatId, $imageFile, $pStatus,
                                 $pProbW, $pAdvW, $pPosW, $editId, $storeId]);

                    // Nouveaux témoignages (uploadés via AJAX)
                    $uploadedFiles    = $_POST['uploaded_testimonials'] ?? [];
                    $uploadedCaptions = $_POST['uploaded_captions']     ?? [];
                    $order = count($existingTestimonials);
                    foreach ($uploadedFiles as $i => $tFile) {
                        $tFile = basename(trim($tFile));
                        $tPath = __DIR__ . '/uploads/testimonials/' . $tFile;
                        if (!$tFile || !file_exists($tPath)) continue;
                        $tType    = getTestimonialType($tFile);
                        $tCaption = trim($uploadedCaptions[$i] ?? '');
                        $pdo->prepare("INSERT INTO product_testimonials (product_id, file_name, file_type, caption, sort_order) VALUES (?,?,?,?,?)")
                            ->execute([$editId, $tFile, $tType, $tCaption, $order++]);
                    }

                    setFlash('success', 'Produit mis à jour !');
                    redirect(SITE_URL . '/dashboard.php?section=products');
                }
            } else {
                $editError = 'Nom et prix sont obligatoires.';
            }
        }
        ?>

        <div class="dashboard-header">
            <div class="dashboard-title">Modifier le produit</div>
            <a href="?section=products" class="btn btn-secondary">← Retour</a>
        </div>

        <div class="card" style="padding:0;overflow:hidden">
            <div class="form-tabs">
                <button type="button" class="form-tab active" onclick="switchTab('tab-general', this)">📦 Informations</button>
                <button type="button" class="form-tab" onclick="switchTab('tab-health', this)">🩺 Description santé</button>
                <button type="button" class="form-tab" onclick="switchTab('tab-testimonials', this)">
                    🎥 Témoignages
                    <?php if (count($existingTestimonials) > 0): ?>
                        <span style="background:var(--primary);color:white;border-radius:20px;padding:.1rem .5rem;font-size:.72rem;margin-left:.3rem"><?= count($existingTestimonials) ?></span>
                    <?php endif; ?>
                </button>
            </div>

            <?php if (isset($editError)): ?>
                <div class="alert alert-error" style="margin:1.5rem 1.5rem 0"><?= escape($editError) ?></div>
            <?php endif; ?>

            <form method="POST" action="?section=edit_product&id=<?= $editId ?>" enctype="multipart/form-data">
                <input type="hidden" name="edit_product" value="1">
                <div style="padding:1.5rem">

                    <!-- TAB 1 : Informations -->
                    <div id="tab-general" class="tab-panel">
                        <div style="display:grid;grid-template-columns:1fr 280px;gap:2rem;align-items:start">
                            <div class="form-grid-2">
                                <div class="form-group" style="grid-column:1/-1">
                                    <label class="form-label">Nom du produit *</label>
                                    <input type="text" name="name" class="form-control" value="<?= escape($editProduct['name']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Prix (FCFA) *</label>
                                    <input type="number" name="price" class="form-control" value="<?= $editProduct['price'] ?>" min="0" step="100" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Prix barré (FCFA)</label>
                                    <input type="number" name="original_price" class="form-control" value="<?= $editProduct['original_price'] ?? '' ?>" min="0" step="100">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Frais de livraison (FCFA)</label>
                                    <input type="number" name="delivery_fee" class="form-control" value="<?= $editProduct['delivery_fee'] ?? 0 ?>" min="0" step="100">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Stock</label>
                                    <input type="number" name="stock" class="form-control" value="<?= $editProduct['stock'] ?>" min="0">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Catégorie</label>
                                    <select name="category_id" class="form-select">
                                        <option value="">Sélectionner</option>
                                        <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= $editProduct['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                                            <?= escape($cat['icon'] . ' ' . $cat['name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Statut</label>
                                    <select name="status" class="form-select">
                                        <option value="active" <?= $editProduct['status'] === 'active' ? 'selected' : '' ?>>✅ Actif (visible)</option>
                                        <option value="inactive" <?= $editProduct['status'] === 'inactive' ? 'selected' : '' ?>>🔒 Inactif (masqué)</option>
                                    </select>
                                </div>
                                <div class="form-group" style="grid-column:1/-1">
                                    <label class="form-label">Description générale</label>
                                    <textarea name="description" class="form-control" rows="3"><?= escape($editProduct['description'] ?? '') ?></textarea>
                                </div>
                            </div>
                            <div>
                                <label class="form-label">Photo principale</label>
                                <div class="upload-zone" id="uploadZone" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)" ondrop="handleDrop(event)">
                                    <input type="file" name="image" id="imageInput" accept="image/jpeg,image/png,image/webp" onchange="previewImage(this)">
                                    <?php if ($editProduct['image']): ?>
                                    <div class="upload-preview" id="uploadPreview">
                                        <img id="previewImg" src="<?= getProductImageUrl($editProduct['image']) ?>" alt="Photo actuelle">
                                        <span class="upload-preview-remove" onclick="removePreview(event)">×</span>
                                    </div>
                                    <div id="uploadPlaceholder" style="display:none">
                                    <?php else: ?>
                                    <div id="uploadPreview" style="display:none">
                                        <img id="previewImg" src="" alt="Aperçu">
                                        <span class="upload-preview-remove" onclick="removePreview(event)">×</span>
                                    </div>
                                    <div id="uploadPlaceholder">
                                    <?php endif; ?>
                                        <div class="upload-zone-icon">🖼️</div>
                                        <div class="upload-zone-text">Changer la photo</div>
                                        <div class="upload-zone-hint">JPG, PNG, WEBP — max 5 Mo</div>
                                    </div>
                                </div>
                                <p style="font-size:.75rem;color:var(--gray-500);margin-top:.5rem">Laisser vide pour conserver l'actuelle.</p>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2 : Description santé -->
                    <div id="tab-health" class="tab-panel" style="display:none">
                        <div style="display:flex;flex-direction:column;gap:1.5rem">
                            <div class="health-section-card" style="border-left-color:#EF4444">
                                <div class="health-section-header">
                                    <span class="health-section-icon" style="background:#FEE2E2">😣</span>
                                    <div>
                                        <div class="health-section-title">Problèmes du patient</div>
                                        <div class="health-section-subtitle">Symptômes et maux que ce produit traite</div>
                                    </div>
                                </div>
                                <textarea name="patient_problems" class="form-control" rows="5"
                                          placeholder="Ex:&#10;• Douleurs articulaires&#10;• Fatigue chronique&#10;• Troubles digestifs"><?= escape($editProduct['patient_problems'] ?? '') ?></textarea>
                                <?= healthImageUpload('problems_image', 'prob', '#FEE2E2', '#B91C1C', $editProduct['problems_image'] ?? null) ?>
                                <?php if (!empty($editProduct['problems_image'])): ?>
                                <label style="font-size:.75rem;color:var(--danger);margin-top:.25rem;display:flex;align-items:center;gap:.4rem">
                                    <input type="checkbox" name="del_problems_image"> Supprimer cette image
                                </label>
                                <?php endif; ?>
                                <div style="margin-top:.6rem">
                                    <label class="form-label" style="font-size:.78rem">Taille d'affichage : <span id="eprob_w_val"><?= $editProduct['problems_image_width'] ?? 100 ?></span>%</label>
                                    <input type="range" name="problems_image_width" min="10" max="100" step="5"
                                           value="<?= $editProduct['problems_image_width'] ?? 100 ?>"
                                           style="width:100%;accent-color:#B91C1C" oninput="document.getElementById('eprob_w_val').textContent=this.value">
                                </div>
                            </div>
                            <div class="health-section-card" style="border-left-color:#10B981">
                                <div class="health-section-header">
                                    <span class="health-section-icon" style="background:#D1FAE5">✅</span>
                                    <div>
                                        <div class="health-section-title">Avantages du produit</div>
                                        <div class="health-section-subtitle">Bienfaits et effets bénéfiques</div>
                                    </div>
                                </div>
                                <textarea name="advantages" class="form-control" rows="5"
                                          placeholder="Ex:&#10;• Soulage rapidement&#10;• 100% naturel&#10;• Sans effets secondaires"><?= escape($editProduct['advantages'] ?? '') ?></textarea>
                                <?= healthImageUpload('advantages_image', 'adv', '#D1FAE5', '#065F46', $editProduct['advantages_image'] ?? null) ?>
                                <?php if (!empty($editProduct['advantages_image'])): ?>
                                <label style="font-size:.75rem;color:var(--danger);margin-top:.25rem;display:flex;align-items:center;gap:.4rem">
                                    <input type="checkbox" name="del_advantages_image"> Supprimer cette image
                                </label>
                                <?php endif; ?>
                                <div style="margin-top:.6rem">
                                    <label class="form-label" style="font-size:.78rem">Taille d'affichage : <span id="eadv_w_val"><?= $editProduct['advantages_image_width'] ?? 100 ?></span>%</label>
                                    <input type="range" name="advantages_image_width" min="10" max="100" step="5"
                                           value="<?= $editProduct['advantages_image_width'] ?? 100 ?>"
                                           style="width:100%;accent-color:#065F46" oninput="document.getElementById('eadv_w_val').textContent=this.value">
                                </div>
                            </div>
                            <div class="health-section-card" style="border-left-color:#7C3AED">
                                <div class="health-section-header">
                                    <span class="health-section-icon" style="background:#EDE9FE">💊</span>
                                    <div>
                                        <div class="health-section-title">Posologie / Mode d'emploi</div>
                                        <div class="health-section-subtitle">Doses, fréquences et précautions</div>
                                    </div>
                                </div>
                                <textarea name="posologie" class="form-control" rows="5"
                                          placeholder="Ex:&#10;• 2 gélules matin et soir&#10;• Avec un grand verre d'eau&#10;• Cure de 30 jours"><?= escape($editProduct['posologie'] ?? '') ?></textarea>
                                <?= healthImageUpload('posologie_image', 'pos', '#EDE9FE', '#5B21B6', $editProduct['posologie_image'] ?? null) ?>
                                <?php if (!empty($editProduct['posologie_image'])): ?>
                                <label style="font-size:.75rem;color:var(--danger);margin-top:.25rem;display:flex;align-items:center;gap:.4rem">
                                    <input type="checkbox" name="del_posologie_image"> Supprimer cette image
                                </label>
                                <?php endif; ?>
                                <div style="margin-top:.6rem">
                                    <label class="form-label" style="font-size:.78rem">Taille d'affichage : <span id="epos_w_val"><?= $editProduct['posologie_image_width'] ?? 100 ?></span>%</label>
                                    <input type="range" name="posologie_image_width" min="10" max="100" step="5"
                                           value="<?= $editProduct['posologie_image_width'] ?? 100 ?>"
                                           style="width:100%;accent-color:#5B21B6" oninput="document.getElementById('epos_w_val').textContent=this.value">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 3 : Témoignages -->
                    <div id="tab-testimonials" class="tab-panel" style="display:none">
                        <!-- Témoignages existants -->
                        <?php if (!empty($existingTestimonials)): ?>
                        <div style="margin-bottom:1.5rem">
                            <div style="font-weight:700;color:var(--dark);margin-bottom:.75rem">Témoignages actuels</div>
                            <div class="testimonials-preview-grid">
                                <?php foreach ($existingTestimonials as $t): ?>
                                <div class="testimonial-item-existing" style="position:relative">
                                    <?php if ($t['file_type'] === 'video'): ?>
                                        <video src="<?= SITE_URL ?>/uploads/testimonials/<?= escape($t['file_name']) ?>"
                                               style="width:100%;height:130px;object-fit:cover;border-radius:var(--radius-sm)" controls></video>
                                    <?php else: ?>
                                        <img src="<?= SITE_URL ?>/uploads/testimonials/<?= escape($t['file_name']) ?>"
                                             style="width:100%;height:130px;object-fit:cover;border-radius:var(--radius-sm)" alt="">
                                    <?php endif; ?>
                                    <?php if ($t['caption']): ?>
                                        <div style="font-size:.75rem;color:var(--gray-500);margin-top:.3rem;text-align:center"><?= escape($t['caption']) ?></div>
                                    <?php endif; ?>
                                    <label style="position:absolute;top:.4rem;right:.4rem;background:rgba(239,68,68,.9);color:white;border-radius:6px;padding:.2rem .5rem;font-size:.72rem;cursor:pointer;display:flex;align-items:center;gap:.3rem">
                                        <input type="checkbox" name="delete_testimonials[]" value="<?= $t['id'] ?>" style="accent-color:white">
                                        Supprimer
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Ajouter de nouveaux -->
                        <div style="font-weight:700;color:var(--dark);margin-bottom:.75rem">Ajouter de nouveaux témoignages</div>
                        <div class="testimonial-upload-zone" id="testimonialsZone"
                             onclick="document.getElementById('testimonialsFileInput').click()"
                             ondragover="handleTestimonialDragOver(event)"
                             ondragleave="handleTestimonialDragLeave(event)"
                             ondrop="handleTestimonialDrop(event)">
                            <input type="file" id="testimonialsFileInput"
                                   accept="image/*,video/*,.mov,.avi,.mkv"
                                   multiple style="display:none"
                                   onchange="handleTestimonialsSelected(this.files)">
                            <div>
                                <div style="font-size:2.5rem;margin-bottom:.5rem">📸</div>
                                <div style="font-weight:700;color:var(--gray-700);margin-bottom:.25rem">Cliquer ou glisser</div>
                                <div style="font-size:.8rem;color:var(--gray-500)">Images & Vidéos — max 50 Mo</div>
                            </div>
                        </div>
                        <div id="testimonialsPreviewGrid" class="testimonials-preview-grid"></div>
                        <div id="testimonialHiddenInputs"></div>
                    </div>

                </div>

                <div style="display:flex;gap:1rem;justify-content:flex-end;padding:1rem 1.5rem;border-top:1px solid var(--gray-100);background:var(--gray-100)">
                    <a href="?section=products" class="btn btn-secondary">Annuler</a>
                    <button type="submit" class="btn btn-primary" id="submitProductBtn">💾 Enregistrer les modifications</button>
                </div>
            </form>
        </div>

        <!-- STORE SETTINGS -->
        <?php elseif ($section === 'livraison'): ?>
        <?php
        // Read-only: contacts gérés par l'admin depuis global_delivery_contacts
        $deliveries = $pdo->query("SELECT * FROM global_delivery_contacts WHERE is_active=1 ORDER BY zone, contact_name")->fetchAll();

        $byZone = [];
        foreach ($deliveries as $d) {
            $byZone[$d['zone']][] = $d;
        }
        ?>

        <div class="dashboard-header">
            <div>
                <div class="dashboard-title">🚚 Contacts de livraison</div>
                <div style="color:var(--gray-500);font-size:.88rem">Livreurs disponibles par zone — gérés par l'administrateur</div>
            </div>
        </div>

        <?php if (empty($deliveries)): ?>
        <div style="text-align:center;padding:4rem 1rem;background:white;border-radius:var(--radius);border:2px dashed var(--gray-200)">
            <div style="font-size:3.5rem;margin-bottom:.75rem">🚚</div>
            <h3 style="font-weight:700;color:var(--dark);margin-bottom:.4rem">Aucun livreur disponible</h3>
            <p style="color:var(--gray-500);font-size:.9rem">L'administrateur n'a pas encore ajouté de contacts de livraison.</p>
        </div>

        <?php else: ?>

        <!-- Cartes par zone -->
        <?php foreach ($byZone as $zoneName => $livreurs): ?>
        <div style="margin-bottom:1.5rem">
            <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.75rem">
                <span style="background:linear-gradient(135deg,#7C3AED,#EC4899);color:white;padding:.25rem .85rem;border-radius:20px;font-size:.8rem;font-weight:700">
                    📍 <?= escape($zoneName) ?>
                </span>
                <span style="font-size:.78rem;color:var(--gray-400)"><?= count($livreurs) ?> livreur<?= count($livreurs) > 1 ? 's' : '' ?></span>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem">
                <?php foreach ($livreurs as $d): ?>
                <div style="background:white;border-radius:var(--radius);border:1.5px solid var(--gray-100);padding:1.15rem;display:flex;flex-direction:column;gap:.6rem;transition:box-shadow .2s"
                     onmouseover="this.style.boxShadow='0 4px 16px rgba(0,0,0,.08)'" onmouseout="this.style.boxShadow='none'">
                    <div style="display:flex;align-items:center;gap:.75rem">
                        <div style="width:42px;height:42px;background:linear-gradient(135deg,#EDE9FE,#DDD6FE);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0">
                            🧑
                        </div>
                        <div>
                            <div style="font-weight:700;font-size:.95rem;color:var(--dark)"><?= escape($d['contact_name']) ?></div>
                            <a href="tel:<?= escape($d['phone']) ?>"
                               style="font-size:.88rem;color:var(--primary);font-weight:600;text-decoration:none;display:flex;align-items:center;gap:.3rem">
                                📞 <?= escape($d['phone']) ?>
                            </a>
                        </div>
                    </div>
                    <?php if ($d['note']): ?>
                    <div style="font-size:.78rem;color:var(--gray-500);background:var(--gray-100);border-radius:6px;padding:.4rem .65rem;line-height:1.5">
                        <?= escape($d['note']) ?>
                    </div>
                    <?php endif; ?>
                    <div style="display:flex;gap:.5rem;margin-top:.2rem">
                        <a href="tel:<?= escape($d['phone']) ?>"
                           style="flex:1;text-align:center;padding:.4rem;background:#EDE9FE;color:#7C3AED;border-radius:6px;font-size:.78rem;font-weight:700;text-decoration:none">
                            📞 Appeler
                        </a>
                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/','',$d['phone']) ?>" target="_blank"
                           style="flex:1;text-align:center;padding:.4rem;background:#DCFCE7;color:#16A34A;border-radius:6px;font-size:.78rem;font-weight:700;text-decoration:none">
                            💬 WhatsApp
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Tableau récapitulatif -->
        <div style="margin-top:2rem">
            <div style="font-weight:700;color:var(--dark);margin-bottom:.75rem;font-size:.95rem">📋 Récapitulatif complet</div>
            <div class="card" style="overflow:hidden">
                <table style="width:100%;border-collapse:collapse">
                    <thead>
                        <tr style="background:var(--gray-100)">
                            <th style="padding:.65rem 1rem;text-align:left;font-size:.78rem;color:var(--gray-500);font-weight:600">Zone</th>
                            <th style="padding:.65rem 1rem;text-align:left;font-size:.78rem;color:var(--gray-500);font-weight:600">Livreur</th>
                            <th style="padding:.65rem 1rem;text-align:left;font-size:.78rem;color:var(--gray-500);font-weight:600">Téléphone</th>
                            <th style="padding:.65rem 1rem;text-align:left;font-size:.78rem;color:var(--gray-500);font-weight:600">Note</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($deliveries as $d): ?>
                    <tr style="border-top:1px solid var(--gray-100)">
                        <td style="padding:.7rem 1rem">
                            <span style="background:#EDE9FE;color:#7C3AED;padding:.2rem .65rem;border-radius:20px;font-size:.75rem;font-weight:700">
                                <?= escape($d['zone']) ?>
                            </span>
                        </td>
                        <td style="padding:.7rem 1rem;font-weight:600;font-size:.87rem"><?= escape($d['contact_name']) ?></td>
                        <td style="padding:.7rem 1rem">
                            <a href="tel:<?= escape($d['phone']) ?>" style="color:var(--primary);font-weight:600;font-size:.87rem;text-decoration:none"><?= escape($d['phone']) ?></a>
                        </td>
                        <td style="padding:.7rem 1rem;font-size:.8rem;color:var(--gray-500)"><?= escape($d['note'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php elseif ($section === 'abonnement'): ?>
        <?php
        $confirmedPays = $pdo->prepare("SELECT * FROM subscriptions WHERE store_id = ? AND status='confirmed' ORDER BY created_at DESC LIMIT 5");
        $confirmedPays->execute([$storeId]);
        $confirmedPays = $confirmedPays->fetchAll();
        ?>

        <div class="dashboard-header">
            <div>
                <div class="dashboard-title">💳 Mon abonnement</div>
                <div style="color:var(--gray-500);font-size:.88rem">Gérez votre abonnement mensuel WecanShop</div>
            </div>
        </div>

        <!-- Statut actuel -->
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:2rem">
            <div style="background:white;border-radius:var(--radius);padding:1.25rem;border:1.5px solid var(--gray-100);text-align:center">
                <div style="font-size:1.8rem;font-weight:900;color:<?= $sub['needs_payment'] ? '#DC2626' : ($sub['in_trial'] ? '#7C3AED' : '#10B981') ?>">
                    <?= $sub['is_subscribed'] ? '✅ Actif' : ($sub['in_trial'] ? '🎁 Essai' : '⛔ Expiré') ?>
                </div>
                <div style="font-size:.78rem;color:var(--gray-500);margin-top:.3rem">Statut</div>
            </div>
            <div style="background:white;border-radius:var(--radius);padding:1.25rem;border:1.5px solid var(--gray-100);text-align:center">
                <div style="font-size:1.8rem;font-weight:900;color:var(--primary)"><?= $sub['order_count'] ?>/<?= FREE_ORDER_LIMIT ?></div>
                <div style="font-size:.78rem;color:var(--gray-500);margin-top:.3rem">Commandes gratuites</div>
            </div>
            <div style="background:white;border-radius:var(--radius);padding:1.25rem;border:1.5px solid var(--gray-100);text-align:center">
                <div style="font-size:1.8rem;font-weight:900;color:var(--dark)">
                    <?= $sub['is_subscribed'] ? date('d/m/Y', strtotime($sub['sub_end_date'])) : '—' ?>
                </div>
                <div style="font-size:.78rem;color:var(--gray-500);margin-top:.3rem">Expire le</div>
            </div>
        </div>


        <!-- Offre + Formulaire -->
        <div style="display:grid;grid-template-columns:300px 1fr;gap:2rem;align-items:start">

            <!-- Plan card -->
            <div style="background:linear-gradient(160deg,#1E1B4B 0%,#7C3AED 60%,#EC4899 100%);border-radius:var(--radius-lg);padding:2rem;color:white">
                <div style="font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;opacity:.6;margin-bottom:.5rem">Plan mensuel</div>
                <div style="font-size:3rem;font-weight:900;line-height:1">3 000</div>
                <div style="font-size:.9rem;opacity:.75;margin-bottom:1.5rem">FCFA / mois &nbsp;<span style="font-size:.75rem;opacity:.5">≈ 5 $</span></div>
                <ul style="list-style:none;padding:0;margin:0 0 1.5rem;display:flex;flex-direction:column;gap:.6rem">
                    <?php foreach (['Produits illimités','Commandes illimitées','Dashboard complet','Statistiques de vente','Témoignages & médias','Support prioritaire'] as $f): ?>
                    <li style="font-size:.84rem;display:flex;align-items:center;gap:.5rem;opacity:.9"><span style="color:#A78BFA">✓</span> <?= $f ?></li>
                    <?php endforeach; ?>
                </ul>
                <div style="border-top:1px solid rgba(255,255,255,.15);padding-top:.85rem;font-size:.74rem;opacity:.55;line-height:1.6">
                    10 premières commandes gratuites. Aucune carte requise pour l'essai.
                </div>
            </div>

            <!-- Paiement -->
            <div style="background:white;border-radius:var(--radius-lg);border:1.5px solid var(--gray-100);overflow:hidden">
                <div style="padding:1.25rem 1.5rem;border-bottom:1px solid var(--gray-100);display:flex;align-items:center;gap:.75rem">
                    <span style="background:#00B4E6;color:white;border-radius:8px;padding:.3rem .6rem;font-size:.95rem">📱</span>
                    <div>
                        <div style="font-weight:800;font-size:1rem;color:var(--dark)">Paiement par Wave</div>
                        <div style="font-size:.8rem;color:var(--gray-500);margin-top:.1rem">Scannez le QR code avec l'application Wave</div>
                    </div>
                </div>

                <div style="padding:1.5rem;display:flex;justify-content:center">
                    <div style="text-align:center">
                        <div style="border-radius:16px;overflow:hidden;width:260px;box-shadow:0 6px 24px rgba(0,180,230,.3);margin:0 auto">
                            <iframe src="<?= SITE_URL ?>/assets/images/wave_qr.pdf#toolbar=0&navpanes=0&scrollbar=0&view=FitH"
                                    style="width:260px;height:340px;border:none;display:block"
                                    title="QR Code Wave"></iframe>
                        </div>
                        <div style="margin-top:1rem;font-size:.82rem;color:var(--gray-500)">
                            Votre abonnement sera activé sous 24h après réception du paiement.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Historique des paiements -->
        <?php if (!empty($confirmedPays)): ?>
        <div style="margin-top:2rem">
            <div style="font-weight:700;color:var(--dark);margin-bottom:.75rem">Historique des paiements</div>
            <div class="card" style="overflow:hidden">
            <table style="width:100%;border-collapse:collapse">
                <thead><tr style="background:var(--gray-100)">
                    <th style="padding:.6rem 1rem;text-align:left;font-size:.78rem;color:var(--gray-500)">Date</th>
                    <th style="padding:.6rem 1rem;text-align:left;font-size:.78rem;color:var(--gray-500)">Méthode</th>
                    <th style="padding:.6rem 1rem;text-align:left;font-size:.78rem;color:var(--gray-500)">Référence</th>
                    <th style="padding:.6rem 1rem;text-align:left;font-size:.78rem;color:var(--gray-500)">Période</th>
                    <th style="padding:.6rem 1rem;text-align:left;font-size:.78rem;color:var(--gray-500)">Montant</th>
                </tr></thead>
                <tbody>
                <?php foreach ($confirmedPays as $cp): ?>
                <tr style="border-top:1px solid var(--gray-100)">
                    <td style="padding:.75rem 1rem;font-size:.83rem"><?= date('d/m/Y', strtotime($cp['created_at'])) ?></td>
                    <td style="padding:.75rem 1rem;font-size:.83rem"><?= match($cp['payment_method']){
                        'wave'=>'📱 Wave','orange_money'=>'🟠 Orange Money','card'=>'💳 Carte', default=>$cp['payment_method']
                    } ?></td>
                    <td style="padding:.75rem 1rem;font-size:.78rem;color:var(--gray-500)"><?= escape($cp['payment_reference']) ?></td>
                    <td style="padding:.75rem 1rem;font-size:.78rem;color:var(--gray-500)">
                        <?= date('d/m/Y', strtotime($cp['period_start'])) ?> → <?= date('d/m/Y', strtotime($cp['period_end'])) ?>
                    </td>
                    <td style="padding:.75rem 1rem;font-weight:700;font-size:.85rem;color:var(--primary)"><?= number_format($cp['amount'],0,',',' ') ?> FCFA</td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
        <?php endif; ?>

        <?php elseif ($section === 'admin_subs' && isAdmin()): ?>
        <div class="dashboard-header">
            <div>
                <div class="dashboard-title">💳 Gestion des abonnements</div>
                <div style="color:var(--gray-500);font-size:.88rem">Confirmez ou rejetez les paiements des vendeurs</div>
            </div>
        </div>
        <?php
        $allSubs = $pdo->query("
            SELECT s.*, st.name as store_name, u.email as user_email, u.name as user_name
            FROM subscriptions s
            JOIN stores st ON s.store_id = st.id
            JOIN users u ON st.user_id = u.id
            ORDER BY FIELD(s.status,'pending','confirmed','rejected'), s.created_at DESC
            LIMIT 100
        ")->fetchAll();
        ?>
        <?php if (empty($allSubs)): ?>
        <div style="text-align:center;padding:4rem;color:var(--gray-400)">Aucun abonnement soumis pour le moment.</div>
        <?php else: ?>
        <div class="card" style="overflow:hidden">
            <table style="width:100%;border-collapse:collapse">
                <thead>
                    <tr style="background:var(--gray-100)">
                        <th style="padding:.75rem 1rem;text-align:left;font-size:.8rem;color:var(--gray-500);font-weight:600">Boutique / Vendeur</th>
                        <th style="padding:.75rem 1rem;text-align:left;font-size:.8rem;color:var(--gray-500);font-weight:600">Méthode</th>
                        <th style="padding:.75rem 1rem;text-align:left;font-size:.8rem;color:var(--gray-500);font-weight:600">Référence / Téléphone</th>
                        <th style="padding:.75rem 1rem;text-align:left;font-size:.8rem;color:var(--gray-500);font-weight:600">Montant</th>
                        <th style="padding:.75rem 1rem;text-align:left;font-size:.8rem;color:var(--gray-500);font-weight:600">Soumis le</th>
                        <th style="padding:.75rem 1rem;text-align:left;font-size:.8rem;color:var(--gray-500);font-weight:600">Statut / Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($allSubs as $row): ?>
                <tr style="border-top:1px solid var(--gray-100)">
                    <td style="padding:.85rem 1rem">
                        <div style="font-weight:600;font-size:.88rem"><?= escape($row['store_name']) ?></div>
                        <div style="font-size:.75rem;color:var(--gray-500)"><?= escape($row['user_email']) ?></div>
                    </td>
                    <td style="padding:.85rem 1rem">
                        <span style="font-size:.82rem;font-weight:600">
                            <?= match($row['payment_method']) {
                                'wave'         => '📱 Wave',
                                'orange_money' => '🟠 Orange Money',
                                'card'         => '💳 Carte',
                                default        => $row['payment_method']
                            } ?>
                        </span>
                    </td>
                    <td style="padding:.85rem 1rem;font-size:.8rem">
                        <div><?= escape($row['payment_reference'] ?? '—') ?></div>
                        <div style="color:var(--gray-500)"><?= escape($row['payment_phone'] ?? '') ?></div>
                    </td>
                    <td style="padding:.85rem 1rem;font-weight:700;font-size:.88rem;color:var(--primary)"><?= number_format($row['amount'],0,',',' ') ?> FCFA</td>
                    <td style="padding:.85rem 1rem;font-size:.78rem;color:var(--gray-500)"><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                    <td style="padding:.85rem 1rem">
                        <?php if ($row['status'] === 'pending'): ?>
                        <form method="POST" style="display:flex;gap:.4rem;align-items:center;flex-wrap:wrap">
                            <input type="hidden" name="sub_id" value="<?= $row['id'] ?>">
                            <button name="confirm_sub" value="confirm" type="submit"
                                    style="padding:.35rem .85rem;background:#10B981;color:white;border:none;border-radius:6px;font-size:.78rem;font-weight:700;cursor:pointer"
                                    onclick="return confirm('Confirmer et activer cet abonnement ?')">✓ Confirmer</button>
                            <button name="confirm_sub" value="reject" type="submit"
                                    style="padding:.35rem .85rem;background:#EF4444;color:white;border:none;border-radius:6px;font-size:.78rem;font-weight:700;cursor:pointer"
                                    onclick="return confirm('Rejeter ce paiement ?')">✗ Rejeter</button>
                        </form>
                        <?php elseif ($row['status'] === 'confirmed'): ?>
                        <span style="background:#D1FAE5;color:#065F46;padding:.25rem .75rem;border-radius:20px;font-size:.75rem;font-weight:700">✓ Confirmé</span>
                        <div style="font-size:.72rem;color:var(--gray-400);margin-top:.2rem">
                            <?= date('d/m/Y', strtotime($row['period_start'])) ?> → <?= date('d/m/Y', strtotime($row['period_end'])) ?>
                        </div>
                        <?php else: ?>
                        <span style="background:#FEE2E2;color:#991B1B;padding:.25rem .75rem;border-radius:20px;font-size:.75rem;font-weight:700">✗ Rejeté</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php elseif ($section === 'pixel'): ?>
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_pixel'])) {
            $pixelId = trim($_POST['facebook_pixel_id'] ?? '');
            $pixelId = preg_replace('/[^0-9]/', '', $pixelId); // seulement les chiffres
            $pdo->prepare("UPDATE stores SET facebook_pixel_id = ? WHERE id = ?")
                ->execute([$pixelId ?: null, $storeId]);
            setFlash('success', 'Pixel Facebook enregistré !');
            redirect(SITE_URL . '/dashboard.php?section=pixel');
        }
        // Recharger la boutique pour avoir la valeur à jour
        $storePixel = $pdo->prepare("SELECT facebook_pixel_id FROM stores WHERE id = ?");
        $storePixel->execute([$storeId]);
        $currentPixelId = $storePixel->fetchColumn();
        ?>
        <div class="dashboard-header">
            <div class="dashboard-title">Pixel Facebook</div>
        </div>

        <div class="card" style="margin-bottom:1.5rem">
            <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;padding-bottom:1.25rem;border-bottom:1px solid var(--gray-100)">
                <div style="width:52px;height:52px;background:#1877F2;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="white"><path d="M18 3a3 3 0 00-3 3v12a3 3 0 003 3 3 3 0 003-3 3 3 0 00-3-3H6a3 3 0 00-3 3 3 3 0 003 3 3 3 0 003-3V6a3 3 0 00-3-3 3 3 0 00-3 3 3 3 0 003 3h12a3 3 0 003-3 3 3 0 00-3-3z"/></svg>
                </div>
                <div>
                    <div style="font-weight:700;font-size:1.05rem;color:var(--dark)">Connecter votre Pixel Meta (Facebook)</div>
                    <div style="font-size:.85rem;color:var(--gray-500);margin-top:.2rem">Suivez vos visiteurs et mesurez vos conversions publicitaires</div>
                </div>
            </div>

            <form method="POST" action="?section=pixel">
                <input type="hidden" name="save_pixel" value="1">

                <div class="form-group">
                    <label class="form-label" style="font-weight:700">ID du Pixel Facebook</label>
                    <input type="text" name="facebook_pixel_id" class="form-control"
                           placeholder="Ex : 1234567890123456"
                           value="<?= escape($currentPixelId ?? '') ?>"
                           style="font-size:1.05rem;letter-spacing:.05em">
                    <div style="font-size:.8rem;color:var(--gray-500);margin-top:.5rem">
                        Saisissez uniquement les chiffres de votre Pixel ID (15–16 chiffres)
                    </div>
                </div>

                <div style="background:#EFF6FF;border:1px solid #BFDBFE;border-radius:var(--radius-sm);padding:1rem;margin-bottom:1.5rem">
                    <div style="font-weight:700;font-size:.88rem;color:#1D4ED8;margin-bottom:.6rem">📍 Comment trouver votre Pixel ID ?</div>
                    <ol style="font-size:.82rem;color:#1E40AF;margin:0;padding-left:1.2rem;line-height:2">
                        <li>Connectez-vous à <strong>business.facebook.com</strong></li>
                        <li>Allez dans <strong>Gestionnaire d'événements</strong></li>
                        <li>Sélectionnez votre Pixel dans la liste</li>
                        <li>Copiez l'<strong>ID du Pixel</strong> affiché en haut</li>
                    </ol>
                </div>

                <?php if ($currentPixelId): ?>
                <div style="background:#F0FDF4;border:1px solid #BBF7D0;border-radius:var(--radius-sm);padding:.85rem 1rem;margin-bottom:1.25rem;display:flex;align-items:center;gap:.75rem">
                    <span style="font-size:1.2rem">✅</span>
                    <div>
                        <div style="font-weight:700;font-size:.85rem;color:#15803D">Pixel actif</div>
                        <div style="font-size:.8rem;color:#166534">ID : <strong><?= escape($currentPixelId) ?></strong> — le code se déclenche sur chaque page produit</div>
                    </div>
                </div>
                <?php endif; ?>

                <div style="display:flex;gap:.75rem;justify-content:flex-end">
                    <?php if ($currentPixelId): ?>
                    <button type="button" class="btn btn-outline"
                            onclick="if(confirm('Supprimer le pixel ?')){document.getElementById('pixelIdInput').value='';document.querySelector('[name=save_pixel]').closest('form').submit()}"
                            style="color:#EF4444;border-color:#EF4444">
                        Supprimer le pixel
                    </button>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary">
                        💾 Enregistrer
                    </button>
                </div>
            </form>
        </div>

        <!-- Événements trackés -->
        <div class="card">
            <div style="font-weight:700;font-size:.95rem;color:var(--dark);margin-bottom:1rem">📊 Événements suivis automatiquement</div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:.75rem">
                <div style="background:var(--gray-50);border-radius:var(--radius-sm);padding:.85rem;border-left:3px solid #1877F2">
                    <div style="font-weight:700;font-size:.88rem;color:var(--dark)">PageView</div>
                    <div style="font-size:.78rem;color:var(--gray-500);margin-top:.2rem">Chaque visite sur une page produit</div>
                </div>
                <div style="background:var(--gray-50);border-radius:var(--radius-sm);padding:.85rem;border-left:3px solid #10B981">
                    <div style="font-weight:700;font-size:.88rem;color:var(--dark)">ViewContent</div>
                    <div style="font-size:.78rem;color:var(--gray-500);margin-top:.2rem">Consultation d'un produit</div>
                </div>
                <div style="background:var(--gray-50);border-radius:var(--radius-sm);padding:.85rem;border-left:3px solid #F59E0B">
                    <div style="font-weight:700;font-size:.88rem;color:var(--dark)">Purchase</div>
                    <div style="font-size:.78rem;color:var(--gray-500);margin-top:.2rem">Confirmation de commande</div>
                </div>
            </div>
        </div>

        <?php elseif ($section === 'store_settings'): ?>
        <div class="dashboard-header">
            <div class="dashboard-title">Paramètres de la boutique</div>
        </div>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_store'])) {
            $sName = trim($_POST['store_name'] ?? '');
            $sDesc = trim($_POST['store_description'] ?? '');
            if ($sName) {
                $pdo->prepare("UPDATE stores SET name = ?, description = ? WHERE id = ?")
                    ->execute([$sName, $sDesc, $storeId]);
                setFlash('success', 'Boutique mise à jour !');
                redirect(SITE_URL . '/dashboard.php?section=store_settings');
            }
        }
        ?>

        <div class="card">
            <form method="POST" action="?section=store_settings">
                <input type="hidden" name="update_store" value="1">
                <div class="form-group">
                    <label class="form-label">Nom de la boutique *</label>
                    <input type="text" name="store_name" class="form-control"
                           value="<?= escape($store['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="store_description" class="form-control" rows="4"><?= escape($store['description'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">URL de votre boutique</label>
                    <div style="display:flex;align-items:center;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);overflow:hidden">
                        <span style="padding:.7rem 1rem;background:var(--gray-100);color:var(--gray-500);font-size:.9rem;border-right:1px solid var(--gray-300)">wecanshop.com/</span>
                        <input type="text" value="<?= escape($store['slug']) ?>" class="form-control" style="border:none" readonly>
                    </div>
                </div>
                <div style="display:flex;justify-content:flex-end">
                    <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </main>
</div>

<script>
const siteUrl = "<?= SITE_URL ?>";

// ---- Onglets ----
function switchTab(tabId, btn) {
    document.querySelectorAll('.tab-panel').forEach(p => p.style.display = 'none');
    document.querySelectorAll('.form-tab').forEach(b => b.classList.remove('active'));
    document.getElementById(tabId).style.display = 'block';
    btn.classList.add('active');
}

// ---- Témoignages (upload AJAX) ----
const T_UPLOAD_URL = siteUrl + '/api/testimonials_upload.php';
const T_VIDEO_EXTS = ['mp4','webm','mov','avi','mkv'];
let tUploadCount = 0; // pour les IDs uniques

function isVideoFile(file) {
    if (file.type && file.type.startsWith('video/')) return true;
    const ext = file.name.split('.').pop().toLowerCase();
    return T_VIDEO_EXTS.includes(ext);
}

function handleTestimonialsSelected(files) {
    Array.from(files).forEach(f => uploadOneTestimonial(f));
    // Réinitialiser l'input pour permettre de re-sélectionner le même fichier
    const inp = document.getElementById('testimonialsFileInput');
    if (inp) inp.value = '';
}

function uploadOneTestimonial(file) {
    const id  = 'titem_' + (++tUploadCount);
    const isVid = isVideoFile(file);

    // Créer l'item de prévisualisation immédiatement
    const grid = document.getElementById('testimonialsPreviewGrid');
    if (!grid) return;

    const item = document.createElement('div');
    item.className = 'testimonial-preview-item';
    item.id = id;

    // Spinner de chargement (remplacé une fois uploadé)
    item.innerHTML = `
        <div style="height:130px;display:flex;align-items:center;justify-content:center;background:var(--gray-100)">
            <div style="text-align:center">
                <div style="font-size:1.5rem;margin-bottom:.25rem">${isVid ? '🎥' : '🖼️'}</div>
                <div style="font-size:.75rem;color:var(--gray-500)">Envoi en cours…</div>
            </div>
        </div>`;
    grid.appendChild(item);

    const formData = new FormData();
    formData.append('file', file);

    fetch(T_UPLOAD_URL, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                item.innerHTML = `
                    <div style="height:130px;display:flex;align-items:center;justify-content:center;background:#FEF2F2;padding:.5rem;text-align:center">
                        <div>
                            <div style="font-size:1.2rem">⚠️</div>
                            <div style="font-size:.72rem;color:var(--danger)">${data.error || 'Erreur upload'}</div>
                        </div>
                    </div>
                    <span class="testimonial-remove" onclick="removeTestimonialItem('${id}')">×</span>`;
                return;
            }
            // Succès : afficher la prévisualisation
            renderUploadedTestimonial(item, data, id);
        })
        .catch(() => {
            item.innerHTML = `
                <div style="height:130px;display:flex;align-items:center;justify-content:center;background:#FEF2F2">
                    <div style="font-size:.72rem;color:var(--danger);text-align:center;padding:.5rem">Erreur réseau</div>
                </div>
                <span class="testimonial-remove" onclick="removeTestimonialItem('${id}')">×</span>`;
        });
}

function renderUploadedTestimonial(item, data, id) {
    const isVid = data.type === 'video';

    // Ajouter un champ caché pour stocker le nom de fichier
    const hiddenContainer = document.getElementById('testimonialHiddenInputs');
    if (hiddenContainer) {
        const hFile    = document.createElement('input');
        hFile.type     = 'hidden';
        hFile.name     = 'uploaded_testimonials[]';
        hFile.value    = data.filename;
        hFile.id       = 'hfile_' + id;

        const hCaption = document.createElement('input');
        hCaption.type  = 'hidden';
        hCaption.name  = 'uploaded_captions[]';
        hCaption.value = '';
        hCaption.id    = 'hcap_' + id;

        hiddenContainer.appendChild(hFile);
        hiddenContainer.appendChild(hCaption);
    }

    let mediaHtml = '';
    if (isVid) {
        mediaHtml = `<video src="${data.url}" controls muted preload="metadata"
            style="width:100%;height:130px;object-fit:cover;display:block;background:#000"></video>`;
    } else {
        mediaHtml = `<img src="${data.url}" alt=""
            style="width:100%;height:130px;object-fit:cover;display:block">`;
    }

    item.innerHTML = `
        ${mediaHtml}
        <span class="testimonial-type-badge">${isVid ? '🎥 Vidéo' : '🖼️ Photo'}</span>
        <span class="testimonial-remove" onclick="removeTestimonialItem('${id}')">×</span>
        <input type="text" placeholder="Légende (optionnel)"
               oninput="document.getElementById('hcap_${id}').value=this.value"
               style="width:100%;border:none;border-top:1px solid var(--gray-100);padding:.4rem .6rem;font-size:.75rem;font-family:inherit;outline:none;background:white">`;
}

function removeTestimonialItem(id) {
    document.getElementById(id)?.remove();
    document.getElementById('hfile_' + id)?.remove();
    document.getElementById('hcap_'  + id)?.remove();
}

function handleTestimonialDragOver(e) {
    e.preventDefault();
    document.getElementById('testimonialsZone')?.classList.add('drag-over');
}
function handleTestimonialDragLeave() {
    document.getElementById('testimonialsZone')?.classList.remove('drag-over');
}
function handleTestimonialDrop(e) {
    e.preventDefault();
    document.getElementById('testimonialsZone')?.classList.remove('drag-over');
    handleTestimonialsSelected(e.dataTransfer.files);
}

// ---- Images sections santé ----
function previewHealthImg(input, uid) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        const prev  = document.getElementById('hprev_' + uid);
        const img   = prev.querySelector('img') || document.getElementById('himg_' + uid);
        const label = document.getElementById('hlabel_' + uid);
        if (img) img.src = e.target.result;
        if (prev) prev.style.display = 'flex';
        if (label) label.style.display = 'none';
    };
    reader.readAsDataURL(input.files[0]);
}

function removeHealthImg(uid) {
    const prev  = document.getElementById('hprev_' + uid);
    const label = document.getElementById('hlabel_' + uid);
    const file  = document.getElementById('hfile_' + uid);
    if (prev)  { prev.style.display = 'none'; const img = prev.querySelector('img'); if(img) img.src=''; }
    if (label) label.style.display = '';
    if (file)  file.value = '';
}

// ---- Upload image preview ----
function previewImage(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('previewImg').src = e.target.result;
        document.getElementById('uploadPreview').style.display = 'block';
        document.getElementById('uploadPlaceholder').style.display = 'none';
    };
    reader.readAsDataURL(input.files[0]);
}

function removePreview(e) {
    e.stopPropagation();
    document.getElementById('previewImg').src = '';
    document.getElementById('uploadPreview').style.display = 'none';
    document.getElementById('uploadPlaceholder').style.display = 'block';
    document.getElementById('imageInput').value = '';
}

function handleDragOver(e) {
    e.preventDefault();
    document.getElementById('uploadZone').classList.add('drag-over');
}

function handleDragLeave(e) {
    document.getElementById('uploadZone').classList.remove('drag-over');
}

function handleDrop(e) {
    e.preventDefault();
    document.getElementById('uploadZone').classList.remove('drag-over');
    const file = e.dataTransfer.files[0];
    if (file && file.type.startsWith('image/')) {
        const input = document.getElementById('imageInput');
        const dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
        previewImage(input);
    }
}

// ---- Orders ----
function updateOrderStatus(orderId, status) {
    fetch(`${siteUrl}/api/orders.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'update_status', order_id: orderId, status })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) showToast('Statut mis à jour');
        else showToast('Erreur', 'error');
    });
}
</script>


<!-- ===================== PANNEAU ASSISTANT IA ===================== -->
<div id="aiOverlay" onclick="closeAiPanel()"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9000;backdrop-filter:blur(2px)"></div>

<div id="aiPanel"
     style="display:none;position:fixed;top:0;right:0;width:420px;max-width:100vw;height:100vh;
            background:white;z-index:9001;box-shadow:-8px 0 40px rgba(0,0,0,.2);
            display:flex;flex-direction:column;transform:translateX(100%);transition:transform .3s cubic-bezier(.4,0,.2,1)">

    <!-- Header -->
    <div style="padding:1.25rem 1.5rem;background:linear-gradient(135deg,#7C3AED,#EC4899);color:white;display:flex;align-items:center;justify-content:space-between;flex-shrink:0">
        <div>
            <div style="font-weight:800;font-size:1.05rem">✨ Assistant IA</div>
            <div style="font-size:.78rem;opacity:.8;margin-top:.1rem">Génération automatique de fiche produit</div>
        </div>
        <button onclick="closeAiPanel()" style="background:rgba(255,255,255,.2);border:none;color:white;border-radius:50%;width:32px;height:32px;font-size:1.1rem;cursor:pointer;display:flex;align-items:center;justify-content:center">×</button>
    </div>

    <!-- Input zone -->
    <div style="padding:1.25rem 1.5rem;border-bottom:1px solid #F3F4F6;flex-shrink:0">
        <label style="font-size:.82rem;font-weight:600;color:#374151;display:block;margin-bottom:.4rem">Nom du produit</label>
        <div style="display:flex;gap:.5rem">
            <input type="text" id="aiProductInput" placeholder="Ex: Tisane Détox Bio, Café Minceur..."
                   style="flex:1;border:1.5px solid #E5E7EB;border-radius:8px;padding:.55rem .85rem;font-size:.9rem;outline:none"
                   onfocus="this.style.borderColor='#7C3AED'" onblur="this.style.borderColor='#E5E7EB'"
                   onkeydown="if(event.key==='Enter')generateAiContent()">
            <button onclick="generateAiContent()" id="aiGenerateBtn"
                    style="padding:.55rem 1.1rem;background:linear-gradient(135deg,#7C3AED,#EC4899);color:white;border:none;border-radius:8px;font-size:.85rem;font-weight:700;cursor:pointer;white-space:nowrap">
                Générer
            </button>
        </div>
        <div id="aiError" style="display:none;margin-top:.5rem;font-size:.78rem;color:#DC2626;background:#FEF2F2;border-radius:6px;padding:.4rem .7rem"></div>
    </div>

    <!-- Résultats -->
    <div id="aiResults" style="flex:1;overflow-y:auto;padding:1.25rem 1.5rem;display:none">

        <div id="aiBlock_description" class="ai-block">
            <div class="ai-block-header">
                <span>📝 Description générale</span>
                <button onclick="aiCopyTo('description','aiVal_description')" class="ai-copy-btn">Copier → formulaire</button>
            </div>
            <div id="aiVal_description" class="ai-block-content" contenteditable="true" spellcheck="false"></div>
        </div>

        <div id="aiBlock_problems" class="ai-block">
            <div class="ai-block-header">
                <span>😣 Problèmes traités</span>
                <button onclick="aiCopyTo('problems','aiVal_problems')" class="ai-copy-btn">Copier → formulaire</button>
            </div>
            <div id="aiVal_problems" class="ai-block-content" contenteditable="true" spellcheck="false"></div>
        </div>

        <div id="aiBlock_advantages" class="ai-block">
            <div class="ai-block-header">
                <span>✅ Avantages</span>
                <button onclick="aiCopyTo('advantages','aiVal_advantages')" class="ai-copy-btn">Copier → formulaire</button>
            </div>
            <div id="aiVal_advantages" class="ai-block-content" contenteditable="true" spellcheck="false"></div>
        </div>

        <div id="aiBlock_posologie" class="ai-block">
            <div class="ai-block-header">
                <span>💊 Posologie / Mode d'emploi</span>
                <button onclick="aiCopyTo('posologie','aiVal_posologie')" class="ai-copy-btn">Copier → formulaire</button>
            </div>
            <div id="aiVal_posologie" class="ai-block-content" contenteditable="true" spellcheck="false"></div>
        </div>

        <button onclick="aiApplyAll()"
                style="width:100%;margin-top:.5rem;padding:.75rem;background:linear-gradient(135deg,#7C3AED,#EC4899);color:white;border:none;border-radius:10px;font-size:.9rem;font-weight:800;cursor:pointer;letter-spacing:.01em">
            ✅ Tout appliquer dans le formulaire
        </button>
    </div>

    <!-- Loader -->
    <div id="aiLoader" style="display:none;flex:1;flex-direction:column;align-items:center;justify-content:center;gap:1rem;padding:2rem">
        <div style="width:48px;height:48px;border:4px solid #EDE9FE;border-top-color:#7C3AED;border-radius:50%;animation:spin 1s linear infinite"></div>
        <div style="font-size:.92rem;font-weight:600;color:#6B7280;text-align:center">
            L'IA rédige votre fiche produit…<br>
            <span style="font-size:.78rem;font-weight:400">Cela prend ~5 secondes</span>
        </div>
    </div>
</div>

<style>
.ai-block { margin-bottom:1rem;border:1.5px solid #E5E7EB;border-radius:10px;overflow:hidden; }
.ai-block-header {
    display:flex;align-items:center;justify-content:space-between;
    padding:.5rem .85rem;background:#F9FAFB;border-bottom:1px solid #E5E7EB;
    font-size:.8rem;font-weight:700;color:#374151;
}
.ai-copy-btn {
    padding:.28rem .75rem;background:#7C3AED;color:white;border:none;border-radius:20px;
    font-size:.73rem;font-weight:700;cursor:pointer;transition:background .15s;
}
.ai-copy-btn:hover { background:#6D28D9; }
.ai-block-content {
    padding:.75rem .85rem;font-size:.84rem;line-height:1.65;color:#1F2937;
    min-height:60px;white-space:pre-wrap;outline:none;
}
.ai-block-content:focus { background:#FAFAFA; }
</style>

<script>
const AI_URL = siteUrl + '/api/ai_product.php';

function openAiPanel() {
    const nameVal = document.getElementById('addProductName')?.value?.trim() || '';
    document.getElementById('aiProductInput').value = nameVal;
    document.getElementById('aiOverlay').style.display = 'block';
    const panel = document.getElementById('aiPanel');
    panel.style.display = 'flex';
    requestAnimationFrame(() => { panel.style.transform = 'translateX(0)'; });
    document.getElementById('aiProductInput').focus();
}

function closeAiPanel() {
    const panel = document.getElementById('aiPanel');
    panel.style.transform = 'translateX(100%)';
    setTimeout(() => {
        panel.style.display = 'none';
        document.getElementById('aiOverlay').style.display = 'none';
    }, 300);
}

async function generateAiContent() {
    const name = document.getElementById('aiProductInput').value.trim();
    if (!name) { document.getElementById('aiProductInput').focus(); return; }

    document.getElementById('aiError').style.display = 'none';
    document.getElementById('aiResults').style.display = 'none';
    document.getElementById('aiLoader').style.display = 'flex';
    document.getElementById('aiGenerateBtn').disabled = true;
    document.getElementById('aiGenerateBtn').textContent = '…';

    try {
        const fd = new FormData();
        fd.append('product_name', name);
        const res = await fetch(AI_URL, { method: 'POST', body: fd });
        const json = await res.json();

        if (!json.success) throw new Error(json.error || 'Erreur inconnue');

        const d = json.data;
        document.getElementById('aiVal_description').textContent = d.description || '';
        document.getElementById('aiVal_problems').textContent   = d.problems    || '';
        document.getElementById('aiVal_advantages').textContent = d.advantages  || '';
        document.getElementById('aiVal_posologie').textContent  = d.posologie   || '';

        document.getElementById('aiLoader').style.display = 'none';
        document.getElementById('aiResults').style.display = 'block';

    } catch(e) {
        document.getElementById('aiLoader').style.display = 'none';
        const errEl = document.getElementById('aiError');
        errEl.textContent = e.message;
        errEl.style.display = 'block';
    } finally {
        document.getElementById('aiGenerateBtn').disabled = false;
        document.getElementById('aiGenerateBtn').textContent = 'Générer';
    }
}

function aiCopyTo(field, srcId) {
    const text = document.getElementById(srcId)?.textContent?.trim() || '';
    const targets = {
        description : () => document.querySelector('[name="description"]'),
        problems    : () => document.querySelector('[name="patient_problems"]'),
        advantages  : () => document.querySelector('[name="advantages"]'),
        posologie   : () => document.querySelector('[name="posologie"]'),
    };
    const el = targets[field]?.();
    if (el) {
        el.value = text;
        showToast('Copié dans le formulaire ✓');
        // Switch to health tab if needed
        if (['problems','advantages','posologie'].includes(field)) {
            const healthBtn = [...document.querySelectorAll('.form-tab')]
                .find(b => b.getAttribute('onclick')?.includes('tab-health'));
            if (healthBtn) switchTab('tab-health', healthBtn);
        }
    }
}

function aiApplyAll() {
    aiCopyTo('description','aiVal_description');
    aiCopyTo('problems','aiVal_problems');
    aiCopyTo('advantages','aiVal_advantages');
    aiCopyTo('posologie','aiVal_posologie');
    // Also copy name
    const aiName = document.getElementById('aiProductInput').value.trim();
    const nameEl = document.getElementById('addProductName');
    if (aiName && nameEl && !nameEl.value) nameEl.value = aiName;
    closeAiPanel();
    showToast('Tout le contenu a été appliqué ✅');
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
