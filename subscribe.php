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

// Vérifier un paiement en attente
$pendingStmt = $pdo->prepare("SELECT * FROM subscriptions WHERE store_id = ? AND status = 'pending' ORDER BY created_at DESC LIMIT 1");
$pendingStmt->execute([$store['id']]);
$pendingPayment = $pendingStmt->fetch();

$success = $_GET['success'] ?? '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payment'])) {
    $method = $_POST['payment_method'] ?? '';
    $phone  = trim($_POST['payment_phone'] ?? '');
    $ref    = trim($_POST['payment_reference'] ?? '');

    if (!in_array($method, ['wave','orange_money','card'])) {
        $error = 'Méthode de paiement invalide.';
    } elseif ($method !== 'card' && empty($phone)) {
        $error = 'Veuillez saisir votre numéro de téléphone.';
    } elseif ($method !== 'card' && empty($ref)) {
        $error = 'Veuillez saisir votre référence de transaction.';
    } else {
        // Supprimer les paiements en attente précédents
        $pdo->prepare("DELETE FROM subscriptions WHERE store_id = ? AND status = 'pending'")->execute([$store['id']]);

        $pdo->prepare("INSERT INTO subscriptions (store_id, amount, payment_method, payment_phone, payment_reference) VALUES (?, ?, ?, ?, ?)")
            ->execute([$store['id'], SUBSCRIPTION_PRICE, $method, $phone, $ref]);

        redirect(SITE_URL . '/subscribe.php?success=1');
    }
}
?>

<div style="background:var(--gradient);padding:3rem 0 4rem">
    <div class="container" style="max-width:900px">
        <a href="<?= SITE_URL ?>/dashboard.php" style="color:rgba(255,255,255,.7);font-size:.85rem;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;margin-bottom:1.5rem">
            ← Retour au dashboard
        </a>
        <h1 style="color:white;font-size:1.9rem;font-weight:800;margin-bottom:.5rem">Abonnement WecanShop</h1>
        <p style="color:rgba(255,255,255,.75)">Continuez à vendre et gérez votre boutique sans limite.</p>
    </div>
</div>

