-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : ven. 17 juil. 2026 à 18:56
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `wecanshop`
--

-- --------------------------------------------------------

--
-- Structure de la table `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `session_id` varchar(100) DEFAULT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `icon` varchar(10) DEFAULT '?️'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `categories`
--

INSERT INTO `categories` (`id`, `name`, `slug`, `icon`) VALUES
(1, 'Mode & Vêtements', 'mode-vetements', '👗'),
(2, 'Électronique', 'electronique', '📱'),
(3, 'Maison & Décor', 'maison-decor', '🏠'),
(4, 'Beauté & Santé', 'beaute-sante', '💄'),
(5, 'Sports & Loisirs', 'sports-loisirs', '⚽'),
(6, 'Alimentation', 'alimentation', '🍎');

-- --------------------------------------------------------

--
-- Structure de la table `delivery_contacts`
--

CREATE TABLE `delivery_contacts` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `zone` varchar(100) NOT NULL,
  `contact_name` varchar(100) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `global_delivery_contacts`
--

CREATE TABLE `global_delivery_contacts` (
  `id` int(11) NOT NULL,
  `zone` varchar(100) NOT NULL,
  `contact_name` varchar(100) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `note` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `global_delivery_contacts`
--

INSERT INTO `global_delivery_contacts` (`id`, `zone`, `contact_name`, `phone`, `note`, `is_active`, `created_at`) VALUES
(1, 'Dakar', 'Thierno', '761291112', '7j/7', 1, '2026-06-26 16:41:33'),
(2, 'Pikine', 'cheikh', '+221706440533', '7j/7', 1, '2026-06-26 22:20:43');

-- --------------------------------------------------------

--
-- Structure de la table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_email` varchar(150) NOT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `delivery_address` text NOT NULL,
  `city` varchar(100) DEFAULT NULL,
  `total` decimal(10,2) NOT NULL,
  `delivery_fee` decimal(10,2) DEFAULT 2500.00,
  `payment_method` enum('wave','orange_money','stripe','paypal','cash') DEFAULT 'cash',
  `payment_status` enum('pending','paid','failed') DEFAULT 'pending',
  `status` enum('pending','confirmed','shipping','delivered','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `customer_name`, `customer_email`, `customer_phone`, `delivery_address`, `city`, `total`, `delivery_fee`, `payment_method`, `payment_status`, `status`, `notes`, `created_at`) VALUES
(1, 4, 'Cheikh ndiaye', 'cn0560506@gmail.com', '781135717', 'THIAROYE', 'Dakar', 9500.00, 2500.00, 'cash', 'pending', 'confirmed', '12H', '2026-06-24 15:49:35'),
(2, 4, 'Cheikh ndiaye', 'cn0560506@gmail.com', '781135717', 'THIAROYE', 'Dakar', 9500.00, 2500.00, 'stripe', 'pending', 'confirmed', '16H', '2026-06-25 07:15:44'),
(3, 1, 'Admin WecanShop', 'admin@wecanshop.com', '', 'thiaroye', 'Dakar', 5000.00, 0.00, 'cash', 'pending', 'confirmed', '', '2026-07-01 16:19:34'),
(4, 4, 'Cheikh ndiaye', '', '778352402', 'Pkine', 'Dakar', 5000.00, 0.00, 'cash', 'pending', 'confirmed', 'tally bou mak', '2026-07-15 18:55:42'),
(5, 4, 'Cheikh ndiaye', '', '781135717', 'Thiaroye/mer', NULL, 5000.00, 0.00, 'cash', 'pending', 'confirmed', 'a 12H', '2026-07-17 12:35:12');

-- --------------------------------------------------------

--
-- Structure de la table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(200) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `quantity`, `price`) VALUES
(1, 1, 9, 'Café', 1, 7000.00),
(2, 2, 9, 'Café', 1, 7000.00),
(3, 3, 10, 'PATE DENTIFRICE LONGRICH', 1, 5000.00),
(4, 4, 10, 'PATE DENTIFRICE LONGRICH', 1, 5000.00),
(5, 5, 10, 'PATE DENTIFRICE LONGRICH', 1, 5000.00);

