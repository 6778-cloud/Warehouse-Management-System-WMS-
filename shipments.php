<?php
require_once 'config/db.php';
requireLogin();

// แสดง 2 รายการ:
// 1. Outbound orders ที่ shipped แล้ว แต่ยังไม่มี shipment record (รอคีย์)
// 2. Shipment records ที่คีย์เสร็จแล้ว

// Fetch outbound orders ที่ shipped แต่ยังไม่มี shipment (รอคีย์)
$pending_shipments = $pdo->query("
    SELECT o.* 
    FROM outbound_orders o
    LEFT JOIN shipments s ON o.outbound_id = s.outbound_id
    WHERE o.status = 'shipped' 
      AND o.order_type = 'sale'
      AND s.shipment_id IS NULL
    ORDER BY o.created_at DESC
")->fetchAll();

// Fetch completed shipments
$completed_shipments = $pdo->query("
    SELECT s.*, o.order_no, u.username as created_by_name
    FROM shipments s
    LEFT JOIN outbound_orders o ON s.outbound_id = o.outbound_id
    LEFT JOIN users u ON s.created_by = u.user_id
    ORDER BY s.created_at DESC
")->fetchAll();

include 'includes/header.php';
?>

<div class="flex justify-between items-center mb-4">
    <div>
        <h2>📦 จัดการการจัดส่ง</h2>
        <p class="text-muted">จัดการข้อมูลการจัดส่งสินค้า</p>
    </div>
    <div class="flex gap-2">
        <span class="badge badge-warning">รอดำเนินการ: <?php echo count($pending_shipments); ?></span>
        <span class="badge badge-success">เสร็จสิ้น: <?php echo count($completed_shipments); ?></span>
    </div>
</div>

<!-- รายการรอคีย์ Shipment -->
<?php if (count($pending_shipments) > 0): ?>
    <div class="card mb-4" style="border-left: 4px solid var(--warning-color);">
        <h3 class="mb-3" style="color: var(--warning-color);">
            <i class="fas fa-clock"></i> รอคีย์ข้อมูลจัดส่ง
        </h3>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>เลขที่</th>
                        <th>ประเภท</th>
                        <th>วันที่ส่ง</th>
                        <th class="text-right">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_shipments as $order): ?>
                        <?php
                        $typeText = $order['order_type'];
                        if ($order['order_type'] == 'sale')
                            $typeText = 'ขาย';
                        elseif ($order['order_type'] == 'return')
                            $typeText = 'คืนสินค้า';
                        elseif ($order['order_type'] == 'internal')
                            $typeText = 'ภายใน';
                        ?>
                        <tr>
                            <td><span class="badge badge-warning"><?php echo h($order['order_no']); ?></span></td>
                            <td><?php echo $typeText; ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                            <td class="text-right">
                                <a href="shipment_form.php?outbound_id=<?php echo $order['outbound_id']; ?>"
                                    class="btn btn-primary btn-sm">
                                    <i class="fas fa-edit"></i> กรอกข้อมูลจัดส่ง
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- รายการคีย์เสร็จแล้ว -->
<div class="card">
    <h3 class="mb-3">
        <i class="fas fa-check-circle"></i> การจัดส่งเสร็จสิ้น
    </h3>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>รหัส</th>
                    <th>เลขที่</th>
                    <th>ผู้รับ</th>
                    <th>ที่อยู่จัดส่ง</th>
                    <th>วันที่สร้าง</th>
                    <th class="text-right">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($completed_shipments) > 0): ?>
                    <?php foreach ($completed_shipments as $s): ?>
                        <tr>
                            <td>#<?php echo $s['shipment_id']; ?></td>
                            <td><span class="badge badge-info"><?php echo h($s['order_no']); ?></span></td>
                            <td>
                                <div style="font-weight: 600;"><?php echo h($s['receiver_name']); ?></div>
                                <?php if ($s['receiver_phone']): ?>
                                    <div class="text-muted" style="font-size: 0.85rem;"><?php echo h($s['receiver_phone']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="max-width: 300px;">
                                <div style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <?php echo h($s['delivery_address']); ?>
                                </div>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($s['created_at'])); ?></td>
                            <td class="text-right">
                                <a href="shipment_view.php?id=<?php echo $s['shipment_id']; ?>"
                                    class="btn btn-secondary btn-sm">
                                    <i class="fas fa-eye"></i> ดู
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center" style="padding: 2rem;">
                            <i class="fas fa-truck" style="font-size: 3rem; color: var(--text-muted);"></i>
                            <p style="margin-top: 1rem;">ยังไม่มีการจัดส่งที่เสร็จสิ้น</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>