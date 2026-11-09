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

    public function generarArchivo($venta)
    {
        $this->AddPage();
        $verde = array(27, 140, 105);
        $gris = array(90, 90, 90);

        $empresa = "Tu empresa";
        $slogan = "Nota de venta";
        $telefono = "";
        $direccion = "";

        $logo = __DIR__ . "/../img/logo_nota.png"; // déjalo pendiente; si no existe, no pasa nada

        if (file_exists($logo)) {
            $this->Image($logo, 8, 8, 22);
        }

        $this->SetFont("Arial", "B", 15);
        $this->SetTextColor($verde[0], $verde[1], $verde[2]);
        $this->SetXY(33, 9);
        $this->Cell(65, 6, utf8_decode($empresa), 0, 1, "L");

        $this->SetFont("Arial", "", 8);
        $this->SetTextColor($gris[0], $gris[1], $gris[2]);
        $this->SetX(33);
        $this->Cell(90, 4, utf8_decode($slogan), 0, 1, "L");

        if ($telefono !== "") {
            $this->SetX(33);
            $this->Cell(90, 4, utf8_decode("Tel. " . $telefono), 0, 1, "L");
        }

        if ($direccion !== "") {
            $this->SetX(33);
            $this->MultiCell(90, 4, utf8_decode($direccion), 0, "L");
        }

        $this->Ln(2);

        $this->SetFillColor($verde[0], $verde[1], $verde[2]);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont("Arial", "B", 10);
        $this->Cell(126, 7, utf8_decode("NOTA DE VENTA"), 0, 1, "C", true);

        $this->SetTextColor(0, 0, 0);
        $this->SetFont("Arial", "", 9);

        $this->Cell(20, 6, "Folio:", 0, 0, "L");
        $this->SetFont("Arial", "B", 9);
        $this->Cell(45, 6, utf8_decode($venta["note_folio"]), 0, 0, "L");
        $this->SetFont("Arial", "", 9);
        $this->Cell(18, 6, "Fecha:", 0, 0, "L");
        $this->Cell(43, 6, $this->fechaMx($venta["created_at"]), 0, 1, "L");

        $this->Cell(20, 6, "Cliente:", 0, 0, "L");
        $this->Cell(106, 6, utf8_decode($venta["client_name"]), 0, 1, "L");

        $this->Cell(20, 6, utf8_decode("Dirección:"), 0, 0, "L");
        $this->MultiCell(106, 6, utf8_decode($venta["client_address"] !== "" ? $venta["client_address"] : "-"), 0, "L");

        $this->Cell(20, 6, utf8_decode("Teléfono:"), 0, 0, "L");
        $this->Cell(43, 6, utf8_decode($venta["client_phone"] !== "" ? $venta["client_phone"] : "-"), 0, 0, "L");
        $this->Cell(20, 6, "Vendedor:", 0, 0, "L");
        $this->Cell(43, 6, utf8_decode($venta["seller_name"] !== "" ? $venta["seller_name"] : "-"), 0, 1, "L");

        $this->Ln(2);

        $this->SetFillColor(235, 246, 240);
        $this->SetDrawColor(180, 205, 190);
        $this->SetFont("Arial", "B", 8);
        $this->Cell(12, 7, "Cant.", 1, 0, "C", true);
        $this->Cell(68, 7, utf8_decode("Descripción"), 1, 0, "C", true);
        $this->Cell(22, 7, "P. Unit.", 1, 0, "C", true);
        $this->Cell(24, 7, "Importe", 1, 1, "C", true);

        $this->SetFont("Arial", "", 8);

        if (!empty($venta["items"])) {
            foreach ($venta["items"] as $item) {
                $yInicial = $this->GetY();
                $xInicial = $this->GetX();

                $cantidad = number_format((float)$item["quantity"], 2, ".", "");
                $descripcion = $item["manual_name"];
                if (!empty($item["manual_code"])) {
                    $descripcion .= " [" . $item["manual_code"] . "]";
                }

                $this->Cell(12, 8, $cantidad, 1, 0, "C");

                $this->MultiCell(68, 4, utf8_decode($descripcion), 1, "L");
                $altoDesc = $this->GetY() - $yInicial;

                $this->SetXY($xInicial + 80, $yInicial);
                $this->Cell(22, $altoDesc, $this->moneda($item["price"]), 1, 0, "R");
                $this->Cell(24, $altoDesc, $this->moneda($item["total"]), 1, 1, "R");
            }
        } else {
            $this->Cell(126, 8, "Sin conceptos", 1, 1, "C");
        }

        $this->Ln(3);

        $this->SetFont("Arial", "", 9);
        $this->Cell(84, 6, utf8_decode("Forma de pago: " . $venta["payment_method"]), 0, 0, "L");
        $this->Cell(18, 6, "Subtotal:", 0, 0, "R");
        $this->SetFont("Arial", "B", 9);
        $this->Cell(24, 6, $this->moneda($venta["total_amount"]), 0, 1, "R");

        $this->SetFont("Arial", "", 9);
        $this->Cell(84, 6, utf8_decode("Referencia: " . ($venta["bank_reference"] !== "" ? $venta["bank_reference"] : "-")), 0, 0, "L");
        $this->Cell(18, 6, "Abono:", 0, 0, "R");
        $this->SetFont("Arial", "B", 9);
        $this->Cell(24, 6, $this->moneda($venta["initial_paid"]), 0, 1, "R");

        $this->SetFont("Arial", "", 9);
        $this->Cell(84, 6, "", 0, 0, "L");
        $this->Cell(18, 6, "Saldo:", 0, 0, "R");
        $this->SetFont("Arial", "B", 9);
        $this->Cell(24, 6, $this->moneda($venta["pending_amount"]), 0, 1, "R");

        $this->SetFont("Arial", "", 10);
        $this->Cell(84, 8, "", 0, 0, "L");
        $this->Cell(18, 8, "TOTAL:", 0, 0, "R");
        $this->SetFont("Arial", "B", 12);
        $this->SetTextColor($verde[0], $verde[1], $verde[2]);
        $this->Cell(24, 8, $this->moneda($venta["total_amount"]), 0, 1, "R");

        $this->SetTextColor(0, 0, 0);

        if (!empty($venta["notes"])) {
            $this->Ln(3);
            $this->SetFont("Arial", "B", 9);
            $this->Cell(126, 5, "Observaciones:", 0, 1, "L");
            $this->SetFont("Arial", "", 8);
            $this->MultiCell(126, 4, utf8_decode($venta["notes"]), 1, "L");
        }

        $this->Ln(10);
        $this->SetFont("Arial", "", 8);
        $this->Cell(126, 5, "__________________________________", 0, 1, "C");
        $this->Cell(126, 5, utf8_decode("Recibí / Firma"), 0, 1, "C");

        $carpetaFisica = __DIR__ . "/../pdf_generados/notas_venta/";
        
if (!is_dir($carpetaFisica) && !mkdir($carpetaFisica, 0750, true) && !is_dir($carpetaFisica)) {
    throw new RuntimeException("No fue posible crear el directorio de las notas.");
}

if (!is_writable($carpetaFisica)) {
    throw new RuntimeException("El directorio de notas no tiene permisos de escritura.");
}

        $folioArchivo = $this->limpiarNombreArchivo($venta["note_folio"] !== "" ? $venta["note_folio"] : ("venta_" . $venta["id"]));
        $nombreArchivo = "nota_" . $folioArchivo . "_" . date("Ymd_His") . ".pdf";
        $rutaFisica = $carpetaFisica . $nombreArchivo;

        $this->Output("F", $rutaFisica);

        return array(
            "nombre" => $nombreArchivo,
            "ruta_fisica" => $rutaFisica,
            "ruta_web" => "pdf_generados/notas_venta/" . $nombreArchivo
        );
    }
}