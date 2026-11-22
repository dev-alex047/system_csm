
<?php
date_default_timezone_set('America/Mexico_City');
//define("DEBUG_NOTA_PDF", true);
define("DEBUG_NOTA_PDF", false);

header("Content-Type: application/json; charset=utf-8");

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/sesion.php';
csmExigirSesion();


require_once __DIR__ . "/conexion.php";
require_once __DIR__ . "/consultas.php";
require_once __DIR__ . "/utils.php";
require_once __DIR__ . "/notaVenta.php";
require_once __DIR__ . "/ticketVenta.php";

$action = isset($_POST["action"]) ? trim((string)$_POST["action"]) : "";
$consultas = new consultas();
$utils = new utils();
$response = [];

switch ($action) {
    case "getResumenInicial":
        $response = $consultas->getResumenInicial($_POST);
        break;
        
    case "getCajasVenta":
        $response = $consultas->getCajasVenta();
        break;

    case "buscarProductosVenta":
        $response = $consultas->buscarProductosVenta($_POST);
        break;

    case "getUnidadProducto":
        $response = $consultas->getUnidadProducto($_POST);
        break;
        
    case "getVentas":
        $response = $consultas->getVentas($_POST);
        break;
 
    case "guardarVentaNota":
        $response = $consultas->guardarVentaNota($_POST);
        break;
        
    case "guardarVentaRapida":
        $response = $consultas->guardarVentaRapida($_POST);
        break;

    case "getCatalogosCosteo":
        $response = $consultas->getCatalogosCosteo($_POST);
        break;

    case "guardarCatalogoCosteo":
        $response = $consultas->guardarCatalogoCosteo($_POST);
        break;

    case "cambiarEstadoCatalogoCosteo":
        $response = $consultas->cambiarEstadoCatalogoCosteo($_POST);
        break;

    case "cotizarMaterial":
        $response = $consultas->cotizarMaterial($_POST, false);
        break;

    case "guardarCotizacionCosteo":
        $response = $consultas->cotizarMaterial($_POST, true);
        break;
        
    case "guardarCotizacionMaterial":
        $response = $consultas->cotizarMaterial($_POST, true);
        break;

    case "generarPdfNotaVenta":
        $idVenta = isset($_POST["id"]) ? (int)$_POST["id"] : 0;
        $response = $consultas->generarPdfNotaVenta($idVenta);
        break;
        
    case "generarDocumentosNotaVenta":
        $response = $consultas->generarDocumentosNotaVenta($_POST);
        break;
        
    case "generarTicketVenta":
        $response = $consultas->generarTicketVenta($_POST);
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
    
    case "getAbonoGlobalCliente":
        $response = $consultas->getAbonoGlobalCliente($_POST);
        break;

    case "registrarAbonoGlobalCliente":
        $response = $consultas->registrarAbonoGlobalCliente($_POST);
        break;
        
    case "getPersonasCuentas":
        $response = $consultas->getPersonasCuentas($_POST);
        break;
    
    case "getAbonosCuentas":
        $response = $consultas->getAbonosCuentas($_POST);
        break;

    case "generarConcentradoCxc":
        $response = $consultas->generarConcentradoCxc($_POST);
        break;

    case "getClientes":
        $response = $consultas->getClientes($_POST);
        break;

    case "guardarCliente":
        $response = $consultas->guardarCliente($_POST);
        break;

    case "eliminarCliente":
        $response = $consultas->eliminarCliente($_POST);
        break;

    case "getCatalogosClientes":
        $response = $consultas->getCatalogosClientes();
        break;

    case "guardarCatalogoCliente":
        $response = $consultas->guardarCatalogoCliente($_POST);
        break;

        case "getTasasFinanciamiento":
        $response = $consultas->getTasasFinanciamiento($_POST);
        break;

    case "guardarTasaFinanciamiento":
        $response = $consultas->guardarTasaFinanciamiento($_POST);
        break;

    case "cambiarEstadoTasaFinanciamiento":
        $response = $consultas->cambiarEstadoTasaFinanciamiento($_POST);
        break;

    case "eliminarTasaFinanciamiento":
        $response = $consultas->eliminarTasaFinanciamiento($_POST);
        break;

    case "getPaisesEstados":
        $response = $utils->getPaisesEstados($_POST);
        break;
        
    case "getColaboradores":
        $response = $consultas->getColaboradores($_POST);
        break;
        
    case "guardarColaborador":
        $response = $consultas->guardarColaborador($_POST);
        break;
        
    case "eliminarColaborador":
        $response = $consultas->eliminarColaborador($_POST);
        break;

    case "getPreparacionValesMaterial":
        $response = $consultas->getPreparacionValesMaterial($_POST);
        break;
        
    case "guardarValeMaterial":
        $response = $consultas->guardarValeMaterial($_POST);
        break;
        
    case "confirmarValeMaterial":
        $response = $consultas->confirmarValeMaterial($_POST);
        break;
        
    case "anularValeMaterial":
        $response = $consultas->anularValeMaterial($_POST);
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
