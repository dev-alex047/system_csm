<?php
// Cuentas por cobrar
header('Content-Type: application/json');
require_once __DIR__ . '/auth.php';
requireLogin();

$pdo = getPDO();

// GET: Listar cuentas por cobrar
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $status = $_GET['status'] ?? null;
    $clientName = $_GET['client_name'] ?? null;
    $userId = $_GET['user_id'] ?? null;
    
    // Traer receivables con el nombre del creador y del último operador que realizó un cobro (si aplica)
    $sql = 'SELECT r.*, u.username AS creator_name, (
                SELECT COALESCE(am.user_reference_name, u2.username)
                FROM account_moves am
                LEFT JOIN users u2 ON u2.id = am.user_id
                WHERE am.link_type = "OTRO" AND am.link_id = r.id
                ORDER BY am.created_at DESC
                LIMIT 1
            ) AS last_operator_name
            FROM receivables r
            LEFT JOIN users u ON u.id = r.user_id
            WHERE 1=1';
    $params = [];
    
    if ($status) {
        $sql .= ' AND status = ?';
        $params[] = strtoupper($status);
    }
    
    if ($clientName) {
        $sql .= ' AND client_name LIKE ?';
        $params[] = '%' . $clientName . '%';
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

// POST: Crear o actualizar cuenta por cobrar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? 'create';
    
    if ($action === 'create') {
        $saleId = $data['sale_id'] ?? null;
        $clientId = $data['client_id'] ?? null;
        $clientName = trim($data['client_name'] ?? '');
        $totalAmount = floatval($data['total_amount'] ?? 0);
        $userId = currentUser()['id'];
        
        if (!$clientName || $totalAmount <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Datos inválidos']);
            exit;
        }
        
        $st = $pdo->prepare('INSERT INTO receivables (sale_id, client_id, client_name, user_id, total_amount, status) VALUES (?,?,?,?,?,?)');
        $st->execute([$saleId, $clientId, $clientName, $userId, $totalAmount, 'PENDIENTE']);
        
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
        
        $st = $pdo->prepare('SELECT * FROM receivables WHERE id = ?');
        $st->execute([$id]);
        $rec = $st->fetch(PDO::FETCH_ASSOC);
        
        if (!$rec) {
            http_response_code(404);
            echo json_encode(['error' => 'Cuenta no encontrada']);
            exit;
        }
        
        $newPaid = ($rec['paid_amount'] ?? 0) + $amount;
        $newStatus = ($newPaid >= $rec['total_amount']) ? 'PAGADO' : 'PENDIENTE';
        
        // Update receivable
        $st = $pdo->prepare('UPDATE receivables SET paid_amount = ?, status = ?, updated_at = NOW() WHERE id = ?');
        $st->execute([$newPaid, $newStatus, $id]);
        
        // Create account movement for the payment (only if cash_register_id provided)
        try {
            $userId = currentUser()['id'];
            $moveNote = 'COBRO - ' . $rec['client_name'];

            $origin = 'OTRO';
            if (strtoupper($paymentMethod) === 'EFECTIVO') $origin = 'CAJA';
            elseif (strtoupper($paymentMethod) === 'TRANSFERENCIA') $origin = 'BANCO';

            if ($cashRegisterId) {
                // Insert with user_reference_name so UI can show who realizó/autorizó el movimiento
                $username = (currentUser()['username'] ?? null);
                $moveSql = "INSERT INTO account_moves (type, origin, cash_register_id, reference, amount, user_id, user_reference_name, link_type, link_id, note, status) VALUES ('ENTRADA', ?, ?, 'COBRO', ?, ?, ?, 'OTRO', ?, ?, 'PAGADO')";
                $moveStmt = $pdo->prepare($moveSql);
                $moveStmt->execute([$origin, $cashRegisterId, $amount, $userId, $username, $id, $moveNote]);
            }
        } catch (Exception $e) {
            // Log error but don't fail the payment
            error_log("Error creating account move for receivable payment: " . $e->getMessage());
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
