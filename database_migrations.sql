-- MIGRACIONES PARA CORREGIR CAJAS Y PAGOS
-- Ejecuta este archivo en MySQL para actualizar la base de datos existente

USE inventario;

-- 1. Actualizar tabla cash_registers con campos faltantes
ALTER TABLE cash_registers 
ADD COLUMN IF NOT EXISTS type ENUM('CASH','BANK') NOT NULL DEFAULT 'CASH' AFTER name,
ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER type,
ADD COLUMN IF NOT EXISTS saldo_inicial DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER is_active,
ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER saldo_inicial,
ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Crear índice único si no existe
ALTER TABLE cash_registers ADD UNIQUE KEY IF NOT EXISTS ux_cash_registers_name (name);

-- Insertar cajas iniciales si no existen
INSERT IGNORE INTO cash_registers (name,type,is_active) VALUES
 ('Oficina','CASH',1),
 ('Ferreteria','CASH',1),
 ('Banamex','BANK',1),
 ('Banorte','BANK',1);

-- 2. Actualizar tabla purchases con campos faltantes
ALTER TABLE purchases 
ADD COLUMN IF NOT EXISTS folio VARCHAR(100) AFTER notes,
ADD COLUMN IF NOT EXISTS cash_register_id INT AFTER folio,
ADD COLUMN IF NOT EXISTS bank_operation_number VARCHAR(100) AFTER cash_register_id,
ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Agregar foreign key para cash_register_id si no existe
SET @fk_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'purchases'
  AND CONSTRAINT_NAME = 'fk_purchases_cash_register'
);

SET @sql := IF(@fk_exists = 0, 
  'ALTER TABLE purchases ADD CONSTRAINT fk_purchases_cash_register FOREIGN KEY (cash_register_id) REFERENCES cash_registers(id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. Actualizar tabla account_moves con campos y estructura correcta
ALTER TABLE account_moves 
ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'ACTIVO' AFTER link_id,
ADD COLUMN IF NOT EXISTS note TEXT AFTER status,
ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER note,
ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Crear índices si no existen
ALTER TABLE account_moves 
ADD INDEX IF NOT EXISTS idx_date (date),
ADD INDEX IF NOT EXISTS idx_cash_register_id (cash_register_id);

-- 4. Actualizar tabla sales si falta alguna columna
ALTER TABLE sales
MODIFY COLUMN payment_method VARCHAR(50) NOT NULL DEFAULT 'CASH',
ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- 5. Crear tabla bank_accounts si no existe (para cuentas bancarias)
CREATE TABLE IF NOT EXISTS bank_accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cash_register_id INT NOT NULL,
  account_number VARCHAR(50) NOT NULL,
  account_holder VARCHAR(100) NOT NULL,
  bank_name VARCHAR(100) NOT NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_account_number (account_number),
  FOREIGN KEY (cash_register_id) REFERENCES cash_registers(id) ON DELETE CASCADE,
  INDEX idx_cash_register_id (cash_register_id)
);

