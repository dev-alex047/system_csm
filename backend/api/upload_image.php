<?php
header('Content-Type: application/json');
require_once __DIR__ . '/auth.php';

requireLogin();

/**
 * Upload de imagen robusto:
 * - Acepta input name: image o file
 * - Valida MIME real con finfo
 * - Genera extensión por MIME (no confía en el nombre)
 * - Guarda en backend/uploads/products
 * - Devuelve: { path: "uploads/products/xxxx.jpg" }
 */

try {
    // 1) Recibir archivo (image o file)
    $key = null;
    if (isset($_FILES['image'])) $key = 'image';
    if (isset($_FILES['file']))  $key = $key ?: 'file';

    if (!$key) {
        http_response_code(400);
        echo json_encode(['error' => 'No se recibió archivo (image/file)']);
        exit;
    }

    if (!isset($_FILES[$key]) || $_FILES[$key]['error'] !== UPLOAD_ERR_OK) {
        $err = $_FILES[$key]['error'] ?? 'unknown';
        http_response_code(400);
        echo json_encode(['error' => 'Error al subir archivo: ' . $err]);
        exit;
    }

    $tmp = $_FILES[$key]['tmp_name'];
    if (!is_uploaded_file($tmp)) {
        http_response_code(400);
        echo json_encode(['error' => 'Archivo temporal inválido']);
        exit;
    }

    // 2) Validar MIME real con finfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp);

    // Permitir formatos típicos de navegador
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif'
    ];

    if (!isset($allowed[$mime])) {
        http_response_code(400);
        echo json_encode(['error' => 'Formato de imagen no permitido']);
        exit;
    }

    $ext = $allowed[$mime];

    // 3) Directorio destino
    $uploadDir = __DIR__ . '/../uploads/products';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // 4) Nombre único
    $name = 'prod_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dest = $uploadDir . '/' . $name;

    if (!move_uploaded_file($tmp, $dest)) {
        http_response_code(500);
        echo json_encode(['error' => 'No se pudo guardar la imagen']);
        exit;
    }

    // 5) Ruta relativa (para guardar en BD y mostrar en frontend)
    $relativePath = 'uploads/products/' . $name;

    echo json_encode(['path' => $relativePath]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno al subir imagen']);
}
