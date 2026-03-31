<?php
// Price history report API
header('Content-Type: application/json');
require_once __DIR__ . '/auth.php';

requireLogin();

$pdo = getPDO();
$productId = $_GET['product_id'] ?? null;
if ($productId) {
    $stmt = $pdo->prepare('SELECT * FROM price_histories WHERE product_id = ? ORDER BY created_at DESC');
    $stmt->execute([$productId]);
} else {
    $stmt = $pdo->query('SELECT * FROM price_histories ORDER BY created_at DESC');
}
$histories = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($histories);