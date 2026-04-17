<?php
require_once 'config/db.php';
requireLogin();

$id = $_GET['id'] ?? null;
if (!$id)
    die("รหัสไม่ถูกต้อง");

// Fetch Order
$stmt = $pdo->prepare("SELECT * FROM outbound_orders WHERE outbound_id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch();

// Fetch Lines
$stmt = $pdo->prepare("SELECT l.*, p.sku, p.name as product_name 
                       FROM outbound_lines l 
                       JOIN products p ON l.product_id = p.product_id 
                       WHERE l.outbound_id = ?");
$stmt->execute([$id]);
$lines = $stmt->fetchAll();

// Translate status
$statusText = $order['status'];
if ($order['status'] == 'draft')
    $statusText = 'ร่าง';
elseif ($order['status'] == 'allocated')
    $statusText = 'จัดสรรแล้ว';
elseif ($order['status'] == 'picked')
    $statusText = 'หยิบแล้ว';
elseif ($order['status'] == 'packed')
    $statusText = 'แพ็คแล้ว';
elseif ($order['status'] == 'shipped')
    $statusText = 'ส่งแล้ว';

include 'includes/header.php';
?>

<div class="flex justify-between items-center mb-4">
    <h2>รายการส่งออก #<?php echo h($order['order_no']); ?></h2>
    <a href="outbound.php" class="btn btn-secondary">กลับไปรายการ</a>
</div>

<div class="card">
    <div class="mb-4">
        <strong>สถานะ:</strong> <span class="badge badge-success"><?php echo $statusText; ?></span>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>สินค้า</th>
                <th>จำนวน</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lines as $line): ?>
                <tr>
                    <td><?php echo h($line['sku'] . ' - ' . $line['product_name']); ?></td>
                    <td><?php echo number_format($line['qty']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include 'includes/footer.php'; ?>