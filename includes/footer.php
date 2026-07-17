</div><!-- end .page-content -->

<footer class="footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <a href="<?= SITE_URL ?>/index.php" class="navbar-brand" style="color:white">
                    <span class="brand-icon">W</span>
                    <span>WecanShop</span>
                </a>
                <p>La plateforme e-commerce qui vous permet de créer facilement votre boutique en ligne et de vendre vos produits partout en Afrique.</p>
                <div class="social-links">
                    <a href="#" aria-label="Facebook">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg>
                    </a>
                    <a href="#" aria-label="Instagram">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
                    </a>
                    <a href="#" aria-label="Twitter">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M23 3a10.9 10.9 0 01-3.14 1.53 4.48 4.48 0 00-7.86 3v1A10.66 10.66 0 013 4s-4 9 5 13a11.64 11.64 0 01-7 2c9 5 20 0 20-11.5a4.5 4.5 0 00-.08-.83A7.72 7.72 0 0023 3z"/></svg>
                    </a>
                </div>
            </div>

            <div class="footer-col">
                <h4>Plateforme</h4>
                <ul>
                    <li><a href="<?= SITE_URL ?>/register.php?type=seller">Devenir vendeur</a></li>
                    <li><a href="#">Tarifs</a></li>
                    <li><a href="#">Blog</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Aide</h4>
                <ul>
                    <li><a href="#">Centre d'aide</a></li>
                    <li><a href="#">Livraison</a></li>
                    <li><a href="#">Retours</a></li>
                    <li><a href="#">Contact</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Paiements acceptés</h4>
                <div class="payment-icons">
                    <span class="payment-badge">Wave</span>
                    <span class="payment-badge">Orange Money</span>
                    <span class="payment-badge">Stripe</span>
                    <span class="payment-badge">PayPal</span>
                    <span class="payment-badge">Cash</span>
                </div>
                <h4 style="margin-top:1.5rem">Newsletter</h4>
                <form class="newsletter-form" onsubmit="subscribeNewsletter(event)">
                    <input type="email" placeholder="Votre email" required>
                    <button type="submit" class="btn btn-primary" style="padding:.6rem 1rem">OK</button>
                </form>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> WecanShop. Tous droits réservés.</p>
            <div class="footer-bottom-links">
                <a href="#">Confidentialité</a>
                <a href="#">CGU</a>
                <a href="#">Mentions légales</a>
            </div>
        </div>
    </div>
</footer>

<script src="<?= SITE_URL ?>/assets/js/main.js"></script>

<script>
function toggleUserMenu() {
    document.getElementById('userDropdown').classList.toggle('show');
}
function toggleMobileMenu() {
    document.getElementById('mobileMenu').classList.toggle('show');
}
document.addEventListener('click', function(e) {
    if (!e.target.closest('.user-menu')) {
        const dd = document.getElementById('userDropdown');
        if (dd) dd.classList.remove('show');
    }
});
function subscribeNewsletter(e) {
    e.preventDefault();
    e.target.innerHTML = '<p style="color:#a78bfa">Merci pour votre inscription !</p>';
}
</script>
<!-- Bouton Telegram flottant (boutique uniquement) -->
<?php if ($currentPage === 'shop.php'): ?>
<a href="https://t.me/+7V9ZcLr2LbsyYzU0" target="_blank" id="telegramBtn" title="Contactez-nous sur Telegram">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="white">
        <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12L8.32 13.617l-2.96-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.828.942z"/>
    </svg>
    <span>Telegram</span>
</a>
<style>
#telegramBtn {
    position:fixed;bottom:24px;left:24px;z-index:9998;
    display:flex;align-items:center;gap:.5rem;
    background:#229ED9;color:white;text-decoration:none;
    padding:.65rem 1.1rem;border-radius:50px;
    font-family:Inter,sans-serif;font-size:.85rem;font-weight:700;
    box-shadow:0 4px 18px rgba(34,158,217,.45);
    transition:transform .2s,box-shadow .2s;
}
#telegramBtn:hover {
    transform:translateY(-2px);
    box-shadow:0 8px 26px rgba(34,158,217,.55);
    color:white;
}
</style>
<?php endif; ?>
</body>
</html>
