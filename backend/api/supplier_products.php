<?php
// Devuelve los productos asociados a un proveedor específico
header('Content-Type: application/json');
require_once __DIR__ . '/auth.php';

$pdo = getPDO();
requireLogin();

// ID de proveedor requerido
if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing supplier id']);
    exit;
}
$supplierId = $_GET['id'];

try {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE supplier_id = ? ORDER BY id DESC');
    $stmt->execute([$supplierId]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($products);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error fetching products for supplier']);
}