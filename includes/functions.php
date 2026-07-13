<?php
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

function requireSeller(): void {
    requireLogin();
    if ($_SESSION['role'] !== 'seller' && $_SESSION['role'] !== 'admin') {
        header('Location: ' . SITE_URL . '/index.php');
        exit;
    }
}

function isSeller(): bool {
    return isset($_SESSION['role']) && ($_SESSION['role'] === 'seller' || $_SESSION['role'] === 'admin');
}

function isAdmin(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function formatPrice(float $price): string {
    return number_format($price, 0, ',', ' ') . ' FCFA';
}

function slugify(string $text): string {
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[àáâãäå]/u', 'a', $text);
    $text = preg_replace('/[èéêë]/u', 'e', $text);
    $text = preg_replace('/[ìíîï]/u', 'i', $text);
    $text = preg_replace('/[òóôõö]/u', 'o', $text);
    $text = preg_replace('/[ùúûü]/u', 'u', $text);
    $text = preg_replace('/[ç]/u', 'c', $text);
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s_-]+/', '-', $text);
    return trim($text, '-') . '-' . substr(md5(uniqid()), 0, 6);
}

function getCartCount(): int {
    global $pdo;
    if (!isset($_SESSION['started'])) return 0;

    if (isLoggedIn()) {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    } else {
        $sessionId = session_id();
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE session_id = ?");
        $stmt->execute([$sessionId]);
    }
    return (int) $stmt->fetchColumn();
}

function getCartItems(): array {
    global $pdo;
    if (isLoggedIn()) {
        $stmt = $pdo->prepare("
            SELECT c.*, p.name, p.price, p.delivery_fee, p.image, p.stock, s.name as store_name
            FROM cart c
            JOIN products p ON c.product_id = p.id
            JOIN stores s ON p.store_id = s.id
            WHERE c.user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
    } else {
        $stmt = $pdo->prepare("
            SELECT c.*, p.name, p.price, p.delivery_fee, p.image, p.stock, s.name as store_name
            FROM cart c
            JOIN products p ON c.product_id = p.id
            JOIN stores s ON p.store_id = s.id
            WHERE c.session_id = ?
        ");
        $stmt->execute([session_id()]);
    }
    return $stmt->fetchAll();
}

function addToCart(int $productId, int $quantity = 1): bool {
    global $pdo;

    if (isLoggedIn()) {
        $stmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$_SESSION['user_id'], $productId]);
        $existing = $stmt->fetch();

        if ($existing) {
            $pdo->prepare("UPDATE cart SET quantity = quantity + ? WHERE id = ?")
                ->execute([$quantity, $existing['id']]);
        } else {
            $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)")
                ->execute([$_SESSION['user_id'], $productId, $quantity]);
        }
    } else {
        $sessionId = session_id();
        $stmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE session_id = ? AND product_id = ?");
        $stmt->execute([$sessionId, $productId]);
        $existing = $stmt->fetch();

        if ($existing) {
            $pdo->prepare("UPDATE cart SET quantity = quantity + ? WHERE id = ?")
                ->execute([$quantity, $existing['id']]);
        } else {
            $pdo->prepare("INSERT INTO cart (session_id, product_id, quantity) VALUES (?, ?, ?)")
                ->execute([$sessionId, $productId, $quantity]);
        }
    }
    return true;
}

function escape(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function uploadProductImage(array $file, ?string $oldImage = null): string|false {
    if ($file['error'] !== UPLOAD_ERR_OK) return false;

    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $maxSize = 5 * 1024 * 1024; // 5 Mo

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowed)) return false;
    if ($file['size'] > $maxSize) return false;

    $ext = match($mime) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
        default      => 'jpg',
    };

    $filename = 'product_' . uniqid() . '_' . time() . '.' . $ext;
    $dest = __DIR__ . '/../uploads/products/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) return false;

    // Supprimer l'ancienne image si elle existe
    if ($oldImage) {
        $old = __DIR__ . '/../uploads/products/' . $oldImage;
        if (file_exists($old)) unlink($old);
    }

    return $filename;
}

