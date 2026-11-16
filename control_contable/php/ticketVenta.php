
<?php
require_once __DIR__ . "/libs/fpdf/fpdf.php";

class ticketVentaTermico extends FPDF {
    private $venta;
    private $items;
    private $anchoPapel;
    private $margen;
    private $util;
    private $yTicket = 0;
    private $medir = true;

    public function __construct($venta, $items, $ancho) {
        parent::__construct("P", "mm", [$ancho, 200]);
        $this->venta = $venta;
        $this->items = $items;
        $this->anchoPapel = (int)$ancho;
        $this->margen = $ancho === 58 ? 5 : 4;
        $this->util = $ancho - (2 * $this->margen);
        $this->SetMargins($this->margen, 3, $this->margen);
        $this->SetAutoPageBreak(false);
    }

    private function texto($valor) {
        $valor = (string)($valor === null ? "" : $valor);
        $salida = @iconv("UTF-8", "Windows-1252//TRANSLIT", $valor);
        return $salida === false ? utf8_decode($valor) : $salida;
    }

    private function dinero($valor) {
        return "$" . number_format((float)$valor, 2, ".", ",");
    }

    private function partir($texto, $ancho) {
        $lineas = [];

        foreach (explode("\n", str_replace("\r", "", $texto)) as $parrafo) {
            $actual = "";

            foreach (preg_split('/\s+/', trim($parrafo), -1, PREG_SPLIT_NO_EMPTY) as $palabra) {
                $prueba = $actual === "" ? $palabra : $actual . " " . $palabra;

                if ($this->GetStringWidth($prueba) <= $ancho) {
                    $actual = $prueba;
                    continue;
                }

                if ($actual !== "") $lineas[] = $actual;
                $actual = "";

                while ($palabra !== "" && $this->GetStringWidth($palabra) > $ancho) {
                    $parte = "";

                    while ($palabra !== "" && $this->GetStringWidth($parte . $palabra[0]) <= $ancho) {
                        $parte .= $palabra[0];
                        $palabra = substr($palabra, 1);
                    }

                    if ($parte === "") {
                        $parte = $palabra[0];
                        $palabra = substr($palabra, 1);
                    }

                    $lineas[] = $parte;
                }

                $actual = $palabra;
            }

            $lineas[] = $actual;
        }

        return $lineas;
    }

    private function bloque($texto, $tam, $alto, $estilo = "", $alinear = "L") {
        $this->SetFont("Arial", $estilo, $tam);
        $lineas = $this->partir($this->texto($texto), $this->util - 1);

        foreach ($lineas as $linea) {
            if (!$this->medir) {
                $this->SetXY($this->margen, $this->yTicket);
                $this->Cell($this->util, $alto, $linea, 0, 0, $alinear);
            }

            $this->yTicket += $alto;
        }
    }

    private function columnas($izquierda, $derecha, $tam, $alto, $estilo = "") {
        $this->SetFont("Arial", $estilo, $tam);

        $w1 = $this->util * 0.64;
        $w2 = $this->util - $w1;

        $l1 = $this->partir($this->texto($izquierda), $w1 - 1);
        $l2 = $this->partir($this->texto($derecha), $w2 - 1);

        $lineas = max(count($l1), count($l2));

        for ($i = 0; $i < $lineas; $i++) {
            if (!$this->medir) {
                $this->SetXY($this->margen, $this->yTicket);
                $this->Cell($w1, $alto, $l1[$i] ?? "", 0, 0, "L");
                $this->Cell($w2, $alto, $l2[$i] ?? "", 0, 0, "R");
            }

            $this->yTicket += $alto;
        }
    }

    private function separador() {
        if (!$this->medir) {
            $this->SetDrawColor(90, 90, 90);

            for ($x = $this->margen; $x < $this->anchoPapel - $this->margen; $x += 2.8) {
                $this->Line(
                    $x,
                    $this->yTicket + 1,
                    min($x + 1.3, $this->anchoPapel - $this->margen),
                    $this->yTicket + 1
                );
            }
        }

        $this->yTicket += 3;
    }

