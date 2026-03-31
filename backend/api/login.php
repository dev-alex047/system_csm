<?php
// Handle user login
header('Content-Type: application/json');
require_once __DIR__ . '/auth.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Parse JSON body
$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['username']) || !isset($data['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$username = $data['username'];
$password = $data['password'];

if (loginUser($username, $password)) {
    echo json_encode(['success' => true, 'user' => currentUser()]);
} else {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
}