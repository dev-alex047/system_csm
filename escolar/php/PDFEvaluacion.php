<?php

require_once __DIR__ . '/libs/fpdf/fpdf.php';

class PDFEvaluacion extends FPDF
{
    public function texto($texto)
    {
        $texto = (string) $texto;
        $convertido = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $texto);
        return $convertido !== false ? $convertido : utf8_decode($texto);
    }

    public function cellFit($w, $h, $txt, $border = 0, $ln = 0, $align = 'L', $fill = false, $maxSize = 7, $minSize = 4.8, $style = '')
    {
        $txt = $this->texto($txt);
        $size = $maxSize;
        $this->SetFont('Arial', $style, $size);

        while ($size > $minSize && $this->GetStringWidth($txt) > ($w - 0.12)) {
            $size -= 0.2;
            $this->SetFont('Arial', $style, $size);
        }

        $this->Cell($w, $h, $txt, $border, $ln, $align, $fill);
    }

    public function normalizarTextoEvaluacion($texto)
    {
        $texto = trim((string) $texto);
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        if ($ascii !== false) $texto = $ascii;

        return strtolower($texto);
    }

    public function valorEvaluacion($array, $key, $default = '')
    {
        return is_array($array) && isset($array[$key]) ? $array[$key] : $default;
    }

    public function rutaLogo($archivo){
    $ruta = __DIR__ . '/../recursos/' . $archivo;

    return file_exists($ruta)
        ? $ruta
        : '';
}

public function imagenAjustada(
    $doc,
    $ruta,
    $x,
    $y,
    $maxW,
    $maxH
) {
    if ($ruta === '' || !file_exists($ruta)) {
        return;
    }

    $info = @getimagesize($ruta);

    if (!$info || empty($info[0]) || empty($info[1])) {
        return;
    }

    $anchoOriginal = (float)$info[0];
    $altoOriginal = (float)$info[1];

    $escala = min(
        $maxW / $anchoOriginal,
        $maxH / $altoOriginal
    );

    $w = $anchoOriginal * $escala;
    $h = $altoOriginal * $escala;

    $xFinal =
        $x +
        (($maxW - $w) / 2);

    $yFinal =
        $y +
        (($maxH - $h) / 2);

    $doc->Image(
        $ruta,
        $xFinal,
        $yFinal,
        $w,
        $h
    );
}

public function trimestreRomano($periodo)
{
    $periodo = $this->normalizarTextoEvaluacion($periodo);

    if (strpos($periodo, 'primer') !== false) {
        return 'I';
    }

    if (strpos($periodo, 'segundo') !== false) {
        return 'II';
    }

    if (strpos($periodo, 'tercer') !== false) {
        return 'III';
    }

    return strtoupper($periodo);
}

public function gradoTexto($grado)
{
    $numero = (int) preg_replace('/[^0-9]/', '', (string) $grado);

    $grados = array(
        1 => 'PRIMERO',
        2 => 'SEGUNDO',
        3 => 'TERCERO',
        4 => 'CUARTO',
        5 => 'QUINTO',
        6 => 'SEXTO'
    );

    if (isset($grados[$numero])) {
        return $grados[$numero];
    }

    return mb_strtoupper(
        (string) $grado,
        'UTF-8'
    );
}

public function encabezadoRegistro(
    $doc,
    $data,
    $reporteFinal = false
) {
    $escuela = $this->valorEvaluacion(
        $data,
        'escuela',
        array()
    );

    $nombreInstitucion = $this->valorEvaluacion(
        $escuela,
        'nombre',
        'INSTITUTO ESTATAL DE EDUCACION PUBLICA DE OAXACA'
    );

    $nombreEscuela = $this->valorEvaluacion(
        $escuela,
        'tipo',
        'ESC. SECUNDARIA GENERAL "SIGLO XXI"'
    );

    $clave = $this->valorEvaluacion(
        $escuela,
        'clave',
        'ES-372-180'
    );

    $cct = $this->valorEvaluacion(
        $escuela,
        'cct',
        ''
    );

    $profesor = $this->valorEvaluacion(
        $data,
        'profesor',
        ''
    );

    $asignatura = $this->valorEvaluacion(
        $data,
        'asignatura',
        ''
    );

    $periodo = $this->valorEvaluacion(
        $data,
        'periodo',
        ''
    );

    $grado = $this->gradoTexto(
        $this->valorEvaluacion(
            $data,
            'grado',
            ''
        )
    );

    $grupo = mb_strtoupper(
        $this->valorEvaluacion(
            $data,
            'grupo',
            ''
        ),
        'UTF-8'
    );

    $ciclo = $this->valorEvaluacion(
        $data,
        'ciclo',
        ''
    );

    $logoIeepo = $this->rutaLogo('ieepo.png');

    $logoSiglo = $this->rutaLogo('siglo21.png');

    if ($logoIeepo !== '') {
        $doc->Image(
            $logoIeepo,
            1.05,
            0.35,
            1.45
        );
    }

    if ($logoSiglo !== '') {
        $doc->Image(
            $logoSiglo,
            18.85,
            0.32,
            1.55
        );
    }

    $doc->SetTextColor(55, 55, 55);

    $doc->SetFont(
        'Arial',
        'B',
        8.2
    );

    $doc->SetXY(2.55, 0.38);

    $doc->Cell(
        16.25,
        0.30,
        $doc->texto(
            mb_strtoupper(
                $nombreInstitucion,
                'UTF-8'
            )
        ),
        0,
        1,
        'C'
    );

    $doc->SetFont(
        'Arial',
        'B',
        7.4
    );

    $doc->SetX(2.55);

    $doc->Cell(
        16.25,
        0.29,
        $doc->texto(
            mb_strtoupper(
                $nombreEscuela,
                'UTF-8'
            )
        ),
        0,
        1,
        'C'
    );

    $doc->SetFont(
        'Arial',
        '',
        6.6
    );

    $doc->SetX(2.55);

    $textoClave =
        'CLAVE: ' .
        $clave;

    if ($cct !== '') {
        $textoClave .=
            '          C.C.T. ' .
            $cct;
    }

    $doc->Cell(
        16.25,
        0.28,
        $doc->texto($textoClave),
        0,
        1,
        'C'
    );

    $doc->SetFont(
        'Arial',
        'B',
        7
    );

    $doc->SetX(2.55);

    $titulo = $reporteFinal
        ? 'REGISTRO FINAL DE EVALUACIONES'
        : 'REGISTRO DE EVALUACIONES';

    $doc->Cell(
        16.25,
        0.29,
        $doc->texto(
            $titulo .
            '   CICLO ESCOLAR ' .
            $ciclo
        ),
        0,
        1,
        'C'
    );

    /*
     * Profesor.
     */
    $y = 1.72;

    $doc->SetFont(
        'Arial',
        '',
        7.4
    );

    $doc->SetXY(1.0, $y);
    $doc->Cell(
        1.45,
        0.34,
        $doc->texto('PROFR.(A)'),
        0,
        0
    );

    $doc->SetXY(2.45, $y);
    $doc->cellFit(
        5.65,
        0.34,
        $profesor,
        0,
        0,
        'C',
        false,
        7.2,
        5.2
    );

    $doc->Line(
        2.45,
        $y + 0.32,
        8.10,
        $y + 0.32
    );

    /*
     * Asignatura.
     */
    $doc->SetXY(8.35, $y);
    $doc->Cell(
        1.55,
        0.34,
        $doc->texto('ASIGNATURA'),
        0,
        0
    );

    $doc->SetXY(9.90, $y);

    $doc->cellFit(
        5.0,
        0.34,
        $asignatura,
        0,
        0,
        'C',
        false,
        7.2,
        5.2
    );

    $doc->Line(
        9.90,
        $y + 0.32,
        14.90,
        $y + 0.32
    );

    /*
     * Trimestre.
     */
    $doc->SetXY(15.10, $y);

    $doc->Cell(
        1.55,
        0.34,
        $doc->texto(
            $reporteFinal
                ? 'RESULTADO'
                : 'TRIMESTRE'
        ),
        0,
        0
    );

    $doc->SetXY(16.70, $y);

    $doc->SetFont(
        'Arial',
        'B',
        8
    );

    $doc->Cell(
        3.0,
        0.34,
        $doc->texto(
            $reporteFinal
                ? 'FINAL'
                : $this->trimestreRomano(
                    $periodo
                )
        ),
        0,
        0,
        'C'
    );

    $doc->Line(
        16.70,
        $y + 0.32,
        19.70,
        $y + 0.32
    );

    /*
     * Grado y grupo.
     */
    $y = 2.20;

    $doc->SetFont(
        'Arial',
        '',
        7.5
    );

    $doc->SetXY(11.60, $y);

    $doc->Cell(
        1.0,
        0.35,
        $doc->texto('GRADO:'),
        0,
        0
    );

    $doc->SetFont(
        'Arial',
        'B',
        7.6
    );

    $doc->Cell(
        2.5,
        0.35,
        $doc->texto($grado),
        0,
        0,
        'C'
    );

    $doc->SetFont(
        'Arial',
        '',
        7.5
    );

    $doc->Cell(
        1.20,
        0.35,
        $doc->texto('GRUPO'),
        0,
        0,
        'R'
    );

    $doc->SetFont(
        'Arial',
        'B',
        7.6
    );

    $doc->Cell(
        1.30,
        0.35,
        $doc->texto(
            '"' . $grupo . '"'
        ),
        0,
        0,
        'C'
    );

    $doc->SetTextColor(20, 20, 20);
}

   public function generarPDFEvaluacion($data)
{
    if (!is_array($data)) {
        return array(
            'status' => 'error',
            'answer' => 'Los datos de la evaluación no son válidos.',
            'code' => 400
        );
    }

    $tipoReporte =
        $this->valorEvaluacion(
            $data,
            'tipo_reporte',
            'periodo'
        );

    if ($tipoReporte === 'aprovechamiento') {
        return $this->generarPDFAprovechamientoEscolar(
            $data
        );
    }

    if ($tipoReporte === 'final') {
        return $this->generarPDFFinalEvaluacion(
            $data
        );
    }

    return $this->generarPDFPeriodoEvaluacion(
        $data
    );
}

