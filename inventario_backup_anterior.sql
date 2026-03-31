-- SQL para MySQL que crea la base de datos y tablas necesarias, e inserta un usuario administrador

CREATE DATABASE IF NOT EXISTS inventario;
USE inventario;

-- Tabla de proveedores
CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    contact_name VARCHAR(100),
    phone_number VARCHAR(50),
    mobile_number VARCHAR(50) NULL,
    email VARCHAR(100),
    address VARCHAR(255),
    company VARCHAR(100),
    -- Datos fiscales
    rfc VARCHAR(30),
    legal_name VARCHAR(100),
    postal_code VARCHAR(20),
    -- Datos bancarios
    bank VARCHAR(50),
    interbank_key VARCHAR(50),
    branch VARCHAR(50),
    account_number VARCHAR(50),
    account_holder VARCHAR(100),
    -- Categorías (separadas por comas)
    categories TEXT,
    registration_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


-- Tabla de roles
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL
);

-- Tabla de usuarios
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    -- Ruta a la foto de perfil del usuario (puede ser NULL)
    photo_path VARCHAR(255) NULL,
    role_id INT NOT NULL,
    FOREIGN KEY (role_id) REFERENCES roles(id)
);


-- Tabla de productos
CREATE TABLE IF NOT EXISTS products (
    -- Identificador único
    id INT AUTO_INCREMENT PRIMARY KEY,
    -- Fecha de compra o entrada del producto al sistema
    last_purchase DATE DEFAULT NULL,
    -- Clave o código interno del producto
    code VARCHAR(100) UNIQUE,
    -- Nombre del producto
    name VARCHAR(100) NOT NULL,
    -- Descripción detallada
    description TEXT,
    -- Unidad de medida (p. ej. PZA, BLISTER, BOLSA, SET, JUEGO, CAJA, ROLLO, PAR, KG, M3, TNA, UM)
    unit VARCHAR(20),
    -- Precio de compra del artículo
    purchase_price DECIMAL(10,2) DEFAULT 0,
    -- Precio mínimo de venta
    min_price DECIMAL(10,2) DEFAULT 0,
    -- Margen de ganancia (por ejemplo 0.18 para 18 %)
    profit_margin DECIMAL(6,2) DEFAULT NULL,
    -- Precio de venta al público
    public_price DECIMAL(10,2) DEFAULT 0,
    -- Precio de la competencia (puede estar vacío)
    competitor_price DECIMAL(10,2) DEFAULT 0,
    -- Código de barras del producto
    barcode VARCHAR(50),
    -- Ruta a la imagen del producto (subida o capturada)
    image_path VARCHAR(255),
    -- Existencias actuales
    stock INT DEFAULT 0,
    -- Stock mínimo
    min_stock INT DEFAULT 0,
    -- Stock máximo
    max_stock INT DEFAULT 0,
    -- Clasificación del producto (Ferretería, Aceros, Materiales para construcción, Renta de maquinaria)
    classification ENUM('FERRETERIA','ACEROS','MATERIALES PARA CONSTRUCCION','RENTA DE MAQUINARIA','COMBUSTIBLE') NOT NULL DEFAULT 'FERRETERIA',
    -- Identificador del proveedor (relación con suppliers.id)
    supplier_id INT NULL,
    -- (campo ya definido arriba) margenes de ganancia se almacenan en profit_margin
    -- Datos adicionales: presentación, marca y contenido, por si fueran necesarios en otras vistas
    presentation VARCHAR(100),
    brand VARCHAR(50),
    content VARCHAR(100),
    -- Timestamp de creación y actualización
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Claves foráneas e índices para las nuevas columnas
    CONSTRAINT fk_products_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_products_classification (classification),
    INDEX idx_products_supplier (supplier_id)
);


-- Tabla de cajas (cajas registradoras y cuentas bancarias)
CREATE TABLE IF NOT EXISTS cash_registers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    kind ENUM('cash', 'bank') NOT NULL DEFAULT 'cash',
    type VARCHAR(50) NULL,
    category VARCHAR(50) NULL,
    initial_balance DECIMAL(12,2) DEFAULT 0,
    saldo_inicial DECIMAL(12,2) DEFAULT 0,
    current_balance DECIMAL(12,2) DEFAULT 0,
    balance DECIMAL(12,2) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_kind (kind),
    INDEX idx_active (is_active)
);

-- Tabla de movimientos de caja (entradas y salidas de efectivo)
CREATE TABLE IF NOT EXISTS cash_flows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cash_register_id INT NOT NULL,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    type ENUM('in','out') NOT NULL,
    description VARCHAR(255),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cash_register_id) REFERENCES cash_registers(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Historial de precios
CREATE TABLE IF NOT EXISTS price_histories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    -- Precio de compra registrado en este momento del histórico (puede ser NULL si solo cambia el precio de venta)
    purchase_price DECIMAL(10,2) NULL,
    -- Precio público o de venta registrado en este momento del histórico (puede ser NULL si solo cambia el precio de compra)
    public_price DECIMAL(10,2) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Ventas y detalles
CREATE TABLE IF NOT EXISTS sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    -- Datos del cliente: nombre, dirección y teléfono
    client_name VARCHAR(100),
    client_address VARCHAR(255),
    client_phone VARCHAR(50),
    -- Método de pago (Efectivo, Transferencia, Cuenta)
    payment_method VARCHAR(50),
    -- Referencia bancaria o número de operación cuando el pago es por transferencia
    bank_reference VARCHAR(100),
    -- Caja utilizada para el movimiento (puede ser NULL para pagos a cuenta)
    cash_register_id INT DEFAULT NULL,
    -- Importe pendiente por pagar (para ventas a crédito)
    pending_amount DECIMAL(10,2) DEFAULT 0,
    -- Importe total de la venta
    total_amount DECIMAL(10,2) DEFAULT 0,
    -- Código de barras único del ticket para búsquedas rápidas
    ticket_barcode VARCHAR(50) UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (cash_register_id) REFERENCES cash_registers(id)
);

