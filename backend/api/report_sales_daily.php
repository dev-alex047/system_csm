<?php
/**
 * Reporte Diario de Ventas
 * Agrupa ventas por usuario y método de pago
 */

require_once(__DIR__ . '/../config/auth.php');
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$pdo = getPDO();

try {
    // Parámetros opcionales
    $date = $_GET['date'] ?? date('Y-m-d');
    $userId = $_GET['user_id'] ?? null;
    $cashRegisterId = $_GET['cash_register_id'] ?? null;

    $sql = "SELECT 
        s.id,
        s.cliente as client_name,
        s.telefono as phone,
        s.total,
        s.descuento as discount,
        s.metodo as payment_method,
        s.forma_pago as payment_form,
        s.id_usuario as user_id,
        u.nombre as user_name,
        cr.nombre as cash_register_name,
        DATE(s.created_at) as sale_date,
        s.created_at
    FROM sales s
    LEFT JOIN users u ON s.id_usuario = u.id
    LEFT JOIN cash_registers cr ON s.id_caja = cr.id
    WHERE DATE(s.created_at) = ?";

    $params = [$date];

    if ($userId) {
        $sql .= " AND s.id_usuario = ?";
        $params[] = $userId;
    }

    if ($cashRegisterId) {
        $sql .= " AND s.id_caja = ?";
        $params[] = $cashRegisterId;
    }

    $sql .= " ORDER BY s.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcular resumen
    $summary = [
        'total_sales' => 0,
        'total_discount' => 0,
        'net_sales' => 0,
        'count_by_method' => [],
        'count_by_user' => [],
        'by_user' => []
    ];

    foreach ($sales as $sale) {
        $total = floatval($sale['total']);
        $discount = floatval($sale['discount'] ?? 0);

        $summary['total_sales'] += $total;
        $summary['total_discount'] += $discount;
        $summary['net_sales'] += ($total - $discount);

        // Por método
        $method = $sale['payment_method'] ?? 'DESCONOCIDO';
        if (!isset($summary['count_by_method'][$method])) {
            $summary['count_by_method'][$method] = ['count' => 0, 'amount' => 0];
        }
        $summary['count_by_method'][$method]['count']++;
        $summary['count_by_method'][$method]['amount'] += $total;

        // Por usuario
        $userKey = $sale['user_name'] ?? 'Sin usuario';
        if (!isset($summary['by_user'][$userKey])) {
            $summary['by_user'][$userKey] = ['count' => 0, 'amount' => 0, 'discount' => 0];
        }
        $summary['by_user'][$userKey]['count']++;
        $summary['by_user'][$userKey]['amount'] += $total;
        $summary['by_user'][$userKey]['discount'] += $discount;
    }

    echo json_encode([
        'ok' => true,
        'date' => $date,
        'summary' => $summary,
        'sales' => $sales,
        'generated_at' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
?>