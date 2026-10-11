<?php

date_default_timezone_set('America/Mexico_City');
header("Content-Type: application/json; charset=utf-8");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/conexion.php";
require_once __DIR__ . "/consultas.php";
require_once __DIR__ . "/PDFEvaluacion.php";

if (isset($_POST["session"])) {
    $session = $_POST["session"];
} else {
    $session = 1;
}

if (isset($_POST["action"])) {
    $action = $_POST["action"];
} else {
    $action = "";
}

$consultas = new consultas($session);

$response = [];

switch ($action) {

    case "getAlumnos":
        $response = $consultas->getAlumnos();
    break;

    case "guardarAlumno":
        $response = $consultas->guardarAlumno($_POST);
    break;

    case "eliminarAlumno":
        $idAlumno = isset($_POST["id"])
            ? (int)$_POST["id"]
            : 0;

        $response = $consultas->eliminarAlumno($idAlumno);
    break;

    case "getMaestros":
        $response = $consultas->getMaestros();
    break;
    
    case "guardarMaestro":
        $response = $consultas->guardarMaestro($_POST);
    break;
    
    case "eliminarMaestro":
        $idMaestro = isset($_POST["id"]) ? (int)$_POST["id"] : 0;
        $response = $consultas->eliminarMaestro($idMaestro);
    break;

    case "getAsignaturas":
        $response = $consultas->getAsignaturas();
    break;
    
    case "guardarAsignatura":
        $response = $consultas->guardarAsignatura($_POST);
    break;
    
    case "eliminarAsignatura":
        $idAsignatura = isset($_POST["id"]) ? (int)$_POST["id"] : 0;
        $response = $consultas->eliminarAsignatura($idAsignatura);
    break;

    case "getActividadesEvaluacion":
        $response = $consultas->getActividadesEvaluacion($_POST);
    break;
    
    case "guardarActividadesEvaluacion":
        $response = $consultas->guardarActividadesEvaluacion($_POST);
    break;
    
    case "guardarCalificacionesActividades":
        $response = $consultas->guardarCalificacionesActividades($_POST);
    break;

    case "getCalificaciones":
        $response = $consultas->getCalificaciones($_POST);
    break;
        
    case "guardarCalificaciones":
        $response = $consultas->guardarCalificaciones($_POST);
    break;

    case "getAprovechamientoEscolar":
        $response = $consultas->getAprovechamientoEscolar($_POST);
    break;

    case "generarPdfEvaluacion":
        $datosPdf = isset($_POST["data"]) ? json_decode($_POST["data"], true) : null;
        
        if (!is_array($datosPdf)) {
            $response["status"] = "warning";
            $response["answer"] = "Los datos enviados para generar el PDF no son válidos.";
            $response["code"] = 400;
        } else {
            $pdfEvaluacion = new PDFEvaluacion();
            $response = $pdfEvaluacion->generarPDFEvaluacion($datosPdf);
        }
    break;

    default:
        if ($action === "") {
            $response["status"] = "ok";
            $response["answer"] = "El endpoint de gestión escolar está activo.";
            $response["code"] = 200;
        } else {
            $response["status"] = "warning";
            $response["answer"] = "La acción solicitada no es válida.";
            $response["action"] = $action;
            $response["code"] = 400;
        }
    break;
}

print json_encode($response);

?>