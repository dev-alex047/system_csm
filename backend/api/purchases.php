<?php
// API para registrar y consultar compras de proveedores
header('Content-Type: application/json');
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../lib/fpdf.php';

$pdo = getPDO();
$method = $_SERVER['REQUEST_METHOD'];

/**
 * Asegura que purchase_items tenga columnas para guardar el snapshot
 * del precio de venta y margen en el momento de la entrada.
 * Esto permite que el historial sea real (no dependiente del precio actual).
 */
function ensurePurchaseItemSnapshotColumns(PDO $pdo): void {
    try {
        $check = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $cols = ['public_price' => "DECIMAL(10,2) NULL", 'profit_margin' => "DECIMAL(10,4) NULL"]; 
        foreach ($cols as $col => $def) {
            $check->execute(['purchase_items', $col]);
            $exists = (int)$check->fetchColumn() > 0;
            if (!$exists) {
                // ALTER TABLE ... ADD COLUMN
                $pdo->exec("ALTER TABLE purchase_items ADD COLUMN $col $def");
            }
        }
    } catch (Throwable $e) {
        // Si la BD no permite INFORMATION_SCHEMA o ALTER (por permisos),
        // no bloqueamos la compra, solo registramos el problema.
        error_log('ensurePurchaseItemSnapshotColumns: ' . $e->getMessage());
    }
}