CREATE TABLE IF NOT EXISTS sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT,
    product_id INT,
    quantity DECIMAL(10,2) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Inserta un usuario administrador por defecto con contraseña 'admin123'
-- Inserta roles predeterminados
INSERT INTO roles (id, name) VALUES
  (1, 'admin'),
  (2, 'sales')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Inserta un usuario administrador por defecto con contraseña 'admin123'
-- Inserta un usuario administrador por defecto. Por petición se utiliza el nombre de
-- la persona administradora "Francisco Javier Pérez Quiroz" como usuario simplificado
-- "francisco" y contraseña "090602". La contraseña se almacena en texto plano en
-- el campo password_hash para permitir el inicio de sesión; el código de inicio
-- de sesión admite tanto contraseñas planas como hasheadas.
INSERT INTO users (username, password_hash, role_id)
VALUES ('francisco', '090602', 1)
ON DUPLICATE KEY UPDATE username=username;

-- Inserta un usuario con nombre de usuario 'admin' y contraseña 'admin123'
-- Este registro se añade para simplificar el acceso inicial al sistema.
-- La contraseña también se almacena en texto plano en password_hash, permitiendo
-- que la función de inicio de sesión la valide directamente sin hashing. Si
-- el usuario ya existe, no se modificará (ON DUPLICATE KEY).
INSERT INTO users (username, password_hash, role_id)
VALUES ('admin', 'admin123', 1)
ON DUPLICATE KEY UPDATE username=username;

-- Inserta cajas por defecto
INSERT INTO cash_registers (id, name)
VALUES (1, 'Caja 1'), (2, 'Caja 2')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- ===== Nuevas tablas y vistas para compras, historial de precios, contabilidad y combustible =====

-- Tabla de compras
CREATE TABLE IF NOT EXISTS purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    date DATE NOT NULL,
    consider_vat TINYINT(1) DEFAULT 0,
    payment_method ENUM('EFECTIVO','TRANSFERENCIA','PENDIENTE') NOT NULL DEFAULT 'PENDIENTE',
    receipt_path VARCHAR(255) NULL,
    created_by_user_id INT,
    received_by_user_id INT,
    notes TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON UPDATE CASCADE,
    FOREIGN KEY (created_by_user_id) REFERENCES users(id),
    FOREIGN KEY (received_by_user_id) REFERENCES users(id)
);

-- Tabla de items de compra
CREATE TABLE IF NOT EXISTS purchase_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_id INT NOT NULL,
    product_id INT NOT NULL,
    presentation ENUM('PIEZA','CAJA','ROLLO','OTRO') DEFAULT 'PIEZA',
    quantity DECIMAL(10,2) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    -- Snapshot del precio de venta y margen al momento de la entrada
    public_price DECIMAL(10,2) NULL,
    profit_margin DECIMAL(10,4) NULL,
    total DECIMAL(12,2) AS (quantity * unit_price) STORED,
    FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE CASCADE
);

-- Vista consolidada de compras
CREATE OR REPLACE VIEW v_purchases_summary AS
SELECT p.date, SUM(pi.total) AS total, p.payment_method
FROM purchases p
JOIN purchase_items pi ON pi.purchase_id = p.id
GROUP BY p.date, p.payment_method;

-- Ampliar la tabla de historial de precios para incluir precios de compra y venta
ALTER TABLE price_histories
    ADD COLUMN IF NOT EXISTS purchase_price DECIMAL(10,2) NULL,
    ADD COLUMN IF NOT EXISTS public_price DECIMAL(10,2) NULL;

-- Tabla de movimientos de cuenta para contabilidad
CREATE TABLE IF NOT EXISTS account_moves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    type ENUM('ENTRADA','SALIDA','VENTA','AJUSTE') NOT NULL,
    origin ENUM('CAJA','BANCO','OTRO') NOT NULL DEFAULT 'CAJA',
    cash_register_id INT NULL,
    reference VARCHAR(80),
    amount DECIMAL(12,2) NOT NULL,
    user_id INT NULL,
    link_type ENUM('COMPRA','VENTA','COMBUSTIBLE','OTRO') NULL,
    link_id INT NULL,
    FOREIGN KEY (cash_register_id) REFERENCES cash_registers(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Tabla de tipos de combustible (mejorada)
CREATE TABLE IF NOT EXISTS fuel_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    type_name VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name)
);

-- Tabla de vehículos
CREATE TABLE IF NOT EXISTS vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plate VARCHAR(20) NOT NULL UNIQUE,
    model VARCHAR(100) NOT NULL,
    fuel_type_id INT,
    tank_capacity DECIMAL(10,2) NOT NULL,
    status ENUM('ACTIVO', 'MANTENIMIENTO', 'INACTIVO') DEFAULT 'ACTIVO',
    last_refuel_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (fuel_type_id) REFERENCES fuel_types(id),
    INDEX idx_plate (plate),
    INDEX idx_status (status)
);

-- Tabla consolidada de movimientos de combustible
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

-- Inserta tipos de combustible predeterminados si no existen
INSERT IGNORE INTO fuel_types (name, type_name) VALUES 
    ('Gasolina', 'Gasolina'),
    ('Diesel', 'Diesel'),
    ('LP', 'Gas LP'),
    ('Otro', 'Otro');