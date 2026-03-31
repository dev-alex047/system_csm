<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../lib/fpdf.php';

requireLogin();
$pdo = getPDO();
$user = currentUser();
// Ensure newer columns exist for refunds workflow (safe to run multiple times)
try {
  $pdo->exec("ALTER TABLE refund_requests ADD COLUMN IF NOT EXISTS rejected_by INT NULL, ADD COLUMN IF NOT EXISTS rejected_at DATETIME NULL, ADD COLUMN IF NOT EXISTS reject_reason TEXT NULL");
} catch (Throwable $e) {
  // Older MySQL may not support IF NOT EXISTS — try without IF and ignore any errors
  try { $pdo->exec("ALTER TABLE refund_requests ADD COLUMN rejected_by INT NULL"); } catch (Throwable $e2) {}
  try { $pdo->exec("ALTER TABLE refund_requests ADD COLUMN rejected_at DATETIME NULL"); } catch (Throwable $e2) {}
  try { $pdo->exec("ALTER TABLE refund_requests ADD COLUMN reject_reason TEXT NULL"); } catch (Throwable $e2) {}
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  // Listar solicitudes (solo ADMIN)
  if (isset($_GET['action']) && $_GET['action'] === 'list_requests') {
    requireRole('admin');
    $stmt = $pdo->query("SELECT rr.*, u.username AS requester_name FROM refund_requests rr LEFT JOIN users u ON u.id = rr.requester_id WHERE rr.status = 'PENDIENTE' ORDER BY rr.created_at ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok' => true, 'requests' => $rows], JSON_UNESCAPED_UNICODE);
    exit;
  }

  if (isset($_GET['request_id'])) {
    requireRole('admin');
    $stmt = $pdo->prepare('SELECT rr.*, u.username AS requester_name FROM refund_requests rr LEFT JOIN users u ON u.id = rr.requester_id WHERE rr.id = ?');
    $stmt->execute([intval($_GET['request_id'])]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['ok' => true, 'request' => $r], JSON_UNESCAPED_UNICODE);
    exit;
  }

  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Invalid GET action']);
  exit;
}

$in = json_decode(file_get_contents('php://input'), true);
if (!$in) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); exit; }

$rawSale = trim((string)($in['sale_id'] ?? ''));
$type = strtoupper(trim($in['type'] ?? 'REMB'));
$items = is_array($in['items']) ? $in['items'] : [];
$new_items = is_array($in['new_items']) ? $in['new_items'] : [];
$payment_method = strtolower(trim((string)($in['payment_method'] ?? 'efectivo')));
$cash_register_id = isset($in['cash_register_id']) ? intval($in['cash_register_id']) : null;
$bank_reference = trim((string)($in['bank_reference'] ?? ''));
$note = trim((string)($in['note'] ?? ''));
$action = strtolower(trim($in['action'] ?? ''));

// REGLA: reembolsos (REMB) requieren autorización de ADMIN, salvo cuando se crea una solicitud (action=request)
if ($type === 'REMB' && $action !== 'request') {
  requireRole('admin');
}

// Resolver sale por id numérico o por ticket_barcode
$sale = null;
if (ctype_digit($rawSale) && intval($rawSale) > 0) {
  $stmt = $pdo->prepare('SELECT * FROM sales WHERE id = ?');
  $stmt->execute([intval($rawSale)]);
  $sale = $stmt->fetch(PDO::FETCH_ASSOC);
} else if ($rawSale !== '') {
  $stmt = $pdo->prepare('SELECT * FROM sales WHERE ticket_barcode = ? LIMIT 1');
  $stmt->execute([$rawSale]);
  $sale = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$sale) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Valid sale_id required (id or ticket code)']); exit; }
$saleId = (int)$sale['id'];

if ($type !== 'REMB' && $type !== 'CAMB') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'type must be REMB or CAMB']); exit; }

// Cargar venta
$stmt = $pdo->prepare('SELECT * FROM sales WHERE id = ?');
$stmt->execute([$saleId]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$sale) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'Sale not found']); exit; }

