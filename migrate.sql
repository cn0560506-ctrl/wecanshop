-- Migration : nouveaux champs produit + table témoignages
USE wecanshop;

ALTER TABLE products
    ADD COLUMN IF NOT EXISTS patient_problems TEXT AFTER description,
    ADD COLUMN IF NOT EXISTS advantages TEXT AFTER patient_problems,
    ADD COLUMN IF NOT EXISTS posologie TEXT AFTER advantages;

CREATE TABLE IF NOT EXISTS product_testimonials (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_type ENUM('image','video') DEFAULT 'image',
    caption VARCHAR(255),
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);
