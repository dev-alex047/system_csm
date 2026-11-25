
<?php
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');
ini_set('session.gc_maxlifetime', '1800');
define('CSM_CORP_ID', '1');


if (session_status() !== PHP_SESSION_ACTIVE) {
    $base = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
    $ruta = ($base === '' ? '' : $base) . '/';
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

    session_name('CSMSESSID');
    session_set_cookie_params(0, $ruta, '', $https, true);
    session_start();
}

/*function csmSesionActiva() {
    return !empty($_SESSION['csm_user_id']) &&
        !empty($_SESSION['csm_actividad']) &&
        time() - (int)$_SESSION['csm_actividad'] <= 1800;
}*/

function csmSesionActiva(): bool {
    $activa = !empty($_SESSION['csm_user_id']) &&
        !empty($_SESSION['csm_actividad']) &&
        time() - (int)$_SESSION['csm_actividad'] <= 1800;

    if ($activa) {
        $_SESSION['csm_corp_id'] = CSM_CORP_ID;
    }

    return $activa;
}

function csmTokenValido() {
    $token = isset($_SERVER['HTTP_X_CSRF_TOKEN'])
        ? (string)$_SERVER['HTTP_X_CSRF_TOKEN'] : '';

    return !empty($_SESSION['csm_csrf']) &&
        hash_equals((string)$_SESSION['csm_csrf'], $token);
}

function csmCerrarSesion() {
    $_SESSION = [];
    $p = session_get_cookie_params();

    setcookie(session_name(), '', time() - 3600,
        $p['path'], $p['domain'], $p['secure'], $p['httponly']);

    session_destroy();
}

function csmExigirSesion() {
    $codigo = 200;

    if (!csmSesionActiva()) {
        $codigo = 401;
    } elseif (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csmTokenValido()) {
        $codigo = 403;
    }

    if ($codigo !== 200) {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');

        $json = [];
        $json['status'] = 'error';
        $json['answer'] = $codigo === 401 ? 'Sesión expirada. Inicia sesión nuevamente.' : 'Solicitud no autorizada.';
        $json['code'] = $codigo;

        echo json_encode($json, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $_SESSION['csm_actividad'] = time();
}
