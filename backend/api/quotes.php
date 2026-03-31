<?php
// quotes.php – PDF de cotización corporativo (San Martín)
// POST JSON: { items:[{product_id, quantity, price_type?, custom_price?}], date, place, recipient, iva }

header('Content-Type: application/json');
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../lib/fpdf.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Method not allowed']);
  exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['items']) || !is_array($data['items'])) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid request body']);
  exit;
}

$pdo  = getPDO();
$user = currentUser();

// ===== Helpers =====
function u($s) { return utf8_decode((string)($s ?? '')); }
function money($n) { return '$' . number_format((float)$n, 2); }

function hexToRgb($hex) {
  $hex = trim((string)$hex);
  if ($hex === '') return [0, 128, 0];
  $hex = ltrim($hex, '#');
  if (strlen($hex) === 3) {
    $r = hexdec(str_repeat($hex[0], 2));
    $g = hexdec(str_repeat($hex[1], 2));
    $b = hexdec(str_repeat($hex[2], 2));
    return [$r, $g, $b];
  }
  if (strlen($hex) !== 6) return [0, 128, 0];
  return [hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2))];
}

// ===== Cargar customization.json =====
$settingsPath = __DIR__ . '/../config/customization.json';
$settings = file_exists($settingsPath) ? json_decode(file_get_contents($settingsPath), true) : [];

$companyName     = $settings['company_name'] ?? 'Empresa';
$companySubtitle = $settings['company_subtitle'] ?? '';
$tagline1        = $settings['tagline1'] ?? '';
$rfc             = $settings['rfc'] ?? '';
$contactLine     = $settings['contact_info'] ?? (($settings['email'] ?? '') . (($settings['phone'] ?? '') ? ', ' . ($settings['phone'] ?? '') : ''));
$footerAddress   = $settings['footer_address'] ?? '';

$primaryColorHex = $settings['primary_color'] ?? '#21a630';
$GREEN = hexToRgb($primaryColorHex);

$logoPath = '';
if (!empty($settings['logo_path'])) {
  $p = __DIR__ . '/../' . $settings['logo_path'];
  if (file_exists($p)) $logoPath = $p;
}

$rightImgPath = '';
if (!empty($settings['header_right_image_path'])) {
  $p = __DIR__ . '/../' . $settings['header_right_image_path'];
  if (file_exists($p)) $rightImgPath = $p;
}

// firma
$signaturePath = '';
if (!empty($settings['signature_path'])) {
  $p = __DIR__ . '/../' . $settings['signature_path'];
  if (file_exists($p)) $signaturePath = $p;
}
$signatureName = $settings['signature_name'] ?? 'C. HERMINIO PEREZ BERNABE';

// ===== Datos recibidos =====
$quoteDate      = trim((string)($data['date'] ?? ''));
$quotePlace     = trim((string)($data['place'] ?? ''));
$quoteRecipient = trim((string)($data['recipient'] ?? ''));
$includeIva     = isset($data['iva']) ? (bool)$data['iva'] : true;

// ===== Notas =====
$NOTES = [
  'NOTAS:',
  '1. El precio se actualiza al día de la compra y puede diferir del día en que se cotizó.',
  '2. El precio cotizado es valido hasta los 15 días posterior a su emisión.',
  '3. Forma de pago: Contado Anticipado.',
  '4. Entrega del Material a pie de camión.',
  '5. La descarga es efectuada por el cliente.'
];

// ===== Obtener partidas =====
$rows = [];
$subtotal = 0.0;

