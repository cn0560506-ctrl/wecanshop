<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    if (isAdmin()) {
        redirect(SITE_URL . '/admin/index.php');
    } elseif (isSeller()) {
        redirect(SITE_URL . '/dashboard.php');
    } else {
        redirect(SITE_URL . '/index.php');
    }
}

$error = '';
$redirect = $_GET['redirect'] ?? SITE_URL . '/index.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Vérifier que l'email est confirmé (les admins sont exemptés)
            if (($user['email_verified'] ?? 1) == 0 && $user['role'] !== 'admin') {
                $error = 'email_not_verified';
                $unverifiedEmail = $user['email'];
                $unverifiedName  = $user['name'];
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['started'] = true;

                setFlash('success', 'Bienvenue ' . $user['name'] . ' !');
                if ($user['role'] === 'admin') {
                    redirect(SITE_URL . '/admin/index.php');
                } elseif (isSeller()) {
                    redirect(SITE_URL . '/dashboard.php');
                } else {
                    redirect($redirect);
                }
            }
        } else {
            $error = 'Email ou mot de passe incorrect.';
        }

        // Renvoi de l'email de vérification
        if ($error === 'email_not_verified' && isset($_POST['resend'])) {
            $newToken = bin2hex(random_bytes(32));
            $pdo->prepare("UPDATE users SET email_token = ? WHERE email = ?")
                ->execute([$newToken, $unverifiedEmail]);
            sendVerificationEmail($unverifiedEmail, $unverifiedName, $newToken);
            $error = 'resent';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — WecanShop</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="auth-page">
    <!-- Visual Side -->
    <div class="auth-visual">
        <div class="auth-visual-content">
            <a href="<?= SITE_URL ?>/index.php" class="navbar-brand" style="justify-content:center;margin-bottom:2rem;color:white">
                <span class="brand-icon">W</span>
                <span>WecanShop</span>
            </a>
            <h2>Bienvenue sur WecanShop</h2>
            <p>Connectez-vous pour accéder à votre tableau de bord et gérer votre boutique en ligne.</p>
            <div class="auth-feature-list">
                <div class="auth-feature">
                    <div class="auth-feature-icon">🏪</div>
                    <span>Gérez votre boutique en ligne</span>
                </div>
                <div class="auth-feature">
                    <div class="auth-feature-icon">📦</div>
                    <span>Suivez vos commandes en temps réel</span>
                </div>
                <div class="auth-feature">
                    <div class="auth-feature-icon">💰</div>
                    <span>Encaissez vos paiements facilement</span>
                </div>
                <div class="auth-feature">
                    <div class="auth-feature-icon">📊</div>
                    <span>Analysez vos performances</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Side -->
    <div class="auth-form-wrap">
        <div class="auth-form">
            <h1>Se connecter</h1>
            <p class="auth-subtitle">Pas encore de boutique ? <a href="<?= SITE_URL ?>/register.php">Créez-en une gratuitement</a></p>

            <?php if ($error === 'email_not_verified'): ?>
                <div style="background:#FFF7ED;border:1px solid #FED7AA;border-radius:12px;padding:1rem 1.25rem;margin-bottom:1.5rem">
                    <div style="font-weight:700;color:#C2410C;margin-bottom:.4rem">📧 Email non confirmé</div>
                    <div style="font-size:.88rem;color:#9A3412;margin-bottom:.75rem">
                        Vérifiez votre boîte mail et cliquez sur le lien de confirmation.
                    </div>
                    <form method="POST">
                        <input type="hidden" name="email" value="<?= escape($_POST['email'] ?? '') ?>">
                        <input type="hidden" name="password" value="<?= escape($_POST['password'] ?? '') ?>">
                        <input type="hidden" name="resend" value="1">
                        <button type="submit" style="background:#EA580C;color:white;border:none;padding:.45rem 1rem;border-radius:6px;font-size:.82rem;font-weight:600;cursor:pointer">
                            Renvoyer l'email de confirmation
                        </button>
                    </form>
                </div>
            <?php elseif ($error === 'resent'): ?>
                <div class="alert alert-success">Email de confirmation renvoyé ! Vérifiez votre boîte mail.</div>
            <?php elseif ($error): ?>
                <div class="alert alert-error"><?= escape($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="redirect" value="<?= escape($redirect) ?>">

                <div class="form-group">
                    <label class="form-label" for="email">Adresse email</label>
                    <input type="email" id="email" name="email" class="form-control"
                           placeholder="votremail@exemple.com"
                           value="<?= escape($_POST['email'] ?? '') ?>"
                           required autofocus>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">
                        Mot de passe
                        <a href="#" style="float:right;color:var(--primary);font-size:.85rem">Mot de passe oublié ?</a>
                    </label>
                    <div style="position:relative">
                        <input type="password" id="password" name="password" class="form-control"
                               placeholder="Votre mot de passe" required
                               style="padding-right:3rem">
                        <button type="button" onclick="togglePassword()" style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);color:var(--gray-500)">
                            👁
                        </button>
                    </div>
                </div>

                <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:1.5rem">
                    <input type="checkbox" id="remember" name="remember" style="width:16px;height:16px;accent-color:var(--primary)">
                    <label for="remember" style="font-size:.9rem;color:var(--gray-700)">Se souvenir de moi</label>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;padding:.85rem;font-size:1rem">
                    Se connecter
                </button>
            </form>

            <div class="auth-link">
                Pas encore de boutique ? <a href="<?= SITE_URL ?>/register.php">Créer ma boutique</a>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword() {
    const input = document.getElementById('password');
    input.type = input.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
