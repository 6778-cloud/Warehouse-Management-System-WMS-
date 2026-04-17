<?php
require_once 'config/db.php';
requireLogin();
requireOffice(); // พนักงานออฟฟิศคีย์ข้อมูล

$outbound_id = $_GET['outbound_id'] ?? null;
if (!$outbound_id)
    die("รหัสไม่ถูกต้อง");

try {
    // Fetch outbound order
    $stmt = $pdo->prepare("SELECT * FROM outbound_orders WHERE outbound_id = ? AND status = 'shipped'");
    $stmt->execute([$outbound_id]);
    $order = $stmt->fetch();

    if (!$order)
        die("ไม่พบรายการหรือยังไม่ได้จัดส่ง");

    // Check if invoice already exists
    $existing = $pdo->prepare("SELECT invoice_id FROM invoices WHERE outbound_id = ?");
    $existing->execute([$outbound_id]);
    if ($existing_invoice = $existing->fetch()) {
        // ถ้ามี Invoice แล้ว ไปหน้า View
        header("Location: invoice_view.php?id=" . $existing_invoice['invoice_id']);
        exit;
    }

    // Fetch shipment data
    $stmt = $pdo->prepare("SELECT * FROM shipments WHERE outbound_id = ?");
    $stmt->execute([$outbound_id]);
    $shipment = $stmt->fetch();

    if (!$shipment) {
        die("ไม่พบข้อมูลการจัดส่ง กรุณาสร้างข้อมูลจัดส่งก่อน");
    }

    // Fetch order lines with product prices
    $stmt = $pdo->prepare("SELECT l.*, p.name, p.sku, p.selling_price, p.cost_price
                           FROM outbound_lines l 
                           JOIN products p ON l.product_id = p.product_id 
                           WHERE l.outbound_id = ?");
    $stmt->execute([$outbound_id]);
    $lines = $stmt->fetchAll();

    // === AUTO-CREATE INVOICE ===

    // 1. Generate Invoice Number
    $year_month = date('Ym'); // 202601
    $stmt = $pdo->prepare("SELECT invoice_no FROM invoices 
                           WHERE invoice_no LIKE ? 
                           ORDER BY invoice_id DESC LIMIT 1");
    $stmt->execute(["INV-$year_month-%"]);
    $last_invoice = $stmt->fetch();

    if ($last_invoice) {
        // Extract running number
        preg_match('/INV-\d{6}-(\d+)/', $last_invoice['invoice_no'], $matches);
        $running = isset($matches[1]) ? intval($matches[1]) + 1 : 1;
    } else {
        $running = 1;
    }

    $invoice_no = sprintf("INV-%s-%04d", $year_month, $running);

    // 2. Get customer data from shipment
    $customer_name = $shipment['receiver_name'];
    $customer_phone = $shipment['receiver_phone'];
    $customer_email = $shipment['receiver_email'];
    $customer_address = $shipment['delivery_address'];
    $customer_tax_id = ''; // ไม่มีใน shipment

    // 3. Calculate totals
    $subtotal = 0;
    foreach ($lines as $line) {
        $subtotal += $line['qty'] * $line['selling_price'];
    }

    $discount_amount = 0; // No discount by default
    $tax_rate = 7.00;
    $tax_amount = ($subtotal - $discount_amount) * ($tax_rate / 100);
    $total_amount = $subtotal - $discount_amount + $tax_amount;

    $issue_date = date('Y-m-d');
    $due_date = date('Y-m-d', strtotime('+30 days'));

    // 4. Insert invoice
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO invoices 
        (outbound_id, invoice_no, customer_name, customer_tax_id, customer_address, customer_phone, customer_email,
         issue_date, due_date, subtotal, discount_amount, tax_rate, tax_amount, total_amount, status, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?)");

    $stmt->execute([
        $outbound_id,
        $invoice_no,
        $customer_name,
        $customer_tax_id,
        $customer_address,
        $customer_phone,
        $customer_email,
        $issue_date,
        $due_date,
        $subtotal,
        $discount_amount,
        $tax_rate,
        $tax_amount,
        $total_amount,
        $_SESSION['user_id']
    ]);

    $invoice_id = $pdo->lastInsertId();

    // 5. Insert invoice lines
    $stmtLine = $pdo->prepare("INSERT INTO invoice_lines (invoice_id, product_id, description, qty, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?)");

    foreach ($lines as $line) {
        $unit_price = $line['selling_price'];
        $line_total = $line['qty'] * $unit_price;

        $stmtLine->execute([
            $invoice_id,
            $line['product_id'],
            $line['sku'] . ' - ' . $line['name'],
            $line['qty'],
            $unit_price,
            $line_total
        ]);
    }

    $pdo->commit();

    // 6. Redirect to invoice view
    header("Location: invoice_view.php?id=$invoice_id");
    exit;

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("เกิดข้อผิดพลาดในการสร้างใบแจ้งหนี้: " . $e->getMessage());
}
?>