foreach ($data['items'] as $item) {
  $productId = $item['product_id'] ?? null;
  $quantity  = (float)($item['quantity'] ?? 0);
  if (!$productId || $quantity <= 0) continue;

  $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
  $stmt->execute([$productId]);
  $product = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$product) continue;

  if (isset($item['custom_price']) && $item['custom_price'] !== '' && $item['custom_price'] !== null) {
    $price = (float)$item['custom_price'];
  } else {
    $priceType = $item['price_type'] ?? 'public_price';
    if (($user['role'] ?? '') === 'ADMIN' || ($user['role'] ?? '') === 'admin') {
      $price = isset($product[$priceType]) ? (float)$product[$priceType] : (float)$product['public_price'];
    } else {
      $price = (float)$product['public_price'];
    }
  }

  $amount = $price * $quantity;
  $subtotal += $amount;

  $rows[] = [
    'description' => trim((string)($product['name'] ?? '')),
    'quantity'    => $quantity,
    'unit'        => (string)($product['unit'] ?? ''),
    'price'       => $price,
    'total'       => $amount,
  ];
}

if (!$rows) {
  http_response_code(400);
  echo json_encode(['error' => 'No valid items']);
  exit;
}

$iva   = $includeIva ? ($subtotal * 0.16) : 0.0;
$total = $subtotal + $iva;

// ===== PDF helper =====
class PDFQuote extends FPDF {
  public $widths = [];
  public $aligns = [];

  function SetWidths($w) { $this->widths = $w; }
  function SetAligns($a) { $this->aligns = $a; }

  function Row($data, $lineHeight = 5) {
    $nb = 0;
    for ($i=0; $i<count($data); $i++) {
      $nb = max($nb, $this->NbLines($this->widths[$i], $data[$i]));
    }
    $h = $lineHeight * $nb;
    $this->CheckPageBreak($h);

    for ($i=0; $i<count($data); $i++) {
      $w = $this->widths[$i];
      $a = $this->aligns[$i] ?? 'L';
      $x = $this->GetX();
      $y = $this->GetY();

      $this->Rect($x, $y, $w, $h);
      $this->MultiCell($w, $lineHeight, $data[$i], 0, $a);
      $this->SetXY($x + $w, $y);
    }
    $this->Ln($h);
  }

  function CheckPageBreak($h) {
    if ($this->GetY() + $h > $this->PageBreakTrigger) {
      $this->AddPage($this->CurOrientation);
    }
  }

  function NbLines($w, $txt) {
    $cw =& $this->CurrentFont['cw'];
    if ($w == 0) $w = $this->w - $this->rMargin - $this->x;
    $wmax = ($w - 2*$this->cMargin) * 1000 / $this->FontSize;
    $s = str_replace("\r", '', $txt);
    $nb = strlen($s);
    if ($nb > 0 && $s[$nb-1] == "\n") $nb--;
    $sep = -1; $i = 0; $j = 0; $l = 0; $nl = 1;
    while ($i < $nb) {
      $c = $s[$i];
      if ($c == "\n") { $i++; $sep=-1; $j=$i; $l=0; $nl++; continue; }
      if ($c == ' ') $sep = $i;
      $l += $cw[$c] ?? 0;
      if ($l > $wmax) {
        if ($sep == -1) { if ($i == $j) $i++; }
        else { $i = $sep + 1; }
        $sep=-1; $j=$i; $l=0; $nl++;
      } else $i++;
    }
    return $nl;
  }
}

$pdf = new PDFQuote('P', 'mm', 'A4');
$pdf->SetAutoPageBreak(true, 18);
$pdf->SetMargins(15, 12, 15);
$pdf->AddPage();

// ===== ENCABEZADO =====
$yTop = 10;

if ($logoPath) {
  $pdf->Image($logoPath, 15, $yTop, 22);
}
if ($rightImgPath) {
  $pdf->Image($rightImgPath, 210 - 15 - 35, $yTop - 2, 35);
}

$pdf->SetXY(15, $yTop);
$pdf->SetFont('Arial', 'B', 13);
$pdf->Cell(0, 6, u($companyName), 0, 1, 'C');

$pdf->SetFont('Arial', 'B', 9.5);
if ($companySubtitle) $pdf->Cell(0, 4.5, u($companySubtitle), 0, 1, 'C');
if ($tagline1)        $pdf->Cell(0, 4.5, u($tagline1), 0, 1, 'C');

$pdf->SetFont('Arial', 'B', 9.5);
if ($rfc) $pdf->Cell(0, 4.5, u('RFC: ' . $rfc), 0, 1, 'C');

