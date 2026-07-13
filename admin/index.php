<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); $_SESSION['started'] = true; }
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Sécurité : admin uniquement
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$section = $_GET['section'] ?? 'overview';

// ================================================================
//  TRAITEMENTS POST
// ================================================================

// --- Abonnements ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_sub'])) {
    $subId  = (int)$_POST['sub_id'];
    $action = $_POST['action_sub'];
    $stmt   = $pdo->prepare("SELECT * FROM subscriptions WHERE id=?");
    $stmt->execute([$subId]);
    $row = $stmt->fetch();
    if ($row) {
        if ($action === 'confirm') {
            $start = date('Y-m-d');
            $end   = date('Y-m-d', strtotime('+30 days'));
            $pdo->prepare("UPDATE subscriptions SET status='confirmed',period_start=?,period_end=?,confirmed_at=NOW() WHERE id=?")->execute([$start,$end,$subId]);
            $pdo->prepare("UPDATE stores SET subscription_status='active',subscription_end_date=? WHERE id=?")->execute([$end,$row['store_id']]);
            setFlash('success','Abonnement activé — 30 jours.');
        } else {
            $pdo->prepare("UPDATE subscriptions SET status='rejected' WHERE id=?")->execute([$subId]);
            setFlash('error','Paiement rejeté.');
        }
    }
    redirect(SITE_URL.'/admin/index.php?section=abonnements');
}

// --- Utilisateurs ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_user'])) {
    $uid    = (int)$_POST['user_id'];
    $action = $_POST['action_user'];
    if ($action === 'delete' && $uid !== (int)$_SESSION['user_id']) {
        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
        setFlash('success','Utilisateur supprimé.');
    } elseif ($action === 'set_role') {
        $role = $_POST['new_role'] ?? '';
        if (in_array($role,['buyer','seller','admin'])) {
            $pdo->prepare("UPDATE users SET role=? WHERE id=?")->execute([$role,$uid]);
            setFlash('success','Rôle mis à jour.');
        }
    } elseif ($action === 'toggle') {
        // pas de colonne active pour l'instant — on supprime juste
    }
    redirect(SITE_URL.'/admin/index.php?section=utilisateurs');
}

// --- Boutiques ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_store'])) {
    $sid    = (int)$_POST['store_id'];
    $action = $_POST['action_store'];
    if ($action === 'toggle') {
        $cur = $pdo->prepare("SELECT status FROM stores WHERE id=?"); $cur->execute([$sid]);
        $cur = $cur->fetchColumn();
        $pdo->prepare("UPDATE stores SET status=? WHERE id=?")->execute([$cur==='active'?'inactive':'active',$sid]);
        setFlash('success','Statut boutique mis à jour.');
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM stores WHERE id=?")->execute([$sid]);
        setFlash('success','Boutique supprimée.');
    }
    redirect(SITE_URL.'/admin/index.php?section=boutiques');
}

// --- Livreurs globaux ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_delivery'])) {
    $action = $_POST['action_delivery'];
    $did    = (int)($_POST['delivery_id'] ?? 0);
    if ($action === 'add') {
        $zone  = trim($_POST['zone_custom'] ?: ($_POST['zone'] ?? ''));
        $cname = trim($_POST['contact_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $note  = trim($_POST['note'] ?? '');
        if ($zone && $cname && $phone) {
            $pdo->prepare("INSERT INTO global_delivery_contacts (zone,contact_name,phone,note) VALUES (?,?,?,?)")->execute([$zone,$cname,$phone,$note]);
            setFlash('success','Livreur ajouté.');
        }
    } elseif ($action === 'edit') {
        $zone  = trim($_POST['zone_custom'] ?: ($_POST['zone'] ?? ''));
        $cname = trim($_POST['contact_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $note  = trim($_POST['note'] ?? '');
        $pdo->prepare("UPDATE global_delivery_contacts SET zone=?,contact_name=?,phone=?,note=? WHERE id=?")->execute([$zone,$cname,$phone,$note,$did]);
        setFlash('success','Livreur mis à jour.');
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM global_delivery_contacts WHERE id=?")->execute([$did]);
        setFlash('success','Livreur supprimé.');
    } elseif ($action === 'toggle') {
        $pdo->prepare("UPDATE global_delivery_contacts SET is_active = 1-is_active WHERE id=?")->execute([$did]);
        setFlash('success','Statut mis à jour.');
    }
    redirect(SITE_URL.'/admin/index.php?section=livreurs');
}

// --- Catégories ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_cat'])) {
    $action = $_POST['action_cat'];
    $cid    = (int)($_POST['cat_id'] ?? 0);
    if ($action === 'add') {
        $name = trim($_POST['cat_name'] ?? '');
        $icon = trim($_POST['cat_icon'] ?? '🛍️');
        $slug = slugify($name);
        if ($name) { $pdo->prepare("INSERT INTO categories (name,slug,icon) VALUES (?,?,?)")->execute([$name,$slug,$icon]); setFlash('success','Catégorie ajoutée.'); }
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([$cid]);
        setFlash('success','Catégorie supprimée.');
    }
    redirect(SITE_URL.'/admin/index.php?section=categories');
}

// --- Commandes ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_order'])) {
    $oid    = (int)$_POST['order_id'];
    $status = $_POST['new_status'] ?? '';
    if (in_array($status,['pending','confirmed','shipping','delivered','cancelled'])) {
        $pdo->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$status,$oid]);
        setFlash('success','Commande mise à jour.');
    }
    redirect(SITE_URL.'/admin/index.php?section=commandes');
}

