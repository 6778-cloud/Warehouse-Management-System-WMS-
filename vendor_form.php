<?php
require_once 'config/db.php';
requireLogin();

$id = $_GET['id'] ?? null;
$vendor = null;

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM vendors WHERE vendor_id = ?");
    $stmt->execute([$id]);
    $vendor = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verifyCsrfToken($_POST['csrf_token']);
    $name = trim($_POST['name']);
    $contact = trim($_POST['contact']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);

    if ($id) {
        $stmt = $pdo->prepare("UPDATE vendors SET name=?, contact=?, phone=?, email=?, address=? WHERE vendor_id=?");
        $stmt->execute([$name, $contact, $phone, $email, $address, $id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO vendors (name, contact, phone, email, address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $contact, $phone, $email, $address]);
    }
    header("Location: vendors.php");
    exit;
}

include 'includes/header.php';
?>

<div class="flex justify-between items-center mb-4">
    <h2><?php echo $id ? 'แก้ไขผู้จำหน่าย' : 'เพิ่มผู้จำหน่าย'; ?></h2>
    <a href="vendors.php" class="btn btn-secondary">กลับ</a>
</div>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
        <div class="form-group">
            <label class="form-label">ชื่อผู้จำหน่าย <span style="color:red">*</span></label>
            <input type="text" name="name" class="form-control" value="<?php echo h($vendor['name'] ?? ''); ?>"
                required>
        </div>
        <div class="flex gap-2">
            <div class="form-group" style="flex:1">
                <label class="form-label">ผู้ติดต่อ</label>
                <input type="text" name="contact" class="form-control"
                    value="<?php echo h($vendor['contact'] ?? ''); ?>">
            </div>
            <div class="form-group" style="flex:1">
                <label class="form-label">โทรศัพท์</label>
                <input type="text" name="phone" class="form-control" value="<?php echo h($vendor['phone'] ?? ''); ?>">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">อีเมล</label>
            <input type="email" name="email" class="form-control" value="<?php echo h($vendor['email'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label class="form-label">ที่อยู่</label>
            <textarea name="address" class="form-control" rows="3"><?php echo h($vendor['address'] ?? ''); ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary">บันทึกผู้จำหน่าย</button>
    </form>
</div>

<?php include 'includes/footer.php'; ?>