// Si la acción es 'request' (crear solicitud) -> crear fila en refund_requests y terminar
if ($action === 'request') {
  $pdo->exec("CREATE TABLE IF NOT EXISTS refund_requests (
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
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $insReq = $pdo->prepare('INSERT INTO refund_requests (sale_id, requester_id, `type`, items, new_items, payment_method, cash_register_id, bank_reference, note) VALUES (?,?,?,?,?,?,?,?,?)');
  $insReq->execute([$saleId, $user['id'], $type, json_encode($items), json_encode($new_items), $payment_method, $cash_register_id ?: null, $bank_reference ?: null, $note ?: null]);
  echo json_encode(['ok'=>true, 'request_id'=>$pdo->lastInsertId()]);
  exit;
}

// Si la acción es 'approve' la ejecuta un ADMIN (procesa la solicitud)
if ($action === 'approve') {
  requireRole('admin');
  $reqId = intval($in['request_id'] ?? 0);
  if ($reqId <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'request_id required']); exit; }

  $stmtReq = $pdo->prepare('SELECT * FROM refund_requests WHERE id = ?');
  $stmtReq->execute([$reqId]);
  $req = $stmtReq->fetch(PDO::FETCH_ASSOC);
  if (!$req) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'Request not found']); exit; }
  if ($req['status'] !== 'PENDIENTE') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Request is not pending']); exit; }

  // Reemplazar variables de la petición por las del request
  $type = strtoupper(trim($req['type'] ?? $type));
  $items = json_decode($req['items'] ?? '[]', true) ?: [];
  $new_items = json_decode($req['new_items'] ?? '[]', true) ?: [];
  $payment_method = $req['payment_method'] ?? $payment_method;
  $cash_register_id = $req['cash_register_id'] !== null ? intval($req['cash_register_id']) : $cash_register_id;
  $bank_reference = $req['bank_reference'] ?? $bank_reference;
  $note = $req['note'] ?? $note;

  // marcar que estamos procesando una aprobación para actualizar la fila al final
  $isApprovalRequest = $reqId;
}

// Rechazar solicitud
if ($action === 'reject') {
  requireRole('admin');
  $reqId = intval($in['request_id'] ?? 0);
  $reason = trim((string)($in['reason'] ?? ''));
  if ($reqId <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'request_id required']); exit; }
  $stmtReq = $pdo->prepare('SELECT * FROM refund_requests WHERE id = ?');
  $stmtReq->execute([$reqId]);
  $req = $stmtReq->fetch(PDO::FETCH_ASSOC);
  if (!$req) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'Request not found']); exit; }
  if ($req['status'] !== 'PENDIENTE') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Request is not pending']); exit; }
  $pdo->prepare('UPDATE refund_requests SET status=\'RECHAZADO\', rejected_by = ?, rejected_at = NOW(), reject_reason = ?, updated_at = NOW() WHERE id = ?')
    ->execute([$user['id'], $reason ?: null, $reqId]);
  echo json_encode(['ok'=>true, 'request_id'=>$reqId]);
  exit;
}