<div class="container" style="max-width:900px;padding-top:2.5rem;padding-bottom:4rem">

    <?php if ($success): ?>
    <!-- Confirmation envoyée -->
    <div style="text-align:center;padding:3rem 2rem;background:white;border-radius:var(--radius-lg);border:2px solid #D1FAE5;margin-bottom:2rem">
        <div style="font-size:4rem;margin-bottom:1rem">✅</div>
        <h2 style="font-size:1.4rem;font-weight:800;color:var(--dark);margin-bottom:.5rem">Paiement envoyé pour vérification</h2>
        <p style="color:var(--gray-500);margin-bottom:1.5rem;max-width:480px;margin-left:auto;margin-right:auto">
            Votre paiement est en cours de vérification. Votre abonnement sera activé dans les <strong>24 heures</strong>. Vous recevrez une confirmation.
        </p>
        <a href="<?= SITE_URL ?>/dashboard.php" class="btn btn-primary">Retour au dashboard</a>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-error" style="margin-bottom:1.5rem"><?= escape($error) ?></div>
    <?php endif; ?>

    <!-- Statut actuel -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-bottom:2.5rem">
        <div style="background:white;border-radius:var(--radius);padding:1.25rem;border:1.5px solid var(--gray-100);text-align:center">
            <div style="font-size:1.8rem;font-weight:800;color:<?= $sub['in_trial'] ? '#7C3AED' : '#DC2626' ?>">
                <?= $sub['order_count'] ?>/<?= FREE_ORDER_LIMIT ?>
            </div>
            <div style="font-size:.8rem;color:var(--gray-500);margin-top:.25rem">Commandes gratuites utilisées</div>
        </div>
        <div style="background:white;border-radius:var(--radius);padding:1.25rem;border:1.5px solid var(--gray-100);text-align:center">
            <div style="font-size:1.8rem;font-weight:800;color:<?= $sub['is_subscribed'] ? '#10B981' : '#F59E0B' ?>">
                <?= $sub['is_subscribed'] ? 'Actif' : ($sub['in_trial'] ? 'Essai' : 'Expiré') ?>
            </div>
            <div style="font-size:.8rem;color:var(--gray-500);margin-top:.25rem">Statut abonnement</div>
        </div>
        <div style="background:white;border-radius:var(--radius);padding:1.25rem;border:1.5px solid var(--gray-100);text-align:center">
            <div style="font-size:1.8rem;font-weight:800;color:var(--primary)">
                <?= $sub['is_subscribed'] ? date('d/m/Y', strtotime($sub['sub_end_date'])) : '—' ?>
            </div>
            <div style="font-size:.8rem;color:var(--gray-500);margin-top:.25rem">Renouvellement</div>
        </div>
    </div>

    <?php if ($pendingPayment && !$success): ?>
    <div style="background:#FFF7ED;border:1.5px solid #FED7AA;border-radius:var(--radius);padding:1.1rem 1.4rem;margin-bottom:2rem;display:flex;align-items:center;gap:1rem">
        <span style="font-size:1.5rem">⏳</span>
        <div>
            <strong style="color:#92400E">Paiement en attente de confirmation</strong>
            <div style="font-size:.82rem;color:#B45309;margin-top:.2rem">
                Référence : <strong><?= escape($pendingPayment['payment_reference'] ?? 'N/A') ?></strong> —
                Méthode : <?= strtoupper(str_replace('_',' ',$pendingPayment['payment_method'])) ?> —
                Soumis le <?= date('d/m/Y à H:i', strtotime($pendingPayment['created_at'])) ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1.7fr;gap:2rem;align-items:start">

        <!-- Plan card -->
        <div style="background:linear-gradient(135deg,#1E1B4B,#7C3AED);border-radius:var(--radius-lg);padding:2rem;color:white;position:sticky;top:1rem">
            <div style="font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;opacity:.7;margin-bottom:.75rem">Plan mensuel</div>
            <div style="font-size:2.8rem;font-weight:900;line-height:1">3 000</div>
            <div style="font-size:.95rem;opacity:.8;margin-bottom:1.5rem">FCFA / mois <span style="font-size:.75rem;opacity:.6">(≈ 5 $)</span></div>
            <ul style="list-style:none;padding:0;margin:0 0 1.5rem;display:flex;flex-direction:column;gap:.65rem">
                <?php foreach ([
                    'Produits illimités',
                    'Commandes illimitées',
                    'Dashboard complet',
                    'Statistiques de vente',
                    'Support prioritaire',
                    'Témoignages & médias',
                ] as $f): ?>
                <li style="display:flex;align-items:center;gap:.6rem;font-size:.87rem;opacity:.9">
                    <span style="color:#A78BFA;font-size:1rem">✓</span> <?= $f ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <div style="border-top:1px solid rgba(255,255,255,.15);padding-top:1rem;font-size:.78rem;opacity:.65;line-height:1.6">
                Les 10 premières commandes sont gratuites. Aucune carte requise pour l'essai.
            </div>
        </div>

        <!-- Formulaire de paiement -->
        <?php if (!$pendingPayment || $success): ?>
        <div style="background:white;border-radius:var(--radius-lg);border:1.5px solid var(--gray-100);overflow:hidden">
            <div style="padding:1.5rem 1.75rem;border-bottom:1px solid var(--gray-100)">
                <div style="font-size:1.05rem;font-weight:800;color:var(--dark)">Choisir un mode de paiement</div>
                <div style="font-size:.82rem;color:var(--gray-500);margin-top:.2rem">Paiement sécurisé — 3 000 FCFA pour 30 jours</div>
            </div>

            <!-- Onglets méthode -->
            <div style="display:flex;border-bottom:1px solid var(--gray-100)">
                <?php
                $methods = [
                    'wave'         => ['📱','Wave'],
                    'orange_money' => ['🟠','Orange Money'],
                    'card'         => ['💳','Carte bancaire'],
                ];
                foreach ($methods as $k => [$icon,$label]):
                ?>
                <button type="button" onclick="selectPayMethod('<?= $k ?>')" id="pmtab_<?= $k ?>"
                        style="flex:1;padding:.85rem .5rem;border:none;background:none;font-size:.82rem;font-weight:600;cursor:pointer;border-bottom:2.5px solid <?= $k==='wave' ? 'var(--primary)' : 'transparent' ?>;color:<?= $k==='wave' ? 'var(--primary)' : 'var(--gray-500)' ?>;transition:all .15s">
                    <?= $icon ?> <?= $label ?>
                </button>
                <?php endforeach; ?>
            </div>

            <form method="POST" style="padding:1.75rem">
                <input type="hidden" name="submit_payment" value="1">
                <input type="hidden" name="payment_method" id="paymentMethodInput" value="wave">

                <!-- WAVE -->
                <div id="pm_wave" class="pm-section">
                    <div style="background:#F0FDF4;border-radius:10px;padding:1.1rem;margin-bottom:1.25rem;border:1px solid #BBF7D0">
                        <div style="font-weight:700;color:#166534;margin-bottom:.4rem">📱 Paiement par Wave</div>
                        <div style="font-size:.84rem;color:#15803D;line-height:1.7">
                            1. Ouvrez votre app <strong>Wave</strong><br>
                            2. Envoyez <strong>3 000 FCFA</strong> au numéro :<br>
                            <span style="font-size:1.2rem;font-weight:800;letter-spacing:.04em"><?= WAVE_NUMBER ?></span><br>
                            3. Entrez ci-dessous votre numéro et la référence reçue
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Votre numéro Wave *</label>
                        <input type="tel" name="payment_phone" class="form-control" placeholder="+221 77 000 0000">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Référence de transaction *</label>
                        <input type="text" name="payment_reference" class="form-control" placeholder="Ex: TXN123456789">
                    </div>
                </div>

                <!-- ORANGE MONEY -->
                <div id="pm_orange_money" class="pm-section" style="display:none">
                    <div style="background:#FFF7ED;border-radius:10px;padding:1.1rem;margin-bottom:1.25rem;border:1px solid #FED7AA">
                        <div style="font-weight:700;color:#92400E;margin-bottom:.4rem">🟠 Paiement par Orange Money</div>
                        <div style="font-size:.84rem;color:#B45309;line-height:1.7">
                            1. Composez le <strong>#144#</strong> ou ouvrez l'app Orange Money<br>
                            2. Effectuez un transfert de <strong>3 000 FCFA</strong> au :<br>
                            <span style="font-size:1.2rem;font-weight:800;letter-spacing:.04em"><?= OM_NUMBER ?></span><br>
                            3. Saisissez votre numéro et le code de confirmation ci-dessous
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Votre numéro Orange Money *</label>
                        <input type="tel" name="payment_phone" class="form-control" placeholder="+221 77 000 0000">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Code de confirmation *</label>
                        <input type="text" name="payment_reference" class="form-control" placeholder="Ex: OM20241234567">
                    </div>
                </div>

                <!-- CARD -->
                <div id="pm_card" class="pm-section" style="display:none">
                    <div style="background:#EFF6FF;border-radius:10px;padding:1.1rem;margin-bottom:1.25rem;border:1px solid #BFDBFE">
                        <div style="font-weight:700;color:#1E40AF;margin-bottom:.2rem">💳 Paiement par carte bancaire</div>
                        <div style="font-size:.82rem;color:#3B82F6">Visa, Mastercard — Paiement sécurisé SSL</div>
                    </div>
                    <div class="form-grid-2">
                        <div class="form-group" style="grid-column:1/-1">
                            <label class="form-label">Numéro de carte</label>
                            <input type="text" name="card_number" class="form-control" placeholder="1234 5678 9012 3456" maxlength="19" oninput="formatCard(this)">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Date d'expiration</label>
                            <input type="text" name="card_expiry" class="form-control" placeholder="MM/AA" maxlength="5">
                        </div>
                        <div class="form-group">
                            <label class="form-label">CVV</label>
                            <input type="text" name="card_cvv" class="form-control" placeholder="123" maxlength="4">
                        </div>
                        <div class="form-group" style="grid-column:1/-1">
                            <label class="form-label">Nom sur la carte</label>
                            <input type="text" name="card_name" class="form-control" placeholder="PRÉNOM NOM">
                        </div>
                    </div>
                    <input type="hidden" name="payment_phone" value="card">
                    <input type="hidden" name="payment_reference" value="CARD-<?= $store['id'] ?>-<?= time() ?>">
                </div>

                <button type="submit" style="width:100%;padding:.9rem;background:linear-gradient(135deg,#7C3AED,#EC4899);color:white;border:none;border-radius:var(--radius-sm);font-size:1rem;font-weight:800;cursor:pointer;margin-top:.5rem">
                    🔒 Payer 3 000 FCFA — Activer l'abonnement
                </button>

                <p style="text-align:center;font-size:.75rem;color:var(--gray-400);margin-top:.75rem">
                    Votre abonnement sera activé dans les 24h après confirmation du paiement.
                </p>
            </form>
        </div>
        <?php else: ?>
        <div style="background:white;border-radius:var(--radius-lg);border:1.5px solid #FED7AA;padding:2rem;text-align:center">
            <div style="font-size:3rem;margin-bottom:1rem">⏳</div>
            <h3 style="font-weight:800;color:var(--dark);margin-bottom:.5rem">Paiement en attente</h3>
            <p style="color:var(--gray-500);font-size:.88rem;margin-bottom:1.5rem">
                Votre paiement est en cours de vérification par notre équipe.<br>
                L'abonnement sera activé dans les <strong>24 heures</strong>.
            </p>
            <form method="POST">
                <input type="hidden" name="submit_payment" value="1">
                <button type="submit" name="cancel_pending" style="background:none;border:1.5px solid var(--gray-200);padding:.55rem 1.25rem;border-radius:var(--radius-sm);cursor:pointer;font-size:.85rem;color:var(--gray-500)">
                    Soumettre un autre paiement
                </button>
            </form>
        </div>
        <?php endif; ?>

    </div>
</div>

<script>
function selectPayMethod(method) {
    document.getElementById('paymentMethodInput').value = method;
    document.querySelectorAll('.pm-section').forEach(s => s.style.display = 'none');
    document.getElementById('pm_' + method).style.display = 'block';
    document.querySelectorAll('[id^="pmtab_"]').forEach(b => {
        b.style.borderBottomColor = 'transparent';
        b.style.color = 'var(--gray-500)';
    });
    document.getElementById('pmtab_' + method).style.borderBottomColor = 'var(--primary)';
    document.getElementById('pmtab_' + method).style.color = 'var(--primary)';
}

function formatCard(input) {
    let v = input.value.replace(/\D/g,'').substring(0,16);
    input.value = v.replace(/(.{4})/g,'$1 ').trim();
}
</script>

<script>const siteUrl = "<?= SITE_URL ?>";</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
