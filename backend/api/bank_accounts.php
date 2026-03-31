<?php
// API para gestionar cuentas bancarias y sus movimientos
header('Content-Type: application/json');
require_once __DIR__ . '/auth.php';
requireRole('admin');
require_once __DIR__ . '/db.php';

function normalizeDate($date) {
    if (!$date) return null;
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d ? $d->format('Y-m-d') : null;
}

$pdo = getPDO();

// Soporta métodos para crear/editar cuentas
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $start = isset($_GET['start']) ? normalizeDate($_GET['start']) : null;
    $end   = isset($_GET['end'])   ? normalizeDate($_GET['end'])   : null;

    // Obtener cuenta
    $stmtAcc = $pdo->prepare('SELECT * FROM bank_accounts WHERE id = ?');
    $stmtAcc->execute([$id]);
    $account = $stmtAcc->fetch(PDO::FETCH_ASSOC);
    if (!$account) {
        http_response_code(404);
        echo json_encode(['error' => 'Bank account not found']);
        exit;
    }
    // Obtener movimientos
    $where = ['bm.bank_account_id = ?'];
    $params = [$id];
    if ($start) {
        $where[] = 'bm.date >= ?';
        $params[] = $start . ' 00:00:00';
    }
    if ($end) {
        $where[] = 'bm.date <= ?';
        $params[] = $end . ' 23:59:59';
    }
    $whereClause = 'WHERE ' . implode(' AND ', $where);
    $sqlMoves = "SELECT bm.*, u.username AS user_name
      FROM bank_moves bm
      LEFT JOIN users u ON bm.user_id = u.id
      $whereClause
      ORDER BY bm.date DESC";
    $stmtMov = $pdo->prepare($sqlMoves);
    $stmtMov->execute($params);
    $moves = $stmtMov->fetchAll(PDO::FETCH_ASSOC);
    // Calcular saldos
    $incomes = 0;
    $expenses = 0;
    foreach ($moves as $m) {
        $amt = floatval($m['amount']);
        if (in_array($m['type'], ['DEPOSIT','SALE'])) {
            $incomes += $amt;
        } else {
            $expenses += $amt;
        }
    }
    echo json_encode(['account' => $account, 'moves' => $moves, 'incomes' => $incomes, 'expenses' => $expenses]);
    exit;
    // already returned account details
    return;
}

if ($method === 'GET') {
    // Listado de cuentas bancarias
    $stmt = $pdo->query('SELECT * FROM bank_accounts ORDER BY id');
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['accounts' => $accounts]);
    return;
}

// Solo admins pueden crear o editar cuentas
requireRole('admin');

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Nombre de la cuenta requerido']);
        return;
    }
    $name = trim($data['name']);
    $bankName = isset($data['bank_name']) ? trim($data['bank_name']) : null;
    $accountNumber = isset($data['account_number']) ? trim($data['account_number']) : null;
    $initial = isset($data['balance']) ? floatval($data['balance']) : 0;
    $stmt = $pdo->prepare('INSERT INTO bank_accounts (name, bank_name, account_number, balance) VALUES (?,?,?,?)');
    $stmt->execute([$name, $bankName, $accountNumber, $initial]);
    $id = $pdo->lastInsertId();
    echo json_encode(['success' => true, 'id' => $id]);
    return;
}

if ($method === 'PUT') {
    parse_str(file_get_contents('php://input'), $data);
    if (empty($data)) {
        $data = json_decode(file_get_contents('php://input'), true);
    }
    $id = isset($data['id']) ? intval($data['id']) : 0;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID requerido']);
        return;
    }
    // Proteger cuentas iniciales 1 y 2 (Banamex y Banorte) de eliminación
    $name = isset($data['name']) ? trim($data['name']) : null;
    $bankName = isset($data['bank_name']) ? trim($data['bank_name']) : null;
    $accountNumber = isset($data['account_number']) ? trim($data['account_number']) : null;
    $balance = isset($data['balance']) ? floatval($data['balance']) : null;
    $updates = [];
    $params = [];
    if ($name !== null && $name !== '') {
        $updates[] = 'name = ?';
        $params[] = $name;
    }
    if ($bankName !== null) {
        $updates[] = 'bank_name = ?';
        $params[] = $bankName;
    }
    if ($accountNumber !== null) {
        $updates[] = 'account_number = ?';
        $params[] = $accountNumber;
    }
    if ($balance !== null) {
        $updates[] = 'balance = ?';
        $params[] = $balance;
    }
    if (!$updates) {
        echo json_encode(['error' => 'Nada para actualizar']);
        return;
    }
    $params[] = $id;
    $sql = 'UPDATE bank_accounts SET ' . implode(',', $updates) . ' WHERE id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['success' => true]);
    return;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
?>