
<?php

date_default_timezone_set('America/Mexico_City');
header("Content-Type: application/json; charset=utf-8");

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/conexion.php";
require_once __DIR__ . "/consultas.php";

$session = isset($_POST["session"]) ? trim((string)$_POST["session"]) : "";
$action = isset($_POST["action"]) ? trim((string)$_POST["action"]) : "";

$response = [];

if ($action === "") {
    $response["status"] = "ok";
    $response["answer"] = "El endpoint está activo.";
    $response["code"] = 200;
} elseif ($session === "") {
    $response["status"] = "error";
    $response["answer"] = "La sesión es requerida";
    $response["code"] = 401;
} else {
    $consultas = new consultas($session);

    switch ($action) {
        case "setComisiones":
            $response = $consultas->setComisiones($_POST);
        break;

        case "getComisiones":
            $tipo = isset($_POST["tipo"]) ? (int)$_POST["tipo"] : 0;
            $response = $consultas->getComisiones($tipo);
        break;

        case "updateComisiones":
            $response = $consultas->updateComisiones($_POST);
        break;

        case "eliminarComisiones":
            $id = isset($_POST["id"]) ? (int)$_POST["id"] : 0;
            $response = $consultas->eliminarComisiones($id);
        break;

        default:
            $response["status"] = "warning";
            $response["answer"] = "La acción solicitada no es válida.";
            $response["action"] = $action;
            $response["code"] = 400;
        break;
    }
}

print json_encode($response, JSON_UNESCAPED_UNICODE);

?>
