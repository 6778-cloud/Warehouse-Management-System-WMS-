<?php
require_once 'config/db.php';
requireLogin();

// Fetch Outbound Orders
$sql = "SELECT o.*, u.username as creator_name,
        (SELECT COUNT(*) FROM outbound_lines WHERE outbound_id = o.outbound_id) as item_count,
        (SELECT SUM(qty) FROM outbound_lines WHERE outbound_id = o.outbound_id) as total_qty
        FROM outbound_orders o
        LEFT JOIN users u ON o.created_by = u.user_id
        ORDER BY o.outbound_id DESC";
$orders = $pdo->query($sql)->fetchAll();

include 'includes/header.php';
?>

<div class="flex justify-between items-center mb-4">
    <h2>รายการส่งสินค้าออก</h2>
    <a href="outbound_form.php" class="btn btn-primary"><i class="fas fa-plus"></i> สร้างรายการ</a>
</div>

<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>รหัส</th>
                    <th>เลขที่</th>
                    <th>ประเภท</th>
                    <th>รายการ</th>
                    <th>จำนวนรวม</th>
                    <th>สถานะ</th>
                    <th>วันที่สร้าง</th>
                    <th class="text-right">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($orders) > 0): ?>
                    <?php foreach ($orders as $o): ?>
                        <?php
                        // Translate order type
                        $typeText = $o['order_type'];
                        if ($o['order_type'] == 'sale')
                            $typeText = 'ขาย';
                        elseif ($o['order_type'] == 'return')
                            $typeText = 'คืนสินค้า';
                        elseif ($o['order_type'] == 'transfer')
                            $typeText = 'โอนย้าย';
                        elseif ($o['order_type'] == 'internal')
                            $typeText = 'ภายใน';

                        // Translate status
                        $statusText = $o['status'];
                        if ($o['status'] == 'draft')
                            $statusText = 'ร่าง';
                        elseif ($o['status'] == 'allocated')
                            $statusText = 'จัดสรรแล้ว';
                        elseif ($o['status'] == 'picked')
                            $statusText = 'หยิบแล้ว';
                        elseif ($o['status'] == 'packed')
                            $statusText = 'แพ็คแล้ว';
                        elseif ($o['status'] == 'shipped')
                            $statusText = 'ส่งแล้ว';
                        ?>
                        <tr>
                            <td><span class="badge badge-info text-white">#<?php echo $o['outbound_id']; ?></span></td>
                            <td><?php echo h($o['order_no']); ?></td>
                            <td><?php echo $typeText; ?></td>
                            <td><?php echo $o['item_count']; ?></td>
                            <td><?php echo number_format($o['total_qty']); ?></td>
                            <td>
                                <?php
                                $statusClass = 'badge-info';
                                if ($o['status'] == 'shipped')
                                    $statusClass = 'badge-success';
                                if ($o['status'] == 'draft')
                                    $statusClass = 'badge-warning';
                                ?>
                                <span class="badge <?php echo $statusClass; ?>">
                                    <?php echo $statusText; ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($o['created_at'])); ?></td>
                            <td class="text-right">
                                <?php if ($o['status'] == 'draft' || $o['status'] == 'allocated'): ?>
                                    <?php if (hasRole(['admin', 'staff'])): ?>
                                        <a href="picking.php?id=<?php echo $o['outbound_id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-box-open"></i> หยิบ
                                        </a>
                                    <?php endif; ?>
                                    <a href="outbound_form.php?id=<?php echo $o['outbound_id']; ?>"
                                        class="btn btn-secondary btn-sm">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                <?php elseif ($o['status'] == 'picked'): ?>
                                    <?php if ($o['order_type'] == 'sale' || $o['order_type'] == 'return'): ?>
                                        <?php if (hasRole(['admin', 'staff'])): ?>
                                            <a href="packing.php?id=<?php echo $o['outbound_id']; ?>" class="btn btn-warning btn-sm">
                                                <i class="fas fa-check-double"></i> แพ็คและตรวจสอบ
                                            </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge badge-success">เสร็จสิ้นอัตโนมัติ (ภายใน)</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a href="outbound_view.php?id=<?php echo $o['outbound_id']; ?>"
                                        class="btn btn-secondary btn-sm">
                                        <i class="fas fa-eye"></i> ดู
                                    </a>
                                <?php endif; ?>
                                <a href="outbound_print.php?id=<?php echo $o['outbound_id']; ?>" target="_blank"
                                    class="btn btn-success btn-sm" title="พิมพ์">
                                    <i class="fas fa-print"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center" style="padding: 2rem; color: var(--text-muted);">
                            ไม่พบรายการส่งสินค้าออก
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>