<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Déjà connecté en admin → dashboard direct
if (isLoggedIn() && isAdmin()) {
    header('Location: ' . SITE_URL . '/admin/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin' LIMIT 1");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['user_id']   = $admin['id'];
            $_SESSION['user_name'] = $admin['name'];
            $_SESSION['user_email']= $admin['email'];
            $_SESSION['role']      = $admin['role'];
            $_SESSION['started']   = true;
            header('Location: ' . SITE_URL . '/admin/index.php');
            exit;
        } else {
            $error = 'Email ou mot de passe incorrect, ou compte non administrateur.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin — Connexion WecanShop</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;
     background:linear-gradient(135deg,#0F172A 0%,#1E1B4B 50%,#4C1D95 100%);position:relative;overflow:hidden}

/* Cercles décoratifs */
body::before,body::after{content:'';position:absolute;border-radius:50%;opacity:.08}
body::before{width:600px;height:600px;background:radial-gradient(circle,#7C3AED,transparent);top:-200px;right:-200px}
body::after{width:400px;height:400px;background:radial-gradient(circle,#EC4899,transparent);bottom:-150px;left:-100px}

.login-box{
    background:white;border-radius:24px;padding:2.75rem 2.5rem;width:100%;max-width:420px;
    box-shadow:0 32px 80px rgba(0,0,0,.5);position:relative;z-index:1;
}

.logo{display:flex;align-items:center;justify-content:center;gap:.75rem;margin-bottom:2rem}
.logo-icon{width:48px;height:48px;background:linear-gradient(135deg,#7C3AED,#EC4899);border-radius:14px;
           display:flex;align-items:center;justify-content:center;color:white;font-weight:900;font-size:1.4rem}
.logo-text{font-size:1.3rem;font-weight:900;color:#0F172A}
.logo-sub{font-size:.72rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#94A3B8;margin-top:.1rem}

h1{font-size:1.3rem;font-weight:800;color:#0F172A;margin-bottom:.3rem}
.subtitle{font-size:.85rem;color:#64748B;margin-bottom:2rem}

.form-group{margin-bottom:1.1rem}
.form-label{display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.4rem}
.form-control{
    width:100%;padding:.7rem 1rem;border:1.5px solid #E2E8F0;border-radius:10px;
    font-size:.9rem;outline:none;font-family:inherit;transition:border .15s;color:#0F172A
}
.form-control:focus{border-color:#7C3AED;box-shadow:0 0 0 3px rgba(124,58,237,.08)}

.btn-login{
    width:100%;padding:.85rem;background:linear-gradient(135deg,#7C3AED,#EC4899);
    color:white;border:none;border-radius:10px;font-size:.95rem;font-weight:800;
    cursor:pointer;font-family:inherit;margin-top:.5rem;transition:opacity .15s;letter-spacing:.01em
}
.btn-login:hover{opacity:.9}
.btn-login:active{transform:scale(.99)}

.error{background:#FEF2F2;border:1px solid #FECACA;color:#DC2626;border-radius:10px;
       padding:.75rem 1rem;font-size:.83rem;font-weight:500;margin-bottom:1rem}

.back-link{display:block;text-align:center;margin-top:1.5rem;font-size:.8rem;color:#94A3B8;text-decoration:none;transition:color .15s}
.back-link:hover{color:#7C3AED}

.pw-wrap{position:relative}
.pw-toggle{position:absolute;right:.85rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#94A3B8;padding:0;display:flex}
.pw-toggle:hover{color:#7C3AED}
</style>
</head>
<body>

<div class="login-box">
    <div class="logo">
        <div class="logo-icon">W</div>
        <div>
            <div class="logo-text">WecanShop</div>
            <div class="logo-sub">Panneau Admin</div>
        </div>
    </div>

    <h1>Connexion administrateur</h1>
    <p class="subtitle">Accès réservé aux administrateurs du site.</p>

    <?php if ($error): ?>
    <div class="error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <div class="form-group">
            <label class="form-label">Adresse email</label>
            <input type="email" name="email" class="form-control"
                   placeholder="admin@wecanshop.com"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                   autofocus required>
        </div>
        <div class="form-group">
            <label class="form-label">Mot de passe</label>
            <div class="pw-wrap">
                <input type="password" name="password" id="pwField" class="form-control"
                       placeholder="••••••••" required>
                <button type="button" class="pw-toggle" onclick="togglePw()" title="Afficher/masquer">
                    <svg id="eyeIcon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                    </svg>
                </button>
            </div>
        </div>
        <button type="submit" class="btn-login">🔐 Se connecter</button>
    </form>

    <a href="<?= SITE_URL ?>/index.php" class="back-link">← Retour au site principal</a>
</div>

<script>
function togglePw() {
    const f = document.getElementById('pwField');
    f.type = f.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
