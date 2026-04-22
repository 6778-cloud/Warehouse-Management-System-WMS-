CREATE DATABASE IF NOT EXISTS wms_smart;
USE wms_smart;

-- =====================
-- USERS (Sample Data)
-- =====================
CREATE TABLE users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','staff','office') DEFAULT 'office',
  active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users (username, full_name, email, password_hash, role)
VALUES ('admin', 'Admin User', 'admin@example.com', '$2y$10$examplehash', 'admin');


-- =====================
-- PRODUCTS
-- =====================
CREATE TABLE products (
  product_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  sku VARCHAR(50) UNIQUE,
  min_stock INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO products (name, sku, min_stock)
VALUES ('Sample Product 1', 'SKU001', 10),
       ('Sample Product 2', 'SKU002', 5);


-- =====================
-- STOCK
-- =====================
CREATE TABLE stock (
  stock_id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT,
  qty INT DEFAULT 0,
  FOREIGN KEY (product_id) REFERENCES products(product_id)
);

INSERT INTO stock (product_id, qty)
VALUES (1, 50),
       (2, 20);


-- =====================
-- INBOUND ORDERS
-- =====================
CREATE TABLE inbound_orders (
  inbound_id INT AUTO_INCREMENT PRIMARY KEY,
  created_by INT,
  status ENUM('draft','completed') DEFAULT 'draft',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(user_id)
);


-- =====================
-- OUTBOUND ORDERS
-- =====================
CREATE TABLE outbound_orders (
  outbound_id INT AUTO_INCREMENT PRIMARY KEY,
  created_by INT,
  status ENUM('draft','shipped') DEFAULT 'draft',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(user_id)
);