function getProductImageUrl(?string $image): string {
    if ($image && file_exists(__DIR__ . '/../uploads/products/' . $image)) {
        return SITE_URL . '/uploads/products/' . $image;
    }
    // Placeholder coloré selon le nom
    return 'https://placehold.co/400x400/7C3AED/ffffff?text=WecanShop';
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function renderFlash(): string {
    $flash = getFlash();
    if (!$flash) return '';
    $class = $flash['type'] === 'success' ? 'alert-success' : ($flash['type'] === 'error' ? 'alert-error' : 'alert-info');
    return '<div class="alert ' . $class . '">' . escape($flash['message']) . '</div>';
}

function healthImageUpload(string $fieldName, string $uid, string $bg, string $color, ?string $existing = null): string {
    $siteUrl = SITE_URL;
    if ($existing) {
        $preview = "<div class='himg-preview' id='hprev_{$uid}' style='display:flex'>
                       <img src='{$siteUrl}/uploads/products/{$existing}' alt=''>
                       <button type='button' onclick='removeHealthImg(\"{$uid}\")' class='himg-remove'>×</button>
                       <input type='hidden' name='keep_{$fieldName}' value='1' id='hkeep_{$uid}'>
                   </div>";
    } else {
        $preview = "<div class='himg-preview' id='hprev_{$uid}' style='display:none'>
                       <img src='' alt='' id='himg_{$uid}'>
                       <button type='button' onclick='removeHealthImg(\"{$uid}\")' class='himg-remove'>×</button>
                   </div>";
    }
    return "
    <div class='health-img-zone' style='margin-top:.75rem;padding-top:.75rem;border-top:1px dashed {$color}40'>
        <label style='font-size:.8rem;font-weight:600;color:{$color};display:flex;align-items:center;gap:.4rem;margin-bottom:.5rem'>
            📷 Image illustrative <span style='font-weight:400;color:#6B7280'>(optionnel)</span>
        </label>
        {$preview}
        <label class='himg-upload-btn' id='hlabel_{$uid}'
               style='background:{$bg};color:{$color};border:1.5px dashed {$color}'
               " . ($existing ? "style='display:none'" : "") . "
               onclick='document.getElementById(\"hfile_{$uid}\").click()'>
            <span>+ Ajouter une image</span>
        </label>
        <input type='file' id='hfile_{$uid}' name='{$fieldName}'
               accept='image/jpeg,image/png,image/webp,image/gif'
               style='display:none'
               onchange='previewHealthImg(this,\"{$uid}\")'>
    </div>";
}

function uploadTestimonialFile(array $file): string|false {
    if ($file['error'] !== UPLOAD_ERR_OK) return false;

    $maxSize = 50 * 1024 * 1024; // 50 Mo
    if ($file['size'] > $maxSize) return false;

    // Détection par extension (finfo échoue souvent pour les vidéos sur Windows)
    $origExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    $imageExts = ['jpg' => 'jpg', 'jpeg' => 'jpg', 'png' => 'png', 'webp' => 'webp', 'gif' => 'gif'];
    $videoExts = ['mp4' => 'mp4', 'webm' => 'webm', 'mov' => 'mov', 'avi' => 'avi', 'mkv' => 'mkv'];

    if (isset($imageExts[$origExt])) {
        // Pour les images : valider le MIME réel avec finfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        $allowedImageMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($mime, $allowedImageMimes)) return false;
        $ext = $imageExts[$origExt];

    } elseif (isset($videoExts[$origExt])) {
        // Pour les vidéos : on fait confiance à l'extension
        // (finfo retourne application/octet-stream sur Windows pour MP4)
        $ext = $videoExts[$origExt];

    } else {
        return false;
    }

    $filename = 'testimonial_' . uniqid() . '_' . time() . '.' . $ext;
    $dest = __DIR__ . '/../uploads/testimonials/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) return false;

    return $filename;
}

function getTestimonialType(string $filename): string {
    $videoExts = ['mp4', 'webm', 'mov', 'avi'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, $videoExts) ? 'video' : 'image';
}

// ---------------------------------------------------------------
// Email
// ---------------------------------------------------------------
function sendWecanEmail(string $to, string $toName, string $subject, string $htmlBody): bool {
    if (!defined('SMTP_HOST') || !SMTP_HOST || !MAIL_FROM) {
        // Fallback : PHP mail()
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . MAIL_FROM_NAME . " <noreply@wecanshop.com>\r\n";
        return @mail($to, $subject, $htmlBody, $headers);
    }

    // SMTP avec STARTTLS
    $sock = @fsockopen('tcp://' . SMTP_HOST, SMTP_PORT, $errno, $errstr, 10);
    if (!$sock) return false;

    $read = function() use ($sock): string {
        $out = '';
        while ($line = fgets($sock, 512)) {
            $out .= $line;
            if ($line[3] === ' ') break;
        }
        return $out;
    };
    $cmd = function(string $c) use ($sock, $read): string {
        fwrite($sock, $c . "\r\n");
        return $read();
    };

    $read();
    $cmd('EHLO wecanshop.local');
    $r = $cmd('STARTTLS');
    if (strpos($r, '220') === false) { fclose($sock); return false; }

    stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);

    $cmd('EHLO wecanshop.local');
    $cmd('AUTH LOGIN');
    $cmd(base64_encode(SMTP_USER));
    $r = $cmd(base64_encode(SMTP_PASS));
    if (strpos($r, '235') === false) { fclose($sock); return false; }

    $cmd('MAIL FROM:<' . MAIL_FROM . '>');
    $cmd('RCPT TO:<' . $to . '>');
    $cmd('DATA');

    $msg  = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
    $msg .= "To: {$toName} <{$to}>\r\n";
    $msg .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $msg .= "MIME-Version: 1.0\r\n";
    $msg .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $msg .= $htmlBody . "\r\n.";
    $cmd($msg);
    $cmd('QUIT');
    fclose($sock);
    return true;
}

