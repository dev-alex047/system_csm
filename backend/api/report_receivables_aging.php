<?php
/**
 * Reporte de Antigüedad de Cuentas por Cobrar
 * Clasifica las deudas por edad: 0-30, 31-60, 61-90, 90+ días
 */

require_once(__DIR__ . '/../config/auth.php');
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$pdo = getPDO();

try {
    $sql = "SELECT 
        r.id,
        r.sale_id,
        r.client_name,
        r.total_amount,
        r.paid_amount,
        (r.total_amount - r.paid_amount) as pending_amount,
        r.status,
        r.created_at,
        DATEDIFF(NOW(), r.created_at) as days_overdue,
        CASE 
            WHEN DATEDIFF(NOW(), r.created_at) <= 30 THEN 'CURRENT'
            WHEN DATEDIFF(NOW(), r.created_at) <= 60 THEN '31-60'
            WHEN DATEDIFF(NOW(), r.created_at) <= 90 THEN '61-90'
            ELSE '90+'
        END as age_bucket
    FROM receivables r
    WHERE r.status IN ('PENDIENTE', 'PAGADO')
    ORDER BY r.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcular totales por rango de edad
    $summary = [
        'CURRENT' => ['count' => 0, 'amount' => 0],
        '31-60' => ['count' => 0, 'amount' => 0],
        '61-90' => ['count' => 0, 'amount' => 0],
        '90+' => ['count' => 0, 'amount' => 0],
        'TOTAL' => ['count' => 0, 'amount' => 0]
    ];

    foreach ($records as $r) {
        $bucket = $r['age_bucket'];
        $pending = $r['pending_amount'];
        
        if ($r['status'] === 'PENDIENTE') {
            $summary[$bucket]['count']++;
            $summary[$bucket]['amount'] += $pending;
            $summary['TOTAL']['count']++;
            $summary['TOTAL']['amount'] += $pending;
        }
    }

    echo json_encode([
        'ok' => true,
        'summary' => $summary,
        'details' => $records,
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