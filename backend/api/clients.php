<?php
// Lista de clientes (nombres) para autocompletado en ventas
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/auth.php';
if (!currentUser()) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'No autorizado'], JSON_UNESCAPED_UNICODE);
  exit;
}

require_once __DIR__ . '/db.php';
$pdo = getPDO();

// Devuelve nombres distintos y no vacíos
$sql = "
  SELECT DISTINCT TRIM(client_name) AS name
  FROM sales
  WHERE client_name IS NOT NULL AND TRIM(client_name) <> ''
  ORDER BY name ASC
";

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$names = array_values(array_filter(array_map(fn($r) => $r['name'] ?? '', $rows)));

echo json_encode([
  'success' => true,
  'data' => $names
], JSON_UNESCAPED_UNICODE);
