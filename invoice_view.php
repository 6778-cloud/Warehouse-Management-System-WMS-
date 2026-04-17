<?php
require_once 'config/db.php';
requireLogin();

$id = $_GET['id'] ?? null;
if (!$id)
    die("รหัสไม่ถูกต้อง");

// Fetch invoice
$stmt = $pdo->prepare("SELECT i.*, o.order_no FROM invoices i
                       LEFT JOIN outbound_orders o ON i.outbound_id = o.outbound_id
                       WHERE i.invoice_id = ?");
$stmt->execute([$id]);
$invoice = $stmt->fetch();

if (!$invoice)
    die("ไม่พบใบแจ้งหนี้");

// Fetch invoice lines
$stmt = $pdo->prepare("SELECT * FROM invoice_lines WHERE invoice_id = ?");
$stmt->execute([$id]);
$lines = $stmt->fetchAll();

// Handle mark as paid
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_paid'])) {
    verifyCsrfToken($_POST['csrf_token']);

    $pdo->prepare("UPDATE invoices SET payment_status = 'paid', paid_amount = total_amount WHERE invoice_id = ?")->execute([$id]);
    header("Location: invoice_view.php?id=$id");
    exit;
}

// Handle issue invoice
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['issue_invoice'])) {
    verifyCsrfToken($_POST['csrf_token']);

    $pdo->prepare("UPDATE invoices SET status = 'issued' WHERE invoice_id = ?")->execute([$id]);
    header("Location: invoice_view.php?id=$id");
    exit;
}

include 'includes/header.php';

// Translate payment status
$paymentStatusText = $invoice['payment_status'];
if ($invoice['payment_status'] == 'unpaid' || $invoice['payment_status'] === null)
    $paymentStatusText = 'ค้างชำระ';
elseif ($invoice['payment_status'] == 'partial')
    $paymentStatusText = 'ชำระบางส่วน';
elseif ($invoice['payment_status'] == 'paid')
    $paymentStatusText = 'ชำระแล้ว';
?>

<style>
    @media print {
        body * {
            visibility: hidden;
        }

        #invoice-print,
        #invoice-print * {
            visibility: visible;
        }

        #invoice-print {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            margin: 0 !important;
            padding: 2rem !important;
            box-shadow: none !important;
            border: none !important;
        }

        .no-print {
            display: none !important;
        }
    }
</style>

<div class="flex justify-between items-center mb-4 no-print">
    <h2>ใบแจ้งหนี้ #
        <?php echo h($invoice['invoice_no']); ?>
    </h2>
    <div class="flex gap-2">
        <button onclick="window.print()" class="btn btn-secondary">
            <i class="fas fa-print"></i> พิมพ์
        </button>
        <a href="invoices.php" class="btn btn-secondary">กลับไปรายการ</a>
    </div>
</div>

<!-- Actions no-print -->
<?php if ($invoice['status'] == 'draft'): ?>
    <div class="card no-print">
        <form method="post" style="display: inline;">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <button type="submit" name="issue_invoice" class="btn btn-primary">
                <i class="fas fa-paper-plane"></i> ออกใบแจ้งหนี้
            </button>
        </form>
    </div>
<?php endif; ?>

<?php if ($invoice['payment_status'] != 'paid'): ?>
    <div class="card no-print">
        <form method="post" style="display: inline;">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <button type="submit" name="mark_paid" class="btn btn-success"
                onclick="return confirm('ทำเครื่องหมายว่าชำระแล้ว?')">
                <i class="fas fa-check-circle"></i> ทำเครื่องหมายว่าชำระแล้ว
            </button>
        </form>
    </div>
<?php endif; ?>