public function generarPDFAprovechamientoEscolar($data)
{
    $json = array(
        'status' => 'error',
        'answer' => 'No fue posible generar el PDF de aprovechamiento escolar.',
        'code' => 500
    );

    $resultados =
        $this->valorEvaluacion(
            $data,
            'resultados',
            array()
        );

    $ciclo =
        $this->valorEvaluacion(
            $data,
            'ciclo',
            ''
        );

    $coordinador =
        $this->valorEvaluacion(
            $data,
            'coordinador',
            ''
        );

    $subdirector =
        $this->valorEvaluacion(
            $data,
            'subdirector',
            ''
        );

    $director =
        $this->valorEvaluacion(
            $data,
            'director',
            ''
        );

    $valido = true;

    if (
        !is_array($resultados) ||
        count($resultados) === 0
    ) {
        $json['answer'] =
            'No hay datos de aprovechamiento escolar para generar el documento.';

        $json['code'] = 422;
        $valido = false;
    }

    if ($valido) {
        /*
         * HORIZONTAL.
         */
        $doc = new PDFEvaluacion(
            'L',
            'cm',
            'Letter'
        );

        $doc->SetMargins(
            0.8,
            0.45,
            0.8
        );

        $doc->SetAutoPageBreak(false);

        $doc->SetDrawColor(
            65,
            65,
            65
        );

        $doc->SetLineWidth(
            0.015
        );

        /*
         * 20 filas por hoja para conservar
         * la apariencia del formato físico.
         */
        $filasPorPagina = 20;

        $totalFilas =
            count($resultados);

        $paginas =
            max(
                1,
                (int)ceil(
                    $totalFilas /
                    $filasPorPagina
                )
            );

        for (
            $pagina = 0;
            $pagina < $paginas;
            $pagina++
        ) {
            $doc->AddPage();

            /*
             * ==========================
             * ENCABEZADO INSTITUCIONAL
             * ==========================
             */
            $doc->SetTextColor(
                35,
                35,
                35
            );

            $doc->SetFont(
                'Arial',
                'B',
                8
            );

            $xTexto = 4.10;
            $yTexto = 0.62;
            $anchoTexto = 14.20;

            $doc->SetXY(
                $xTexto,
                $yTexto
            );

            $doc->Cell(
                $anchoTexto,
                0.32,
                $doc->texto(
                    'INSTITUTO ESTATAL DE EDUCACION PUBLICA DE OAXACA'
                ),
                0,
                1,
                'L'
            );

            $doc->SetX(
                $xTexto
            );

            $doc->Cell(
                $anchoTexto,
                0.32,
                $doc->texto(
                    'UNIDAD DE EDUCACION SECUNDARIA'
                ),
                0,
                1,
                'L'
            );

            $doc->SetX(
                $xTexto
            );

            $doc->Cell(
                $anchoTexto,
                0.32,
                $doc->texto(
                    'SECRETARIA DE TRABAJOS Y CONFLICTOS DE ESCUELAS SECUNDARIAS GENERALES'
                ),
                0,
                1,
                'L'
            );

            $doc->SetX(
                $xTexto
            );

            $doc->Cell(
                $anchoTexto,
                0.32,
                $doc->texto(
                    'SUBJEFATURA TECNICO PEDAGOGICA DE ESCUELAS SECUNDARIAS GENERALES'
                ),
                0,
                1,
                'L'
            );

            /*
             * El formato original muestra 3 logos.
             * Por ahora usamos el IEEPO en los tres,
             * como pediste.
             */
           $logoIeepo = $this->rutaLogo(
    'ieepo.png'
);

$logoSegundo = $this->rutaLogo(
    'seccion_22.png'
);

$logoTercero = $this->rutaLogo(
    'mesa_tecnica.png'
);

/*
 * Cada logo queda dentro de una caja máxima
 * de 1.45 x 1.45 cm sin deformarse.
 */
$this->imagenAjustada(
    $doc,
    $logoIeepo,
    19.15,
    0.42,
    1.45,
    1.45
);

$this->imagenAjustada(
    $doc,
    $logoSegundo,
    21.20,
    0.42,
    1.45,
    1.45
);

$this->imagenAjustada(
    $doc,
    $logoTercero,
    23.25,
    0.42,
    1.45,
    1.45
);

            /*
             * Título.
             */
            $doc->SetFont(
                'Arial',
                '',
                7.5
            );

            $doc->SetXY(
                1.80,
                2.42
            );

            $doc->Cell(
                24.10,
                0.38,
                $doc->texto(
                    'APROVECHAMIENTO ESCOLAR POR GRADO Y GRUPO AL FINALIZAR EL CURSO'
                ),
                0,
                0,
                'C'
            );

            /*
             * Ciclo escolar pequeño a la derecha.
             */
            if ($ciclo !== '') {
                $doc->SetFont(
                    'Arial',
                    '',
                    5.7
                );

                $doc->SetXY(
                    22.20,
                    2.15
                );

                $doc->Cell(
                    3.70,
                    0.25,
                    $doc->texto(
                        'CICLO ESCOLAR ' .
                        $ciclo
                    ),
                    0,
                    0,
                    'R'
                );
            }

            /*
             * ==========================
             * TABLA
             * ==========================
             */
            $x = 1.85;
            $y = 2.92;

            $wAsignatura = 6.40;
            $wGrado = 1.20;
            $wGrupo = 1.20;
            $wExistencia = 2.25;
            $wAprobados = 2.65;
            $wPctAprobados = 2.50;
            $wReprobados = 2.65;
            $wPctReprobados = 2.50;
            $wPromedio = 2.60;

            $altoCabecera = 0.82;
            $altoFila = 0.53;

            $doc->SetFillColor(
                245,
                245,
                245
            );

            $doc->SetFont(
                'Arial',
                '',
                6.1
            );

            $doc->SetXY(
                $x,
                $y
            );

            $doc->cellFit(
                $wAsignatura,
                $altoCabecera,
                'ASIGNATURAS ATENDIDAS',
                1,
                0,
                'C',
                true,
                6.1,
                5,
                ''
            );

            $doc->Cell(
                $wGrado,
                $altoCabecera,
                $doc->texto('GDO.'),
                1,
                0,
                'C',
                true
            );

            $doc->Cell(
                $wGrupo,
                $altoCabecera,
                $doc->texto('GPO.'),
                1,
                0,
                'C',
                true
            );

            $doc->cellFit(
                $wExistencia,
                $altoCabecera,
                'EXISTENCIA',
                1,
                0,
                'C',
                true,
                6,
                4.8
            );

            $doc->cellFit(
                $wAprobados,
                $altoCabecera,
                'NUM. DE APROBADOS',
                1,
                0,
                'C',
                true,
                5.5,
                4.3
            );

            $doc->cellFit(
                $wPctAprobados,
                $altoCabecera,
                '% DE APROBADOS',
                1,
                0,
                'C',
                true,
                5.5,
                4.3
            );

            $doc->cellFit(
                $wReprobados,
                $altoCabecera,
                'NUM. DE REPROBADOS',
                1,
                0,
                'C',
                true,
                5.5,
                4.3
            );

            $doc->cellFit(
                $wPctReprobados,
                $altoCabecera,
                '% DE REPROBADOS',
                1,
                0,
                'C',
                true,
                5.5,
                4.3
            );

            $doc->cellFit(
                $wPromedio,
                $altoCabecera,
                'PROMEDIO GENERAL',
                1,
                0,
                'C',
                true,
                5.5,
                4.3
            );

            $y +=
                $altoCabecera;

            $inicio =
                $pagina *
                $filasPorPagina;

            /*
             * Siempre dejamos el formato
             * completo con 20 renglones.
             */
            for (
                $fila = 0;
                $fila < $filasPorPagina;
                $fila++
            ) {
                $indice =
                    $inicio +
                    $fila;

                $registro =
                    isset(
                        $resultados[
                            $indice
                        ]
                    )
                        ? $resultados[
                            $indice
                        ]
                        : null;

                $doc->SetXY(
                    $x,
                    $y
                );

                $doc->SetFillColor(
                    255,
                    255,
                    255
                );

                if ($registro !== null) {
                    $asignatura =
                        $this->valorEvaluacion(
                            $registro,
                            'asignatura',
                            ''
                        );

                    $grado =
                        $this->valorEvaluacion(
                            $registro,
                            'grado',
                            ''
                        );

                    $grupo =
                        $this->valorEvaluacion(
                            $registro,
                            'grupo',
                            ''
                        );

                    $existencia =
                        (int)$this->valorEvaluacion(
                            $registro,
                            'existencia',
                            0
                        );

                    $aprobados =
                        (int)$this->valorEvaluacion(
                            $registro,
                            'aprobados',
                            0
                        );

                    $porcentajeAprobados =
                        (float)$this->valorEvaluacion(
                            $registro,
                            'porcentaje_aprobados',
                            0
                        );

                    $reprobados =
                        (int)$this->valorEvaluacion(
                            $registro,
                            'reprobados',
                            0
                        );

                    $porcentajeReprobados =
                        (float)$this->valorEvaluacion(
                            $registro,
                            'porcentaje_reprobados',
                            0
                        );

                    $promedioGeneral =
                        $this->valorEvaluacion(
                            $registro,
                            'promedio_general',
                            null
                        );

                    $doc->SetFont(
                        'Arial',
                        'B',
                        6.3
                    );

                    $doc->cellFit(
                        $wAsignatura,
                        $altoFila,
                        mb_strtoupper(
                            $asignatura,
                            'UTF-8'
                        ),
                        1,
                        0,
                        'C',
                        true,
                        6.3,
                        4.7,
                        'B'
                    );

                    $gradoNumero =
                        preg_replace(
                            '/[^0-9]/',
                            '',
                            (string)$grado
                        );

                    $textoGrado =
                        $gradoNumero !== ''
                            ? $gradoNumero . '°'
                            : $grado;

                    $doc->SetFont(
                        'Arial',
                        'B',
                        6
                    );

                    $doc->Cell(
                        $wGrado,
                        $altoFila,
                        $doc->texto(
                            $textoGrado
                        ),
                        1,
                        0,
                        'C',
                        true
                    );

                    $doc->Cell(
                        $wGrupo,
                        $altoFila,
                        $doc->texto(
                            mb_strtoupper(
                                $grupo,
                                'UTF-8'
                            )
                        ),
                        1,
                        0,
                        'C',
                        true
                    );

                    $doc->Cell(
                        $wExistencia,
                        $altoFila,
                        (string)$existencia,
                        1,
                        0,
                        'C',
                        true
                    );

                    $doc->Cell(
                        $wAprobados,
                        $altoFila,
                        (string)$aprobados,
                        1,
                        0,
                        'C',
                        true
                    );

                    $doc->Cell(
                        $wPctAprobados,
                        $altoFila,
                        number_format(
                            $porcentajeAprobados,
                            0
                        ) . '%',
                        1,
                        0,
                        'C',
                        true
                    );

                    $doc->Cell(
                        $wReprobados,
                        $altoFila,
                        (string)$reprobados,
                        1,
                        0,
                        'C',
                        true
                    );

                    $doc->Cell(
                        $wPctReprobados,
                        $altoFila,
                        number_format(
                            $porcentajeReprobados,
                            0
                        ) . '%',
                        1,
                        0,
                        'C',
                        true
                    );

                    $doc->Cell(
                        $wPromedio,
                        $altoFila,
                        $promedioGeneral === null
                            ? ''
                            : number_format(
                                (float)$promedioGeneral,
                                1
                            ),
                        1,
                        0,
                        'C',
                        true
                    );
                } else {
                    /*
                     * Fila vacía.
                     */
                    $anchos = array(
                        $wAsignatura,
                        $wGrado,
                        $wGrupo,
                        $wExistencia,
                        $wAprobados,
                        $wPctAprobados,
                        $wReprobados,
                        $wPctReprobados,
                        $wPromedio
                    );

                    foreach (
                        $anchos as
                        $ancho
                    ) {
                        $doc->Cell(
                            $ancho,
                            $altoFila,
                            '',
                            1,
                            0,
                            'C',
                            true
                        );
                    }
                }

                $y +=
                    $altoFila;
            }

            /*
             * ==========================
             * FIRMAS
             * Solo en la última página.
             * ==========================
             */
            if (
                $pagina ===
                ($paginas - 1)
            ) {
                $y += 0.45;

                $anchoFirma = 7.40;

                /*
                 * Coordinador.
                 */
                $doc->SetFont(
                    'Arial',
                    '',
                    6.1
                );

                $doc->SetXY(
                    1.75,
                    $y
                );

                $doc->Cell(
                    $anchoFirma,
                    0.30,
                    $doc->texto(
                        'COORDINADOR(A) ACADEMICO'
                    ),
                    0,
                    0,
                    'C'
                );

                /*
                 * Subdirector.
                 */
                $doc->SetXY(
                    10.25,
                    $y
                );

                $doc->Cell(
                    $anchoFirma,
                    0.30,
                    $doc->texto(
                        'SUBDIRECTOR(A) DE LA ESCUELA'
                    ),
                    0,
                    0,
                    'C'
                );

                /*
                 * Director.
                 */
                $doc->SetXY(
                    18.75,
                    $y
                );

                $doc->Cell(
                    $anchoFirma,
                    0.30,
                    $doc->texto(
                        'DIRECTOR(A) DE LA ESCUELA'
                    ),
                    0,
                    0,
                    'C'
                );

                $y += 0.32;

                /*
                 * Nombres.
                 */
                $doc->SetFont(
                    'Arial',
                    '',
                    5.8
                );

                $doc->SetXY(
                    1.75,
                    $y
                );

                $doc->cellFit(
                    $anchoFirma,
                    0.28,
                    $coordinador,
                    0,
                    0,
                    'C',
                    false,
                    5.8,
                    4.5
                );

                $doc->SetXY(
                    10.25,
                    $y
                );

                $doc->cellFit(
                    $anchoFirma,
                    0.28,
                    $subdirector,
                    0,
                    0,
                    'C',
                    false,
                    5.8,
                    4.5
                );

                $doc->SetXY(
                    18.75,
                    $y
                );

                $doc->cellFit(
                    $anchoFirma,
                    0.28,
                    $director,
                    0,
                    0,
                    'C',
                    false,
                    5.8,
                    4.5
                );

                $y += 0.30;

                /*
                 * Líneas de firma.
                 */
                $doc->Line(
                    2.20,
                    $y,
                    8.70,
                    $y
                );

                $doc->Line(
                    10.70,
                    $y,
                    17.20,
                    $y
                );

                $doc->Line(
                    19.20,
                    $y,
                    25.70,
                    $y
                );

                $y += 0.05;

                $doc->SetFont(
                    'Arial',
                    '',
                    5.1
                );

                $doc->SetXY(
                    1.75,
                    $y
                );

                $doc->Cell(
                    $anchoFirma,
                    0.25,
                    $doc->texto(
                        'Nombre y firma'
                    ),
                    0,
                    0,
                    'C'
                );

                $doc->SetXY(
                    10.25,
                    $y
                );

                $doc->Cell(
                    $anchoFirma,
                    0.25,
                    $doc->texto(
                        'Nombre y firma'
                    ),
                    0,
                    0,
                    'C'
                );

                $doc->SetXY(
                    18.75,
                    $y
                );

                $doc->Cell(
                    $anchoFirma,
                    0.25,
                    $doc->texto(
                        'Nombre y firma'
                    ),
                    0,
                    0,
                    'C'
                );

                /*
                 * ==========================
                 * OBSERVACIONES
                 * ==========================
                 */
                $y += 0.55;

                $doc->SetFont(
                    'Arial',
                    '',
                    5.5
                );

                $doc->SetXY(
                    1.30,
                    $y
                );

                $doc->Cell(
                    2.60,
                    0.25,
                    $doc->texto(
                        'OBSERVACIONES:'
                    ),
                    0,
                    0,
                    'L'
                );

                $doc->SetFont(
                    'Arial',
                    'B',
                    5.4
                );

                $doc->SetXY(
                    4.35,
                    $y
                );

                $doc->Cell(
                    15.50,
                    0.25,
                    $doc->texto(
                        'ANEXAR AL PRESENTE INFORME LOS DOCUMENTOS SIGUIENTES'
                    ),
                    0,
                    0,
                    'L'
                );

                $y += 0.34;

                $doc->SetFont(
                    'Arial',
                    '',
                    5.2
                );

                $doc->SetXY(
                    1.35,
                    $y
                );

                $doc->Cell(
                    8.60,
                    0.25,
                    $doc->texto(
                        '1.- GUIAS DE ESTUDIOS Y CUESTIONARIOS PARA LOS E. E. R.'
                    ),
                    0,
                    0,
                    'L'
                );

                $doc->SetXY(
                    11.50,
                    $y
                );

                $doc->Cell(
                    5.30,
                    0.25,
                    $doc->texto(
                        '2.- FICHA DE NO ADEUDO'
                    ),
                    0,
                    0,
                    'L'
                );

                $doc->SetXY(
                    19.25,
                    $y
                );

                $doc->Cell(
                    7.00,
                    0.25,
                    $doc->texto(
                        '3.- INFORME DE LOS PROYECTOS DE HORAS DE FC Y SE'
                    ),
                    0,
                    0,
                    'L'
                );
            }
        }

        /*
         * ==========================
         * GUARDAR PDF
         * ==========================
         */
        $directorio =
            __DIR__ .
            '/../docs/evaluaciones';

        if (!is_dir($directorio)) {
            $json['answer'] =
                'No existe la carpeta donde se guardarán los documentos.';

            $json['directorio'] =
                $directorio;

            $valido = false;
        }

        if (
            $valido &&
            !is_writable(
                $directorio
            )
        ) {
            $json['answer'] =
                'La carpeta de documentos no tiene permisos de escritura.';

            $json['directorio'] =
                $directorio;

            $valido = false;
        }

        if ($valido) {
            $slug =
                $this->normalizarTextoEvaluacion(
                    'aprovechamiento_' .
                    $ciclo
                );

            $slug =
                preg_replace(
                    '/[^a-z0-9]+/',
                    '_',
                    $slug
                );

            $slug =
                trim(
                    $slug,
                    '_'
                );

            if ($slug === '') {
                $slug =
                    'aprovechamiento_escolar';
            }

            $archivo =
                $slug .
                '_' .
                date('Ymd_His') .
                '.pdf';

            $ruta =
                $directorio .
                '/' .
                $archivo;

            $doc->Output(
                'F',
                $ruta
            );

            if (!file_exists($ruta)) {
                $json['answer'] =
                    'FPDF terminó el proceso, pero no se encontró el archivo generado.';

                $valido = false;
            }
        }

        if ($valido) {
            $scriptName =
                isset(
                    $_SERVER[
                        'SCRIPT_NAME'
                    ]
                )
                    ? str_replace(
                        '\\',
                        '/',
                        $_SERVER[
                            'SCRIPT_NAME'
                        ]
                    )
                    : '';

            $phpDir =
                dirname(
                    $scriptName
                );

            $baseUrl =
                dirname(
                    $phpDir
                );

            if (
                $baseUrl === '/' ||
                $baseUrl === '.' ||
                $baseUrl === '\\'
            ) {
                $baseUrl = '';
            }

            $urlPdf =
                $baseUrl .
                '/docs/evaluaciones/' .
                $archivo;

            $json['status'] = 'ok';
            $json['answer'] =
                'PDF de aprovechamiento escolar generado correctamente.';
            $json['code'] = 200;
            $json['file'] =
                $archivo;
            $json['url'] =
                $urlPdf;
            $json['pdf'] =
                $urlPdf;
        }
    }

    return $json;
}

