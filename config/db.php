<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'wecanshop');
define('SITE_URL', 'http://localhost/wecanshop');
define('SITE_NAME', 'WecanShop');

// Clé API Anthropic (Claude) — https://console.anthropic.com/
define('ANTHROPIC_API_KEY', '');  // Coller votre clé ici

// Configuration SMTP — remplir pour activer l'envoi d'emails
define('SMTP_HOST', 'smtp.gmail.com');   // ex: smtp.gmail.com
define('SMTP_PORT', 587);
define('SMTP_USER', '');                 // votre adresse Gmail
define('SMTP_PASS', '');                 // mot de passe d'application Gmail
define('MAIL_FROM', '');                 // même que SMTP_USER
define('MAIL_FROM_NAME', 'WecanShop');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die(json_encode(['error' => 'Connexion échouée: ' . $e->getMessage()]));
}

// Colonnes optionnelles ajoutées progressivement
try { $pdo->exec("ALTER TABLE stores ADD COLUMN facebook_pixel_id VARCHAR(30) DEFAULT NULL"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE products ADD COLUMN delivery_fee INT DEFAULT 0"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE products ADD COLUMN problems_image_width TINYINT DEFAULT 100"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE products ADD COLUMN advantages_image_width TINYINT DEFAULT 100"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE products ADD COLUMN posologie_image_width TINYINT DEFAULT 100"); } catch(Exception $e) {}
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN email_verified TINYINT DEFAULT 0");
    // Les comptes existants sont déjà vérifiés
    $pdo->exec("UPDATE users SET email_verified = 1 WHERE email_verified = 0");
} catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN email_token VARCHAR(64) DEFAULT NULL"); } catch(Exception $e) {}
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS product_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        sort_order TINYINT DEFAULT 0,
        INDEX idx_product_id (product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch(Exception $e) {}
