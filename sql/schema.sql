-- StreetSource Database Schema
-- Database: streetsource

CREATE DATABASE IF NOT EXISTS streetsource;
USE streetsource;

-- Users table (vendors & suppliers)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    business_name VARCHAR(100) NULL,
    shop_logo VARCHAR(255) NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('vendor', 'supplier') NOT NULL,
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    city VARCHAR(100) NULL,
    phone VARCHAR(15) NULL,
    address TEXT NULL,
    is_verified BOOLEAN DEFAULT FALSE,
    email_verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- OTP verification table
CREATE TABLE email_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    is_used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_otp_code (otp_code),
    INDEX idx_expires (expires_at)
);

-- Products table (listed by suppliers)
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    unit VARCHAR(20) NOT NULL DEFAULT 'kg',
    description TEXT NULL,
    image_url VARCHAR(255) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Orders table (placed by vendors)
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    supplier_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'accepted', 'delivered', 'cancelled') DEFAULT 'pending',
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    delivered_date TIMESTAMP NULL,
    notes TEXT NULL,
    FOREIGN KEY (vendor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Reviews table (vendor rates supplier)
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    vendor_id INT NOT NULL,
    supplier_id INT NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    note TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (vendor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_order_review (order_id)
);

-- Sample data for testing
INSERT INTO users (name, email, password, role, latitude, longitude, city, phone, address, is_verified, email_verified_at) VALUES
-- Suppliers
('Fresh Vegetables Co.', 'fresh@vegetables.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'supplier', 28.6139, 77.2090, 'New Delhi', '9876543210', 'Connaught Place, New Delhi', TRUE, NOW()),
('Spice Master', 'spice@master.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'supplier', 28.6129, 77.2295, 'New Delhi', '9876543211', 'Chandni Chowk, New Delhi', TRUE, NOW()),
('Quality Grains', 'quality@grains.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'supplier', 28.6304, 77.2177, 'New Delhi', '9876543212', 'Karol Bagh, New Delhi', TRUE, NOW()),

-- Vendors
('Raju Chaat Corner', 'raju@chaat.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'vendor', 28.6169, 77.2090, 'New Delhi', '9876543213', 'India Gate, New Delhi', TRUE, NOW()),
('Mumbai Pav Bhaji', 'mumbai@pavbhaji.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'vendor', 28.6289, 77.2065, 'New Delhi', '9876543214', 'CP Metro Station, New Delhi', TRUE, NOW());

-- Sample products
INSERT INTO products (supplier_id, name, price, stock, unit, description) VALUES
-- Fresh Vegetables Co. products
(1, 'Onions', 30.00, 100, 'kg', 'Fresh red onions from Nashik'),
(1, 'Tomatoes', 40.00, 80, 'kg', 'Ripe red tomatoes'),
(1, 'Potatoes', 25.00, 120, 'kg', 'Fresh potatoes from Punjab'),
(1, 'Green Chilies', 80.00, 20, 'kg', 'Hot green chilies'),

-- Spice Master products
(2, 'Red Chili Powder', 150.00, 50, 'kg', 'Premium quality red chili powder'),
(2, 'Turmeric Powder', 120.00, 40, 'kg', 'Pure turmeric powder'),
(2, 'Cumin Seeds', 200.00, 30, 'kg', 'Whole cumin seeds'),
(2, 'Coriander Powder', 100.00, 35, 'kg', 'Fresh ground coriander'),

-- Quality Grains products
(3, 'Wheat Flour', 35.00, 200, 'kg', 'Fine wheat flour for bread'),
(3, 'Rice', 45.00, 150, 'kg', 'Basmati rice'),
(3, 'Lentils (Dal)', 90.00, 80, 'kg', 'Mixed lentils'),
(3, 'Chickpea Flour', 60.00, 60, 'kg', 'Fresh chickpea flour');

-- Sample orders
INSERT INTO orders (vendor_id, supplier_id, product_id, quantity, total_price, status) VALUES
(4, 1, 1, 10, 300.00, 'delivered'),
(4, 2, 5, 2, 300.00, 'delivered'),
(5, 1, 2, 5, 200.00, 'pending');

-- Sample reviews
INSERT INTO reviews (order_id, vendor_id, supplier_id, rating, note) VALUES
(1, 4, 1, 5, 'Excellent quality onions, very fresh!'),
(2, 4, 2, 4, 'Good spices, delivered on time');
