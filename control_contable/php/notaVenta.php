<?php

require_once __DIR__ . "/libs/fpdf/fpdf.php"; 

class notaVenta extends FPDF
{
    private $anchoNota = 140;
    private $altoNota = 216;

    public function __construct()
    {
        parent::__construct("P", "mm", array(140, 216));
        $this->SetAutoPageBreak(true, 8);
        $this->SetMargins(7, 7, 7);
    }

    private function moneda($valor)
    {
        return "$" . number_format((float)$valor, 2, ".", ",");
    }

    private function fechaMx($fecha)
    {
        if (!$fecha) return "";
        $time = strtotime($fecha);
        if (!$time) return $fecha;
        return date("d/m/Y H:i", $time);
    }

    private function limpiarNombreArchivo($texto)
    {
        $texto = preg_replace('/[^A-Za-z0-9_\-]/', '_', $texto);
        return trim($texto, '_');
    }

    
public function generarArchivo($venta) {
    $this->SetAutoPageBreak(false);
    $this->SetMargins(7, 7, 7);
    $this->AddPage();

    $y = $this->encabezadoNotaFisica($venta);
    $items = isset($venta['items']) && is_array($venta['items']) ? $venta['items'] : [];

    foreach ($items as $item) {
        $this->SetFont('Arial', '', 7.1);
        $descripcion = trim((string)($item['manual_name'] ?? ''));
        if (!empty($item['manual_code'])) $descripcion .= ' [' . $item['manual_code'] . ']';

        $lineas = $this->lineasArticuloNota($descripcion, 61);
        $alto = max(8, count($lineas) * 3.8 + 2);

        if ($y + $alto > 155) {
            $this->AddPage();
            $y = $this->encabezadoNotaFisica($venta);
        }

        $this->SetDrawColor(126, 169, 140);
        foreach ([[7,13],[20,65],[85,23],[108,25]] as $celda) {
            $this->Rect($celda[0], $y, $celda[1], $alto);
        }

        $this->SetTextColor(35, 45, 39);
        $cant = (float)($item['quantity'] ?? 0);
        $cantidad = rtrim(rtrim(number_format($cant, 2, '.', ''), '0'), '.');

        $this->escribirNota(7.5, $y + 2, 12, $cantidad, 7.7, '', 'C');

        foreach ($lineas as $i => $linea) {
            $this->escribirNota(22, $y + 1.1 + $i * 3.8, 61, $linea, 7.1);
        }

        $this->escribirNota(86, $y + 2, 21, $this->moneda($item['price'] ?? 0), 7.6, '', 'R');
        $this->escribirNota(109, $y + 2, 23, $this->moneda($item['total'] ?? 0), 7.6, 'B', 'R');

        $y += $alto;
    }

    while ($y + 8 <= 155) {
        $this->SetDrawColor(163, 195, 174);
        foreach ([[7,13],[20,65],[85,23],[108,25]] as $celda) {
            $this->Rect($celda[0], $y, $celda[1], 8);
        }
        $y += 8;
    }

    $this->pieNotaFisica($venta);

    $carpeta = __DIR__ . '/../pdf_generados/notas_venta/';

    if (!is_dir($carpeta) && !mkdir($carpeta, 0750, true) && !is_dir($carpeta)) {
        throw new RuntimeException('No fue posible crear el directorio de notas.');
    }

    if (!is_writable($carpeta)) {
        throw new RuntimeException('La carpeta de notas no tiene permisos de escritura.');
    }

    $folio = $this->limpiarNombreArchivo(
        !empty($venta['note_folio']) ? $venta['note_folio'] : 'venta_' . $venta['id']
    );

    $nombre = 'nota_' . $folio . '_' . date('Ymd_His') . '.pdf';
    $this->Output('F', $carpeta . $nombre);

    return [
        'nombre' => $nombre,
        'ruta_fisica' => $carpeta . $nombre,
        'ruta_web' => 'pdf_generados/notas_venta/' . $nombre
    ];
}


private function textoNota($texto) {
    $texto = (string)($texto === null ? '' : $texto);
    $convertido = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $texto);
    return $convertido === false ? utf8_decode($texto) : $convertido;
}

