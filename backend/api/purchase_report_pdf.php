<?php
// purchase_report_pdf.php – PDF historial de compras con snapshot de precios y tabla estable.

header('Content-Type: application/json');
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../lib/fpdf.php';

requireLogin();
$pdo = getPDO();

$search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';

// ============================
// Consulta (snapshot real)
// ============================
$sql = "SELECT
    p.date            AS operation_date,
    p.created_at      AS created_at,
    u.username        AS user_name,
    prod.id           AS product_id,
    prod.code         AS code,
    prod.barcode      AS barcode,
    prod.name         AS product_name,
    pi.quantity       AS quantity,
    pi.unit_price     AS purchase_price,
    COALESCE(pi.public_price, prod.public_price) AS public_price_at_purchase,
    COALESCE(pi.profit_margin, prod.profit_margin) AS profit_margin_at_purchase,
    s.name            AS supplier_name
  FROM purchase_items pi
  JOIN purchases p       ON pi.purchase_id = p.id
  JOIN products prod     ON pi.product_id = prod.id
  LEFT JOIN users u      ON p.created_by_user_id = u.id
  LEFT JOIN suppliers s  ON p.supplier_id = s.id";

$params = [];
if ($search !== '') {
    $sql .= " WHERE (prod.barcode LIKE ? OR prod.code LIKE ? OR prod.name LIKE ? OR CAST(prod.id AS CHAR) LIKE ?)";
    $like = '%' . $search . '%';
    $params = [$like, $like, $like, $like];
}
$sql .= " ORDER BY p.date DESC, p.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$records) {
    echo json_encode(['error' => 'No se encontraron operaciones para generar el reporte']);
    exit;
}

// ============================
// Config empresa
// ============================
$settingsPath = __DIR__ . '/../config/customization.json';
$settings = file_exists($settingsPath) ? json_decode(file_get_contents($settingsPath), true) : [];

$companyName     = $settings['company_name'] ?? 'CORPORATIVO SAN MARTÍN';
$companySubtitle = $settings['company_subtitle'] ?? '';

$headerRightImage = '';
if (!empty($settings['header_right_image_path'])) {
    $p = __DIR__ . '/../' . $settings['header_right_image_path'];
    if (file_exists($p)) $headerRightImage = $p;
}

$logoPath = '';
if (!empty($settings['logo_path'])) {
    $p = __DIR__ . '/../' . $settings['logo_path'];
    if (file_exists($p)) $logoPath = $p;
}

function u($t) { return utf8_decode((string)$t); }

// ============================
// Clase PDF
// ============================
class PDFReport extends FPDF {
    public $companyName = '';
    public $companySubtitle = '';
    public $logoPath = '';
    public $headerRightImage = '';
    public $titleLine = '';

    // Anchos columnas (Landscape A4: 297mm - márgenes aprox)
    public $wFecha  = 22;
    public $wUser   = 24;
    public $wID     = 8;
    public $wCodigo = 16;
    public $wBar    = 26;
    public $wProd   = 78; // MultiCell + padding
    public $wCant   = 14;
    public $wCompra = 18;
    public $wVenta  = 18;
    public $wProv   = 53; // MultiCell + padding

    function Header() {
        $left = 10;
        $topY = 6;

        if ($this->logoPath && file_exists($this->logoPath)) {
            $this->Image($this->logoPath, $left, $topY, 34);
        }
        if ($this->headerRightImage && file_exists($this->headerRightImage)) {
            $this->Image($this->headerRightImage, 255, $topY, 32);
        }

        $this->SetFont('Arial', 'B', 15);
        $this->SetXY(0, $topY + 2);
        $this->Cell(0, 7, u($this->companyName), 0, 1, 'C');

        if ($this->companySubtitle) {
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 5, u($this->companySubtitle), 0, 1, 'C');
        }

        // Línea separadora un poco más abajo
        $this->SetDrawColor(200, 200, 200);
        $this->Line(10, 32, 287, 32);

