<?php
// Acciones sobre movimientos contables (fase 2+)
// POST JSON:
//  - action=settle    {move_id, cash_register_id, note?, reference_code?}
//  - action=cancel    {move_id, note?}
//  - action=refund    {move_id, note?, cash_register_id?, reference_code?, restore_stock?}

header('Content-Type: application/json');
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

requireRole('admin');
$pdo = getPDO();

// Función para manejar la falla
function fail(int $code, string $msg) {
  http_response_code($code);
  echo json_encode(['ok' => false, 'error' => $msg]);
  exit;
}

function jsonIn(): array {
  $input = file_get_contents('php://input');
  if (!$input) return [];
  $decoded = json_decode($input, true);
  return is_array($decoded) ? $decoded : [];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  fail(405, 'Método no permitido');
}

$in = jsonIn();
$action = strtolower(trim((string)($in['action'] ?? '')));

// Obtener el movimiento original
$moveId = (int)($in['move_id'] ?? 0);
if ($moveId <= 0) fail(400, 'move_id inválido');

// Obtener movimiento
$st = $pdo->prepare('SELECT * FROM account_moves WHERE id = ?');
$st->execute([$moveId]);
$move = $st->fetch(PDO::FETCH_ASSOC);
if (!$move) fail(404, 'Movimiento no encontrado');

