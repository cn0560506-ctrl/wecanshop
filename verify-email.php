<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$token = trim($_GET['token'] ?? '');
$status = 'invalid';

if ($token) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email_token = ? AND email_verified = 0");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $pdo->prepare("UPDATE users SET email_verified = 1, email_token = NULL WHERE id = ?")
            ->execute([$user['id']]);

        // Connexion automatique après vérification
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['role']       = $user['role'];
        $_SESSION['started']    = true;

        $status = 'success';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation email — WecanShop</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="container" style="max-width:540px;padding:4rem 1rem;text-align:center">
    <div style="background:white;border-radius:20px;padding:3rem;box-shadow:0 4px 30px rgba(0,0,0,.08);border:1px solid #EDE9FE">

        <?php if ($status === 'success'): ?>
        <div style="font-size:4rem;margin-bottom:1rem">🎉</div>
        <h1 style="font-size:1.6rem;font-weight:900;color:#0F172A;margin-bottom:.75rem">Email confirmé !</h1>
        <p style="color:#475569;font-size:.95rem;line-height:1.7;margin-bottom:2rem">
            Bienvenue <strong style="color:#7C3AED"><?= escape($user['name']) ?></strong> !<br>
            Votre boutique est maintenant activée. Vous êtes connecté.
        </p>
        <a href="<?= SITE_URL ?>/dashboard.php"
           style="display:inline-block;background:linear-gradient(135deg,#7C3AED,#EC4899);color:white;text-decoration:none;padding:14px 36px;border-radius:50px;font-weight:700;font-size:1rem">
            Accéder à mon dashboard →
        </a>
        <script>setTimeout(() => { window.location = '<?= SITE_URL ?>/dashboard.php'; }, 3000);</script>

        <?php else: ?>
        <div style="font-size:4rem;margin-bottom:1rem">❌</div>
        <h1 style="font-size:1.6rem;font-weight:900;color:#0F172A;margin-bottom:.75rem">Lien invalide</h1>
        <p style="color:#475569;font-size:.95rem;line-height:1.7;margin-bottom:2rem">
            Ce lien de confirmation est invalide ou a déjà été utilisé.
        </p>
        <a href="<?= SITE_URL ?>/login.php"
           style="display:inline-block;background:#7C3AED;color:white;text-decoration:none;padding:12px 28px;border-radius:50px;font-weight:700">
            Retour à la connexion
        </a>
        <?php endif; ?>

    </div>
</div>
</body>
</html>
