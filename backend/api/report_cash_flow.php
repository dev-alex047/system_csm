<?php
/**
 * Reporte de Flujo de Caja
 * Muestra entradas y salidas por caja/banco
 */

require_once(__DIR__ . '/../config/auth.php');
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$pdo = getPDO();

try {
    // Parámetros
    $startDate = $_GET['start_date'] ?? date('Y-m-01');
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    $cashRegisterId = $_GET['cash_register_id'] ?? null;

    $sql = "SELECT 
        am.id,
        am.tipo,
        am.concepto,
        am.monto,
        am.metodo,
        am.nota,
        am.id_caja as cash_register_id,
        cr.nombre as cash_register_name,
        cr.tipo as cash_type,
        am.id_usuario as user_id,
        u.nombre as user_name,
        DATE(am.created_at) as movement_date,
        am.created_at
    FROM account_moves am
    LEFT JOIN cash_registers cr ON am.id_caja = cr.id
    LEFT JOIN users u ON am.id_usuario = u.id
    WHERE DATE(am.created_at) BETWEEN ? AND ?";

    $params = [$startDate, $endDate];

    if ($cashRegisterId) {
        $sql .= " AND am.id_caja = ?";
        $params[] = $cashRegisterId;
    }

    $sql .= " ORDER BY am.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcular resumen
    $summary = [
        'period' => [
            'start' => $startDate,
            'end' => $endDate
        ],
        'total_inflows' => 0,
        'total_outflows' => 0,
        'net_flow' => 0,
        'by_cash_register' => [],
        'by_date' => []
    ];

    foreach ($movements as $move) {
        $monto = floatval($move['monto']);
        $tipo = strtoupper($move['tipo']);
        
        if ($tipo === 'ENTRADA') {
            $summary['total_inflows'] += $monto;
        } else {
            $summary['total_outflows'] += $monto;
        }

        // Por caja
        $cajaKey = $move['cash_register_name'] ?? 'Sin caja';
        if (!isset($summary['by_cash_register'][$cajaKey])) {
            $summary['by_cash_register'][$cajaKey] = [
                'type' => $move['cash_type'],
                'inflows' => 0,
                'outflows' => 0,
                'net' => 0
            ];
        }

        if ($tipo === 'ENTRADA') {
            $summary['by_cash_register'][$cajaKey]['inflows'] += $monto;
        } else {
            $summary['by_cash_register'][$cajaKey]['outflows'] += $monto;
        }

        // Por fecha
        $dateKey = $move['movement_date'];
        if (!isset($summary['by_date'][$dateKey])) {
            $summary['by_date'][$dateKey] = ['inflows' => 0, 'outflows' => 0];
        }

        if ($tipo === 'ENTRADA') {
            $summary['by_date'][$dateKey]['inflows'] += $monto;
        } else {
            $summary['by_date'][$dateKey]['outflows'] += $monto;
        }
    }

    $summary['net_flow'] = $summary['total_inflows'] - $summary['total_outflows'];

    // Calcular neto por caja
    foreach ($summary['by_cash_register'] as &$caja) {
        $caja['net'] = $caja['inflows'] - $caja['outflows'];
    }

    echo json_encode([
        'ok' => true,
        'summary' => $summary,
        'movements' => $movements,
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