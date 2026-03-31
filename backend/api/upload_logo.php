<?php
// Upload logo for company customization
header('Content-Type: application/json');
require_once __DIR__ . '/auth.php';

requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}
$file = $_FILES['logo'];
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$allowed = ['png','jpg','jpeg','gif'];
if (!in_array(strtolower($ext), $allowed)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file type']);
    exit;
}
$uploadsDir = __DIR__ . '/../uploads';
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0777, true);
}
$filename = 'logo_' . time() . '.' . $ext;
$destPath = $uploadsDir . '/' . $filename;
if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to upload file']);
    exit;
}
// Update config file
$configFile = __DIR__ . '/../config/customization.json';
$settings = json_decode(file_get_contents($configFile), true);
$settings['logo_path'] = 'uploads/' . $filename;
file_put_contents($configFile, json_encode($settings, JSON_PRETTY_PRINT));

echo json_encode(['success' => true, 'logo_path' => $settings['logo_path']]);