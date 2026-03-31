-- Esquema de la base de datos para la aplicación PHP de gestión de inventario

-- Usuarios
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL
);

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    -- Ruta a la foto de perfil (opcional)
    photo_path VARCHAR(255) NULL,
    role_id INT NOT NULL,
    FOREIGN KEY (role_id) REFERENCES roles(id)
);

-- Productos
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
    -- Margen de ganancia (por ejemplo 0.18 para 18 %). Almacena porcentaje como valor decimal (por ejemplo, 0.18 para 18%).
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
    stock INTEGER DEFAULT 0,
    -- Stock mínimo
    min_stock INTEGER DEFAULT 0,
    -- Stock máximo
    max_stock INTEGER DEFAULT 0,
    -- Clasificación del producto (Ferretería, Aceros, Materiales para construcción, Renta de maquinaria)
    classification ENUM('FERRETERIA','ACEROS','MATERIALES PARA CONSTRUCCION','RENTA DE MAQUINARIA','COMBUSTIBLE') NOT NULL DEFAULT 'FERRETERIA',
    -- Identificador del proveedor (relación con suppliers.id)
    supplier_id INT NULL,
    -- (campo ya definido arriba) margen de ganancia ya está incluido en profit_margin
    -- Datos adicionales: presentación, marca y contenido, por si fueran necesarios en otras vistas
    presentation VARCHAR(100),
    brand VARCHAR(50),
    content VARCHAR(100),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Clave foránea y índices de nuevas columnas
    CONSTRAINT fk_products_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_products_classification (classification),
    INDEX idx_products_supplier (supplier_id)
);

-- Proveedores
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
    -- Datos bancarios: nombre del banco
    bank VARCHAR(50),
    -- Clave interbancaria (para bancos distintos a Banamex)
    interbank_key VARCHAR(50),
    -- Sucursal de Banamex (solo se utiliza si bank = 'BANAMEX')
    branch VARCHAR(50),
    -- Número de cuenta de Banamex (solo si bank = 'BANAMEX')
    account_number VARCHAR(50),
    -- Titular de la cuenta bancaria
    account_holder VARCHAR(100),
    -- Categorías que provee el proveedor (lista separada por comas)
    categories TEXT,
    registration_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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

-- Ventas (tickets)
CREATE TABLE IF NOT EXISTS sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    -- Datos del cliente: nombre, dirección y teléfono
    client_name VARCHAR(100),
    client_address VARCHAR(255),
    client_phone VARCHAR(50),
    -- Método de pago (Efectivo, Transferencia, Cuenta)
    payment_method VARCHAR(50),
    -- Referencia bancaria u operación para transferencias
    bank_reference VARCHAR(100),
    -- Caja asociada al movimiento (nullable en caso de venta a cuenta)
    cash_register_id INT DEFAULT NULL,
    -- Monto pendiente (para ventas a crédito)
    pending_amount DECIMAL(10,2) DEFAULT 0,
    -- Total de la venta
    total_amount DECIMAL(10,2) DEFAULT 0,
    -- Código de barras único del ticket
    ticket_barcode VARCHAR(50) UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (cash_register_id) REFERENCES cash_registers(id)
);

-- Detalles de venta
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

-- Tabla de cajas (cajas registradoras) para gestionar entradas y salidas de efectivo y bancos
CREATE TABLE IF NOT EXISTS cash_registers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  type ENUM('CASH','BANK') NOT NULL DEFAULT 'CASH',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  saldo_inicial DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY ux_cash_registers_name (name)
);

-- Insertar cajas iniciales si no existen
INSERT IGNORE INTO cash_registers (name,type,is_active) VALUES
 ('Oficina','CASH',1),
 ('Ferreteria','CASH',1),
 ('Banamex','BANK',1),
 ('Banorte','BANK',1);

-- Tabla de movimientos de caja (entradas/salidas)
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

-- Extender la tabla de historial de precios para incluir precios de compra y venta
ALTER TABLE price_histories
    ADD COLUMN IF NOT EXISTS purchase_price DECIMAL(10,2) NULL,
    ADD COLUMN IF NOT EXISTS public_price DECIMAL(10,2) NULL;

-- Tabla de movimientos de cuenta
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
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVO',
    note TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (date),
    INDEX (cash_register_id),
    FOREIGN KEY (cash_register_id) REFERENCES cash_registers(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Tabla de tipos de combustible
CREATE TABLE IF NOT EXISTS fuel_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name ENUM('LP','GASOLINA_DIESEL','GASOLINA_PREMIUM','GASOLINA_MAGNA') UNIQUE
);

-- Tabla de vehículos
CREATE TABLE IF NOT EXISTS vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    type VARCHAR(80),
    capacity_liters DECIMAL(8,2) DEFAULT 0
);

-- Tabla de existencias de combustible
CREATE TABLE IF NOT EXISTS fuel_stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type_id INT NOT NULL,
    location ENUM('DEPOSITO','BODEGA_G','BODEGA_C') NOT NULL,
    liters_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    UNIQUE KEY unique_type_location (type_id, location),
    FOREIGN KEY (type_id) REFERENCES fuel_types(id)
);

-- Tabla de entradas de combustible
CREATE TABLE IF NOT EXISTS fuel_in (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type_id INT NOT NULL,
    jerrycans INT NOT NULL,
    liters_per_jerrycan DECIMAL(8,2) NOT NULL,
    liters_total DECIMAL(12,2) NOT NULL,
    purchase_price DECIMAL(10,2) NOT NULL,
    date DATE NOT NULL,
    supplier VARCHAR(120),
    location ENUM('DEPOSITO','BODEGA_G','BODEGA_C') NOT NULL,
    FOREIGN KEY (type_id) REFERENCES fuel_types(id)
);

-- Tabla de salidas de combustible
CREATE TABLE IF NOT EXISTS fuel_out (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type_id INT NOT NULL,
    date DATE NOT NULL,
    destination ENUM('VENTA','CAMBIO','USO') NOT NULL,
    sale_price DECIMAL(10,2) NULL,
    liters DECIMAL(12,2) NOT NULL,
    vehicle_id INT NULL,
    counterparty VARCHAR(120) NULL,
    account_target ENUM('CAJA','BANCO','OTRO') NULL,
    note TEXT,
    FOREIGN KEY (type_id) REFERENCES fuel_types(id),
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id)
);

-- Semillas de tipos de combustible
INSERT IGNORE INTO fuel_types (name) VALUES ('LP'), ('GASOLINA_DIESEL'), ('GASOLINA_PREMIUM'), ('GASOLINA_MAGNA');