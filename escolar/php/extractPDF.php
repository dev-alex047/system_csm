<?php
require __DIR__ . '/adobeAR.php';

[$script, $jobLocation, $accessToken, $userNumber, $userName, $urlPDF, $timestamp] = $argv;

$adobe = new adobeAR();

$getInfoPDF = [];
$jsonFile = [];
$downloadUriJson = "";
$urlPathJson = "";
$saveJson = "";
$pathJson = "";
$raw_json = "";
$data_json = "";
$text_json = "";

$getInfoPDF = $adobe->pollJobStatus($jobLocation, $accessToken);

if($getInfoPDF["status"] == "ok"){
    $downloadUriJson = $getInfoPDF["downloadUriJson"];
    $urlPathJson = __DIR__ . "/../../audios/jsonPdfExtract_" . $userNumber . "_" . $timestamp . ".json";

    $saveJson = $adobe->downloadAsset($downloadUriJson, $urlPathJson);
    if($saveJson["status"] == "ok"){
        $pathJson = $saveJson["url_json"];
        $raw_json = $saveJson["data_json"];
        $data_json = json_decode($raw_json, true);
        
        $text_json = [];
        foreach($data_json['elements'] as $element){
            if (isset($element['Text'])) {
                $text_json[] = $element['Text'];
            }
        }

        $texto_json = implode("\n\n", $text_json);

        $numerosPermitidos = [
            "5215523155321",
            "5219531879907",
            "5219532115620"
        ];

        if(in_array($userNumber, $numerosPermitidos)){
            
            $jsonFile["status"] = "ok";
            $jsonFile["accion"] = "consultaAdmin";
            $jsonFile["text_json"] = $texto_json;
            $jsonFile["link_media"] = $urlPDF; 
            $jsonFile["user_number"] = $userNumber;
            $jsonFile["user_name"] = $userName;
            $jsonFile["code"] = 200;
            
            $jsonPayload = json_encode($jsonFile);
    
            $url = "https://app.aizu.ai/modulos/bot/admin/php/ejecutarAdmin.php";
            
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS     => $jsonPayload,
                CURLOPT_TIMEOUT        => 10
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
    
            $logFile = fopen(__DIR__ . "/curl_ExtractPDFAdmin.txt", "a");
            fwrite($logFile, "----------\n");
            fwrite($logFile, "Fecha: " . date("Y-m-d H:i:s") . "\n");
            fwrite($logFile, "Payload: " . $jsonPayload . "\n");
            fwrite($logFile, "HTTP Code: " . $httpCode . "\n");
            fwrite($logFile, "Respuesta: " . $response . "\n");
            if ($error) {
                fwrite($logFile, "Error cURL: " . $error . "\n");
            }
            fclose($logFile);

        }else{
            $jsonFile["status"] = "ok";
            $jsonFile["accion"] = "procesarMsj";
            $jsonFile["text_json"] = $texto_json;
            $jsonFile["link_media"] = $urlPDF; 
            $jsonFile["user_number"] = $userNumber;
            $jsonFile["user_name"] = $userName;
            $jsonFile["code"] = 200;
            
            $jsonPayload = json_encode($jsonFile);
    
            $url = "https://app.aizu.ai/modulos/bot/ventas/php/ejecutarVentasIA.php";
            
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS     => $jsonPayload,
                CURLOPT_TIMEOUT        => 10
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
    
            $logFile = fopen(__DIR__ . "/curl_ExtractPDF.txt", "a");
            fwrite($logFile, "----------\n");
            fwrite($logFile, "Fecha: " . date("Y-m-d H:i:s") . "\n");
            fwrite($logFile, "Payload: " . $jsonPayload . "\n");
            fwrite($logFile, "HTTP Code: " . $httpCode . "\n");
            fwrite($logFile, "Respuesta: " . $response . "\n");
            if ($error) {
                fwrite($logFile, "Error cURL: " . $error . "\n");
            }
            fclose($logFile);
        }
        
        
    }else{
        $jsonFile["status"] = "error";
        $jsonFile["answer"] = $saveJson["answer"];
        $jsonFile["code"] = 500;
    }
}else{
    $jsonFile["status"] = "error";
    $jsonFile["answer"] = $getInfoPDF["answer"] ?? '';
    $jsonFile["code"] = 501;
}
?>