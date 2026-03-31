<?php
// Genera un PDF tipo "ficha" (pro) para un proveedor.
// Retorna un JSON con la ruta relativa al archivo generado dentro de backend/uploads.

header('Content-Type: application/json');
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../lib/fpdf.php';

requireLogin();
$pdo = getPDO();

// ===== Helpers =====
function pdfText($s) {
    // FPDF usa ISO-8859-1 por defecto
    if ($s === null) return '';
    $s = (string)$s;
    $s = preg_replace('/[[:cntrl:]]/', ' ', $s);
    $s = trim($s);
    $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s);
    return $converted !== false ? $converted : $s;
}

function safeVal($s, $fallback = 'N/A') {
    $s = trim((string)($s ?? ''));
    return $s !== '' ? $s : $fallback;
}

function sectionHeader($pdf, $title, $w = 190) {
    $pdf->SetFillColor(255, 122, 0);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell($w, 8, pdfText($title), 0, 1, 'L', true);
    $pdf->Ln(2);
}

function labelValue($pdf, $label, $value, $labelW = 50, $lineH = 6, $wTotal = 190) {
    $pdf->SetTextColor(20, 20, 20);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell($labelW, $lineH, pdfText($label), 0, 0, 'L');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell($wTotal - $labelW, $lineH, pdfText($value), 0, 1, 'L');
}

function labelValueMulti($pdf, $label, $value, $labelW = 50, $lineH = 6, $wTotal = 190) {
    $pdf->SetTextColor(20, 20, 20);
    $pdf->SetFont('Arial', 'B', 10);
    $x = $pdf->GetX();
    $y = $pdf->GetY();
    $pdf->Cell($labelW, $lineH, pdfText($label), 0, 0, 'L');
    $pdf->SetXY($x + $labelW, $y);
    $pdf->SetFont('Arial', '', 10);
    $pdf->MultiCell($wTotal - $labelW, $lineH, pdfText($value), 0, 'L');
}

// ===== PDF con Footer correcto (NO crea página extra) =====
class SupplierPDF extends FPDF {
    public string $companyName = 'Empresa';

    function Footer() {
        // Posición segura arriba del trigger de salto
        // (con margen inferior default, -22 evita el salto)
        $this->SetY(-22);

        $this->SetDrawColor(200, 200, 200);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(2);

        $this->SetTextColor(100, 100, 100);
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 4, pdfText('Generado por el sistema de inventario - ' . date('Y-m-d H:i')), 0, 1, 'C');

        $this->SetFont('Arial', '', 7);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(0, 3, pdfText($this->companyName . ' © ' . date('Y')), 0, 0, 'C');
    }
}

// ===== Obtener ID =====
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de proveedor inválido']);
    exit;
}

// ===== Consultar proveedor =====
$stmt = $pdo->prepare('SELECT * FROM suppliers WHERE id = ?');
$stmt->execute([$id]);
$sup = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sup) {
    http_response_code(404);
    echo json_encode(['error' => 'Proveedor no encontrado']);
    exit;
}

// ===== Config empresa =====
$settingsPath = __DIR__ . '/../config/customization.json';
$settings = file_exists($settingsPath) ? json_decode(file_get_contents($settingsPath), true) : [];

$companyName = $settings['company_name'] ?? 'Empresa';
$logoRel = $settings['logo_path'] ?? '';
$logoAbs = (!empty($logoRel)) ? (__DIR__ . '/../' . $logoRel) : '';

// ===== PDF A4 vertical =====
$pdf = new SupplierPDF('P', 'mm', 'A4');
$pdf->companyName = $companyName;

// deja el auto page break como está; el Footer() ya no dispara página extra
$pdf->AddPage();

// ===== Encabezado negro + naranja =====
$pdf->SetFillColor(15, 15, 15);
$pdf->Rect(0, 0, 210, 28, 'F');
$pdf->SetFillColor(255, 122, 0);
$pdf->Rect(0, 28, 210, 2, 'F');

