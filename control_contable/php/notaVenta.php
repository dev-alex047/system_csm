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

    
public function generarArchivo($venta, $firmaRuta = '') {
    $this->SetAutoPageBreak(false);
    $this->SetMargins(7, 7, 7);
    $this->AddPage();

    $y = $this->encabezadoNotaFisica($venta);
    $this->marcaAguaNota();
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
            $this->marcaAguaNota();
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

    //$this->pieNotaFisica($venta);
    $this->pieNotaFisica($venta, $firmaRuta);

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

    //$nombre = 'nota_' . $folio . '_' . date('Ymd_His') . '.pdf';
    $nombre = 'nota_' . $folio . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.pdf';
    $this->Output('F', $carpeta . $nombre);

    return [
        'nombre' => $nombre,
        'ruta_fisica' => $carpeta . $nombre,
        'ruta_web' => 'pdf_generados/notas_venta/' . $nombre
    ];
}



private function textoNota($texto) {
    $texto = (string)($texto === null ? '' : $texto);
    if (!preg_match('//u', $texto)) return $texto;
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

    if ($this->GetStringWidth($texto) > $ancho - 1.5) {
        $sufijo = '...';
        while ($texto !== '' && $this->GetStringWidth($texto . $sufijo) > $ancho - 1.5) {
            $texto = substr($texto, 0, -1);
        }
        $texto = rtrim($texto) . $sufijo;
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


private function iconoContactoNota($tipo, $x, $y) {
    $this->SetDrawColor(255, 255, 255);
    $this->SetLineWidth(0.3);

    if ($tipo === 'telefono') {
        $this->Rect($x + 0.5, $y, 2.8, 4.7);
        $this->Line($x + 1.4, $y + 4, $x + 2.4, $y + 4);
    } elseif ($tipo === 'whatsapp') {
        $this->Rect($x, $y, 4, 3.4);
        $this->Line($x + 1, $y + 3.4, $x + 0.3, $y + 4.5);
        $this->Line($x + 0.3, $y + 4.5, $x + 2, $y + 3.4);
        $this->Line($x + 1.1, $y + 1, $x + 2.9, $y + 2.5);
    } elseif ($tipo === 'ubicacion') {
        $this->Line($x + 0.4, $y + 0.5, $x + 2, $y + 4.2);
        $this->Line($x + 2, $y + 4.2, $x + 3.6, $y + 0.5);
        $this->Line($x + 0.4, $y + 0.5, $x + 3.6, $y + 0.5);
    }

    $this->SetDrawColor(72, 150, 116);
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


// FRANJA VERDE COMPLETA
$this->SetFillColor(25, 132, 91);
$this->Rect(7, 30, 126, 21, 'F');
$this->SetTextColor(255, 255, 255);

// CONTACTOS
$this->iconoContactoNota('telefono', 9, 32.5);
$this->escribirNota(15, 32.5, 33, '953 55 212 53', 8.7, 'B');

$this->iconoContactoNota('whatsapp', 51, 32.5);
$this->escribirNota(57, 32.5, 41, '951 287 04 99', 8.7, 'B');

// DIRECCIÓN Y RESPONSABLE
$this->iconoContactoNota('ubicacion', 9, 40);
$this->escribirNota(15, 40, 82, 'CARRETERA YUCUDAA KM. 34 // SAN MARTÍN HUAMELULPAM, TLAX. OAX.', 5.5, 'B');
$this->escribirNota(15, 45.2, 82, 'HERMINIO PÉREZ BERNABÉ', 6, 'B');

// FECHA DENTRO DE LA FRANJA VERDE
$fecha = strtotime($venta['created_at'] ?? '') ?: time();
$valoresFecha = [date('d', $fecha), date('m', $fecha), date('Y', $fecha)];
$etiquetasFecha = ['DÍA', 'MES', 'AÑO'];

foreach ($valoresFecha as $i => $valor) {
    $fx = 100 + ($i * 10.6);
    $this->SetFillColor(239, 248, 242);
    $this->SetDrawColor(255, 255, 255);
    $this->Rect($fx, 32, 10.2, 16, 'DF');

    $this->SetTextColor(34, 112, 77);
    $this->escribirNota($fx + 0.2, 33.5, 9.8, $etiquetasFecha[$i], 5.3, 'B', 'C');

    $this->SetTextColor(35, 52, 42);
    $this->escribirNota($fx + 0.2, 40, 9.8, $valor, 8.3, 'B', 'C');
}

$this->SetDrawColor(72, 150, 116);


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


private function marcaAguaNota() {
    // TEXTO TENUE DETRÁS DE LOS RENGLONES
    $this->SetTextColor(231, 241, 234);
    $this->SetFont('Arial', 'B', 29);
    $this->SetXY(27, 116);
    $this->Cell(103, 16, $this->textoNota('San Martín'), 0, 0, 'C');

    // LOGO DESVANECIDO EN LA PARTE INFERIOR IZQUIERDA
    $ruta = __DIR__ . '/../recursos/logo.png';
    if (!is_file($ruta) || !function_exists('imagecreatefrompng')) return;

    $original = @imagecreatefrompng($ruta);
    if (!$original) return;

    $n = 160;
    $mini = imagecreatetruecolor($n, $n);
    imagealphablending($mini, false);
    imagesavealpha($mini, true);
    $transparente = imagecolorallocatealpha($mini, 255, 255, 255, 127);
    imagefill($mini, 0, 0, $transparente);

    imagecopyresampled($mini, $original, 0, 0, 0, 0, $n, $n, imagesx($original), imagesy($original));
    imagedestroy($original);

    $fondo = imagecreatetruecolor($n, $n);
    $intensidad = 0.10;

    for ($y = 0; $y < $n; $y++) {
        for ($x = 0; $x < $n; $x++) {
            $p = imagecolorat($mini, $x, $y);
            $alfa = ($p >> 24) & 127;
            $opacidad = $intensidad * (1 - $alfa / 127);

            $r = (int)(255 - (255 - (($p >> 16) & 255)) * $opacidad);
            $g = (int)(255 - (255 - (($p >> 8) & 255)) * $opacidad);
            $b = (int)(255 - (255 - ($p & 255)) * $opacidad);

            imagesetpixel($fondo, $x, $y, ($r << 16) | ($g << 8) | $b);
        }
    }

    imagedestroy($mini);

    $temporal = tempnam(sys_get_temp_dir(), 'wm_nota_');
    if ($temporal) {
        if (@imagejpeg($fondo, $temporal, 85)) {
            $this->Image($temporal, 12, 156, 32, 32, 'JPG');
        }
        @unlink($temporal);
    }

    imagedestroy($fondo);
}


private function pieNotaFisica($venta, $firmaRuta = '') {
    $this->SetTextColor(35, 45, 39);
    $this->escribirNota(8, 157, 79, 'Forma de pago: ' . ($venta['payment_method'] ?? ''), 7.8, 'B');
    $this->escribirNota(8, 164, 79, 'Referencia: ' . (!empty($venta['bank_reference']) ? $venta['bank_reference'] : '—'), 7.5);

    if (!empty($venta['notes'])) {
        $this->escribirNota(8, 173, 79, 'Observaciones:', 7.5, 'B');
        $this->escribirNota(8, 178, 79, $venta['notes'], 7);
    }

    //$this->Line(19, 190, 78, 190);


if ($firmaRuta !== '' && is_file($firmaRuta)) {
    $this->Image($firmaRuta, 30, 176, 35, 12, 'PNG');
}

    $this->escribirNota(19, 191, 59, ($firmaRuta !== '' ? 'Firma de autorización' : 'Recibí / Firma'), 7.2, '', 'C');

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
    $this->escribirNota(7, 207, 126, 'Documento de venta. I.V.A. no desglosado.', 6, '', 'C');
}

}


class notaVentaCompleta extends FPDF {
    private $venta = [];
    private $firma = '';
    private $firmante = '';
    private $marcaTemporal = '';

    private function texto($valor) {
        $resultado = @iconv('UTF-8', 'Windows-1252//TRANSLIT', (string)$valor);
        return $resultado === false ? utf8_decode((string)$valor) : $resultado;
    }

    private function dinero($valor) {
        return '$'.number_format((float)$valor, 2, '.', ',');
    }

    public function __construct($venta, $firma, $firmante) {
        parent::__construct('P','mm','A4');
        $this->venta = $venta;
        $this->firma = $firma;
        $this->firmante = $firmante;
        $this->SetMargins(15,61,15);
        $this->SetAutoPageBreak(true,25);
    }

    private function imagenAjustada($ruta,$x,$y,$maxW,$maxH) {
        if (!is_file($ruta)) return;
        $dim = @getimagesize($ruta);
        if (!$dim || !$dim[0] || !$dim[1]) return;
        $factor = min($maxW/$dim[0], $maxH/$dim[1]);
        $w=$dim[0]*$factor; $h=$dim[1]*$factor;
        $this->Image($ruta,$x+($maxW-$w)/2,$y+($maxH-$h)/2,$w,$h,'PNG');
    }

    private function prepararMarca() {
        $ruta = __DIR__.'/../recursos/logo.png';
        if (!is_file($ruta) || !function_exists('imagecreatefrompng')) return;
        $original = @imagecreatefrompng($ruta);
        if (!$original) return;
        $n=230; $base=imagecreatetruecolor($n,$n);
        $palida=imagecreatetruecolor($n,$n);
        $blanco=imagecolorallocate($base,255,255,255);
        imagefill($base,0,0,$blanco);
        imagefill($palida,0,0,imagecolorallocate($palida,255,255,255));
        imagealphablending($base,true);
        imagecopyresampled($base,$original,0,0,0,0,$n,$n,imagesx($original),imagesy($original));
        imagecopymerge($palida,$base,0,0,0,0,$n,$n,9);
        $tmp=tempnam(sys_get_temp_dir(),'marca_nv_');
        if ($tmp && @imagejpeg($palida,$tmp,88)) $this->marcaTemporal=$tmp;
        elseif ($tmp) @unlink($tmp);
        imagedestroy($palida); imagedestroy($base); imagedestroy($original);
    }

    public function Header() {
        $this->SetTextColor(34,54,43);
        if ($this->marcaTemporal) {
            $this->Image($this->marcaTemporal,66,114,78,78,'JPG');
        } else {
            $this->SetFont('Arial','B',28);
            $this->SetTextColor(238,244,239);
            $this->Text(49,152,$this->texto('SAN MARTÍN'));
        }
        $this->imagenAjustada(__DIR__.'/../recursos/logo.png',15,12,25,25);
        $this->imagenAjustada(__DIR__.'/../recursos/maquinaria.png',164,12,31,25);
        $this->SetXY(43,11);
        $this->SetFont('Arial','B',15);
        $this->SetTextColor(21,93,65);
        $this->Cell(118,7,$this->texto('CORPORATIVO SAN MARTÍN'),0,1,'C');
        $this->SetX(43);
        $this->SetFont('Arial','B',8.5);
        $this->SetTextColor(40,50,44);
        $this->Cell(118,5,$this->texto('RENTA DE MAQUINARIA Y VENTA DE MATERIALES'),0,1,'C');
        $this->SetX(43);
        $this->Cell(118,4,$this->texto('PARA LA CONSTRUCCIÓN'),0,1,'C');
        $this->SetX(43);
        $this->SetFont('Arial','',8);
        $this->Cell(118,5,$this->texto('RFC: QUSA8001193T1'),0,1,'C');
        $this->SetX(41);
        $this->SetFont('Arial','',7.2);
        $this->Cell(122,5,'953 55 212 53  |  951 287 04 99',0,1,'C');
        $this->SetDrawColor(18,123,79);
        $this->SetLineWidth(.55);
        $this->Line(15,45,195,45);
        $this->SetY(61);
    }

    public function Footer() {
        $this->SetY(-20);
        $this->SetDrawColor(29,130,84);
        $this->Line(15,$this->GetY(),195,$this->GetY());
        $this->SetY(-17);
        $this->SetFont('Arial','',7.3);
        $this->SetTextColor(92,103,96);
        $this->Cell(160,5,$this->texto('CARRETERA A YUCUDAA KM. 34+800 · SAN MARTÍN HUAMELULPAM · TLAXIACO, OAXACA'),0,0,'L');
        $this->Cell(20,5,$this->texto('Pág. ').$this->PageNo(),0,0,'R');
    }

    private function encabezadoTabla() {
        $this->SetFillColor(226,243,233);
        $this->SetTextColor(24,96,67);
        $this->SetFont('Arial','B',8);
        $this->Cell(105,9,$this->texto('DESCRIPCIÓN / CONCEPTO'),1,0,'L',true);
        $this->Cell(18,9,'CANT.',1,0,'C',true);
        $this->Cell(27,9,'P. UNIT.',1,0,'R',true);
        $this->Cell(30,9,'IMPORTE',1,1,'R',true);
    }

    private function lineas($texto,$ancho) {
        $palabras=preg_split('/\s+/', trim($texto));
        $filas=[]; $actual='';
        foreach ($palabras as $palabra) {
            $prueba=$actual===''?$palabra:$actual.' '.$palabra;
            if ($this->GetStringWidth($prueba)<=$ancho-4) {
                $actual=$prueba; continue;
            }
            if ($actual!=='') {$filas[]=$actual;$actual='';}
            while ($this->GetStringWidth($palabra)>$ancho-4 && strlen($palabra)>1) {
                $trozo='';
                while (strlen($palabra) && $this->GetStringWidth($trozo.$palabra[0])<=$ancho-4) {
                    $trozo.=$palabra[0];
                    $palabra=substr($palabra,1);
                }
                if ($trozo==='') {$trozo=$palabra[0];$palabra=substr($palabra,1);}
                $filas[]=$trozo;
            }
            $actual=$palabra;
        }
        if ($actual!=='' || !$filas) $filas[]=$actual;
        return $filas;
    }

    private function filaArticulo($r) {
        $this->SetFont('Arial','',8.5);
        $this->SetTextColor(37,49,41);
        $desc=$this->texto(trim((string)($r['manual_name']??'')));
        if (!empty($r['manual_code'])) $desc.=' ['.$this->texto($r['manual_code']).']';
        $filas=$this->lineas($desc,105);
        $h=max(9,count($filas)*5+3);
        if ($this->GetY()+$h>263) {
            $this->AddPage();
            $this->encabezadoTabla();
            $this->SetFont('Arial','',8.5);
            $this->SetTextColor(37,49,41);
        }
        $x=$this->GetX(); $y=$this->GetY();
        foreach ([105,18,27,30] as $w) {
            $this->Rect($this->GetX(),$y,$w,$h);
            $this->SetX($this->GetX()+$w);
        }
        $this->SetXY($x+2,$y+1.5);
        foreach ($filas as $linea) $this->Cell(101,5,$linea,0,2);
        $this->SetXY($x+105,$y+2);
        $this->Cell(18,5,number_format((float)$r['quantity'],2,'.',','),0,0,'C');
        $this->Cell(27,5,$this->dinero($r['price']),0,0,'R');
        $this->Cell(30,5,$this->dinero($r['total']),0,0,'R');
        $this->SetXY($x,$y+$h);
    }

    private function espacio($alto) {
        if ($this->GetY()+$alto>263) $this->AddPage();
    }

    public function generarArchivo() {
        $this->prepararMarca();
        $this->AddPage();
        $this->SetFont('Arial','B',12);
        $this->SetTextColor(28,99,69);
        $this->Cell(130,9,$this->texto('NOTA DE VENTA · FORMATO CORPORATIVO'),0,0,'L');
        $this->SetFont('Arial','B',9);
        $this->SetTextColor(50,60,54);
        $this->Cell(50,9,$this->texto('Folio: '.$this->venta['note_folio']),0,1,'R');
        $this->SetFont('Arial','',8.5);
        $this->Cell(180,6,$this->texto('Fecha: '.date('d/m/Y H:i',strtotime($this->venta['created_at']))),0,1);
        $this->SetFillColor(247,250,248);
        $this->SetFont('Arial','B',9);
        $this->Cell(180,7,$this->texto('DATOS DEL CLIENTE'),0,1,'L',true);
        $this->SetFont('Arial','',9);
        $this->MultiCell(180,5,$this->texto('Cliente: '.($this->venta['client_name']??'')));
        $this->MultiCell(180,5,$this->texto('Dirección: '.($this->venta['client_address']?:'No especificada')));
        $this->Cell(95,6,$this->texto('Teléfono: '.($this->venta['client_phone']?:'—')),0,0);
        $this->Cell(85,6,$this->texto('Vendedor: '.($this->venta['seller_name']??'')),0,1);
        $this->Ln(6);
        $this->espacio(14);
        $this->encabezadoTabla();
        foreach ($this->venta['items'] as $r) $this->filaArticulo($r);
        $this->Ln(7);
        $this->espacio(45);
        $this->SetFont('Arial','',8.5);
        $this->SetTextColor(45,55,48);
        $this->Cell(110,7,$this->texto('Forma de pago: '.($this->venta['payment_method']??'')),0,0);
        $this->SetFont('Arial','B',9);
        $this->Cell(42,7,'TOTAL',0,0,'R');
        $this->Cell(28,7,$this->dinero($this->venta['total_amount']),0,1,'R');
        $this->SetFont('Arial','',8.5);
        $this->Cell(110,7,$this->texto('Referencia: '.($this->venta['bank_reference']?:'—')),0,0);
        $this->Cell(42,7,'Recibido',0,0,'R');
        $this->Cell(28,7,$this->dinero($this->venta['initial_paid']),0,1,'R');
        $this->Cell(110,7,'',0,0);
        $this->SetFont('Arial','B',9);
        $this->SetTextColor(17,105,68);
        $this->Cell(42,7,'Saldo pendiente',0,0,'R');
        $this->Cell(28,7,$this->dinero($this->venta['pending_amount']),0,1,'R');
        if (!empty($this->venta['notes'])) {
            $this->espacio(18);
            $this->Ln(5);
            $this->SetFont('Arial','',8.5);
            $this->SetTextColor(44,54,47);
            $this->MultiCell(180,5,$this->texto('Observaciones: '.$this->venta['notes']));
        }
        $this->Ln(7);
        $this->espacio(45);
        $this->SetFont('Arial','',8.5);
        $this->SetTextColor(60,70,63);
        $this->MultiCell(180,5,$this->texto('Documento comercial de venta. Conserve este comprobante para cualquier aclaración sobre su pedido.'));
        $this->Ln(4);
        $y=$this->GetY();
        if ($this->firma && is_file($this->firma)) {
            $this->imagenAjustada($this->firma,78,$y,54,17);
        }
        $this->SetDrawColor(40,50,42);
        $this->Line(66,$y+19,144,$y+19);
        $this->SetY($y+21);
        $this->SetFont('Arial','B',9);
        $this->SetTextColor(40,52,44);
        $this->Cell(180,5,$this->texto($this->firmante),0,1,'C');
        $this->SetFont('Arial','',8);
        $this->Cell(180,5,$this->texto('Firma de autorización'),0,1,'C');

        $carpeta=__DIR__.'/../pdf_generados/notas_venta/';
        if (!is_dir($carpeta) && !mkdir($carpeta,0750,true) && !is_dir($carpeta)) {
            throw new RuntimeException('No se pudo crear el directorio PDF.');
        }
        if (!is_writable($carpeta)) throw new RuntimeException('Directorio PDF sin permisos de escritura.');
        $nombre='nota_corporativa_'.(int)$this->venta['id'].'_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.pdf';
        $this->Output('F',$carpeta.$nombre);
        if ($this->marcaTemporal) {
            @unlink($this->marcaTemporal);
            $this->marcaTemporal='';
        }
        return [
            'nombre'=>$nombre,
            'ruta_fisica'=>$carpeta.$nombre,
            'ruta_web'=>'pdf_generados/notas_venta/'.$nombre
        ];
    }

    public function __destruct() {
        if ($this->marcaTemporal) @unlink($this->marcaTemporal);
    }
}