// Obtener cuenta para derivar origen
function getOrigin(PDO $pdo, ?int $cashId): string {
  if (!$cashId) return '';
  try {
    $st = $pdo->prepare('SELECT kind FROM cash_registers WHERE id = ?');
    $st->execute([$cashId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    $kind = strtoupper((string)($row['kind'] ?? ''));
    return $kind === 'BANCO' ? 'BANCO' : 'CAJA';
  } catch (Throwable $e) {
    return 'CAJA';
  }
}

// ===== ACTION: SETTLE (PAGAR/COBRAR) =====
if ($action === 'settle') {
  $cashId = (int)($in['cash_register_id'] ?? 0);
  if ($cashId <= 0) fail(400, 'cash_register_id requerido');

  $extra = trim((string)($in['reference_code'] ?? ''));
  $note = trim((string)($in['note'] ?? ''));

  $origin = getOrigin($pdo, $cashId);

  // Nota final
  $parts = [];
  if ($note !== '') $parts[] = $note;
  if ($extra !== '') $parts[] = 'Ref: ' . $extra;
  $finalNote = implode(' | ', $parts);

  $up = $pdo->prepare('UPDATE account_moves
                        SET cash_register_id = ?, origin = ?, status = "PAGADO", note = ?, updated_at = NOW()
                        WHERE id = ?');
  $up->execute([$cashId, $origin, $finalNote, $moveId]);

  echo json_encode(['ok' => true]);
  exit;
}

// ===== ACTION: CANCEL =====
if ($action === 'cancel') {
  $note = trim((string)($in['note'] ?? ''));
  $finalNote = $note !== '' ? $note : ($move['note'] ?? '');
  $up = $pdo->prepare('UPDATE account_moves SET status = "CANCELADO", note = ?, updated_at = NOW() WHERE id = ?');
  $up->execute([$finalNote, $moveId]);
  echo json_encode(['ok' => true]);
  exit;
}

// ===== ACTION: REFUND (DEVOLUCIÓN) =====
if ($action === 'refund') {
  // Reembolso / devolución
  // REGLA: NO cancelamos el movimiento original (para no "doblear" el efecto)
  // Solo registramos la salida de dinero (SALIDA) y, si viene de una VENTA,
  // restauramos stock y generamos ticket REMB####.

  $note = trim((string)($in['note'] ?? ''));
  $restoreStock = isset($in['restore_stock']) ? (int)$in['restore_stock'] : 1;
  $cashRegisterId = isset($in['cash_register_id']) ? (int)$in['cash_register_id'] : (int)($move['cash_register_id'] ?? 0);
  $bankRef = trim((string)($in['reference_code'] ?? ''));

  $linkType = strtoupper((string)($move['link_type'] ?? ''));
  $linkId = (int)($move['link_id'] ?? 0);

  // Si es reembolso de VENTA, hacemos flujo completo (ticket + stock)
  if ($linkType === 'VENTA' && $linkId > 0) {
    // Crear tablas si no existen (auto-migración simple)
    try {
      $pdo->exec("CREATE TABLE IF NOT EXISTS refunds (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sale_id INT NOT NULL,
        code VARCHAR(32) NOT NULL UNIQUE,
        amount DECIMAL(10,2) NOT NULL,
        cash_register_id INT NULL,
        origin VARCHAR(16) NOT NULL DEFAULT 'CAJA',
        bank_reference VARCHAR(64) NULL,
        note TEXT NULL,
        ticket_path VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

      $pdo->exec("CREATE TABLE IF NOT EXISTS refund_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        refund_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL,
        unit_price DECIMAL(10,2) NOT NULL,
        total DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (refund_id) REFERENCES refunds(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {
      // Las tablas pueden ya existir, ignorar
    }

    // Cargar venta e items
    $saleStmt = $pdo->prepare('SELECT * FROM sales WHERE id=?');
    $saleStmt->execute([$linkId]);
    $sale = $saleStmt->fetch(PDO::FETCH_ASSOC);
    if (!$sale) {
      fail(404, 'No se encontró la venta ligada al movimiento');
    }

    $itemsStmt = $pdo->prepare('SELECT si.*, p.name AS product_name FROM sale_items si LEFT JOIN products p ON p.id=si.product_id WHERE si.sale_id=?');
    $itemsStmt->execute([$linkId]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    $amountRefund = (float)($sale['total_amount'] ?? $sale['total'] ?? 0);
    if ($amountRefund <= 0) {
      fail(400, 'La venta no tiene total válido para reembolso');
    }

    $origin = strtoupper((string)($move['origin'] ?? 'CAJA'));
    if ($origin !== 'BANCO') $origin = 'CAJA';

    $pdo->beginTransaction();
    try {
      // Crear refund
      $insRefund = $pdo->prepare('INSERT INTO refunds (sale_id, code, amount, cash_register_id, origin, bank_reference, note) VALUES (?,?,?,?,?,?,?)');
      $insRefund->execute([$linkId, 'PENDIENTE', $amountRefund, $cashRegisterId ?: null, $origin, $bankRef ?: null, $note ?: null]);
      $refundId = (int)$pdo->lastInsertId();
      $code = 'REMB' . str_pad((string)$refundId, 6, '0', STR_PAD_LEFT);
      $pdo->prepare('UPDATE refunds SET code=? WHERE id=?')->execute([$code, $refundId]);

      // Guardar items
      if ($items) {
        $insItem = $pdo->prepare('INSERT INTO refund_items (refund_id, product_id, quantity, unit_price, total) VALUES (?,?,?,?,?)');
        foreach ($items as $it) {
          $insItem->execute([
            $refundId,
            (int)$it['product_id'],
            (int)$it['quantity'],
            (float)$it['unit_price'],
            (float)$it['total']
          ]);
        }
      }

      // Restaurar stock (si se pide)
      if ($restoreStock && $items) {
        $updStock = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id=?');
        foreach ($items as $it) {
          $updStock->execute([(int)$it['quantity'], (int)$it['product_id']]);
        }
      }

      // Crear movimiento contable de salida
      $insMove = $pdo->prepare('INSERT INTO account_moves
        (type, origin, cash_register_id, reference, amount, user_id, link_type, link_id, status, note, created_at, updated_at)
        VALUES (?,?,?,?,?,?,?,?,"PAGADO",?,NOW(),NOW())');
      $insMove->execute([
        "SALIDA",
        $origin,
        $cashRegisterId ?: null,
        $code,
        -abs($amountRefund),
        (int)($move['user_id'] ?? 0),
        "REFUND",
        $refundId,
        trim('Reembolso de VENTA #' . $linkId . ($note ? (' | ' . $note) : ''))
      ]);
      $newMoveId = (int)$pdo->lastInsertId();

      // Marcar el movimiento original como REEMBOLSADO (no CANCELADO)
      $pdo->prepare('UPDATE account_moves SET status="REEMBOLSADO", updated_at=NOW() WHERE id=?')->execute([$moveId]);

      // Generar ticket PDF (mismo estilo que ventas)
      $ticketDir = __DIR__ . '/../uploads/tickets';
      if (!is_dir($ticketDir)) {
        mkdir($ticketDir, 0777, true);
      }
      $ticketName = 'ticket_' . $code . '.pdf';
      $ticketPath = $ticketDir . '/' . $ticketName;

      require_once __DIR__ . '/../lib/fpdf.php';

      $pdf = new FPDF('P', 'mm', array(80, 250));
      $pdf->AddPage();
      $pdf->SetFont('Arial','B',12);
      $pdf->Cell(0,6,'REEMBOLSO',0,1,'C');
      $pdf->SetFont('Arial','B',11);
      $pdf->Cell(0,6,$code,0,1,'C');
      $pdf->Ln(2);
      $pdf->SetFont('Arial','',9);
      $pdf->Cell(0,5,'Venta: #' . $linkId,0,1,'L');
      $pdf->Cell(0,5,'Fecha: ' . date('Y-m-d H:i'),0,1,'L');
      if (!empty($sale['client_name'])) {
        $pdf->Cell(0,5,'Cliente: ' . substr($sale['client_name'], 0, 30),0,1,'L');
      }
      $pdf->Ln(1);
      $pdf->SetFont('Arial','B',9);
      $pdf->Cell(34,5,'Producto',0,0,'L');
      $pdf->Cell(10,5,'Cant',0,0,'R');
      $pdf->Cell(18,5,'P.Unit',0,0,'R');
      $pdf->Cell(18,5,'Total',0,1,'R');
      $pdf->SetFont('Arial','',9);
      foreach ($items as $it) {
        $name = (string)($it['product_name'] ?? '');
        $pdf->Cell(34,5,substr($name,0,16),0,0,'L');
        $pdf->Cell(10,5,(string)$it['quantity'],0,0,'R');
        $pdf->Cell(18,5,number_format((float)$it['unit_price'],2),0,0,'R');
        $pdf->Cell(18,5,number_format((float)$it['total'],2),0,1,'R');
      }
      $pdf->Ln(1);
      $pdf->SetFont('Arial','B',10);
      $pdf->Cell(0,6,'Total reembolso: $' . number_format($amountRefund,2),0,1,'R');
      if ($origin === 'BANCO') {
        $pdf->SetFont('Arial','',9);
        $pdf->Cell(0,5,'Metodo: Transferencia',0,1,'L');
        if ($bankRef) $pdf->Cell(0,5,'Folio: ' . $bankRef,0,1,'L');
      } else {
        $pdf->SetFont('Arial','',9);
        $pdf->Cell(0,5,'Metodo: Efectivo',0,1,'L');
      }
      if ($note) {
        $pdf->Ln(1);
        $pdf->SetFont('Arial','',8);
        $pdf->MultiCell(0,4,'Nota: ' . $note,0,'L');
      }
      $pdf->Ln(2);
      $pdf->SetFont('Arial','I',8);
      $pdf->Cell(0,5,'Gracias por su preferencia',0,1,'C');

      $pdf->Output('F', $ticketPath);

      $relTicket = 'backend/uploads/tickets/' . $ticketName;
      $pdo->prepare('UPDATE refunds SET ticket_path=? WHERE id=?')->execute([$relTicket, $refundId]);

      $pdo->commit();

      echo json_encode([
        'ok' => true,
        'refund_id' => $refundId,
        'code' => $code,
        'ticket' => $relTicket,
        'new_move_id' => $newMoveId
      ], JSON_UNESCAPED_UNICODE);
      exit;
    } catch (Throwable $e) {
      $pdo->rollBack();
      fail(500, 'Error en reembolso: ' . $e->getMessage());
    }
  }

  // Fallback: movimiento inverso simple si NO es VENTA
  $t = strtoupper((string)($move['type'] ?? ''));
  $newType = ($t === 'SALIDA') ? 'ENTRADA' : 'SALIDA';

  $amount = (float)($move['amount'] ?? 0);
  // Mantén signo coherente: ENTRADA siempre positivo, SALIDA siempre negativo
  if ($newType === 'ENTRADA') $amount = abs($amount);
  if ($newType === 'SALIDA') $amount = -abs($amount);

  $finalNote = trim('Reverso de #' . $moveId . ($note ? (' | ' . $note) : ''));

  $ins = $pdo->prepare('INSERT INTO account_moves
    (type, origin, cash_register_id, reference, amount, user_id, link_type, link_id, status, note, created_at, updated_at)
    VALUES (?,?,?,?,?,?,?,?,"PAGADO",?,NOW(),NOW())');
  $ins->execute([
    $newType,
    $move['origin'],
    $move['cash_register_id'],
    'REEMBOLSO',
    $amount,
    (int)($move['user_id'] ?? 0),
    $move['link_type'],
    $move['link_id'],
    $finalNote
  ]);

  echo json_encode(['ok' => true, 'new_id' => (int)$pdo->lastInsertId()], JSON_UNESCAPED_UNICODE);
  exit;
}

fail(400, 'Acción no soportada');
