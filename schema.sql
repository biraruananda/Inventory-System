-- ==========================================
-- Inventory System Database Schema
-- Author: Bilal Ananda
-- Year: 2025
-- ==========================================

-- Create database (optional, only if not created)
-- CREATE DATABASE inventory_db;
-- USE inventory_db;

-- =======================
-- Users Table
-- =======================
CREATE TABLE IF NOT EXISTS users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default admin (password: admin123)
INSERT INTO users (username, email, password, role) VALUES
('admin', 'admin@example.com', 
  '$2y$10$0y4fI6Se59m4HVZxvWQp8uWu6gV9hrUmYOBU1/6.NADk6RtgjD6a2', 
  'admin')
ON DUPLICATE KEY UPDATE username=username;

-- =======================
-- Categories Table
-- =======================
CREATE TABLE IF NOT EXISTS categories (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sample categories
INSERT INTO categories (name, description) VALUES
('Electronics', 'Electronic devices and accessories'),
('Clothing', 'Apparel and fashion items'),
('Books', 'Books and publications'),
('Home & Garden', 'Home and garden supplies'),
('Sports', 'Sports equipment and accessories')
ON DUPLICATE KEY UPDATE name=name;

-- =======================
-- Products Table
-- =======================
CREATE TABLE IF NOT EXISTS products (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    quantity INT(11) NOT NULL,
    category_id INT(11),
    image_url VARCHAR(500),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Sample products
INSERT INTO products (name, description, price, quantity, category_id, image_url, status) VALUES
('Smartphone', 'Latest smartphone with high-resolution camera', 599.99, 25, 1, 'https://via.placeholder.com/150', 'active'),
('Laptop', 'Powerful laptop with fast processor', 1299.99, 15, 1, 'https://via.placeholder.com/150', 'active'),
('T-Shirt', 'Comfortable cotton t-shirt', 24.99, 100, 2, 'https://via.placeholder.com/150', 'active'),
('Novel', 'Bestselling fiction novel', 14.99, 50, 3, 'https://via.placeholder.com/150', 'active'),
('Basketball', 'Professional quality basketball', 29.99, 45, 5, 'https://via.placeholder.com/150', 'active')
ON DUPLICATE KEY UPDATE name=name;

-- =======================
-- Settings Table
-- =======================
CREATE TABLE IF NOT EXISTS settings (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default settings
INSERT INTO settings (setting_key, setting_value) VALUES
('company_name', 'Inventory System'),
('admin_email', 'admin@example.com'),
('items_per_page', '20'),
('low_stock_threshold', '10'),
('theme', 'light'),
('currency', 'USD'),
('enable_notifications', '1')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);
