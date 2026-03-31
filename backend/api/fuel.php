<?php
// API para gestionar combustible, vehículos y movimientos
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/auth.php';

$pdo = getPDO();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? null;

function json_ok($data) {
  echo json_encode(['success' => true] + $data, JSON_UNESCAPED_UNICODE);
  exit;
}

function json_err($msg, $code = 400) {
  http_response_code($code);
  echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  if ($method === 'GET') {
    requireLogin();
    
    if ($action === 'types') {
      // Obtener tipos de combustible
      $stmt = $pdo->query('SELECT id, name, type_name FROM fuel_types ORDER BY name');
      $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
      json_ok(['data' => $types]);
    }
    elseif ($action === 'vehicles') {
      // Obtener vehículos
      $stmt = $pdo->query('SELECT id, plate, model, fuel_type_id, tank_capacity, status, last_refuel_date FROM vehicles ORDER BY plate');
      $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
      json_ok(['data' => $vehicles]);
    }
    elseif ($action === 'movements') {
      // Obtener movimientos de combustible
      $stmt = $pdo->query('
        SELECT 
          m.id, m.movement_type, m.fuel_type_id, 
          ft.name as fuel_type_name, m.location, 
          m.liters, m.unit_price, m.supplier, 
          m.destination, m.vehicle_id, m.counterparty, 
          m.account_target, m.notes, m.date
        FROM fuel_movements m
        LEFT JOIN fuel_types ft ON m.fuel_type_id = ft.id
        ORDER BY m.date DESC LIMIT 100
      ');
      $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);
      json_ok(['data' => $movements]);
    }
    elseif ($action === 'stock') {
      // Obtener stock actual por ubicación
      $stmt = $pdo->query('
        SELECT 
          ft.name as fuel_type,
          ft.id as fuel_type_id,
          m.location,
          SUM(CASE WHEN m.movement_type = "ENTRADA" THEN m.liters ELSE -m.liters END) as liters_total
        FROM fuel_movements m
        JOIN fuel_types ft ON m.fuel_type_id = ft.id
        WHERE m.date >= DATE_SUB(CURDATE(), INTERVAL 2 YEAR)
        GROUP BY ft.id, m.location
        ORDER BY ft.name, m.location
      ');
      $stock = $stmt->fetchAll(PDO::FETCH_ASSOC);
      json_ok(['data' => $stock]);
    }
    else {
      json_err('Acción GET no especificada', 400);
    }
  }
  elseif ($method === 'POST') {
    requireLogin();
    
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    
    if ($action === 'add_movement') {
      $movement_type = $data['movement_type'] ?? null;
      $fuel_type_id = (int)($data['fuel_type_id'] ?? 0);
      $location = $data['location'] ?? null;
      $liters = (float)($data['liters'] ?? 0);
      $unit_price = (float)($data['unit_price'] ?? 0);
      $supplier = $data['supplier'] ?? null;
      $destination = $data['destination'] ?? null;
      $vehicle_id = empty($data['vehicle_id']) ? null : (int)$data['vehicle_id'];
      $counterparty = $data['counterparty'] ?? null;
      $account_target = $data['account_target'] ?? null;
      $notes = $data['notes'] ?? null;
      $date = $data['date'] ?? date('Y-m-d');
      
      if (!$movement_type || !$fuel_type_id || !$liters) {
        json_err('Datos incompletos (tipo, combustible, liters requeridos)', 400);
      }
      
      $stmt = $pdo->prepare('
        INSERT INTO fuel_movements 
        (movement_type, fuel_type_id, location, liters, unit_price, 
         supplier, destination, vehicle_id, counterparty, account_target, notes, date)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      ');
      
      $stmt->execute([
        $movement_type, $fuel_type_id, $location, $liters, $unit_price,
        $supplier, $destination, $vehicle_id, $counterparty, $account_target, $notes, $date
      ]);
      
      json_ok(['id' => $pdo->lastInsertId()]);
    }
    elseif ($action === 'add_vehicle') {
      $plate = $data['plate'] ?? null;
      $model = $data['model'] ?? null;
      $fuel_type_id = (int)($data['fuel_type_id'] ?? 0);
      $tank_capacity = (float)($data['tank_capacity'] ?? 0);
      $status = $data['status'] ?? 'ACTIVO';
      
      if (!$plate || !$model || !$fuel_type_id) {
        json_err('Datos incompletos (placa, modelo, combustible requeridos)', 400);
      }
      
      $stmt = $pdo->prepare('
        INSERT INTO vehicles (plate, model, fuel_type_id, tank_capacity, status)
        VALUES (?, ?, ?, ?, ?)
      ');
      
      $stmt->execute([$plate, $model, $fuel_type_id, $tank_capacity, $status]);
      
      json_ok(['id' => $pdo->lastInsertId()]);
    }
    elseif ($action === 'delete_movement') {
      $id = (int)($data['id'] ?? 0);
      if (!$id) {
        json_err('ID no especificado', 400);
      }
      
      $stmt = $pdo->prepare('DELETE FROM fuel_movements WHERE id = ?');
      $stmt->execute([$id]);
      
      json_ok(['deleted' => true]);
    }
    elseif ($action === 'delete_vehicle') {
      $id = (int)($data['id'] ?? 0);
      if (!$id) {
        json_err('ID no especificado', 400);
      }
      
      $stmt = $pdo->prepare('DELETE FROM vehicles WHERE id = ?');
      $stmt->execute([$id]);
      
      json_ok(['deleted' => true]);
    }
    else {
      json_err('Acción POST no válida', 400);
    }
  }
  else {
    json_err('Método no permitido', 405);
  }
} catch (Throwable $e) {
  json_err('Error: ' . $e->getMessage(), 500);
}
}
