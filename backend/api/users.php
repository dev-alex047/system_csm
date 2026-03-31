<?php
// Users API endpoint
header('Content-Type: application/json');
require_once __DIR__ . '/auth.php';

$pdo = getPDO();
$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'GET':
        // List users (admin only)
        requireRole('admin');
        // Devolver id, username y nombre de rol
        $stmt = $pdo->query('SELECT users.id, users.username, roles.name AS role_name FROM users JOIN roles ON users.role_id = roles.id ORDER BY users.id ASC');
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;
    case 'POST':
        // Create new user (admin only)
        requireRole('admin');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['username']) || !isset($data['password']) || !isset($data['role_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON']);
            exit;
        }
        $username = $data['username'];
        $password = $data['password'];
        $role_id = intval($data['role_id']);
        // Verificar que el rol exista
        $roleCheck = $pdo->prepare('SELECT id FROM roles WHERE id = ?');
        $roleCheck->execute([$role_id]);
        if (!$roleCheck->fetchColumn()) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid role']);
            exit;
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        // Preparar inserción con foto opcional
        $photoPath = $data['photo_path'] ?? null;
        try {
            $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role_id, photo_path) VALUES (?,?,?,?)');
            $stmt->execute([$username, $hash, $role_id, $photoPath]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error creating user']);
        }
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}