    private function dibujar($soloMedir) {
        $this->medir = $soloMedir;
        $this->yTicket = 3;

        $pequeno = $this->anchoPapel === 58;
        $cuerpo = $pequeno ? 7.4 : 8.4;
        $linea = $pequeno ? 3.6 : 4.1;

        $logo = __DIR__ . "/../recursos/logo.png";

        if (is_file($logo)) {
            if (!$soloMedir) {
                $this->Image($logo, ($this->anchoPapel - 14) / 2, $this->yTicket, 14, 14, "PNG");
            }
            $this->yTicket += 15.5;
        }

        // ENCABEZADO
        $this->bloque("CORPORATIVO SAN MARTÍN", $pequeno ? 10 : 11, 4.8, "B", "C");
        $this->bloque("Renta de maquinaria y materiales para construcción", $pequeno ? 6.8 : 7.6, 3.4, "", "C");
        $this->bloque("RFC: QUSA8001193T1", 7.4, 3.8, "", "C");
        $this->bloque("Tel. 220 286 2755 / 951 287 04 99", $pequeno ? 6.6 : 7.7, 3.5, "", "C");
        $this->bloque("jpebernabe@hotmail.com", 7, 3.6, "", "C");
        $this->separador();

        // INFORMACIÓN DE VENTA
        $folio = 'DIG-' . trim((string)$this->venta["note_folio"]);
        if ($folio === "") $folio = trim((string)$this->venta["ticket_barcode"]);
        if ($folio === "") $folio = "V-" . $this->venta["id"];

        $titulo = $this->venta["sale_kind"] === "RAPIDA" ? "TICKET" : "NOTA DE VENTA";

        $this->bloque($titulo . " " . $folio, $pequeno ? 8.5 : 9, 4.5, "B", "C");

        $fecha = strtotime((string)$this->venta["created_at"]);
        $this->bloque("Fecha: " . ($fecha ? date("d/m/Y H:i", $fecha) : "No disponible"), $cuerpo, $linea);
        $this->bloque("Cliente: " . ($this->venta["client_name"] ?: "Mostrador"), $cuerpo, $linea);

        if (!empty($this->venta["client_address"])) {
            $this->bloque("Dirección: " . $this->venta["client_address"], $cuerpo, $linea);
        }

        if (!empty($this->venta["client_phone"])) {
            $this->bloque("Teléfono: " . $this->venta["client_phone"], $cuerpo, $linea);
        }

        if (!empty($this->venta["seller_name"])) {
            $this->bloque("Atendió: " . $this->venta["seller_name"], $cuerpo, $linea);
        }

        $this->bloque("Pago: " . $this->venta["payment_method"], $cuerpo, $linea);

        if (!empty($this->venta["bank_reference"])) {
            $this->bloque("Referencia: " . $this->venta["bank_reference"], $cuerpo, $linea);
        }

        $this->separador();

        // ARTÍCULOS
        $this->bloque("ARTÍCULOS", $pequeno ? 7.5 : 8.4, 4.2, "B", "C");

        foreach ($this->items as $item) {
            $nombre = trim((string)$item["descripcion"]);

            if (!empty($item["manual_code"])) {
                $nombre .= " [" . $item["manual_code"] . "]";
            }

            $this->bloque($nombre, $pequeno ? 7.5 : 8.5, $linea, "B");

            $cant = rtrim(rtrim(number_format((float)$item["quantity"], 2, ".", ""), "0"), ".");
            $unidad = trim((string)$item["unidad"]);

            $detalle = $cant .
                ($unidad !== "" && $unidad !== "-" ? " " . $unidad : "") .
                " x " . $this->dinero($item["price"]);

            $this->columnas(
                $detalle,
                $this->dinero($item["total"]),
                $pequeno ? 7.1 : 8.1,
                $linea
            );

            $this->yTicket += 1;
        }

        $this->separador();

        // TOTALES
        $this->columnas("TOTAL", $this->dinero($this->venta["total_amount"]), $pequeno ? 10 : 11, 5.5, "B");

        $saldo = (float)$this->venta["saldo_ticket"];

        if ($this->venta["sale_kind"] === "NOTA" && $saldo > 0) {
            $this->columnas("Anticipo inicial", $this->dinero($this->venta["initial_paid"]), $cuerpo, $linea);
            $this->columnas("Saldo actual", $this->dinero($saldo), $cuerpo, $linea, "B");
        } elseif ($saldo <= 0) {
            $this->bloque("PAGADO", $pequeno ? 8.5 : 9.5, 4.5, "B", "C");
        }

        $this->yTicket += 2;

        // PIE DE TICKET
        $this->bloque("Gracias por su preferencia", $cuerpo, 4.1, "I", "C");
        $this->bloque(
            "Carretera a Yucudaa km 34.5, San Martín Huamelúlpam, Tlaxiaco, Oaxaca",
            $pequeno ? 6.3 : 7.1,
            3.4,
            "",
            "C"
        );
        $this->bloque("Este documento no es una factura.", $pequeno ? 6.3 : 7.1, 3.5, "", "C");
        $this->yTicket += 5;
    }

    public function generarArchivo() {
        // Primera pasada: medir la altura real.
        $this->dibujar(true);
        $alto = max(65, (int)ceil($this->yTicket + 2));

        if ($alto > 1200) {
            throw new RuntimeException("El ticket es demasiado largo para este formato de prueba.");
        }

        // Segunda pasada: crear el ticket con la altura calculada.
        $this->AddPage("P", [$this->anchoPapel, $alto]);
        $this->dibujar(false);

        $carpeta = __DIR__ . "/../pdf_generados/tickets/";

        if (!is_dir($carpeta) && !mkdir($carpeta, 0750, true) && !is_dir($carpeta)) {
            throw new RuntimeException("No se pudo crear la carpeta de tickets.");
        }

        if (!is_writable($carpeta)) {
            throw new RuntimeException("La carpeta de tickets no permite escribir.");
        }

        $nombre = "ticket_" . (int)$this->venta["id"] . "_" .
            $this->anchoPapel . "mm_" . bin2hex(random_bytes(6)) . ".pdf";

        $this->Output("F", $carpeta . $nombre);

        return [
            "nombre" => $nombre,
            "ruta_web" => "pdf_generados/tickets/" . $nombre
        ];
    }
}
