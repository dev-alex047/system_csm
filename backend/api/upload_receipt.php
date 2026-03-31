<?php
// API para subir recibos o facturas (imágenes o PDFs) para compras.
// Devuelve JSON con la ruta relativa del archivo guardado.

header('Content-Type: application/json');
require_once __DIR__ . '/auth.php';

// Asegurar que el usuario haya iniciado sesión
requireLogin();

/*
 * Este script acepta archivos subidos mediante POST en un campo llamado
 * "file". Valida el tipo MIME real mediante finfo para permitir tanto
 * imágenes (JPEG, PNG, WEBP, GIF) como archivos PDF. Los archivos se
 * guardan en la carpeta uploads/receipts con un nombre único. Se devuelve
 * un JSON con la ruta relativa que puede ser almacenada en la base de datos
 * o utilizada en el frontend para mostrar el comprobante.
 */

try {
    // Validar que se haya enviado un archivo
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $err = $_FILES['file']['error'] ?? 'unknown';
        http_response_code(400);
        echo json_encode(['error' => 'Error al subir archivo: ' . $err]);
        exit;
    }

    $tmp = $_FILES['file']['tmp_name'];
    if (!is_uploaded_file($tmp)) {
        http_response_code(400);
        echo json_encode(['error' => 'Archivo temporal inválido']);
        exit;
    }

    // Validar MIME real usando finfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp);

    // Tipos permitidos: imágenes comunes y PDF
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
        'application/pdf' => 'pdf'
    ];
    if (!isset($allowed[$mime])) {
        http_response_code(400);
        echo json_encode(['error' => 'Formato de archivo no permitido']);
        exit;
    }

    $ext = $allowed[$mime];
    
    // Directorio destino
    $uploadDir = __DIR__ . '/../uploads/receipts';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Generar nombre único
    $name = 'recibo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dest = $uploadDir . '/' . $name;

    if (!move_uploaded_file($tmp, $dest)) {
        http_response_code(500);
        echo json_encode(['error' => 'No se pudo guardar el archivo']);
        exit;
    }

    // Ruta relativa para guardar en BD o mostrar en frontend
    $relativePath = 'uploads/receipts/' . $name;
    echo json_encode(['path' => $relativePath]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno al subir archivo']);
}