// Logo (si existe)
if (!empty($logoAbs) && file_exists($logoAbs)) {
    $pdf->Image($logoAbs, 10, 5, 18);
}

// Título + empresa
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetXY(32, 7);
$pdf->Cell(168, 7, pdfText($companyName), 0, 2, 'L');
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(168, 6, pdfText('Ficha de proveedor'), 0, 0, 'L');

// Nombre proveedor grande (debajo del header)
$pdf->SetY(34);
$pdf->SetTextColor(20, 20, 20);
$pdf->SetFont('Arial', 'B', 18);
$pdf->Cell(190, 10, pdfText(safeVal($sup['name'], 'Proveedor')), 0, 1, 'L');

$pdf->Ln(2);

// ===== Sección: Datos generales =====
sectionHeader($pdf, 'Datos generales');
labelValue($pdf, 'Empresa:', safeVal($sup['company'] ?? '', ''));
labelValue($pdf, 'Categorías:', safeVal($sup['categories'] ?? '', ''));

// ===== Sección: Contacto =====
$pdf->Ln(2);
sectionHeader($pdf, 'Contacto');
labelValue($pdf, 'Contacto:', safeVal($sup['contact_name'] ?? '', ''));
labelValue($pdf, 'Teléfono:', safeVal($sup['phone_number'] ?? '', ''));
labelValue($pdf, 'Celular:', safeVal($sup['mobile_number'] ?? '', ''));
labelValue($pdf, 'Email:', safeVal($sup['email'] ?? '', ''));

// ===== Sección: Dirección =====
$pdf->Ln(2);
sectionHeader($pdf, 'Dirección');
labelValueMulti($pdf, 'Dirección:', safeVal($sup['address'] ?? '', ''));

// ===== Sección: Datos fiscales =====
$pdf->Ln(2);
sectionHeader($pdf, 'Datos fiscales');
labelValue($pdf, 'RFC:', safeVal($sup['rfc'] ?? '', ''));
labelValueMulti($pdf, 'Razón social:', safeVal($sup['legal_name'] ?? '', ''));
labelValue($pdf, 'C.P.:', safeVal($sup['postal_code'] ?? '', ''));

// ===== Sección: Datos bancarios =====
$pdf->Ln(2);
sectionHeader($pdf, 'Datos bancarios');

$bank = trim((string)($sup['bank'] ?? ''));
labelValue($pdf, 'Banco:', safeVal($bank, ''));

if ($bank !== '') {
    if (strcasecmp($bank, 'banamex') === 0) {
        labelValue($pdf, 'Sucursal:', safeVal($sup['branch'] ?? '', ''));
        labelValue($pdf, 'No. cuenta:', safeVal($sup['account_number'] ?? '', ''));
    } else {
        labelValue($pdf, 'CLABE:', safeVal($sup['interbank_key'] ?? '', ''));
    }
}
labelValueMulti($pdf, 'Titular:', safeVal($sup['account_holder'] ?? '', ''));

// ===== Sección: Categorías (en lista) =====
$pdf->Ln(2);
sectionHeader($pdf, 'Categorías que provee');

$catsRaw = trim((string)($sup['categories'] ?? ''));
if ($catsRaw === '') {
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(60, 60, 60);
    $pdf->MultiCell(190, 6, pdfText('No se registraron categorías.'));
} else {
    $cats = array_filter(array_map('trim', explode(',', $catsRaw)));
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(20, 20, 20);

    foreach ($cats as $c) {
        $pdf->Cell(5, 6, chr(149), 0, 0);
        $pdf->MultiCell(185, 6, pdfText($c), 0, 'L');
    }
}

// ===== Guardar PDF =====
$dir = __DIR__ . '/../uploads/supplier_cards';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}
$fileName = 'supplier_' . $id . '_' . time() . '.pdf';
$filePath = $dir . '/' . $fileName;

$pdf->Output('F', $filePath);

// Devolver ruta relativa
$relative = 'uploads/supplier_cards/' . $fileName;
echo json_encode(['success' => true, 'file' => $relative]);
