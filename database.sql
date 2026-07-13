-- WecanShop Database Schema
CREATE DATABASE IF NOT EXISTS wecanshop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE wecanshop;

CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('buyer', 'seller', 'admin') DEFAULT 'buyer',
    phone VARCHAR(20),
    avatar VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE stores (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    logo VARCHAR(255),
    banner VARCHAR(255),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    icon VARCHAR(10) DEFAULT '🛍️'
);

CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    store_id INT NOT NULL,
    category_id INT,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(200) UNIQUE NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    original_price DECIMAL(10,2),
    stock INT DEFAULT 0,
    image VARCHAR(255),
    status ENUM('active', 'inactive') DEFAULT 'active',
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    customer_name VARCHAR(100) NOT NULL,
    customer_email VARCHAR(150) NOT NULL,
    customer_phone VARCHAR(20),
    delivery_address TEXT NOT NULL,
    city VARCHAR(100),
    total DECIMAL(10,2) NOT NULL,
    delivery_fee DECIMAL(10,2) DEFAULT 2500,
    payment_method ENUM('wave', 'orange_money', 'stripe', 'paypal', 'cash') DEFAULT 'cash',
    payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
    status ENUM('pending', 'confirmed', 'shipping', 'delivered', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(200) NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE subscriptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    store_id INT NOT NULL,
    amount DECIMAL(10,2) DEFAULT 3000.00,
    payment_method ENUM('wave','orange_money','card') NOT NULL,
    payment_phone VARCHAR(30) NULL,
    payment_reference VARCHAR(150) NULL,
    status ENUM('pending','confirmed','rejected') DEFAULT 'pending',
    period_start DATE NULL,
    period_end DATE NULL,
    admin_note TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    confirmed_at TIMESTAMP NULL,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
);

CREATE TABLE cart (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    session_id VARCHAR(100),
    product_id INT NOT NULL,
    quantity INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Données d'exemple
INSERT INTO categories (name, slug, icon) VALUES
('Mode & Vêtements', 'mode-vetements', '👗'),
('Électronique', 'electronique', '📱'),
('Maison & Décor', 'maison-decor', '🏠'),
('Beauté & Santé', 'beaute-sante', '💄'),
('Sports & Loisirs', 'sports-loisirs', '⚽'),
('Alimentation', 'alimentation', '🍎');

-- password: password (bcrypt)
INSERT INTO users (name, email, password, role, phone) VALUES
('Admin WecanShop', 'admin@wecanshop.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '+221 77 000 0000'),
('Aminata Diallo', 'vendeur@wecanshop.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'seller', '+221 77 123 4567'),
('Ibrahima Sow', 'acheteur@wecanshop.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'buyer', '+221 76 987 6543');

INSERT INTO stores (user_id, name, slug, description) VALUES
(2, 'Fashion Dakar', 'fashion-dakar', 'La boutique tendance de Dakar - Mode africaine et internationale'),
(1, 'Tech Store SN', 'tech-store-sn', 'Vente de produits électroniques et accessoires');

INSERT INTO products (store_id, category_id, name, slug, description, price, original_price, stock) VALUES
(1, 1, 'Robe Wax Élégante', 'robe-wax-elegante', 'Magnifique robe en tissu wax africain, idéale pour toutes les occasions festives. Disponible en plusieurs tailles.', 35000, 45000, 25),
(1, 1, 'Boubou Homme Grand', 'boubou-homme-grand', 'Grand boubou brodé pour homme, tissu bazin riche. Élégance et confort garantis.', 55000, 65000, 15),
(1, 4, 'Crème Karité Bio', 'creme-karite-bio', 'Crème au beurre de karité 100% naturelle, hydratante et nourrissante pour peau et cheveux.', 8500, 12000, 100),
(1, 4, 'Huile de Coco Pure', 'huile-de-coco-pure', 'Huile de noix de coco pure et naturelle, extraite à froid. Idéale pour la peau et les cheveux.', 7500, NULL, 80),
(2, 2, 'Smartphone 4G Android', 'smartphone-4g-android', 'Téléphone Android 4G, 64Go de stockage, appareil photo 48MP, batterie 5000mAh. Performant et abordable.', 185000, 220000, 12),
(2, 2, 'Écouteurs Bluetooth', 'ecouteurs-bluetooth', 'Écouteurs sans fil Bluetooth 5.0, autonomie 24h, réduction de bruit active. Son cristallin.', 28000, 35000, 40),
(2, 2, 'Chargeur Rapide USB-C', 'chargeur-rapide-usb-c', 'Chargeur rapide 65W USB-C compatible tous téléphones, charge complète en 35 minutes.', 12500, 15000, 60),
(1, 3, 'Panier Tressé Artisanal', 'panier-tresse-artisanal', 'Panier tressé à la main par des artisans sénégalais, décoration intérieure unique et authentique.', 18000, NULL, 20);
