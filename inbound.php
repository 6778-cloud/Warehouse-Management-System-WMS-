<?php
require_once 'config/db.php';
requireLogin();

$sql = "SELECT i.*, v.name as vendor_name, u.username as creator_name,
        (SELECT COUNT(*) FROM inbound_lines WHERE inbound_id = i.inbound_id) as item_count,
        (SELECT SUM(expected_qty) FROM inbound_lines WHERE inbound_id = i.inbound_id) as total_qty
        FROM inbound_orders i
        LEFT JOIN vendors v ON i.vendor_id = v.vendor_id
        LEFT JOIN users u ON i.created_by = u.user_id
        ORDER BY i.inbound_id DESC";
$inbounds = $pdo->query($sql)->fetchAll();

include 'includes/header.php';
?>

<div class="flex justify-between items-center mb-4">
    <h2>รายการรับสินค้าเข้า</h2>
    <a href="inbound_form.php" class="btn btn-primary"><i class="fas fa-plus"></i> สร้างรายการรับเข้า</a>
</div>

<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>รหัส</th>
                    <th>เลขที่ PO</th>
                    <th>ผู้จำหน่าย</th>
                    <th>รายการ</th>
                    <th>จำนวนรวม</th>
                    <th>สร้างโดย</th>
                    <th>สถานะ</th>
                    <th>วันที่</th>
                    <th class="text-right">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($inbounds as $o): ?>
                    <tr>
                        <td>#<?php echo $o['inbound_id']; ?></td>
                        <td><?php echo h($o['po_no']); ?></td>
                        <td><?php echo h($o['vendor_name']); ?></td>
                        <td><?php echo $o['item_count']; ?></td>
                        <td><?php echo number_format($o['total_qty']); ?></td>
                        <td><?php echo h($o['creator_name']); ?></td>
                        <td>
                            <?php
                            $statusText = $o['status'];
                            if ($o['status'] == 'completed')
                                $statusText = 'เสร็จสิ้น';
                            elseif ($o['status'] == 'draft')
                                $statusText = 'ร่าง';
                            elseif ($o['status'] == 'partial')
                                $statusText = 'รับบางส่วน';
                            elseif ($o['status'] == 'received')
                                $statusText = 'รับแล้ว';
                            ?>
                            <span
                                class="badge badge-<?php echo $o['status'] == 'completed' ? 'success' : ($o['status'] == 'draft' ? 'warning' : ($o['status'] == 'partial' ? 'primary' : 'info')); ?>">
                                <?php echo $statusText; ?>
                            </span>
                        </td>
                        <td><?php echo date('d/m/Y', strtotime($o['created_at'])); ?></td>
                        <td class="text-right">
                            <?php if ($o['status'] == 'draft' || $o['status'] == 'partial'): ?>
                                <?php if (hasRole(['admin', 'staff'])): ?>
                                    <a href="receive.php?id=<?php echo $o['inbound_id']; ?>"
                                        class="btn btn-primary btn-sm">รับสินค้า</a>
                                <?php endif; ?>
                                <a href="inbound_form.php?id=<?php echo $o['inbound_id']; ?>"
                                    class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></a>
                            <?php else: ?>
                                <a href="inbound_view.php?id=<?php echo $o['inbound_id']; ?>"
                                    class="btn btn-secondary btn-sm"><i class="fas fa-eye"></i></a>
                            <?php endif; ?>
                            <a href="inbound_print.php?id=<?php echo $o['inbound_id']; ?>" target="_blank"
                                class="btn btn-success btn-sm" title="พิมพ์">
                                <i class="fas fa-print"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>