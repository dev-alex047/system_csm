<?php
// purchase_report.php – Lista detallada de operaciones de compra.
// Devuelve un arreglo JSON con cada entrada de inventario registrada
// a través del módulo de compras. Se incluyen datos del producto,
// precio de compra, precio público vigente, proveedor y usuario que
// registró la compra. Se admite un parámetro opcional "search" para
// filtrar por código interno, código de barras, nombre o ID del producto.

header('Content-Type: application/json');
require_once __DIR__ . '/auth.php';

// Sólo usuarios autenticados pueden consultar el reporte
requireLogin();

// Conexión a la base de datos
$pdo = getPDO();

// Filtro opcional de búsqueda
$search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';

// Construcción de la consulta base
$sql = "SELECT
    p.date           AS operation_date,
    p.created_at     AS created_at,
    u.username       AS user_name,
    prod.id          AS product_id,
    prod.code        AS code,
    prod.barcode     AS barcode,
    prod.name        AS product_name,
    pi.quantity      AS quantity,
    pi.unit_price    AS purchase_price,
    COALESCE(pi.public_price, prod.public_price) AS public_price_at_purchase,
    pi.profit_margin AS profit_margin_at_purchase,
    s.name           AS supplier_name
  FROM purchase_items pi
  JOIN purchases p       ON pi.purchase_id = p.id
  JOIN products prod     ON pi.product_id = prod.id
  LEFT JOIN users u      ON p.created_by_user_id = u.id
  LEFT JOIN suppliers s  ON p.supplier_id = s.id";

// Parámetros de enlace
$params = [];
// Aplicar filtro si se proporcionó
if ($search !== '') {
    $sql .= " WHERE (prod.barcode LIKE ? OR prod.code LIKE ? OR prod.name LIKE ? OR CAST(prod.id AS CHAR) LIKE ?)";
    $like = '%' . $search . '%';
    $params = [$like, $like, $like, $like];
}

// Ordenar por fecha de operación y momento de creación
$sql .= " ORDER BY p.date DESC, p.created_at DESC";

// Ejecutar consulta
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($records);