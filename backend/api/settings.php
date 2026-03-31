<?php
// Settings API
header('Content-Type: application/json');
require_once __DIR__ . '/auth.php';

requireLogin();

$configFile = __DIR__ . '/../config/customization.json';

if (!file_exists($configFile)) {
    // Create default config
    $default = [
        'company_name'    => 'Mi Empresa',
        'primary_color'   => '#0d6efd',
        'secondary_color' => '#6c757d',
        'logo_path'       => '',
        // Preferencias de interfaz
        'font_size'       => '1rem',
        'dark_mode_auto'  => true
    ];
    file_put_contents($configFile, json_encode($default, JSON_PRETTY_PRINT));
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Return current settings
        $settings = json_decode(file_get_contents($configFile), true);
        echo json_encode($settings);
        break;
    case 'POST':
        requireRole('admin');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON']);
            exit;
        }
        $settings = json_decode(file_get_contents($configFile), true);
        // Campos permitidos para actualizar
        foreach (['company_name', 'primary_color', 'secondary_color', 'font_size', 'dark_mode_auto'] as $field) {
            if (isset($data[$field])) {
                $settings[$field] = $data[$field];
            }
        }
        file_put_contents($configFile, json_encode($settings, JSON_PRETTY_PRINT));
        echo json_encode(['success' => true]);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}