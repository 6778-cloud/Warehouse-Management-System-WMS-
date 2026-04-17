<?php
// api/dashboard_stats.php
require_once '../config/db.php';

header('Content-Type: application/json');

try {
    // Get filter parameter
    $month = $_GET['month'] ?? 'all';

    // Build WHERE clause for filter
    $dateFilter = "";
    if ($month !== 'all') {
        $dateFilter = " AND DATE_FORMAT(o.created_at, '%Y-%m') = '" . $pdo->quote($month) . "'";
        $dateFilter = str_replace("'", "", $dateFilter); // Remove quotes added by quote()
        $dateFilter = " AND DATE_FORMAT(o.created_at, '%Y-%m') = '$month'";
    }

    // 1. Total Products (not affected by filter)
    $total_products = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();

    // 2. Low Stock (not affected by filter)
    $low_stock_count = $pdo->query("SELECT COUNT(*) FROM products WHERE (SELECT COALESCE(SUM(qty), 0) FROM stock WHERE stock.product_id = products.product_id) <= min_stock")->fetchColumn();

    // 3. Pending Inbound (filtered by month)
    $pending_inbound_sql = "SELECT COUNT(*) FROM inbound_orders WHERE status != 'completed'";
    if ($month !== 'all') {
        $pending_inbound_sql .= " AND DATE_FORMAT(created_at, '%Y-%m') = '$month'";
    }
    $pending_inbound = $pdo->query($pending_inbound_sql)->fetchColumn();

    // 4. Pending Outbound (filtered by month)
    $pending_outbound_sql = "SELECT COUNT(*) FROM outbound_orders WHERE status != 'shipped'";
    if ($month !== 'all') {
        $pending_outbound_sql .= " AND DATE_FORMAT(created_at, '%Y-%m') = '$month'";
    }
    $pending_outbound = $pdo->query($pending_outbound_sql)->fetchColumn();

    // 5. Orders Shipped (filtered)
    $shipped_sql = "SELECT COUNT(*) FROM outbound_orders o WHERE o.status = 'shipped' AND o.order_type = 'sale'";
    if ($month !== 'all') {
        $shipped_sql .= " AND DATE_FORMAT(o.created_at, '%Y-%m') = '$month'";
    }
    $shipped_count = $pdo->query($shipped_sql)->fetchColumn();



    // === CHARTS DATA ===

    // Chart 1: Inbound Completed vs Outbound Shipped (filtered)
    if ($month !== 'all') {
        $completed_inbound = $pdo->query("SELECT COUNT(*) FROM inbound_orders WHERE status = 'completed' AND DATE_FORMAT(created_at, '%Y-%m') = '$month'")->fetchColumn();
        $shipped_outbound = $pdo->query("SELECT COUNT(*) FROM outbound_orders WHERE status = 'shipped' AND DATE_FORMAT(created_at, '%Y-%m') = '$month'")->fetchColumn();
        $pending_count = $pdo->query("SELECT COUNT(*) FROM outbound_orders WHERE status != 'shipped' AND DATE_FORMAT(created_at, '%Y-%m') = '$month'")->fetchColumn();
    } else {
        $completed_inbound = $pdo->query("SELECT COUNT(*) FROM inbound_orders WHERE status = 'completed'")->fetchColumn();
        $shipped_outbound = $pdo->query("SELECT COUNT(*) FROM outbound_orders WHERE status = 'shipped'")->fetchColumn();
        $pending_count = $pdo->query("SELECT COUNT(*) FROM outbound_orders WHERE status != 'shipped'")->fetchColumn();
    }

    // Chart 2: Movement History (filtered by period)
    $movement_labels = [];
    $movement_in = [];
    $movement_out = [];

    if ($month === 'all') {
        // Last 6 months
        for ($i = 5; $i >= 0; $i--) {
            $period = date('Y-m', strtotime("-$i months"));
            $label = date('M', strtotime("-$i months"));
            $movement_labels[] = $label;

            $sqlIn = "SELECT COUNT(*) FROM inbound_orders WHERE DATE_FORMAT(created_at, '%Y-%m') = '$period'";
            $movement_in[] = (int) $pdo->query($sqlIn)->fetchColumn();

            $sqlOut = "SELECT COUNT(*) FROM outbound_orders WHERE DATE_FORMAT(created_at, '%Y-%m') = '$period'";
            $movement_out[] = (int) $pdo->query($sqlOut)->fetchColumn();
        }
    } else {
        // Weekly breakdown for selected month
        $daysInMonth = date('t', strtotime($month . '-01'));
        for ($week = 1; $week <= 4; $week++) {
            $startDay = ($week - 1) * 7 + 1;
            $endDay = min($week * 7, $daysInMonth);
            if ($week == 4)
                $endDay = $daysInMonth; // Last week includes remaining days

            $movement_labels[] = "Week $week";

            $sqlIn = "SELECT COUNT(*) FROM inbound_orders 
                      WHERE DATE_FORMAT(created_at, '%Y-%m') = '$month' 
                      AND DAY(created_at) BETWEEN $startDay AND $endDay";
            $movement_in[] = (int) $pdo->query($sqlIn)->fetchColumn();

            $sqlOut = "SELECT COUNT(*) FROM outbound_orders 
                       WHERE DATE_FORMAT(created_at, '%Y-%m') = '$month' 
                       AND DAY(created_at) BETWEEN $startDay AND $endDay";
            $movement_out[] = (int) $pdo->query($sqlOut)->fetchColumn();
        }
    }



    // Chart 4: Top Selling Products (filtered)
    $topProductsSql = "
        SELECT p.name, SUM(ol.qty) as total_qty
        FROM outbound_orders o
        JOIN outbound_lines ol ON o.outbound_id = ol.outbound_id
        JOIN products p ON ol.product_id = p.product_id
        WHERE o.order_type = 'sale' AND o.status = 'shipped'";
    if ($month !== 'all') {
        $topProductsSql .= " AND DATE_FORMAT(o.created_at, '%Y-%m') = '$month'";
    }
    $topProductsSql .= " GROUP BY p.product_id ORDER BY total_qty DESC LIMIT 5";

    $topProducts = $pdo->query($topProductsSql)->fetchAll();
    $top_products_labels = array_column($topProducts, 'name');
    $top_products_data = array_column($topProducts, 'total_qty');

    echo json_encode([
        'success' => true,
        'data' => [
            'total_products' => $total_products,
            'low_stock_count' => $low_stock_count,
            'pending_inbound' => $pending_inbound,
            'pending_outbound' => $pending_outbound,
            'shipped_count' => $shipped_count,
            'timestamp' => date('H:i:s'),
            'charts' => [
                'stock_distribution' => [$completed_inbound, $shipped_outbound, $pending_count],
                'movement_labels' => $movement_labels,
                'movement_in' => $movement_in,
                'movement_out' => $movement_out,
                'top_products_labels' => $top_products_labels,
                'top_products_data' => $top_products_data
            ]
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>