<?php
// Importar productos desde un archivo CSV
header('Content-Type: application/json');
require_once __DIR__ . '/auth.php';

// Sólo los administradores pueden importar productos
requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Comprobar que se haya subido un archivo
if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No CSV file uploaded']);
    exit;
}

$file = $_FILES['csv']['tmp_name'];
$pdo = getPDO();

// Orden de las columnas esperado en el archivo CSV:
// fecha_compra;clave;nombre;descripcion;clasificacion;unidad;precio_compra;precio_minimo;
// margen_ganancia;precio_publico;precio_competencia;codigo_barras;stock;stock_minimo;stock_maximo

// Normaliza clasificación para que coincida con el ENUM de la base de datos
function normalizeClassification($value) {
    if (!$value) return 'FERRETERIA';
    $value = trim(strtoupper($value));

    $allowed = [
        'FERRETERIA',
        'ACEROS',
        'MATERIALES PARA CONSTRUCCION',
        'RENTA DE MAQUINARIA',
        'COMBUSTIBLE'
    ];

    // Permitir variaciones sencillas (sin acentos, con/ sin espacio)
    $normalized = str_replace(['Á','É','Í','Ó','Ú','Ü'], ['A','E','I','O','U','U'], $value);
    $normalized = preg_replace('/\s+/', ' ', $normalized);

    foreach ($allowed as $opt) {
        if (strcasecmp($normalized, $opt) === 0) {
            return $opt;
        }
    }

    // Si no coincide, retornar valor por defecto (o el valor original en mayúsculas para debug)
    return 'FERRETERIA';
}

// Abrir el archivo; se utiliza punto y coma como delimitador por compatibilidad con Excel en español
if (($handle = fopen($file, 'r')) === false) {
    http_response_code(400);
    echo json_encode(['error' => 'Unable to open CSV']);
    exit;
}
$rowNum = 0;
$imported = 0;
// Leer cada línea con delimitador ';' (punto y coma). Si la línea no contiene suficientes
// columnas separadas por ';', fgetcsv devolverá una fila con un único elemento; en ese caso
// puedes ajustar el delimitador en la plantilla a comas.
while (($row = fgetcsv($handle, 0, ';')) !== false) {
    $rowNum++;
    // Saltar la cabecera
    if ($rowNum === 1) continue;
    // Asegurar que al menos el nombre esté presente
    if (empty($row[2])) continue;
    // Extraer columnas según el nuevo formato
    $lastPurchase = trim($row[0]);          // fecha_compra
    $code = trim($row[1]);                  // clave
    $name = trim($row[2]);                  // nombre
    $description = trim($row[3] ?? '');     // descripcion
    $classification = normalizeClassification(trim($row[4] ?? '')); // clasificacion
    $unit = trim($row[5] ?? '');            // unidad
    $purchasePrice = trim($row[6] ?? '');   // precio_compra
    $minPrice = trim($row[7] ?? '');        // precio_minimo
    $profitMargin = trim($row[8] ?? '');    // margen_ganancia
    $publicPrice = trim($row[9] ?? '');     // precio_publico
    $competitorPrice = trim($row[10] ?? ''); // precio_competencia
    $barcode = trim($row[11] ?? '');        // codigo_barras
    $stock = trim($row[12] ?? '');          // stock
    $minStock = trim($row[13] ?? '');       // stock_minimo
    $maxStock = trim($row[14] ?? '');       // stock_maximo

    // Normalizar valores numéricos
    $purchaseVal = $purchasePrice !== '' ? (float)$purchasePrice : null;
    $publicVal = $publicPrice !== '' ? (float)$publicPrice : null;
    $marginVal = $profitMargin !== '' ? (float)$profitMargin : null;

    // Calcular precio público si no se proporciona pero existen margen y precio de compra
    if ($publicVal === null && $purchaseVal !== null && $marginVal !== null) {
        $publicVal = round($purchaseVal * (1 + $marginVal), 2);
    }
    // Calcular margen si no se proporciona pero existen precio público y precio de compra
    if ($marginVal === null && $purchaseVal !== null && $publicVal !== null) {
        if ($purchaseVal > 0) {
            $marginVal = round(($publicVal - $purchaseVal) / $purchaseVal, 2);
        }
    }
    // Calcular precio mínimo: si no se proporciona pero existen margen y precio de compra, usar 80% del margen.
    // Si no hay margen pero se proporciona precio público y compra, calcular margen primero.
    if ($minPrice === '' && $purchaseVal !== null) {
        if ($marginVal !== null) {
            $minPrice = round($purchaseVal * (1 + ($marginVal * 0.8)), 2);
        } elseif ($publicVal !== null && $purchaseVal > 0) {
            $tmpMargin = ($publicVal - $purchaseVal) / $purchaseVal;
            $minPrice = round($purchaseVal * (1 + ($tmpMargin * 0.8)), 2);
        } else {
            // fallback al precio de compra
            $minPrice = $purchasePrice;
        }
    }

    // Buscar si ya existe un producto con la misma clave o código de barras
    $existing = null;
    if ($code || $barcode) {
        $checkStmt = $pdo->prepare('SELECT id FROM products WHERE (code = :code AND :code IS NOT NULL AND code IS NOT NULL) OR (barcode = :barcode AND :barcode IS NOT NULL AND barcode IS NOT NULL) LIMIT 1');
        $checkStmt->execute([
            ':code' => $code ?: null,
            ':barcode' => $barcode ?: null
        ]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
    }

    try {
        if ($existing) {
            // Actualizar el producto existente
            $update = $pdo->prepare('UPDATE products SET name = ?, description = ?, classification = ?, unit = ?, purchase_price = ?, min_price = ?, profit_margin = ?, public_price = ?, competitor_price = ?, barcode = ?, last_purchase = ?, stock = ?, min_stock = ?, max_stock = ?, updated_at = NOW() WHERE id = ?');
            $update->execute([
                $name,
                $description ?: null,
                $classification ?: 'FERRETERIA',
                $unit ?: null,
                $purchaseVal,
                $minPrice !== '' ? (float)$minPrice : null,
                $marginVal,
                $publicVal,
                $competitorPrice !== '' ? (float)$competitorPrice : null,
                $barcode ?: null,
                $lastPurchase ?: null,
                $stock !== '' ? (int)$stock : 0,
                $minStock !== '' ? (int)$minStock : 0,
                $maxStock !== '' ? (int)$maxStock : 0,
                $existing['id']
            ]);
            $imported++;
        } else {
            // Crear nuevo producto
            $insert = $pdo->prepare('INSERT INTO products (code, name, description, classification, unit, purchase_price, min_price, profit_margin, public_price, competitor_price, barcode, last_purchase, stock, min_stock, max_stock, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())');
            $insert->execute([
                $code ?: null,
                $name,
                $description ?: null,
                $classification ?: 'FERRETERIA',
                $unit ?: null,
                $purchaseVal,
                $minPrice !== '' ? (float)$minPrice : null,
                $marginVal,
                $publicVal,
                $competitorPrice !== '' ? (float)$competitorPrice : null,
                $barcode ?: null,
                $lastPurchase ?: null,
                $stock !== '' ? (int)$stock : 0,
                $minStock !== '' ? (int)$minStock : 0,
                $maxStock !== '' ? (int)$maxStock : 0
            ]);
            $imported++;
        }
    } catch (PDOException $e) {
        // Omitir registros con errores para continuar con el resto
        continue;
    }
}
fclose($handle);
echo json_encode(['success' => true, 'imported' => $imported]);