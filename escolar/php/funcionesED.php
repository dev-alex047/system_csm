<?php 
class funcionesED{
    private $document;
    private $functIA;
    public function __construct() {

        $this->document = new extractDocument();
        $this->functIA = new apiRestAI();
       
    }
    public function analizarArchivoCalificaciones(
    $file,
    $contexto = []
) {
    $json = [];

    $json["status"] = "error";
    $json["answer"] = "No fue posible analizar el archivo.";
    $json["code"] = 500;
    $json["rows"] = [];

    if (
        !is_array($file) ||
        !isset($file["tmp_name"]) ||
        $file["tmp_name"] === ""
    ) {
        $json["status"] = "warning";
        $json["answer"] = "No se recibió ningún archivo.";
        $json["code"] = 400;

        return $json;
    }

    if (
        !isset($file["error"]) ||
        $file["error"] !== UPLOAD_ERR_OK
    ) {
        $json["status"] = "error";
        $json["answer"] = "Ocurrió un error al recibir el archivo.";
        $json["upload_error"] = isset($file["error"])
            ? $file["error"]
            : null;
        $json["code"] = 400;

        return $json;
    }

    $rutaTemporal = $file["tmp_name"];
    $nombreArchivo = isset($file["name"])
        ? basename($file["name"])
        : "archivo";

    $tamano = isset($file["size"])
        ? (int) $file["size"]
        : 0;

    if ($tamano <= 0) {
        $json["status"] = "warning";
        $json["answer"] = "El archivo está vacío.";
        $json["code"] = 400;

        return $json;
    }

    if ($tamano > (5 * 1024 * 1024)) {
        $json["status"] = "warning";
        $json["answer"] = "El archivo supera el máximo permitido de 5 MB.";
        $json["code"] = 413;

        return $json;
    }

    $extension = strtolower(
        pathinfo(
            $nombreArchivo,
            PATHINFO_EXTENSION
        )
    );

    $mime = $this->detectarMimeCalificaciones(
        $rutaTemporal
    );

    $tipoArchivo = $this->clasificarArchivoCalificaciones(
        $mime,
        $extension
    );

    $resultadoIA = [];
    $datosExtraidos = [];

    switch ($tipoArchivo) {

        case "csv":

            $datosExtraidos =
                $this->document->getDataCSV_new(
                    $rutaTemporal
                );

            if (
                !isset($datosExtraidos["status"]) ||
                $datosExtraidos["status"] !== "ok"
            ) {
                return $datosExtraidos;
            }

            $contenido = json_encode(
                [
                    "headers" => isset(
                        $datosExtraidos["headers"]
                    )
                        ? $datosExtraidos["headers"]
                        : [],
                    "data" => array_slice(
                        $datosExtraidos["data"],
                        0,
                        1000
                    )
                ],
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            );

            $resultadoIA =
                $this->functIA
                    ->analizarCalificacionesEscolares(
                        "text",
                        $contenido,
                        $contexto,
                        $nombreArchivo,
                        $mime
                    );

            break;

        case "excel":

            /*
             * SimpleXLSX lee XLSX.
             * Para XLS antiguo se manda directamente a la IA.
             */
            if ($extension === "xlsx") {

                $datosExtraidos =
                    $this->document
                        ->getDataExcel_new(
                            $rutaTemporal
                        );

                if (
                    isset($datosExtraidos["status"]) &&
                    $datosExtraidos["status"] === "ok"
                ) {
                    $contenido = json_encode(
                        [
                            "headers" => isset(
                                $datosExtraidos["headers"]
                            )
                                ? $datosExtraidos["headers"]
                                : [],
                            "data" => array_slice(
                                $datosExtraidos["data"],
                                0,
                                1000
                            )
                        ],
                        JSON_UNESCAPED_UNICODE |
                        JSON_UNESCAPED_SLASHES
                    );

                    $resultadoIA =
                        $this->functIA
                            ->analizarCalificacionesEscolares(
                                "text",
                                $contenido,
                                $contexto,
                                $nombreArchivo,
                                $mime
                            );

                    break;
                }
            }

            $mimeArchivo =
                $extension === "xls"
                    ? "application/vnd.ms-excel"
                    : "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet";

            $base64 = base64_encode(
                file_get_contents($rutaTemporal)
            );

            $archivoBase64 =
                "data:" .
                $mimeArchivo .
                ";base64," .
                $base64;

            $resultadoIA =
                $this->functIA
                    ->analizarCalificacionesEscolares(
                        "file",
                        $archivoBase64,
                        $contexto,
                        $nombreArchivo,
                        $mimeArchivo
                    );

            break;

        case "pdf":

            $base64 = base64_encode(
                file_get_contents($rutaTemporal)
            );

            $archivoBase64 =
                "data:application/pdf;base64," .
                $base64;

            $resultadoIA =
                $this->functIA
                    ->analizarCalificacionesEscolares(
                        "file",
                        $archivoBase64,
                        $contexto,
                        $nombreArchivo,
                        "application/pdf"
                    );

            break;

        case "image":

            $contenidoImagen = file_get_contents(
                $rutaTemporal
            );

            if ($contenidoImagen === false) {
                $json["status"] = "error";
                $json["answer"] = "No fue posible leer la imagen.";
                $json["code"] = 500;

                return $json;
            }

            $base64Imagen = base64_encode(
                $contenidoImagen
            );

            $imagenBase64 =
                "data:" .
                $mime .
                ";base64," .
                $base64Imagen;

            $resultadoIA =
                $this->functIA
                    ->analizarCalificacionesEscolares(
                        "image",
                        $imagenBase64,
                        $contexto,
                        $nombreArchivo,
                        $mime
                    );

            break;

        default:

            $json["status"] = "warning";
            $json["answer"] =
                "El formato no es compatible. Usa CSV, XLSX, XLS, PDF o una imagen.";
            $json["code"] = 415;
            $json["mime"] = $mime;
            $json["extension"] = $extension;

            return $json;
    }

    if (
        !isset($resultadoIA["status"]) ||
        $resultadoIA["status"] !== "ok"
    ) {
        return $resultadoIA;
    }

    if (
        !isset($resultadoIA["answer"]) ||
        !is_array($resultadoIA["answer"])
    ) {
        $json["status"] = "error";
        $json["answer"] =
            "La IA no regresó una estructura válida.";
        $json["code"] = 500;
        $json["ai_response"] = $resultadoIA;

        return $json;
    }

    $analisis = $resultadoIA["answer"];

    $rows = $this->convertirCalificacionesARows(
        $analisis,
        $contexto
    );

    if (count($rows) <= 1) {
        $json["status"] = "warning";
        $json["answer"] =
            "No se encontraron calificaciones válidas en el archivo.";
        $json["code"] = 422;
        $json["analysis"] = $analisis;
        $json["rows"] = $rows;

        return $json;
    }

    $json["status"] = "ok";
    $json["answer"] =
        "Las calificaciones fueron detectadas correctamente.";
    $json["code"] = 200;

    $json["file_name"] = $nombreArchivo;
    $json["file_type"] = $tipoArchivo;
    $json["mime"] = $mime;

    /*
     * Este arreglo lo recibe directamente
     * validateImportedRows() en JavaScript.
     */
    $json["rows"] = $rows;

    /*
     * Se conserva el análisis completo para debug
     * o para mostrar advertencias posteriormente.
     */
    $json["analysis"] = $analisis;

    $json["warnings"] = isset(
        $analisis["advertencias"]
    )
        ? $analisis["advertencias"]
        : [];

    $json["confidence"] = isset(
        $analisis["confianza_general"]
    )
        ? $analisis["confianza_general"]
        : 0;

    return $json;
}

private function detectarMimeCalificaciones(
    $rutaArchivo
) {
    $mime = "application/octet-stream";

    if (function_exists("finfo_open")) {
        $finfo = finfo_open(
            FILEINFO_MIME_TYPE
        );

        if ($finfo !== false) {
            $resultado = finfo_file(
                $finfo,
                $rutaArchivo
            );

            finfo_close($finfo);

            if (
                is_string($resultado) &&
                trim($resultado) !== ""
            ) {
                $mime = strtolower(
                    trim($resultado)
                );
            }
        }
    }

    return $mime;
}

private function clasificarArchivoCalificaciones(
    $mime,
    $extension
) {
    $extension = strtolower(
        trim($extension)
    );

    switch ($extension) {

        case "csv":
            return "csv";

        case "xlsx":
        case "xls":
            return "excel";

        case "pdf":
            return "pdf";

        case "jpg":
        case "jpeg":
        case "png":
        case "webp":
            return "image";
    }

    switch ($mime) {

        case "text/csv":
        case "application/csv":
        case "text/plain":
            return "csv";

        case "application/vnd.ms-excel":
        case "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet":
            return "excel";

        case "application/pdf":
            return "pdf";

        case "image/jpeg":
        case "image/png":
        case "image/webp":
            return "image";
    }

    return "unsupported";
}

private function convertirCalificacionesARows(
    $analisis,
    $contexto
) {
    $criterios = [];

    if (
        isset($contexto["criterios"]) &&
        is_array($contexto["criterios"])
    ) {
        foreach (
            $contexto["criterios"]
            as $criterio
        ) {
            if (
                is_array($criterio) &&
                isset($criterio["nombre"]) &&
                trim($criterio["nombre"]) !== ""
            ) {
                $criterios[] = trim(
                    $criterio["nombre"]
                );
            }
        }
    }

    if (
        count($criterios) === 0 &&
        isset($analisis["criterios_detectados"]) &&
        is_array(
            $analisis["criterios_detectados"]
        )
    ) {
        $criterios =
            $analisis["criterios_detectados"];
    }

    $headers = [
        "Matrícula",
        "Alumno"
    ];

    foreach ($criterios as $criterio) {
        $headers[] = $criterio;
    }

    $rows = [];
    $rows[] = $headers;

    if (
        !isset($analisis["alumnos"]) ||
        !is_array($analisis["alumnos"])
    ) {
        return $rows;
    }

    foreach (
        $analisis["alumnos"]
        as $alumno
    ) {
        if (!is_array($alumno)) {
            continue;
        }

        $matricula = isset(
            $alumno["matricula"]
        )
            ? trim($alumno["matricula"])
            : "";

        $nombre = isset(
            $alumno["nombre"]
        )
            ? trim($alumno["nombre"])
            : "";

        if (
            $matricula === "" &&
            $nombre === ""
        ) {
            continue;
        }

        $mapaCalificaciones = [];

        if (
            isset($alumno["calificaciones"]) &&
            is_array(
                $alumno["calificaciones"]
            )
        ) {
            foreach (
                $alumno["calificaciones"]
                as $calificacion
            ) {
                if (
                    !is_array($calificacion) ||
                    !isset(
                        $calificacion["criterio"]
                    )
                ) {
                    continue;
                }

                $clave =
                    $this->normalizarCriterioEscolar(
                        $calificacion["criterio"]
                    );

                $valor = isset(
                    $calificacion["calificacion"]
                )
                    ? $calificacion["calificacion"]
                    : "";

                if (
                    $valor !== "" &&
                    $valor !== null
                ) {
                    $valor = (float) $valor;

                    if ($valor < 0) {
                        $valor = 0;
                    }

                    if ($valor > 10) {
                        $valor = 10;
                    }
                } else {
                    $valor = "";
                }

                $mapaCalificaciones[$clave] =
                    $valor;
            }
        }

        $row = [
            $matricula,
            $nombre
        ];

        foreach ($criterios as $criterio) {
            $claveCriterio =
                $this->normalizarCriterioEscolar(
                    $criterio
                );

            $valor = "";

            if (
                array_key_exists(
                    $claveCriterio,
                    $mapaCalificaciones
                )
            ) {
                $valor =
                    $mapaCalificaciones[
                        $claveCriterio
                    ];
            } else {
                foreach (
                    $mapaCalificaciones
                    as $claveDetectada => $valorDetectado
                ) {
                    if (
                        strpos(
                            $claveDetectada,
                            $claveCriterio
                        ) !== false ||
                        strpos(
                            $claveCriterio,
                            $claveDetectada
                        ) !== false
                    ) {
                        $valor =
                            $valorDetectado;
                        break;
                    }
                }
            }

            $row[] = $valor;
        }

        $rows[] = $row;
    }

    return $rows;
}

private function normalizarCriterioEscolar(
    $texto
) {
    $texto = trim((string) $texto);

    if (function_exists("iconv")) {
        $convertido = iconv(
            "UTF-8",
            "ASCII//TRANSLIT//IGNORE",
            $texto
        );

        if ($convertido !== false) {
            $texto = $convertido;
        }
    }

    $texto = strtolower($texto);

    $texto = preg_replace(
        "/[^a-z0-9]+/",
        "",
        $texto
    );

    return $texto;
}


    public function normalizeFilesArray($filesInput) {
        $normalized = [];
        $count = count($filesInput['name']);
        for ($i = 0; $i < $count; $i++) {
            $normalized[] = [
                'name' => $filesInput['name'][$i],
                'type' => $filesInput['type'][$i],
                'tmp_name' => $filesInput['tmp_name'][$i],
                'error' => $filesInput['error'][$i],
                'size' => $filesInput['size'][$i]
            ];
        }
        return $normalized;
    }



}
?>