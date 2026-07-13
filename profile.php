<?php
ob_start();
$pageTitle = 'Mon Profil';
require_once __DIR__ . '/includes/header.php';
requireLogin();

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!$name) {
        $error = 'Le nom est obligatoire.';
    } elseif ($newPassword && strlen($newPassword) < 6) {
        $error = 'Le nouveau mot de passe doit contenir au moins 6 caractères.';
    } elseif ($newPassword && $newPassword !== $confirmPassword) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        if ($newPassword) {
            $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET name = ?, phone = ?, password = ? WHERE id = ?")
                ->execute([$name, $phone, $hashed, $_SESSION['user_id']]);
        } else {
            $pdo->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?")
                ->execute([$name, $phone, $_SESSION['user_id']]);
        }
        $_SESSION['user_name'] = $name;
        setFlash('success', 'Profil mis à jour !');
        redirect(SITE_URL . '/profile.php');
    }
}
?>

<div class="page-header">
    <div class="container">
        <h1>👤 Mon Profil</h1>
    </div>
</div>

<div class="container" style="padding:2rem 0;max-width:600px">
    <?= renderFlash() ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= escape($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <div style="display:flex;align-items:center;gap:1.25rem;margin-bottom:2rem;padding-bottom:1.5rem;border-bottom:1px solid var(--gray-100)">
            <div style="width:72px;height:72px;background:var(--gradient);border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:1.8rem;font-weight:700;flex-shrink:0">
                <?= strtoupper(substr($user['name'], 0, 1)) ?>
            </div>
            <div>
                <div style="font-size:1.2rem;font-weight:700"><?= escape($user['name']) ?></div>
                <div style="color:var(--gray-500)"><?= escape($user['email']) ?></div>
                <span class="badge badge-active"><?= ucfirst($user['role']) ?></span>
            </div>
        </div>

        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label">Nom complet *</label>
                <input type="text" name="name" class="form-control"
                       value="<?= escape($_POST['name'] ?? $user['name']) ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" value="<?= escape($user['email']) ?>" disabled style="opacity:.6">
            </div>
            <div class="form-group">
                <label class="form-label">Téléphone</label>
                <input type="tel" name="phone" class="form-control"
                       value="<?= escape($_POST['phone'] ?? $user['phone'] ?? '') ?>">
            </div>
            <hr style="margin:1.5rem 0;border-color:var(--gray-100)">
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label">Nouveau mot de passe</label>
                    <input type="password" name="new_password" class="form-control" placeholder="Laisser vide pour ne pas changer">
                </div>
                <div class="form-group">
                    <label class="form-label">Confirmer le mot de passe</label>
                    <input type="password" name="confirm_password" class="form-control">
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end">
                <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
            </div>
        </form>
    </div>
</div>

<script>const siteUrl = "<?= SITE_URL ?>";</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
