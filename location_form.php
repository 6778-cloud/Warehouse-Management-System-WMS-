<?php
require_once 'config/db.php';
requireLogin();

$id = $_GET['id'] ?? null;
$location = null;
$error = '';
$success = '';

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM locations WHERE location_id = :id");
    $stmt->execute([':id' => $id]);
    $location = $stmt->fetch();
    if (!$location) {
        die("ไม่พบตำแหน่ง");
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verifyCsrfToken($_POST['csrf_token']);

    $code = trim($_POST['code']);
    $zone = trim($_POST['zone']);
    $shelf = trim($_POST['shelf']);
    $bin = trim($_POST['bin']);
    $capacity = (int) $_POST['capacity'];

    // Auto-generate code if empty: Zone-Shelf-Bin
    if (empty($code)) {
        $code = "$zone-$shelf-$bin";
    }

    try {
        if ($id) {
            $sql = "UPDATE locations SET code=?, zone=?, shelf=?, bin=?, capacity=? WHERE location_id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$code, $zone, $shelf, $bin, $capacity, $id]);
            $success = "อัพเดทตำแหน่งเรียบร้อย";

            // Refresh
            $stmt = $pdo->prepare("SELECT * FROM locations WHERE location_id = :id");
            $stmt->execute([':id' => $id]);
            $location = $stmt->fetch();
        } else {
            $sql = "INSERT INTO locations (code, zone, shelf, bin, capacity) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$code, $zone, $shelf, $bin, $capacity]);
            header("Location: locations.php");
            exit;
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $error = "รหัสตำแหน่งนี้มีอยู่แล้ว";
        } else {
            $error = "เกิดข้อผิดพลาดฐานข้อมูล: " . $e->getMessage();
        }
    }
}

include 'includes/header.php';
?>

<div class="flex justify-between items-center mb-4">
    <h2><?php echo $id ? 'แก้ไขตำแหน่ง' : 'เพิ่มตำแหน่งใหม่'; ?></h2>
    <a href="locations.php" class="btn btn-secondary">กลับไปรายการ</a>
</div>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <?php if ($error): ?>
        <div class="badge badge-danger mb-4" style="display:block; padding: 1rem;"><?php echo h($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="badge badge-success mb-4" style="display:block; padding: 1rem;"><?php echo h($success); ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

        <div class="form-group">
            <label class="form-label">รหัสตำแหน่ง <span style="color:red">*</span></label>
            <input type="text" name="code" class="form-control" value="<?php echo h($location['code'] ?? ''); ?>"
                placeholder="ว่างไว้เพื่อสร้างอัตโนมัติ (โซน-ชั้น-ช่อง)">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label class="form-label">โซน</label>
                <input type="text" name="zone" class="form-control" value="<?php echo h($location['zone'] ?? 'A'); ?>"
                    required>
            </div>
            <div class="form-group">
                <label class="form-label">ชั้น</label>
                <input type="text" name="shelf" class="form-control" value="<?php echo h($location['shelf'] ?? ''); ?>"
                    required>
            </div>
            <div class="form-group">
                <label class="form-label">ช่อง</label>
                <input type="text" name="bin" class="form-control" value="<?php echo h($location['bin'] ?? ''); ?>"
                    required>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">ความจุ (ชิ้น)</label>
            <input type="number" name="capacity" class="form-control"
                value="<?php echo h($location['capacity'] ?? '100'); ?>" required>
        </div>

        <div
            style="margin-top: 2rem; border-top: 1px solid var(--border-color); padding-top: 1.5rem; text-align: right;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> บันทึกตำแหน่ง</button>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>