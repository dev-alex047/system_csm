<?php
// API para listar cuentas por pagar (compras a crédito)
// Accesible sólo para administradores
header('Content-Type: application/json');
require_once __DIR__ . '/auth.php';
requireRole('admin');
require_once __DIR__ . '/db.php';

function normalizeDate($date) {
    if (!$date) return null;
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d ? $d->format('Y-m-d') : null;
}

$pdo = getPDO();
$start = isset($_GET['start']) ? normalizeDate($_GET['start']) : null;
$end   = isset($_GET['end'])   ? normalizeDate($_GET['end'])   : null;

// Seleccionar compras con método de pago pendiente o pendiente > 0
$where = [];
$params = [];
$where[] = "(p.payment_method = 'PENDIENTE' OR p.pending_amount > 0)";
if ($start) {
    $where[] = 'p.date >= ?';
    $params[] = $start;
}
if ($end) {
    $where[] = 'p.date <= ?';
    $params[] = $end;
}
$whereClause = 'WHERE ' . implode(' AND ', $where);

$sql = "SELECT
    p.id,
    p.date AS date,
    s.name AS supplier_name,
    am.id AS account_move_id,
    SUM(pi.quantity * pi.unit_price) AS total,
    p.pending_amount AS pending,
    p.payment_method,
    u.username AS user_name,
    p.receipt_path
  FROM purchases p
  JOIN purchase_items pi ON pi.purchase_id = p.id
  LEFT JOIN suppliers s ON p.supplier_id = s.id
  LEFT JOIN account_moves am
    ON am.link_type = 'COMPRA'
    AND am.link_id = p.id
    AND UPPER(am.status) = 'PENDIENTE'
  LEFT JOIN users u ON p.created_by_user_id = u.id
  $whereClause
  GROUP BY p.id, p.date, s.name, am.id, p.pending_amount, p.payment_method, u.username, p.receipt_path
  ORDER BY p.date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['records' => $records]);
?>