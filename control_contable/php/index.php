
<?php
date_default_timezone_set('America/Mexico_City');
define("DEBUG_NOTA_PDF", true);
header("Content-Type: application/json; charset=utf-8");

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/conexion.php";
require_once __DIR__ . "/consultas.php";
require_once __DIR__ . "/notaVenta.php";

$action = isset($_POST["action"]) ? trim((string)$_POST["action"]) : "";
$consultas = new consultas();
$response = [];

switch ($action) {
    case "getResumenInicial":
        $response = $consultas->getResumenInicial($_POST);
        break;
        
    case "getCajasVenta":
        $response = $consultas->getCajasVenta();
        break;
        
    case "guardarVentaNota":
        $response = $consultas->guardarVentaNota($_POST);
        break;

    
case "guardarVentaRapida":
    $response = $consultas->guardarVentaRapida($_POST);
break;


    case "generarPdfNotaVenta":
        $idVenta = isset($_POST["id"]) ? (int)$_POST["id"] : 0;
        $response = $consultas->generarPdfNotaVenta($idVenta);
        break;

    case "getControlCaja":
    $response = $consultas->getControlCaja($_POST);
    break;
case "abrirCaja":
    $response = $consultas->abrirCaja($_POST);
    break;
case "cerrarCaja":
    $response = $consultas->cerrarCaja($_POST);
    break;
case "getEgresos":
    $response = $consultas->getEgresos();
    break;
case "guardarEgreso":
    $response = $consultas->guardarEgreso($_POST);
    break;


case "getCuentas":
    $response = $consultas->getCuentas($_POST);
    break;
case "crearCuenta":
    $response = $consultas->crearCuenta($_POST);
    break;
case "getDetalleCuenta":
    $response = $consultas->getDetalleCuenta($_POST);
    break;
case "registrarAbonoCuenta":
    $response = $consultas->registrarAbonoCuenta($_POST);
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
