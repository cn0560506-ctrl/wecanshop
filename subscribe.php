<?php
ob_start();
$pageTitle = 'Abonnement Vendeur';
require_once __DIR__ . '/includes/header.php';
requireSeller();

$stmt = $pdo->prepare("SELECT * FROM stores WHERE user_id = ? LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$store = $stmt->fetch();
if (!$store) { redirect(SITE_URL . '/dashboard.php'); }

$sub = getStoreSubscription($store['id']);

// Vérifier si une demande est déjà en attente
$pendingStmt = $pdo->prepare("SELECT * FROM subscriptions WHERE store_id = ? AND status = 'pending' ORDER BY created_at DESC LIMIT 1");
$pendingStmt->execute([$store['id']]);
$pendingPayment = $pendingStmt->fetch();

// Traitement "J'ai déjà payé"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notify_payment'])) {
    if (!$pendingPayment) {
        $pdo->prepare("INSERT INTO subscriptions (store_id, payment_method, amount, status, created_at) VALUES (?, 'wave', ?, 'pending', NOW())")
            ->execute([$store['id'], SUBSCRIPTION_PRICE]);
    }
    redirect(SITE_URL . '/subscribe.php?success=1');
}

$success = $_GET['success'] ?? '';
?>

<div style="background:var(--gradient);padding:2.5rem 0 3.5rem">
    <div class="container" style="max-width:560px">
        <a href="<?= SITE_URL ?>/dashboard.php" style="color:rgba(255,255,255,.7);font-size:.85rem;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;margin-bottom:1.5rem">
            ← Retour au dashboard
        </a>
        <h1 style="color:white;font-size:1.8rem;font-weight:800;margin-bottom:.4rem">Abonnement WecanShop</h1>
        <p style="color:rgba(255,255,255,.75);font-size:.9rem">3 000 FCFA / mois — Boutique illimitée</p>
    </div>
</div>

<div class="container" style="max-width:560px;padding-top:2rem;padding-bottom:4rem">

    <?php if ($success): ?>
    <div style="text-align:center;padding:2.5rem 2rem;background:white;border-radius:var(--radius-lg);border:2px solid #D1FAE5;margin-bottom:2rem">
        <div style="font-size:3.5rem;margin-bottom:.75rem">✅</div>
        <h2 style="font-size:1.3rem;font-weight:800;color:var(--dark);margin-bottom:.5rem">Paiement signalé !</h2>
        <p style="color:var(--gray-500);margin-bottom:1.5rem;font-size:.9rem">
            Votre paiement est en cours de vérification. L'abonnement sera activé dans les <strong>24 heures</strong>.
        </p>
        <a href="<?= SITE_URL ?>/dashboard.php" class="btn btn-primary">Retour au dashboard</a>
    </div>

    <?php elseif ($pendingPayment): ?>
    <div style="text-align:center;padding:2.5rem 2rem;background:white;border-radius:var(--radius-lg);border:2px solid #FED7AA">
        <div style="font-size:3rem;margin-bottom:.75rem">⏳</div>
        <h2 style="font-size:1.2rem;font-weight:800;color:var(--dark);margin-bottom:.5rem">Paiement en attente de validation</h2>
        <p style="color:var(--gray-500);font-size:.88rem;margin-bottom:1.5rem">
            Soumis le <?= date('d/m/Y à H:i', strtotime($pendingPayment['created_at'])) ?>.<br>
            L'admin validera votre abonnement sous 24h.
        </p>
        <a href="<?= SITE_URL ?>/dashboard.php" class="btn btn-primary">Retour au dashboard</a>
    </div>

    <?php else: ?>

    <!-- Statut -->
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.75rem;margin-bottom:2rem">
        <div style="background:white;border-radius:var(--radius);padding:1rem;border:1.5px solid var(--gray-100);text-align:center">
            <div style="font-size:1.5rem;font-weight:900;color:<?= $sub['in_trial'] ? '#7C3AED' : '#DC2626' ?>"><?= $sub['order_count'] ?>/<?= FREE_ORDER_LIMIT ?></div>
            <div style="font-size:.73rem;color:var(--gray-500);margin-top:.2rem">Commandes gratuites</div>
        </div>
        <div style="background:white;border-radius:var(--radius);padding:1rem;border:1.5px solid var(--gray-100);text-align:center">
            <div style="font-size:1.5rem;font-weight:900;color:<?= $sub['is_subscribed'] ? '#10B981' : '#F59E0B' ?>"><?= $sub['is_subscribed'] ? 'Actif' : ($sub['in_trial'] ? 'Essai' : 'Expiré') ?></div>
            <div style="font-size:.73rem;color:var(--gray-500);margin-top:.2rem">Statut</div>
        </div>
        <div style="background:white;border-radius:var(--radius);padding:1rem;border:1.5px solid var(--gray-100);text-align:center">
            <div style="font-size:1.5rem;font-weight:900;color:var(--dark)"><?= $sub['is_subscribed'] ? date('d/m', strtotime($sub['sub_end_date'])) : '—' ?></div>
            <div style="font-size:.73rem;color:var(--gray-500);margin-top:.2rem">Renouvellement</div>
        </div>
    </div>

    <!-- QR Code Wave -->
    <div style="background:white;border-radius:var(--radius-lg);border:1.5px solid var(--gray-100);overflow:hidden;text-align:center">

        <div style="padding:1.25rem 1.5rem;border-bottom:1px solid var(--gray-100);display:flex;align-items:center;justify-content:center;gap:.75rem">
            <span style="background:#00B4E6;color:white;border-radius:8px;padding:.3rem .6rem;font-size:1rem">📱</span>
            <div style="text-align:left">
                <div style="font-weight:800;font-size:1rem;color:var(--dark)">Paiement par Wave</div>
                <div style="font-size:.8rem;color:var(--gray-500)">Scannez pour payer 3 000 FCFA</div>
            </div>
        </div>

        <div style="padding:1.5rem">
            <div style="font-size:.82rem;font-weight:700;color:#00B4E6;margin-bottom:1rem;text-transform:uppercase;letter-spacing:.05em">
                📷 Scannez avec l'app Wave
            </div>

            <div style="border-radius:16px;overflow:hidden;width:260px;box-shadow:0 6px 24px rgba(0,180,230,.25);margin:0 auto">
                <iframe src="<?= SITE_URL ?>/assets/images/wave_qr.pdf#toolbar=0&navpanes=0&scrollbar=0&view=FitH"
                        style="width:260px;height:340px;border:none;display:block"
                        title="QR Code Wave"></iframe>
            </div>

            <div style="margin-top:1.25rem;font-size:.83rem;color:var(--gray-500);line-height:1.6">
                Après avoir effectué le paiement,<br>cliquez sur le bouton ci-dessous.
            </div>

            <form method="POST" style="margin-top:1.25rem" onsubmit="return confirm('Confirmez-vous avoir effectué le paiement de 3 000 FCFA par Wave ?')">
                <input type="hidden" name="notify_payment" value="1">
                <button type="submit" style="width:100%;padding:.9rem;background:linear-gradient(135deg,#10B981,#059669);color:white;border:none;border-radius:50px;font-size:1rem;font-weight:800;cursor:pointer;box-shadow:0 4px 18px rgba(16,185,129,.35)">
                    ✅ J'ai payé — Notifier l'admin
                </button>
            </form>

            <p style="font-size:.75rem;color:var(--gray-400);margin-top:.85rem">
                L'abonnement est activé dans les 24h après confirmation.
            </p>
        </div>
    </div>

    <?php endif; ?>

</div>

<script>const siteUrl = "<?= SITE_URL ?>";</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