        // Espacio cómodo para el título
        $this->SetY(35);
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 9, u($this->titleLine), 0, 1, 'L');
        $this->Ln(3);

        // Encabezado tabla
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(235, 235, 235);

        $this->Cell($this->wFecha, 7, u('Fecha'), 1, 0, 'C', true);
        $this->Cell($this->wUser,  7, u('Registrado'), 1, 0, 'C', true);
        $this->Cell($this->wID,    7, u('ID'), 1, 0, 'C', true);
        $this->Cell($this->wCodigo,7, u('Código'), 1, 0, 'C', true);
        $this->Cell($this->wBar,   7, u('Cód. Barras'), 1, 0, 'C', true);
        $this->Cell($this->wProd,  7, u('Producto'), 1, 0, 'C', true);
        $this->Cell($this->wCant,  7, u('Cant.'), 1, 0, 'C', true);
        $this->Cell($this->wCompra,7, u('P. compra'), 1, 0, 'C', true);
        $this->Cell($this->wVenta, 7, u('P. venta'), 1, 0, 'C', true);
        $this->Cell($this->wProv,  7, u('Proveedor'), 1, 1, 'C', true);

        $this->SetFont('Arial', '', 8);
    }

    function Footer() {
        $this->SetY(-12);
        $this->SetFont('Arial', '', 7);
        $this->SetTextColor(90, 90, 90);
        $this->SetDrawColor(200, 200, 200);
        $this->Line(10, $this->GetY(), 287, $this->GetY());
        $this->Ln(2);
        $this->Cell(0, 3, u('Reporte generado el ' . date('Y-m-d H:i')), 0, 1, 'C');
        $this->Cell(0, 3, u($this->companyName . ' © ' . date('Y')), 0, 0, 'C');
        $this->SetTextColor(0, 0, 0);
    }

    function NbLines($w, $txt) {
        $cw = $this->CurrentFont['cw'];
        if ($w == 0) $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2*$this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r",'',(string)$txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb-1] == "\n") $nb--;
        $sep = -1; $i=0; $j=0; $l=0; $nl=1;
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

    function CheckPageBreak($h) {
        if ($this->GetY() + $h > $this->PageBreakTrigger) {
            $this->AddPage($this->CurOrientation);
        }
    }

    function Row($data) {
        $lineH = 5;

        $nbProd = $this->NbLines($this->wProd, $data['product']);
        $nbProv = $this->NbLines($this->wProv, $data['supplier']);
        $nb = max(1, $nbProd, $nbProv);

        $h = $lineH * $nb;
        if ($h < 9) $h = 9; // altura mínima

        $this->CheckPageBreak($h);

        $x = $this->GetX();
        $y = $this->GetY();

        // Celdas fijas
        $this->Cell($this->wFecha,  $h, $data['date'], 1);
        $this->Cell($this->wUser,   $h, $data['user'], 1);
        $this->Cell($this->wID,     $h, $data['id'], 1, 0, 'C');
        $this->Cell($this->wCodigo, $h, $data['code'], 1);
        $this->Cell($this->wBar,    $h, $data['barcode'], 1);

        // Producto (borde + padding)
        $xProd = $this->GetX();
        $yProd = $this->GetY();
        $this->Rect($xProd, $yProd, $this->wProd, $h);
        $this->SetXY($xProd, $yProd + 1.2);
        $this->MultiCell($this->wProd, $lineH, $data['product'], 0, 'L');
        $this->SetXY($xProd + $this->wProd, $yProd);

        // Cantidad / precios
        $this->Cell($this->wCant,   $h, $data['qty'], 1, 0, 'R');
        $this->Cell($this->wCompra, $h, $data['buy'], 1, 0, 'R');
        $this->Cell($this->wVenta,  $h, $data['sell'], 1, 0, 'R');

        // Proveedor (borde + padding)
        $xProv = $this->GetX();
        $yProv = $this->GetY();
        $this->Rect($xProv, $yProv, $this->wProv, $h);
        $this->SetXY($xProv, $yProv + 1.2);
        $this->MultiCell($this->wProv, $lineH, $data['supplier'], 0, 'L');

        // Cursor al final de la fila
        $this->SetXY($x, $y + $h);
    }
}

// ============================
// Crear PDF
// ============================
$title = 'Historial de operaciones de compra';
if ($search !== '') $title .= ' (filtro: ' . $search . ')';

$pdf = new PDFReport('L', 'mm', 'A4');
$pdf->companyName = $companyName;
$pdf->companySubtitle = $companySubtitle;
$pdf->logoPath = $logoPath;
$pdf->headerRightImage = $headerRightImage;
$pdf->titleLine = $title;

$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();
$pdf->SetFont('Arial', '', 8);

// ============================
// Imprimir registros
// ============================
foreach ($records as $row) {
    $dateStr = !empty($row['operation_date']) ? date('Y-m-d', strtotime($row['operation_date'])) : '';

    $qty = (float)($row['quantity'] ?? 0);
    $pcompra = (float)($row['purchase_price'] ?? 0);
    $pventa  = (float)($row['public_price_at_purchase'] ?? 0);

    $pdf->Row([
        'date'     => u($dateStr),
        'user'     => u($row['user_name'] ?? ''),
        'id'       => u($row['product_id'] ?? ''),
        'code'     => u($row['code'] ?? ''),
        'barcode'  => u($row['barcode'] ?? ''),
        'product'  => u($row['product_name'] ?? ''),
        'qty'      => number_format($qty, 2),
        'buy'      => '$' . number_format($pcompra, 2),
        'sell'     => '$' . number_format($pventa, 2),
        'supplier' => u($row['supplier_name'] ?? ''),
    ]);
}

// ============================
// Guardar PDF
// ============================
$dir = __DIR__ . '/../uploads/purchase_reports';
if (!is_dir($dir)) mkdir($dir, 0777, true);

$filename = 'historial_compras_' . time() . '.pdf';
$filePath = $dir . '/' . $filename;

$pdf->Output('F', $filePath);

echo json_encode(['success' => true, 'file' => 'uploads/purchase_reports/' . $filename]);
