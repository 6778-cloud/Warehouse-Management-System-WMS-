<?php
require_once 'config/db.php';
requireLogin();
// ไม่จำกัดสิทธิ์ - ใครก็ปริ้นได้

$id = $_GET['id'] ?? null;
if (!$id)
    die("รหัสไม่ถูกต้อง");

// Fetch inbound order
$stmt = $pdo->prepare("SELECT i.*, v.name as vendor_name, v.phone as vendor_phone
                       FROM inbound_orders i
                       LEFT JOIN vendors v ON i.vendor_id = v.vendor_id
                       WHERE i.inbound_id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order)
    die("ไม่พบรายการ");

// Fetch order lines
$stmt = $pdo->prepare("SELECT il.*, p.name, p.sku, p.unit
                       FROM inbound_lines il
                       JOIN products p ON il.product_id = p.product_id
                       WHERE il.inbound_id = ?
                       ORDER BY il.line_id");
$stmt->execute([$id]);
$lines = $stmt->fetchAll();

// Translate status
$statusText = $order['status'];
if ($order['status'] == 'draft')
    $statusText = 'ร่าง';
elseif ($order['status'] == 'received')
    $statusText = 'รับแล้ว';
elseif ($order['status'] == 'partial')
    $statusText = 'รับบางส่วน';
elseif ($order['status'] == 'completed')
    $statusText = 'เสร็จสิ้น';
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ใบรับสินค้าเข้า - <?php echo h($order['po_no'] ?? 'ID#' . $order['inbound_id']); ?></title>
    <style>
        @media print {
            .no-print {
                display: none;
            }

            @page {
                margin: 1cm;
            }
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Sarabun', Arial, sans-serif;
            padding: 20px;
            max-width: 21cm;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            border-bottom: 3px solid #333;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }

        .header p {
            color: #666;
            font-size: 14px;
        }

        .info-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-box {
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 5px;
        }

        .info-box h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }

        .info-row {
            display: flex;
            padding: 5px 0;
        }

        .info-label {
            width: 120px;
            color: #666;
            font-size: 13px;
        }

        .info-value {
            flex: 1;
            font-weight: 600;
            font-size: 13px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
            font-size: 13px;
        }

        th {
            background: #f5f5f5;
            font-weight: 600;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-draft {
            background: #fee;
            color: #c33;
        }

        .status-completed {
            background: #efe;
            color: #3c3;
        }

        .status-received {
            background: #fef3cd;
            color: #856404;
        }

        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }

        .signature-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-top: 60px;
        }

        .signature-box {
            text-align: center;
        }

        .signature-line {
            border-top: 1px solid #333;
            margin-top: 60px;
            padding-top: 10px;
        }

        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #4ade80;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        .print-button:hover {
            background: #22c55e;
        }
    </style>
</head>

<body>
    <button onclick="window.print()" class="print-button no-print">
        🖨️ พิมพ์
    </button>

    <div class="header">
        <h1>ใบรับสินค้าเข้า</h1>
        <p>INBOUND ORDER</p>
    </div>

    <div class="info-section">
        <div class="info-box">
            <h3>ข้อมูลคำสั่งซื้อ</h3>
            <div class="info-row">
                <span class="info-label">เลขที่ PO:</span>
                <span class="info-value">
                    <?php echo h($order['po_no'] ?? 'N/A'); ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">รหัสรับเข้า:</span>
                <span class="info-value">
                    #<?php echo h($order['inbound_id']); ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">สถานะ:</span>
                <span class="info-value">
                    <span class="status-badge status-<?php echo $order['status']; ?>">
                        <?php echo strtoupper($statusText); ?>
                    </span>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">วันที่สร้าง:</span>
                <span class="info-value">
                    <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?>
                </span>
            </div>
        </div>

        <div class="info-box">
            <h3>ข้อมูลผู้จำหน่าย</h3>
            <div class="info-row">
                <span class="info-label">ผู้จำหน่าย:</span>
                <span class="info-value">
                    <?php echo h($order['vendor_name'] ?? '-'); ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">โทรศัพท์:</span>
                <span class="info-value">
                    <?php echo h($order['vendor_phone'] ?? '-'); ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">วันที่คาดรับ:</span>
                <span class="info-value">
                    <?php echo !empty($order['expected_date']) ? date('d/m/Y', strtotime($order['expected_date'])) : '-'; ?>
                </span>
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th class="text-center" width="50">#</th>
                <th>SKU</th>
                <th>ชื่อสินค้า</th>
                <th class="text-center">หน่วย</th>
                <th class="text-center">จำนวนที่คาด</th>
                <?php if ($order['status'] != 'draft'): ?>
                    <th class="text-center">จำนวนที่รับ</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lines as $index => $line): ?>
                <tr>
                    <td class="text-center">
                        <?php echo $index + 1; ?>
                    </td>
                    <td>
                        <?php echo h($line['sku']); ?>
                    </td>
                    <td>
                        <?php echo h($line['name']); ?>
                    </td>
                    <td class="text-center">
                        <?php echo h($line['unit']); ?>
                    </td>
                    <td class="text-center">
                        <?php echo number_format($line['expected_qty']); ?>
                    </td>
                    <?php if ($order['status'] != 'draft'): ?>
                        <td class="text-center"><strong>
                                <?php echo number_format($line['received_qty'] ?? 0); ?>
                            </strong></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>


    <?php if (!empty($order['notes'] ?? '')): ?>
        <div class="info-box" style="margin-top: 20px;">
            <h3>หมายเหตุ</h3>
            <p>
                <?php echo nl2br(h($order['notes'])); ?>
            </p>
        </div>
    <?php endif; ?>


    <div class="footer">
        <p style="text-align: center; color: #999; font-size: 11px;">
            พิมพ์เมื่อ
            <?php echo date('d/m/Y H:i:s'); ?> | WMS Smart
        </p>
    </div>
</body>

</html>