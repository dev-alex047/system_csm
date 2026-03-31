<?php
// Roles API endpoint
header('Content-Type: application/json');
require_once __DIR__ . '/auth.php';

// Only admin can list roles
requireRole('ADMIN');
$pdo = getPDO();
$stmt = $pdo->query('SELECT id, name FROM roles ORDER BY id');
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));