$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(0, 0, 160);
if (trim($contactLine) !== '') $pdf->Cell(0, 4.5, u($contactLine), 0, 1, 'C');
$pdf->SetTextColor(0, 0, 0);

// Línea verde bajo encabezado
$pdf->SetDrawColor($GREEN[0], $GREEN[1], $GREEN[2]);
$pdf->SetLineWidth(0.4);
$pdf->Line(15, $pdf->GetY() + 2, 195, $pdf->GetY() + 2);
$pdf->Ln(10);

// ===== LUGAR Y FECHA (ALINEADO A LA DERECHA) =====
$pdf->SetFont('Arial', '', 9);
$line = '';
if ($quotePlace) $line .= mb_strtoupper($quotePlace);
if ($quotePlace && $quoteDate) $line .= ', ';
if ($quoteDate) $line .= $quoteDate;

$pdf->SetX(15);
$pdf->Cell(180, 5, u($line), 0, 1, 'R');
$pdf->Ln(3);

// ===== ASUNTO CENTRADO (SIN LÍNEA VERDE) =====
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetX(15);
$pdf->Cell(180, 6, u('ASUNTO: COTIZACIÓN.'), 0, 1, 'R');
$pdf->Ln(6);

// ===== A QUIEN CORRESPONDA (QUITAR SIGNOS RAROS, SUBRAYADO NEGRO) =====
if ($quoteRecipient) {
  $pdf->SetFont('Arial', 'B', 10);
  $pdf->SetTextColor(0,0,0);

  // limpiar signos raros (¿ ?)
  $cleanRecipient = str_replace(['¿','?'], '', $quoteRecipient);
  $cleanRecipient = trim($cleanRecipient);

  $text = 'A QUIEN CORRESPONDA: ' . mb_strtoupper($cleanRecipient);
  $pdf->SetX(15);
  $pdf->Cell(0, 6, u($text), 0, 1, 'L');

  // subrayado NEGRO según el ancho del texto
  $pdf->SetDrawColor(0,0,0);
  $x1 = 15;
  $y1 = $pdf->GetY() - 1;
  $w  = $pdf->GetStringWidth(u($text));
  $pdf->Line($x1, $y1, $x1 + $w, $y1);

  $pdf->SetFont('Arial', 'B', 9.5);
  $pdf->SetX(15);
  $pdf->Cell(0, 5, u('PRESENTE.'), 0, 1, 'L');
  $pdf->Ln(2);
}

// ===== TEXTO =====
$pdf->SetFont('Arial', '', 9);
$pdf->MultiCell(0, 4.8, u('Por medio del presente me permito brindarle un cordial saludo y a la vez poner a sus órdenes la siguiente propuesta de cotización:'), 0, 'J');
$pdf->Ln(4);

// ===== TABLA (BORDES NEGROS) =====
$pdf->SetDrawColor(0,0,0);
$pdf->SetLineWidth(0.2);

$wDesc  = 92;
$wQty   = 18;
$wUnit  = 20;
$wPrice = 25;
$wImp   = 25;

$pdf->SetWidths([$wDesc, $wQty, $wUnit, $wPrice, $wImp]);
$pdf->SetAligns(['L','R','C','R','R']);

$pdf->SetFont('Arial', 'B', 8.8);
$pdf->SetFillColor(235,235,235);

$pdf->Cell($wDesc, 7, u('DESCRIPCIÓN'), 1, 0, 'C', true);
$pdf->Cell($wQty,  7, u('CANTIDAD'),    1, 0, 'C', true);
$pdf->Cell($wUnit, 7, u('UNIDAD'),      1, 0, 'C', true);
$pdf->Cell($wPrice,7, u('PRECIO'),      1, 0, 'C', true);
$pdf->Cell($wImp,  7, u('IMPORTE'),     1, 1, 'C', true);

$pdf->SetFont('Arial', '', 8.7);

foreach ($rows as $r) {
  $pdf->Row([
    u($r['description']),
    number_format((float)$r['quantity'], 2),
    u($r['unit']),
    money($r['price']),
    money($r['total'])
  ], 5);
}

