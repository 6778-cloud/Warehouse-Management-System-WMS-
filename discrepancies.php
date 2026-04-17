<?php
require_once 'config/db.php';
requireLogin();

// Fetch Discrepancies
$status_filter = $_GET['status'] ?? 'all';
$sql = "SELECT d.*, 
        i.po_no, 
        p.sku, p.name as product_name,
        u.username as created_by_name,
        r.username as resolved_by_name
        FROM inbound_discrepancies d
        LEFT JOIN inbound_orders i ON d.inbound_id = i.inbound_id
        LEFT JOIN products p ON d.product_id = p.product_id
        LEFT JOIN users u ON d.created_by = u.user_id
        LEFT JOIN users r ON d.resolved_by = r.user_id";

if ($status_filter != 'all') {
    $sql .= " WHERE d.resolution = :status";
}

$sql .= " ORDER BY d.created_at DESC";

$stmt = $pdo->prepare($sql);
if ($status_filter != 'all') {
    $stmt->execute([':status' => $status_filter]);
} else {
    $stmt->execute();
}
$discrepancies = $stmt->fetchAll();

// Count by status
$pending_count = $pdo->query("SELECT COUNT(*) FROM inbound_discrepancies WHERE resolution='pending'")->fetchColumn();

include 'includes/header.php';
?>

<div class="flex justify-between items-center mb-4">
    <div>
        <h2>⚠️ รายการไม่ตรงกับที่สั่ง</h2>
        <p class="text-muted">รายการสินค้าที่รับไม่ตรงกับที่สั่ง</p>
    </div>
    <div>
        <span class="badge badge-<?php echo $pending_count > 0 ? 'danger' : 'success'; ?>" style="font-size: 1rem;">
            รอดำเนินการ:
            <?php echo $pending_count; ?>
        </span>
    </div>
</div>

<!-- Filter -->
<div class="card mb-4">
    <div class="flex gap-2">
        <a href="?status=all"
            class="btn <?php echo $status_filter == 'all' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">
            ทั้งหมด
        </a>
        <a href="?status=pending"
            class="btn <?php echo $status_filter == 'pending' ? 'btn-warning' : 'btn-secondary'; ?> btn-sm">
            รอดำเนินการ
        </a>
        <a href="?status=accepted"
            class="btn <?php echo $status_filter == 'accepted' ? 'btn-success' : 'btn-secondary'; ?> btn-sm">
            ยอมรับแล้ว
        </a>
        <a href="?status=escalated"
            class="btn <?php echo $status_filter == 'escalated' ? 'btn-danger' : 'btn-secondary'; ?> btn-sm">
            ส่งต่อแล้ว
        </a>
    </div>
</div>

<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>รหัส</th>
                    <th>เลขที่ PO</th>
                    <th>สินค้า</th>
                    <th>ประเภทปัญหา</th>
                    <th>คาดว่า</th>
                    <th>รับจริง</th>
                    <th>ส่วนต่าง</th>
                    <th>สถานะ</th>
                    <th>สร้างโดย</th>
                    <th>วันที่สร้าง</th>
                    <th class="text-right">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($discrepancies) > 0): ?>
                    <?php foreach ($discrepancies as $d): ?>
                        <?php
                        // Translate resolution status
                        $resolutionText = $d['resolution'];
                        if ($d['resolution'] == 'pending')
                            $resolutionText = 'รอดำเนินการ';
                        elseif ($d['resolution'] == 'accepted')
                            $resolutionText = 'ยอมรับแล้ว';
                        elseif ($d['resolution'] == 'escalated')
                            $resolutionText = 'ส่งต่อแล้ว';
                        ?>
                        <tr>
                            <td>#
                                <?php echo $d['discrepancy_id']; ?>
                            </td>
                            <td>
                                <?php echo h($d['po_no']); ?>
                            </td>
                            <td>
                                <div style="font-weight: bold;">
                                    <?php echo h($d['sku']); ?>
                                </div>
                                <div class="text-muted" style="font-size: 0.85rem;">
                                    <?php echo h($d['product_name']); ?>
                                </div>
                            </td>
                            <td>
                                <?php
                                $typeClass = '';
                                $typeLabel = '';
                                switch ($d['issue_type']) {
                                    case 'short':
                                        $typeClass = 'badge-warning';
                                        $typeLabel = '📉 ขาด (น้อยกว่า)';
                                        break;
                                    case 'over':
                                        $typeClass = 'badge-info';
                                        $typeLabel = '📈 เกิน (มากกว่า)';
                                        break;
                                    case 'damage':
                                        $typeClass = 'badge-danger';
                                        $typeLabel = '💔 เสียหาย';
                                        break;
                                    default:
                                        $typeClass = 'badge-secondary';
                                        $typeLabel = 'อื่นๆ';
                                }
                                ?>
                                <span class="badge <?php echo $typeClass; ?>">
                                    <?php echo $typeLabel; ?>
                                </span>
                            </td>
                            <td class="text-center font-bold">
                                <?php echo number_format($d['expected_qty']); ?>
                            </td>
                            <td class="text-center font-bold">
                                <?php echo number_format($d['received_qty']); ?>
                            </td>
                            <td class="text-center">
                                <span class="badge <?php echo $d['variance'] < 0 ? 'badge-warning' : 'badge-info'; ?>"
                                    style="font-size: 0.9rem;">
                                    <?php echo ($d['variance'] > 0 ? '+' : '') . $d['variance']; ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $statusClass = '';
                                switch ($d['resolution']) {
                                    case 'pending':
                                        $statusClass = 'badge-warning';
                                        break;
                                    case 'accepted':
                                        $statusClass = 'badge-success';
                                        break;
                                    case 'escalated':
                                        $statusClass = 'badge-danger';
                                        break;
                                }
                                ?>
                                <span class="badge <?php echo $statusClass; ?>">
                                    <?php echo $resolutionText; ?>
                                </span>
                            </td>
                            <td>
                                <?php echo h($d['created_by_name']); ?>
                            </td>
                            <td>
                                <?php echo date('d/m/Y H:i', strtotime($d['created_at'])); ?>
                            </td>
                            <td class="text-right">
                                <?php if ($d['resolution'] == 'pending'): ?>
                                    <button class="btn btn-success btn-sm"
                                        onclick="resolveDiscrepancy(<?php echo $d['discrepancy_id']; ?>, 'accepted')">
                                        <i class="fas fa-check"></i> ยอมรับ
                                    </button>
                                    <button class="btn btn-danger btn-sm"
                                        onclick="resolveDiscrepancy(<?php echo $d['discrepancy_id']; ?>, 'escalated')">
                                        <i class="fas fa-exclamation-triangle"></i> ส่งต่อ
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted">ดำเนินการแล้ว</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="11" class="text-center" style="padding: 2rem;">
                            <i class="fas fa-check-circle" style="font-size: 3rem; color: var(--success-color);"></i>
                            <p style="margin-top: 1rem; color: var(--text-muted);">
                                ไม่มีรายการที่มีปัญหา
                            </p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    function resolveDiscrepancy(id, action) {
        const actionText = action === 'accepted' ? 'ยอมรับ' : 'ส่งต่อ';
        if (!confirm('ยืนยันจะ' + actionText + 'รายการนี้?')) return;

        fetch('discrepancy_resolve.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'discrepancy_id=' + id + '&action=' + action + '&csrf_token=<?php echo generateCsrfToken(); ?>'
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('เกิดข้อผิดพลาด: ' + data.message);
                }
            });
    }
</script>

<?php include 'includes/footer.php'; ?>