private function escribirNota($x, $y, $ancho, $texto, $tamano = 8, $estilo = '', $alineacion = 'L') {
    $texto = $this->textoNota($texto);
    $this->SetFont('Arial', $estilo, $tamano);
    while ($tamano > 5 && $this->GetStringWidth($texto) > $ancho - 1.5) {
        $tamano -= 0.25;
        $this->SetFont('Arial', $estilo, $tamano);
    }
    while ($this->GetStringWidth($texto) > $ancho - 1.5 && strlen($texto) > 3) {
    $texto = substr($texto, 0, -2);
    $texto = rtrim($texto, '.') . '...';
}

$this->SetXY($x, $y);
$this->Cell($ancho, 4, $texto, 0, 0, $alineacion);
}

private function lineasArticuloNota($texto, $ancho) {
    $texto = str_replace("\r", '', $this->textoNota($texto));
    $lineas = [];
    foreach (explode("\n", $texto) as $parrafo) {
        $resto = trim($parrafo);
        if ($resto === '') { $lineas[] = ''; continue; }
        while ($resto !== '') {
            $limite = strlen($resto);
            $corte = 1;
            while ($corte <= $limite && $this->GetStringWidth(substr($resto, 0, $corte)) <= $ancho) $corte++;
            $corte = max(1, $corte - 1);
            if ($corte < $limite) {
                $espacio = strrpos(substr($resto, 0, $corte), ' ');
                if ($espacio !== false && $espacio > 0) $corte = $espacio;
            }
            $lineas[] = trim(substr($resto, 0, $corte));
            $resto = ltrim(substr($resto, $corte));
        }
    }
    return $lineas;
}


private function encabezadoNotaFisica($venta) {
    $x = 7; $w = 126;
    $this->SetDrawColor(72, 150, 116);
    $this->SetLineWidth(0.25);

    $logo = __DIR__ . '/../recursos/logo.png';
    if (is_file($logo)) {
        $info = @getimagesize($logo);
        if ($info && $info[0] > 0 && $info[1] > 0) {
            $factor = min(20 / $info[0], 20 / $info[1]);
            $lw = $info[0] * $factor; $lh = $info[1] * $factor;
            $this->Image($logo, 8 + (20 - $lw) / 2, 7 + (20 - $lh) / 2, $lw, $lh);
        }
    }

    $this->SetTextColor(26, 116, 83);
    $this->escribirNota(31, 8, 73, 'Corporativo San Martín', 13, 'B');
    $this->SetTextColor(60, 78, 68);
    $this->escribirNota(31, 15, 73, 'Renta de maquinaria y venta de materiales', 6.6);
    $this->escribirNota(31, 19, 73, 'para la construcción', 6.6);

    $this->SetFillColor(235, 247, 239);
    $this->Rect(106, 7, 27, 19, 'DF');
    $this->SetTextColor(24, 101, 72);
    $this->escribirNota(108, 9, 23, 'NOTA N.º', 6.2, 'B', 'C');
    $this->escribirNota(107, 16, 25, $venta['note_folio'] ?? '', 8.5, 'B', 'C');

    $this->SetFillColor(26, 134, 94);
    $this->Rect($x, 30, $w, 9, 'F');
    $this->SetTextColor(255, 255, 255);
    $this->escribirNota(9, 32.2, 55, 'Tel. 953 55 212 53', 9, 'B');
    $this->escribirNota(67, 32.2, 64, 'WhatsApp 951 287 04 99', 8.5, 'B');

    $this->SetFillColor(235, 247, 239);
    $this->Rect($x, 39, $w, 9, 'F');
    $this->SetTextColor(33, 93, 68);
    $this->escribirNota(9, 39.4, 122, 'CARRETERA YUCUDAA KM. 34 // SAN MARTÍN HUAMELULPAM, TLAX. OAX.', 6.4, 'B');
    $this->escribirNota(9, 43.7, 122, 'ATENCIÓN: HERMINIO PÉREZ BERNABÉ', 6.3);

    $this->SetTextColor(37, 46, 41);
    $this->escribirNota(8, 52, 16, 'Nombre:', 8, 'B');
    $this->escribirNota(26, 52, 73, $venta['client_name'] ?? '', 8.5);
    $this->Line(26, 57, 99, 57);

    $this->escribirNota(8, 60, 17, 'Dirección:', 8, 'B');
    $this->escribirNota(26, 60, 105, $venta['client_address'] ?? '', 8);
    $this->Line(26, 65, 132, 65);

    $this->escribirNota(8, 68, 17, 'Vendedor:', 8, 'B');
    $this->escribirNota(26, 68, 56, $venta['seller_name'] ?? '', 8);
    $this->Line(26, 73, 80, 73);
    $this->escribirNota(83, 68, 9, 'Tel.:', 8, 'B');
    $this->escribirNota(93, 68, 39, $venta['client_phone'] ?? '', 8);
    $this->Line(93, 73, 132, 73);

    $fecha = strtotime($venta['created_at'] ?? '') ?: time();
    $partes = [date('d', $fecha), date('m', $fecha), date('Y', $fecha)];

    foreach ($partes as $i => $valor) {
        $px = 101 + $i * 10.7;
        $this->SetFillColor(234, 245, 238);
        $this->Rect($px, 49, 10.5, 10.5, 'DF');
        $this->SetTextColor(46, 119, 85);
        $this->escribirNota($px, 49.5, 10.5, ['DÍA','MES','AÑO'][$i], 5.4, 'B', 'C');
        $this->SetTextColor(34, 45, 38);
        $this->escribirNota($px, 54, 10.5, $valor, 8, 'B', 'C');
    }

    $this->SetFillColor(225, 243, 231);
    $this->SetFont('Arial', 'B', 7.2);
    $this->SetTextColor(29, 80, 55);

    $columnas = [
        [7,13,'CANT.'], [20,65,'DESCRIPCIÓN'],
        [85,23,'P. UNIT.'], [108,25,'IMPORTE']
    ];

    foreach ($columnas as $c) {
        $this->Rect($c[0], 78, $c[1], 7, 'DF');
        $this->SetXY($c[0], 79);
        $this->Cell($c[1], 5, $this->textoNota($c[2]), 0, 0, 'C');
    }

    $this->SetTextColor(35, 45, 39);
    return 85;
}

