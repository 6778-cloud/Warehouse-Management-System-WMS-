<?php
require_once '../config/db.php';
requireLogin();

$search = $_GET['search'] ?? '';
$location_filter = $_GET['location_id'] ?? '';

// Build Query
$sql = "SELECT p.sku, p.name as product_name, l.code as location_code, l.zone, s.qty, p.unit, s.updated_at
        FROM stock s
        JOIN products p ON s.product_id = p.product_id
        JOIN locations l ON s.location_id = l.location_id
        WHERE s.qty > 0";
$params = [];

if ($search) {
    $sql .= " AND (p.sku LIKE :search OR p.name LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($location_filter) {
    $sql .= " AND s.location_id = :loc";
    $params[':loc'] = $location_filter;
}

$sql .= " ORDER BY p.sku, l.code";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set Headers for CSV Download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=stock_report_' . date('Y-m-d_H-i') . '.csv');

// Create File Pointer
$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 compatibility
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Validation: Define readable headers
$headers = ['SKU', 'Product Name', 'Location', 'Zone', 'Quantity', 'Unit', 'Last Updated'];
fputcsv($output, $headers);

// Loop and write data
foreach ($stocks as $row) {
    fputcsv($output, [
        $row['sku'],
        $row['product_name'],
        $row['location_code'],
        $row['zone'],
        $row['qty'],
        $row['unit'],
        $row['updated_at']
    ]);
}

fclose($output);
exit();
