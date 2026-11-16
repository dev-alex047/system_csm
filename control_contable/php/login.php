
<?php
date_default_timezone_set('America/Mexico_City');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/sesion.php';
require_once __DIR__ . '/conexion.php';

$action = isset($_POST['action']) ? trim((string)$_POST['action']) : '';
$json = [];

$json['status'] = 'error';
$json['answer'] = 'No fue posible procesar la solicitud.';
$json['code'] = 500;

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $json['answer'] = 'Método no permitido.';
    $json['code'] = 405;
} else {
    switch ($action) {

        case 'estado':
            $json['status'] = 'ok';
            $json['code'] = 200;

            if (csmSesionActiva()) {
                $_SESSION['csm_actividad'] = time();

                $json['answer'] = 'Sesión activa.';
                $json['results'] = [
                    'autenticado' => true,
                    'id' => (int)$_SESSION['csm_user_id'],
                    'nombre' => (string)$_SESSION['csm_nombre'],
                    'username' => (string)$_SESSION['csm_username'],
                    'role_id' => (int)$_SESSION['csm_role_id'],
                    'csrf' => (string)$_SESSION['csm_csrf']
                ];
            } else {
                unset(
                    $_SESSION['csm_user_id'],
                    $_SESSION['csm_nombre'],
                    $_SESSION['csm_username'],
                    $_SESSION['csm_role_id'],
                    $_SESSION['csm_actividad'],
                    $_SESSION['csm_csrf']
                );

                if (empty($_SESSION['csm_login_csrf'])) {
                    $_SESSION['csm_login_csrf'] = bin2hex(random_bytes(32));
                }

                $json['answer'] = 'Sin sesión activa.';
                $json['results'] = [
                    'autenticado' => false,
                    'csrf' => $_SESSION['csm_login_csrf']
                ];
            }
            break;

        case 'entrar':
            $correo = isset($_POST['correo']) && is_string($_POST['correo'])
                ? strtolower(trim($_POST['correo'])) : '';
            $clave = isset($_POST['clave']) && is_string($_POST['clave'])
                ? $_POST['clave'] : '';
            $csrf = isset($_SERVER['HTTP_X_CSRF_TOKEN'])
                ? (string)$_SERVER['HTTP_X_CSRF_TOKEN'] : '';

            if (empty($_SESSION['csm_login_csrf']) ||
                !hash_equals((string)$_SESSION['csm_login_csrf'], $csrf)) {
                $json['answer'] = 'Actualiza la página e inténtalo nuevamente.';
                $json['code'] = 403;
                break;
            }

            if (strlen($correo) > 190 || !filter_var($correo, FILTER_VALIDATE_EMAIL) ||
                $clave === '' || strlen($clave) > 72) {
                $json['status'] = 'warning';
                $json['answer'] = 'Correo o contraseña incorrectos.';
                $json['code'] = 401;
                break;
            }

            try {
                $db = (new conexion())->getConnection();
                if (!$db || $db->connect_error) {
                    throw new RuntimeException('No hay conexión MySQL.');
                }

                $db->set_charset('utf8mb4');

                $stmt = $db->prepare(
                    'SELECT id, username, name, password_hash, role_id,
                            is_active, failed_attempts, locked_until
                     FROM users
                     WHERE email = ?
                     LIMIT 1'
                );

                if (!$stmt) throw new RuntimeException($db->error);

                $stmt->bind_param('s', $correo);
                if (!$stmt->execute()) throw new RuntimeException($stmt->error);

                $usuario = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$usuario) {
                    // Verificación ficticia para reducir diferencias de tiempo.
                    password_verify($clave, '$2y$10$RdcJhJtRiL1rPMhYTm31w.LFrDZPON/QHSNPsXMfJuPw71IJudGXm');
                    $json['status'] = 'warning';
                    $json['answer'] = 'Correo o contraseña incorrectos.';
                    $json['code'] = 401;
                    break;
                }

                if ((int)$usuario['is_active'] !== 1) {
                    $json['status'] = 'warning';
                    $json['answer'] = 'Correo o contraseña incorrectos.';
                    $json['code'] = 401;
                    break;
                }

                if (!empty($usuario['locked_until']) &&
                    strtotime($usuario['locked_until']) > time()) {
                    $json['status'] = 'warning';
                    $json['answer'] = 'Demasiados intentos. Intenta nuevamente en unos minutos.';
                    $json['code'] = 429;
                    break;
                }

                $almacenada = (string)$usuario['password_hash'];
                $infoHash = password_get_info($almacenada);
                $esHash = (int)$infoHash['algo'] !== 0;

                // Contraseña moderna: password_verify().
                // Contraseña antigua en texto plano: comparación temporal.
                $valida = $almacenada !== '' && (
                    $esHash
                        ? password_verify($clave, $almacenada)
                        : hash_equals($almacenada, $clave)
                );

                $id = (int)$usuario['id'];

                if (!$valida) {
                    $intentos = (int)$usuario['failed_attempts'] + 1;

                    if (!empty($usuario['locked_until']) &&
                        strtotime($usuario['locked_until']) <= time()) {
                        $intentos = 1;
                    }

                    $hasta = $intentos >= 5
                        ? date('Y-m-d H:i:s', time() + 900) : null;

                    $stmt = $db->prepare(
                        'UPDATE users
                         SET failed_attempts = ?, locked_until = ?
                         WHERE id = ?'
                    );
                    if (!$stmt) throw new RuntimeException($db->error);

                    $stmt->bind_param('isi', $intentos, $hasta, $id);
                    if (!$stmt->execute()) throw new RuntimeException($stmt->error);
                    $stmt->close();

                    $json['status'] = 'warning';
                    $json['answer'] = 'Correo o contraseña incorrectos.';
                    $json['code'] = 401;
                    break;
                }

                $stmt = $db->prepare(
                    'UPDATE users
                     SET failed_attempts = 0,
                         locked_until = NULL,
                         last_login_at = NOW()
                     WHERE id = ?'
                );
                if (!$stmt) throw new RuntimeException($db->error);

                $stmt->bind_param('i', $id);
                if (!$stmt->execute()) throw new RuntimeException($stmt->error);
                $stmt->close();

                if (!session_regenerate_id(true)) {
                    throw new RuntimeException('No se pudo crear la sesión.');
                }

                $_SESSION['csm_user_id'] = $id;
                $_SESSION['csm_username'] = (string)$usuario['username'];
                $_SESSION['csm_nombre'] = (string)$usuario['name'];
                $_SESSION['csm_role_id'] = (int)$usuario['role_id'];
                $_SESSION['csm_actividad'] = time();
                $_SESSION['csm_csrf'] = bin2hex(random_bytes(32));
                unset($_SESSION['csm_login_csrf']);

                $json['status'] = 'ok';
                $json['answer'] = 'Bienvenido a SM Corp.';
                $json['code'] = 200;
                $json['results'] = [
                    'id' => $id,
                    'nombre' => $usuario['name'],
                    'username' => $usuario['username'],
                    'role_id' => (int)$usuario['role_id']
                ];

            } catch (Throwable $e) {
                error_log('SM Corp login: ' . $e->getMessage());
                $json['status'] = 'error';
                $json['answer'] = 'No fue posible iniciar sesión.';
                $json['code'] = 500;
            }
            break;

        case 'salir':
            if (!csmSesionActiva()) {
                $json['answer'] = 'Sesión no activa.';
                $json['code'] = 401;
            } elseif (!csmTokenValido()) {
                $json['answer'] = 'Solicitud no autorizada.';
                $json['code'] = 403;
            } else {
                csmCerrarSesion();
                $json['status'] = 'ok';
                $json['answer'] = 'Sesión cerrada.';
                $json['code'] = 200;
            }
            break;

        default:
            $json['status'] = 'warning';
            $json['answer'] = 'Acción no válida.';
            $json['code'] = 400;
            break;
    }
}

http_response_code((int)$json['code']);
print json_encode($json, JSON_UNESCAPED_UNICODE);
