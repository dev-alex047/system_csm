<?php
// Handle sales (tickets) for thermal printer
header('Content-Type: application/json');
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../lib/fpdf.php';

requireLogin();

$pdo  = getPDO();
$user = currentUser();

// Soporte GET para obtener una venta por id
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $raw = trim($_GET['id']);
    // Try numeric id first, otherwise search by ticket_barcode
    if (ctype_digit($raw)) {
        $saleId = intval($raw);
        $stmt = $pdo->prepare('SELECT * FROM sales WHERE id = ?');
        $stmt->execute([$saleId]);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare('SELECT * FROM sales WHERE ticket_barcode = ? LIMIT 1');
        $stmt->execute([$raw]);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$sale) {
        http_response_code(404);
        echo json_encode(['error' => 'Sale not found']);
        exit;
    }
    $saleId = (int)$sale['id'];
    $itemsStmt = $pdo->prepare('SELECT si.*, p.name AS product_name, p.unit AS product_unit FROM sale_items si LEFT JOIN products p ON p.id = si.product_id WHERE si.sale_id = ?');
    $itemsStmt->execute([$saleId]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['sale' => $sale, 'items' => $items]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Leer la solicitud JSON
$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['items']) || !is_array($data['items'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$items          = $data['items'];
$clientName     = $data['client_name']     ?? '';
$clientAddress  = $data['client_address']  ?? '';
$clientPhone    = $data['client_phone']    ?? '';
$paymentMethod  = $data['payment_method']  ?? 'Efectivo';
$cashRegisterId = $data['cash_register_id']?? null;
$bankReference  = $data['bank_reference']  ?? null;
$cashReceived   = isset($data['cash_received']) ? floatval($data['cash_received']) : null;
$applyIva       = $data['apply_iva'] ?? false; // Verificar si el IVA fue activado en el frontend

$pdo->beginTransaction();
try {
    $subtotal   = 0;
    $ticketRows = [];

    // Procesar cada artículo de la venta
    foreach ($items as $item) {
        $productId = $item['product_id'] ?? null;
        $quantity  = floatval($item['quantity'] ?? 0);
        if (!$productId || $quantity <= 0) continue;

        // Bloquear la fila del producto para evitar condiciones de carrera
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? FOR UPDATE');
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) continue;

        // Precio unitario (permite que un administrador lo modifique)
        $price = floatval($product['public_price']);
        if (isset($item['price']) && is_numeric($item['price']) && strtoupper($user['role']) === 'ADMIN') {
            $price = floatval($item['price']);
        }

        $amount   = $price * $quantity;
        $subtotal += $amount;

        // Disminuir inventario
        $stmt2 = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
        $stmt2->execute([$quantity, $productId]);

        $ticketRows[] = [
            'product_id'  => $productId,
            'quantity'    => $quantity,
            'price'       => $price,
            'total'       => $amount,
            'description' => $product['name'],
            'unit'        => $product['unit'] ?? '',
        ];
    }

    if (empty($ticketRows)) {
        throw new Exception('No valid items');
    }

    // Solo aplicar IVA si está habilitado
    $iva = 0;
    if ($applyIva) {
        $iva = $subtotal * 0.16; // Aplicar el 16% de IVA
    }

    $total = $subtotal + $iva;

    // Calcular cambio en caso de pago en efectivo
    $change = null;
    if (strtolower($paymentMethod) === 'efectivo' && $cashReceived !== null) {
        $change = round($cashReceived - $total, 2);
        if ($change < 0) $change = 0;
    }

    // Monto pendiente para ventas “a cuenta”
    $pendingAmount = 0;
    if (strtolower($paymentMethod) === 'cuenta') {
        $pendingAmount = $total;
    }

    // Registrar la venta en la base de datos
    $insertSale = $pdo->prepare('INSERT INTO sales (user_id, client_name, client_address, client_phone, payment_method, bank_reference, cash_register_id, pending_amount, total_amount, ticket_barcode, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW())');
    $insertSale->execute([
        $user['id'],
        $clientName ?: null,
        $clientAddress ?: null,
        $clientPhone ?: null,
        $paymentMethod,
        $bankReference ?: null,
        $cashRegisterId ?: null,
        $pendingAmount,
        $total,
        ''  // Folio temporal (se actualiza después)
    ]);
    $saleId = $pdo->lastInsertId();

    // Registrar cada producto vendido
    $insertItem = $pdo->prepare('INSERT INTO sale_items (sale_id, product_id, quantity, price, total) VALUES (?,?,?,?,?)');
    foreach ($ticketRows as $row) {
        $insertItem->execute([$saleId, $row['product_id'], $row['quantity'], $row['price'], $row['total']]);
    }

    // Crear folio progresivo con prefijo dependiendo del método de pago
    $prefix = '';
    switch (strtolower($paymentMethod)) {
        case 'efectivo':
            $prefix = 'EFEC';
            break;
        case 'transferencia':
            $prefix = 'TRANS';
            break;
        case 'cuenta':
            $prefix = 'CUEN';
            break;
        default:
            $prefix = strtoupper(substr($paymentMethod, 0, 4));
            break;
    }
    $ticketCode = sprintf('%s%04d', $prefix, $saleId);

    // Actualizar la venta con el folio generado
    $pdo->prepare('UPDATE sales SET ticket_barcode = ? WHERE id = ?')->execute([$ticketCode, $saleId]);

    // Registrar movimiento contable (ENTRADA)
    $pm = strtolower($paymentMethod);
    $moveType = 'ENTRADA';
    $origin = 'CAJA';
    $status = 'PAGADO';
    $crId = $cashRegisterId ?: null;
    if ($pm === 'transferencia') {
        $origin = 'BANCO';
    } elseif ($pm === 'cuenta') {
        $origin = 'CUENTA';
        $status = 'PENDIENTE';
        $crId = null;
    }
    $noteMove = $clientName ? ('CLIENTE: ' . $clientName) : '';
    $stmtMove = $pdo->prepare('INSERT INTO account_moves (type, origin, cash_register_id, reference, amount, user_id, link_type, link_id, note, status) VALUES (?,?,?,?,?,?,?,?,?,?)');
    $stmtMove->execute([$moveType, $origin, $crId, 'VENTA', $total, $user['id'], 'VENTA', $saleId, $noteMove, $status]);

    // Create receivable if payment method is 'cuenta' (on credit)
    if (strtolower($paymentMethod) === 'cuenta') {
        $recvSql = 'INSERT INTO receivables (sale_id, client_name, user_id, total_amount, paid_amount, status, created_at) VALUES (?,?,?,?,?,?,NOW())';
        $recvStmt = $pdo->prepare($recvSql);
        $recvStmt->execute([
            $saleId,
            $clientName ?: 'Cliente sin nombre',
            $user['id'],
            $total,
            0,
            'PENDIENTE'
        ]);
    }

    // Finalizar transacción
    $pdo->commit();

    // Leer configuración de la empresa
    $settingsPath = __DIR__ . '/../config/customization.json';
    $settings = file_exists($settingsPath) ? json_decode(file_get_contents($settingsPath), true) : [];
    $companyName   = $settings['company_name'] ?? '';
    $rfc           = $settings['rfc'] ?? '';
    $contactLine   = '';
    if (!empty($settings['email'])) $contactLine .= $settings['email'];
    if (!empty($settings['phone'])) $contactLine .= ($contactLine ? '   ' : '') . $settings['phone'];
    $footerAddress = $settings['footer_address'] ?? '';

    // Crear el ticket térmico (80 mm de ancho)
    $pdf = new FPDF('P','mm', array(80,200));
    $pdf->AddPage();
    $pdf->SetMargins(5,5,5);

    // Colocar el logo (si existe) y ajustarlo para que no se superponga al texto
    $logoHeight = 0;
    if (!empty($settings['logo_path'])) {
        $logoFile = __DIR__ . '/../' . $settings['logo_path'];
        if (file_exists($logoFile)) {
            $logoWidth  = 15;  // logo más pequeño
            $logoHeight = 15;
            $pdf->Image($logoFile, 33, 5, $logoWidth);
        }
    }

    // Imprimir encabezado después del logo para evitar superposición
    $headerY = 5 + $logoHeight + 2; // 2 mm de separación
    $pdf->SetXY(5, $headerY);
    $pdf->SetFont('Arial','B',10);
    $pdf->SetTextColor(0);
    $pdf->Cell(0,5, utf8_decode(strtoupper($companyName)), 0, 1, 'C');
    $pdf->SetFont('Arial','',8);
    if ($rfc) $pdf->Cell(0,4, utf8_decode('RFC: ' . $rfc), 0, 1, 'C');
    if ($contactLine) {
        $pdf->MultiCell(0,3, utf8_decode($contactLine), 0, 'C');
    }
    $pdf->Ln(2);

    // Datos del ticket
    $pdf->SetFont('Arial','',7);
    $pdf->Cell(0,4,'Folio: '.$ticketCode,0,1);
    $pdf->Cell(0,4,'Fecha: '.date('Y-m-d H:i:s'),0,1);
    if ($clientName)    $pdf->Cell(0,4,'Cliente: '.utf8_decode($clientName),0,1);
    if ($clientAddress) $pdf->Cell(0,4,utf8_decode('Dirección: ').utf8_decode($clientAddress),0,1);
    if ($clientPhone)   $pdf->Cell(0,4,utf8_decode('Teléfono: ').$clientPhone,0,1);
    $pdf->Cell(0,4,'Vendedor: '.$user['username'],0,1);
    // Método de pago con acento corregido
    $pdf->Cell(0,4, utf8_decode('Método de pago: '.$paymentMethod),0,1);
    if ($bankReference) $pdf->Cell(0,4,'Referencia: '.$bankReference,0,1);
    if ($pendingAmount > 0) $pdf->Cell(0,4,'Pendiente: $'.number_format($pendingAmount,2),0,1);
    $pdf->Ln(2);

    // Encabezado de la tabla ajustado (20 + 30 + 10 + 10 = 70 mm)
    $pdf->SetFont('Arial', 'B', 7);
    // Encabezado con nueva columna de empaque (12 + 30 + 8 + 10 + 10 = 70 mm)
    $pdf->Cell(12,5, utf8_decode('CANT.'),1,0,'C');
    $pdf->Cell(30,5, utf8_decode('DESCRIPCIÓN'),1,0,'C');
    $pdf->Cell(8,5,  utf8_decode('EMP.'),1,0,'C');
    $pdf->Cell(10,5, utf8_decode('P. U.'),1,0,'C');
    $pdf->Cell(10,5, utf8_decode('IMP.'),1,1,'C');

    // Filas de productos (con salto de línea en la descripción)
    $pdf->SetFont('Arial','',5);

    // Helper para partir texto en líneas sin romper palabras (FPDF no tiene wrap automático en Cell)
    $splitText = function($pdf, $text, $maxWidth) {
        $text = trim((string)$text);
        if ($text === '') return [''];
        $words = preg_split('/\s+/', $text);
        $lines = [];
        $line = '';
        foreach ($words as $w) {
            $test = $line === '' ? $w : ($line . ' ' . $w);
            if ($pdf->GetStringWidth($test) <= $maxWidth) {
                $line = $test;
            } else {
                if ($line !== '') $lines[] = $line;
                // palabra muy larga: partir por caracteres
                if ($pdf->GetStringWidth($w) > $maxWidth) {
                    $chunk = '';
                    $chars = preg_split('//u', $w, -1, PREG_SPLIT_NO_EMPTY);
                    foreach ($chars as $ch) {
                        $t2 = $chunk . $ch;
                        if ($pdf->GetStringWidth($t2) <= $maxWidth) {
                            $chunk = $t2;
                        } else {
                            if ($chunk !== '') $lines[] = $chunk;
                            $chunk = $ch;
                        }
                    }
                    $line = $chunk;
                } else {
                    $line = $w;
                }
            }
        }
        if ($line !== '') $lines[] = $line;
        return $lines;
    };

    foreach ($ticketRows as $r) {
        $qtyW=12; $descW=30; $unitW=8; $puW=10; $impW=10;
        $lineH = 4;

        $descLines = $splitText($pdf, utf8_decode($r['description']), $descW-1);
        $unitText  = utf8_decode((string)($r['unit'] ?? ''));
        $unitLines = $splitText($pdf, $unitText, $unitW-1);

        $nLines = max(count($descLines), count($unitLines), 1);
        $rowH = $nLines * $lineH;

        $x = $pdf->GetX();
        $y = $pdf->GetY();

        // Borde completo de la fila
        $pdf->Rect($x, $y, $qtyW, $rowH);
        $pdf->Rect($x+$qtyW, $y, $descW, $rowH);
        $pdf->Rect($x+$qtyW+$descW, $y, $unitW, $rowH);
        $pdf->Rect($x+$qtyW+$descW+$unitW, $y, $puW, $rowH);
        $pdf->Rect($x+$qtyW+$descW+$unitW+$puW, $y, $impW, $rowH);

        // Cantidad centrada
        $pdf->SetXY($x, $y);
        $pdf->Cell($qtyW, $rowH, number_format($r['quantity'],2), 0, 0, 'C');

        // Descripción multilinea
        $pdf->SetXY($x+$qtyW, $y);
        for ($i=0; $i<count($descLines); $i++) {
            $pdf->SetX($x+$qtyW);
            $pdf->Cell($descW, $lineH, $descLines[$i], 0, 2, 'L');
        }

        // Empaque multilinea
        $pdf->SetXY($x+$qtyW+$descW, $y);
        for ($i=0; $i<count($unitLines); $i++) {
            $pdf->SetX($x+$qtyW+$descW);
            $pdf->Cell($unitW, $lineH, $unitLines[$i], 0, 2, 'C');
        }

        // P.U y IMP alineados abajo a la derecha
        $pdf->SetXY($x+$qtyW+$descW+$unitW, $y);
        $pdf->Cell($puW, $rowH, '$'.number_format($r['price'],2), 0, 0, 'R');

        $pdf->SetXY($x+$qtyW+$descW+$unitW+$puW, $y);
        $pdf->Cell($impW, $rowH, '$'.number_format($r['total'],2), 0, 0, 'R');

        // Siguiente línea
        $pdf->SetXY($x, $y + $rowH);
    }

    // Totales (55 mm + 15 mm = 70 mm)
    $pdf->SetFont('Arial','B',7);
    $pdf->Cell(55,5,'SUBTOTAL',1,0,'R');
    $pdf->Cell(15,5,'$'.number_format($subtotal,2),1,1,'R');
    $pdf->Cell(55,5, utf8_decode('IVA 16%'),1,0,'R');
    $pdf->Cell(15,5,'$'.number_format($iva,2),1,1,'R');
    $pdf->Cell(55,5,'TOTAL',1,0,'R');
    $pdf->Cell(15,5,'$'.number_format($total,2),1,1,'R');
    $pdf->Ln(3);

    // Mostrar cambio si aplica
    if ($change !== null) {
        $pdf->SetFont('Arial','',7);
        $pdf->Cell(55,4, utf8_decode('Cambio'),0,0,'R');
        $pdf->Cell(15,4,'$'.number_format($change,2),0,1,'R');
        $pdf->Ln(2);
    }

    // Mensaje de agradecimiento
    $pdf->SetFont('Arial','I',7);
    $pdf->MultiCell(0,3, utf8_decode('Gracias por su preferencia.'));
    if ($footerAddress) {
        $pdf->Ln(2);
        $pdf->SetFont('Arial','',6);
        $pdf->MultiCell(0,3, utf8_decode($footerAddress),0,'C');
    }

    // Guardar archivo PDF
    $ticketsDir = __DIR__ . '/../uploads/tickets';
    if (!is_dir($ticketsDir)) mkdir($ticketsDir, 0777, true);
    $fileName = 'ticket_'.sprintf('%04d',$saleId).'.pdf';
    $filePath = $ticketsDir.'/'.$fileName;
    $pdf->Output('F', $filePath);

    // Enviar respuesta al cliente
    $response = [
        'success' => true,
        'sale_id' => $saleId,
        'ticket'  => 'uploads/tickets/'.$fileName
    ];
    if ($change !== null) $response['change'] = $change;
    echo json_encode($response);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