public function generarPDFFinalEvaluacion($data)
{
    $json = array(
        'status' => 'error',
        'answer' => 'No fue posible generar el PDF final.',
        'code' => 500
    );

    $periodos = $this->valorEvaluacion(
        $data,
        'periodos',
        array()
    );

    $alumnos = $this->valorEvaluacion(
        $data,
        'alumnos_final',
        array()
    );

    $asignatura = $this->valorEvaluacion(
        $data,
        'asignatura',
        ''
    );

    $grado = $this->valorEvaluacion(
        $data,
        'grado',
        ''
    );

    $grupo = $this->valorEvaluacion(
        $data,
        'grupo',
        ''
    );

    $fecha = $this->valorEvaluacion(
        $data,
        'fecha',
        date('d/m/Y')
    );

    $valido = true;

    if (!is_array($periodos) || count($periodos) === 0) {
        $json['answer'] = 'No hay periodos para generar el reporte final.';
        $json['code'] = 422;
        $valido = false;
    }

    if (
        $valido &&
        (!is_array($alumnos) || count($alumnos) === 0)
    ) {
        $json['answer'] = 'No hay alumnos para generar el reporte final.';
        $json['code'] = 422;
        $valido = false;
    }

    if ($valido) {
        $doc = new PDFEvaluacion(
            'P',
            'cm',
            'Letter'
        );

        $doc->SetMargins(
            0.9,
            0.35,
            0.9
        );

        $doc->SetAutoPageBreak(false);

        $doc->SetDrawColor(
            70,
            70,
            70
        );

        $doc->SetLineWidth(0.015);

        $totalAlumnos = count($alumnos);
        $porPagina = 30;

        $paginas = max(
            1,
            (int) ceil(
                $totalAlumnos / $porPagina
            )
        );

        $promediosFinalesGrupo = array();
        $aprobados = 0;
        $reprobados = 0;

        for (
            $pagina = 0;
            $pagina < $paginas;
            $pagina++
        ) {
            $doc->AddPage();

            /*
             * Mismo encabezado oficial
             * que el reporte por trimestre.
             */
            $this->encabezadoRegistro(
                $doc,
                $data,
                true
            );

            /*
             * Medidas.
             */
            $xInicial = 0.90;
            $yInicial = 2.85;
            $anchoTotal = 19.80;

            $wNo = 0.55;
            $wAlumno = 7.25;
            $wPromedio = 2.00;
            $wEstado = 1.00;

            $cantidadPeriodos = max(
                1,
                count($periodos)
            );

            $wPeriodosTotal =
                $anchoTotal -
                $wNo -
                $wAlumno -
                $wPromedio -
                $wEstado;

            $wPeriodo =
                $wPeriodosTotal /
                $cantidadPeriodos;

            $altoCabecera = 0.72;
            $altoFila = 0.47;

            /*
             * Cabecera de la tabla.
             */
            $doc->SetFillColor(
                245,
                245,
                245
            );

            $doc->SetFont(
                'Arial',
                'B',
                6.2
            );

            $doc->SetXY(
                $xInicial,
                $yInicial
            );

            $doc->Cell(
                $wNo,
                $altoCabecera,
                $doc->texto('N/P'),
                1,
                0,
                'C',
                true
            );

            $doc->Cell(
                $wAlumno,
                $altoCabecera,
                $doc->texto('NOMBRE DEL ALUMNO'),
                1,
                0,
                'C',
                true
            );

            /*
             * I, II, III.
             */
            foreach (
                $periodos as
                $nombrePeriodo
            ) {
                $doc->SetFont(
                    'Arial',
                    'B',
                    6.3
                );

                $doc->Cell(
                    $wPeriodo,
                    $altoCabecera,
                    $doc->texto(
                        $this->trimestreRomano(
                            $nombrePeriodo
                        )
                    ),
                    1,
                    0,
                    'C',
                    true
                );
            }

            $doc->cellFit(
                $wPromedio,
                $altoCabecera,
                'PROMEDIO FINAL',
                1,
                0,
                'C',
                true,
                5.8,
                4.3,
                'B'
            );

            $doc->Cell(
                $wEstado,
                $altoCabecera,
                $doc->texto('ESTADO'),
                1,
                0,
                'C',
                true
            );

            $y =
                $yInicial +
                $altoCabecera;

            $inicio =
                $pagina *
                $porPagina;

            /*
             * Siempre dibujamos 30 renglones.
             */
            for (
                $fila = 0;
                $fila < $porPagina;
                $fila++
            ) {
                $indiceAlumno =
                    $inicio +
                    $fila;

                $alumno =
                    isset(
                        $alumnos[
                            $indiceAlumno
                        ]
                    )
                        ? $alumnos[
                            $indiceAlumno
                        ]
                        : null;

                $doc->SetFillColor(
                    255,
                    255,
                    255
                );

                $doc->SetXY(
                    $xInicial,
                    $y
                );

                /*
                 * Número de lista.
                 */
                $doc->SetFont(
                    'Arial',
                    '',
                    5.7
                );

                $doc->Cell(
                    $wNo,
                    $altoFila,
                    (string)
                    ($indiceAlumno + 1),
                    1,
                    0,
                    'C',
                    true
                );

                if ($alumno !== null) {
                    $nombre =
                        $this->valorEvaluacion(
                            $alumno,
                            'nombre',
                            ''
                        );

                    $valoresPeriodos =
                        $this->valorEvaluacion(
                            $alumno,
                            'periodos',
                            array()
                        );

                    if (
                        !is_array(
                            $valoresPeriodos
                        )
                    ) {
                        $valoresPeriodos =
                            array();
                    }

                    $promedioFinal =
                        $this->valorEvaluacion(
                            $alumno,
                            'promedio_final',
                            null
                        );

                    $estado = '';

                    if (
                        $promedioFinal !== null &&
                        $promedioFinal !== ''
                    ) {
                        $promedioFinal =
                            (float)
                            $promedioFinal;

                        /*
                         * Solo contamos estadísticas
                         * una vez, no en páginas posteriores.
                         */
                        $promediosFinalesGrupo[] =
                            $promedioFinal;

                        if (
                            $promedioFinal >= 6
                        ) {
                            $estado = 'A';
                            $aprobados++;
                        } else {
                            $estado = 'R';
                            $reprobados++;
                        }
                    }

                    /*
                     * Nombre.
                     */
                    $doc->cellFit(
                        $wAlumno,
                        $altoFila,
                        mb_strtoupper(
                            $nombre,
                            'UTF-8'
                        ),
                        1,
                        0,
                        'L',
                        true,
                        5.8,
                        4.2
                    );

                    /*
                     * Promedio de cada trimestre.
                     */
                    foreach (
                        $periodos as
                        $indice =>
                        $nombrePeriodo
                    ) {
                        $valor =
                            isset(
                                $valoresPeriodos[
                                    $indice
                                ]
                            )
                                ? $valoresPeriodos[
                                    $indice
                                ]
                                : null;

                        $textoValor =
                            $valor === null ||
                            $valor === ''
                                ? ''
                                : number_format(
                                    (float)
                                    $valor,
                                    1
                                );

                        $doc->SetFont(
                            'Arial',
                            '',
                            6
                        );

                        $doc->Cell(
                            $wPeriodo,
                            $altoFila,
                            $textoValor,
                            1,
                            0,
                            'C',
                            true
                        );
                    }

                    /*
                     * Promedio final.
                     */
                    $textoFinal =
                        $promedioFinal === null ||
                        $promedioFinal === ''
                            ? ''
                            : number_format(
                                (float)
                                $promedioFinal,
                                1
                            );

                    $doc->SetFont(
                        'Arial',
                        'B',
                        6.2
                    );

                    $doc->Cell(
                        $wPromedio,
                        $altoFila,
                        $textoFinal,
                        1,
                        0,
                        'C',
                        true
                    );

                    /*
                     * A = aprobado
                     * R = reprobado.
                     */
                    $doc->Cell(
                        $wEstado,
                        $altoFila,
                        $estado,
                        1,
                        0,
                        'C',
                        true
                    );
                } else {
                    /*
                     * Renglón vacío.
                     */
                    $doc->Cell(
                        $wAlumno,
                        $altoFila,
                        '',
                        1,
                        0,
                        'L',
                        true
                    );

                    for (
                        $p = 0;
                        $p < $cantidadPeriodos;
                        $p++
                    ) {
                        $doc->Cell(
                            $wPeriodo,
                            $altoFila,
                            '',
                            1,
                            0,
                            'C',
                            true
                        );
                    }

                    $doc->Cell(
                        $wPromedio,
                        $altoFila,
                        '',
                        1,
                        0,
                        'C',
                        true
                    );

                    $doc->Cell(
                        $wEstado,
                        $altoFila,
                        '',
                        1,
                        0,
                        'C',
                        true
                    );
                }

                $y += $altoFila;
            }

            /*
             * Resumen únicamente en
             * la última página.
             */
            if (
                $pagina ===
                ($paginas - 1)
            ) {
                $totalEvaluados =
                    count(
                        $promediosFinalesGrupo
                    );

                $promedioGrupo =
                    $totalEvaluados > 0
                        ? array_sum(
                            $promediosFinalesGrupo
                        ) /
                        $totalEvaluados
                        : 0;

                $resumen = array();

                if (
                    $totalEvaluados > 0
                ) {
                    $resumen[] = array(
                        '% APROBADOS DEL CICLO',
                        number_format(
                            (
                                $aprobados /
                                $totalEvaluados
                            ) * 100,
                            1
                        ) . '%'
                    );

                    $resumen[] = array(
                        '% REPROBADOS DEL CICLO',
                        number_format(
                            (
                                $reprobados /
                                $totalEvaluados
                            ) * 100,
                            1
                        ) . '%'
                    );

                    $resumen[] = array(
                        'PROMEDIO FINAL DEL GRUPO',
                        number_format(
                            $promedioGrupo,
                            1
                        )
                    );
                } else {
                    $resumen[] = array(
                        '% APROBADOS DEL CICLO',
                        ''
                    );

                    $resumen[] = array(
                        '% REPROBADOS DEL CICLO',
                        ''
                    );

                    $resumen[] = array(
                        'PROMEDIO FINAL DEL GRUPO',
                        ''
                    );
                }

                foreach (
                    $resumen as
                    $filaResumen
                ) {
                    $doc->SetXY(
                        $xInicial,
                        $y
                    );

                    $doc->SetFont(
                        'Arial',
                        '',
                        6.4
                    );

                    $doc->Cell(
                        $wNo +
                        $wAlumno,
                        0.40,
                        $doc->texto(
                            $filaResumen[0]
                        ),
                        1,
                        0,
                        'L'
                    );

                    $doc->Cell(
                        $wPeriodosTotal +
                        $wPromedio +
                        $wEstado,
                        0.40,
                        $doc->texto(
                            $filaResumen[1]
                        ),
                        1,
                        0,
                        'C'
                    );

                    $y += 0.40;
                }

                /*
                 * Firma física.
                 */
                $y += 0.70;

                $doc->SetFont(
                    'Arial',
                    '',
                    7
                );

                $doc->SetXY(
                    1.10,
                    $y
                );

                $doc->Cell(
                    10.7,
                    0.35,
                    $doc->texto(
                        'FIRMA DEL (A) PROFR. (A):'
                    ),
                    0,
                    0
                );

                $doc->Line(
                    4.80,
                    $y + 0.31,
                    11.70,
                    $y + 0.31
                );

                $doc->SetXY(
                    15.30,
                    $y
                );

                $doc->Cell(
                    4.70,
                    0.35,
                    $doc->texto(
                        'FECHA: ' .
                        $fecha
                    ),
                    0,
                    0,
                    'R'
                );
            }
        }

        /*
         * Guardado del archivo.
         */
        $directorio =
            __DIR__ .
            '/../docs/evaluaciones';

        if (
            !is_dir(
                $directorio
            )
        ) {
            $json['answer'] =
                'No existe la carpeta donde se guardarán los documentos.';

            $json['directorio'] =
                $directorio;

            $valido = false;
        }

        if (
            $valido &&
            !is_writable(
                $directorio
            )
        ) {
            $json['answer'] =
                'La carpeta de documentos no tiene permisos de escritura.';

            $json['directorio'] =
                $directorio;

            $valido = false;
        }

        if ($valido) {
            $slug =
                $this->normalizarTextoEvaluacion(
                    $asignatura .
                    '_' .
                    $grado .
                    '_' .
                    $grupo .
                    '_final'
                );

            $slug =
                preg_replace(
                    '/[^a-z0-9]+/',
                    '_',
                    $slug
                );

            $slug =
                trim(
                    $slug,
                    '_'
                );

            if ($slug === '') {
                $slug =
                    'evaluacion_final';
            }

            $archivo =
                'resultado_final_' .
                $slug .
                '_' .
                date('Ymd_His') .
                '.pdf';

            $ruta =
                $directorio .
                '/' .
                $archivo;

            $doc->Output(
                'F',
                $ruta
            );

            if (
                !file_exists(
                    $ruta
                )
            ) {
                $json['answer'] =
                    'FPDF terminó el proceso, pero no se encontró el archivo generado.';

                $valido = false;
            }
        }

        if ($valido) {
            $scriptName =
                isset(
                    $_SERVER[
                        'SCRIPT_NAME'
                    ]
                )
                    ? str_replace(
                        '\\',
                        '/',
                        $_SERVER[
                            'SCRIPT_NAME'
                        ]
                    )
                    : '';

            $phpDir =
                dirname(
                    $scriptName
                );

            $baseUrl =
                dirname(
                    $phpDir
                );

            if (
                $baseUrl === '/' ||
                $baseUrl === '.' ||
                $baseUrl === '\\'
            ) {
                $baseUrl = '';
            }

            $urlPdf =
                $baseUrl .
                '/docs/evaluaciones/' .
                $archivo;

            $json['status'] = 'ok';
            $json['answer'] =
                'PDF final generado correctamente.';
            $json['code'] = 200;
            $json['file'] = $archivo;
            $json['url'] = $urlPdf;
            $json['pdf'] = $urlPdf;
        }
    }

    return $json;
}

