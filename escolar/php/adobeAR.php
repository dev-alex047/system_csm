<?php 
class adobeAR {
    private $clientId;
    private $clientSecret;
    public function __construct(){
        $this->clienteID = "";
        $this->clienteSEC = "";
        $this->orgID = "";
    }
    function getAdobeAccessToken() {
        $json = [];
    
        $url = 'https://pdf-services.adobe.io/token';
        $data = http_build_query([
            'client_id' => $this->clienteID,
            'client_secret' => $this->clienteSEC
        ]);
    
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
    
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    
        $resultado = json_decode($response, true);
    
        if (isset($resultado['access_token'])) {
            $json['status'] = 'ok';
            $json['answer'] = 'Token obtenido correctamente';
            $json['access_token'] = $resultado['access_token'];
            $json['expires_in'] = $resultado['expires_in'];
            $json['event'] = $resultado;
            $json['code'] = 200;
        } else {
            $json['status'] = 'error';
            $json['answer'] = 'Error al obtener el token';
            $json['error'] = $resultado['error'] ?? 'Error desconocido';
            $json['response'] = $response;
            $json['code'] = $httpCode;
        }
    
        return $json;
    }

    function getAdobeUploadUrl($accessToken, $mediaType) {
        $json = [];
    
        $url = 'https://pdf-services.adobe.io/assets';
    
        $payload = json_encode([
            'mediaType' => $mediaType
        ]);
    
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $accessToken",
            "X-API-Key: $this->clienteID",
            "Content-Type: application/json"
        ]);
    
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    
        $resultado = json_decode($response, true);
    
        if (isset($resultado['uploadUri'])) {
            $json['status'] = 'ok';
            $json['answer'] = 'Upload URI obtenido correctamente';
            $json['uploadUri'] = $resultado['uploadUri'];
            $json['assetID'] = $resultado['assetID'];
            $json['code'] = 200;
        } else {
            $json['status'] = 'error';
            $json['answer'] = $resultado['error']['message'] ?? 'No se pudo obtener la URI de subida';
            $json['error'] = $resultado['error'] ?? 'Error desconocido';
            $json['code'] = $httpCode;
        }
    
        return $json;
    }

    function uploadFileToAdobe($uploadUri, $filePath) {
        $json = [];
    
        if (!file_exists($filePath)) {
            $json['status'] = 'error';
            $json['answer'] = 'El archivo no existe en la ruta especificada';
            $json['code'] = 404;
            $json['error'] = 'Archivo no encontrado';
            return $json;
        }
    
        $fileData = file_get_contents($filePath);
    
        $ch = curl_init($uploadUri);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fileData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/pdf'
        ]);
    
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    
        if ($httpCode == 200) {
            $json['status'] = 'ok';
            $json['answer'] = 'Archivo subido correctamente a Adobe';
            $json['link'] = $uploadUri;
            $json['response'] = $response;
        } else {
            $json['status'] = 'error';
            $json['answer'] = 'Error al subir el archivo PDF';
            $json['code'] = $httpCode;
            $json['error'] = 'No se pudo subir el archivo';
            $json['response'] = $response;
            $json['fileData'] = $fileData;
            $json['ch'] = $ch;
        }
    
        return $json;
    }

    function createExtractTextJob($accessToken, $assetId) {
        $json = [];
    
        $url   = 'https://pdf-services.adobe.io/operation/extractpdf';
        
        $payload = json_encode([
            'assetID'           => $assetId,
            'elementsToExtract' => ['text']
        ]);
    
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer $accessToken",
                "X-API-Key: {$this->clienteID}",
                "Content-Type: application/json",
                "Content-Length: " . strlen($payload)
            ],
            CURLOPT_POSTFIELDS     => $payload,
        ]);
    
        $rawResponse = curl_exec($ch);
        $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
    
        $header = substr($rawResponse, 0, $headerSize);
        $body   = substr($rawResponse, $headerSize);
    
        $location = null;
        foreach (explode("\r\n", $header) as $line) {
            if (stripos($line, 'Location:') === 0) {
                $location = trim(substr($line, 9));
                break;
            }
        }
    
        if ($httpCode == 201 && $location) {
            $parts = explode('/', trim(parse_url($location, PHP_URL_PATH), '/'));
            $jobID = count($parts) >= 2 ? $parts[count($parts)-2] : null;
            
            $json['status'] = 'ok';
            $json['answer'] = 'Trabajo de extracción creado correctamente';
            $json['job_location'] = $location;
            $json['job_id']  = $jobID;
            $json['response'] = $body;  
            $json['code'] = 200;    
        } else {
            $json['status'] = 'error';
            $json['answer'] = 'Error al crear el trabajo de extracción';
            $err = json_decode($body, true);
            $json['error'] = $err['error']['message'] ?? 'Sin detalle';
            $json['response'] = $body;
            $json['code'] = $httpCode;
        }
    
        return $json;
    }

    function createExtractTextJobWebHook($accessToken, $assetId) { //conWebHook
        $json = [];
    
        $url = 'https://pdf-services.adobe.io/operation/extractpdf';
    
        $callbackUrl = 'https://app.aizu.ai/modulos/bot/ventas/php/webhook_adobe.php';
    
        $body = [
            'assetID'           => $assetId,
            'elementsToExtract' => ['text', 'tables'], 
            'tableOutputFormat' => 'xlsx',
            'notifiers' => [
                [
                    'type' => 'CALLBACK',
                    'data' => [
                        'url' => $callbackUrl,
                        'headers' => [
                            'x-api-key' => $this->clienteID,
                            'access-token' => $accessToken
                        ]
                    ]
                ]
            ]
        ];
    
        $payload = json_encode($body);
    
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer $accessToken",
                "X-API-Key: {$this->clienteID}",
                "Content-Type: application/json",
                "Content-Length: " . strlen($payload)
            ],
            CURLOPT_POSTFIELDS     => $payload,
        ]);
    
        $rawResponse = curl_exec($ch);
        $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
    
        $header = substr($rawResponse, 0, $headerSize);
        $body   = substr($rawResponse, $headerSize);
    
        $location = null;
        foreach (explode("\r\n", $header) as $line) {
            if (stripos($line, 'Location:') === 0) {
                $location = trim(substr($line, 9));
                break;
            }
        }
    
        if ($httpCode == 201 && $location) {
            $parts = explode('/', trim(parse_url($location, PHP_URL_PATH), '/'));
            $jobID = count($parts) >= 2 ? $parts[count($parts)-2] : null;
    
            $json['status']       = 'ok';
            $json['answer']       = 'Trabajo de extracción creado correctamente';
            $json['job_location'] = $location;
            $json['job_id']       = $jobID;
            $json['response']     = $body;
            $json['code']         = 200;
        } else {
            $json['status']   = 'error';
            $json['answer']   = 'Error al crear el trabajo de extracción';
            $err              = json_decode($body, true);
            $json['error']    = $err['error']['message'] ?? 'Sin detalle';
            $json['response'] = $body;
            $json['code']     = $httpCode;
        }
    
        return $json;
    }
    

    function pollJobStatusOld($location, $accessToken) {
        $json = [];
    
        $ch = curl_init($location);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $accessToken",
            "x-api-key: $this->clienteID"
        ]);
    
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    
        $resultado = json_decode($response, true);
    
        if ($httpCode == 200) {
            $json['status'] = 'ok';
            $json['answer'] = 'Estado obtenido';
            $json['jobStatus'] = $resultado['status'] ?? '';  
            $json['downloadUriJson'] = $resultado['content']['downloadUri'] ?? '';
            $json['assetIdJson'] = $resultado['content']['assetID'] ?? '';
            $json['downloadUriZip'] = $resultado['resource']['downloadUri'] ?? '';
            $json['assetIdZip'] = $resultado['resource']['assetID'] ?? '';
            $json['event'] = $resultado;
            $json['code'] = 200;
        } else {
            $json['status'] = 'error';
            $json['answer'] = 'Error al consultar estado';
            $json['error'] = $resultado['error']  ?? 'Sin detalle';
            $json['response'] = $response;
            $json['code'] = $httpCode;
        }
    
        return $json;
    }

    function pollJobStatus($location, $accessToken, $maxIntentos = 6, $esperaSegundos = 20) {
        $json = [];
        $intento = 0;
    
        while ($intento < $maxIntentos) {
            $ch = curl_init($location);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer $accessToken",
                "x-api-key: $this->clienteID"
            ]);
    
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
    
            $resultado = json_decode($response, true);
    
            if ($httpCode == 200) {
                $jobStatus = strtolower($resultado['status'] ?? '');
    
                if ($jobStatus == 'done') {
                    $json['status'] = 'ok';
                    $json['answer'] = 'Proceso completado';
                    $json['jobStatus'] = $jobStatus;
                    $json['downloadUriJson'] = $resultado['content']['downloadUri'] ?? '';
                    $json['assetIdJson'] = $resultado['content']['assetID'] ?? '';
                    $json['downloadUriZip'] = $resultado['resource']['downloadUri'] ?? '';
                    $json['assetIdZip'] = $resultado['resource']['assetID'] ?? '';
                    $json['data'] = $resultado;
                    $json['code'] = 200;
                    return $json;
                } elseif ($jobStatus == 'failed') {
                    $json['status'] = 'error';
                    $json['answer'] = 'El procesamiento del PDF falló.';
                    $json['jobStatus'] = $jobStatus;
                    $json['data'] = $resultado;
                    $json['code'] = 500;
                    return $json;
                }else{
                    sleep($esperaSegundos);
                }
               
            } else {
                $json['status'] = 'error';
                $json['answer'] = 'Error al consultar estado';
                $json['error'] = $resultado['error'] ?? 'Sin detalle';
                $json['response'] = $response;
                $json['code'] = $httpCode;
                return $json;
            }
    
            $intento++;
        }
    
        $json['status'] = 'error';
        $json['answer'] = 'Tiempo de espera agotado. El job no finalizó a tiempo.';
        $json['code'] = 408;

        return $json;
    }
    
    function downloadAsset($downloadUri, $savePath) {
        $json = [];
    
        $ch = curl_init($downloadUri);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    
        if ($httpCode == 200 && $data) {
            file_put_contents($savePath, $data);
            $json['status'] = 'ok';
            $json['answer'] = 'Archivo JSOM descargado correctamente';
            $json['url_json'] = $savePath;
            $json['data_json'] = $data;
            $json['code'] = 200;
        } else {
            $json['status'] = 'error';
            $json['answer'] = 'Error al descargar archivo JSON';
            $json['error'] = 'No se obtuvo contenido';
            $json['code'] = $httpCode;
        }
    
        return $json;
    }    
}

?>