// ================================================================
//  STATS OVERVIEW
// ================================================================
$stats = [
    'users'        => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'sellers'      => $pdo->query("SELECT COUNT(*) FROM users WHERE role='seller'")->fetchColumn(),
    'buyers'       => $pdo->query("SELECT COUNT(*) FROM users WHERE role='buyer'")->fetchColumn(),
    'stores'       => $pdo->query("SELECT COUNT(*) FROM stores")->fetchColumn(),
    'products'     => $pdo->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn(),
    'orders'       => $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
    'orders_pending'=> $pdo->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn(),
    'sub_pending'  => $pdo->query("SELECT COUNT(*) FROM subscriptions WHERE status='pending'")->fetchColumn(),
    'sub_revenue'  => $pdo->query("SELECT COALESCE(SUM(amount),0) FROM subscriptions WHERE status='confirmed'")->fetchColumn(),
    'active_subs'  => $pdo->query("SELECT COUNT(*) FROM stores WHERE subscription_status='active'")->fetchColumn(),
];

$pageTitle = 'Administration';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin — WecanShop</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:#F1F5F9;color:#1E293B;min-height:100vh;display:flex}

/* Sidebar */
.adm-sidebar{width:260px;min-height:100vh;background:#0F172A;display:flex;flex-direction:column;flex-shrink:0;position:fixed;top:0;left:0;bottom:0;z-index:100;overflow-y:auto}
.adm-logo{padding:1.5rem 1.25rem;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:.75rem}
.adm-logo-icon{width:38px;height:38px;background:linear-gradient(135deg,#7C3AED,#EC4899);border-radius:10px;display:flex;align-items:center;justify-content:center;color:white;font-weight:900;font-size:1.1rem;flex-shrink:0}
.adm-logo-text{font-weight:800;color:white;font-size:1rem;line-height:1.2}
.adm-logo-sub{font-size:.72rem;color:rgba(255,255,255,.4);font-weight:400}

.adm-section-label{font-size:.68rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.3);padding:.9rem 1.25rem .3rem;display:block}
.adm-link{display:flex;align-items:center;gap:.75rem;padding:.7rem 1.25rem;color:rgba(255,255,255,.6);font-size:.87rem;font-weight:500;text-decoration:none;transition:all .15s;border-left:3px solid transparent}
.adm-link:hover{background:rgba(255,255,255,.06);color:white}
.adm-link.active{background:rgba(124,58,237,.25);color:white;border-left-color:#7C3AED}
.adm-badge{margin-left:auto;background:#EF4444;color:white;border-radius:20px;font-size:.68rem;font-weight:700;padding:.1rem .5rem}
.adm-badge.green{background:#10B981}

.adm-user{padding:1rem 1.25rem;border-top:1px solid rgba(255,255,255,.08);margin-top:auto;display:flex;align-items:center;gap:.75rem}
.adm-user-avatar{width:34px;height:34px;background:linear-gradient(135deg,#7C3AED,#EC4899);border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:.9rem;flex-shrink:0}

/* Main */
.adm-main{margin-left:260px;flex:1;display:flex;flex-direction:column;min-height:100vh}
.adm-topbar{background:white;border-bottom:1px solid #E2E8F0;padding:.9rem 2rem;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50}
.adm-topbar-title{font-size:1.1rem;font-weight:800;color:#0F172A}
.adm-content{padding:2rem;flex:1}

/* Cards */
.adm-stats{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1rem;margin-bottom:2rem}
.adm-stat{background:white;border-radius:14px;padding:1.25rem;border:1px solid #E2E8F0;transition:box-shadow .2s}
.adm-stat:hover{box-shadow:0 4px 20px rgba(0,0,0,.08)}
.adm-stat-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;margin-bottom:.85rem}
.adm-stat-val{font-size:1.8rem;font-weight:900;color:#0F172A;line-height:1}
.adm-stat-label{font-size:.78rem;color:#64748B;margin-top:.3rem;font-weight:500}

/* Table */
.adm-card{background:white;border-radius:14px;border:1px solid #E2E8F0;overflow:hidden;margin-bottom:1.5rem}
.adm-card-header{padding:1rem 1.5rem;border-bottom:1px solid #F1F5F9;display:flex;align-items:center;justify-content:space-between}
.adm-card-title{font-weight:800;font-size:.95rem;color:#0F172A}
table{width:100%;border-collapse:collapse}
th{padding:.65rem 1.25rem;text-align:left;font-size:.74rem;font-weight:700;color:#64748B;text-transform:uppercase;letter-spacing:.05em;background:#F8FAFC}
td{padding:.85rem 1.25rem;border-top:1px solid #F1F5F9;font-size:.85rem;color:#334155;vertical-align:middle}
tr:hover td{background:#FAFAFA}

/* Badges */
.badge{display:inline-flex;align-items:center;padding:.2rem .65rem;border-radius:20px;font-size:.72rem;font-weight:700}
.badge-green{background:#DCFCE7;color:#15803D}
.badge-red{background:#FEE2E2;color:#DC2626}
.badge-blue{background:#DBEAFE;color:#1D4ED8}
.badge-purple{background:#EDE9FE;color:#7C3AED}
.badge-orange{background:#FEF3C7;color:#D97706}
.badge-gray{background:#F1F5F9;color:#64748B}

/* Buttons */
.btn{display:inline-flex;align-items:center;gap:.4rem;padding:.5rem 1rem;border-radius:8px;font-size:.83rem;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:all .15s;white-space:nowrap}
.btn-primary{background:linear-gradient(135deg,#7C3AED,#EC4899);color:white}
.btn-primary:hover{opacity:.9}
.btn-success{background:#10B981;color:white}
.btn-danger{background:#EF4444;color:white}
.btn-sm{padding:.35rem .75rem;font-size:.76rem}
.btn-ghost{background:transparent;border:1.5px solid #E2E8F0;color:#475569}
.btn-ghost:hover{background:#F8FAFC}

/* Form */
.form-group{margin-bottom:1rem}
.form-label{display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.35rem}
.form-control{width:100%;padding:.55rem .85rem;border:1.5px solid #E2E8F0;border-radius:8px;font-size:.88rem;outline:none;transition:border .15s;font-family:inherit}
.form-control:focus{border-color:#7C3AED}
.form-select{width:100%;padding:.55rem .85rem;border:1.5px solid #E2E8F0;border-radius:8px;font-size:.88rem;outline:none;background:white;cursor:pointer}
.form-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:1rem}

/* Alert */
.alert{padding:.85rem 1.1rem;border-radius:10px;margin-bottom:1.5rem;font-size:.87rem;font-weight:500}
.alert-success{background:#DCFCE7;color:#166534;border:1px solid #BBF7D0}
.alert-error{background:#FEE2E2;color:#991B1B;border:1px solid #FECACA}
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="adm-sidebar">
    <div class="adm-logo">
        <div class="adm-logo-icon">W</div>
        <div>
            <div class="adm-logo-text">WecanShop</div>
            <div class="adm-logo-sub">Administration</div>
        </div>
    </div>

    <span class="adm-section-label">Vue d'ensemble</span>
    <a href="?section=overview" class="adm-link <?= $section==='overview'?'active':'' ?>">
        <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        Dashboard
    </a>

    <span class="adm-section-label">Finance</span>
    <a href="?section=abonnements" class="adm-link <?= $section==='abonnements'?'active':'' ?>">
        <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
        Abonnements
        <?php if ($stats['sub_pending'] > 0): ?><span class="adm-badge"><?= $stats['sub_pending'] ?></span><?php endif; ?>
    </a>

    <span class="adm-section-label">Utilisateurs</span>
    <a href="?section=utilisateurs" class="adm-link <?= $section==='utilisateurs'?'active':'' ?>">
        <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
        Utilisateurs
        <span class="adm-badge green"><?= $stats['users'] ?></span>
    </a>
    <a href="?section=boutiques" class="adm-link <?= $section==='boutiques'?'active':'' ?>">
        <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
        Boutiques
        <span class="adm-badge green"><?= $stats['stores'] ?></span>
    </a>

    <span class="adm-section-label">Catalogue</span>
    <a href="?section=produits" class="adm-link <?= $section==='produits'?'active':'' ?>">
        <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>
        Produits
        <span class="adm-badge green"><?= $stats['products'] ?></span>
    </a>
    <a href="?section=categories" class="adm-link <?= $section==='categories'?'active':'' ?>">
        <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
        Catégories
    </a>

    <span class="adm-section-label">Logistique</span>
    <a href="?section=commandes" class="adm-link <?= $section==='commandes'?'active':'' ?>">
        <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
        Commandes
        <?php if ($stats['orders_pending'] > 0): ?><span class="adm-badge"><?= $stats['orders_pending'] ?></span><?php endif; ?>
    </a>
    <a href="?section=livreurs" class="adm-link <?= $section==='livreurs'?'active':'' ?>">
        <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 5v3h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
        Livreurs
    </a>

    <div class="adm-user">
        <div class="adm-user-avatar"><?= strtoupper(substr($_SESSION['user_name']??'A',0,1)) ?></div>
        <div style="flex:1;min-width:0">
            <div style="font-size:.82rem;font-weight:700;color:white;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= escape($_SESSION['user_name']??'Admin') ?></div>
            <a href="<?= SITE_URL ?>/logout.php" style="font-size:.73rem;color:rgba(255,255,255,.4);text-decoration:none">Déconnexion</a>
        </div>
    </div>
</aside>

<!-- MAIN -->
<main class="adm-main">
<div class="adm-topbar">
    <div class="adm-topbar-title">
        <?= match($section) {
            'overview'     => '📊 Vue d\'ensemble',
            'abonnements'  => '💳 Abonnements',
            'utilisateurs' => '👥 Utilisateurs',
            'boutiques'    => '🏪 Boutiques',
            'produits'     => '📦 Produits',
            'categories'   => '🏷️ Catégories',
            'commandes'    => '🛍️ Commandes',
            'livreurs'     => '🚚 Livreurs',
            default        => 'Administration'
        } ?>
    </div>
    <div style="display:flex;align-items:center;gap:1rem">
        <a href="<?= SITE_URL ?>/index.php" class="btn btn-ghost btn-sm" target="_blank">Voir le site ↗</a>
        <div style="font-size:.8rem;color:#64748B"><?= date('d/m/Y') ?></div>
    </div>
</div>

<div class="adm-content">
<?php
$flash = getFlash();
if ($flash): ?>
<div class="alert alert-<?= $flash['type']==='success'?'success':'error' ?>"><?= escape($flash['message']) ?></div>
<?php endif; ?>

<?php // ============================================================
      // OVERVIEW
      // ============================================================
if ($section === 'overview'): ?>

<div class="adm-stats">
    <?php
    $cards = [
        ['👥','Utilisateurs totaux', $stats['users'],        'background:#EDE9FE'],
        ['🏪','Boutiques actives',   $stats['stores'],       'background:#DBEAFE'],
        ['📦','Produits actifs',     $stats['products'],     'background:#DCFCE7'],
        ['🛍️','Commandes totales',  $stats['orders'],       'background:#FEF3C7'],
        ['⏳','Commandes en attente',$stats['orders_pending'],'background:#FEE2E2'],
        ['💳','Abonnements actifs',  $stats['active_subs'],  'background:#DCFCE7'],
        ['⏳','Paiements en attente',$stats['sub_pending'],  'background:#FEF3C7'],
        ['💰','Revenus abonnements', number_format($stats['sub_revenue'],0,',',' ').' FCFA', 'background:#DCFCE7'],
    ];
    foreach ($cards as [$icon,$label,$val,$bg]): ?>
    <div class="adm-stat">
        <div class="adm-stat-icon" style="<?= $bg ?>"><?= $icon ?></div>
        <div class="adm-stat-val"><?= $val ?></div>
        <div class="adm-stat-label"><?= $label ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">
    <!-- Dernières commandes -->
    <div class="adm-card">
        <div class="adm-card-header">
            <div class="adm-card-title">Dernières commandes</div>
            <a href="?section=commandes" class="btn btn-ghost btn-sm">Voir tout</a>
        </div>
        <table>
            <thead><tr><th>Client</th><th>Montant</th><th>Statut</th></tr></thead>
            <tbody>
            <?php $recent = $pdo->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 6")->fetchAll();
            foreach ($recent as $o): ?>
            <tr>
                <td><div style="font-weight:600"><?= escape($o['customer_name']) ?></div><div style="font-size:.75rem;color:#94A3B8"><?= date('d/m/Y',strtotime($o['created_at'])) ?></div></td>
                <td style="font-weight:700;color:#7C3AED"><?= number_format($o['total'],0,',',' ') ?> F</td>
                <td><span class="badge <?= match($o['status']){'pending'=>'badge-orange','confirmed'=>'badge-blue','shipping'=>'badge-purple','delivered'=>'badge-green',default=>'badge-gray'} ?>">
                    <?= ucfirst($o['status']) ?>
                </span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Paiements en attente -->
    <div class="adm-card">
        <div class="adm-card-header">
            <div class="adm-card-title">Paiements à confirmer</div>
            <a href="?section=abonnements" class="btn btn-ghost btn-sm">Voir tout</a>
        </div>
        <table>
            <thead><tr><th>Boutique</th><th>Méthode</th><th>Action</th></tr></thead>
            <tbody>
            <?php $pends = $pdo->query("SELECT s.*,st.name as store_name FROM subscriptions s JOIN stores st ON s.store_id=st.id WHERE s.status='pending' ORDER BY s.created_at DESC LIMIT 6")->fetchAll();
            if (empty($pends)): ?>
            <tr><td colspan="3" style="text-align:center;color:#94A3B8;padding:2rem">✅ Aucun paiement en attente</td></tr>
            <?php else: foreach ($pends as $p): ?>
            <tr>
                <td style="font-weight:600"><?= escape($p['store_name']) ?></td>
                <td><span class="badge badge-blue"><?= strtoupper(str_replace('_',' ',$p['payment_method'])) ?></span></td>
                <td>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="sub_id" value="<?= $p['id'] ?>">
                        <button name="action_sub" value="confirm" class="btn btn-success btn-sm">✓</button>
                        <button name="action_sub" value="reject"  class="btn btn-danger  btn-sm">✗</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php // ============================================================
      // ABONNEMENTS
      // ============================================================
elseif ($section === 'abonnements'):
    $allSubs = $pdo->query("
        SELECT s.*,st.name as store_name,u.email,u.name as user_name
        FROM subscriptions s
        JOIN stores st ON s.store_id=st.id
        JOIN users u ON st.user_id=u.id
        ORDER BY FIELD(s.status,'pending','confirmed','rejected'),s.created_at DESC
    ")->fetchAll();
?>
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.5rem">
    <div class="adm-stat"><div class="adm-stat-icon" style="background:#FEF3C7">⏳</div><div class="adm-stat-val"><?= $stats['sub_pending'] ?></div><div class="adm-stat-label">En attente</div></div>
    <div class="adm-stat"><div class="adm-stat-icon" style="background:#DCFCE7">✅</div><div class="adm-stat-val"><?= $stats['active_subs'] ?></div><div class="adm-stat-label">Abonnements actifs</div></div>
    <div class="adm-stat"><div class="adm-stat-icon" style="background:#EDE9FE">💰</div><div class="adm-stat-val"><?= number_format($stats['sub_revenue'],0,',',' ') ?></div><div class="adm-stat-label">FCFA encaissés</div></div>
</div>

<div class="adm-card">
    <div class="adm-card-header"><div class="adm-card-title">Tous les paiements</div></div>
    <table>
        <thead><tr><th>Boutique / Email</th><th>Méthode</th><th>Référence</th><th>Téléphone</th><th>Montant</th><th>Date</th><th>Statut / Action</th></tr></thead>
        <tbody>
        <?php foreach ($allSubs as $s): ?>
        <tr>
            <td><div style="font-weight:600"><?= escape($s['store_name']) ?></div><div style="font-size:.75rem;color:#94A3B8"><?= escape($s['email']) ?></div></td>
            <td><span class="badge badge-blue"><?= strtoupper(str_replace('_',' ',$s['payment_method'])) ?></span></td>
            <td style="font-family:monospace;font-size:.8rem"><?= escape($s['payment_reference']??'—') ?></td>
            <td style="font-size:.82rem"><?= escape($s['payment_phone']??'—') ?></td>
            <td style="font-weight:700;color:#7C3AED"><?= number_format($s['amount'],0,',',' ') ?> FCFA</td>
            <td style="font-size:.78rem;color:#64748B"><?= date('d/m/Y H:i',strtotime($s['created_at'])) ?></td>
            <td>
                <?php if ($s['status']==='pending'): ?>
                <form method="POST" style="display:flex;gap:.35rem">
                    <input type="hidden" name="sub_id" value="<?= $s['id'] ?>">
                    <button name="action_sub" value="confirm" class="btn btn-success btn-sm" onclick="return confirm('Confirmer ?')">✓ Confirmer</button>
                    <button name="action_sub" value="reject"  class="btn btn-danger  btn-sm" onclick="return confirm('Rejeter ?')">✗</button>
                </form>
                <?php elseif ($s['status']==='confirmed'): ?>
                    <span class="badge badge-green">✓ Confirmé</span>
                    <?php if ($s['period_end']): ?>
                    <div style="font-size:.72rem;color:#94A3B8;margin-top:.2rem">→ <?= date('d/m/Y',strtotime($s['period_end'])) ?></div>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="badge badge-red">✗ Rejeté</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($allSubs)): ?><tr><td colspan="7" style="text-align:center;padding:3rem;color:#94A3B8">Aucun paiement soumis</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php // ============================================================
      // UTILISATEURS
      // ============================================================
elseif ($section === 'utilisateurs'):
    $search = trim($_GET['q'] ?? '');
    $usersQ = $pdo->prepare("SELECT u.*, (SELECT COUNT(*) FROM stores WHERE user_id=u.id) as store_count FROM users u WHERE u.name LIKE ? OR u.email LIKE ? ORDER BY u.created_at DESC");
    $usersQ->execute(["%$search%","%$search%"]);
    $users = $usersQ->fetchAll();
?>
<div style="display:flex;gap:.75rem;margin-bottom:1.5rem">
    <input type="text" id="userSearch" value="<?= escape($search) ?>" placeholder="Rechercher un utilisateur..."
           onkeydown="if(event.key==='Enter') window.location='?section=utilisateurs&q='+encodeURIComponent(this.value)"
           style="flex:1;padding:.6rem 1rem;border:1.5px solid #E2E8F0;border-radius:8px;font-size:.88rem;outline:none">
    <button onclick="window.location='?section=utilisateurs&q='+encodeURIComponent(document.getElementById('userSearch').value)" class="btn btn-primary">Rechercher</button>
</div>
<div class="adm-card">
    <div class="adm-card-header"><div class="adm-card-title"><?= count($users) ?> utilisateur<?= count($users)>1?'s':'' ?></div></div>
    <table>
        <thead><tr><th>Nom / Email</th><th>Rôle</th><th>Téléphone</th><th>Boutique</th><th>Inscrit le</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
            <td>
                <div style="font-weight:600"><?= escape($u['name']) ?></div>
                <div style="font-size:.75rem;color:#94A3B8"><?= escape($u['email']) ?></div>
            </td>
            <td>
                <form method="POST" style="display:flex;align-items:center;gap:.4rem">
                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    <input type="hidden" name="action_user" value="set_role">
                    <select name="new_role" onchange="this.form.submit()" class="form-select" style="width:auto;padding:.25rem .5rem;font-size:.78rem">
                        <?php foreach (['buyer','seller','admin'] as $r): ?>
                        <option value="<?= $r ?>" <?= $u['role']===$r?'selected':'' ?>><?= ucfirst($r) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </td>
            <td style="font-size:.82rem"><?= escape($u['phone']??'—') ?></td>
            <td><?= $u['store_count']>0 ? '<span class="badge badge-purple">'.$u['store_count'].' boutique'.($u['store_count']>1?'s':'').'</span>' : '—' ?></td>
            <td style="font-size:.78rem;color:#64748B"><?= date('d/m/Y',strtotime($u['created_at'])) ?></td>
            <td>
                <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer cet utilisateur ?')">
                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    <input type="hidden" name="action_user" value="delete">
                    <button class="btn btn-danger btn-sm">🗑️</button>
                </form>
                <?php else: ?>
                <span style="font-size:.75rem;color:#94A3B8">Vous</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php // ============================================================
      // BOUTIQUES
      // ============================================================
elseif ($section === 'boutiques'):
    $stores = $pdo->query("
        SELECT st.*,u.name as owner_name,u.email as owner_email,
               COUNT(DISTINCT p.id) as product_count,
               COUNT(DISTINCT oi.order_id) as order_count
        FROM stores st
        JOIN users u ON st.user_id=u.id
        LEFT JOIN products p ON p.store_id=st.id
        LEFT JOIN order_items oi ON oi.product_id=p.id
        GROUP BY st.id ORDER BY st.created_at DESC
    ")->fetchAll();
?>
<div class="adm-card">
    <div class="adm-card-header"><div class="adm-card-title"><?= count($stores) ?> boutique<?= count($stores)>1?'s':'' ?></div></div>
    <table>
        <thead><tr><th>Boutique</th><th>Propriétaire</th><th>Produits</th><th>Commandes</th><th>Abonnement</th><th>Statut</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($stores as $st): ?>
        <tr>
            <td><div style="font-weight:600"><?= escape($st['name']) ?></div><div style="font-size:.75rem;color:#94A3B8"><?= escape($st['slug']) ?></div></td>
            <td><div><?= escape($st['owner_name']) ?></div><div style="font-size:.75rem;color:#94A3B8"><?= escape($st['owner_email']) ?></div></td>
            <td><span class="badge badge-purple"><?= $st['product_count'] ?></span></td>
            <td><span class="badge badge-blue"><?= $st['order_count'] ?></span></td>
            <td>
                <?php if ($st['subscription_status']==='active'): ?>
                    <span class="badge badge-green">Actif</span>
                    <div style="font-size:.72rem;color:#94A3B8"><?= date('d/m/Y',strtotime($st['subscription_end_date'])) ?></div>
                <?php elseif ($st['subscription_status']==='trial'): ?>
                    <span class="badge badge-orange">Essai</span>
                <?php else: ?>
                    <span class="badge badge-red">Expiré</span>
                <?php endif; ?>
            </td>
            <td>
                <form method="POST" style="display:inline">
                    <input type="hidden" name="store_id" value="<?= $st['id'] ?>">
                    <button name="action_store" value="toggle" class="btn btn-ghost btn-sm">
                        <?= $st['status']==='active' ? '🔒 Désactiver' : '✅ Activer' ?>
                    </button>
                </form>
            </td>
            <td>
                <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer cette boutique ?')">
                    <input type="hidden" name="store_id" value="<?= $st['id'] ?>">
                    <button name="action_store" value="delete" class="btn btn-danger btn-sm">🗑️</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php // ============================================================
      // PRODUITS
      // ============================================================
elseif ($section === 'produits'):
    $products = $pdo->query("
        SELECT p.*,s.name as store_name,c.name as cat_name
        FROM products p JOIN stores s ON p.store_id=s.id
        LEFT JOIN categories c ON p.category_id=c.id
        ORDER BY p.created_at DESC LIMIT 100
    ")->fetchAll();
?>
<div class="adm-card">
    <div class="adm-card-header"><div class="adm-card-title"><?= count($products) ?> produits</div></div>
    <table>
        <thead><tr><th>Produit</th><th>Boutique</th><th>Catégorie</th><th>Prix</th><th>Stock</th><th>Statut</th></tr></thead>
        <tbody>
        <?php foreach ($products as $p): ?>
        <tr>
            <td style="font-weight:600"><?= escape($p['name']) ?></td>
            <td style="font-size:.82rem"><?= escape($p['store_name']) ?></td>
            <td><span class="badge badge-gray"><?= escape($p['cat_name']??'—') ?></span></td>
            <td style="font-weight:700;color:#7C3AED"><?= number_format($p['price'],0,',',' ') ?> F</td>
            <td><?= $p['stock'] <= 0 ? '<span class="badge badge-red">Épuisé</span>' : $p['stock'] ?></td>
            <td><span class="badge <?= $p['status']==='active'?'badge-green':'badge-gray' ?>"><?= $p['status']==='active'?'Actif':'Inactif' ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php // ============================================================
      // CATÉGORIES
      // ============================================================
elseif ($section === 'categories'):
    $cats = $pdo->query("SELECT c.*,COUNT(p.id) as product_count FROM categories c LEFT JOIN products p ON p.category_id=c.id GROUP BY c.id ORDER BY c.name")->fetchAll();
?>
<div style="display:grid;grid-template-columns:340px 1fr;gap:1.5rem;align-items:start">
    <div class="adm-card">
        <div class="adm-card-header"><div class="adm-card-title">➕ Nouvelle catégorie</div></div>
        <form method="POST" style="padding:1.25rem">
            <input type="hidden" name="action_cat" value="add">
            <div class="form-group">
                <label class="form-label">Icône (emoji)</label>
                <input type="text" name="cat_icon" class="form-control" placeholder="🛍️" maxlength="4" value="🛍️">
            </div>
            <div class="form-group">
                <label class="form-label">Nom de la catégorie *</label>
                <input type="text" name="cat_name" class="form-control" placeholder="Ex: Santé & Bien-être" required>
            </div>
            <button class="btn btn-primary" style="width:100%">Ajouter</button>
        </form>
    </div>
    <div class="adm-card">
        <div class="adm-card-header"><div class="adm-card-title">Catégories (<?= count($cats) ?>)</div></div>
        <table>
            <thead><tr><th>Icône</th><th>Nom</th><th>Slug</th><th>Produits</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($cats as $c): ?>
            <tr>
                <td style="font-size:1.4rem"><?= $c['icon'] ?></td>
                <td style="font-weight:600"><?= escape($c['name']) ?></td>
                <td style="font-family:monospace;font-size:.78rem;color:#94A3B8"><?= escape($c['slug']) ?></td>
                <td><span class="badge badge-purple"><?= $c['product_count'] ?></span></td>
                <td>
                    <form method="POST" onsubmit="return confirm('Supprimer cette catégorie ?')">
                        <input type="hidden" name="action_cat" value="delete">
                        <input type="hidden" name="cat_id" value="<?= $c['id'] ?>">
                        <button class="btn btn-danger btn-sm">🗑️</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php // ============================================================
      // COMMANDES
      // ============================================================
elseif ($section === 'commandes'):
    $orders = $pdo->query("SELECT o.*,GROUP_CONCAT(oi.product_name SEPARATOR ', ') as products FROM orders o LEFT JOIN order_items oi ON oi.order_id=o.id GROUP BY o.id ORDER BY o.created_at DESC LIMIT 100")->fetchAll();
    $statusColors = ['pending'=>'badge-orange','confirmed'=>'badge-blue','shipping'=>'badge-purple','delivered'=>'badge-green','cancelled'=>'badge-red'];
    $statusLabels = ['pending'=>'En attente','confirmed'=>'Confirmée','shipping'=>'En livraison','delivered'=>'Livrée','cancelled'=>'Annulée'];
?>
<div class="adm-card">
    <div class="adm-card-header"><div class="adm-card-title"><?= count($orders) ?> commandes</div></div>
    <table>
        <thead><tr><th>#</th><th>Client</th><th>Produits</th><th>Total</th><th>Paiement</th><th>Statut</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
        <tr>
            <td style="font-weight:700;color:#7C3AED">#<?= $o['id'] ?></td>
            <td>
                <div style="font-weight:600"><?= escape($o['customer_name']) ?></div>
                <div style="font-size:.75rem;color:#94A3B8"><?= escape($o['city']??'') ?> · <?= escape($o['customer_phone']??'') ?></div>
            </td>
            <td style="max-width:200px;font-size:.78rem;color:#64748B;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= escape($o['products']??'') ?></td>
            <td style="font-weight:700"><?= number_format($o['total'],0,',',' ') ?> F</td>
            <td><span class="badge badge-gray"><?= strtoupper(str_replace('_',' ',$o['payment_method'])) ?></span></td>
            <td>
                <form method="POST" style="display:flex;gap:.3rem;align-items:center">
                    <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                    <select name="new_status" onchange="this.form.submit()" class="form-select" style="width:auto;padding:.2rem .5rem;font-size:.76rem">
                        <?php foreach ($statusLabels as $k=>$v): ?>
                        <option value="<?= $k ?>" <?= $o['status']===$k?'selected':'' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="action_order" value="1">
                </form>
            </td>
            <td style="font-size:.78rem;color:#64748B"><?= date('d/m/Y',strtotime($o['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php // ============================================================
      // LIVREURS GLOBAUX
      // ============================================================
elseif ($section === 'livreurs'):
    $editId  = (int)($_GET['edit'] ?? 0);
    $editRow = null;
    if ($editId) { $s=$pdo->prepare("SELECT * FROM global_delivery_contacts WHERE id=?");$s->execute([$editId]);$editRow=$s->fetch(); }
    $livreurs = $pdo->query("SELECT * FROM global_delivery_contacts ORDER BY zone,contact_name")->fetchAll();
    $zonesList = ['Dakar','Thiès','Kaolack','Saint-Louis','Ziguinchor','Touba','Mbour','Rufisque','Pikine','Guédiawaye','Conakry','Abidjan','Bamako','Autre'];
    $byZone = []; foreach ($livreurs as $l) $byZone[$l['zone']][] = $l;
?>
<div style="display:grid;grid-template-columns:340px 1fr;gap:1.5rem;align-items:start">
    <!-- Formulaire -->
    <div class="adm-card">
        <div class="adm-card-header"><div class="adm-card-title"><?= $editRow?'✏️ Modifier':'➕ Ajouter'?> un livreur</div></div>
        <form method="POST" style="padding:1.25rem">
            <input type="hidden" name="action_delivery" value="<?= $editRow?'edit':'add' ?>">
            <?php if ($editRow): ?><input type="hidden" name="delivery_id" value="<?= $editRow['id'] ?>"><?php endif; ?>
            <div class="form-group">
                <label class="form-label">Zone / Ville *</label>
                <select name="zone" class="form-select" onchange="document.getElementById('customZone').style.display=this.value==='Autre'?'block':'none'">
                    <?php foreach ($zonesList as $z): ?>
                    <option value="<?= $z ?>" <?= ($editRow&&$editRow['zone']===$z)?'selected':'' ?>><?= $z ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="zone_custom" id="customZone" class="form-control" style="display:none;margin-top:.5rem" placeholder="Zone personnalisée">
            </div>
            <div class="form-group">
                <label class="form-label">Nom du livreur *</label>
                <input type="text" name="contact_name" class="form-control" required placeholder="Ex: Thierno" value="<?= $editRow?escape($editRow['contact_name']):'' ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Téléphone *</label>
                <input type="tel" name="phone" class="form-control" required placeholder="+221 76 129 11 12" value="<?= $editRow?escape($editRow['phone']):'' ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Note (optionnel)</label>
                <input type="text" name="note" class="form-control" placeholder="Ex: Disponible 7j/7" value="<?= $editRow?escape($editRow['note']):'' ?>">
            </div>
            <div style="display:flex;gap:.5rem">
                <button class="btn btn-primary" style="flex:1"><?= $editRow?'💾 Enregistrer':'➕ Ajouter' ?></button>
                <?php if ($editRow): ?><a href="?section=livreurs" class="btn btn-ghost">Annuler</a><?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Liste -->
    <div>
    <?php if (empty($livreurs)): ?>
        <div style="text-align:center;padding:4rem;background:white;border-radius:14px;border:2px dashed #E2E8F0;color:#94A3B8">Aucun livreur enregistré</div>
    <?php else: foreach ($byZone as $zoneName => $rows): ?>
        <div style="margin-bottom:1.25rem">
            <div style="margin-bottom:.6rem"><span style="background:linear-gradient(135deg,#7C3AED,#EC4899);color:white;padding:.25rem .85rem;border-radius:20px;font-size:.78rem;font-weight:700">📍 <?= escape($zoneName) ?></span></div>
            <div class="adm-card">
            <table>
                <thead><tr><th>Nom</th><th>Téléphone</th><th>Note</th><th>Statut</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $l): ?>
                <tr>
                    <td style="font-weight:600"><?= escape($l['contact_name']) ?></td>
                    <td><a href="tel:<?= escape($l['phone']) ?>" style="color:#7C3AED;font-weight:600"><?= escape($l['phone']) ?></a></td>
                    <td style="font-size:.8rem;color:#64748B"><?= escape($l['note']??'—') ?></td>
                    <td>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action_delivery" value="toggle">
                            <input type="hidden" name="delivery_id" value="<?= $l['id'] ?>">
                            <button class="btn btn-ghost btn-sm"><?= $l['is_active']?'✅ Actif':'🔒 Inactif' ?></button>
                        </form>
                    </td>
                    <td style="display:flex;gap:.35rem">
                        <a href="?section=livreurs&edit=<?= $l['id'] ?>" class="btn btn-ghost btn-sm">✏️</a>
                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/','',$l['phone']) ?>" target="_blank" class="btn btn-success btn-sm">💬</a>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ?')">
                            <input type="hidden" name="action_delivery" value="delete">
                            <input type="hidden" name="delivery_id" value="<?= $l['id'] ?>">
                            <button class="btn btn-danger btn-sm">🗑️</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    <?php endforeach; endif; ?>
    </div>
</div>

<?php endif; ?>
</div><!-- adm-content -->
</main>
</body>
</html>
