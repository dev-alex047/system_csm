<?php
// Products API endpoint
header('Content-Type: application/json');
require_once __DIR__ . '/auth.php';

// Connect DB
$pdo = getPDO();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // List products or get single product
        // NOTA: price_consultation.html necesita acceso sin autenticación
        // Solo requiere login si es desde una acción administrativa
        if (!isset($_GET['public'])) {
            requireLogin();
        }
        
        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
            $stmt->execute([$_GET['id']]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$product) {
                http_response_code(404);
                echo json_encode(['error' => 'Product not found']);
            } else {
                echo json_encode($product);
            }
        } else {
            // Soporta filtro opcional por clasificación
            if (isset($_GET['classification']) && $_GET['classification']) {
                $class = $_GET['classification'];
                $stmt = $pdo->prepare('SELECT * FROM products WHERE classification = ? ORDER BY id DESC');
                $stmt->execute([$class]);
            } else {
                $stmt = $pdo->query('SELECT * FROM products ORDER BY id DESC');
            }
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($products);
        }
        break;
    case 'POST':
        // Create new product (admin only)
        requireRole('admin');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON']);
            exit;
        }
        // Define allowed fields for creation. Se incluyen los nuevos campos "code", "unit", y se elimina suggested_price.
        $fields = [
            'code', 'name', 'description', 'unit', 'presentation', 'brand', 'content',
            'purchase_price', 'competitor_price', 'public_price', 'min_price',
            'stock', 'barcode', 'min_stock', 'max_stock',
            'last_purchase', 'profit_margin', 'image_path',
            // Nuevos campos
            'classification', 'supplier_id'
        ];
        $values = [];
        foreach ($fields as $field) {
            $values[$field] = $data[$field] ?? null;
        }

        // Si no se envía clasificación, usar valor predeterminado
        if (empty($values['classification'])) {
            $values['classification'] = 'FERRETERIA';
        }
        // Si se proporciona margen y no se define precio público, calcular el precio público.
        // Si solo se proporciona precio público, calcular el margen.
        if ($values['purchase_price'] && !$values['public_price']) {
            // Cuando hay margen se usa para calcular; si no, usar 18% por defecto
            if ($values['profit_margin']) {
                $values['public_price'] = round(floatval($values['purchase_price']) * (1 + floatval($values['profit_margin'])), 2);
            } else {
                $values['public_price'] = round(floatval($values['purchase_price']) * 1.18, 2);
            }
        } elseif ($values['purchase_price'] && $values['public_price'] && !$values['profit_margin']) {
            // Calcular margen a partir del precio público
            $purchase = floatval($values['purchase_price']);
            $public = floatval($values['public_price']);
            if ($purchase > 0) {
                $values['profit_margin'] = round(($public - $purchase) / $purchase, 2);
            }
        }
        // Establecer el precio mínimo si no se envió; por defecto igual al precio de compra
        if (!$values['min_price'] && $values['purchase_price']) {
            $values['min_price'] = $values['purchase_price'];
        }
        // Preparar sentencia de inserción con los nuevos campos
        $sql = 'INSERT INTO products (code, name, description, unit, presentation, brand, content, purchase_price, competitor_price, public_price, min_price, stock, barcode, min_stock, max_stock, last_purchase, profit_margin, image_path, classification, supplier_id, created_at, updated_at) VALUES (:code,:name,:description,:unit,:presentation,:brand,:content,:purchase_price,:competitor_price,:public_price,:min_price,:stock,:barcode,:min_stock,:max_stock,:last_purchase,:profit_margin,:image_path,:classification,:supplier_id,NOW(),NOW())';
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            $id = $pdo->lastInsertId();
            // Registrar historial de precios
            if ($values['purchase_price'] !== null || $values['public_price'] !== null) {
                $ph = $pdo->prepare('INSERT INTO price_histories (product_id, purchase_price, public_price) VALUES (?,?,?)');
                $ph->execute([$id, $values['purchase_price'], $values['public_price']]);
            }
            echo json_encode(['success' => true, 'id' => $id]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al insertar el producto']);
        }
        break;
    case 'PUT':
        // Update product (admin only)
        requireRole('admin');
        if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing product id']);
            exit;
        }
        $id = $_GET['id'];
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON']);
            exit;
        }
        // Build dynamic SET clause
        $fields = [
            'code', 'name', 'description', 'unit', 'presentation', 'brand', 'content',
            'purchase_price', 'competitor_price', 'public_price', 'min_price',
            'stock', 'barcode', 'min_stock', 'max_stock',
            'last_purchase', 'profit_margin', 'image_path',
            // Nuevos campos
            'classification', 'supplier_id'
        ];
        $set = [];
        $params = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $set[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        if (empty($set)) {
            echo json_encode(['success' => true]);
            exit;
        }
        $params[':id'] = $id;
        // Si se envía purchase_price y public_price pero no profit_margin, calcular margen en servidor
        if (isset($data['purchase_price']) && isset($data['public_price']) && !isset($data['profit_margin'])) {
            $purchase = floatval($data['purchase_price']);
            $public = floatval($data['public_price']);
            if ($purchase > 0) {
                $params[':profit_margin'] = round(($public - $purchase) / $purchase, 2);
                $set[] = 'profit_margin = :profit_margin';
            }
        }
        $sql = 'UPDATE products SET ' . implode(',', $set) . ', updated_at = NOW() WHERE id = :id';
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            // Si cambian purchase_price o public_price, registrar historial de precios
            if ((isset($data['purchase_price']) && $data['purchase_price'] !== null) || (isset($data['public_price']) && $data['public_price'] !== null)) {
                $ph = $pdo->prepare('INSERT INTO price_histories (product_id, purchase_price, public_price) VALUES (?,?,?)');
                $purch = $data['purchase_price'] ?? null;
                $pub = $data['public_price'] ?? null;
                $ph->execute([$id, $purch, $pub]);
            }
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error updating product']);
        }
        break;
    case 'DELETE':
        // Delete product (admin only)
        requireRole('admin');
        if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing product id']);
            exit;
        }
        $id = $_GET['id'];
        try {
            $pdo->beginTransaction();

            // Si existen filas en tablas que referencian el producto, intentamos limpiarlas.
            // Si algunas tablas no existen, ignoramos los errores.
            try {
                $pdo->prepare('DELETE FROM refund_items WHERE product_id = ?')->execute([$id]);
            } catch (PDOException $ignore) {}
            try {
                $pdo->prepare('DELETE FROM sale_items WHERE product_id = ?')->execute([$id]);
            } catch (PDOException $ignore) {}
            try {
                $pdo->prepare('DELETE FROM purchase_items WHERE product_id = ?')->execute([$id]);
            } catch (PDOException $ignore) {}

            // Forzar eliminación ignorando restricciones de FK para evitar bloqueos
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
            $stmt->execute([$id]);
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Error deleting product: ' . $e->getMessage()]);
        }
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}