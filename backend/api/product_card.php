<?php
header('Content-Type: application/json');

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../lib/fpdf.php';

requireLogin();
$pdo = getPDO();

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'ID inválido']);
    exit;
}

$stmt = $pdo->prepare('
  SELECT p.*, s.name AS supplier_name
  FROM products p
  LEFT JOIN suppliers s ON p.supplier_id = s.id
  WHERE p.id = ?
');
$stmt->execute([$id]);
$prod = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$prod) {
    http_response_code(404);
    echo json_encode(['error' => 'Producto no encontrado']);
    exit;
}

$settingsPath = __DIR__ . '/../config/customization.json';
$settings = file_exists($settingsPath) ? json_decode(file_get_contents($settingsPath), true) : [];

function u($t) { return utf8_decode((string)$t); }
function money($n) { return '$' . number_format((float)$n, 2); }

function makeBullets($txt, $max = 6) {
    $txt = trim((string)$txt);
    if ($txt === '') return [];
    $parts = preg_split("/\r\n|\r|\n|\. /", $txt);
    $res = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p !== '') {
            $res[] = $p;
            if (count($res) >= $max) break;
        }
    }
    return $res;
}

function theme() {
    return [
        'black'  => [18, 18, 18],
        'orange' => [255, 120, 0],
        'gray'   => [245, 245, 245],
        'line'   => [220, 220, 220],
        'soft'   => [100, 100, 100],
    ];
}

