
<?php
date_default_timezone_set('America/Mexico_City');
header("Content-Type: application/json; charset=utf-8");

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/conexion.php";
require_once __DIR__ . "/consultas.php";

$action = isset($_POST["action"]) ? trim((string)$_POST["action"]) : "";
$consultas = new consultas();
$response = [];

switch ($action) {
    case "getResumenInicial":
    $response = $consultas->getResumenInicial($_POST);
break;

    default:
        $response["status"] = "warning";
        $response["answer"] = "La acción solicitada no es válida.";
        $response["code"] = 400;
    break;
}

http_response_code(isset($response["code"]) ? (int)$response["code"] : 500);
print json_encode($response, JSON_UNESCAPED_UNICODE);
?>