// ===== TOTALES =====
$pdf->SetFont('Arial', 'B', 8.8);

$pdf->Cell($wDesc + $wQty + $wUnit + $wPrice, 7, u('SUBTOTAL'), 1, 0, 'R');
$pdf->SetFont('Arial', '', 8.8);
$pdf->Cell($wImp, 7, money($subtotal), 1, 1, 'R');

if ($includeIva) {
  $pdf->SetFont('Arial', 'B', 8.8);
  $pdf->Cell($wDesc + $wQty + $wUnit + $wPrice, 7, u('IVA 16%'), 1, 0, 'R');
  $pdf->SetFont('Arial', '', 8.8);
  $pdf->Cell($wImp, 7, money($iva), 1, 1, 'R');
}

$pdf->SetFont('Arial', 'B', 9.2);
$pdf->SetFillColor(225,245,225);
$pdf->Cell($wDesc + $wQty + $wUnit + $wPrice, 8, u('TOTAL'), 1, 0, 'R', true);
$pdf->Cell($wImp, 8, money($total), 1, 1, 'R', true);

$pdf->Ln(6);

// ===== CIERRE =====
$pdf->SetFont('Arial', '', 9);
$pdf->MultiCell(0, 4.8, u('Agradeciendo de antemano su atención, quedo a sus órdenes para cualquier duda o aclaración.'), 0, 'J');
$pdf->Ln(10);

// ===== ATENTAMENTE + FIRMA =====
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 6, u('ATENTAMENTE:'), 0, 1, 'C');
$pdf->Ln(10);

// Firma
$lineW = 70;
$xLine = (210 - $lineW) / 2;

// colocar firma (si existe) arriba de la línea
if ($signaturePath) {
  $sigW = 45;
  $xSig = (210 - $sigW) / 2;
  $pdf->Image($signaturePath, $xSig, $pdf->GetY(), $sigW);
}

// Línea de firma
$pdf->SetY($pdf->GetY() + 18);
$pdf->SetDrawColor(0,0,0);
$pdf->Line($xLine, $pdf->GetY(), $xLine + $lineW, $pdf->GetY());
$pdf->Ln(3);

// Nombre
$pdf->SetFont('Arial', '', 9.5);
$pdf->Cell(0, 6, u($signatureName), 0, 1, 'C');
$pdf->Ln(8);

// ===== NOTAS =====
$pdf->SetFont('Arial', '', 8.2);
$pdf->MultiCell(0, 4.2, u(implode("\n", $NOTES)), 0, 'L');

// ===== PIE DE PÁGINA (MISMA PÁGINA, SIN FORZAR SEGUNDA) =====
// IMPORTANTE: NO usamos SetY(-22) porque eso puede empujar a otra página.
// Solo lo imprimimos donde va, si aún hay espacio; si no hay, se va a la siguiente (FPDF).
// Pero con AutoPageBreak y márgenes, normalmente quedará en la misma si el contenido cabe.
$pdf->Ln(6);

// línea superior verde
$pdf->SetDrawColor($GREEN[0], $GREEN[1], $GREEN[2]);
$pdf->SetLineWidth(0.4);
$yFooterLine = $pdf->GetY();
$pdf->Line(15, $yFooterLine, 195, $yFooterLine);
$pdf->Ln(2);

$pdf->SetFont('Arial', '', 7.8);
$pdf->SetTextColor(70,70,70);
$pdf->MultiCell(0, 3.8, u($footerAddress), 0, 'C');
$pdf->SetTextColor(0,0,0);

// ===== Guardar PDF =====
$quotesDir = __DIR__ . '/../uploads/quotes';
if (!is_dir($quotesDir)) mkdir($quotesDir, 0777, true);

$fileName = 'quote_' . time() . '.pdf';
$filePath = $quotesDir . '/' . $fileName;
$pdf->Output('F', $filePath);

echo json_encode(['success' => true, 'file' => 'uploads/quotes/' . $fileName]);
