<?php 
class apiRestAI{
    public function __construct() {
        $this->apiKey = "";
        $this->url_responses = 'https://api.openai.com/v1/responses';
        $this->url_completions = 'https://api.openai.com/v1/chat/completions';
    }

    public function generarContratoTP($titulo_contrato, $variables, $tipo_persona){
        $model = "gpt-5-mini";
    
        $mensaje = "Eres un experto en derecho laboral, civil y comercial, especializado en la redacción profesional de contratos personalizados. Tu tarea es generar el contenido completo y detallado de un contrato formal, tomando como base el título, el tipo de persona involucrada (trabajador, cliente o proveedor), y un conjunto de variables dinámicas que se integrarán en el cuerpo del contrato.

        Título de referencia (no lo incluyas en la respuesta): {$titulo_contrato}
        Tipo de persona: {$tipo_persona}
        Variables disponibles: " . $variables . "

        Importante: Si recibes algo que no sea un titulo coherente(por ejemplo caracteres especiales, simbolos), interpretalo como prueba y regresa un contrato de ejemplo simplemente
        
        Las variables que debes utilizar están entre doble llave {{}}. Ejemplos: {{nombre}}, {{identificacion}}, {{fecha_inicio}}, {{objeto_contrato}}, {{duracion}}, {{honorarios}}, {{responsabilidades}}, etc.
        
        Instrucciones:
        - El contrato debe iniciar con una introducción narrativa clara, que presente a las partes involucradas, su voluntad de celebrar el contrato y que enmarque legalmente la relación antes de comenzar con las cláusulas numeradas.
        - No incluyas ningún título o encabezado al principio del texto (como “Contrato laboral”, “Contrato de servicios”, etc.). Comienza directamente con el contenido del contrato desde la introducción.
        - El texto debe tener redacción legal, formal y estructurada.
        - Asegúrate de adaptar el contrato al tipo de persona:
            - Trabajador: relación laboral formal con cláusulas como funciones, jornada, salario, duración, confidencialidad, etc.
            - Cliente: relación de prestación de servicios o colaboración comercial.
            - Proveedor: suministro de productos o servicios contratados por la empresa.
        - Integra todas las variables proporcionadas de forma fluida y coherente.
        - Resalta en negritas aquellas partes del texto que consideres especialmente importantes o críticas, como cláusulas legales, condiciones esenciales, montos, fechas, obligaciones clave, conceptos jurídicos relevantes, etc. Usa formato Markdown (**texto en doble asterisco**) para ello.
        - **No debes dejar ningún campo con líneas en blanco (como ____, ----, etc.). Si no conoces el valor específico, usa una variable entre llaves como {{ciudad}}, {{fecha}}, {{monto}}, etc.**
        - No agregues secciones de firma ni encabezados automáticos.
        - Devuelve exclusivamente el cuerpo completo del contrato, sin explicaciones ni títulos externos.
        
        Devuelve únicamente el contenido del contrato, con introducción narrativa, cláusulas numeradas, y partes clave destacadas en negritas (Markdown).";
        

        $getData = $this->procesarConsulta($mensaje, $model);
        return $getData;
    }