private function pieNotaFisica($venta) {
    $this->SetTextColor(35, 45, 39);
    $this->escribirNota(8, 157, 79, 'Forma de pago: ' . ($venta['payment_method'] ?? ''), 7.8, 'B');
    $this->escribirNota(8, 164, 79, 'Referencia: ' . (!empty($venta['bank_reference']) ? $venta['bank_reference'] : '—'), 7.5);

    if (!empty($venta['notes'])) {
        $this->escribirNota(8, 173, 79, 'Observaciones:', 7.5, 'B');
        $this->escribirNota(8, 178, 79, $venta['notes'], 7);
    }

    $this->Line(19, 190, 78, 190);
    $this->escribirNota(19, 191, 59, 'Recibí / Firma', 7.2, '', 'C');

    $filas = [
        ['SUBTOTAL', $this->moneda($venta['total_amount'] ?? 0)],
        ['I.V.A.', '—'],
        ['ABONO', $this->moneda($venta['initial_paid'] ?? 0)],
        ['SALDO', $this->moneda($venta['pending_amount'] ?? 0)],
        ['TOTAL', $this->moneda($venta['total_amount'] ?? 0)]
    ];

    foreach ($filas as $i => $fila) {
        $y = 157 + ($i * 7.4);
        $ultimo = $i === 4;
        $this->SetFillColor($ultimo ? 27 : 240, $ultimo ? 135 : 248, $ultimo ? 96 : 242);
        $this->Rect(91, $y, 42, 7.4, 'DF');
        $this->SetTextColor($ultimo ? 255 : 35, $ultimo ? 255 : 60, $ultimo ? 255 : 45);
        $this->escribirNota(93, $y + 1.6, 17, $fila[0], 7, 'B');
        $this->escribirNota(110, $y + 1.6, 21, $fila[1], 8, 'B', 'R');
    }

    $this->SetTextColor(37, 95, 67);
    $this->escribirNota(7, 199, 126, 'CONSERVE SU NOTA PARA PODER RECOGER SU PEDIDO.', 7, 'B', 'C');
    $this->escribirNota(7, 204, 126, 'No se entregará el pedido sin presentar esta nota en mostrador.', 6.5, '', 'C');
    $this->escribirNota(7, 209, 126, 'Documento de venta. I.V.A. no desglosado.', 6, '', 'C');
}

}

