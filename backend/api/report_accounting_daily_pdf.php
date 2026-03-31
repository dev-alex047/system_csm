<?php
/**
 * report_accounting_daily_pdf.php
 * Genera un PDF con resumen diario de contabilidad:
 * - Entradas/Salidas (cuentas)
 * - Saldos iniciales/finales por caja
 * - CxC y CxP como 2 "cajas" (filas)
 * - Concentrado de productos vendidos (con precio unitario)
 * - Tabla de movimientos del día (con wrap, sin invadir celdas)
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE);

// Asegura respuesta JSON incluso si ocurre un error fatal (evita "Unexpected end of JSON input")
register_shutdown_function(function () {
  $err = error_get_last();
  if (!$err) return;

  $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
  if (!in_array($err['type'], $fatalTypes, true)) return;

  if (headers_sent()) return;

  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => 'Fatal error: ' . ($err['message'] ?? 'Error desconocido'),
    'file' => $err['file'] ?? null,
    'line' => $err['line'] ?? null
  ]);
});

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../lib/fpdf.php';

requireRole('admin');
$pdo = getPDO();
$user = currentUser();

// ---------- helpers ----------
function pdf_txt($s) {
  if ($s === null) return '';
  if (function_exists('iconv')) {
    $out = @iconv('UTF-8', 'Windows-1252//IGNORE', (string)$s);
    return ($out !== false) ? $out : (string)$s;
  }
  return (string)$s;
}

function hasColumn(PDO $pdo, string $table, string $column): bool {
  try {
    $stmt = $pdo->prepare("
      SELECT COUNT(*) AS c
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
        AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $column]);
    return ((int)$stmt->fetchColumn()) > 0;
  } catch (Throwable $e) {
    return false;
  }
}

function extractPartyFromText($txt) {
  $t = trim((string)$txt);
  if ($t === '') return '';
  if (preg_match('/CLIENTE\s*:\s*([^\n\r,;]+)/i', $t, $m) && !empty($m[1])) return trim($m[1]);
  if (preg_match('/PROVEEDOR\s*:\s*([^\n\r,;]+)/i', $t, $m) && !empty($m[1])) return trim($m[1]);
  return '';
}

class PDFAcc extends FPDF {
  public $companyName = '';
  public $companySubtitle = '';
  public $logoPath = '';
  public $title = '';

  public $widths = [];
  public $aligns = [];

  function Header() {
    $top = 6;

    if ($this->logoPath && file_exists($this->logoPath)) {
      $this->Image($this->logoPath, 10, $top, 30);
    }

    $this->SetFont('Arial','B',14);
    $this->Cell(0,6, pdf_txt($this->companyName), 0,1,'C');

    $this->SetFont('Arial','',9);
    if ($this->companySubtitle) {
      $this->Cell(0,5, pdf_txt($this->companySubtitle), 0,1,'C');
    }

    // Doble salto para que el título NO se encime con el encabezado
    $this->Ln(12);

    $this->SetFont('Arial','B',11);
    $this->Cell(0,6, pdf_txt($this->title), 0,1,'L');

    // Separación
    $this->Ln(3);
  }

  function Footer() {
    $this->SetY(-18);
    $this->SetFont('Arial','',7);
    $this->Cell(0,4, pdf_txt('Reporte generado: ' . date('Y-m-d H:i:s')), 0,1,'R');
    $this->Cell(0,4, pdf_txt('Página ' . $this->PageNo()), 0,0,'C');
  }

  function SetWidths($w) { $this->widths = $w; }
  function SetAligns($a) { $this->aligns = $a; }

  function NbLines($w, $txt) {
    $cw = &$this->CurrentFont['cw'];
    if ($w==0) $w = $this->w - $this->rMargin - $this->x;
    $wmax = ($w - 2*$this->cMargin) * 1000 / $this->FontSize;
    $s = str_replace("\r",'',(string)$txt);
    $nb = strlen($s);
    if ($nb>0 && $s[$nb-1]=="\n") $nb--;
    $sep = -1;
    $i = 0;
    $j = 0;
    $l = 0;
    $nl = 1;
    while ($i < $nb) {
      $c = $s[$i];
      if ($c=="\n") { $i++; $sep=-1; $j=$i; $l=0; $nl++; continue; }
      if ($c==' ') $sep=$i;
      $l += $cw[$c] ?? 0;
      if ($l > $wmax) {
        if ($sep==-1) {
          if ($i==$j) $i++;
        } else {
          $i = $sep+1;
        }
        $sep=-1; $j=$i; $l=0; $nl++;
      } else {
        $i++;
      }
    }
    return $nl;
  }

  function Row($data, $lineHeight = 5, $borders = true) {
    $nb = 0;
    for ($i=0; $i<count($data); $i++) {
      $w = $this->widths[$i] ?? 0;
      $nb = max($nb, $this->NbLines($w, $data[$i]));
    }
    $h = $lineHeight * $nb;

    if ($this->GetY() + $h > $this->PageBreakTrigger) $this->AddPage($this->CurOrientation);

    for ($i=0; $i<count($data); $i++) {
      $w = $this->widths[$i] ?? 0;
      $a = $this->aligns[$i] ?? 'L';
      $x = $this->GetX();
      $y = $this->GetY();

      if ($borders) $this->Rect($x, $y, $w, $h);

      $this->MultiCell($w, $lineHeight, $data[$i], 0, $a);

      $this->SetXY($x + $w, $y);
    }
    $this->Ln($h);
  }
}

// ---------- input ----------
$in = json_decode(file_get_contents('php://input'), true) ?: [];
$date = (isset($in['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $in['date'])) ? $in['date'] : date('Y-m-d');

try {
  // ===== Movimientos (entries/exits) del día =====
  $stmt = $pdo->prepare("
    SELECT am.*, u.username AS user_name, cr.name AS cash_name
    FROM account_moves am
    LEFT JOIN users u ON u.id = am.user_id
    LEFT JOIN cash_registers cr ON cr.id = am.cash_register_id
    WHERE DATE(am.created_at) = ?
    ORDER BY am.created_at ASC
  ");
  $stmt->execute([$date]);
  $moves = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $summaryMoves = ['inflows' => 0, 'outflows' => 0];
  foreach ($moves as $m) {
    $amt = floatval($m['amount'] ?? 0);
    $type = strtoupper((string)($m['type'] ?? $m['move_type'] ?? 'ENTRADA'));
    if ($type === 'ENTRADA') $summaryMoves['inflows'] += abs($amt);
    else $summaryMoves['outflows'] += abs($amt);
  }

  // ===== Productos vendidos (con unidad) =====
  $productUnitCol = hasColumn($pdo, 'products', 'unit') ? 'p.unit' : "'' AS unit";
  $stmt = $pdo->prepare("
    SELECT
      si.product_id,
      p.code,
      p.name,
      $productUnitCol,
      SUM(si.quantity) AS qty,
      SUM(si.total) AS total
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    JOIN products p ON p.id = si.product_id
    WHERE DATE(s.created_at) = ?
    GROUP BY si.product_id, p.code, p.name" . (hasColumn($pdo,'products','unit') ? ", p.unit" : "") . "
    ORDER BY qty DESC
    LIMIT 100
  ");
  $stmt->execute([$date]);
  $productsSold = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // ===== Saldos por Caja =====
  $saldoInicialCol = hasColumn($pdo, 'cash_registers', 'saldo_inicial') ? 'saldo_inicial' :
                     (hasColumn($pdo, 'cash_registers', 'current_balance') ? 'current_balance' : '0');

  $cashStmt = $pdo->query("SELECT id, name, type, $saldoInicialCol AS saldo_inicial FROM cash_registers WHERE is_active = 1 ORDER BY id ASC");
  $cashRegisters = $cashStmt->fetchAll(PDO::FETCH_ASSOC);

  $cashSummary = [];
  foreach ($cashRegisters as $c) {
    $cid = intval($c['id']);
    $saldoInicial = floatval($c['saldo_inicial'] ?? 0);

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM account_moves WHERE cash_register_id = ? AND DATE(created_at) < ?");
    $stmt->execute([$cid, $date]);
    $before = floatval($stmt->fetchColumn() ?: 0);

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM account_moves WHERE cash_register_id = ? AND DATE(created_at) = ?");
    $stmt->execute([$cid, $date]);
    $dayTotal = floatval($stmt->fetchColumn() ?: 0);

    $startBal = $saldoInicial + $before;
    $endBal = $startBal + $dayTotal;

    $cashSummary[] = [
      'id' => $cid,
      'name' => $c['name'],
      'type' => $c['type'] ?? '',
      'start' => $startBal,
      'day_total' => $dayTotal,
      'end' => $endBal
    ];
  }

  // ===== CxC / CxP (como 2 "cajas") =====
  $arAp = ['total_ar' => 0.0, 'total_ap' => 0.0];
  foreach ($cashRegisters as $c) {
    $nm = strtolower((string)($c['name'] ?? ''));
    $type = strtolower((string)($c['type'] ?? ''));
    if (preg_match('/\b(cx c|cxc|por cobrar|cobrar|cuentas por cobrar)\b/i', $nm) || $type === 'ar') {
      $arAp['total_ar'] += floatval($c['saldo_inicial'] ?? 0);
    }
    if (preg_match('/\b(cx p|cxp|por pagar|pagar|cuentas por pagar)\b/i', $nm) || $type === 'ap') {
      $arAp['total_ap'] += floatval($c['saldo_inicial'] ?? 0);
    }
  }

  // ===== Log de reporte =====
  $pdo->prepare("
    CREATE TABLE IF NOT EXISTS reports (
      id INT AUTO_INCREMENT PRIMARY KEY,
      type VARCHAR(80),
      report_date DATE NOT NULL,
      filename VARCHAR(255) NOT NULL,
      generated_by INT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    )
  ")->execute();

  $ins = $pdo->prepare('INSERT INTO reports (type, report_date, generated_by, filename) VALUES (?,?,?,?)');
  $ins->execute(['accounting_daily', $date, $user['id'] ?? null, '']);
  $reportId = (int)$pdo->lastInsertId();

  // ===== PDF =====
  $settingsPath = __DIR__ . '/../config/customization.json';
  $settings = file_exists($settingsPath) ? json_decode(file_get_contents($settingsPath), true) : [];
  $companyName = $settings['company_name'] ?? 'SISTEMA CSM';
  $companySubtitle = $settings['company_subtitle'] ?? '';
  $logoPath = (!empty($settings['logo_path']) && file_exists(__DIR__ . '/../' . $settings['logo_path'])) ? __DIR__ . '/../' . $settings['logo_path'] : '';

  $pdf = new PDFAcc('P','mm','A4');
  $pdf->companyName = $companyName;
  $pdf->companySubtitle = $companySubtitle;
  $pdf->logoPath = $logoPath;
  $pdf->title = 'Reporte Diario de Contabilidad - ' . $date . '   (ID: ' . str_pad($reportId, 6, '0', STR_PAD_LEFT) . ')';
  $pdf->SetAutoPageBreak(true, 18);
  $pdf->AddPage();

  // ===== Resumen general (solo Entradas/Salidas) =====
  $pdf->SetFont('Arial','B',10);
  $pdf->Cell(0,6, pdf_txt('Resumen general'), 0,1);
  $pdf->Ln(2);

  $pdf->SetFont('Arial','',9);
  $pdf->Cell(60,5, pdf_txt('Entradas (cuentas):'), 0,0);
  $pdf->Cell(40,5, '$' . number_format($summaryMoves['inflows'],2), 0,1);
  $pdf->Cell(60,5, pdf_txt('Salidas (cuentas):'), 0,0);
  $pdf->Cell(40,5, '$' . number_format($summaryMoves['outflows'],2), 0,1);

  $pdf->Ln(6);

  // Mini gráfico (2 barras: Entradas / Salidas)
  $maxVal = max([$summaryMoves['inflows'], $summaryMoves['outflows'], 1]);
  $chartX = $pdf->GetX();
  $chartY = $pdf->GetY();
  $chartW = 160; $chartH = 30;
  $pdf->SetDrawColor(200,200,200);
  $pdf->Rect($chartX, $chartY, $chartW, $chartH);

  $labels = [
    'Entradas' => $summaryMoves['inflows'],
    'Salidas'  => $summaryMoves['outflows']
  ];
  $i = 0; $barGap = 8; $barWidth = ($chartW - ($barGap * (count($labels)+1))) / count($labels);

  foreach ($labels as $label => $val) {
    $barH = ($val / $maxVal) * ($chartH - 10);
    $bx = $chartX + $barGap + $i * ($barWidth + $barGap);
    $by = $chartY + $chartH - $barH - 6;

    $pdf->SetFillColor(60 + ($i*70), 120, 180 - ($i*30));
    $pdf->Rect($bx, $by, $barWidth, $barH, 'F');

    $pdf->SetXY($bx, $chartY + $chartH - 5);
    $pdf->SetFont('Arial','',7);
    $pdf->Cell($barWidth,4, pdf_txt($label),0,0,'C');

    $pdf->SetXY($bx, $by - 6);
    $pdf->Cell($barWidth,4,'$' . number_format($val,0),0,0,'C');

    $i++;
  }

  $pdf->SetY($chartY + $chartH + 8);
  $pdf->Ln(2);

  // ===== Saldos por Caja (CxC/CxP como filas) =====
  $pdf->SetFont('Arial','B',9);
  $pdf->Cell(0,6, pdf_txt('Saldos por Caja'), 0,1);

  $pdf->SetFont('Arial','B',8);
  $pdf->Cell(70,6, pdf_txt('Caja'),1,0,'C');
  $pdf->Cell(30,6, pdf_txt('Inicio'),1,0,'R');
  $pdf->Cell(30,6, pdf_txt('Mov. Día'),1,0,'R');
  $pdf->Cell(30,6, pdf_txt('Final'),1,1,'R');

  $pdf->SetFont('Arial','',8);
  foreach ($cashSummary as $c) {
    $pdf->Cell(70,6, pdf_txt($c['name']),1,0);
    $pdf->Cell(30,6,'$' . number_format($c['start'],2),1,0,'R');
    $pdf->Cell(30,6,'$' . number_format($c['day_total'],2),1,0,'R');
    $pdf->Cell(30,6,'$' . number_format($c['end'],2),1,1,'R');
  }

  // 2 "cajas" extra: CxC / CxP
  $pdf->Cell(70,6, pdf_txt('CUENTAS POR COBRAR'),1,0);
  $pdf->Cell(30,6,'$' . number_format(0,2),1,0,'R');
  $pdf->Cell(30,6,'$' . number_format($arAp['total_ar'],2),1,0,'R');
  $pdf->Cell(30,6,'$' . number_format($arAp['total_ar'],2),1,1,'R');

  $pdf->Cell(70,6, pdf_txt('CUENTAS POR PAGAR'),1,0);
  $pdf->Cell(30,6,'$' . number_format(0,2),1,0,'R');
  $pdf->Cell(30,6,'$' . number_format($arAp['total_ap'],2),1,0,'R');
  $pdf->Cell(30,6,'$' . number_format($arAp['total_ap'],2),1,1,'R');

  $pdf->Ln(10);

  // ===== Productos vendidos (concentrado) =====
  $pdf->SetFont('Arial','B',9);
  $pdf->Cell(0,6, pdf_txt('Productos vendidos (concentrado)'),0,1);

  $pdf->SetFont('Arial','B',7);
  $pdf->Cell(18,6, pdf_txt('Código'),1,0,'C');
  $pdf->Cell(72,6, pdf_txt('Producto'),1,0,'C');
  $pdf->Cell(16,6, pdf_txt('Unidad'),1,0,'C');
  $pdf->Cell(14,6, pdf_txt('Cant.'),1,0,'C');
  $pdf->Cell(18,6, pdf_txt('P.Unit'),1,0,'C');
  $pdf->Cell(22,6, pdf_txt('Total'),1,1,'C');

  $pdf->SetFont('Arial','',7);
  foreach ($productsSold as $ps) {
    $qty = floatval($ps['qty'] ?? 0);
    $tot = floatval($ps['total'] ?? 0);
    $unitPrice = ($qty > 0) ? ($tot / $qty) : 0;

    $pdf->Cell(18,6, pdf_txt($ps['code'] ?? ''),1,0);
    $pdf->Cell(72,6, pdf_txt(($ps['name'] ?? '')),1,0);
    $pdf->Cell(16,6, pdf_txt($ps['unit'] ?? ''),1,0,'C');
    $pdf->Cell(14,6, number_format($qty,2),1,0,'R');
    $pdf->Cell(18,6, '$' . number_format($unitPrice,2),1,0,'R');
    $pdf->Cell(22,6, '$' . number_format($tot,2),1,1,'R');
  }

  $pdf->Ln(8);

  // ===== Movimientos del día =====
  $pdf->SetFont('Arial','B',9);
  $pdf->Cell(0,6, pdf_txt('Movimientos del día'),0,1);

  // Encabezados (con bordes)
  $pdf->SetFont('Arial','B',7);
  $pdf->SetWidths([28, 16, 20, 28, 20, 58]); // fecha, tipo, caja, cliente, usuario, movimiento
  $pdf->SetAligns(['C','C','C','L','L','L']);

  $pdf->Row([
    pdf_txt('Fecha y hora'),
    pdf_txt('Tipo'),
    pdf_txt('Caja'),
    pdf_txt('Cliente'),
    pdf_txt('Usuario'),
    pdf_txt('Movimiento')
  ], 5, true);

  $pdf->SetFont('Arial','',7);

  $count = 0;
  foreach ($moves as $mv) {
    if ($count++ > 80) break;

    $created = (string)($mv['created_at'] ?? '');
    $dateTime = '';
    if ($created) {
      $ts = strtotime($created);
      $dateTime = $ts ? date('d/m/Y H:i:s', $ts) : $created;
    }

    $type = strtoupper((string)($mv['type'] ?? $mv['move_type'] ?? ''));
    $cash = trim((string)($mv['cash_name'] ?? ''));
    if ($cash === '') $cash = 'A CUENTA';

    // Cliente: busca en note y/o referencia
    $note = (string)($mv['note'] ?? '');
    $ref  = (string)($mv['reference'] ?? $mv['reference_code'] ?? '');
    $client = extractPartyFromText($note);
    if ($client === '') $client = extractPartyFromText($ref);

    $userName = (string)($mv['user_name'] ?? '');

    // Movimiento: referencia + nota (con wrap)
    $movementText = trim($ref);
    if ($movementText !== '' && $note !== '') $movementText .= ' - ';
    $movementText .= trim($note);

    $pdf->Row([
      pdf_txt($dateTime),
      pdf_txt($type),
      pdf_txt($cash),
      pdf_txt($client),
      pdf_txt($userName),
      pdf_txt($movementText),
    ], 5, true);
  }

  // Guardar PDF
  $dir = __DIR__ . '/../uploads/reports';
  if (!is_dir($dir)) mkdir($dir, 0777, true);

  $filename = sprintf('accounting_daily_%s_%06d.pdf', $date, $reportId);
  $path = $dir . '/' . $filename;
  $pdf->Output('F', $path);

  // Update report record with filename
  $pdo->prepare('UPDATE reports SET filename = ? WHERE id = ?')
      ->execute(['uploads/reports/' . $filename, $reportId]);

  echo json_encode(['ok' => true, 'report_id' => $reportId, 'file' => 'uploads/reports/' . $filename]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