    public function analizarDatosAds($dataAd){
        $model = "gpt-5-mini";

        $mensaje = "Actúa como un analista profesional de marketing digital y medios pagados. Tu tarea es analizar un conjunto de datos sobre anuncios provenientes de distintas plataformas (como Facebook, Instagram, TikTok, etc.), y generar un informe claro y profesional sobre el rendimiento de los anuncios.
        
        Cada dato incluye:
        - ID del anuncio
        - Plataforma (ej. Facebook, Instagram, etc.)
        - Tipo de contenido (ej. ad, post, campaign, story, group)
        - Cuerpo del anuncio o texto visible
        - Fecha de interacción

        Si es el mismo id, es proque es el mismo anuncio, no son anuncios diferente, entonces lo del body y el headline serán el mismo, no son auncios diferentes, así que debes considerar la cantidad de interacciones por auncio por la cantidad que se repite el ID.
        
        El objetivo es que entregues un análisis completo de desempeño, incluyendo:
        1. **Resumen general** del rendimiento de los anuncios: cuál fue la plataforma más activa, el tipo de contenido más efectivo, y un análisis breve de los resultados.
        2. **Detección de patrones**: ¿hay alguna plataforma o tipo de anuncio que destaque en interacciones?
        3. **Recomendaciones accionables**: sugiere mejoras concretas para próximos anuncios según los datos observados (por ejemplo, usar más stories en Instagram, evitar publicaciones tipo post con bajo desempeño, priorizar videos si son más efectivos, etc.).
        4. **Análisis de copy**: con base en los cuerpos/textos, ¿alguno sobresale por ser persuasivo o débil? ¿Qué tipo de lenguaje se recomienda?
        5. **Ideas para tests A/B**: basadas en la comparación de tipos de anuncios, plataformas y formatos.
        
        Importante:
        - Redacta el análisis en un lenguaje claro, profesional y directo, como si fuera dirigido a un equipo de marketing.
        - No inventes datos, usa únicamente lo que se te proporciona.
        - Separa las secciones con subtítulos claros.
        - Si hay poca data o resultados muy similares, indícalo y sé conciso.
        
        Datos a analizar:
        " . $dataAd . "
        
        Devuelve únicamente el análisis completo. No incluyas encabezados como 'Respuesta:', ni instrucciones extra.";
        
        $getData = $this->procesarConsulta($mensaje, $model);
        return $getData;
    }

