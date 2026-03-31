-- Actualización Fase 2 (Contabilidad + Compras) - MariaDB/MySQL
-- Ejecuta este SQL si NO quieres depender del bootstrap automático.

-- cash_registers
CREATE TABLE IF NOT EXISTS cash_registers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  type ENUM('CASH','BANK') NOT NULL DEFAULT 'CASH',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  saldo_inicial DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Índice único por nombre (si no existe)
SET @idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='cash_registers' AND INDEX_NAME='ux_cash_registers_name'
);
SET @sql := IF(@idx=0, 'CREATE UNIQUE INDEX ux_cash_registers_name ON cash_registers(name)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Datos base
INSERT IGNORE INTO cash_registers (name,type,is_active) VALUES
 ('Oficina','CASH',1),
 ('Ferreteria','CASH',1),
 ('Banamex','BANK',1),
 ('Banorte','BANK',1);

-- account_moves
CREATE TABLE IF NOT EXISTS account_moves (
  id INT AUTO_INCREMENT PRIMARY KEY,
  date DATETIME NOT NULL,
  type VARCHAR(20) NOT NULL,
  origin VARCHAR(20) NOT NULL,
  cash_register_id INT NULL,
  reference VARCHAR(100) NULL,
  amount DECIMAL(12,2) NOT NULL,
  user_id INT NULL,
  link_type VARCHAR(20) NULL,
  link_id INT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'ACTIVO',
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (date),
  INDEX (cash_register_id)
) ENGINE=InnoDB;

-- Asegurar columnas faltantes (no falla si ya existen)
ALTER TABLE account_moves ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'ACTIVO';
ALTER TABLE account_moves ADD COLUMN IF NOT EXISTS note TEXT NULL;
ALTER TABLE account_moves ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE account_moves ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- purchases
ALTER TABLE purchases ADD COLUMN IF NOT EXISTS folio VARCHAR(100) NULL;
ALTER TABLE purchases ADD COLUMN IF NOT EXISTS cash_register_id INT NULL;
ALTER TABLE purchases ADD COLUMN IF NOT EXISTS bank_operation_number VARCHAR(100) NULL;
ALTER TABLE purchases ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

