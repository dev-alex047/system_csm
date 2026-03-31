<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// Evita que warnings/notices rompan el JSON
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Convierte warnings/notices en excepción para responder JSON
set_error_handler(function($severity, $message, $file, $line){
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function($e){
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "SERVER_ERROR",
        "message" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

require_once __DIR__ . '/auth.php';
requireLogin();
require_once __DIR__ . '/db.php';

$pdo = getPDO();

// Handle POST requests (CREATE)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = json_decode(file_get_contents('php://input'), true);
    
    if (!$json || !isset($json['action'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Missing action']);
        exit;
    }
    $action = $json['action'];

    if ($action === 'create') {
        // Create new account move
        // Expected parameters: type (ENTRADA/SALIDA), cash_register_id, amount, reference, link_type, link_id, note, etc.
        $type = strtoupper($json['type'] ?? 'ENTRADA');
        $origin = $json['origin'] ?? 'CAJA';
        $cashRegId = $json['cash_register_id'] ?? null;
        $amount = floatval($json['amount'] ?? 0);
        $reference = $json['reference'] ?? null;
        $linkType = $json['link_type'] ?? 'OTRO';
        $linkId = $json['link_id'] ?? null;
        $note = $json['note'] ?? null;
        $ticketNumber = $json['ticket_number'] ?? null;

        if ($amount <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid amount']);
            exit;
        }

        try {
            $userId = $_SESSION['usuarioId'] ?? 1;
            
            $sql = "INSERT INTO account_moves (
                type, origin, cash_register_id, amount, reference, user_id, 
                link_type, link_id, ticket_number, note, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PAGADO')";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $type,
                $origin,
                $cashRegId,
                $amount,
                $reference,
                $userId,
                $linkType,
                $linkId,
                $ticketNumber,
                $note
            ]);

            echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId()]);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
}

// Handle GET requests (READ)
function normalizeDate(?string $date): ?string {
    if (!$date) return null;
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d ? $d->format('Y-m-d') : null;
}

$start = normalizeDate($_GET['start'] ?? null);
$end   = normalizeDate($_GET['end'] ?? null);

if ($start && !$end) $end = $start;
if ($end && !$start) $start = $end;

if (!$start && !$end) {
    $start = date('Y-m-d', strtotime('-30 days'));
    $end   = date('Y-m-d');
}

// Filtros
$where = [];
$params = [];
if ($start) { $where[] = "am.date >= ?"; $params[] = $start . " 00:00:00"; }
if ($end)   { $where[] = "am.date <= ?"; $params[] = $end   . " 23:59:59"; }

// Por defecto, excluimos movimientos que representan ventas a crédito
// (origin = 'CUENTA' y link_type = 'VENTA') ya que deben verse en Cuentas por Cobrar
if (!(isset($_GET['include_credit_moves']) && $_GET['include_credit_moves'] === '1')) {
    $where[] = "NOT (am.origin = 'CUENTA' AND am.link_type = 'VENTA')";
}

$whereClause = $where ? ("WHERE " . implode(" AND ", $where)) : "";

// Movimientos (mapeo a los nombres que tu JS espera)
$sql = "
SELECT
  am.id,
  am.date AS move_date,
  am.type AS move_type,
  am.origin AS origin,
  am.reference AS reference,
  am.amount AS amount,
  am.status AS status,
  am.note AS note,
  am.created_at,
  am.updated_at,
  am.user_reference_name AS operator_name,
  u.username AS user_name,
  cr.name AS account_name,
  am.cash_register_id,
  am.link_type,
  am.link_id
FROM account_moves am
LEFT JOIN users u ON u.id = am.user_id
LEFT JOIN cash_registers cr ON cr.id = am.cash_register_id
$whereClause
ORDER BY am.date DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Saldos (solo si include_balances=1)
$includeBalances = ($_GET['include_balances'] ?? '') === '1';
$balances = [];

if ($includeBalances) {
    // Traer cajas/cuentas
    // OJO: tu tabla parece NO tener saldo_inicial, así que lo dejamos en 0 si no existe
    // Si sí existe, la consulta lo traerá.
    $cols = $pdo->query("SHOW COLUMNS FROM cash_registers")->fetchAll(PDO::FETCH_ASSOC);
    $hasInitial = false;
    foreach ($cols as $c) {
        if ($c['Field'] === 'saldo_inicial') { $hasInitial = true; break; }
    }

    $cashSql = $hasInitial
        ? "SELECT id, name, saldo_inicial FROM cash_registers WHERE is_active=1 ORDER BY id ASC"
        : "SELECT id, name FROM cash_registers WHERE is_active=1 ORDER BY id ASC";

    $cashStmt = $pdo->query($cashSql);
    $accounts = [];
    foreach ($cashStmt as $r) {
        $accounts[(int)$r['id']] = [
            "id" => (int)$r['id'],
            "name" => $r['name'],
            "balance" => $hasInitial ? (float)$r['saldo_inicial'] : 0.0
        ];
    }

    // Sumar movimientos (ENTRADA suma, SALIDA resta)
    // NOTA: En tu BD ya guardas entradas con amount positivo y salidas negativo,
    // pero igual lo manejamos por tipo para que sea estable.
    foreach ($rows as $m) {
        if (isset($m['status']) && strtoupper((string)$m['status']) === 'CANCELADO') {
            continue;
        }
        $cid = $m['cash_register_id'];
        if (!$cid) continue;
        $cid = (int)$cid;
        if (!isset($accounts[$cid])) continue;

        $amt = (float)$m['amount'];
        $t = strtoupper((string)$m['move_type']);

        if ($t === 'SALIDA') {
            $accounts[$cid]['balance'] += ($amt <= 0 ? $amt : -$amt);
        } else {
            $accounts[$cid]['balance'] += ($amt >= 0 ? $amt : -$amt);
        }
    }

    $balances = array_values($accounts);
}

echo json_encode([
    "success" => true,
    "data" => $rows,
    "balances" => $balances,
    "start" => $start,
    "end" => $end
], JSON_UNESCAPED_UNICODE);
