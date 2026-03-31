-- ============================================================
-- SISTEMCSM v2.0 - BASE DE DATOS DEFINITIVA Y PROBADA
-- MySQL 5.7+ Compatible
-- ============================================================
-- Última actualización: 2024
-- Estado: Probado y Listo para Producción

CREATE DATABASE IF NOT EXISTS inventario;
USE inventario;

-- ============================================================
-- TABLA: ROLES
-- ============================================================
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- TABLA: USUARIOS
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    photo_path VARCHAR(255) NULL,
    role_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id),
    INDEX idx_username (username),
    INDEX idx_role_id (role_id)
);

-- ============================================================
-- TABLA: PROVEEDORES
-- ============================================================
CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    contact_name VARCHAR(100),
    phone_number VARCHAR(50),
    mobile_number VARCHAR(50),
    email VARCHAR(100),
    address VARCHAR(255),
    company VARCHAR(100),
    rfc VARCHAR(30),
    legal_name VARCHAR(100),
    postal_code VARCHAR(20),
    bank VARCHAR(50),
    interbank_key VARCHAR(50),
    branch VARCHAR(50),
    account_number VARCHAR(50),
    account_holder VARCHAR(100),
    categories TEXT,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name)
);

-- ============================================================
-- TABLA: PRODUCTOS
-- ============================================================
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(100) UNIQUE,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    unit VARCHAR(20),
    purchase_price DECIMAL(10,2) DEFAULT 0,
    min_price DECIMAL(10,2) DEFAULT 0,
    profit_margin DECIMAL(6,2),
    public_price DECIMAL(10,2) DEFAULT 0,
    competitor_price DECIMAL(10,2) DEFAULT 0,
    barcode VARCHAR(50),
    image_path VARCHAR(255),
    stock INT DEFAULT 0,
    min_stock INT DEFAULT 0,
    max_stock INT DEFAULT 0,
    classification ENUM('FERRETERIA','ACEROS','MATERIALES_CONSTRUCCION','RENTA_MAQUINARIA','COMBUSTIBLE','OTRO') DEFAULT 'FERRETERIA',
    supplier_id INT,
    presentation VARCHAR(100),
    brand VARCHAR(50),
    content VARCHAR(100),
    last_purchase DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_code (code),
    INDEX idx_name (name),
    INDEX idx_barcode (barcode),
    INDEX idx_classification (classification),
    INDEX idx_supplier_id (supplier_id)
);

-- ============================================================
-- TABLA: CUENTAS Y CAJAS (MEJORADA v2.0)
-- ============================================================
CREATE TABLE IF NOT EXISTS cash_registers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    kind ENUM('CASH', 'BANK') NOT NULL DEFAULT 'CASH',
    type VARCHAR(50),
    category VARCHAR(50),
    description TEXT,
    initial_balance DECIMAL(15,2) DEFAULT 0,
    current_balance DECIMAL(15,2) DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_kind (kind),
    INDEX idx_is_active (is_active),
    INDEX idx_name (name)
);

-- ============================================================
-- TABLA: MOVIMIENTOS DE CAJA
-- ============================================================
CREATE TABLE IF NOT EXISTS cash_flows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cash_register_id INT NOT NULL,
    user_id INT,
    amount DECIMAL(10,2) NOT NULL,
    type ENUM('IN', 'OUT') NOT NULL,
    description VARCHAR(255),
    reference VARCHAR(80),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cash_register_id) REFERENCES cash_registers(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_cash_register_id (cash_register_id),
    INDEX idx_created_at (created_at)
);

-- ============================================================
-- TABLA: HISTORIAL DE PRECIOS
-- ============================================================
CREATE TABLE IF NOT EXISTS price_histories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    purchase_price DECIMAL(10,2),
    public_price DECIMAL(10,2),
    profit_margin DECIMAL(6,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product_id (product_id),
    INDEX idx_created_at (created_at)
);