$pdo->beginTransaction();
try {
  // Asegurar que tablas refunds/refund_items existen
  $pdo->exec("CREATE TABLE IF NOT EXISTS refunds (
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
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $pdo->exec("CREATE TABLE IF NOT EXISTS refund_items (
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
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  // Calcular montos
  $refundTotal = 0.0;

  // Insert refund record with temporary code
  $ins = $pdo->prepare('INSERT INTO refunds (sale_id, code, amount, cash_register_id, origin, bank_reference, note) VALUES (?,?,?,?,?,?,?)');
  $ins->execute([$saleId, 'PENDIENTE', 0, $cash_register_id ?: null, ($payment_method === 'transferencia' ? 'BANCO' : 'CAJA'), $bank_reference ?: null, $note ?: null]);
  $refundId = (int)$pdo->lastInsertId();
  $code = ($type === 'REMB' ? 'REMB' : 'CAMB') . str_pad((string)$refundId, 6, '0', STR_PAD_LEFT);
  $pdo->prepare('UPDATE refunds SET code=? WHERE id=?')->execute([$code, $refundId]);

  // Procesar items devueltos: each item {sale_item_id, product_id, quantity, unit_price}
  $insItem = $pdo->prepare('INSERT INTO refund_items (refund_id, sale_item_id, product_id, quantity, unit_price, total) VALUES (?,?,?,?,?,?)');
  $updStock = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
  foreach ($items as $it) {
    $pid = intval($it['product_id'] ?? 0);
    $sid = isset($it['sale_item_id']) ? intval($it['sale_item_id']) : null;
    $qty = floatval($it['quantity'] ?? 0);
    $unit = floatval($it['unit_price'] ?? 0);
    if ($pid <= 0 || $qty <= 0) continue;
    $total = round($unit * $qty, 2);
    $insItem->execute([$refundId, $sid, $pid, $qty, $unit, $total]);
    $updStock->execute([$qty, $pid]);
    $refundTotal += $total;
  }

  // Procesar nuevos items entregados en CAMB (restar stock)
  $newTotal = 0.0;
  if ($type === 'CAMB' && !empty($new_items)) {
    // Validar stock disponible antes de decrementar
    $checkStmt = $pdo->prepare('SELECT stock FROM products WHERE id = ? FOR UPDATE');
    $updStockDown = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
    foreach ($new_items as $ni) {
      $pid = intval($ni['product_id'] ?? 0);
      $qty = floatval($ni['quantity'] ?? 0);
      $unit = floatval($ni['unit_price'] ?? 0);
      if ($pid <= 0 || $qty <= 0) continue;
      // Check stock
      $checkStmt->execute([$pid]);
      $row = $checkStmt->fetch(PDO::FETCH_ASSOC);
      if (!$row) throw new Exception('Producto no encontrado para cambio: ' . $pid);
      $avail = floatval($row['stock'] ?? 0);
      if ($avail < $qty) throw new Exception('Stock insuficiente para producto id ' . $pid . '. Disponibles: ' . $avail);

      $total = round($unit * $qty, 2);
      // Guardar el item con cantidad negativa para distinguir entrega al cliente
      $insItem->execute([$refundId, null, $pid, -$qty, $unit, -$total]);
      $updStockDown->execute([$qty, $pid]);
      $newTotal += $total;
    }
  }

  // Calcular diferencia: amount_to_return = refundTotal - newTotal
  $amountToReturn = round($refundTotal - $newTotal, 2);

  // Actualizar el registro refund con el monto final
  $pdo->prepare('UPDATE refunds SET amount=? WHERE id=?')->execute([$amountToReturn, $refundId]);

  // Ajustar receivables si la venta era 'cuenta'
  if (strtolower($sale['payment_method'] ?? '') === 'cuenta' && $amountToReturn > 0) {
    // Reducir el total_amount en receivables (si existe)
    $r = $pdo->prepare('SELECT * FROM receivables WHERE sale_id = ? LIMIT 1');
    $r->execute([$saleId]);
    $rec = $r->fetch(PDO::FETCH_ASSOC);
    if ($rec) {
      $newTotal = max(0.0, (float)$rec['total_amount'] - $amountToReturn);
      $pdo->prepare('UPDATE receivables SET total_amount = ?, updated_at = NOW() WHERE id = ?')->execute([$newTotal, $rec['id']]);
    }
  }

  // Crear movimiento contable si hay flujo de caja/banco (amountToReturn > 0 => payout to customer)
  $newMoveId = null;
  if ($amountToReturn != 0) {
    $moveType = ($amountToReturn > 0) ? 'SALIDA' : 'ENTRADA';
    $moveAmount = ($amountToReturn > 0) ? -abs($amountToReturn) : abs($amountToReturn);
    $origin = ($payment_method === 'transferencia') ? 'BANCO' : 'CAJA';

    $stmtMove = $pdo->prepare('INSERT INTO account_moves (type, origin, cash_register_id, reference, amount, user_id, link_type, link_id, note, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?, ?, NOW(), NOW())');
    // Note: reference = code, link_type = 'REFUND', link_id = refundId
    $stmtMove->execute([
      $moveType,
      $origin,
      $cash_register_id ?: null,
      $code,
      $moveAmount,
      $user['id'],
      'REFUND',
      $refundId,
      trim(($note ? $note . ' | ' : '') . 'Refund for sale #' . $saleId),
      'PAGADO'
    ]);
    $newMoveId = (int)$pdo->lastInsertId();
  }

  // Generar ticket PDF
  $ticketsDir = __DIR__ . '/../uploads/tickets';
  if (!is_dir($ticketsDir)) mkdir($ticketsDir, 0777, true);
  $fileName = strtolower(($type === 'REMB' ? 'remb' : 'camb') . '_' . $refundId . '.pdf');
  $filePath = $ticketsDir . '/' . $fileName;

  $pdf = new FPDF('P','mm', array(80,250));
  $pdf->AddPage();
  $pdf->SetMargins(5,5,5);
  $pdf->SetFont('Arial','B',12);
  $pdf->Cell(0,6, ($type === 'REMB' ? 'REEMBOLSO' : 'CAMBIO'), 0,1,'C');
  $pdf->SetFont('Arial','B',10);
  $pdf->Cell(0,6, $code, 0,1,'C');
  $pdf->Ln(2);
  $pdf->SetFont('Arial','',9);
  $pdf->Cell(0,5,'Venta: #' . $saleId,0,1);
  $pdf->Cell(0,5,'Fecha: ' . date('Y-m-d H:i'),0,1);
  if (!empty($sale['client_name'])) $pdf->Cell(0,5,'Cliente: ' . substr($sale['client_name'],0,30),0,1);
  $pdf->Ln(2);
  $pdf->SetFont('Arial','B',9);
  $pdf->Cell(34,5,'Producto',0,0,'L');
  $pdf->Cell(10,5,'Cant',0,0,'R');
  $pdf->Cell(18,5,'P.Unit',0,0,'R');
  $pdf->Cell(18,5,'Total',0,1,'R');
  $pdf->SetFont('Arial','',9);

  $itemsStmt = $pdo->prepare('SELECT ri.*, p.name AS product_name FROM refund_items ri LEFT JOIN products p ON p.id = ri.product_id WHERE ri.refund_id = ?');
  $itemsStmt->execute([$refundId]);
  $rows = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as $r) {
    $name = (string)($r['product_name'] ?? '');
    $pdf->Cell(34,5,substr($name,0,16),0,0,'L');
    $pdf->Cell(10,5,(string)$r['quantity'],0,0,'R');
    $pdf->Cell(18,5,number_format((float)$r['unit_price'],2),0,0,'R');
    $pdf->Cell(18,5,number_format((float)$r['total'],2),0,1,'R');
  }

  $pdf->Ln(2);
  $pdf->SetFont('Arial','B',10);
  $pdf->Cell(0,6,($amountToReturn >= 0 ? 'Total a pagar al cliente: $' : 'Total a cobrar al cliente: $') . number_format(abs($amountToReturn),2),0,1,'R');
  $pdf->Ln(2);
  if ($note) { $pdf->SetFont('Arial','',8); $pdf->MultiCell(0,4,'Nota: ' . $note); }
  $pdf->SetFont('Arial','I',8); $pdf->Cell(0,5,'Gracias por su preferencia',0,1,'C');
  $pdf->Output('F', $filePath);

  $relTicket = 'uploads/tickets/' . $fileName;
  $pdo->prepare('UPDATE refunds SET ticket_path=? WHERE id=?')->execute([$relTicket, $refundId]);

  // Si venimos de una aprobación, actualizar la solicitud como APROBADO y enlazar refund/ticket
  if (isset($isApprovalRequest)) {
    $pdo->prepare('UPDATE refund_requests SET status=\'APROBADO\', approved_by = ?, approved_at = NOW(), refund_id = ?, ticket_path = ?, updated_at = NOW() WHERE id = ?')
      ->execute([$user['id'], $refundId, $relTicket, $isApprovalRequest]);
  }

  $pdo->commit();

  echo json_encode(['ok'=>true, 'refund_id'=>$refundId, 'code'=>$code, 'ticket'=>$relTicket, 'move_id'=>$newMoveId]);
  exit;
} catch (Throwable $e) {
  $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Error processing return: ' . $e->getMessage()]);
  exit;
}