    public function analizarDatosProductos($dataMuestra){
        $model = "gpt-5-mini";
        
        $mensaje = "Eres un analista experto en datos de inventario y productos.
        Voy a pasarte algunas filas de ejemplo de un archivo que contiene productos con diferentes encabezados.
        Necesito que deduzcas qué encabezado corresponde a cada uno de los siguientes **campos canónicos**
        (escribe EXACTAMENTE cada key tal como aparece en la siguiente lista, con MAYÚSCULAS, espacios y acentos):
        
        Devuélveme un JSON con este formato EXACTO:

        El tipo puede ser producto o servicio, y el precio varaible es que si puede cambiar de precio o no
         
        {
            \"Nombre (Obligatorio)\": \"<encabezado o null>\",
            \"Tipo\": \"<encabezado o null>\",
            \"Precio Variable\": \"<encabezado o null>\",
            \"Precio de venta 1 (Obligatorio)\": \"<encabezado o null>\",
            \"Precio 2\": \"<encabezado o null>\",
            \"Precio 3\": \"<encabezado o null>\",
            \"Precio 4\": \"<encabezado o null>\",
            \"Precio 5\": \"<encabezado o null>\",
            \"Precio 6\": \"<encabezado o null>\",
            \"Precio 7\": \"<encabezado o null>\",
            \"Precio 8\": \"<encabezado o null>\",
            \"Precio 9\": \"<encabezado o null>\",
            \"Precio 10\": \"<encabezado o null>\",
            \"Categoria (Obligatorio)\": \"<encabezado o null>\",
            \"Codigo de barras\": \"<encabezado o null>\",
            \"Codigo interno\": \"<encabezado o null>\",
            \"Descripcion\": \"<encabezado o null>\",
            \"Ficha tecnica\": \"<encabezado o null>\",
            \"Clave Producto Servicio SAT\": \"<encabezado o null>\",
            \"Unidad de Medida SAT\": \"<encabezado o null>\",
            \"Tasa IVA\": \"<encabezado o null>\",
            \"Tasa o Cuota IEPS\": \"<encabezado o null>\",
            \"IEPS\": \"<encabezado o null>\",
            \"Tipo de calculo IEPS/IVA\": \"<encabezado o null>\",
            \"Numero de piezas en inventario\": \"<encabezado o null>\",
            \"Costo total de Inventario\": \"<encabezado o null>\",
            \"Inventario Minimo\": \"<encabezado o null>\",
            \"Inventario Maximo\": \"<encabezado o null>\",
            \"RFC del Proveedor\": \"<encabezado o null>\",
            \"Nombre del Proveedor\": \"<encabezado o null>\",
            \"Link: Imagen de Producto / Servicio\": \"<encabezado o null>\",
            \"Dias de garantia\": \"<encabezado o null>\",
            \"Dias de respuesta a cliente por garantia\": \"<encabezado o null>\",
            \"Terminos y condiciones (Garantia)\": \"<encabezado o null>\",
            \"Requiere Orden de Trabajo\": \"<encabezado o null>\",
            \"Producto ID de Mercado Libre\": \"<encabezado o null>\"
        }

        
        Datos a analizar (encabezado: valor):
        " . $dataMuestra . "

        Response únicamente un objeto JSON válido que contenga exactamente esos campos (no lo envuelvas en un arreglo). No incluyas ningún texto adicional, sin etiquetas markdown como ```json o json,  ni comillas invertidas, ni comentarios, ni encabezados, ni explicaciones. Solo el arreglo limpio.
        ";
        
        $getData = $this->procesarConsulta($mensaje, $model);
        return $getData;
        

    }

    public function searchWebAI($pregunta, $model) {
        $data = [
            "model" => $model,
            "input" => $pregunta,
            "tools" => [[
                "type" => "web_search_preview",
                "search_context_size" => "medium"
            ]]
        ];
    
        $payload = json_encode($data);
    
        $ch = curl_init($this->url_responses);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    
        $json = [];

        $data = json_decode($response, true);

        $mensaje = null;
        foreach ($data['output'] as $item) {
            if ($item['type'] == 'message' && $item['status'] == 'completed') {
                $mensaje = $item;
                break;
            }
        }
    
        if ($httpCode == 200 && isset($mensaje['content'][0]['text'])) {
            $textoPlano = $mensaje['content'][0]['text'];
            $decodificado = json_decode($textoPlano, true);
            $json['status'] = 'ok';
            if (json_last_error() === JSON_ERROR_NONE) {
                $json['answer'] = $decodificado;
            } else {
                $json['answer'] = $textoPlano; 
            }
            $json['code'] = $httpCode;
        } else {
            $json['status'] = 'error';
            $json['answer'] = $data['error']['message'] ?? 'Error desconocido';
            $json['data'] = $data;
            $json['code'] = $httpCode;
        }
    
        return $json;
    }

    public function procesarConsulta($pregunta, $model = "gpt-4.1-nano") {
        $data = [
            "model" => $model,
            "input" => $pregunta
        ];
    
        $payload = json_encode($data);
    
        $ch = curl_init($this->url_responses);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    
        $json = [];

        $data = json_decode($response, true);

        $mensaje = null;
        foreach ($data['output'] as $item) {
            if ($item['type'] == 'message' && $item['status'] == 'completed') {
                $mensaje = $item;
                break;
            }
        }
    
        if ($httpCode == 200 && isset($mensaje['content'][0]['text'])) {
            $textoPlano = $mensaje['content'][0]['text'];
            $decodificado = json_decode($textoPlano, true);
            $json['status'] = 'ok';
            if (json_last_error() === JSON_ERROR_NONE) {
                $json['answer'] = $decodificado;
            } else {
                $json['answer'] = $textoPlano; 
            }
            $json['code'] = $httpCode;
        } else {
            $json['status'] = 'error';
            $json['answer'] = $data['error']['message'] ?? 'Error desconocido';
            $json['data'] = $data;
            $json['code'] = $httpCode;
        }
    
        return $json;
    }

    public function analizarCalificacionesEscolares(
    $tipoEntrada,
    $contenido,
    $contexto = [],
    $nombreArchivo = "",
    $mime = ""
) {
    $model = "gpt-5";

    $contextoJson = json_encode(
        $contexto,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    $mensaje =
        'Eres un sistema experto en control escolar y captura de calificaciones. ' .
        'Tu tarea es revisar el archivo proporcionado y extraer exclusivamente las calificaciones reales que aparezcan en él. ' .
        'El archivo puede ser una hoja de cálculo, CSV, PDF, fotografía, captura de pantalla o documento escaneado.' .
        PHP_EOL .
        PHP_EOL .
        'CONTEXTO CONFIGURADO EN EL SISTEMA:' .
        PHP_EOL .
        $contextoJson .
        PHP_EOL .
        PHP_EOL .
        'INSTRUCCIONES OBLIGATORIAS:' .
        PHP_EOL .
        '1. Detecta las filas que correspondan a alumnos y no incluyas encabezados, subtotales, promedios generales ni filas de resumen como alumnos.' .
        PHP_EOL .
        '2. Extrae la matrícula exactamente como aparece. Si no aparece, puedes utilizar una matrícula del listado configurado solamente cuando el nombre coincida claramente con un alumno. Si no existe coincidencia confiable, deja la matrícula vacía.' .
        PHP_EOL .
        '3. Extrae el nombre completo del alumno sin inventar apellidos ni corregir datos de manera arbitraria.' .
        PHP_EOL .
        '4. Relaciona cada columna de calificaciones con los criterios configurados. Considera equivalencias evidentes como Examen y Exámenes, Participación y Participaciones, pero no inventes equivalencias dudosas.' .
        PHP_EOL .
        '5. Las calificaciones finales deben estar en una escala de 0 a 10.' .
        PHP_EOL .
        '6. Si el documento usa una escala de 0 a 100, conviértela a 0 a 10 solamente cuando la escala sea clara y agrega una advertencia.' .
        PHP_EOL .
        '7. Si una calificación no puede leerse con seguridad, devuelve null. Nunca inventes una calificación.' .
        PHP_EOL .
        '8. Si solamente aparece un promedio final y no aparecen los criterios individuales, no distribuyas ni fabriques calificaciones por criterio.' .
        PHP_EOL .
        '9. Devuelve todos los alumnos visibles y todas las calificaciones legibles.' .
        PHP_EOL .
        '10. La confianza de cada calificación y la confianza general deben ser números entre 0 y 1.' .
        PHP_EOL .
        '11. En criterios_detectados coloca únicamente nombres de columnas que realmente correspondan a evaluaciones.' .
        PHP_EOL .
        '12. Devuelve exclusivamente la estructura JSON solicitada.';

    $schema = [
        "type" => "object",
        "properties" => [
            "materia" => [
                "type" => "string"
            ],
            "grado_grupo" => [
                "type" => "string"
            ],
            "periodo" => [
                "type" => "string"
            ],
            "ciclo_escolar" => [
                "type" => "string"
            ],
            "criterios_detectados" => [
                "type" => "array",
                "items" => [
                    "type" => "string"
                ]
            ],
            "alumnos" => [
                "type" => "array",
                "items" => [
                    "type" => "object",
                    "properties" => [
                        "matricula" => [
                            "type" => "string"
                        ],
                        "nombre" => [
                            "type" => "string"
                        ],
                        "calificaciones" => [
                            "type" => "array",
                            "items" => [
                                "type" => "object",
                                "properties" => [
                                    "criterio" => [
                                        "type" => "string"
                                    ],
                                    "calificacion" => [
                                        "type" => [
                                            "number",
                                            "null"
                                        ]
                                    ],
                                    "confianza" => [
                                        "type" => "number"
                                    ]
                                ],
                                "required" => [
                                    "criterio",
                                    "calificacion",
                                    "confianza"
                                ],
                                "additionalProperties" =>
                                    false
                            ]
                        ],
                        "observaciones" => [
                            "type" => "array",
                            "items" => [
                                "type" => "string"
                            ]
                        ]
                    ],
                    "required" => [
                        "matricula",
                        "nombre",
                        "calificaciones",
                        "observaciones"
                    ],
                    "additionalProperties" =>
                        false
                ]
            ],
            "advertencias" => [
                "type" => "array",
                "items" => [
                    "type" => "string"
                ]
            ],
            "confianza_general" => [
                "type" => "number"
            ]
        ],
        "required" => [
            "materia",
            "grado_grupo",
            "periodo",
            "ciclo_escolar",
            "criterios_detectados",
            "alumnos",
            "advertencias",
            "confianza_general"
        ],
        "additionalProperties" => false
    ];

    $content = [];

    switch ($tipoEntrada) {

        case "image":

            $content[] = [
                "type" => "input_text",
                "text" => $mensaje
            ];

            $content[] = [
                "type" => "input_image",
                "image_url" => $contenido,
                "detail" => "high"
            ];

            break;

        case "file":

            $content[] = [
                "type" => "input_text",
                "text" => $mensaje
            ];

            $inputFile = [
                "type" => "input_file",
                "filename" => $nombreArchivo,
                "file_data" => $contenido
            ];

            if ($mime === "application/pdf") {
                $inputFile["detail"] = "high";
            }

            $content[] = $inputFile;

            break;

        default:

            $content[] = [
                "type" => "input_text",
                "text" =>
                    $mensaje .
                    PHP_EOL .
                    PHP_EOL .
                    'DATOS EXTRAÍDOS DEL ARCHIVO:' .
                    PHP_EOL .
                    $contenido
            ];

            break;
    }

    return $this->procesarConsultaEstructurada(
        $content,
        $schema,
        $model
    );
}

public function procesarConsultaEstructurada(
    $content,
    $schema,
    $model = "gpt-5-mini"
) {
    $json = [];

    $data = [
        "model" => $model,
        "store" => false,
        "input" => [
            [
                "role" => "user",
                "content" => $content
            ]
        ],
        "text" => [
            "format" => [
                "type" => "json_schema",
                "name" =>
                    "calificaciones_escolares",
                "strict" => true,
                "schema" => $schema
            ]
        ],
        "max_output_tokens" => 12000
    ];

    $payload = json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    if ($payload === false) {
        $json["status"] = "error";
        $json["answer"] =
            "No fue posible preparar la solicitud para la IA.";
        $json["code"] = 500;

        return $json;
    }

    $ch = curl_init(
        $this->url_responses
    );

    curl_setopt(
        $ch,
        CURLOPT_RETURNTRANSFER,
        true
    );

    curl_setopt(
        $ch,
        CURLOPT_HTTPHEADER,
        [
            "Content-Type: application/json",
            "Authorization: Bearer " .
                $this->apiKey
        ]
    );

    curl_setopt(
        $ch,
        CURLOPT_POST,
        true
    );

    curl_setopt(
        $ch,
        CURLOPT_POSTFIELDS,
        $payload
    );

    curl_setopt(
        $ch,
        CURLOPT_TIMEOUT,
        120
    );

    $response = curl_exec($ch);
    $curlError = curl_error($ch);

    $httpCode = curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    curl_close($ch);

    if ($response === false) {
        $json["status"] = "error";
        $json["answer"] =
            "No fue posible conectar con la IA: " .
            $curlError;
        $json["code"] = 500;

        return $json;
    }

    $dataResponse = json_decode(
        $response,
        true
    );

    if (!is_array($dataResponse)) {
        $json["status"] = "error";
        $json["answer"] =
            "La IA regresó una respuesta inválida.";
        $json["raw_response"] = $response;
        $json["code"] = $httpCode;

        return $json;
    }

    if ($httpCode !== 200) {
        $json["status"] = "error";
        $json["answer"] =
            isset(
                $dataResponse["error"]["message"]
            )
                ? $dataResponse["error"]["message"]
                : "Ocurrió un error al analizar el archivo.";
        $json["data"] = $dataResponse;
        $json["code"] = $httpCode;

        return $json;
    }

    $textoPlano = "";
    $rechazo = "";

    if (
        isset($dataResponse["output"]) &&
        is_array($dataResponse["output"])
    ) {
        foreach (
            $dataResponse["output"]
            as $item
        ) {
            if (
                !isset($item["type"]) ||
                $item["type"] !== "message" ||
                !isset($item["content"]) ||
                !is_array($item["content"])
            ) {
                continue;
            }

            foreach (
                $item["content"]
                as $contentItem
            ) {
                if (
                    isset($contentItem["type"]) &&
                    $contentItem["type"] ===
                        "output_text" &&
                    isset($contentItem["text"])
                ) {
                    $textoPlano .=
                        $contentItem["text"];
                }

                if (
                    isset($contentItem["type"]) &&
                    $contentItem["type"] ===
                        "refusal" &&
                    isset($contentItem["refusal"])
                ) {
                    $rechazo =
                        $contentItem["refusal"];
                }
            }
        }
    }

    if ($rechazo !== "") {
        $json["status"] = "error";
        $json["answer"] = $rechazo;
        $json["code"] = 422;

        return $json;
    }

    if (trim($textoPlano) === "") {
        $json["status"] = "error";
        $json["answer"] =
            "La IA no regresó información del archivo.";
        $json["data"] = $dataResponse;
        $json["code"] = 500;

        return $json;
    }

    $resultado = json_decode(
        $textoPlano,
        true
    );

    if (
        json_last_error() !==
        JSON_ERROR_NONE ||
        !is_array($resultado)
    ) {
        $json["status"] = "error";
        $json["answer"] =
            "No fue posible interpretar el JSON generado por la IA.";
        $json["raw_answer"] =
            $textoPlano;
        $json["code"] = 500;

        return $json;
    }

    $json["status"] = "ok";
    $json["answer"] = $resultado;
    $json["code"] = 200;

    return $json;
}

}

?>