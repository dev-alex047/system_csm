<?php
// Products API endpoint
header('Content-Type: application/json');
require_once __DIR__ . '/auth.php';

// Connect DB
$pdo = getPDO();

if(isset($_POST['action'])){
    $action = $_POST['action'];
}else{

}

switch ($action) {
    case 'getProducto':
    requireLogin();

    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

    if (!$id || $id < 1) {
        $json['status'] = 'error';
        $json['answer'] = 'Identificador de producto inválido';
        $json['code'] = 400;
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND eliminado = 0 LIMIT 1");
            $stmt->execute([$id]);
            $producto = $stmt->fetch(PDO::FETCH_ASSOC);

            $json['status'] = $producto ? 'ok' : 'error';
            $json['answer'] = $producto ? 'Producto encontrado' : 'Producto no encontrado';
            $json['results'] = $producto ?: null;
            $json['code'] = $producto ? 200 : 404;
        } catch (PDOException $e) {
            error_log('getProducto: ' . $e->getMessage());
            $json['status'] = 'error';
            $json['answer'] = 'No fue posible consultar el producto';
            $json['code'] = 500;
        }
    }
    break;

case 'getProductoPorClave':
    requireLogin();

    $clave = trim((string)($_POST['clave'] ?? ''));

    if ($clave === '' || strlen($clave) > 100) {
        $json['status'] = 'error';
        $json['answer'] = 'Ingresa una clave o código de barras válido';
        $json['code'] = 400;
    } else {
        try {
            $sql = "SELECT * FROM products
                    WHERE eliminado = 0 AND (code = ? OR barcode = ?)
                    ORDER BY CASE WHEN code = ? THEN 0 ELSE 1 END, id DESC
                    LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$clave, $clave, $clave]);
            $producto = $stmt->fetch(PDO::FETCH_ASSOC);

            $json['status'] = $producto ? 'ok' : 'error';
            $json['answer'] = $producto ? 'Producto encontrado' : 'Producto no encontrado';
            $json['results'] = $producto ?: null;
            $json['code'] = $producto ? 200 : 404;
        } catch (PDOException $e) {
            error_log('getProductoPorClave: ' . $e->getMessage());
            $json['status'] = 'error';
            $json['answer'] = 'No fue posible buscar el producto';
            $json['code'] = 500;
        }
    }
    break;

    case 'getProductos':
    requireLogin();

    $pagina = max(1, (int)($_POST['pagina'] ?? 1));
    $limite = max(1, min(96, (int)($_POST['limite'] ?? 24)));
    $buscar = trim(substr((string)($_POST['buscar'] ?? ''), 0, 150));
    $clasificacion = trim(substr((string)($_POST['clasificacion'] ?? ''), 0, 255));

    $where = "WHERE eliminado = 0";
    $params = [];

    if ($clasificacion !== '') {
        $where .= " AND classification = :clasificacion";
        $params[':clasificacion'] = $clasificacion;
    }

    if ($buscar !== '') {
        $texto = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $buscar);
        $valor = '%' . $texto . '%';

        $where .= " AND (
            name LIKE :nombre ESCAPE '!'
            OR code LIKE :codigo ESCAPE '!'
            OR barcode LIKE :barras ESCAPE '!'
            OR description LIKE :descripcion ESCAPE '!'
        )";

        $params[':nombre'] = $valor;
        $params[':codigo'] = $valor;
        $params[':barras'] = $valor;
        $params[':descripcion'] = $valor;
    }

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products $where");

        foreach ($params as $campo => $valor) {
            $stmt->bindValue($campo, $valor, PDO::PARAM_STR);
        }

        $stmt->execute();
        $total = (int)$stmt->fetchColumn();
        $paginas = max(1, (int)ceil($total / $limite));
        $pagina = min($pagina, $paginas);
        $offset = ($pagina - 1) * $limite;

        $sql = "SELECT id, code, name, description, unit, presentation,
                       brand, content, purchase_price, min_price, profit_margin,
                       public_price, competitor_price, barcode, image_path,
                       stock, min_stock, max_stock, classification,
                       supplier_id, last_purchase
                FROM products $where
                ORDER BY id DESC
                LIMIT :limite OFFSET :offset";

        $stmt = $pdo->prepare($sql);

        foreach ($params as $campo => $valor) {
            $stmt->bindValue($campo, $valor, PDO::PARAM_STR);
        }

        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $json['status'] = 'ok';
        $json['answer'] = 'Productos consultados correctamente';
        $json['results'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $json['total'] = $total;
        $json['pagina'] = $pagina;
        $json['limite'] = $limite;
        $json['paginas'] = $paginas;
        $json['code'] = 200;
    } catch (PDOException $e) {
        error_log('getProductos: ' . $e->getMessage());

        $json['status'] = 'error';
        $json['answer'] = 'No fue posible consultar los productos';
        $json['code'] = 500;
    }
    break;

    case 'getResumenInventario':
    requireRole('admin');

    $buscar = trim(substr((string)($_POST['buscar'] ?? ''), 0, 150));
    $clasificacion = trim(substr((string)($_POST['clasificacion'] ?? ''), 0, 255));
    $where = "WHERE eliminado = 0";
    $params = [];

    if ($clasificacion !== '') {
        $where .= " AND classification = :clasificacion";
        $params[':clasificacion'] = $clasificacion;
    }

    if ($buscar !== '') {
        $texto = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $buscar);
        $valor = '%' . $texto . '%';

        $where .= " AND (
            name LIKE :nombre ESCAPE '!'
            OR code LIKE :codigo ESCAPE '!'
            OR barcode LIKE :barras ESCAPE '!'
            OR description LIKE :descripcion ESCAPE '!'
        )";

        $params[':nombre'] = $valor;
        $params[':codigo'] = $valor;
        $params[':barras'] = $valor;
        $params[':descripcion'] = $valor;
    }

    try {
        $sql = "SELECT COUNT(*) AS cantidad,
                       COALESCE(SUM(COALESCE(purchase_price, 0) * COALESCE(stock, 0)), 0) AS valor
                FROM products $where";

        $stmt = $pdo->prepare($sql);

        foreach ($params as $campo => $valor) {
            $stmt->bindValue($campo, $valor, PDO::PARAM_STR);
        }

        $stmt->execute();
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        $json['status'] = 'ok';
        $json['answer'] = 'Resumen del inventario consultado correctamente';
        $json['results'] = [
            'cantidad' => (int)$resultado['cantidad'],
            'valor' => round((float)$resultado['valor'], 2),
            'clasificacion' => $clasificacion !== '' ? $clasificacion : 'Todas'
        ];
        $json['code'] = 200;
    } catch (PDOException $e) {
        error_log('getResumenInventario: ' . $e->getMessage());
        $json['status'] = 'error';
        $json['answer'] = 'No fue posible consultar el resumen del inventario';
        $json['code'] = 500;
    }
    break;

    case 'eliminarProducto':
    requireRole('admin');

    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

    if (!$id || $id < 1) {
        $json['status'] = 'error';
        $json['answer'] = 'Identificador de producto inválido';
        $json['code'] = 400;
    } else {
        try {
            $sql = "UPDATE products
                    SET eliminado = 1, updated_at = NOW()
                    WHERE id = ? AND eliminado = 0";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);

            $eliminado = $stmt->rowCount() > 0;

            $json['status'] = $eliminado ? 'ok' : 'error';
            $json['answer'] = $eliminado
                ? 'Producto eliminado correctamente'
                : 'El producto no existe o ya fue eliminado';
            $json['success'] = $eliminado;
            $json['code'] = $eliminado ? 200 : 404;
        } catch (PDOException $e) {
            error_log('eliminarProducto: ' . $e->getMessage());
            $json['status'] = 'error';
            $json['answer'] = 'No fue posible eliminar el producto';
            $json['code'] = 500;
        }
    }
    break;

    case 'setProducto':
    requireRole('admin');

    $idTexto = trim((string)($_POST['id'] ?? ''));
    $editar = $idTexto !== '';
    $id = $editar ? filter_var($idTexto, FILTER_VALIDATE_INT) : null;

    $campos = [
        'code', 'name', 'description', 'unit', 'presentation', 'brand', 'content',
        'purchase_price', 'competitor_price', 'public_price', 'min_price',
        'stock', 'barcode', 'min_stock', 'max_stock', 'last_purchase',
        'profit_margin', 'image_path', 'classification', 'supplier_id'
    ];

    $decimales = ['purchase_price', 'competitor_price', 'public_price', 'min_price', 'profit_margin'];
    $enteros = ['stock', 'min_stock', 'max_stock', 'supplier_id'];
    $datos = [];
    $error = '';

    foreach ($campos as $campo) {
        if (!array_key_exists($campo, $_POST)) {
            continue;
        }

        if (!is_string($_POST[$campo])) {
            $error = 'Los datos enviados no son válidos';
            break;
        }

        $valor = trim($_POST[$campo]);

        if ($valor === '') {
            $datos[$campo] = null;
        } elseif (in_array($campo, $decimales, true)) {
            if (!is_numeric($valor) || !is_finite((float)$valor)
                || ($campo !== 'profit_margin' && (float)$valor < 0)
                || abs((float)$valor) > ($campo === 'profit_margin' ? 9999.99 : 99999999.99)) {
                $error = 'Valor inválido para ' . $campo;
                break;
            }

            $datos[$campo] = round((float)$valor, 2);
        } elseif (in_array($campo, $enteros, true)) {
            if (!ctype_digit($valor) || (float)$valor > 2147483647
                || ($campo === 'supplier_id' && (int)$valor < 1)) {
                $error = 'Valor inválido para ' . $campo;
                break;
            }

            $datos[$campo] = (int)$valor;
        } else {
            $datos[$campo] = $valor;
        }
    }

    if ($editar && (!$id || $id < 1)) {
        $error = 'Identificador de producto inválido';
    }

    if ((!$editar || array_key_exists('name', $datos))
        && trim((string)($datos['name'] ?? '')) === '') {
        $error = 'El nombre del producto es obligatorio';
    }

    $longitudes = [
        'code' => 100, 'name' => 100, 'unit' => 20, 'presentation' => 100,
        'brand' => 50, 'content' => 100, 'barcode' => 50,
        'image_path' => 255, 'classification' => 255
    ];

    foreach ($longitudes as $campo => $maximo) {
        if (isset($datos[$campo])) {
            $longitud = function_exists('mb_strlen')
                ? mb_strlen($datos[$campo], 'UTF-8')
                : strlen($datos[$campo]);

            if ($longitud > $maximo) {
                $error = 'El campo ' . $campo . ' supera la longitud permitida';
                break;
            }
        }
    }

    if (isset($datos['last_purchase'])) {
    $fecha = DateTime::createFromFormat('!Y-m-d', $datos['last_purchase']);

    if (!$fecha || $fecha->format('Y-m-d') !== $datos['last_purchase']) {
        $error = 'La fecha de última compra no es válida';
    }
}

    if ($error !== '') {
        $json['status'] = 'error';
        $json['answer'] = $error;
        $json['code'] = 400;
    } else {
        try {
            $pdo->beginTransaction();
            $anterior = null;

            if ($editar) {
                $stmt = $pdo->prepare("SELECT id, purchase_price, public_price, profit_margin
                                       FROM products WHERE id = ? AND eliminado = 0 FOR UPDATE");
                $stmt->execute([$id]);
                $anterior = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if ($editar && !$anterior) {
                $json['status'] = 'error';
                $json['answer'] = 'El producto no existe o fue eliminado';
                $json['code'] = 404;
            } elseif ($editar && !$datos) {
                $json['status'] = 'error';
                $json['answer'] = 'No se enviaron campos para actualizar';
                $json['code'] = 400;
            } else {
                $compra = array_key_exists('purchase_price', $datos)
                    ? (float)$datos['purchase_price']
                    : (float)($anterior['purchase_price'] ?? 0);

                $venta = array_key_exists('public_price', $datos)
                    ? (float)$datos['public_price']
                    : (float)($anterior['public_price'] ?? 0);

                if (!$editar) {
                    $datos['classification'] = !empty($datos['classification']) ? $datos['classification'] : 'FERRETERIA';

                    if ($compra > 0 && $venta <= 0) {
                        $margen = isset($datos['profit_margin']) ? (float)$datos['profit_margin'] : 0.18;
                        $venta = round($compra * (1 + $margen), 2);
                        $datos['public_price'] = $venta;
                    }

                    if (!isset($datos['min_price']) && $compra > 0) {
                        $datos['min_price'] = $compra;
                    }
                }

                if ($compra > 0
                    && !isset($datos['profit_margin'])
                    && (array_key_exists('purchase_price', $datos)
                        || array_key_exists('public_price', $datos))) {
                    $datos['profit_margin'] = round(($venta - $compra) / $compra, 2);
                }

                if ($editar) {
                    $set = [];

                    foreach (array_keys($datos) as $campo) {
                        $set[] = "$campo = :$campo";
                    }

                    $sql = "UPDATE products SET " . implode(', ', $set)
                         . ", updated_at = NOW() WHERE id = :id AND eliminado = 0";

                    $datos['id'] = $id;
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($datos);
                } else {
                    $columnas = array_keys($datos);
                    $marcadores = array_map(function ($campo) {
                        return ':' . $campo;
                    }, $columnas);

                    $sql = "INSERT INTO products (" . implode(', ', $columnas) . ", eliminado)
                            VALUES (" . implode(', ', $marcadores) . ", 0)";

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($datos);
                    $id = (int)$pdo->lastInsertId();
                }

                $stmt = $pdo->prepare("SELECT purchase_price, public_price FROM products WHERE id = ?");
                $stmt->execute([$id]);
                $precios = $stmt->fetch(PDO::FETCH_ASSOC);

                $compraFinal = (float)$precios['purchase_price'];
                $ventaFinal = (float)$precios['public_price'];

                $cambioPrecio = !$editar
                    || abs($compraFinal - (float)$anterior['purchase_price']) > 0.001
                    || abs($ventaFinal - (float)$anterior['public_price']) > 0.001;

                if ($cambioPrecio) {
                    $stmt = $pdo->prepare("INSERT INTO price_histories
                        (product_id, purchase_price, public_price) VALUES (?, ?, ?)");
                    $stmt->execute([$id, $compraFinal, $ventaFinal]);
                }

                $json['status'] = 'ok';
                $json['answer'] = $editar ? 'Producto actualizado correctamente' : 'Producto registrado correctamente';
                $json['success'] = true;
                $json['id'] = $id;
                $json['code'] = 200;
            }

            if ($json['status'] === 'ok') {
                $pdo->commit();
            } else {
                $pdo->rollBack();
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('setProducto: ' . $e->getMessage());
            $json['status'] = 'error';
            $json['answer'] = 'No fue posible guardar el producto';
            $json['code'] = 500;
        }
    }
    break;

    case 'buscarProductosPublicos':
    $buscar = trim(substr((string)($_POST['buscar'] ?? ''), 0, 150));

    if (strlen($buscar) < 2) {
        $json['status'] = 'ok';
        $json['answer'] = 'Escribe al menos dos caracteres';
        $json['results'] = [];
        $json['code'] = 200;
    } else {
        try {
            $texto = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $buscar);
            $valor = '%' . $texto . '%';

            $sql = "SELECT id, code, name, barcode, public_price, image_path
                    FROM products
                    WHERE eliminado = 0
                    AND (
                        name LIKE :nombre ESCAPE '!'
                        OR code LIKE :codigo ESCAPE '!'
                        OR barcode LIKE :barras ESCAPE '!'
                    )
                    ORDER BY name ASC
                    LIMIT 10";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nombre' => $valor,
                ':codigo' => $valor,
                ':barras' => $valor
            ]);

            $json['status'] = 'ok';
            $json['answer'] = 'Búsqueda realizada correctamente';
            $json['results'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $json['code'] = 200;
        } catch (PDOException $e) {
            error_log('buscarProductosPublicos: ' . $e->getMessage());
            $json['status'] = 'error';
            $json['answer'] = 'No fue posible buscar los productos';
            $json['code'] = 500;
        }
    }
    break;

    case 'getProductoPublico':
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

    if (!$id || $id < 1) {
        $json['status'] = 'error';
        $json['answer'] = 'Identificador de producto inválido';
        $json['code'] = 400;
    } else {
        try {
            $sql = "SELECT id, code, name, description, unit, presentation,
                           brand, content, public_price, barcode, image_path,
                           stock, classification
                    FROM products
                    WHERE id = ? AND eliminado = 0 LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            $producto = $stmt->fetch(PDO::FETCH_ASSOC);

            $json['status'] = $producto ? 'ok' : 'error';
            $json['answer'] = $producto ? 'Producto encontrado' : 'Producto no encontrado';
            $json['results'] = $producto ?: null;
            $json['code'] = $producto ? 200 : 404;
        } catch (PDOException $e) {
            error_log('getProductoPublico: ' . $e->getMessage());
            $json['status'] = 'error';
            $json['answer'] = 'No fue posible consultar el producto';
            $json['code'] = 500;
        }
    }
    break;

    case 'getClasificaciones':
    requireLogin();

    try {
        $sql = "SELECT DISTINCT classification
                FROM products
                WHERE eliminado = 0
                AND classification IS NOT NULL
                AND TRIM(classification) <> ''
                ORDER BY classification ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();

        $json['status'] = 'ok';
        $json['answer'] = 'Clasificaciones consultadas correctamente';
        $json['results'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $json['code'] = 200;
    } catch (PDOException $e) {
        error_log('getClasificaciones: ' . $e->getMessage());

        $json['status'] = 'error';
        $json['answer'] = 'No fue posible consultar las clasificaciones';
        $json['code'] = 500;
    }
    break;

    case 'getProducto':
        break;

    case 'setProducto':
        break;

    case 'eliminarProducto':
        break;

    default:
        $json['status'] = 'error';
        $json['answer'] = 'Acción no reconocida';
        $json['code'] = 400;
        break;
}

print json_encode($json);

?>