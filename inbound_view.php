<?php
require_once 'config/db.php';
requireLogin();

$id = $_GET['id'] ?? null;
if (!$id)
    die("รหัสไม่ถูกต้อง");

// Fetch Order
$stmt = $pdo->prepare("SELECT i.*, v.name as vendor_name, u.username 
                       FROM inbound_orders i 
                       LEFT JOIN vendors v ON i.vendor_id = v.vendor_id 
                       LEFT JOIN users u ON i.created_by = u.user_id 
                       WHERE i.inbound_id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order)
    die("ไม่พบรายการ");

// Fetch Lines
$stmt = $pdo->prepare("SELECT l.*, p.sku, p.name as product_name, p.unit 
                       FROM inbound_lines l 
                       JOIN products p ON l.product_id = p.product_id 
                       WHERE l.inbound_id = ?");
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

include 'includes/header.php';
?>

<div class="flex justify-between items-center mb-4">
    <h2>รายการรับเข้า #<?php echo $order['inbound_id']; ?></h2>
    <a href="inbound.php" class="btn btn-secondary">กลับไปรายการ</a>
</div>

<div class="card mb-4">
    <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:1rem;">
        <div>
            <div class="text-muted text-sm">ผู้จำหน่าย</div>
            <div class="font-bold"><?php echo h($order['vendor_name']); ?></div>
        </div>
        <div>
            <div class="text-muted text-sm">เลขที่ PO</div>
            <div class="font-bold"><?php echo h($order['po_no'] ?? '-'); ?></div>
        </div>
        <div>
            <div class="text-muted text-sm">สถานะ</div>
            <span class="badge badge-info"><?php echo $statusText; ?></span>
        </div>
        <div>
            <div class="text-muted text-sm">วันที่</div>
            <div class="font-bold"><?php echo date('d/m/Y', strtotime($order['receive_date'])); ?></div>
        </div>
    </div>
</div>

<div class="card">
    <h4>รายการสินค้า</h4>
    <table class="table">
        <thead>
            <tr>
                <th>สินค้า</th>
                <th>จำนวนที่คาด</th>
                <th>จำนวนที่รับ</th>
                <th>สถานะ</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lines as $line): ?>
                <tr>
                    <td><?php echo h($line['sku'] . ' - ' . $line['product_name']); ?></td>
                    <td><?php echo number_format($line['expected_qty']) . ' ' . $line['unit']; ?></td>
                    <td><?php echo number_format($line['received_qty']) . ' ' . $line['unit']; ?></td>
                    <td>
                        <?php if ($line['received_qty'] >= $line['expected_qty']): ?>
                            <span class="badge badge-success">เสร็จ</span>
                        <?php elseif ($line['received_qty'] > 0): ?>
                            <span class="badge badge-warning">บางส่วน</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">รอ</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include 'includes/footer.php'; ?>