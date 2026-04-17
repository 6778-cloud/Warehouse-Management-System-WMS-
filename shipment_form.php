<?php
require_once 'config/db.php';
requireLogin();
requireOffice(); // พนักงานออฟฟิศคีย์ข้อมูล

$outbound_id = $_GET['outbound_id'] ?? null;
if (!$outbound_id)
    die("รหัสไม่ถูกต้อง");

// Fetch outbound order
$stmt = $pdo->prepare("SELECT * FROM outbound_orders WHERE outbound_id = ? AND status = 'shipped'");
$stmt->execute([$outbound_id]);
$order = $stmt->fetch();

if (!$order)
    die("ไม่พบรายการหรือยังไม่ได้จัดส่ง");

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verifyCsrfToken($_POST['csrf_token']);

    $receiver_name = trim($_POST['receiver_name']);
    $receiver_phone = trim($_POST['receiver_phone']);
    $receiver_email = trim($_POST['receiver_email']);
    $delivery_address = trim($_POST['delivery_address']);
    $notes = trim($_POST['notes']);

    if (empty($receiver_name) || empty($delivery_address)) {
        $error = "กรุณาใส่ชื่อผู้รับและที่อยู่จัดส่ง";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO shipments 
                (outbound_id, receiver_name, receiver_phone, receiver_email, delivery_address, notes, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");

            $stmt->execute([
                $outbound_id,
                $receiver_name,
                $receiver_phone,
                $receiver_email,
                $delivery_address,
                $notes,
                $_SESSION['user_id']
            ]);

            // Redirect ไปสร้าง Invoice ทันที
            header("Location: invoice_form.php?outbound_id=$outbound_id");
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = "มีข้อมูลการจัดส่งสำหรับรายการนี้แล้ว";
            } else {
                $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="flex justify-between items-center mb-4">
    <h2>📦 บันทึกการจัดส่งสำหรับ #<?php echo $order['order_no']; ?></h2>
    <a href="shipments.php" class="btn btn-secondary">ยกเลิก</a>
</div>

<div class="card">
    <?php if ($error): ?>
        <div class="badge badge-danger mb-4" style="display:block; padding: 1rem;"><?php echo h($error); ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

        <h3 class="mb-4">ข้อมูลผู้รับ</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="form-group">
                <label class="form-label">ชื่อผู้รับ <span style="color:red">*</span></label>
                <input type="text" name="receiver_name" class="form-control" required>
            </div>

            <div class="form-group">
                <label class="form-label">เบอร์โทรศัพท์</label>
                <input type="text" name="receiver_phone" class="form-control">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">อีเมล</label>
            <input type="email" name="receiver_email" class="form-control">
        </div>

        <div class="form-group">
            <label class="form-label">ที่อยู่จัดส่ง <span style="color:red">*</span></label>
            <textarea name="delivery_address" class="form-control" rows="3" required></textarea>
        </div>

        <div class="form-group">
            <label class="form-label">หมายเหตุ</label>
            <textarea name="notes" class="form-control" rows="2"
                placeholder="หมายเหตุเพิ่มเติมเกี่ยวกับการจัดส่ง"></textarea>
        </div>

        <div class="mt-4 text-right"
            style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> บันทึกข้อมูลการจัดส่ง
            </button>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>