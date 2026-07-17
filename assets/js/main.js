/* WecanShop — Main JavaScript */

// Toast Notifications
function showToast(message, type = 'success') {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const icons = { success: '✅', error: '❌', info: 'ℹ️', warning: '⚠️' };
    const toast = document.createElement('div');
    toast.className = `toast ${type !== 'success' ? type : ''}`;
    toast.innerHTML = `<span class="toast-icon">${icons[type] || icons.success}</span>${message}`;
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'slideInRight .3s ease reverse';
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}

// Add to Cart
async function addToCart(productId, qty = 1, btn = null) {
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span style="opacity:.7">Ajout...</span>';
    }

    try {
        const res = await fetch(`${siteUrl}/api/cart.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'add', product_id: productId, quantity: qty })
        });
        const data = await res.json();

        if (data.success) {
            updateCartBadge(data.cart_count);
            showToast('Produit ajouté au panier !');
        } else {
            showToast(data.error || 'Erreur lors de l\'ajout', 'error');
        }
    } catch (e) {
        showToast('Erreur réseau', 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg> Ajouter au panier';
        }
    }
}

// Update cart badge in navbar
function updateCartBadge(count) {
    let badge = document.querySelector('.cart-badge');
    const cartBtn = document.querySelector('.cart-btn');
    if (!cartBtn) return;

    if (count > 0) {
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'cart-badge';
            cartBtn.appendChild(badge);
        }
        badge.textContent = count;
    } else if (badge) {
        badge.remove();
    }
}

// Cart quantity controls
function changeQty(cartItemId, newQty) {
    if (newQty < 1) {
        removeFromCart(cartItemId);
        return;
    }

    fetch(`${siteUrl}/api/cart.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'update', cart_id: cartItemId, quantity: newQty })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

function removeFromCart(cartItemId) {
    if (!confirm('Retirer ce produit du panier ?')) return;

    fetch(`${siteUrl}/api/cart.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'remove', cart_id: cartItemId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) location.reload();
    });
}

// Product quantity controls on product page
function initQtySelector() {
    const qtyInput = document.getElementById('quantity');
    if (!qtyInput) return;

    function syncQty(newVal) {
        const max = parseInt(qtyInput.max) || 999;
        newVal = Math.min(max, Math.max(1, newVal));
        qtyInput.value = newVal;
        if (typeof updateBuyLink === 'function') updateBuyLink(newVal);
    }

    document.querySelector('.qty-minus')?.addEventListener('click', () => {
        syncQty(parseInt(qtyInput.value) - 1);
    });

    document.querySelector('.qty-plus')?.addEventListener('click', () => {
        syncQty(parseInt(qtyInput.value) + 1);
    });

    qtyInput.addEventListener('input', () => {
        syncQty(parseInt(qtyInput.value) || 1);
    });
}

// Product image gallery
function changeImage(src) {
    const main = document.getElementById('mainProductImage');
    if (main) main.src = src;
    document.querySelectorAll('.thumb-img').forEach(img => img.classList.remove('active'));
    event.target.classList.add('active');
}

// Payment method selection
function selectPayment(method) {
    document.querySelectorAll('.payment-option').forEach(el => el.classList.remove('selected'));
    const opt = document.querySelector(`[data-payment="${method}"]`);
    if (opt) opt.classList.add('selected');
    const input = document.getElementById('paymentMethod');
    if (input) input.value = method;
}

// Shop filters
function filterByCategory(slug) {
    const url = new URL(window.location.href);
    if (slug) {
        url.searchParams.set('category', slug);
    } else {
        url.searchParams.delete('category');
    }
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

function filterBySort(val) {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', val);
    window.location.href = url.toString();
}

// Search with debounce
let searchTimer;
function liveSearch(val) {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        const url = new URL(window.location.href);
        if (val.trim()) {
            url.searchParams.set('search', val.trim());
        } else {
            url.searchParams.delete('search');
        }
        url.searchParams.delete('page');
        window.location.href = url.toString();
    }, 600);
}

// Dashboard: delete product
function deleteProduct(id) {
    if (!confirm('Supprimer ce produit ? Cette action est irréversible.')) return;
    fetch(`${siteUrl}/api/products.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete', id })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Produit supprimé');
            document.getElementById(`product-row-${id}`)?.remove();
        } else {
            showToast(data.error || 'Erreur', 'error');
        }
    });
}

// Modal management
function openModal(id) {
    document.getElementById(id)?.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    document.getElementById(id)?.classList.add('hidden');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay:not(.hidden)').forEach(m => {
            m.classList.add('hidden');
            document.body.style.overflow = '';
        });
    }
});

// Form validation
function validateForm(formEl) {
    let valid = true;
    formEl.querySelectorAll('[required]').forEach(field => {
        field.classList.remove('is-invalid');
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            valid = false;
        }
    });
    return valid;
}

// Role selection on register
function selectRole(role) {
    document.querySelectorAll('.role-card').forEach(c => c.classList.remove('selected'));
    document.querySelector(`[data-role="${role}"]`)?.classList.add('selected');
    const input = document.getElementById('role');
    if (input) input.value = role;
}

// Animate numbers (stats)
function animateCounter(el) {
    const target = parseInt(el.dataset.target);
    if (isNaN(target)) return;
    const duration = 1500;
    const step = target / (duration / 16);
    let current = 0;
    const timer = setInterval(() => {
        current += step;
        if (current >= target) {
            current = target;
            clearInterval(timer);
        }
        el.textContent = Math.floor(current).toLocaleString('fr-FR') + (el.dataset.suffix || '');
    }, 16);
}

// Intersection Observer for animations
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.animation = 'fadeInUp .5s ease both';
            if (entry.target.classList.contains('counter')) {
                animateCounter(entry.target);
            }
            observer.unobserve(entry.target);
        }
    });
}, { threshold: 0.1 });

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.feature-card, .step-card, .product-card, .counter').forEach(el => {
        observer.observe(el);
    });
    initQtySelector();

    // Auto-select first payment method
    const firstPayment = document.querySelector('.payment-option');
    if (firstPayment) {
        firstPayment.classList.add('selected');
        const input = document.getElementById('paymentMethod');
        if (input) input.value = firstPayment.dataset.payment;
    }
});

// Declare siteUrl from PHP (injected in each page)
if (typeof siteUrl === 'undefined') {
    var siteUrl = window.location.origin + '/wecanshop';
}