-- 6. Crear tabla receivables (cuentas por cobrar) si no existe
CREATE TABLE IF NOT EXISTS receivables (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sale_id INT,
  client_id INT,
  client_name VARCHAR(100) NOT NULL,
  user_id INT,
  total_amount DECIMAL(12,2) NOT NULL,
  paid_amount DECIMAL(12,2) DEFAULT 0,
  pending_amount DECIMAL(12,2) GENERATED ALWAYS AS (total_amount - COALESCE(paid_amount, 0)) STORED,
  status ENUM('PENDIENTE','PAGADO','CANCELADO') DEFAULT 'PENDIENTE',
  due_date DATE,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (sale_id) REFERENCES sales(id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_status (status),
  INDEX idx_client_name (client_name),
  INDEX idx_created_at (created_at)
);

-- 7. Crear tabla payables (cuentas por pagar) si no existe
CREATE TABLE IF NOT EXISTS payables (
  id INT AUTO_INCREMENT PRIMARY KEY,
  purchase_id INT,
  supplier_id INT NOT NULL,
  supplier_name VARCHAR(100) NOT NULL,
  user_id INT,
  total_amount DECIMAL(12,2) NOT NULL,
  paid_amount DECIMAL(12,2) DEFAULT 0,
  pending_amount DECIMAL(12,2) GENERATED ALWAYS AS (total_amount - COALESCE(paid_amount, 0)) STORED,
  status ENUM('PENDIENTE','PAGADO','CANCELADO') DEFAULT 'PENDIENTE',
  due_date DATE,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (purchase_id) REFERENCES purchases(id),
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_status (status),
  INDEX idx_supplier_name (supplier_name),
  INDEX idx_created_at (created_at)
);

-- 8. Agregar campos adicionales a account_moves para tracking
ALTER TABLE account_moves 
ADD COLUMN IF NOT EXISTS ticket_number VARCHAR(50) AFTER link_id,
ADD COLUMN IF NOT EXISTS cash_register_id INT AFTER ticket_number,
ADD COLUMN IF NOT EXISTS user_reference_name VARCHAR(100) AFTER cash_register_id;

-- 9. Crear índices para mejor rendimiento
CREATE INDEX IF NOT EXISTS idx_receivables_status ON receivables(status);
CREATE INDEX IF NOT EXISTS idx_payables_status ON payables(status);
CREATE INDEX IF NOT EXISTS idx_receivables_client ON receivables(client_name);
CREATE INDEX IF NOT EXISTS idx_payables_supplier ON payables(supplier_name);

-- 10. Crear tablas para reembolsos / devoluciones si no existen
CREATE TABLE IF NOT EXISTS refunds (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sale_id INT NOT NULL,
  code VARCHAR(64) NOT NULL UNIQUE,
  amount DECIMAL(12,2) NOT NULL,
  cash_register_id INT NULL,
  origin VARCHAR(16) NOT NULL DEFAULT 'CAJA',
  bank_reference VARCHAR(64) NULL,
  note TEXT NULL,
  ticket_path VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (sale_id) REFERENCES sales(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS refund_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  refund_id INT NOT NULL,
  sale_item_id INT NULL,
  product_id INT NOT NULL,
  quantity DECIMAL(12,2) NOT NULL,
  unit_price DECIMAL(12,2) NOT NULL,
  total DECIMAL(12,2) NOT NULL,
  FOREIGN KEY (refund_id) REFERENCES refunds(id) ON DELETE CASCADE,
  FOREIGN KEY (sale_item_id) REFERENCES sale_items(id),
  FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. Tabla para solicitudes de devolución/reembolso (flujo de autorización)
CREATE TABLE IF NOT EXISTS refund_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sale_id INT NOT NULL,
  requester_id INT NOT NULL,
  `type` ENUM('REMB','CAMB') NOT NULL DEFAULT 'REMB',
  items JSON NULL,
  new_items JSON NULL,
  payment_method VARCHAR(30) NULL,
  cash_register_id INT NULL,
  bank_reference VARCHAR(64) NULL,
  note TEXT NULL,
  status ENUM('PENDIENTE','APROBADO','RECHAZADO') NOT NULL DEFAULT 'PENDIENTE',
  approved_by INT NULL,
  approved_at DATETIME NULL,
  rejected_by INT NULL,
  rejected_at DATETIME NULL,
  reject_reason TEXT NULL,
  refund_id INT NULL,
  ticket_path VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (sale_id) REFERENCES sales(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. Tabla para almacenar reportes generados (consecutivo)
CREATE TABLE IF NOT EXISTS reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type VARCHAR(80) NOT NULL,
  report_date DATE NOT NULL,
  filename VARCHAR(255) NOT NULL,
  generated_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. Mostrar resumen de cambios realizados
SELECT '✓ Migraciones completadas exitosamente' AS Estado;

SELECT COUNT(*) AS 'Total de Cajas' FROM cash_registers;
SELECT COUNT(*) AS 'Total Receivables' FROM receivables;
SELECT COUNT(*) AS 'Total Payables' FROM payables;
SELECT COUNT(*) AS 'Total Refunds' FROM refunds;

SELECT '✓ Todas las tablas actualizadas' AS Estado;