switch ($method) {
    case 'GET':
        // Listado de compras o detalle
        requireLogin();
        if (isset($_GET['id'])) {
            $purchaseId = intval($_GET['id']);
            // Obtener compra y sus items
            $pStmt = $pdo->prepare('SELECT * FROM purchases WHERE id = ?');
            $pStmt->execute([$purchaseId]);
            $purchase = $pStmt->fetch(PDO::FETCH_ASSOC);
            if (!$purchase) {
                http_response_code(404);
                echo json_encode(['error' => 'Purchase not found']);
                break;
            }
            $itemsStmt = $pdo->prepare('SELECT * FROM purchase_items WHERE purchase_id = ?');
            $itemsStmt->execute([$purchaseId]);
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
            $purchase['items'] = $items;
            echo json_encode($purchase);
        } else {
            // Listado simple de compras
            $stmt = $pdo->query('SELECT * FROM purchases ORDER BY id DESC');
            $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($purchases);
        }
        break;
    case 'POST':
        // Crear una nueva compra
        requireRole('admin');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['supplier_id']) || !is_array($data['items'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Datos de compra incompletos']);
            break;
        }
        // Campos
        $supplierId = intval($data['supplier_id']);
        $date = isset($data['date']) ? $data['date'] : date('Y-m-d');
        $considerVat = !empty($data['consider_vat']) ? 1 : 0;
        // Normalize payment method to uppercase for consistent handling
        $payment = strtoupper(trim($data['payment_method'] ?? 'PENDIENTE'));
        $folio = isset($data['folio']) ? trim((string)$data['folio']) : '';
        $cashRegisterId = isset($data['cash_register_id']) && $data['cash_register_id'] !== '' ? intval($data['cash_register_id']) : null;
        $bankOperationNumber = isset($data['bank_operation_number']) ? trim((string)$data['bank_operation_number']) : '';
        $receipt = $data['receipt_path'] ?? null;

        // If payment method is CUENTA (credit), force cash/bank fields to null for safety
        if (strtoupper(trim($data['payment_method'] ?? '')) === 'CUENTA') {
            $cashRegisterId = null;
            $bankOperationNumber = '';
        }


        $createdBy = isset($data['created_by_user_id']) ? intval($data['created_by_user_id']) : null;
        $receivedBy = isset($data['received_by_user_id']) ? intval($data['received_by_user_id']) : null;
        $notes = $data['notes'] ?? null;
        $items = $data['items'];

        // Asegurar columnas snapshot (no rompe si ya existen)
        ensurePurchaseItemSnapshotColumns($pdo);

        $pdo->beginTransaction();
        try {
            // Insertar compra
	            // Nota: las columnas folio/cash_register_id/bank_operation_number se crean por bootstrap si no existen.
	            $stmt = $pdo->prepare('INSERT INTO purchases (supplier_id, date, consider_vat, payment_method, cash_register_id, bank_operation_number, folio, receipt_path, created_by_user_id, received_by_user_id, notes, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())');
	            $stmt->execute([$supplierId, $date, $considerVat, $payment, $cashRegisterId, ($bankOperationNumber !== '' ? $bankOperationNumber : null), ($folio !== '' ? $folio : null), $receipt, $createdBy, $receivedBy, $notes]);
            $purchaseId = $pdo->lastInsertId();



            $total = 0;
            foreach ($items as $item) {
                $prodId = intval($item['product_id']);
                $presentation = $item['presentation'] ?? 'PIEZA';
                $qty = floatval($item['quantity']);
                $unitPrice = floatval($item['unit_price']);

                // Snapshot de venta/margen en el momento de la compra
                $snapPublic = array_key_exists('public_price', $item) ? (is_null($item['public_price']) ? null : floatval($item['public_price'])) : null;
                $snapMargin = array_key_exists('profit_margin', $item) ? (is_null($item['profit_margin']) ? null : floatval($item['profit_margin'])) : null;

                // Si el frontend no envía public_price, usar el vigente del producto
                if ($snapPublic === null) {
                    $stmtPrice = $pdo->prepare('SELECT public_price, profit_margin FROM products WHERE id = ?');
                    $stmtPrice->execute([$prodId]);
                    $prodRow = $stmtPrice->fetch(PDO::FETCH_ASSOC) ?: [];
                    $snapPublic = isset($prodRow['public_price']) ? floatval($prodRow['public_price']) : null;
                    if ($snapMargin === null && isset($prodRow['profit_margin'])) {
                        $snapMargin = floatval($prodRow['profit_margin']);
                    }
                }

                // Insertar item (incluyendo snapshot)
                $itemStmt = $pdo->prepare('INSERT INTO purchase_items (purchase_id, product_id, presentation, quantity, unit_price, public_price, profit_margin) VALUES (?,?,?,?,?,?,?)');
                $itemStmt->execute([$purchaseId, $prodId, $presentation, $qty, $unitPrice, $snapPublic, $snapMargin]);
                $total += $qty * $unitPrice;
                // Actualizar stock del producto
                $upd = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
                $upd->execute([$qty, $prodId]);

                /*
                 * Registrar en price_histories: cada entrada de inventario debe
                 * reflejar el precio pagado por el producto en ese momento. Al
                 * insertar también capturamos el precio público vigente para
                 * disponer de una referencia al margen de ganancia. Si no se
                 * encuentra precio de venta, se almacena NULL.
                 */
                try {
                    // Insertar registro de historial con snapshot (no precio actual)
                    $phStmt = $pdo->prepare('INSERT INTO price_histories (product_id, purchase_price, public_price) VALUES (?,?,?)');
                    $phStmt->execute([$prodId, $unitPrice, $snapPublic]);

                    // Actualizar precio de compra, fecha y (opcional) precio de venta/margen vigentes
                    if ($snapPublic !== null) {
                        $updPrice = $pdo->prepare('UPDATE products SET purchase_price = ?, last_purchase = ?, public_price = COALESCE(?, public_price), profit_margin = COALESCE(?, profit_margin) WHERE id = ?');
                        $updPrice->execute([$unitPrice, $date, $snapPublic, $snapMargin, $prodId]);
                    } else {
                        $updPrice = $pdo->prepare('UPDATE products SET purchase_price = ?, last_purchase = ? WHERE id = ?');
                        $updPrice->execute([$unitPrice, $date, $prodId]);
                    }
                } catch (PDOException $e) {
                    // Si hay error, no interrumpir el flujo principal; se loguea en error_log
                    error_log('Error updating price history: ' . $e->getMessage());
                }
            }
            // Registrar movimiento contable (SALIDA) — sólo si es pago inmediato
            $origin = 'PENDIENTE';
            if ($payment === 'EFECTIVO') {
                $origin = 'CAJA';
            } elseif ($payment === 'TRANSFERENCIA') {
                $origin = 'BANCO';
            }
            // Si la compra queda en cuenta (PENDIENTE) no creamos movimiento contable
            if ($origin !== 'PENDIENTE' && $cashRegisterId) {
                $mov = $pdo->prepare('INSERT INTO account_moves (type, origin, cash_register_id, reference, amount, user_id, link_type, link_id, note) VALUES (?,?,?,?,?,?,?,?,?)');
                $note = '';
                if (!empty($folio)) $note .= 'Folio: ' . $folio . ' | ';
                if (!empty($bankOperationNumber)) $note .= 'Op: ' . $bankOperationNumber . ' | ';
                $note = trim($note);
                $mov->execute(['SALIDA', $origin, $cashRegisterId, 'COMPRA', -1 * abs((float)$total), $createdBy, 'COMPRA', $purchaseId, $note]);
            }
            // Generar PDF de entrada
            $pdf = new FPDF('P','mm','A4');
            $pdf->AddPage();
            $pdf->SetFont('Arial','B',16);
            $pdf->Cell(0,10,'Reporte de Entrada',0,1,'C');
            $pdf->SetFont('Arial','',12);
            $pdf->Cell(0,8,'Proveedor: ' . $supplierId,0,1);
            $pdf->Cell(0,8,'Fecha: ' . $date,0,1);
            $pdf->Cell(0,8,'Forma de pago: ' . $payment,0,1);
            $pdf->Ln(4);
            // Tabla de items
            $pdf->SetFont('Arial','B',10);
            $pdf->Cell(60,7,'Producto',1);
            $pdf->Cell(30,7,'Presentacion',1);
            $pdf->Cell(30,7,'Cantidad',1);
            $pdf->Cell(30,7,'P.Unit',1);
            $pdf->Cell(30,7,'Importe',1);
            $pdf->Ln();
            $pdf->SetFont('Arial','',10);
            foreach ($items as $it) {
                $importe = floatval($it['quantity']) * floatval($it['unit_price']);
                $pdf->Cell(60,6,$it['product_id'],1);
                $pdf->Cell(30,6,$it['presentation'] ?? 'PIEZA',1);
                $pdf->Cell(30,6,$it['quantity'],1,0,'R');
                $pdf->Cell(30,6,number_format($it['unit_price'],2),1,0,'R');
                $pdf->Cell(30,6,number_format($importe,2),1,0,'R');
                $pdf->Ln();
            }
            $pdf->SetFont('Arial','B',10);
            $pdf->Cell(150,7,'TOTAL',1);
            $pdf->Cell(30,7,number_format($total,2),1,0,'R');
            $pdf->Ln();
            // Guardar PDF
            $uploadDir = realpath(__DIR__ . '/../uploads');
            if (!$uploadDir) {
                $uploadDir = __DIR__ . '/../uploads';
                @mkdir($uploadDir,0775,true);
            }
            $pdfName = 'entrada_' . $purchaseId . '.pdf';
            $pdfPath = $uploadDir . '/' . $pdfName;
            $pdf->Output('F', $pdfPath);

            // Create payable if payment method is 'CUENTA' (on credit)
            if (strtoupper($payment) === 'CUENTA') {
                // Get supplier name (use column 'name')
                $supplierStmt = $pdo->prepare('SELECT name FROM suppliers WHERE id = ?');
                $supplierStmt->execute([$supplierId]);
                $supplierRow = $supplierStmt->fetch(PDO::FETCH_ASSOC);
                $supplierName = $supplierRow ? $supplierRow['name'] : 'Proveedor sin nombre';

                // Get current user (use standard helper to avoid wrong session key)
                $user = currentUser();
                $userId = $user ? intval($user['id']) : 1;

                // Insert payable
                $payableSql = 'INSERT INTO payables (purchase_id, supplier_id, supplier_name, user_id, total_amount, paid_amount, status, created_at) VALUES (?,?,?,?,?,?,?,NOW())';
                $payableStmt = $pdo->prepare($payableSql);
                $payableStmt->execute([
                    $purchaseId,
                    $supplierId,
                    $supplierName,
                    $userId,
                    $total,
                    0,
                    'PENDIENTE'
                ]);
            }

            // Commit
            $pdo->commit();
            echo json_encode(['success' => true, 'id' => $purchaseId, 'total' => $total]);
        } catch (PDOException $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Error creating purchase: ' . $e->getMessage()]);
        }
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}