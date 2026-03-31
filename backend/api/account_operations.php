<?php
/**
 * API para operaciones manuales de contabilidad:
 * - Agregar ENTRADA (dinero que entra a caja)
 * - Agregar SALIDA (dinero que sale de caja)
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

requireRole('admin');
$pdo = getPDO();

function fail($code, $msg) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail(405, 'Solo POST permitido');
}

$data = json_decode(file_get_contents('php://input'), true);
$action = strtolower(trim($data['action'] ?? ''));

// ===== ACTION: ADD ENTRY (Dinero que entra) =====
if ($action === 'entry') {
    $description = trim($data['description'] ?? '');
    $amount = floatval($data['amount'] ?? 0);
    $cashRegisterId = intval($data['cash_register_id'] ?? 0);
    $reference = trim($data['reference'] ?? '');
    $note = trim($data['note'] ?? '');
    
    if (!$description || $amount <= 0) {
        fail(400, 'Descripción y monto requeridos');
    }
    
    if ($cashRegisterId <= 0) {
        fail(400, 'Selecciona una caja/cuenta');
    }
    
    try {
        $userId = $_SESSION['usuarioId'] ?? 1;
        
        $sql = "INSERT INTO account_moves (
            type, origin, cash_register_id, reference, amount, 
            user_id, link_type, link_id, status, note, created_at, updated_at
        ) VALUES ('ENTRADA', 'CAJA', ?, ?, ?, ?, 'MANUAL', 0, 'PAGADO', ?, NOW(), NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $cashRegisterId,
            $reference ?: $description,
            $amount,
            $userId,
            $note ? ($description . ' | ' . $note) : $description
        ]);
        
        echo json_encode([
            'ok' => true,
            'id' => $pdo->lastInsertId(),
            'message' => 'Entrada registrada correctamente'
        ], JSON_UNESCAPED_UNICODE);
        exit;
        
    } catch (Exception $e) {
        fail(500, 'Error: ' . $e->getMessage());
    }
}

// ===== ACTION: ADD EXIT (Dinero que sale) =====
if ($action === 'exit') {
    $description = trim($data['description'] ?? '');
    $amount = floatval($data['amount'] ?? 0);
    $cashRegisterId = intval($data['cash_register_id'] ?? 0);
    $reference = trim($data['reference'] ?? '');
    $note = trim($data['note'] ?? '');
    
    if (!$description || $amount <= 0) {
        fail(400, 'Descripción y monto requeridos');
    }
    
    if ($cashRegisterId <= 0) {
        fail(400, 'Selecciona una caja/cuenta');
    }
    
    try {
        $userId = $_SESSION['usuarioId'] ?? 1;
        
        $sql = "INSERT INTO account_moves (
            type, origin, cash_register_id, reference, amount, 
            user_id, link_type, link_id, status, note, created_at, updated_at
        ) VALUES ('SALIDA', 'CAJA', ?, ?, ?, ?, 'MANUAL', 0, 'PAGADO', ?, NOW(), NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $cashRegisterId,
            $reference ?: $description,
            -abs($amount),  // SALIDA siempre negativa
            $userId,
            $note ? ($description . ' | ' . $note) : $description
        ]);
        
        echo json_encode([
            'ok' => true,
            'id' => $pdo->lastInsertId(),
            'message' => 'Salida registrada correctamente'
        ], JSON_UNESCAPED_UNICODE);
        exit;
        
    } catch (Exception $e) {
        fail(500, 'Error: ' . $e->getMessage());
    }
}

fail(400, 'Acción no soportada');