<!-- Invoice Printable -->
<div id="invoice-print" class="card"
    style="max-width: 900px; margin: 0 auto; padding: 3rem; background: white; color: #1f2937;">
    <!-- Header -->
    <div
        style="display: flex; justify-content: space-between; margin-bottom: 3rem; padding-bottom: 2rem; border-bottom: 3px solid #4ade80;">
        <div>
            <h1 style="font-size: 2rem; color: #4ade80; margin-bottom: 0.5rem;">ใบแจ้งหนี้/ใบกำกับภาษี</h1>
            <h2 style="font-size: 1.5rem; color: #1f2937;">WMS Smart Co., Ltd.</h2>
            <p style="color: #6b7280;">123 ถนนธุรกิจ กรุงเทพฯ 10110<br>
                โทร: 02-123-4567 | อีเมล: info@wmssmart.com<br>
                เลขประจำตัวผู้เสียภาษี: 0123456789012</p>
        </div>
        <div style="text-align: right;">
            <div
                style="background: <?php echo $invoice['payment_status'] == 'paid' ? '#4ade80' : '#ef4444'; ?>; 
                        color: white; padding: 0.5rem 1rem; border-radius: 4px; font-weight: 700; margin-bottom: 1rem;">
                <?php echo strtoupper($paymentStatusText); ?>
            </div>
            <p style="font-size: 0.9rem; color: #6b7280;">
                <strong>เลขที่:</strong>
                <?php echo h($invoice['invoice_no']); ?><br>
                <strong>เลขที่สั่งซื้อ:</strong>
                <?php echo h($invoice['order_no']); ?><br>
                <strong>วันที่:</strong>
                <?php echo date('d/m/Y', strtotime($invoice['issue_date'])); ?><br>
                <?php if ($invoice['due_date']): ?>
                    <strong>กำหนดชำระ:</strong>
                    <?php echo date('d/m/Y', strtotime($invoice['due_date'])); ?>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <!-- Bill To -->
    <div style="margin-bottom: 2rem;">
        <h3 style="color: #1f2937; margin-bottom: 1rem;">ลูกค้า:</h3>
        <div style="padding: 1rem; background: #f9fafb; border-left: 4px solid #4ade80; border-radius: 4px;">
            <p style="font-weight: 700; font-size: 1.1rem; color: #1f2937; margin-bottom: 0.5rem;">
                <?php echo h($invoice['customer_name']); ?>
            </p>
            <?php if ($invoice['customer_tax_id']): ?>
                <p style="color: #6b7280;">เลขประจำตัวผู้เสียภาษี:
                    <?php echo h($invoice['customer_tax_id']); ?>
                </p>
            <?php endif; ?>
            <?php if ($invoice['customer_address']): ?>
                <p style="color: #6b7280;">
                    <?php echo nl2br(h($invoice['customer_address'])); ?>
                </p>
            <?php endif; ?>
            <?php if ($invoice['customer_phone']): ?>
                <p style="color: #6b7280;">โทร:
                    <?php echo h($invoice['customer_phone']); ?>
                </p>
            <?php endif; ?>
            <?php if ($invoice['customer_email']): ?>
                <p style="color: #6b7280;">อีเมล:
                    <?php echo h($invoice['customer_email']); ?>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Items Table -->
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 2rem;">
        <thead>
            <tr style="background: #1f2937; color: white;">
                <th style="padding: 1rem; text-align: left; border: 1px solid #374151;">#</th>
                <th style="padding: 1rem; text-align: left; border: 1px solid #374151;">รายละเอียด</th>
                <th style="padding: 1rem; text-align: center; border: 1px solid #374151;">จำนวน</th>
                <th style="padding: 1rem; text-align: right; border: 1px solid #374151;">ราคาต่อหน่วย</th>
                <th style="padding: 1rem; text-align: right; border: 1px solid #374151;">รวม</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1;
            foreach ($lines as $line): ?>
                <tr>
                    <td style="padding: 0.75rem; border: 1px solid #e5e7eb;">
                        <?php echo $no++; ?>
                    </td>
                    <td style="padding: 0.75rem; border: 1px solid #e5e7eb;">
                        <?php echo h($line['description']); ?>
                    </td>
                    <td style="padding: 0.75rem; text-align: center; border: 1px solid #e5e7eb;">
                        <?php echo number_format($line['qty']); ?>
                    </td>
                    <td style="padding: 0.75rem; text-align: right; border: 1px solid #e5e7eb;">฿
                        <?php echo number_format($line['unit_price'], 2); ?>
                    </td>
                    <td style="padding: 0.75rem; text-align: right; font-weight: 600; border: 1px solid #e5e7eb;">฿
                        <?php echo number_format($line['line_total'], 2); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Totals -->
    <div style="margin-left: auto; max-width: 400px;">
        <table style="width: 100%; font-size: 1rem;">
            <tr>
                <td style="padding: 0.5rem 1rem; text-align: right; color: #6b7280;">ยอดรวม:</td>
                <td style="padding: 0.5rem 1rem; text-align: right; font-weight: 600;">฿
                    <?php echo number_format($invoice['subtotal'], 2); ?>
                </td>
            </tr>
            <?php if ($invoice['discount_amount'] > 0): ?>
                <tr>
                    <td style="padding: 0.5rem 1rem; text-align: right; color: #6b7280;">ส่วนลด:</td>
                    <td style="padding: 0.5rem 1rem; text-align: right; color: #ef4444;">-฿
                        <?php echo number_format($invoice['discount_amount'], 2); ?>
                    </td>
                </tr>
            <?php endif; ?>
            <tr>
                <td style="padding: 0.5rem 1rem; text-align: right; color: #6b7280;">ภาษี (
                    <?php echo $invoice['tax_rate']; ?>%):
                </td>
                <td style="padding: 0.5rem 1rem; text-align: right; font-weight: 600;">฿
                    <?php echo number_format($invoice['tax_amount'], 2); ?>
                </td>
            </tr>
            <tr style="background: #4ade80; color: white; font-size: 1.25rem; font-weight: 700;">
                <td style="padding: 1rem; text-align: right;">ยอดสุทธิ:</td>
                <td style="padding: 1rem; text-align: right;">฿
                    <?php echo number_format($invoice['total_amount'], 2); ?>
                </td>
            </tr>
        </table>
    </div>

    <?php if ($invoice['notes']): ?>
        <div
            style="margin-top: 2rem; padding: 1rem; background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 4px;">
            <strong>หมายเหตุ:</strong><br>
            <?php echo nl2br(h($invoice['notes'])); ?>
        </div>
    <?php endif; ?>

    <!-- Footer -->
    <div
        style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid #e5e7eb; text-align: center; color: #6b7280; font-size: 0.9rem;">
        <p>ขอบคุณที่ใช้บริการ!</p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>