<?php
/**
 * Reporte Diario de Compras
 * Agrupa compras por proveedor y usuario
 */

require_once(__DIR__ . '/../config/auth.php');
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$pdo = getPDO();

try {
    // Parámetros opcionales
    $date = $_GET['date'] ?? date('Y-m-d');
    $userId = $_GET['user_id'] ?? null;
    $supplierId = $_GET['supplier_id'] ?? null;

    $sql = "SELECT 
        p.id,
        p.proveedor as supplier_name,
        p.telefono as phone,
        p.total,
        p.iva,
        p.metodo as payment_method,
        p.id_usuario as user_id,
        u.nombre as user_name,
        cr.nombre as cash_register_name,
        DATE(p.created_at) as purchase_date,
        p.created_at
    FROM purchases p
    LEFT JOIN users u ON p.id_usuario = u.id
    LEFT JOIN cash_registers cr ON p.id_caja = cr.id
    WHERE DATE(p.created_at) = ?";

    $params = [$date];

    if ($userId) {
        $sql .= " AND p.id_usuario = ?";
        $params[] = $userId;
    }

    if ($supplierId) {
        $sql .= " AND p.id_proveedor = ?";
        $params[] = $supplierId;
    }

    $sql .= " ORDER BY p.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcular resumen
    $summary = [
        'total_purchases' => 0,
        'total_iva' => 0,
        'net_purchases' => 0,
        'count_by_method' => [],
        'by_supplier' => [],
        'by_user' => []
    ];

    foreach ($purchases as $purchase) {
        $total = floatval($purchase['total']);
        $iva = floatval($purchase['iva'] ?? 0);

        $summary['total_purchases'] += $total;
        $summary['total_iva'] += $iva;
        $summary['net_purchases'] += ($total - $iva);

        // Por método
        $method = $purchase['payment_method'] ?? 'DESCONOCIDO';
        if (!isset($summary['count_by_method'][$method])) {
            $summary['count_by_method'][$method] = ['count' => 0, 'amount' => 0];
        }
        $summary['count_by_method'][$method]['count']++;
        $summary['count_by_method'][$method]['amount'] += $total;

        // Por proveedor
        $supplierKey = $purchase['supplier_name'] ?? 'Sin proveedor';
        if (!isset($summary['by_supplier'][$supplierKey])) {
            $summary['by_supplier'][$supplierKey] = ['count' => 0, 'amount' => 0];
        }
        $summary['by_supplier'][$supplierKey]['count']++;
        $summary['by_supplier'][$supplierKey]['amount'] += $total;

        // Por usuario
        $userKey = $purchase['user_name'] ?? 'Sin usuario';
        if (!isset($summary['by_user'][$userKey])) {
            $summary['by_user'][$userKey] = ['count' => 0, 'amount' => 0];
        }
        $summary['by_user'][$userKey]['count']++;
        $summary['by_user'][$userKey]['amount'] += $total;
    }

    echo json_encode([
        'ok' => true,
        'date' => $date,
        'summary' => $summary,
        'purchases' => $purchases,
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