function drawSection(FPDF $pdf, $title, $y) {
    $c = theme();
    $pdf->SetFillColor($c['orange'][0], $c['orange'][1], $c['orange'][2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetXY(10, $y);
    $pdf->Cell(190, 7, u($title), 0, 1, 'L', true);
    $pdf->SetTextColor(0, 0, 0);
}

function drawRow(FPDF $pdf, $label, $value, $y, $fill) {
    $c = theme();
    $pdf->SetFillColor(
        $fill ? $c['gray'][0] : 255,
        $fill ? $c['gray'][1] : 255,
        $fill ? $c['gray'][2] : 255
    );
    $pdf->SetDrawColor($c['line'][0], $c['line'][1], $c['line'][2]);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetXY(10, $y);
    $pdf->Cell(60, 7, u($label), 0, 0, 'L', true);
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(130, 7, u($value), 0, 1, 'L', true);
    $pdf->Line(10, $y + 7, 200, $y + 7);
}

class PDF extends FPDF {
    public $companyName = 'CORPORATIVO SAN MARTÍN';
    public $rightTitle = 'Ficha técnica';
    public $logoAbsPath = null;

    function Header() {
        $c = theme();

        $this->SetFillColor($c['black'][0], $c['black'][1], $c['black'][2]);
        $this->Rect(0, 0, 210, 16, 'F');

        $textX = 10;

        if ($this->logoAbsPath && file_exists($this->logoAbsPath)) {
            $this->Image($this->logoAbsPath, 8, 2, 12, 12);
            $textX = 22;
        }

        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 11);
        $this->SetXY($textX, 4);
        $this->Cell(120, 8, u($this->companyName), 0, 0, 'L');

        $this->SetFont('Arial', '', 10);
        $this->SetXY(150, 4);
        $this->Cell(50, 8, u($this->rightTitle), 0, 0, 'R');

        $this->SetTextColor(0, 0, 0);
        $this->Ln(18);
    }

    function Footer() {
        $this->SetY(-16);

        $this->SetDrawColor(200, 200, 200);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(2);

        $this->SetFont('Arial', '', 7);
        $this->SetTextColor(90, 90, 90);
        $this->Cell(0, 3, u('Información sujeta a cambios sin previo aviso.'), 0, 1, 'C');
        $this->Cell(0, 3, u('Generado por el sistema.'), 0, 1, 'C');
        $this->Cell(0, 3, u('SISTEMA CSM © ' . date('Y') . '  |  Página ' . $this->PageNo() . ' / {nb}'), 0, 1, 'C');

        $this->SetFont('Arial', '', 6);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(0, 3, u('Fecha: ' . date('Y-m-d H:i')), 0, 0, 'C');

        $this->SetTextColor(0, 0, 0);
    }
}

$pdf = new PDF('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->SetAutoPageBreak(true, 18);

$pdf->companyName = $settings['company_name'] ?? 'CORPORATIVO SAN MARTÍN';

$logoAbs = null;
if (!empty($settings['logo_path'])) {
    $candidate = __DIR__ . '/../' . ltrim($settings['logo_path'], '/');
    if (file_exists($candidate)) {
        $logoAbs = $candidate;
    }
}
$pdf->logoAbsPath = $logoAbs;

$pdf->AddPage();

$leftX = 10;
$rightX = 95;

$topY = 24;

$imgW = 80;
$imgH = 80;
$imgY = $topY + 10;

$hasImg = false;
if (!empty($prod['image_path'])) {
    $imgPath = __DIR__ . '/../' . ltrim($prod['image_path'], '/');
    if (file_exists($imgPath)) {
        $pdf->Image($imgPath, $leftX, $imgY, $imgW);
        $hasImg = true;
    }
}

$pdf->SetDrawColor(220, 220, 220);
$pdf->Rect($leftX, $imgY, $imgW, $imgH);

if (!$hasImg) {
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->SetXY($leftX, $imgY + 35);
    $pdf->Cell($imgW, 6, u('Sin imagen'), 0, 0, 'C');
    $pdf->SetTextColor(0, 0, 0);
}

$soft = theme()['soft'];
$pdf->SetXY($rightX, $topY);
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor($soft[0], $soft[1], $soft[2]);
$pdf->Cell(60, 5, u('CÓDIGO: ' . $prod['id']), 0, 0);
$pdf->Cell(0, 5, u('CLAVE: ' . ($prod['code'] ?: 'N/A')), 0, 1);
$pdf->SetTextColor(0, 0, 0);

$pdf->SetX($rightX);
$pdf->SetFont('Arial', 'B', 12);
$pdf->MultiCell(105, 6, u($prod['name'] ?: ''), 0, 'L');

$pdf->Ln(2);
$pdf->SetFont('Arial', '', 9);

$bullets = makeBullets($prod['description'] ?? '');
$colors = theme();

if (!empty($bullets)) {
    foreach ($bullets as $b) {
        $pdf->SetX($rightX);
        $pdf->SetTextColor($colors['orange'][0], $colors['orange'][1], $colors['orange'][2]);
        $pdf->Cell(4, 5, chr(149), 0, 0);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->MultiCell(100, 5, u($b), 0, 'L');
    }
} else {
    $pdf->SetX($rightX);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->MultiCell(105, 5, u('Sin descripción.'), 0, 'L');
    $pdf->SetTextColor(0, 0, 0);
}

$y = max($imgY + $imgH + 12, $pdf->GetY() + 6);

drawSection($pdf, 'Especificaciones', $y);
$y += 9;

$specs = [
    ['Código de barras', $prod['barcode'] ?: ''],
    ['Clasificación', $prod['classification'] ?: ''],
    ['Proveedor', $prod['supplier_name'] ?: 'N/A'],
    ['Unidad', $prod['unit'] ?: ''],
    ['Fecha de compra', $prod['last_purchase'] ?: ''],
    ['Precio de compra', money($prod['purchase_price'])],
    ['Precio mínimo de venta', money($prod['min_price'])],
    ['Margen de ganancia', ($prod['profit_margin'] !== null ? (round(floatval($prod['profit_margin']) * 100) . '%') : 'N/A')],
    ['Precio de venta', money($prod['public_price'])],
    ['Precio de competencia', ($prod['competitor_price'] ? money($prod['competitor_price']) : '')],
];

$fill = false;
foreach ($specs as $row) {
    if ($y > 270) {
        $pdf->AddPage();
        $y = 24;
        drawSection($pdf, 'Especificaciones', $y);
        $y += 9;
        $fill = false;
    }
    drawRow($pdf, $row[0], $row[1], $y, $fill);
    $y += 7;
    $fill = !$fill;
}

$y += 5;

if ($y > 270) {
    $pdf->AddPage();
    $y = 24;
}

drawSection($pdf, 'Inventario', $y);
$y += 9;

$inventory = [
    ['Stock existente', (string)($prod['stock'] ?? 0)],
    ['Stock mínimo', (string)($prod['min_stock'] ?? 0)],
    ['Stock máximo', (string)($prod['max_stock'] ?? 0)],
];

$fill = false;
foreach ($inventory as $row) {
    if ($y > 270) {
        $pdf->AddPage();
        $y = 24;
        drawSection($pdf, 'Inventario', $y);
        $y += 9;
        $fill = false;
    }
    drawRow($pdf, $row[0], $row[1], $y, $fill);
    $y += 7;
    $fill = !$fill;
}

$dir = __DIR__ . '/../uploads/product_cards';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$fileName = 'product_' . $id . '_' . time() . '.pdf';
$pdf->Output('F', $dir . '/' . $fileName);

echo json_encode(['success' => true, 'file' => 'uploads/product_cards/' . $fileName]);
