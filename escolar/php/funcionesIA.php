<?php 

class funcionesIA{
    private $apiKey;

    public function __construct(){
        $this->apiKey  = "";
        $this->url_completions = "https://api.openai.com/v1/chat/completions";
        $this->url_responses = "https://api.openai.com/v1/responses";
    }

    //Voz a texto
    public function vozATexto($filePath, $mimeType) {
        $json = [];
    
        try {
            if (!file_exists($filePath)) {
                throw new Exception('El archivo de audio no existe en la ruta especificada.');
            }
    
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/audio/transcriptions');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: multipart/form-data'
            ]);
    
            $postFields = [
                'file' => new CURLFile($filePath, $mimeType, basename($filePath)),
                'model' => 'whisper-1',
                'prompt' => 'Eres un asistente avanzado de transcripción diseñado para capturar con máxima precisión el contenido de los audios. Transcribe cada palabra con exactitud y evita errores en nombres propios. 
                    Mantén la gramática y puntuación adecuadas para asegurar una transcripción clara y profesional.', 
                'response_format' => 'json',
                'language' => 'es'
            ];
    
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
            $respuesta = curl_exec($ch);
    
            if (curl_errno($ch)) {
                throw new Exception('Error en la petición de transcripción: ' . curl_error($ch));
            }
    
            curl_close($ch);
    
            $resultado = json_decode($respuesta, true);
    
            if (isset($resultado['text'])) {
                $json['status'] = 'ok';
                $json['code'] = 200;
                $json['answer'] = $resultado['text'];
            } else {
                throw new Exception('Error en la transcripción. Respuesta: ' . $respuesta);
            }
    
        } catch (Exception $ex) {
            $json['status'] = 'error';
            $json['code'] = 'API-001';
            $json['res'] = $respuesta ?? null;
            $json['answer'] = $ex->getMessage();
        }
    
        return $json;
    }
    
    public function analizarImg($imageBase64) {
        $url = "https://api.openai.com/v1/chat/completions";
    
        $headers = [
            "Authorization: Bearer " . $this->apiKey,
            "Content-Type: application/json"
        ];
    
        $data = [
            "model" => "gpt-4.1-mini",
            "messages" => [
                [
                    "role" => "user",
                    "content" => [
                        [
                            "type" => "text",
                            "text" => "Analiza cuidadosamente la imagen para que extraigas todos los datos de proporciona, y realices una descripción detallada"
                        ],
                        [
                            "type" => "image_url",
                            "image_url" => [
                                "url" => $imageBase64,
                                "detail" => "high"
                            ]
                        ]
                    ]
                ]
            ],
            "max_tokens" => 2200
        ];
    
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    
        $response = curl_exec($ch);
        curl_close($ch);
    
        $decoded = json_decode($response, true);
    
        $json = [];

        if (isset($decoded["choices"][0]["message"]["content"])) {
            $json['status'] = "ok";
            $json['answer'] = trim($decoded["choices"][0]["message"]["content"]);
            $json['raw'] = $decoded;
        } else {
            $json['status'] = "error";
            $json['answer'] = $decoded["error"]["message"] ?? "Error desconocido";
            $json['raw'] = $decoded;
        }
        
        return $json;
    }
    

    public function procesarConsulta($msjChatGPT) {
        $json = [];
    
        try {
            $mensajes = [
                ['role' => 'system', 'content' => 'Eres un asistente útil que proporciona información precisa y concisa.'],
                ['role' => 'user', 'content' => $msjChatGPT]
            ];
    
            $data = [
                'model' => 'gpt-4o-mini',
                'messages' => $mensajes
            ];
    
            $ch = curl_init($this->url_completions);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    
            $response = curl_exec($ch);
    
            if ($response === false) {
                $error = curl_error($ch);
                curl_close($ch);
                $json["status"] = "error";
                $json["answer"] = "Error en cURL: $error";
                $json["code"] = 502;
                return $json;
            }
    
            curl_close($ch);
            $decodedResponse = json_decode($response, true);
    
            if (isset($decodedResponse['error'])) {
                $json["status"] = "error";
                $json["answer"] = $decodedResponse['error']['message'];
                $json["code"] = 501;
            } elseif (!isset($decodedResponse['choices'][0])) {
                $json["status"] = "error";
                $json["answer"] = "Respuesta inválida de la API";
                $json["code"] = 500;
            } else {
                $respuestaAsistente = $decodedResponse['choices'][0]['message']['content'];
                $json["status"] = "ok";
                $json["id"] = $decodedResponse['id'];
                $json["prompt"] = $decodedResponse['usage']['prompt_tokens'];
                $json["completion"] = $decodedResponse['usage']['completion_tokens'];
                $json["total"] = $decodedResponse['usage']['total_tokens'];
                $json["answer"] = $respuestaAsistente;
                $json["code"] = 200;
            }
    
        } catch (Exception $e) {
            $json["status"] = "error";
            $json["answer"] = "Error en la API: ";
            $json["code"] = 500;
        }
    
        return $json;
    }

    //RESPONSES
    public function searchWebAI($pregunta, $model = "gpt-4.1") {
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
            $clean = preg_replace('/\A\s*(?:Array)?\s*```(?:json)?\s*|\s*```(?:\s*Array)?\s*\z/i', '', $textoPlano);
            //$data  = json_decode($clean, true);
            $decodificado = json_decode($clean, true);
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

    
    


    
}

?>