-- ============================================================
-- TABLA: VENTAS
-- ============================================================
CREATE TABLE IF NOT EXISTS sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    client_name VARCHAR(100),
    client_address VARCHAR(255),
    client_phone VARCHAR(50),
    payment_method ENUM('CASH', 'CARD', 'TRANSFER', 'CREDIT') DEFAULT 'CASH',
    bank_reference VARCHAR(100),
    cash_register_id INT,
    pending_amount DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) DEFAULT 0,
    ticket_barcode VARCHAR(50) UNIQUE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (cash_register_id) REFERENCES cash_registers(id),
    INDEX idx_created_at (created_at),
    INDEX idx_ticket_barcode (ticket_barcode)
);

-- ============================================================
-- TABLA: ITEMS DE VENTA
-- ============================================================
CREATE TABLE IF NOT EXISTS sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    INDEX idx_sale_id (sale_id),
    INDEX idx_product_id (product_id)
);

-- ============================================================
-- TABLA: COMPRAS
-- ============================================================
CREATE TABLE IF NOT EXISTS purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    date DATE NOT NULL,
    consider_vat TINYINT(1) DEFAULT 0,
    payment_method ENUM('CASH', 'TRANSFER', 'PENDING') DEFAULT 'PENDING',
    receipt_path VARCHAR(255),
    created_by_user_id INT,
    received_by_user_id INT,
    notes TEXT,
    folio VARCHAR(100),
    cash_register_id INT,
    bank_operation_number VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON UPDATE CASCADE,
    FOREIGN KEY (created_by_user_id) REFERENCES users(id),
    FOREIGN KEY (received_by_user_id) REFERENCES users(id),
    FOREIGN KEY (cash_register_id) REFERENCES cash_registers(id),
    INDEX idx_date (date),
    INDEX idx_supplier_id (supplier_id)
);

-- ============================================================
-- TABLA: ITEMS DE COMPRA
-- ============================================================
CREATE TABLE IF NOT EXISTS purchase_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_id INT NOT NULL,
    product_id INT NOT NULL,
    presentation ENUM('PIECE', 'BOX', 'ROLL', 'OTHER') DEFAULT 'PIECE',
    quantity DECIMAL(10,2) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    public_price DECIMAL(10,2),
    profit_margin DECIMAL(10,4),
    total DECIMAL(12,2) GENERATED ALWAYS AS (quantity * unit_price) STORED,
    FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    INDEX idx_purchase_id (purchase_id),
    INDEX idx_product_id (product_id)
);

-- ============================================================
-- TABLA: MOVIMIENTOS CONTABLES
-- ============================================================
CREATE TABLE IF NOT EXISTS account_moves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATETIME DEFAULT CURRENT_TIMESTAMP,
    type ENUM('ENTRADA', 'SALIDA', 'VENTA', 'AJUSTE') NOT NULL,
    origin ENUM('CAJA', 'BANCO', 'OTRO') DEFAULT 'CAJA',
    cash_register_id INT,
    reference VARCHAR(80),
    amount DECIMAL(12,2) NOT NULL,
    user_id INT,
    link_type ENUM('COMPRA', 'VENTA', 'COMBUSTIBLE', 'OTRO'),
    link_id INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cash_register_id) REFERENCES cash_registers(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_date (date),
    INDEX idx_type (type),
    INDEX idx_cash_register_id (cash_register_id)
);

-- ============================================================
-- TABLA: TIPOS DE COMBUSTIBLE
-- ============================================================
CREATE TABLE IF NOT EXISTS fuel_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    type_name VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name)
);

-- ============================================================
-- TABLA: VEHÍCULOS
-- ============================================================
CREATE TABLE IF NOT EXISTS vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plate VARCHAR(20) NOT NULL UNIQUE,
    model VARCHAR(100) NOT NULL,
    fuel_type_id INT,
    tank_capacity DECIMAL(10,2) NOT NULL,
    status ENUM('ACTIVO', 'MANTENIMIENTO', 'INACTIVO') DEFAULT 'ACTIVO',
    last_refuel_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (fuel_type_id) REFERENCES fuel_types(id),
    INDEX idx_plate (plate),
    INDEX idx_status (status)
);

