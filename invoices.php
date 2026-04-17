<?php
require_once 'config/db.php';
requireLogin();

// Fetch all invoices
$status_filter = $_GET['status'] ?? 'all';
$payment_filter = $_GET['payment'] ?? 'all';

$sql = "SELECT i.*, o.order_no, u.username as created_by_name
        FROM invoices i
        LEFT JOIN outbound_orders o ON i.outbound_id = o.outbound_id
        LEFT JOIN users u ON i.created_by = u.user_id
        WHERE 1=1";

$params = [];

if ($status_filter != 'all') {
    $sql .= " AND i.status = :status";
    $params[':status'] = $status_filter;
}

if ($payment_filter != 'all') {
    $sql .= " AND i.payment_status = :payment";
    $params[':payment'] = $payment_filter;
}

$sql .= " ORDER BY i.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

// Count by status (Count everything not explicitly paid)
$unpaid_count = $pdo->query("SELECT COUNT(*) FROM invoices WHERE payment_status != 'paid' OR payment_status IS NULL")->fetchColumn();

// Sum outstanding (Sum everything not explicitly paid)
$total_unpaid = $pdo->query("SELECT SUM(total_amount - COALESCE(paid_amount, 0)) FROM invoices WHERE payment_status != 'paid' OR payment_status IS NULL")->fetchColumn() ?? 0;

include 'includes/header.php';
?>

<div class="flex justify-between items-center mb-4">
    <div>
        <h2>📄 จัดการใบแจ้งหนี้</h2>
        <p class="text-muted">จัดการใบแจ้งหนี้/ใบเสร็จ</p>
    </div>
    <div class="flex gap-2">
        <span class="badge badge-danger">ค้างชำระ:
            <?php echo $unpaid_count; ?>
        </span>
        <span class="badge badge-warning">ยอดเงินคงค้าง: ฿
            <?php echo number_format($total_unpaid, 2); ?>
        </span>
    </div>
</div>

<!-- Filter -->
<div class="card mb-4">
    <div class="flex gap-2" style="flex-wrap: wrap;">
        <div class="flex gap-2">
            <strong style="padding: 0.25rem 0.5rem;">การชำระ:</strong>
            <a href="?payment=all&status=<?php echo $status_filter; ?>"
                class="btn <?php echo $payment_filter == 'all' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">ทั้งหมด</a>
            <a href="?payment=unpaid&status=<?php echo $status_filter; ?>"
                class="btn <?php echo $payment_filter == 'unpaid' ? 'btn-danger' : 'btn-secondary'; ?> btn-sm">ค้างชำระ</a>
            <a href="?payment=partial&status=<?php echo $status_filter; ?>"
                class="btn <?php echo $payment_filter == 'partial' ? 'btn-warning' : 'btn-secondary'; ?> btn-sm">ชำระบางส่วน</a>
            <a href="?payment=paid&status=<?php echo $status_filter; ?>"
                class="btn <?php echo $payment_filter == 'paid' ? 'btn-success' : 'btn-secondary'; ?> btn-sm">ชำระแล้ว</a>
        </div>

        <div style="border-left: 1px solid var(--border-color); margin: 0 0.5rem;"></div>

        <div class="flex gap-2">
            <strong style="padding: 0.25rem 0.5rem;">สถานะ:</strong>
            <a href="?status=all&payment=<?php echo $payment_filter; ?>"
                class="btn <?php echo $status_filter == 'all' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">ทั้งหมด</a>
            <a href="?status=draft&payment=<?php echo $payment_filter; ?>"
                class="btn <?php echo $status_filter == 'draft' ? 'btn-warning' : 'btn-secondary'; ?> btn-sm">ร่าง</a>
            <a href="?status=issued&payment=<?php echo $payment_filter; ?>"
                class="btn <?php echo $status_filter == 'issued' ? 'btn-info' : 'btn-secondary'; ?> btn-sm">ออกแล้ว</a>
        </div>
    </div>
</div>

<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>เลขที่ใบแจ้งหนี้</th>
                    <th>เลขที่สั่งซื้อ</th>
                    <th>ลูกค้า</th>
                    <th>วันที่ออก</th>
                    <th>กำหนดชำระ</th>
                    <th class="text-right">ยอดรวม</th>
                    <th>การชำระ</th>
                    <th>สถานะ</th>
                    <th class="text-right">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($invoices) > 0): ?>
                    <?php foreach ($invoices as $inv): ?>
                        <?php
                        // Translate payment status
                        $paymentText = $inv['payment_status'];
                        if ($inv['payment_status'] == 'unpaid' || $inv['payment_status'] === null)
                            $paymentText = 'ค้างชำระ';
                        elseif ($inv['payment_status'] == 'partial')
                            $paymentText = 'ชำระบางส่วน';
                        elseif ($inv['payment_status'] == 'paid')
                            $paymentText = 'ชำระแล้ว';

                        // Translate status
                        $statusText = $inv['status'];
                        if ($inv['status'] == 'draft')
                            $statusText = 'ร่าง';
                        elseif ($inv['status'] == 'issued')
                            $statusText = 'ออกแล้ว';
                        ?>
                        <tr>
                            <td><span class="badge badge-info">
                                    <?php echo h($inv['invoice_no']); ?>
                                </span></td>
                            <td>
                                <?php echo h($inv['order_no']); ?>
                            </td>
                            <td>
                                <div style="font-weight: 600;">
                                    <?php echo h($inv['customer_name']); ?>
                                </div>
                                <?php if ($inv['customer_email']): ?>
                                    <div class="text-muted" style="font-size: 0.85rem;">
                                        <?php echo h($inv['customer_email']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo date('d/m/Y', strtotime($inv['issue_date'])); ?>
                            </td>
                            <td>
                                <?php echo $inv['due_date'] ? date('d/m/Y', strtotime($inv['due_date'])) : '-'; ?>
                            </td>
                            <td class="text-right" style="font-weight: 700;">฿
                                <?php echo number_format($inv['total_amount'], 2); ?>
                            </td>
                            <td>
                                <?php
                                $paymentClass = '';
                                switch ($inv['payment_status']) {
                                    case 'partial':
                                        $paymentClass = 'badge-warning';
                                        break;
                                    case 'paid':
                                        $paymentClass = 'badge-success';
                                        break;
                                    case 'unpaid':
                                    case null: // Handle NULL payment_status as unpaid
                                    default:
                                        $paymentClass = 'badge-danger';
                                        break;
                                }
                                ?>
                                <span class="badge <?php echo $paymentClass; ?>">
                                    <?php echo $paymentText; ?>
                                </span>
                            </td>
                            <td>
                                <span
                                    class="badge <?php echo $inv['status'] == 'issued' ? 'badge-success' : 'badge-secondary'; ?>">
                                    <?php echo $statusText; ?>
                                </span>
                            </td>
                            <td class="text-right">
                                <a href="invoice_view.php?id=<?php echo $inv['invoice_id']; ?>"
                                    class="btn btn-secondary btn-sm">
                                    <i class="fas fa-eye"></i> ดู
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center" style="padding: 2rem;">
                            <i class="fas fa-file-invoice-dollar" style="font-size: 3rem; color: var(--text-muted);"></i>
                            <p style="margin-top: 1rem;">ไม่พบใบแจ้งหนี้</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>