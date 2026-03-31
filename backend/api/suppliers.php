<?php
// Suppliers API endpoint
header('Content-Type: application/json');
require_once __DIR__ . '/auth.php';

$pdo = getPDO();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        requireLogin();
        if (isset($_GET['id'])) {
            $id = $_GET['id'];
            // Si se solicita la lista de productos de un proveedor
            if (isset($_GET['products']) && $_GET['products']) {
                // Devolver productos donde supplier_id = id
                $stmt = $pdo->prepare('SELECT * FROM products WHERE supplier_id = ? ORDER BY id DESC');
                $stmt->execute([$id]);
                $prods = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($prods);
            } else {
                $stmt = $pdo->prepare('SELECT * FROM suppliers WHERE id = ?');
                $stmt->execute([$id]);
                $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$supplier) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Supplier not found']);
                } else {
                    echo json_encode($supplier);
                }
            }
        } else {
            $stmt = $pdo->query('SELECT * FROM suppliers ORDER BY id DESC');
            $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($suppliers);
        }
        break;
    case 'POST':
        requireRole('admin');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON']);
            exit;
        }
        // Campos permitidos: se renombra phone a phone_number y se agrega mobile_number
        // Se agregan campos fiscales y bancarios
        $fields = ['name', 'contact_name', 'phone_number', 'mobile_number', 'email', 'address', 'company',
                   'rfc', 'legal_name', 'postal_code', 'bank', 'interbank_key', 'branch', 'account_number', 'account_holder', 'categories'];
        $params = [];
        foreach ($fields as $field) {
            $params[$field] = $data[$field] ?? null;
        }
        try {
            $stmt = $pdo->prepare('INSERT INTO suppliers (name, contact_name, phone_number, mobile_number, email, address, company, rfc, legal_name, postal_code, bank, interbank_key, branch, account_number, account_holder, categories, created_at, updated_at) VALUES (:name,:contact_name,:phone_number,:mobile_number,:email,:address,:company,:rfc,:legal_name,:postal_code,:bank,:interbank_key,:branch,:account_number,:account_holder,:categories,NOW(),NOW())');
            $stmt->execute($params);
            $id = $pdo->lastInsertId();
            echo json_encode(['success' => true, 'id' => $id]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error inserting supplier']);
        }
        break;
    case 'PUT':
        requireRole('admin');
        if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing supplier id']);
            exit;
        }
        $id = $_GET['id'];
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON']);
            exit;
        }
        $fields = ['name', 'contact_name', 'phone_number', 'mobile_number', 'email', 'address', 'company',
                   'rfc', 'legal_name', 'postal_code', 'bank', 'interbank_key', 'branch', 'account_number', 'account_holder', 'categories'];
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
        $sql = 'UPDATE suppliers SET ' . implode(',', $set) . ', updated_at = NOW() WHERE id = :id';
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error updating supplier']);
        }
        break;
    case 'DELETE':
        requireRole('admin');
        if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing supplier id']);
            exit;
        }
        $id = $_GET['id'];
        try {
            $stmt = $pdo->prepare('DELETE FROM suppliers WHERE id = ?');
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error deleting supplier']);
        }
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}