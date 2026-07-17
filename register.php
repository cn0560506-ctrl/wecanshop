<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) redirect(SITE_URL . '/index.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $storeName = trim($_POST['store_name'] ?? '');

    if (empty($name) || empty($email) || empty($password) || empty($storeName)) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse email invalide.';
    } elseif (strlen($password) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères.';
    } elseif ($password !== $confirm) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Cet email est déjà utilisé.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $token  = bin2hex(random_bytes(32));
            $pdo->prepare("INSERT INTO users (name, email, password, phone, role, email_verified, email_token) VALUES (?, ?, ?, ?, ?, 0, ?)")
                ->execute([$name, $email, $hashed, $phone, 'seller', $token]);
            $userId = $pdo->lastInsertId();

            $slug = preg_replace('/[^a-z0-9-]+/', '-', strtolower($storeName)) . '-' . $userId;
            $pdo->prepare("INSERT INTO stores (user_id, name, slug, description) VALUES (?, ?, ?, ?)")
                ->execute([$userId, $storeName, $slug, 'Bienvenue dans ma boutique !']);

            $sent = sendVerificationEmail($email, $name, $token);
            $_SESSION['pending_verify_email'] = $email;
            $_SESSION['pending_verify_sent']  = $sent;
            redirect(SITE_URL . '/register.php?pending=1');
        }
    }
}
// Page "en attente de vérification"
if (isset($_GET['pending'])) {
    $pendingEmail = $_SESSION['pending_verify_email'] ?? '';
    $pendingSent  = $_SESSION['pending_verify_sent'] ?? false;
    unset($_SESSION['pending_verify_email'], $_SESSION['pending_verify_sent']);
    ?>
<!DOCTYPE html><html lang="fr"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Confirmez votre email — WecanShop</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head><body>
<div class="container" style="max-width:540px;padding:4rem 1rem;text-align:center">
    <div style="background:white;border-radius:20px;padding:3rem;box-shadow:0 4px 30px rgba(0,0,0,.08);border:1px solid #EDE9FE">
        <div style="font-size:4rem;margin-bottom:1rem">📧</div>
        <h1 style="font-size:1.6rem;font-weight:900;color:#0F172A;margin-bottom:.75rem">Vérifiez votre email</h1>
        <?php if ($pendingSent): ?>
        <p style="color:#475569;font-size:.95rem;line-height:1.7;margin-bottom:1.5rem">
            Un email de confirmation a été envoyé à<br>
            <strong style="color:#7C3AED"><?= escape($pendingEmail) ?></strong><br>
            Cliquez sur le lien dans l'email pour activer votre boutique.
        </p>
        <?php else: ?>
        <p style="color:#475569;font-size:.95rem;line-height:1.7;margin-bottom:1rem">
            Votre compte a été créé. Cependant l'envoi d'email a échoué.<br>
            Utilisez le lien ci-dessous pour confirmer votre compte directement.
        </p>
        <?php
        // Afficher le lien de vérification si l'email n'a pas pu être envoyé
        $stmt = $pdo->prepare("SELECT email_token FROM users WHERE email = ?");
        $stmt->execute([$pendingEmail]);
        $fallbackToken = $stmt->fetchColumn();
        if ($fallbackToken): ?>
        <a href="<?= SITE_URL ?>/verify-email.php?token=<?= $fallbackToken ?>"
           style="display:inline-block;background:linear-gradient(135deg,#7C3AED,#EC4899);color:white;text-decoration:none;padding:12px 28px;border-radius:50px;font-weight:700;margin-bottom:1.5rem">
            ✅ Confirmer mon compte
        </a>
        <?php endif; ?>
        <?php endif; ?>
        <div style="margin-top:1.5rem;padding-top:1.5rem;border-top:1px solid #F1F5F9">
            <a href="<?= SITE_URL ?>/login.php" style="color:#7C3AED;font-size:.9rem;font-weight:600">
                ← Retour à la connexion
            </a>
        </div>
    </div>
</div>
</body></html>
    <?php exit; }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer ma boutique — WecanShop</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="auth-page">
    <div class="auth-visual">
        <div class="auth-visual-content">
            <a href="<?= SITE_URL ?>/index.php" class="navbar-brand" style="justify-content:center;margin-bottom:2rem;color:white">
                <span class="brand-icon">W</span>
                <span>WecanShop</span>
            </a>
            <h2>Créez votre boutique en ligne</h2>
            <p>Inscrivez-vous gratuitement et commencez à vendre vos produits partout en Afrique dès aujourd'hui.</p>
            <div class="auth-feature-list">
                <div class="auth-feature"><div class="auth-feature-icon">✅</div><span>Inscription 100% gratuite</span></div>
                <div class="auth-feature"><div class="auth-feature-icon">🚀</div><span>Boutique en ligne en 5 minutes</span></div>
                <div class="auth-feature"><div class="auth-feature-icon">🔒</div><span>Paiements 100% sécurisés</span></div>
                <div class="auth-feature"><div class="auth-feature-icon">🌍</div><span>Livraison partout en Afrique</span></div>
            </div>
        </div>
    </div>

    <div class="auth-form-wrap" style="overflow-y:auto">
        <div class="auth-form" style="padding:1rem 0">
            <h1>Créer ma boutique</h1>
            <p class="auth-subtitle">Déjà inscrit ? <a href="<?= SITE_URL ?>/login.php">Se connecter</a></p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= escape($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="" id="registerForm" autocomplete="off">
                <div class="form-group">
                    <label class="form-label" for="store_name">Nom de votre boutique *</label>
                    <input type="text" id="store_name" name="store_name" class="form-control"
                           placeholder="Ex: Fashion Dakar, Tech Store SN..."
                           value="<?= escape($_POST['store_name'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="name">Nom complet *</label>
                    <input type="text" id="name" name="name" class="form-control"
                           placeholder="Prénom et Nom"
                           value="<?= escape($_POST['name'] ?? '') ?>" required>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label" for="email">Email *</label>
                        <input type="email" id="email" name="email" class="form-control"
                               placeholder="email@exemple.com"
                               value="<?= escape($_POST['email'] ?? '') ?>"
                               autocomplete="off" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="phone">Téléphone</label>
                        <input type="tel" id="phone" name="phone" class="form-control"
                               placeholder="+221 77 000 0000"
                               value="<?= escape($_POST['phone'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label" for="password">Mot de passe *</label>
                        <input type="password" id="password" name="password" class="form-control"
                               placeholder="Min. 6 caractères"
                               autocomplete="new-password" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirmer *</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                               placeholder="Répétez le mot de passe"
                               autocomplete="new-password" required>
                    </div>
                </div>

                <div style="display:flex;align-items:flex-start;gap:.5rem;margin-bottom:1.5rem">
                    <input type="checkbox" id="terms" name="terms" required style="width:16px;height:16px;margin-top:.2rem;accent-color:var(--primary)">
                    <label for="terms" style="font-size:.85rem;color:var(--gray-700)">
                        J'accepte les <a href="#" style="color:var(--primary)">conditions d'utilisation</a> et la <a href="#" style="color:var(--primary)">politique de confidentialité</a>.
                    </label>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;padding:.85rem;font-size:1rem">
                    Créer ma boutique gratuitement
                </button>
            </form>

            <div class="auth-link">
                Déjà un compte ? <a href="<?= SITE_URL ?>/login.php">Se connecter</a>
            </div>
        </div>
    </div>
</div>

</body>
</html>
