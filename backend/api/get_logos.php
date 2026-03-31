<?php
// Obtener lista de logos/marcas desde la carpeta uploads/logos_marcas
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$logosDir = __DIR__ . '/../uploads/logos_marcas';

// Crear carpeta si no existe
if (!is_dir($logosDir)) {
    @mkdir($logosDir, 0777, true);
}

$logos = [];

// Verificar si la carpeta existe y es legible
if (!is_readable($logosDir)) {
    echo json_encode(['logos' => [], 'error' => 'No se puede leer la carpeta']);
    exit;
}

$files = @scandir($logosDir);

if (!$files) {
    echo json_encode(['logos' => [], 'error' => 'No se pudo escanear la carpeta']);
    exit;
}

foreach ($files as $file) {
    // Ignorar . y ..
    if (in_array($file, ['.', '..'])) continue;
    
    // Solo archivos de imagen
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) continue;
    
    // Obtener el nombre sin extensión
    $name = pathinfo($file, PATHINFO_FILENAME);
    
    // Usar ruta relativa desde raiz del sitio
    $logos[] = [
        'name' => ucfirst(str_replace(['-', '_'], ' ', $name)),
        'file' => $file,
        'path' => 'backend/uploads/logos_marcas/' . $file
    ];
}

// Si no hay logos, devolver array vacío pero sin error
if (empty($logos)) {
    echo json_encode(['logos' => [], 'count' => 0]);
    exit;
}

// Ordenar alfabéticamente
usort($logos, function($a, $b) {
    return strcmp($a['name'], $b['name']);
});

echo json_encode(['logos' => $logos, 'count' => count($logos)]);
