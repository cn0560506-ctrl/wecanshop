<?php
$pageTitle = 'Créez votre boutique en ligne';
$pageDesc = 'WecanShop — La plateforme e-commerce africaine. Créez votre boutique, vendez vos produits, livrez partout.';
require_once __DIR__ . '/includes/header.php';

// Stats
$totalStores = $pdo->query("SELECT COUNT(*) FROM stores WHERE status='active'")->fetchColumn();
$totalProducts = $pdo->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn();
$totalOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='delivered'")->fetchColumn();
?>

<!-- Hero -->
<section class="hero">
    <div class="container">
        <div class="hero-content">
            <div class="hero-text">
                <div class="hero-badge">
                    <span>🚀</span> La plateforme e-commerce africaine #1
                </div>
                <h1>Vendez vos produits avec <span>WecanShop</span></h1>
                <p>Créez votre boutique en ligne en quelques minutes, gérez vos produits facilement et bénéficiez d'un système de livraison intégré partout en Afrique. Tout vendeur, un seul compte.</p>
                <div class="hero-buttons">
                    <a href="<?= SITE_URL ?>/register.php?type=seller" class="btn btn-primary btn-lg">
                        Créer ma boutique — Gratuit
                    </a>
                </div>
                <div class="hero-stats">
                    <div>
                        <div class="hero-stat-num counter" data-target="<?= max(1000, $totalStores * 10) ?>" data-suffix="+"><?= number_format($totalStores, 0, ',', ' ') ?>+</div>
                        <div class="hero-stat-label">Boutiques actives</div>
                    </div>
                    <div>
                        <div class="hero-stat-num counter" data-target="<?= max(50000, $totalProducts * 100) ?>" data-suffix="+"><?= number_format($totalProducts * 100, 0, ',', ' ') ?>+</div>
                        <div class="hero-stat-label">Produits disponibles</div>
                    </div>
                    <div>
                        <div class="hero-stat-num counter" data-target="<?= max(500000, $totalOrders * 1000) ?>" data-suffix="+"><?= number_format($totalOrders * 1000, 0, ',', ' ') ?>+</div>
                        <div class="hero-stat-label">Clients satisfaits</div>
                    </div>
                </div>
            </div>

            <div class="hero-image">
                <div class="hero-photo-wrap">
                    <!-- Diaporama 2 photos -->
                    <div class="hero-slides">
                        <img src="https://images.unsplash.com/photo-1573497019940-1c28c88b4f3e?w=560&h=680&fit=crop&q=85"
                             alt="Vendeur WecanShop" class="hero-slide hero-slide-1">
                        <img src="https://images.unsplash.com/photo-1531384441138-2736e62e0919?w=560&h=680&fit=crop&q=85"
                             alt="Vendeur WecanShop" class="hero-slide hero-slide-2">
                    </div>
                    <!-- Badge flottant haut-droite -->
                    <div class="hero-float hero-float-tr">
                        <span style="font-size:1.6rem">🛍️</span>
                        <div>
                            <div style="font-weight:700;font-size:.92rem;color:var(--dark)">+247 ventes</div>
                            <div style="font-size:.72rem;color:var(--gray-500)">ce mois-ci</div>
                        </div>
                    </div>
                    <!-- Badge flottant bas-gauche -->
                    <div class="hero-float hero-float-bl">
                        <span style="font-size:1.6rem">💰</span>
                        <div>
                            <div style="font-weight:700;font-size:.92rem;color:var(--dark)">1 850 000 FCFA</div>
                            <div style="font-size:.72rem;color:var(--gray-500)">revenus ce trimestre</div>
                        </div>
                    </div>
                    <!-- Badge flottant milieu-droite -->
                    <div class="hero-float hero-float-ml">
                        <span style="font-size:1.4rem">⭐</span>
                        <div>
                            <div style="font-weight:700;font-size:.88rem;color:var(--dark)">4.9 / 5</div>
                            <div style="font-size:.7rem;color:var(--gray-500)">satisfaction client</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <span class="section-tag">Fonctionnalités</span>
            <h2>Tout ce dont vous avez besoin pour vendre en ligne</h2>
            <p>WecanShop vous offre tous les outils pour lancer et gérer votre boutique en ligne avec succès.</p>
        </div>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">🏪</div>
                <h3>Boutique personnalisée</h3>
                <p>Créez votre boutique en ligne avec votre propre nom de domaine, logo et personnalisation complète.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🚚</div>
                <h3>Livraison intégrée</h3>
                <p>Système de livraison intégré avec suivi en temps réel, disponible dans toutes les grandes villes africaines.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">💳</div>
                <h3>Paiements sécurisés</h3>
                <p>Acceptez Wave, Orange Money, Stripe, PayPal et les paiements à la livraison en toute sécurité.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📊</div>
                <h3>Analytics avancés</h3>
                <p>Suivez vos ventes, vos clients et la performance de votre boutique avec des tableaux de bord intuitifs.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📱</div>
                <h3>100% Mobile</h3>
                <p>Votre boutique est optimisée pour tous les appareils. Vos clients peuvent acheter depuis leur téléphone.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🛡️</div>
                <h3>Sécurité garantie</h3>
                <p>Toutes les transactions sont sécurisées avec le chiffrement SSL. Vos données et celles de vos clients sont protégées.</p>
            </div>
        </div>
    </div>
</section>

<!-- How it works -->
<section class="section section-light">
    <div class="container">
        <div class="section-header">
            <span class="section-tag">Comment ça marche</span>
            <h2>Lancez votre boutique en 3 étapes simples</h2>
            <p>Démarrez votre activité e-commerce en moins de 10 minutes.</p>
        </div>
        <div class="steps-grid">
            <div class="step-card">
                <div class="step-num">1</div>
                <h3>Créez votre compte</h3>
                <p>Inscrivez-vous gratuitement en tant que vendeur et configurez votre profil en quelques secondes.</p>
            </div>
            <div class="step-card">
                <div class="step-num">2</div>
                <h3>Ajoutez vos produits</h3>
                <p>Publiez vos produits avec photos, descriptions et prix. Gérez votre stock facilement depuis votre dashboard.</p>
            </div>
            <div class="step-card">
                <div class="step-num">3</div>
                <h3>Commencez à vendre</h3>
                <p>Recevez les commandes, gérez les livraisons et encaissez vos paiements directement sur votre compte.</p>
            </div>
        </div>
    </div>
</section>


<!-- CTA -->
<section class="cta-section">
    <div class="container">
        <h2>Prêt à lancer votre boutique ?</h2>
        <p>Rejoignez des milliers de vendeurs qui font confiance à WecanShop. Inscription gratuite, boutique en ligne en 5 minutes.</p>
        <a href="<?= SITE_URL ?>/register.php?type=seller" class="btn btn-primary btn-lg">
            Créer ma boutique maintenant →
        </a>
    </div>
</section>

<script>const siteUrl = "<?= SITE_URL ?>";</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
