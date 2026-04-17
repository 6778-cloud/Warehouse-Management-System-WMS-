<?php
require_once 'config/db.php';
requireLogin();
requireStaffWarehouse(); // เฉพาะพนักงานคลัง

// Fetch Inbound Orders ready for Putaway (Status = 'received')
$sql = "SELECT i.*, v.name as vendor_name, 
        (SELECT COUNT(*) FROM inbound_lines WHERE inbound_id = i.inbound_id) as item_count,
        (SELECT SUM(received_qty) FROM inbound_lines WHERE inbound_id = i.inbound_id) as total_qty
        FROM inbound_orders i
        LEFT JOIN vendors v ON i.vendor_id = v.vendor_id
        WHERE i.status = 'received'
        ORDER BY i.receive_date ASC";
$orders = $pdo->query($sql)->fetchAll();

include 'includes/header.php';
?>

<div class="flex justify-between items-center mb-4">
    <h2>งานจัดเก็บสินค้า</h2>
</div>

<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>รหัสรับเข้า</th>
                    <th>ผู้จำหน่าย</th>
                    <th>เลขที่ PO</th>
                    <th>วันที่รับ</th>
                    <th>รายการ</th>
                    <th>จำนวนรวม</th>
                    <th class="text-right">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($orders) > 0): ?>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td><span class="badge badge-info text-white">#<?php echo $o['inbound_id']; ?></span></td>
                            <td><?php echo h($o['vendor_name']); ?></td>
                            <td><?php echo h($o['po_no']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($o['receive_date'])); ?></td>
                            <td><?php echo $o['item_count']; ?></td>
                            <td><?php echo number_format($o['total_qty']); ?></td>
                            <td class="text-right">
                                <a href="putaway_process.php?id=<?php echo $o['inbound_id']; ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-dolly"></i> เริ่มจัดเก็บ
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center" style="padding: 2rem; color: var(--text-muted);">
                            ไม่มีงานจัดเก็บที่รอดำเนินการ
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>