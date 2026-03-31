<?php
// Cuentas por pagar
header('Content-Type: application/json');
require_once __DIR__ . '/auth.php';
requireLogin();

$pdo = getPDO();

// GET: Listar cuentas por pagar
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $status = $_GET['status'] ?? null;
    $supplierName = $_GET['supplier_name'] ?? null;
    $userId = $_GET['user_id'] ?? null;
    
    // Traer payables con el nombre del creador y del último operador que realizó un pago (si aplica)
    $sql = 'SELECT p.*, u.username AS creator_name, (
                SELECT COALESCE(am.user_reference_name, u2.username)
                FROM account_moves am
                LEFT JOIN users u2 ON u2.id = am.user_id
                WHERE am.link_type = "OTRO" AND am.link_id = p.id
                ORDER BY am.created_at DESC
                LIMIT 1
            ) AS last_operator_name
            FROM payables p
            LEFT JOIN users u ON u.id = p.user_id
            WHERE 1=1';
    $params = [];
    
    if ($status) {
        $sql .= ' AND status = ?';
        $params[] = strtoupper($status);
    }
    
    if ($supplierName) {
        $sql .= ' AND supplier_name LIKE ?';
        $params[] = '%' . $supplierName . '%';
    }
    
    if ($userId) {
        $sql .= ' AND user_id = ?';
        $params[] = (int)$userId;
    }
    
    $sql .= ' ORDER BY created_at DESC';
    
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $results = $st->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($results);
    exit;
}

// POST: Crear o actualizar cuenta por pagar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? 'create';
    
    if ($action === 'create') {
        $purchaseId = $data['purchase_id'] ?? null;
        $supplierId = $data['supplier_id'] ?? null;
        $supplierName = trim($data['supplier_name'] ?? '');
        $totalAmount = floatval($data['total_amount'] ?? 0);
        $userId = currentUser()['id'];
        
        if (!$supplierName || $totalAmount <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Datos inválidos']);
            exit;
        }
        
        $st = $pdo->prepare('INSERT INTO payables (purchase_id, supplier_id, supplier_name, user_id, total_amount, status) VALUES (?,?,?,?,?,?)');
        $st->execute([$purchaseId, $supplierId, $supplierName, $userId, $totalAmount, 'PENDIENTE']);


        echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId()]);
        exit;
    }
    
    if ($action === 'pay') {

        $id = (int)($data['id'] ?? 0);
        $amount = floatval($data['amount'] ?? 0);
        $cashRegisterId = $data['cash_register_id'] ?? null;
        $paymentMethod = $data['payment_method'] ?? 'EFECTIVO';
        
        if ($id <= 0 || $amount <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Datos inválidos']);
            exit;
        }
        
        $st = $pdo->prepare('SELECT * FROM payables WHERE id = ?');
        $st->execute([$id]);
        $pay = $st->fetch(PDO::FETCH_ASSOC);
        
        if (!$pay) {
            http_response_code(404);
            echo json_encode(['error' => 'Cuenta no encontrada']);
            exit;
        }
        
        $newPaid = ($pay['paid_amount'] ?? 0) + $amount;
        $newStatus = ($newPaid >= $pay['total_amount']) ? 'PAGADO' : 'PENDIENTE';
        
        // Update payable
        $st = $pdo->prepare('UPDATE payables SET paid_amount = ?, status = ?, updated_at = NOW() WHERE id = ?');
        $st->execute([$newPaid, $newStatus, $id]);
        
        // Crear movimiento contable sólo si se especificó caja/banco (evita duplicados y movimientos inconsistentes)
        try {
            $userId = currentUser()['id'];
            $moveNote = 'PAGO - ' . $pay['supplier_name'];

            $origin = 'OTRO';
            if (strtoupper($paymentMethod) === 'EFECTIVO') $origin = 'CAJA';
            elseif (strtoupper($paymentMethod) === 'TRANSFERENCIA') $origin = 'BANCO';

            if ($cashRegisterId) {
                $username = (currentUser()['username'] ?? null);
                $moveSql = "INSERT INTO account_moves (type, origin, cash_register_id, reference, amount, user_id, user_reference_name, link_type, link_id, note, status) VALUES ('SALIDA', ?, ?, 'PAGO', ?, ?, ?, 'OTRO', ?, ?, 'PAGADO')";
                $moveStmt = $pdo->prepare($moveSql);
                $moveStmt->execute([$origin, $cashRegisterId, $amount, $userId, $username, $id, $moveNote]);

            }
        } catch (Exception $e) {
            // Log error but don't fail the payment
            // Log error to server logs but do not fail the payment
            error_log("Error creating account move for payable payment: " . $e->getMessage());
        }
        
        echo json_encode(['ok' => true]);
        exit;
    }
    
    http_response_code(400);
    echo json_encode(['error' => 'Acción no soportada']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no soportado']);
