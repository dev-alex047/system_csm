<?php
// Database connection using PDO
// Adjust the following constants according to your PostgreSQL setup

// Ajustes de conexión para MySQL
define('DB_HOST', 'localhost');
define('DB_NAME', 'inventario');
define('DB_USER', 'fjpq');
define('DB_PASS', 'smCorp@137047');
// Si utilizas un puerto distinto al predeterminado de MySQL (3306), puedes definirlo aquí
define('DB_PORT', '3306');

function getPDO()
{
    static $pdo;
    if ($pdo === null) {
        // DSN para MySQL. Incluimos el charset para UTF8
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	            // Bootstrap (contabilidad): asegura columnas/tablas mínimas y
	            // registros base de cajas/cuentas para que el módulo funcione
	            // incluso cuando se actualiza el código sobre una BD existente.
	            bootstrapAccounting($pdo);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed']);
            exit;
        }
    }
    return $pdo;
}

/**
 * Crea/ajusta estructuras mínimas usadas por Contabilidad y Compras.
 * - cash_registers: inserta OFICINA/FERRETERIA (CASH) y BANAMEX/BANORTE (BANK)
 * - purchases: agrega columnas folio, cash_register_id, bank_operation_number, receipt_path si no existen
 * - account_moves: crea si no existe
 */
function bootstrapAccounting(PDO $pdo): void
{
    // Helpers
    $tableExists = function(string $table) use ($pdo): bool {
        $st = $pdo->prepare("SHOW TABLES LIKE ?");
        $st->execute([$table]);
        return (bool)$st->fetchColumn();
    };
    $columnExists = function(string $table, string $col) use ($pdo): bool {
        $st = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $st->execute([$col]);
        return (bool)$st->fetchColumn();
    };
    $indexExists = function(string $table, string $indexName) use ($pdo): bool {
        $st = $pdo->prepare("SHOW INDEX FROM `$table` WHERE Key_name = ?");
        $st->execute([$indexName]);
        return (bool)$st->fetchColumn();
    };

    // ----------------------------
    // cash_registers (cajas/cuentas)
    // ----------------------------
    if (!$tableExists('cash_registers')) {
        $pdo->exec("CREATE TABLE cash_registers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            type ENUM('CASH','BANK') NOT NULL DEFAULT 'CASH',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            saldo_inicial DECIMAL(12,2) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } else {
        // Si ya existe, lo actualizamos sin romper datos previos
        if (!$columnExists('cash_registers', 'type')) {
            $pdo->exec("ALTER TABLE cash_registers ADD COLUMN type ENUM('CASH','BANK') NOT NULL DEFAULT 'CASH' AFTER name");
        }
        if (!$columnExists('cash_registers', 'is_active')) {
            $pdo->exec("ALTER TABLE cash_registers ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER type");
        }
        if (!$columnExists('cash_registers', 'saldo_inicial')) {
            $pdo->exec("ALTER TABLE cash_registers ADD COLUMN saldo_inicial DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER is_active");
        }
        if (!$columnExists('cash_registers', 'created_at')) {
            $pdo->exec("ALTER TABLE cash_registers ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
        }
        if (!$columnExists('cash_registers', 'updated_at')) {
            $pdo->exec("ALTER TABLE cash_registers ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        }
    }

    // Índice único por nombre (sin IF NOT EXISTS para compatibilidad con MariaDB)
    if (!$indexExists('cash_registers', 'ux_cash_registers_name')) {
        $pdo->exec("CREATE UNIQUE INDEX ux_cash_registers_name ON cash_registers(name)");
    }

    // Defaults mínimos (no elimina/renombra los existentes)
    $defaults = [
        ['OFICINA', 'CASH'],
        ['FERRETERIA', 'CASH'],
        ['BANAMEX', 'BANK'],
        ['BANORTE', 'BANK'],
    ];
    $ins = $pdo->prepare("INSERT IGNORE INTO cash_registers(name, type, is_active) VALUES(?, ?, 1)");
    foreach ($defaults as $d) { $ins->execute($d); }

    // Si existen cajas heredadas "Caja 1", "Caja 2", "Caja 3" las dejamos inactivas
    // para que no aparezcan en selects (las puedes reactivar desde el módulo de contabilidad más adelante).
    try {
        $pdo->exec("UPDATE cash_registers SET is_active=0 WHERE UPPER(name) IN ('CAJA 1','CAJA 2','CAJA 3')");
    } catch (Exception $e) { /* noop */ }

    // ----------------------------
    // account_moves (movimientos contables)
    // ----------------------------
    if (!$tableExists('account_moves')) {
        $pdo->exec("CREATE TABLE account_moves (
            id INT AUTO_INCREMENT PRIMARY KEY,
            date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            type ENUM('ENTRADA','SALIDA') NOT NULL,
            origin ENUM('CAJA','BANCO') NOT NULL,
            cash_register_id INT NULL,
            reference VARCHAR(100) NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            user_id INT NULL,
            link_type VARCHAR(50) NULL,
            link_id INT NULL,
            status ENUM('PENDIENTE','PAGADO','CANCELADO') NOT NULL DEFAULT 'PAGADO',
            note TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_am_date(date),
            INDEX idx_am_cash(cash_register_id),
            CONSTRAINT fk_am_cash FOREIGN KEY (cash_register_id) REFERENCES cash_registers(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } else {
        // Si ya existe, agregamos columnas faltantes para que no fallen los inserts
        if (!$columnExists('account_moves', 'status')) {
            $pdo->exec("ALTER TABLE account_moves ADD COLUMN status ENUM('PENDIENTE','PAGADO','CANCELADO') NOT NULL DEFAULT 'PAGADO' AFTER link_id");
        }
        if (!$columnExists('account_moves', 'note')) {
            $pdo->exec("ALTER TABLE account_moves ADD COLUMN note TEXT NULL AFTER status");
        }
        if (!$columnExists('account_moves', 'created_at')) {
            $pdo->exec("ALTER TABLE account_moves ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER note");
        }
        if (!$columnExists('account_moves', 'updated_at')) {
            $pdo->exec("ALTER TABLE account_moves ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
        }
    }

    // ----------------------------
    // purchases: columnas para ligar caja/cuenta y folio
    // ----------------------------
    if ($tableExists('purchases')) {
        if (!$columnExists('purchases', 'folio')) {
            $pdo->exec("ALTER TABLE purchases ADD COLUMN folio VARCHAR(100) NULL AFTER supplier_id");
        }
        if (!$columnExists('purchases', 'cash_register_id')) {
            $pdo->exec("ALTER TABLE purchases ADD COLUMN cash_register_id INT NULL AFTER payment_method");
        }
        if (!$columnExists('purchases', 'bank_operation_number')) {
            $pdo->exec("ALTER TABLE purchases ADD COLUMN bank_operation_number VARCHAR(100) NULL AFTER cash_register_id");
        }
        // receipt_path ya existía en tu versión, pero lo dejamos por compatibilidad
        if (!$columnExists('purchases', 'receipt_path')) {
            $pdo->exec("ALTER TABLE purchases ADD COLUMN receipt_path VARCHAR(255) NULL AFTER bank_operation_number");
        }
    }
}