-- --------------------------------------------------------

--
-- Structure de la table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `slug` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `patient_problems` text DEFAULT NULL,
  `advantages` text DEFAULT NULL,
  `posologie` text DEFAULT NULL,
  `problems_image` varchar(255) DEFAULT NULL,
  `advantages_image` varchar(255) DEFAULT NULL,
  `posologie_image` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `delivery_fee` decimal(10,2) DEFAULT 0.00,
  `original_price` decimal(10,2) DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  `image` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `views` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `problems_image_width` tinyint(4) DEFAULT 100,
  `advantages_image_width` tinyint(4) DEFAULT 100,
  `posologie_image_width` tinyint(4) DEFAULT 100
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `products`
--

INSERT INTO `products` (`id`, `store_id`, `category_id`, `name`, `slug`, `description`, `patient_problems`, `advantages`, `posologie`, `problems_image`, `advantages_image`, `posologie_image`, `price`, `delivery_fee`, `original_price`, `stock`, `image`, `status`, `views`, `created_at`, `problems_image_width`, `advantages_image_width`, `posologie_image_width`) VALUES
(9, 3, 6, 'Café', 'cafe-027707', '', 'FATIGUE GENRAL\r\nINSOMMENIE\r\nMANQUE D\'APETIT\r\nMANQUE D\'ENERGIE', 'SYSTEME IMMUNITAIRE\r\nREDUIT LA FATIQUE', '1 SACHET 2 FOIS PAR JOUR', 'product_6a44148048299_1782846592.jpg', NULL, NULL, 7000.00, 1000.00, 8000.00, 8, 'product_6a3c078d23d1c_1782318989.jpg', 'active', 80, '2026-06-24 15:45:45', 100, 100, 100),
(10, 3, NULL, 'PATE DENTIFRICE LONGRICH', 'pate-dentifrice-longrich-4cc42d', '', 'Sensibilité : Douleur ou gêne en consommant des aliments ou des boissons chaudes, froides, sucrées ou acides.\r\nDouleur Aiguë : Douleur intense ou lancinante dans une ou plusieurs dents\r\nLors du Brossage :Gencives qui saignent facilement lors du brossage ou de l\'utilisation du fil dentaire.\r\nGencives Gonflées :Gencives rouges, enflées ou douloureuses.\r\nHalitose Persistante : Mauvaise haleine chronique, même après le brossage\r\nDécoloration : Taches ou changement de couleur des dents.\r\nTaches Blanches ou Marron : Surfaces de dents décolorées, souvent un signe de caries.\r\nDents Mobiles : Dents qui bougent ou semblent instables.\r\n\r\nPerte de Dents :Chute de dents due à une maladie des gencives ou à des blessures.\r\nAphtes : Petites lésions douloureuses à l\'intérieur de la bouche, sur les gencives ou sur la langue.\r\nLésions Persistantes : Plaies qui ne guérissent pas après deux semaines.\r\nDouleur à la Mâchoire : Douleur ou gêne dans la mâchoire, parfois accompagnée de craquements ou de difficultés à mâcher.\r\nClaquement :Bruit de craquement ou de grincement lors de l\'ouverture ou de la fermeture de la bouche.\r\nGonflement des Joues ou des Gencives : Indique une infection ou un abcès.', '🟢 🧼 Nettoyage en Profondeur : Les abrasifs antiseptiques et de silicone doux nettoient en profondeur, laissant une bouche propre, des dents plus blanches et une haleine plus fraîche.\r\n\r\n🟢 🦷 Renforcement des Gencives :Le chlorure de strontium et l\'extrait d\'aloès vera protègent et renforcent les gencives.\r\n\r\n🟢 🩺 Traitement des Problèmes Dentaires :Efficace contre les caries, les douleurs dentaires et les saignements.\r\n\r\n🟢 😌 Haleine Rafraîchie : Procure une haleine fraîche durable.', 'Utilisation\r\n\r\n🕖 Usage Quotidien : Utilisez la pâte dentifrice deux fois par jour pour un nettoyage optimal et une protection continue.\r\n\r\n✨ Gommage du Visage :Peut être utilisée efficacement pour le gommage du visage.', 'product_6a5a33c0bbaf3_1784296384.webp', NULL, NULL, 5000.00, 0.00, 6000.00, 7, 'product_6a44192241262_1782847778.jpg', 'active', 65, '2026-06-30 19:29:38', 100, 100, 100);

