<?php
$pageTitle = 'Boutique';
require_once __DIR__ . '/includes/header.php';

$search      = trim($_GET['search'] ?? '');
$categorySlug = $_GET['category'] ?? '';
$sort        = $_GET['sort'] ?? 'newest';
$page        = max(1, (int)($_GET['page'] ?? 1));
$perPage     = 16;
$offset      = ($page - 1) * $perPage;

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

$where  = ["p.status = 'active'"];
$params = [];

// Un vendeur connecté ne voit que ses propres produits
$sellerStore = null;
if (isSeller() && !isAdmin()) {
    $storeStmt = $pdo->prepare("SELECT * FROM stores WHERE user_id = ? LIMIT 1");
    $storeStmt->execute([$_SESSION['user_id']]);
    $sellerStore = $storeStmt->fetch();
    if ($sellerStore) {
        $where[]  = "p.store_id = ?";
        $params[] = $sellerStore['id'];
    }
}

if ($search) {
    $where[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($categorySlug) {
    $where[] = "c.slug = ?";
    $params[] = $categorySlug;
}

$whereClause = implode(' AND ', $where);
$orderBy = match($sort) {
    'price_asc'  => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'popular'    => 'p.views DESC',
    default      => 'p.created_at DESC',
};

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p JOIN stores s ON p.store_id = s.id LEFT JOIN categories c ON p.category_id = c.id WHERE $whereClause");
$countStmt->execute($params);
$totalProducts = (int)$countStmt->fetchColumn();
$totalPages    = (int)ceil($totalProducts / $perPage);

$stmt = $pdo->prepare("SELECT p.*, s.name as store_name, c.name as category_name, c.icon as category_icon FROM products p JOIN stores s ON p.store_id = s.id LEFT JOIN categories c ON p.category_id = c.id WHERE $whereClause ORDER BY $orderBy LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$products = $stmt->fetchAll();

$activeCategory = null;
foreach ($categories as $cat) {
    if ($cat['slug'] === $categorySlug) { $activeCategory = $cat; break; }
}
?>

<!-- Hero barre de recherche -->
<div style="background:var(--gradient);padding:2.5rem 0 0">
    <div class="container">
        <div style="max-width:640px;margin:0 auto;text-align:center;padding-bottom:2rem">
            <h1 style="color:white;font-size:2rem;font-weight:800;margin-bottom:.5rem">
                <?php if ($sellerStore): ?>
                    🏪 <?= escape($sellerStore['name']) ?>
                <?php elseif ($activeCategory): ?>
                    <?= escape($activeCategory['icon'].' '.$activeCategory['name']) ?>
                <?php else: ?>
                    Découvrez nos produits
                <?php endif; ?>
            </h1>
            <p style="color:rgba(255,255,255,.75);margin-bottom:1.25rem;font-size:.95rem">
                <?= $totalProducts ?> produit<?= $totalProducts > 1 ? 's' : '' ?> disponible<?= $totalProducts > 1 ? 's' : '' ?>
            </p>
            <!-- Barre de recherche -->
            <div style="background:white;border-radius:50px;display:flex;align-items:center;padding:.5rem .75rem .5rem 1.25rem;box-shadow:0 4px 24px rgba(0,0,0,.15)">
                <svg width="18" height="18" fill="none" stroke="#9CA3AF" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                <input type="text" id="searchInput" placeholder="Rechercher un produit..."
                       value="<?= escape($search) ?>"
                       oninput="liveSearch(this.value)"
                       style="flex:1;border:none;outline:none;padding:.4rem .75rem;font-size:.95rem;background:transparent">
                <?php if ($search): ?>
                <a href="<?= SITE_URL ?>/shop.php" style="color:#9CA3AF;font-size:1.3rem;line-height:1;padding:0 .25rem">×</a>
                <?php endif; ?>
                <button onclick="liveSearch(document.getElementById('searchInput').value)"
                        style="background:var(--primary);color:white;border:none;border-radius:40px;padding:.5rem 1.25rem;font-size:.88rem;font-weight:600;cursor:pointer;white-space:nowrap">
                    Rechercher
                </button>
            </div>
        </div>

        <!-- Catégories en chips horizontaux -->
        <div style="display:flex;gap:.5rem;overflow-x:auto;padding-bottom:1rem;scrollbar-width:none">
            <a href="<?= SITE_URL ?>/shop.php<?= $sort !== 'newest' ? '?sort='.$sort : '' ?>"
               style="flex-shrink:0;padding:.5rem 1.25rem;border-radius:50px;font-size:.85rem;font-weight:600;text-decoration:none;transition:all .2s;
                      background:<?= !$categorySlug ? 'white' : 'rgba(255,255,255,.2)' ?>;
                      color:<?= !$categorySlug ? 'var(--primary)' : 'white' ?>">
                Tout
            </a>
            <?php foreach ($categories as $cat): ?>
            <a href="<?= SITE_URL ?>/shop.php?category=<?= $cat['slug'] ?><?= $sort !== 'newest' ? '&sort='.$sort : '' ?>"
               style="flex-shrink:0;padding:.5rem 1.25rem;border-radius:50px;font-size:.85rem;font-weight:600;text-decoration:none;transition:all .2s;white-space:nowrap;
                      background:<?= $categorySlug === $cat['slug'] ? 'white' : 'rgba(255,255,255,.2)' ?>;
                      color:<?= $categorySlug === $cat['slug'] ? 'var(--primary)' : 'white' ?>">
                <?= $cat['icon'] ?> <?= escape($cat['name']) ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="container" style="padding-top:2rem;padding-bottom:3rem">

    <!-- Barre de tri -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:.75rem">
        <div style="font-size:.88rem;color:var(--gray-500)">
            <?php if ($search): ?>
                Résultats pour <strong style="color:var(--dark)">"<?= escape($search) ?>"</strong> —
            <?php endif; ?>
            <strong style="color:var(--dark)"><?= $totalProducts ?></strong> produit<?= $totalProducts > 1 ? 's' : '' ?>
        </div>
        <div style="display:flex;align-items:center;gap:.5rem">
            <span style="font-size:.85rem;color:var(--gray-500)">Trier :</span>
            <select onchange="filterBySort(this.value)"
                    style="border:1.5px solid var(--gray-200);border-radius:var(--radius-sm);padding:.4rem .75rem;font-size:.85rem;background:white;cursor:pointer;outline:none">
                <option value="newest"    <?= $sort === 'newest'     ? 'selected' : '' ?>>Plus récents</option>
                <option value="popular"   <?= $sort === 'popular'    ? 'selected' : '' ?>>Populaires</option>
                <option value="price_asc" <?= $sort === 'price_asc'  ? 'selected' : '' ?>>Prix croissant</option>
                <option value="price_desc"<?= $sort === 'price_desc' ? 'selected' : '' ?>>Prix décroissant</option>
            </select>
        </div>
    </div>

    <!-- Grille produits -->
    <?php if (empty($products)): ?>
    <div style="text-align:center;padding:5rem 1rem">
        <div style="font-size:4rem;margin-bottom:1rem">🛍️</div>
        <h2 style="font-size:1.4rem;font-weight:700;color:var(--dark);margin-bottom:.5rem">
            <?= $search ? 'Aucun résultat trouvé' : 'Aucun produit pour le moment' ?>
        </h2>
        <p style="color:var(--gray-500);margin-bottom:1.5rem;font-size:.95rem">
            <?= $search
                ? "Aucun produit ne correspond à \"".escape($search)."\". Essayez un autre mot-clé."
                : "Cette catégorie ne contient pas encore de produits. Revenez bientôt !" ?>
        </p>
        <a href="<?= SITE_URL ?>/shop.php" class="btn btn-primary">Voir tous les produits</a>
    </div>
    <?php else: ?>

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1.25rem">
        <?php foreach ($products as $p):
            $discount = $p['original_price'] ? round((1 - $p['price'] / $p['original_price']) * 100) : 0;
        ?>
        <a href="<?= SITE_URL ?>/product.php?id=<?= $p['id'] ?>" class="shop-product-card">
            <div class="shop-product-img">
                <img src="<?= getProductImageUrl($p['image']) ?>" alt="<?= escape($p['name']) ?>" loading="lazy">
                <?php if ($discount > 0): ?>
                <span class="shop-badge">-<?= $discount ?>%</span>
                <?php endif; ?>
                <?php if ($p['stock'] <= 0): ?>
                <span class="shop-badge" style="background:#6B7280">Épuisé</span>
                <?php endif; ?>
            </div>
            <div class="shop-product-body">
                <div class="shop-product-store">
                    <?= escape($p['category_icon'] ?? '') ?> <?= escape($p['store_name']) ?>
                </div>
                <div class="shop-product-name"><?= escape($p['name']) ?></div>
                <div class="shop-product-footer">
                    <div>
                        <div class="shop-product-price"><?= formatPrice($p['price']) ?></div>
                        <?php if ($p['original_price']): ?>
                        <div class="shop-product-original"><?= formatPrice($p['original_price']) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php if ($p['stock'] > 0): ?>
                    <button class="shop-cart-btn" onclick="event.preventDefault();addToCart(<?= $p['id'] ?>,1,this)" title="Ajouter au panier">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div style="display:flex;justify-content:center;align-items:center;gap:.4rem;margin-top:3rem;flex-wrap:wrap">
        <?php if ($page > 1): ?>
        <a href="?<?= http_build_query(array_merge($_GET,['page'=>$page-1])) ?>" class="page-btn">←</a>
        <?php endif; ?>
        <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
        <a href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>"
           class="page-btn <?= $i===$page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
        <a href="?<?= http_build_query(array_merge($_GET,['page'=>$page+1])) ?>" class="page-btn">→</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<style>
.shop-product-card {
    display:flex;flex-direction:column;background:white;border-radius:var(--radius);
    border:1px solid var(--gray-100);text-decoration:none;color:inherit;
    transition:transform .2s,box-shadow .2s;overflow:hidden;
}
.shop-product-card:hover { transform:translateY(-3px);box-shadow:0 8px 32px rgba(0,0,0,.1); }

.shop-product-img {
    position:relative;aspect-ratio:1;overflow:hidden;background:var(--gray-100);
}
.shop-product-img img { width:100%;height:100%;object-fit:cover;transition:transform .3s; }
.shop-product-card:hover .shop-product-img img { transform:scale(1.04); }

.shop-badge {
    position:absolute;top:.6rem;left:.6rem;background:var(--danger);color:white;
    font-size:.72rem;font-weight:700;padding:.2rem .55rem;border-radius:20px;
}

.shop-product-body { padding:.85rem;display:flex;flex-direction:column;flex:1; }
.shop-product-store { font-size:.72rem;color:var(--gray-400);margin-bottom:.3rem;font-weight:500; }
.shop-product-name {
    font-size:.92rem;font-weight:600;color:var(--dark);line-height:1.4;
    display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;
    margin-bottom:.6rem;flex:1;
}
.shop-product-footer { display:flex;align-items:flex-end;justify-content:space-between;gap:.5rem;margin-top:auto; }
.shop-product-price { font-size:1rem;font-weight:800;color:var(--primary); }
.shop-product-original { font-size:.75rem;color:var(--gray-400);text-decoration:line-through; }

.shop-cart-btn {
    width:34px;height:34px;background:var(--primary);color:white;border:none;
    border-radius:50%;display:flex;align-items:center;justify-content:center;
    cursor:pointer;flex-shrink:0;transition:background .2s;
}
.shop-cart-btn:hover { background:var(--primary-dark,#6D28D9); }

.page-btn {
    min-width:38px;height:38px;display:flex;align-items:center;justify-content:center;
    border-radius:var(--radius-sm);border:1.5px solid var(--gray-200);background:white;
    color:var(--gray-700);font-size:.88rem;font-weight:600;text-decoration:none;transition:all .2s;
    padding:0 .5rem;
}
.page-btn:hover,.page-btn.active { background:var(--primary);color:white;border-color:var(--primary); }
</style>

<!-- Widget AI Assistant -->
<div id="aiWidget">
    <!-- Bouton flottant -->
    <button id="aiToggleBtn" onclick="toggleAiChat()" title="Assistant IA">
        <span id="aiBtnIcon">✨</span>
        <span id="aiBtnLabel">Assistant IA</span>
    </button>

    <!-- Panel chat -->
    <div id="aiPanel">
        <div id="aiHeader">
            <div style="display:flex;align-items:center;gap:.6rem">
                <div style="width:34px;height:34px;background:rgba(255,255,255,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.1rem">✨</div>
                <div>
                    <div style="font-weight:700;font-size:.95rem">Assistant IA</div>
                    <div style="font-size:.72rem;opacity:.75">Trouvez le produit parfait</div>
                </div>
            </div>
            <button onclick="toggleAiChat()" style="background:none;border:none;color:white;font-size:1.3rem;cursor:pointer;padding:0;line-height:1">×</button>
        </div>

        <div id="aiMessages">
            <div class="ai-msg ai-msg-bot">
                👋 Bonjour ! Décrivez ce que vous cherchez et je vous recommande les meilleurs produits.<br>
                <span style="font-size:.75rem;opacity:.6;margin-top:.3rem;display:block">Ex : "quelque chose pour les douleurs articulaires"</span>
            </div>
        </div>

        <div id="aiInputWrap">
            <input type="text" id="aiInput" placeholder="Décrivez votre besoin..."
                   onkeydown="if(event.key==='Enter')sendAiQuery()">
            <button id="aiSendBtn" onclick="sendAiQuery()">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            </button>
        </div>
    </div>
</div>

<style>
#aiWidget { position:fixed;bottom:24px;right:24px;z-index:9999;font-family:Inter,sans-serif }

#aiToggleBtn {
    display:flex;align-items:center;gap:.5rem;
    background:linear-gradient(135deg,#7C3AED,#EC4899);
    color:white;border:none;border-radius:50px;
    padding:.7rem 1.25rem;font-size:.88rem;font-weight:700;
    cursor:pointer;box-shadow:0 4px 20px rgba(124,58,237,.4);
    transition:transform .2s,box-shadow .2s;
}
#aiToggleBtn:hover { transform:translateY(-2px);box-shadow:0 8px 28px rgba(124,58,237,.5) }

#aiPanel {
    display:none;flex-direction:column;
    width:340px;height:480px;
    background:white;border-radius:20px;
    box-shadow:0 12px 50px rgba(0,0,0,.18);
    overflow:hidden;margin-bottom:.75rem;
}
#aiPanel.open { display:flex }

#aiHeader {
    background:linear-gradient(135deg,#7C3AED,#EC4899);
    color:white;padding:.85rem 1rem;
    display:flex;align-items:center;justify-content:space-between;flex-shrink:0;
}

#aiMessages {
    flex:1;overflow-y:auto;padding:.85rem;
    display:flex;flex-direction:column;gap:.6rem;
    background:#F8FAFC;
}

.ai-msg { max-width:88%;padding:.65rem .85rem;border-radius:14px;font-size:.85rem;line-height:1.55 }
.ai-msg-bot { background:white;border:1px solid #E2E8F0;color:#1E293B;align-self:flex-start;border-radius:4px 14px 14px 14px }
.ai-msg-user { background:linear-gradient(135deg,#7C3AED,#EC4899);color:white;align-self:flex-end;border-radius:14px 14px 4px 14px }
.ai-msg-error { background:#FEE2E2;border:1px solid #FECACA;color:#991B1B;align-self:flex-start;border-radius:14px }

.ai-product-card {
    display:flex;align-items:center;gap:.6rem;
    background:white;border:1px solid #E2E8F0;border-radius:10px;
    padding:.5rem .65rem;text-decoration:none;color:inherit;
    margin-top:.35rem;transition:box-shadow .15s;
}
.ai-product-card:hover { box-shadow:0 2px 12px rgba(0,0,0,.1) }
.ai-product-card img { width:40px;height:40px;object-fit:cover;border-radius:7px;flex-shrink:0 }
.ai-product-name { font-size:.8rem;font-weight:600;color:#1E293B;line-height:1.3 }
.ai-product-price { font-size:.78rem;color:#7C3AED;font-weight:700 }

.ai-typing { display:flex;gap:4px;align-items:center;padding:.65rem .85rem }
.ai-typing span { width:7px;height:7px;background:#94A3B8;border-radius:50%;animation:aiDot 1.2s infinite }
.ai-typing span:nth-child(2) { animation-delay:.2s }
.ai-typing span:nth-child(3) { animation-delay:.4s }
@keyframes aiDot { 0%,60%,100%{transform:translateY(0)}30%{transform:translateY(-6px)} }

#aiInputWrap {
    display:flex;align-items:center;gap:.4rem;
    padding:.65rem .75rem;border-top:1px solid #E2E8F0;background:white;flex-shrink:0;
}
#aiInput {
    flex:1;border:1.5px solid #E2E8F0;border-radius:50px;
    padding:.45rem .85rem;font-size:.85rem;outline:none;
    transition:border-color .2s;
}
#aiInput:focus { border-color:#7C3AED }
#aiSendBtn {
    width:34px;height:34px;background:linear-gradient(135deg,#7C3AED,#EC4899);
    color:white;border:none;border-radius:50%;display:flex;align-items:center;
    justify-content:center;cursor:pointer;flex-shrink:0;
}
#aiSendBtn:disabled { opacity:.5;cursor:not-allowed }
</style>

<script>
const siteUrl = "<?= SITE_URL ?>";

function toggleAiChat() {
    const panel = document.getElementById('aiPanel');
    panel.classList.toggle('open');
    if (panel.classList.contains('open')) {
        document.getElementById('aiInput').focus();
        document.getElementById('aiToggleBtn').style.display = 'none';
    } else {
        document.getElementById('aiToggleBtn').style.display = 'flex';
    }
}

async function sendAiQuery() {
    const input  = document.getElementById('aiInput');
    const query  = input.value.trim();
    if (!query) return;

    const msgs   = document.getElementById('aiMessages');
    const sendBtn = document.getElementById('aiSendBtn');

    // Message utilisateur
    msgs.innerHTML += `<div class="ai-msg ai-msg-user">${escHtml(query)}</div>`;
    input.value = '';
    sendBtn.disabled = true;

    // Animation de frappe
    const typing = document.createElement('div');
    typing.className = 'ai-msg ai-msg-bot ai-typing';
    typing.innerHTML = '<span></span><span></span><span></span>';
    msgs.appendChild(typing);
    msgs.scrollTop = msgs.scrollHeight;

    try {
        const res  = await fetch(siteUrl + '/api/ai_search.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ query })
        });
        const data = await res.json();
        typing.remove();

        if (data.error) {
            msgs.innerHTML += `<div class="ai-msg ai-msg-error">⚠️ ${escHtml(data.error)}</div>`;
        } else {
            let html = `<div class="ai-msg ai-msg-bot">${escHtml(data.reply).replace(/\n/g,'<br>')}`;
            if (data.products && data.products.length) {
                data.products.forEach(p => {
                    html += `<a href="${p.url}" class="ai-product-card">
                        <img src="${p.image}" alt="${escHtml(p.name)}">
                        <div>
                            <div class="ai-product-name">${escHtml(p.name)}</div>
                            <div class="ai-product-price">${p.price}</div>
                        </div>
                    </a>`;
                });
            }
            html += '</div>';
            msgs.innerHTML += html;
        }
    } catch(e) {
        typing.remove();
        msgs.innerHTML += `<div class="ai-msg ai-msg-error">⚠️ Erreur réseau. Réessayez.</div>`;
    }

    sendBtn.disabled = false;
    msgs.scrollTop = msgs.scrollHeight;
    input.focus();
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