function sendVerificationEmail(string $email, string $name, string $token): bool {
    $link = SITE_URL . '/verify-email.php?token=' . $token;
    $subject = 'Confirmez votre adresse email — WecanShop';
    $html = '
<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#F8FAFC;font-family:Inter,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:40px 20px">
<table width="580" cellpadding="0" cellspacing="0" style="background:white;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08)">
  <tr><td style="background:linear-gradient(135deg,#7C3AED,#EC4899);padding:40px;text-align:center">
    <div style="font-size:2rem;font-weight:900;color:white;letter-spacing:-.02em">WecanShop</div>
    <div style="color:rgba(255,255,255,.8);font-size:.95rem;margin-top:.5rem">Plateforme e-commerce africaine</div>
  </td></tr>
  <tr><td style="padding:40px">
    <h1 style="font-size:1.5rem;font-weight:800;color:#0F172A;margin:0 0 1rem">Confirmez votre email 📧</h1>
    <p style="color:#475569;font-size:.95rem;line-height:1.7;margin:0 0 1.5rem">Bonjour <strong>' . htmlspecialchars($name) . '</strong>,<br>
    Merci de vous être inscrit sur WecanShop ! Cliquez sur le bouton ci-dessous pour confirmer votre adresse email et activer votre boutique.</p>
    <div style="text-align:center;margin:2rem 0">
      <a href="' . $link . '" style="display:inline-block;background:linear-gradient(135deg,#7C3AED,#EC4899);color:white;text-decoration:none;padding:14px 36px;border-radius:50px;font-weight:700;font-size:1rem">
        ✅ Confirmer mon email
      </a>
    </div>
    <p style="color:#94A3B8;font-size:.82rem;text-align:center;margin:0">
      Ce lien est valable 24 heures.<br>
      Si vous n\'avez pas créé de compte, ignorez cet email.
    </p>
  </td></tr>
  <tr><td style="background:#F8FAFC;padding:20px;text-align:center;border-top:1px solid #E2E8F0">
    <p style="color:#94A3B8;font-size:.78rem;margin:0">© WecanShop — La plateforme e-commerce africaine</p>
  </td></tr>
</table></td></tr></table>
</body></html>';
    return sendWecanEmail($email, $name, $subject, $html);
}

// ---------------------------------------------------------------
// Abonnement
// ---------------------------------------------------------------
define('SUBSCRIPTION_PRICE',   3000);   // FCFA
define('SUBSCRIPTION_CURRENCY','FCFA');
define('FREE_ORDER_LIMIT',     5);
// Numéros marchands (à personnaliser)
define('WAVE_NUMBER',          '+221 78 113 57 17');
define('OM_NUMBER',            '+221 78 113 57 17');

function getStoreOrderCount(int $storeId): int {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT oi.order_id)
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN orders o ON oi.order_id = o.id
        WHERE p.store_id = ? AND o.status != 'cancelled'
    ");
    $stmt->execute([$storeId]);
    return (int)$stmt->fetchColumn();
}

function getStoreSubscription(int $storeId): array {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM stores WHERE id = ? LIMIT 1");
    $stmt->execute([$storeId]);
    $store = $stmt->fetch();
    $orderCount = getStoreOrderCount($storeId);

    $isSubActive = $store['subscription_status'] === 'active'
        && $store['subscription_end_date']
        && $store['subscription_end_date'] >= date('Y-m-d');

    return [
        'order_count'      => $orderCount,
        'free_limit'       => FREE_ORDER_LIMIT,
        'in_trial'         => $orderCount < FREE_ORDER_LIMIT,
        'trial_remaining'  => max(0, FREE_ORDER_LIMIT - $orderCount),
        'is_subscribed'    => $isSubActive,
        'sub_status'       => $store['subscription_status'],
        'sub_end_date'     => $store['subscription_end_date'],
        'needs_payment'    => !$isSubActive && $orderCount >= FREE_ORDER_LIMIT,
    ];
}

function requireActiveSubscription(int $storeId): void {
    $sub = getStoreSubscription($storeId);
    if ($sub['needs_payment']) {
        header('Location: ' . SITE_URL . '/subscribe.php');
        exit;
    }
}

function timeAgo(string $datetime): string {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return 'À l\'instant';
    if ($diff < 3600) return floor($diff / 60) . ' min';
    if ($diff < 86400) return floor($diff / 3600) . 'h';
    if ($diff < 2592000) return floor($diff / 86400) . ' jours';
    return date('d/m/Y', $time);
}