-- --------------------------------------------------------

--
-- Structure de la table `product_images`
--

CREATE TABLE `product_images` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `sort_order` tinyint(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `product_images`
--

INSERT INTO `product_images` (`id`, `product_id`, `file_name`, `sort_order`) VALUES
(1, 10, 'product_6a5a290a28db2_1784293642.webp', 0),
(2, 10, 'product_6a5a293139864_1784293681.jpg', 0);

-- --------------------------------------------------------

--
-- Structure de la table `product_testimonials`
--

CREATE TABLE `product_testimonials` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_type` enum('image','video') DEFAULT 'image',
  `caption` varchar(255) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `product_testimonials`
--

INSERT INTO `product_testimonials` (`id`, `product_id`, `file_name`, `file_type`, `caption`, `sort_order`, `created_at`) VALUES
(1, 9, 'testimonial_6a3cce5e241a6_1782369886.mp4', 'video', '', 0, '2026-06-25 06:44:48'),
(2, 9, 'testimonial_6a3cd056bb23f_1782370390.mp4', 'video', '', 1, '2026-06-25 06:53:13'),
(3, 10, 'testimonial_6a5a29ef5489d_1784293871.mp4', 'video', '', 0, '2026-07-17 13:11:12'),
(4, 10, 'testimonial_6a5a2a28957e8_1784293928.mp4', 'video', '', 1, '2026-07-17 13:12:11');

-- --------------------------------------------------------

--
-- Structure de la table `stores`
--

CREATE TABLE `stores` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `banner` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `subscription_status` enum('trial','active','expired') DEFAULT 'trial',
  `subscription_end_date` date DEFAULT NULL,
  `facebook_pixel_id` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `stores`
--

INSERT INTO `stores` (`id`, `user_id`, `name`, `slug`, `description`, `logo`, `banner`, `status`, `created_at`, `subscription_status`, `subscription_end_date`, `facebook_pixel_id`) VALUES
(3, 4, 'boutique bi', 'boutique-amine-4', 'Bienvenue dans ma boutique !', NULL, NULL, 'active', '2026-06-24 15:44:39', 'active', '2026-08-16', '1500653177246271'),
(4, 5, 'alimoushop', 'alimoushop-5', 'Bienvenue dans ma boutique !', NULL, NULL, 'active', '2026-07-05 20:46:04', 'trial', NULL, NULL),
(5, 6, 'NiangProgrmmeur', 'niangprogrmmeur-6', 'Bienvenue dans ma boutique !', NULL, NULL, 'active', '2026-07-13 10:46:58', 'trial', NULL, NULL),
(6, 7, 'youcan', 'youcan-7', 'Bienvenue dans ma boutique !', NULL, NULL, 'active', '2026-07-15 19:23:51', 'trial', NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `subscriptions`
--

CREATE TABLE `subscriptions` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `amount` decimal(10,2) DEFAULT 3000.00,
  `payment_method` enum('wave','orange_money','card') NOT NULL,
  `payment_phone` varchar(30) DEFAULT NULL,
  `payment_reference` varchar(150) DEFAULT NULL,
  `status` enum('pending','confirmed','rejected') DEFAULT 'pending',
  `period_start` date DEFAULT NULL,
  `period_end` date DEFAULT NULL,
  `admin_note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `confirmed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `subscriptions`
--

INSERT INTO `subscriptions` (`id`, `store_id`, `amount`, `payment_method`, `payment_phone`, `payment_reference`, `status`, `period_start`, `period_end`, `admin_note`, `created_at`, `confirmed_at`) VALUES
(1, 3, 3000.00, 'wave', 'card', 'CARD-3-1782413227', 'rejected', NULL, NULL, NULL, '2026-06-25 18:47:26', NULL),
(2, 3, 3000.00, 'wave', 'card', 'CARD-3-1782916670', 'rejected', NULL, NULL, NULL, '2026-07-01 14:37:58', NULL),
(3, 3, 3000.00, 'wave', 'card', 'CARD-3-1783954674', 'rejected', NULL, NULL, NULL, '2026-07-13 14:58:04', NULL),
(4, 3, 3000.00, 'wave', NULL, NULL, 'rejected', NULL, NULL, NULL, '2026-07-13 15:50:25', NULL),
(5, 3, 3000.00, 'wave', 'card', 'CARD-3-1784033377', 'rejected', NULL, NULL, NULL, '2026-07-14 12:49:42', NULL),
(6, 3, 3000.00, 'wave', NULL, NULL, 'confirmed', '2026-07-17', '2026-08-16', NULL, '2026-07-17 12:44:53', '2026-07-17 12:45:28');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('buyer','seller','admin') DEFAULT 'buyer',
  `phone` varchar(20) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `email_verified` tinyint(4) DEFAULT 0,
  `email_token` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `phone`, `avatar`, `created_at`, `email_verified`, `email_token`) VALUES
(1, 'Admin WecanShop', 'admin@wecanshop.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '+221 77 000 0000', NULL, '2026-06-24 15:40:54', 1, NULL),
(2, 'Aminata Diallo', 'vendeur@wecanshop.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'seller', '+221 77 123 4567', NULL, '2026-06-24 15:40:54', 1, NULL),
(3, 'Ibrahima Sow', 'acheteur@wecanshop.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'buyer', '+221 76 987 6543', NULL, '2026-06-24 15:40:54', 1, NULL),
(4, 'Cheikh ndiaye', 'cn0560506@gmail.com', '$2y$10$Tm9oCsCKMkklepRXlV8qU.M6kkU6GI/1v.GicGfyiXMNMh9.9yjsq', 'seller', '781135717', NULL, '2026-06-24 15:44:39', 1, NULL),
(5, 'cheikh tidiane', 'catidiane.ndiaye6@univ-thies.sn', '$2y$10$83yZ5Dp2Qze8EuYBZsRTTuFPIaqC0VN2RrZOL/gJUnk5MNjQofRi.', 'seller', '78135717', NULL, '2026-07-05 20:46:04', 1, NULL),
(6, 'Fatou Niang', 'fatou@niang.com', '$2y$10$/Y13tvWmXz8GXsTSGWFLY.R0wAEITQLypOg1OKhnjxOAAmk3QTHQO', 'seller', '786574757', NULL, '2026-07-13 10:46:58', 1, NULL),
(7, 'abdou', 'catidiane@gmail.com', '$2y$10$gwtbEogaprtdF6rB/QfwBeQTMKRufyQk2CPdFtQvzrkM6Eokz6LaK', 'seller', '781135717', NULL, '2026-07-15 19:23:51', 1, NULL);

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Index pour la table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Index pour la table `delivery_contacts`
--
ALTER TABLE `delivery_contacts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `store_id` (`store_id`);

--
-- Index pour la table `global_delivery_contacts`
--
ALTER TABLE `global_delivery_contacts`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Index pour la table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `store_id` (`store_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Index pour la table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_id` (`product_id`);

--
-- Index pour la table `product_testimonials`
--
ALTER TABLE `product_testimonials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Index pour la table `stores`
--
ALTER TABLE `stores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `store_id` (`store_id`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `delivery_contacts`
--
ALTER TABLE `delivery_contacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `global_delivery_contacts`
--
ALTER TABLE `global_delivery_contacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pour la table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `product_testimonials`
--
ALTER TABLE `product_testimonials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `stores`
--
ALTER TABLE `stores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `delivery_contacts`
--
ALTER TABLE `delivery_contacts`
  ADD CONSTRAINT `delivery_contacts_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Contraintes pour la table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `products_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

--
-- Contraintes pour la table `product_testimonials`
--
ALTER TABLE `product_testimonials`
  ADD CONSTRAINT `product_testimonials_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `stores`
--
ALTER TABLE `stores`
  ADD CONSTRAINT `stores_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD CONSTRAINT `subscriptions_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