public function generarPDFPeriodoEvaluacion($data)
    {
        $json = array(
            'status' => 'error',
            'answer' => 'No fue posible generar el PDF.',
            'code' => 500
        );

        if (!is_array($data)) {
            $json['answer'] = 'Los datos de la evaluación no son válidos.';
            $json['code'] = 400;
            return $json;
        }

        $escuela = $this->valorEvaluacion($data, 'escuela', array());
        $criterios = $this->valorEvaluacion($data, 'criterios', array());
        $alumnos = $this->valorEvaluacion($data, 'alumnos', array());

        $detalleActividades = $this->valorEvaluacion($data, 'detalle_actividades', array());
        if (!is_array($detalleActividades)) {
            $detalleActividades = array();
        }

        if (!is_array($criterios) || count($criterios) === 0) {
            $json['answer'] = 'No hay criterios de evaluación para generar el documento.';
            $json['code'] = 422;
            return $json;
        }

        if (!is_array($alumnos) || count($alumnos) === 0) {
            $json['answer'] = 'No hay alumnos para generar el documento.';
            $json['code'] = 422;
            return $json;
        }

        $nombreEscuela = $this->valorEvaluacion($escuela, 'nombre', 'CONTROL ESCOLAR');
        $tipoEscuela = $this->valorEvaluacion($escuela, 'tipo', 'REGISTRO DE EVALUACIONES');
        $claveEscuela = $this->valorEvaluacion($escuela, 'clave', '');
        $cct = $this->valorEvaluacion($escuela, 'cct', '');
        $profesor = $this->valorEvaluacion($data, 'profesor', '');
        $asignatura = $this->valorEvaluacion($data, 'asignatura', '');
        $periodo = $this->valorEvaluacion($data, 'periodo', '');
        $grado = $this->valorEvaluacion($data, 'grado', '');
        $grupo = $this->valorEvaluacion($data, 'grupo', '');
        $ciclo = $this->valorEvaluacion($data, 'ciclo', '');
        $fecha = $this->valorEvaluacion($data, 'fecha', date('d/m/Y'));
        $logoIzquierdo = $this->valorEvaluacion($escuela, 'logo_izquierdo', '');
        $logoDerecho = $this->valorEvaluacion($escuela, 'logo_derecho', '');

        $doc = new PDFEvaluacion(
    'P',
    'cm',
    'Letter'
);

$doc->SetMargins(
    0.9,
    0.35,
    0.9
);

$doc->SetAutoPageBreak(false);

$doc->SetDrawColor(
    70,
    70,
    70
);

$doc->SetLineWidth(0.015);

$totalAlumnos = count($alumnos);

$porPagina = 30;

/*
 * Siempre generamos por lo menos una página,
 * aunque solamente exista un alumno.
 */
$paginas = max(
    1,
    (int) ceil(
        $totalAlumnos / $porPagina
    )
);

$promediosFinales = array();
$calificacionesExamen = array();

$indiceExamen = -1;

foreach (
    $criterios as
    $i => $criterio
) {
    $nombreCriterio =
        $this->valorEvaluacion(
            $criterio,
            'nombre',
            ''
        );

    if (
        $indiceExamen < 0 &&
        strpos(
            $this->normalizarTextoEvaluacion(
                $nombreCriterio
            ),
            'examen'
        ) !== false
    ) {
        $indiceExamen = $i;
    }
}

for (
    $pagina = 0;
    $pagina < $paginas;
    $pagina++
) {
    $doc->AddPage();

    $this->encabezadoRegistro(
        $doc,
        $data,
        false
    );

    /*
     * Medidas del formato.
     */
    $xInicial = 0.90;
    $yInicial = 2.78;

    $anchoTotal = 19.80;

    $wNo = 0.55;
    $wAlumno = 6.30;
    $wPromedio = 0.85;

    $wCriterios =
        $anchoTotal -
        $wNo -
        $wAlumno -
        $wPromedio;

    $cantidadCriterios =
        max(
            1,
            count($criterios)
        );

    $wCriterio =
        $wCriterios /
        $cantidadCriterios;

    /*
     * Cabecera de tres niveles,
     * como el formato físico.
     */
    $h1 = 0.36;
    $h2 = 0.34;
    $h3 = 0.56;

    $altoCabecera =
        $h1 + $h2 + $h3;

    /*
     * N/P.
     */
    $doc->SetFillColor(
        245,
        245,
        245
    );

    $doc->SetFont(
        'Arial',
        'B',
        6
    );

    $doc->SetXY(
        $xInicial,
        $yInicial
    );

    $doc->Cell(
        $wNo,
        $altoCabecera,
        $doc->texto('N/P'),
        1,
        0,
        'C',
        true
    );

    /*
     * Columna grande izquierda,
     * igual al formato original.
     */
    $xAlumno =
        $xInicial +
        $wNo;

    $doc->SetXY(
        $xAlumno,
        $yInicial
    );

    $doc->Cell(
        $wAlumno,
        $h1,
        $doc->texto(
            'ASPECTOS A EVALUAR'
        ),
        1,
        0,
        'C',
        true
    );

    $doc->SetXY(
        $xAlumno,
        $yInicial + $h1
    );

    $doc->Cell(
        $wAlumno,
        $h2,
        $doc->texto('FECHA'),
        1,
        0,
        'C',
        true
    );

    $doc->SetXY(
        $xAlumno,
        $yInicial + $h1 + $h2
    );

    $doc->Cell(
        $wAlumno,
        $h3,
        $doc->texto(
            'NOMBRE DEL ALUMNO'
        ),
        1,
        0,
        'C',
        true
    );

    /*
     * Criterios dinámicos.
     */
    $xCriterio =
        $xAlumno +
        $wAlumno;

    foreach (
    $criterios as
    $criterio
) {

    $criterioId =
        (int)
        $this->valorEvaluacion(
            $criterio,
            'id',
            0
        );

    $nombre =
        $this->valorEvaluacion(
            $criterio,
            'nombre',
            'CRITERIO'
        );

    $porcentaje =
        (float)
        $this->valorEvaluacion(
            $criterio,
            'porcentaje',
            0
        );

    $tipoCaptura =
        $this->valorEvaluacion(
            $criterio,
            'tipo_captura',
            'numero'
        );


    $nombreNormalizado =
        $this->normalizarTextoEvaluacion(
            $nombre
        );


    $detalleCriterio =
        isset(
            $detalleActividades[
                $criterioId
            ]
        )
            ? $detalleActividades[
                $criterioId
            ]
            : array();


    $actividadesExamen =
        isset(
            $detalleCriterio[
                'actividades'
            ]
        ) &&
        is_array(
            $detalleCriterio[
                'actividades'
            ]
        )
            ? $detalleCriterio[
                'actividades'
            ]
            : array();


    $esExamen =
        strpos(
            $nombreNormalizado,
            'examen'
        ) !== false &&
        count(
            $actividadesExamen
        ) > 0;


    /*
     * ==========================
     * EXAMEN
     * ==========================
     */
    if ($esExamen) {

        /*
         * Primera fila vacía.
         */
        $doc->SetXY(
            $xCriterio,
            $yInicial
        );

        $doc->Cell(
            $wCriterio,
            $h1,
            '',
            1,
            0,
            'C',
            true
        );


        /*
         * Segunda fila:
         * EXAMEN
         */
        $doc->SetXY(
            $xCriterio,
            $yInicial + $h1
        );

        $doc->SetFont(
            'Arial',
            'B',
            5.3
        );

        $doc->Cell(
            $wCriterio,
            $h2,
            $doc->texto('EXAMEN'),
            1,
            0,
            'C',
            true
        );


        /*
         * Tercera fila:
         *
         * 1 | 2 | PROM. | 60%
         */
        $cantidadSubcolumnas =
            count(
                $actividadesExamen
            ) + 2;

        $wSub =
            $wCriterio /
            $cantidadSubcolumnas;

        $xSub =
            $xCriterio;


        foreach (
            $actividadesExamen as
            $indiceActividad =>
            $actividad
        ) {

            $doc->SetXY(
                $xSub,
                $yInicial +
                $h1 +
                $h2
            );

            $doc->SetFont(
                'Arial',
                'B',
                5
            );

            $doc->Cell(
                $wSub,
                $h3,
                (string)
                ($indiceActividad + 1),
                1,
                0,
                'C',
                true
            );

            $xSub +=
                $wSub;
        }


        $doc->SetXY(
            $xSub,
            $yInicial +
            $h1 +
            $h2
        );

        $doc->SetFont(
            'Arial',
            'B',
            4.4
        );

        $doc->Cell(
            $wSub,
            $h3,
            $doc->texto(
                'PROM.'
            ),
            1,
            0,
            'C',
            true
        );

        $xSub +=
            $wSub;


        $doc->SetXY(
            $xSub,
            $yInicial +
            $h1 +
            $h2
        );

        $doc->Cell(
            $wSub,
            $h3,
            number_format(
                $porcentaje,
                0
            ) . '%',
            1,
            0,
            'C',
            true
        );


    /*
     * ==========================
     * CONDUCTA
     * ==========================
     */
    } elseif ($tipoCaptura === 'conducta') {

    $doc->SetXY($xCriterio, $yInicial);
    $doc->Cell($wCriterio, $h1, '', 1, 0, 'C', true);

    $doc->SetXY($xCriterio, $yInicial + $h1);
    $doc->Cell($wCriterio, $h2, '', 1, 0, 'C', true);

    $doc->SetXY($xCriterio, $yInicial + $h1 + $h2);
    $doc->cellFit(
        $wCriterio,
        $h3 * 0.60,
        'CONDUCTA',
        'LTR',
        0,
        'C',
        true,
        5.3,
        3.8,
        'B'
    );

    $doc->SetXY($xCriterio, $yInicial + $h1 + $h2 + ($h3 * 0.60));
    $doc->SetFont('Arial', 'B', 5);
    $textoInferior = $tipoCaptura === 'inasistencias'
    ? 'FALT.'
    : ($porcentaje > 0 ? number_format($porcentaje, 0) . '%' : 'NO PONDERA');

$doc->Cell(
    $wCriterio,
    $h3 * 0.40,
    $doc->texto($textoInferior),
    'LBR',
    0,
    'C',
    true
);
    } else {

        $doc->SetXY(
            $xCriterio,
            $yInicial
        );

        $doc->Cell(
            $wCriterio,
            $h1,
            '',
            1,
            0,
            'C',
            true
        );


        $doc->SetXY(
            $xCriterio,
            $yInicial + $h1
        );

        $doc->Cell(
            $wCriterio,
            $h2,
            '',
            1,
            0,
            'C',
            true
        );


        $doc->SetXY(
            $xCriterio,
            $yInicial +
            $h1 +
            $h2
        );

        $doc->cellFit(
            $wCriterio,
            $h3 * 0.60,
            mb_strtoupper(
                $nombre,
                'UTF-8'
            ),
            'LTR',
            0,
            'C',
            true,
            5.3,
            3.8,
            'B'
        );


        $doc->SetXY(
            $xCriterio,
            $yInicial +
            $h1 +
            $h2 +
            ($h3 * 0.60)
        );

        $doc->SetFont(
            'Arial',
            '',
            4.8
        );

        $textoInferior = $tipoCaptura === 'inasistencias'
    ? 'FALT.'
    : ($porcentaje > 0 ? number_format($porcentaje, 0) . '%' : 'NO PONDERA');

$doc->Cell(
    $wCriterio,
    $h3 * 0.40,
    $doc->texto($textoInferior),
    'LBR',
    0,
    'C',
    true
);
    }


    $xCriterio +=
        $wCriterio;
}

    /*
     * Promedio final P.
     */
    $doc->SetXY(
        $xCriterio,
        $yInicial
    );

    $doc->SetFont(
        'Arial',
        'B',
        7
    );

    $doc->Cell(
        $wPromedio,
        $altoCabecera,
        $doc->texto('P'),
        1,
        0,
        'C',
        true
    );

    /*
     * Renglones.
     *
     * Aunque solo exista un alumno,
     * se imprimen los 30 espacios,
     * igual que el formato físico.
     */
    $y =
        $yInicial +
        $altoCabecera;

    $altoFila = 0.47;

    $inicio =
        $pagina *
        $porPagina;

    for (
        $fila = 0;
        $fila < $porPagina;
        $fila++
    ) {
        $indiceAlumno =
            $inicio +
            $fila;

        $alumno =
            isset(
                $alumnos[
                    $indiceAlumno
                ]
            )
                ? $alumnos[
                    $indiceAlumno
                ]
                : null;

        $doc->SetFillColor(
            255,
            255,
            255
        );

        $doc->SetXY(
            $xInicial,
            $y
        );

        $doc->SetFont(
            'Arial',
            '',
            5.6
        );

        $doc->Cell(
            $wNo,
            $altoFila,
            (string)
            ($indiceAlumno + 1),
            1,
            0,
            'C',
            true
        );

        if ($alumno !== null) {
            $nombre =
                $this->valorEvaluacion(
                    $alumno,
                    'nombre',
                    ''
                );

            $calificaciones =
                $this->valorEvaluacion(
                    $alumno,
                    'calificaciones',
                    array()
                );

            if (
                !is_array(
                    $calificaciones
                )
            ) {
                $calificaciones =
                    array();
            }

            $suma =
    0;

$criteriosRequeridos =
    0;

$criteriosCapturados =
    0;

$valoresPorCriterio =
    array();


foreach (
    $criterios as
    $c =>
    $criterio
) {

    $criterioId =
        (int)
        $this->valorEvaluacion(
            $criterio,
            'id',
            0
        );

    $porcentaje =
        (float)
        $this->valorEvaluacion(
            $criterio,
            'porcentaje',
            0
        );

    $tipoCaptura =
        $this->valorEvaluacion(
            $criterio,
            'tipo_captura',
            'numero'
        );

    $afectaPromedio =
        (int)
        $this->valorEvaluacion(
            $criterio,
            'afecta_promedio',
            $porcentaje > 0
                ? 1
                : 0
        );

    $redondearResultado = (int)$this->valorEvaluacion(
    $criterio,
    'redondear_resultado',
    0
);


    if (
        array_key_exists(
            $criterioId,
            $calificaciones
        )
    ) {

        $valorCrudo =
            $calificaciones[
                $criterioId
            ];

    } elseif (
        array_key_exists(
            $c,
            $calificaciones
        )
    ) {

        $valorCrudo =
            $calificaciones[
                $c
            ];

    } else {

        $valorCrudo =
            '';
    }


    if ($tipoCaptura === 'conducta') {
        $valor = trim((string)$valorCrudo);
    } else {
        $valor = $valorCrudo !== '' && $valorCrudo !== null && is_numeric($valorCrudo) ? (float)$valorCrudo : null;
        if ($valor !== null && $afectaPromedio === 1 && $redondearResultado === 1) {
            $valor = round($valor);
        }
    }


    $valoresPorCriterio[
        $criterioId
    ] =
        $valor;

    if (
        $afectaPromedio === 1
    ) {

        $criteriosRequeridos++;


        if (
            $valor !== null &&
            is_numeric($valor)
        ) {

            $suma +=
                $valor *
                (
                    $porcentaje /
                    100
                );

            $criteriosCapturados++;
        }
    }
}


/*
 * Sólo hay promedio final cuando están
 * todos los criterios ponderables.
 */
$promedioReal =
    $criteriosRequeridos > 0 &&
    $criteriosCapturados ===
    $criteriosRequeridos
        ? round(
            $suma,
            2
        )
        : null;


/*
 * La calificación oficial final
 * se redondea a entero.
 */
$promedio =
    $promedioReal === null
        ? null
        : (int)
        round(
            $promedioReal
        );


if (
    $promedio !== null
) {

    $promediosFinales[] =
        $promedio;
}


$doc->cellFit(
    $wAlumno,
    $altoFila,
    mb_strtoupper(
        $nombre,
        'UTF-8'
    ),
    1,
    0,
    'L',
    true,
    5.6,
    4.2
);


/*
 * ===================================
 * IMPRIMIR CRITERIOS
 * ===================================
 */
foreach (
    $criterios as
    $criterio
) {

    $criterioId =
        (int)
        $this->valorEvaluacion(
            $criterio,
            'id',
            0
        );

    $nombreCriterio =
        $this->valorEvaluacion(
            $criterio,
            'nombre',
            ''
        );

    $nombreNormalizado =
        $this->normalizarTextoEvaluacion(
            $nombreCriterio
        );

    $porcentaje =
        (float)
        $this->valorEvaluacion(
            $criterio,
            'porcentaje',
            0
        );

    $tipoCaptura =
        $this->valorEvaluacion(
            $criterio,
            'tipo_captura',
            'numero'
        );

    $afectaPromedio =
        (int)
        $this->valorEvaluacion(
            $criterio,
            'afecta_promedio',
            $porcentaje > 0
                ? 1
                : 0
        );

    $redondearResultado = (int)$this->valorEvaluacion(
    $criterio,
    'redondear_resultado',
    0
);


    $valor =
        isset(
            $valoresPorCriterio[
                $criterioId
            ]
        )
            ? $valoresPorCriterio[
                $criterioId
            ]
            : null;


    $detalleCriterio =
        isset(
            $detalleActividades[
                $criterioId
            ]
        )
            ? $detalleActividades[
                $criterioId
            ]
            : array();


    $actividadesExamen =
        isset(
            $detalleCriterio[
                'actividades'
            ]
        ) &&
        is_array(
            $detalleCriterio[
                'actividades'
            ]
        )
            ? $detalleCriterio[
                'actividades'
            ]
            : array();


    $esExamen =
        strpos(
            $nombreNormalizado,
            'examen'
        ) !== false &&
        count(
            $actividadesExamen
        ) > 0;


    /*
     * =============================
     * EXAMEN
     * =============================
     */
    if ($esExamen) {

        $cantidadSubcolumnas =
            count(
                $actividadesExamen
            ) + 2;

        $wSub =
            $wCriterio /
            $cantidadSubcolumnas;


        $calificacionesActividad =
            array();


        if (
            isset(
                $detalleCriterio[
                    'calificaciones'
                ][
                    $alumno['id']
                ]
            )
        ) {

            $calificacionesActividad =
                $detalleCriterio[
                    'calificaciones'
                ][
                    $alumno['id']
                ];
        }


        foreach (
            $actividadesExamen as
            $actividad
        ) {

            $actividadId =
                (int)
                $this->valorEvaluacion(
                    $actividad,
                    'id',
                    0
                );


            $notaActividad =
                isset(
                    $calificacionesActividad[
                        $actividadId
                    ]
                ) &&
                $calificacionesActividad[
                    $actividadId
                ] !== null
                    ? (float)
                    $calificacionesActividad[
                        $actividadId
                    ]
                    : null;


            $textoActividad =
                $notaActividad === null
                    ? ''
                    : rtrim(
                        rtrim(
                            number_format(
                                $notaActividad,
                                2,
                                '.',
                                ''
                            ),
                            '0'
                        ),
                        '.'
                    );


            $doc->SetFont(
                'Arial',
                '',
                5.5
            );

            $doc->Cell(
                $wSub,
                $altoFila,
                $textoActividad,
                1,
                0,
                'C',
                true
            );
        }


        /*
         * PROMEDIO DE EXÁMENES.
         */
        $textoPromedioExamen =
            $valor === null
                ? ''
                : rtrim(
                    rtrim(
                        number_format(
                            (float)$valor,
                            2,
                            '.',
                            ''
                        ),
                        '0'
                    ),
                    '.'
                );


        $doc->Cell(
            $wSub,
            $altoFila,
            $textoPromedioExamen,
            1,
            0,
            'C',
            true
        );


        /*
         * APORTACIÓN 60%.
         */
        $aportacionExamen =
            $valor === null
                ? null
                : (float)$valor *
                  (
                      $porcentaje /
                      100
                  );


        $textoAportacion = $aportacionExamen === null
    ? ''
    : number_format(floor($aportacionExamen * 10) / 10, 1, '.', '');


        $doc->SetFont(
            'Arial',
            'B',
            5.5
        );

        $doc->Cell(
            $wSub,
            $altoFila,
            $textoAportacion,
            1,
            0,
            'C',
            true
        );


        /*
         * Para estadísticas del examen.
         */
        if ($valor !== null) {
            $calificacionesExamen[] =
                (float)$valor;
        }


    /*
     * =============================
     * CONDUCTA
     * =============================
     */
    } elseif (
        $tipoCaptura ===
        'conducta'
    ) {

        $textoConducta =
            $valor === null
                ? ''
                : mb_strtoupper(
                    trim(
                        (string)$valor
                    ),
                    'UTF-8'
                );


        $doc->SetFont(
            'Arial',
            'B',
            6
        );

        $doc->Cell(
            $wCriterio,
            $altoFila,
            $doc->texto(
                $textoConducta
            ),
            1,
            0,
            'C',
            true
        );


    /*
     * =============================
     * CRITERIO PONDERADO NORMAL
     * Cognoscitivo / Formativo
     * =============================
     */
    } elseif (
        $afectaPromedio === 1
    ) {

        $aportacion =
            $valor === null
                ? null
                : (float)$valor *
                  (
                      $porcentaje /
                      100
                  );


        $textoAportacion = $aportacion === null
    ? ''
    : number_format(floor($aportacion * 10) / 10, 1, '.', '');


        $doc->SetFont(
            'Arial',
            '',
            6
        );

        $doc->Cell(
            $wCriterio,
            $altoFila,
            $textoAportacion,
            1,
            0,
            'C',
            true
        );


    /*
     * Otros tipos futuros.
     */
    } else {

        $doc->Cell(
            $wCriterio,
            $altoFila,
            $valor === null
                ? ''
                : $doc->texto(
                    (string)$valor
                ),
            1,
            0,
            'C',
            true
        );
    }
}

            $doc->SetFont(
                'Arial',
                'B',
                6.2
            );

            $doc->Cell(
                $wPromedio,
                $altoFila,
                $promedio === null
                    ? ''
                    : number_format(
                        $promedio,
                        0
                    ),
                1,
                0,
                'C',
                true
            );
        } else {
            /*
             * Renglón vacío.
             */
            $doc->Cell(
                $wAlumno,
                $altoFila,
                '',
                1,
                0,
                'L',
                true
            );

            for (
                $c = 0;
                $c <
                $cantidadCriterios;
                $c++
            ) {
                $doc->Cell(
                    $wCriterio,
                    $altoFila,
                    '',
                    1,
                    0,
                    'C',
                    true
                );
            }

            $doc->Cell(
                $wPromedio,
                $altoFila,
                '',
                1,
                0,
                'C',
                true
            );
        }

        $y += $altoFila;
    }

    /*
     * Estadísticas inferiores,
     * iguales al formato.
     */
    if (
        $pagina ===
        ($paginas - 1)
    ) {
        $aprobadosFinal = 0;

        foreach (
            $promediosFinales as
            $promedioAlumno
        ) {
            if (
                $promedioAlumno >= 6
            ) {
                $aprobadosFinal++;
            }
        }

        $totalFinal =
            count(
                $promediosFinales
            );

        $promedioGrupo =
            $totalFinal > 0
                ? array_sum(
                    $promediosFinales
                ) /
                $totalFinal
                : 0;

        $resumen = array();

        if (
            $indiceExamen >= 0 &&
            count(
                $calificacionesExamen
            ) > 0
        ) {
            $aprobadosExamen = 0;

            foreach (
                $calificacionesExamen as
                $notaExamen
            ) {
                if (
                    $notaExamen >= 6
                ) {
                    $aprobadosExamen++;
                }
            }

            $totalExamen =
                count(
                    $calificacionesExamen
                );

            $resumen[] = array(
                '% DE APROBADOS EXAMEN',
                number_format(
                    (
                        $aprobadosExamen /
                        $totalExamen
                    ) * 100,
                    1
                ) . '%'
            );

            $resumen[] = array(
                '% DE REPROBADOS EXAMEN',
                number_format(
                    (
                        (
                            $totalExamen -
                            $aprobadosExamen
                        ) /
                        $totalExamen
                    ) * 100,
                    1
                ) . '%'
            );
        } else {
            $resumen[] = array(
                '% DE APROBADOS EXAMEN',
                ''
            );

            $resumen[] = array(
                '% DE REPROBADOS EXAMEN',
                ''
            );
        }

        if ($totalFinal > 0) {
            $resumen[] = array(
                '% APROBADOS EVALUACION',
                number_format(
                    (
                        $aprobadosFinal /
                        $totalFinal
                    ) * 100,
                    1
                ) . '%'
            );

            $resumen[] = array(
                '% REPROBADOS EVALUACION',
                number_format(
                    (
                        (
                            $totalFinal -
                            $aprobadosFinal
                        ) /
                        $totalFinal
                    ) * 100,
                    1
                ) . '%'
            );
        } else {
            $resumen[] = array(
                '% APROBADOS EVALUACION',
                ''
            );

            $resumen[] = array(
                '% REPROBADOS EVALUACION',
                ''
            );
        }

        $promedioExamen =
            count(
                $calificacionesExamen
            ) > 0
                ? array_sum(
                    $calificacionesExamen
                ) /
                count(
                    $calificacionesExamen
                )
                : null;

        $resumen[] = array(
            'PROMEDIO DE GRUPO EXAMEN',
            $promedioExamen === null
                ? ''
                : number_format(
                    $promedioExamen,
                    1
                )
        );

        $resumen[] = array(
            'PROMEDIO DE GRUPO EVALUACION',
            $totalFinal > 0
                ? number_format(
                    $promedioGrupo,
                    1
                )
                : ''
        );

        foreach (
            $resumen as
            $filaResumen
        ) {
            $doc->SetXY(
                $xInicial,
                $y
            );

            $doc->SetFont(
                'Arial',
                '',
                6.4
            );

            $doc->Cell(
                $wNo +
                $wAlumno,
                0.38,
                $doc->texto(
                    $filaResumen[0]
                ),
                1,
                0,
                'L'
            );

            $doc->Cell(
                $wCriterios +
                $wPromedio,
                0.38,
                $doc->texto(
                    $filaResumen[1]
                ),
                1,
                1,
                'C'
            );

            $y += 0.38;
        }

        /*
         * Firma vacía para firma física.
         */
        $y += 0.65;

        $doc->SetFont(
            'Arial',
            '',
            7
        );

        $doc->SetXY(
            1.10,
            $y
        );

        $doc->Cell(
            10.7,
            0.35,
            $doc->texto(
                'FIRMA DEL (A) PROFR. (A):'
            ),
            0,
            0
        );

        $doc->Line(
            4.80,
            $y + 0.31,
            11.70,
            $y + 0.31
        );

        $fecha =
            $this->valorEvaluacion(
                $data,
                'fecha',
                date('d/m/Y')
            );

        $doc->SetXY(
            15.30,
            $y
        );

        $doc->Cell(
            4.70,
            0.35,
            $doc->texto(
                'FECHA: ' .
                $fecha
            ),
            0,
            0,
            'R'
        );
    }
}

        $directorio = __DIR__ . '/../docs/evaluaciones';

        if (!is_dir($directorio)) {
    $json['answer'] = 'No existe la carpeta donde se guardarán los documentos.';
    $json['directorio'] = $directorio;
    return $json;
}

if (!is_writable($directorio)) {
    $json['answer'] = 'La carpeta de documentos no tiene permisos de escritura.';
    $json['directorio'] = $directorio;
    return $json;
}

        if (!is_writable($directorio)) {
            $json['answer'] = 'La carpeta de documentos existe, pero PHP no tiene permisos para escribir en ella.';
            $json['directorio'] = $directorio;
            return $json;
        }

        $slug = $this->normalizarTextoEvaluacion($asignatura . '_' . $grado . '_' . $grupo . '_' . $periodo);
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);
        $slug = trim($slug, '_');

        if ($slug === '') $slug = 'evaluacion';

        $archivo = 'registro_' . $slug . '_' . date('Ymd_His') . '.pdf';
        $ruta = $directorio . '/' . $archivo;

        $doc->Output('F', $ruta);

        if (!file_exists($ruta)) {
            $json['answer'] = 'FPDF terminó el proceso, pero no se encontró el archivo generado.';
            $json['ruta'] = $ruta;
            return $json;
        }

        $scriptName = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '';
        $phpDir = dirname($scriptName);
        $baseUrl = dirname($phpDir);

        if ($baseUrl === '/' || $baseUrl === '.' || $baseUrl === '\\') $baseUrl = '';

        $urlPdf = $baseUrl . '/docs/evaluaciones/' . $archivo;

        $json['status'] = 'ok';
        $json['answer'] = 'PDF generado correctamente.';
        $json['code'] = 200;
        $json['file'] = $archivo;
        $json['url'] = $urlPdf;
        $json['pdf'] = $urlPdf;

        return $json;
    }
}