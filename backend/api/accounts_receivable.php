<?php
// Devuelve una lista de cuentas por cobrar (ventas con monto pendiente)
header('Content-Type: application/json');
require_once __DIR__ . '/auth.php';

// Solo ADMIN puede ver / operar cuentas por cobrar
requireRole('admin');

require_once __DIR__ . '/db.php';
$pdo = getPDO();
$stmt = $pdo->prepare('SELECT
                         s.id,
                         s.client_name,
                         s.client_address,
                         s.client_phone,
                         s.pending_amount,
                         s.total_amount,
                         s.created_at,
                         u.username AS seller,
                         am.id AS account_move_id
                       FROM sales s
                       JOIN users u ON s.user_id = u.id
                       LEFT JOIN account_moves am
                         ON am.link_type = "VENTA"
                         AND am.link_id = s.id
                         AND UPPER(am.status) = "PENDIENTE"
                       WHERE s.pending_amount > 0
                       ORDER BY s.created_at DESC');
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode([
  'success' => true,
  'data' => $results
], JSON_UNESCAPED_UNICODE);