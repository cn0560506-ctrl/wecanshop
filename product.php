<?php
require_once __DIR__ . '/includes/header.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . SITE_URL . '/index.php'); exit; }

$stmt = $pdo->prepare("
    SELECT p.*, s.name as store_name, s.slug as store_slug, s.description as store_desc,
           s.facebook_pixel_id,
           c.name as category_name, c.slug as category_slug
    FROM products p
    JOIN stores s ON p.store_id = s.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.id = ? AND p.status = 'active'
");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) { header('Location: ' . SITE_URL . '/index.php'); exit; }

// Increment views
$pdo->prepare("UPDATE products SET views = views + 1 WHERE id = ?")->execute([$id]);

// Testimonials
$testimonialsStmt = $pdo->prepare("SELECT * FROM product_testimonials WHERE product_id = ? ORDER BY sort_order");
$testimonialsStmt->execute([$id]);
$testimonials = $testimonialsStmt->fetchAll();

// Related products
$related = $pdo->prepare("
    SELECT p.*, s.name as store_name
    FROM products p JOIN stores s ON p.store_id = s.id
    WHERE p.category_id = ? AND p.id != ? AND p.status = 'active'
    ORDER BY RAND() LIMIT 4
");
$related->execute([$product['category_id'], $id]);
$relatedProducts = $related->fetchAll();

$discount = $product['original_price']
    ? round((1 - $product['price'] / $product['original_price']) * 100)
    : 0;

$pageTitle = $product['name'];
$pageDesc = substr(strip_tags($product['description']), 0, 150);
?>

<div class="container">
    <!-- Breadcrumb -->
    <nav style="padding:1.5rem 0;font-size:.88rem;color:var(--gray-500);display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
        <a href="<?= SITE_URL ?>" style="color:var(--gray-500)">Accueil</a> ›
        <?php if ($product['category_name']): ?>
            <span style="color:var(--gray-500)"><?= escape($product['category_name']) ?></span> ›
        <?php endif; ?>
        <span style="color:var(--dark)"><?= escape($product['name']) ?></span>
    </nav>

    <div class="product-detail-grid">
        <!-- Product Image -->
        <div>
            <div class="product-main-image" id="mainImageWrap">
                <img id="mainProductImage"
                     src="<?= getProductImageUrl($product['image']) ?>"
                     alt="<?= escape($product['name']) ?>">
            </div>
        </div>

        <!-- Product Info -->
        <div class="product-detail-info">

            <!-- Prix -->
            <div class="product-detail-prices">
                <span class="product-detail-price"><?= formatPrice($product['price']) ?></span>
                <?php if ($product['original_price']): ?>
                    <span class="product-detail-original"><?= formatPrice($product['original_price']) ?></span>
                    <span class="product-discount-badge">-<?= $discount ?>%</span>
                <?php endif; ?>
            </div>

            <?php if ($product['stock'] > 0): ?>
            <!-- Urgence stock -->
            <div style="font-size:.88rem;font-weight:700;color:#DC2626;margin-bottom:.5rem">
                🔥 Dépêchez-vous ! Seulement <strong><?= $product['stock'] ?></strong> en stock !
            </div>

            <!-- Visiteurs en temps réel -->
            <div style="display:inline-flex;align-items:center;gap:.4rem;background:#FEF3C7;color:#92400E;padding:.3rem .75rem;border-radius:20px;font-size:.78rem;font-weight:700;margin-bottom:1rem">
                <span id="visitorDot" style="width:8px;height:8px;background:#10B981;border-radius:50%;animation:pulse 1.5s infinite"></span>
                <span id="visitorCount">4</span> visiteurs en ce moment
            </div>
            <?php endif; ?>

            <!-- Compte à rebours (toujours visible) -->
            <div style="margin-bottom:1.25rem">
                <div style="font-size:.75rem;font-weight:700;color:#DC2626;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.4rem">⏱ Offre se termine dans :</div>
                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:.5rem;text-align:center;max-width:280px">
                    <?php foreach (['Jour','Heure','Minute','Seconde'] as $label): ?>
                    <div>
                        <div id="cd_<?= strtolower($label) ?>"
                             style="background:#1E1B4B;color:white;font-size:1.4rem;font-weight:900;border-radius:8px;padding:.4rem .2rem;letter-spacing:.05em">00</div>
                        <div style="font-size:.65rem;color:var(--gray-500);margin-top:.25rem;font-weight:600"><?= $label ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Description libre -->
            <?php if ($product['description']): ?>
            <div style="margin-bottom:1.5rem">
                <div style="font-size:1.1rem;font-weight:900;color:var(--dark);text-transform:uppercase;margin-bottom:.75rem;line-height:1.3">
                    <?= strtoupper(escape($product['name'])) ?>
                </div>
                <div style="font-size:.92rem;color:var(--gray-700);line-height:1.8;white-space:pre-line"><?= escape($product['description']) ?></div>
            </div>
            <?php endif; ?>

            <!-- Problèmes du patient -->
            <?php if (!empty($product['patient_problems'])): ?>
            <div style="margin-bottom:1.5rem">
                <div style="font-size:.95rem;font-weight:900;color:#DC2626;text-transform:uppercase;margin-bottom:.6rem">
                    😣 RESSENTEZ-VOUS CES PROBLÈMES :
                </div>
                <div style="font-size:.9rem;color:var(--gray-700);line-height:2;white-space:pre-line"><?= escape($product['patient_problems']) ?></div>
                <?php if (!empty($product['problems_image'])): ?>
                <?php $pw = $product['problems_image_width'] ?? 100; ?>
                <div style="text-align:center;margin-top:1rem">
                <img src="<?= SITE_URL ?>/uploads/products/<?= escape($product['problems_image']) ?>"
                     style="width:<?= $pw ?>%;border-radius:10px;display:inline-block">
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Avantages -->
            <?php if (!empty($product['advantages'])): ?>
            <div style="margin-bottom:1.5rem">
                <div style="font-size:.95rem;font-weight:900;color:#065F46;text-transform:uppercase;margin-bottom:.6rem">
                    ✅ AVANTAGES DU PRODUIT :
                </div>
                <div style="font-size:.9rem;color:var(--gray-700);line-height:2;white-space:pre-line"><?= escape($product['advantages']) ?></div>
                <?php if (!empty($product['advantages_image'])): ?>
                <?php $aw = $product['advantages_image_width'] ?? 100; ?>
                <div style="text-align:center;margin-top:1rem">
                <img src="<?= SITE_URL ?>/uploads/products/<?= escape($product['advantages_image']) ?>"
                     style="width:<?= $aw ?>%;border-radius:10px;display:inline-block">
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Posologie -->
            <?php if (!empty($product['posologie'])): ?>
            <div style="margin-bottom:1.5rem">
                <div style="font-size:.95rem;font-weight:900;color:#5B21B6;text-transform:uppercase;margin-bottom:.6rem">
                    💊 POSOLOGIE / MODE D'EMPLOI :
                </div>
                <div style="font-size:.9rem;color:var(--gray-700);line-height:2;white-space:pre-line"><?= escape($product['posologie']) ?></div>
                <?php if (!empty($product['posologie_image'])): ?>
                <?php $psw = $product['posologie_image_width'] ?? 100; ?>
                <div style="text-align:center;margin-top:1rem">
                <img src="<?= SITE_URL ?>/uploads/products/<?= escape($product['posologie_image']) ?>"
                     style="width:<?= $psw ?>%;border-radius:10px;display:inline-block">
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Témoignages clients -->
            <?php if (!empty($testimonials)): ?>
            <div style="margin-bottom:1.5rem">
                <div style="font-size:.95rem;font-weight:900;color:var(--dark);text-transform:uppercase;margin-bottom:.75rem">⭐ TÉMOIGNAGES CLIENTS</div>
                <div class="testimonials-gallery" style="grid-template-columns:repeat(auto-fill,minmax(130px,1fr))">
                    <?php foreach ($testimonials as $t): ?>
                    <div class="testimonial-gallery-item" onclick="openTestimonialModal('<?= SITE_URL ?>/uploads/testimonials/<?= escape($t['file_name']) ?>','<?= $t['file_type'] ?>','<?= escape($t['caption'] ?? '') ?>')">
                        <?php if ($t['file_type'] === 'video'): ?>
                            <video src="<?= SITE_URL ?>/uploads/testimonials/<?= escape($t['file_name']) ?>"
                                   preload="metadata" muted
                                   style="width:100%;height:110px;object-fit:cover;display:block;pointer-events:none;background:#1a1a2e"></video>
                            <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:36px;height:36px;background:rgba(124,58,237,.85);border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:1rem;pointer-events:none">▶</div>
                        <?php else: ?>
                            <img src="<?= SITE_URL ?>/uploads/testimonials/<?= escape($t['file_name']) ?>"
                                 alt="<?= escape($t['caption'] ?? 'Témoignage') ?>"
                                 style="width:100%;height:110px;object-fit:cover;display:block">
                        <?php endif; ?>
                        <?php if ($t['caption']): ?>
                        <div class="testimonial-gallery-caption"><?= escape($t['caption']) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($product['stock'] > 0): ?>
            <!-- Quantité -->
            <div class="qty-selector">
                <label>Quantité</label>
                <div class="qty-controls">
                    <button type="button" onclick="var i=document.getElementById('quantity'),v=Math.max(1,parseInt(i.value)-1);i.value=v;updateBuyLink(v);">−</button>
                    <input type="number" id="quantity" value="1" min="1" max="<?= $product['stock'] ?>" oninput="var v=Math.min(<?= $product['stock'] ?>,Math.max(1,parseInt(this.value)||1));this.value=v;updateBuyLink(v);">
                    <button type="button" onclick="var i=document.getElementById('quantity'),v=Math.min(<?= $product['stock'] ?>,parseInt(i.value)+1);i.value=v;updateBuyLink(v);">+</button>
                </div>
            </div>
            <a id="buyNowBtn" href="<?= SITE_URL ?>/checkout.php?buy_now=<?= $product['id'] ?>&qty=1"
               class="btn btn-primary btn-lg" style="width:100%;text-align:center;margin-bottom:.75rem">
                🛒 Commander maintenant
            </a>
            <?php else: ?>
            <div style="background:var(--gray-100);border-radius:var(--radius-sm);padding:1rem;text-align:center;color:var(--gray-500);margin-bottom:1rem">
                <strong>Rupture de stock</strong> — Ce produit n'est plus disponible
            </div>
            <?php endif; ?>

            <!-- Meta Info -->
            <div class="product-meta" style="margin-top:1.5rem">
                <span>
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                    Catégorie : <?= escape($product['category_name'] ?? 'Non classé') ?>
                </span>
                <span>
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    Publié le <?= date('d/m/Y', strtotime($product['created_at'])) ?>
                </span>
                <span>
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    <?= number_format($product['views'], 0, ',', ' ') ?> vues
                </span>
            </div>

            <!-- Store Card -->
            <div class="store-card">
                <div class="store-card-logo"><?= strtoupper(substr($product['store_name'], 0, 1)) ?></div>
                <div>
                    <div class="store-card-name"><?= escape($product['store_name']) ?></div>
                    <div class="store-card-sub"><?= escape(substr($product['store_desc'] ?? '', 0, 60)) ?>...</div>
                </div>
            </div>

            <!-- Delivery Info -->
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.75rem;margin-top:1.5rem">
                <div style="text-align:center;padding:.75rem;background:var(--gray-100);border-radius:var(--radius-sm)">
                    <div style="font-size:1.3rem">🚚</div>
                    <div style="font-size:.75rem;font-weight:600;color:var(--dark);margin-top:.25rem">Livraison rapide</div>
                    <div style="font-size:.7rem;color:var(--gray-500)">30 min à 1H</div>
                </div>
                <div style="text-align:center;padding:.75rem;background:var(--gray-100);border-radius:var(--radius-sm)">
                    <div style="font-size:1.3rem">🔒</div>
                    <div style="font-size:.75rem;font-weight:600;color:var(--dark);margin-top:.25rem">Paiement sécurisé</div>
                    <div style="font-size:.7rem;color:var(--gray-500)">SSL 256 bits</div>
                </div>
                <div style="text-align:center;padding:.75rem;background:var(--gray-100);border-radius:var(--radius-sm)">
                    <div style="font-size:1.3rem">↩️</div>
                    <div style="font-size:.75rem;font-weight:600;color:var(--dark);margin-top:.25rem">Retour facile</div>
                    <div style="font-size:.7rem;color:var(--gray-500)">7 jours</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal lightbox témoignage (utilisé par les témoignages inline) -->
    <?php if (!empty($testimonials)): ?>
    <div id="testimonialModal" class="modal-overlay" style="display:none" onclick="if(event.target===this)closeTestimonialModal()">
        <div style="max-width:800px;width:100%;background:black;border-radius:var(--radius-lg);overflow:hidden;position:relative">
            <button onclick="closeTestimonialModal()" style="position:absolute;top:.75rem;right:.75rem;z-index:10;background:rgba(0,0,0,.6);color:white;width:36px;height:36px;border-radius:50%;font-size:1.2rem;display:flex;align-items:center;justify-content:center">×</button>
            <div id="testimonialModalContent"></div>
            <div id="testimonialModalCaption" style="padding:.75rem 1rem;color:white;font-size:.9rem;background:rgba(0,0,0,.8)"></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Related Products -->
    <?php if (!empty($relatedProducts)): ?>
    <div style="padding:3rem 0">
        <h2 style="font-size:1.5rem;font-weight:800;color:var(--dark);margin-bottom:1.5rem">Produits similaires</h2>
        <div class="products-grid">
            <?php foreach ($relatedProducts as $rp):
                $rdiscount = $rp['original_price'] ? round((1 - $rp['price'] / $rp['original_price']) * 100) : 0;
            ?>
            <div class="product-card">
                <div class="product-image-wrap">
                    <a href="<?= SITE_URL ?>/product.php?id=<?= $rp['id'] ?>">
                        <img src="<?= getProductImageUrl($rp['image']) ?>" alt="<?= escape($rp['name']) ?>" loading="lazy">
                    </a>
                    <?php if ($rdiscount > 0): ?>
                        <span class="product-badge">-<?= $rdiscount ?>%</span>
                    <?php endif; ?>
                </div>
                <div class="product-info">
                    <div class="product-store"><?= escape($rp['store_name']) ?></div>
                    <a href="<?= SITE_URL ?>/product.php?id=<?= $rp['id'] ?>">
                        <div class="product-name"><?= escape($rp['name']) ?></div>
                    </a>
                    <div class="product-price-row">
                        <span class="product-price"><?= formatPrice($rp['price']) ?></span>
                        <?php if ($rp['original_price']): ?>
                            <span class="product-original-price"><?= formatPrice($rp['original_price']) ?></span>
                        <?php endif; ?>
                    </div>
                    <a href="<?= SITE_URL ?>/checkout.php?buy_now=<?= $rp['id'] ?>"
                       class="product-add-btn" style="text-decoration:none;display:block;text-align:center">
                        Acheter maintenant
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
const siteUrl = "<?= SITE_URL ?>";
const productId = <?= $product['id'] ?>;

function updateBuyLink(qty) {
    const btn = document.getElementById('buyNowBtn');
    if (btn) btn.href = siteUrl + '/checkout.php?buy_now=' + productId + '&qty=' + qty;
}

// Compte à rebours (5 min, persistant par produit via localStorage)
(function() {
    const key = 'cd_end_<?= $product['id'] ?>';
    let end = parseInt(localStorage.getItem(key));
    if (!end || end < Date.now()) {
        end = Date.now() + 5 * 60 * 1000;
        localStorage.setItem(key, end);
    }
    function tick() {
        const diff = Math.max(0, end - Date.now());
        const j = Math.floor(diff / 86400000);
        const h = Math.floor((diff % 86400000) / 3600000);
        const m = Math.floor((diff % 3600000) / 60000);
        const s = Math.floor((diff % 60000) / 1000);
        const f = n => String(n).padStart(2,'0');
        const dj = document.getElementById('cd_jour');
        const dh = document.getElementById('cd_heure');
        const dm = document.getElementById('cd_minute');
        const ds = document.getElementById('cd_seconde');
        if (dj) { dj.textContent=f(j); dh.textContent=f(h); dm.textContent=f(m); ds.textContent=f(s); }
    }
    tick();
    setInterval(tick, 1000);
})();

// Visiteurs en temps réel (fluctuation aléatoire)
(function() {
    const el = document.getElementById('visitorCount');
    if (!el) return;
    let v = Math.floor(Math.random() * 5) + 3;
    el.textContent = v;
    setInterval(() => {
        v = Math.max(2, Math.min(12, v + (Math.random() > .5 ? 1 : -1)));
        el.textContent = v;
    }, 8000);
})();

function openTestimonialModal(src, type, caption) {
    const content = document.getElementById('testimonialModalContent');
    const capEl   = document.getElementById('testimonialModalCaption');
    if (type === 'video') {
        content.innerHTML = `<video src="${src}" controls autoplay muted style="width:100%;max-height:70vh;display:block;background:#000"></video>`;
    } else {
        content.innerHTML = `<img src="${src}" style="width:100%;max-height:70vh;object-fit:contain;display:block">`;
    }
    capEl.textContent = caption || '';
    capEl.style.display = caption ? 'block' : 'none';
    const modal = document.getElementById('testimonialModal');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeTestimonialModal() {
    const modal = document.getElementById('testimonialModal');
    modal.querySelector('video')?.pause();
    modal.style.display = 'none';
    document.body.style.overflow = '';
}
</script>
<?php if (!empty($product['facebook_pixel_id'])): ?>
<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '<?= escape($product['facebook_pixel_id']) ?>');
fbq('track', 'PageView');
fbq('track', 'ViewContent', {
    content_name: '<?= addslashes(escape($product['name'])) ?>',
    content_ids: ['<?= $product['id'] ?>'],
    content_type: 'product',
    value: <?= (float)$product['price'] ?>,
    currency: 'XOF'
});
</script>
<noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=<?= escape($product['facebook_pixel_id']) ?>&ev=PageView&noscript=1"/></noscript>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
