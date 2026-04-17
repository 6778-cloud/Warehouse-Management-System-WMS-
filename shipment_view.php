<?php
require_once 'config/db.php';
requireLogin();

$id = $_GET['id'] ?? null;
if (!$id)
    die("รหัสไม่ถูกต้อง");

// Fetch shipment
$stmt = $pdo->prepare("SELECT s.*, o.order_no, u.username as created_by_name
                       FROM shipments s
                       LEFT JOIN outbound_orders o ON s.outbound_id = o.outbound_id
                       LEFT JOIN users u ON s.created_by = u.user_id
                       WHERE s.shipment_id = ?");
$stmt->execute([$id]);
$shipment = $stmt->fetch();

if (!$shipment)
    die("ไม่พบข้อมูลการจัดส่ง");

include 'includes/header.php';
?>

<div class="flex justify-between items-center mb-4">
    <h2>รายละเอียดการจัดส่ง #<?php echo $shipment['shipment_id']; ?></h2>
    <a href="shipments.php" class="btn btn-secondary">กลับไปรายการ</a>
</div>

<div class="card">
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
        <!-- Left: Order Info -->
        <div>
            <h3 class="mb-4">ข้อมูลคำสั่งซื้อ</h3>
            <table style="width: 100%;">
                <tr>
                    <td style="padding: 0.5rem 0; color: var(--text-muted);">เลขที่:</td>
                    <td style="padding: 0.5rem 0; font-weight: 600;"><?php echo h($shipment['order_no']); ?></td>
                </tr>
                <tr>
                    <td style="padding: 0.5rem 0; color: var(--text-muted);">สร้างโดย:</td>
                    <td style="padding: 0.5rem 0;"><?php echo h($shipment['created_by_name']); ?></td>
                </tr>
                <tr>
                    <td style="padding: 0.5rem 0; color: var(--text-muted);">วันที่สร้าง:</td>
                    <td style="padding: 0.5rem 0;"><?php echo date('d/m/Y H:i', strtotime($shipment['created_at'])); ?>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Right: Receiver Info -->
        <div>
            <h3 class="mb-4">ข้อมูลผู้รับ</h3>
            <table style="width: 100%;">
                <tr>
                    <td style="padding: 0.5rem 0; color: var(--text-muted);">ชื่อ:</td>
                    <td style="padding: 0.5rem 0; font-weight: 600;"><?php echo h($shipment['receiver_name']); ?></td>
                </tr>
                <tr>
                    <td style="padding: 0.5rem 0; color: var(--text-muted);">โทรศัพท์:</td>
                    <td style="padding: 0.5rem 0;"><?php echo h($shipment['receiver_phone'] ?? '-'); ?></td>
                </tr>
                <tr>
                    <td style="padding: 0.5rem 0; color: var(--text-muted);">อีเมล:</td>
                    <td style="padding: 0.5rem 0;"><?php echo h($shipment['receiver_email'] ?? '-'); ?></td>
                </tr>
                <tr>
                    <td style="padding: 0.5rem 0; color: var(--text-muted); vertical-align: top;">ที่อยู่:</td>
                    <td style="padding: 0.5rem 0;"><?php echo nl2br(h($shipment['delivery_address'])); ?></td>
                </tr>
            </table>
        </div>
    </div>

    <?php if ($shipment['notes']): ?>
        <div
            style="margin-top: 2rem; padding: 1rem; background: rgba(251,191,36,0.1); border-left: 4px solid var(--warning-color); border-radius: 4px;">
            <strong>หมายเหตุ:</strong><br>
            <?php echo nl2br(h($shipment['notes'])); ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>