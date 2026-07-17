<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    $_SESSION['started'] = true;
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

$cartCount = getCartCount();
$currentPage = basename($_SERVER['PHP_SELF']);

// Nom de la boutique du vendeur connecté
$navBrandName = 'WecanShop';
$navBrandLetter = 'W';
if (isSeller() && !isAdmin() && isset($_SESSION['user_id'])) {
    $storeNameStmt = $pdo->prepare("SELECT name FROM stores WHERE user_id = ? LIMIT 1");
    $storeNameStmt->execute([$_SESSION['user_id']]);
    $storeName = $storeNameStmt->fetchColumn();
    if ($storeName) {
        $navBrandName   = $storeName;
        $navBrandLetter = strtoupper(mb_substr($storeName, 0, 1, 'UTF-8'));
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? escape($pageTitle) . ' — ' : '' ?>WecanShop</title>
    <meta name="description" content="<?= isset($pageDesc) ? escape($pageDesc) : 'Créez votre boutique en ligne et vendez vos produits facilement avec WecanShop.' ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
    <link rel="icon" href="<?= SITE_URL ?>/assets/images/favicon.svg" type="image/svg+xml">
</head>
<body class="<?= $bodyClass ?? '' ?>">

<nav class="navbar">
    <div class="container navbar-inner">
        <a href="<?= SITE_URL ?>/index.php" class="navbar-brand">
            <span class="brand-icon"><?= $navBrandLetter ?></span>
            <span><?= escape($navBrandName) ?></span>
        </a>

        <div class="navbar-links">
            <?php if (isLoggedIn()): ?>
                <a href="<?= SITE_URL ?>/shop.php" class="nav-link <?= $currentPage === 'shop.php' ? 'active' : '' ?>">Boutique</a>
            <?php endif; ?>
            <?php if (isSeller()): ?>
                <a href="<?= SITE_URL ?>/dashboard.php" class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">Dashboard</a>
            <?php endif; ?>
        </div>

        <div class="navbar-actions">
            <?php if (isLoggedIn()): ?>
                <div class="user-menu">
                    <button class="user-btn" onclick="toggleUserMenu()">
                        <div class="user-avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?></div>
                        <span><?= escape(explode(' ', $_SESSION['user_name'] ?? '')[0]) ?></span>
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                    <div class="user-dropdown" id="userDropdown">
                        <?php if (isSeller()): ?>
                            <a href="<?= SITE_URL ?>/dashboard.php">Mon Dashboard</a>
                        <?php endif; ?>
                        <a href="<?= SITE_URL ?>/orders.php">Mes Commandes</a>
                        <a href="<?= SITE_URL ?>/profile.php">Mon Profil</a>
                        <hr>
                        <a href="<?= SITE_URL ?>/logout.php" class="logout-link">Déconnexion</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="<?= SITE_URL ?>/login.php" class="btn btn-outline">Connexion</a>
                <a href="<?= SITE_URL ?>/register.php" class="btn btn-primary">S'inscrire</a>
            <?php endif; ?>

            <button class="mobile-menu-btn" onclick="toggleMobileMenu()" aria-label="Menu">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>

    <div class="mobile-menu" id="mobileMenu">
        <?php if (isLoggedIn()): ?>
            <a href="<?= SITE_URL ?>/shop.php">Boutique</a>
        <?php endif; ?>
        <?php if (isSeller()): ?>
            <a href="<?= SITE_URL ?>/dashboard.php">Dashboard</a>
        <?php endif; ?>
        <?php if (isLoggedIn()): ?>
            <a href="<?= SITE_URL ?>/orders.php">Mes commandes</a>
            <a href="<?= SITE_URL ?>/profile.php">Mon profil</a>
            <a href="<?= SITE_URL ?>/logout.php">Déconnexion</a>
        <?php else: ?>
            <a href="<?= SITE_URL ?>/login.php">Connexion</a>
            <a href="<?= SITE_URL ?>/register.php">S'inscrire</a>
        <?php endif; ?>
    </div>
</nav>

<div class="page-content">
