<?php
// CRUD simple para cajas/cuentas (efectivo y bancos)
// - GET  : lista (opcional ?kind=cash|bank&is_active=1)
// - POST : {action:create|update|toggle, ...}

header('Content-Type: application/json');

require_once __DIR__ . '/auth.php';
requireRole('admin');

require_once __DIR__ . '/db.php';
$pdo = getPDO();

function jsonIn(): array {
  $raw = file_get_contents('php://input');
  if (!$raw) return [];
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

// ---- GET ----
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $where = [];
  $params = [];

  // Aceptamos 'kind' (cash|bank) o 'type' (CASH|BANK) o 'category' (CAJA|BANCO) para compatibilidad
  $kind = $_GET['kind'] ?? $_GET['type'] ?? $_GET['category'] ?? '';
  if (!empty($kind)) {
    $kind_upper = strtoupper(trim($kind));
    // Normalizar a TYPE enum ('CASH' o 'BANK')
    if (in_array($kind_upper, ['CASH', 'CAJA', 'EFECTIVO'], true)) $kind_upper = 'CASH';
    elseif (in_array($kind_upper, ['BANK', 'BANCO'], true)) $kind_upper = 'BANK';
    else $kind_upper = '';
    
    if ($kind_upper) {
      $where[] = 'type = ?';
      $params[] = $kind_upper;
    }
  }

  if (isset($_GET['is_active']) && $_GET['is_active'] !== '') {
    $where[] = 'is_active = ?';
    $params[] = (int)($_GET['is_active'] == '1');
  }

  $sql = 'SELECT id, name, type, is_active, saldo_inicial AS initial_balance, 
                  saldo_inicial AS current_balance, kind, category,
                  created_at, updated_at FROM cash_registers';
  if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
  $sql .= ' ORDER BY type ASC, name ASC, id ASC';

  $st = $pdo->prepare($sql);
  if (!$st->execute($params)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error ejecutando query']);
    exit;
  }
  $results = $st->fetchAll(PDO::FETCH_ASSOC);
  
  // Mapear para compatibilidad: agregar 'kind' y 'category' como alias si no existen
  foreach ($results as &$r) {
    if (!isset($r['kind'])) $r['kind'] = strtolower($r['type']);
    if (!isset($r['category'])) $r['category'] = ($r['type'] === 'BANK') ? 'BANCO' : 'CAJA';
    if (!isset($r['current_balance'])) $r['current_balance'] = $r['initial_balance'] ?? 0;
  }
  
  // Devolver solo el array de datos para compatibilidad con frontend
  echo json_encode($results);
  exit;
}

// ---- POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $in = jsonIn();
  $action = strtolower(trim($in['action'] ?? ''));

  if ($action === 'create') {
    $name = trim((string)($in['name'] ?? ''));
    $kind = strtolower(trim((string)($in['kind'] ?? $in['type'] ?? $in['category'] ?? 'cash')));
    $initial = (float)($in['initial_balance'] ?? $in['saldo_inicial'] ?? 0);
    
    if ($name === '') {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'Nombre requerido']);
      exit;
    }
    
    // Normalizar tipo
    $type_enum = 'CASH';
    if (in_array($kind, ['bank', 'banco'], true)) $type_enum = 'BANK';
    
    $st = $pdo->prepare('INSERT INTO cash_registers (name, type, saldo_inicial, is_active, created_at, updated_at) 
                         VALUES (?,?,?,1,NOW(),NOW())');
    $st->execute([$name, $type_enum, $initial]);
    echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
    exit;
  }

  if ($action === 'update') {
    $id = (int)($in['id'] ?? 0);
    $name = trim((string)($in['name'] ?? ''));
    $kind = trim((string)($in['kind'] ?? $in['type'] ?? $in['category'] ?? ''));
    $initial = isset($in['initial_balance']) ? (float)$in['initial_balance'] : 
               (isset($in['saldo_inicial']) ? (float)$in['saldo_inicial'] : null);
    $is_active = isset($in['is_active']) ? (int)$in['is_active'] : null;
    
    if ($id <= 0) {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'ID inválido']);
      exit;
    }

    $sets = [];
    $params = [];
    if ($name !== '') { $sets[] = 'name = ?'; $params[] = $name; }
    
    if ($kind !== '') {
      $type_enum = 'CASH';
      if (in_array(strtolower($kind), ['bank', 'banco'], true)) $type_enum = 'BANK';
      $sets[] = 'type = ?';
      $params[] = $type_enum;
    }
    
    if ($initial !== null) { $sets[] = 'saldo_inicial = ?'; $params[] = $initial; }
    if ($is_active !== null) { $sets[] = 'is_active = ?'; $params[] = $is_active; }
    
    if (!$sets) {
      echo json_encode(['ok' => true]);
      exit;
    }
    
    $sets[] = 'updated_at = NOW()';
    $sql = 'UPDATE cash_registers SET ' . implode(', ', $sets) . ' WHERE id = ?';
    $params[] = $id;
    $st = $pdo->prepare($sql);
    $st->execute($params);
    echo json_encode(['ok' => true]);
    exit;
  }

  if ($action === 'toggle') {
    $id = (int)($in['id'] ?? 0);
    if ($id <= 0) {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'ID inválido']);
      exit;
    }
    // Obtener estado actual
    $st = $pdo->prepare('SELECT is_active FROM cash_registers WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
      http_response_code(404);
      echo json_encode(['ok' => false, 'error' => 'No encontrado']);
      exit;
    }
    $new_active = (int)$row['is_active'] === 1 ? 0 : 1;
    $st = $pdo->prepare('UPDATE cash_registers SET is_active=?, updated_at=NOW() WHERE id=?');
    $st->execute([$new_active, $id]);
    echo json_encode(['ok' => true]);
    exit;
  }

  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Acción no soportada']);
  exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Método no soportado']);