-- ============================================================
-- TABLA: MOVIMIENTOS DE COMBUSTIBLE (CONSOLIDADA)
-- ============================================================
CREATE TABLE IF NOT EXISTS fuel_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    movement_type ENUM('ENTRADA', 'SALIDA') NOT NULL,
    fuel_type_id INT NOT NULL,
    location VARCHAR(50),
    liters DECIMAL(12,2) NOT NULL,
    unit_price DECIMAL(10,2),
    supplier VARCHAR(120),
    destination VARCHAR(50),
    vehicle_id INT,
    counterparty VARCHAR(120),
    account_target VARCHAR(50),
    notes TEXT,
    date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (fuel_type_id) REFERENCES fuel_types(id),
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id),
    INDEX idx_date (date),
    INDEX idx_type (movement_type),
    INDEX idx_location (location)
);

-- ============================================================
-- TABLA: PRESUPUESTOS/COTIZACIONES
-- ============================================================
CREATE TABLE IF NOT EXISTS quotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_number VARCHAR(50) UNIQUE,
    client_name VARCHAR(100),
    client_email VARCHAR(100),
    client_phone VARCHAR(50),
    description TEXT,
    total_amount DECIMAL(12,2) DEFAULT 0,
    status ENUM('DRAFT', 'SENT', 'ACCEPTED', 'REJECTED', 'EXPIRED') DEFAULT 'DRAFT',
    user_id INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_quote_number (quote_number),
    INDEX idx_status (status)
);

-- ============================================================
-- VISTAS
-- ============================================================

-- Vista consolidada de compras por fecha
CREATE OR REPLACE VIEW v_purchases_summary AS
SELECT 
    p.date,
    s.name as supplier_name,
    SUM(pi.total) as total,
    p.payment_method,
    COUNT(pi.id) as items_count
FROM purchases p
JOIN suppliers s ON s.id = p.supplier_id
JOIN purchase_items pi ON pi.purchase_id = p.id
GROUP BY p.date, p.payment_method, s.name, p.id
ORDER BY p.date DESC;

-- Vista consolidada de ventas por fecha
CREATE OR REPLACE VIEW v_sales_summary AS
SELECT 
    s.created_at,
    u.username,
    COUNT(si.id) as items_count,
    s.total_amount,
    s.payment_method
FROM sales s
LEFT JOIN users u ON u.id = s.user_id
LEFT JOIN sale_items si ON si.sale_id = s.id
GROUP BY s.id, s.created_at, u.username, s.total_amount, s.payment_method
ORDER BY s.created_at DESC;

-- ============================================================
-- DATOS INICIALES
-- ============================================================

-- Insertar roles
INSERT IGNORE INTO roles (id, name) VALUES
    (1, 'admin'),
    (2, 'user'),
    (3, 'sales');

-- Insertar usuarios por defecto
INSERT IGNORE INTO users (username, password_hash, role_id) VALUES
    ('admin', 'admin', 1),
    ('user', 'user', 2),
    ('sales', 'sales', 3);

-- Insertar cajas por defecto
INSERT IGNORE INTO cash_registers (name, kind, type) VALUES
    ('Caja Principal', 'CASH', 'Efectivo'),
    ('Caja Secundaria', 'CASH', 'Efectivo'),
    ('Banco Principal', 'BANK', 'Banco');

-- Insertar tipos de combustible
INSERT IGNORE INTO fuel_types (name, type_name) VALUES
    ('Gasolina', 'Gasolina'),
    ('Diesel', 'Diesel'),
    ('LP', 'Gas LP'),
    ('Otro', 'Otro');

-- ============================================================
-- ÍNDICES ADICIONALES PARA OPTIMIZACIÓN
-- ============================================================

ALTER TABLE sales ADD INDEX idx_user_id (user_id);
ALTER TABLE sales ADD INDEX idx_payment_method (payment_method);
ALTER TABLE sale_items ADD INDEX idx_sale_price (price);
ALTER TABLE purchase_items ADD INDEX idx_unit_price (unit_price);
ALTER TABLE cash_flows ADD INDEX idx_type (type);

-- ============================================================
-- FIN DE LA INSTALACIÓN
-- ============================================================
-- Base de datos lista para usar
-- Usuarios predeterminados creados
-- Tipos de combustible inicializados
-- Cajas por defecto creadas
-- Todas las tablas con índices optimizados
