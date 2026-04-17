<?php
require_once 'config/db.php';
requireLogin();

$id = $_GET['id'] ?? null;
$product = null;
$error = '';
$success = '';

// Fetch Categories for dropdown
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
// Fetch Vendors
$vendors = $pdo->query("SELECT * FROM vendors ORDER BY name")->fetchAll();

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE product_id = :id");
    $stmt->execute([':id' => $id]);
    $product = $stmt->fetch();
    if (!$product) {
        die("ไม่พบสินค้า");
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verifyCsrfToken($_POST['csrf_token']);

    // Capture SKU from POST first
    $sku = trim($_POST['sku'] ?? '');

    // Auto-generate SKU if empty (New Product ONLY)
    if (empty($sku) && !$id) {
        // Find max ID to guess next SKU or just use Random/Timestamp
        $sku = 'PROD-' . strtoupper(substr(uniqid(), -6));
    }

    $name = trim($_POST['name']);
    $description = trim($_POST['description']);

    // Category Logic: Check if it's a new category (text input) or existing (select)
    $category_id = null;
    $new_category_name = trim($_POST['new_category'] ?? '');

    if (!empty($new_category_name)) {
        // Create new category
        // Check if exists first to avoid dupes
        $checkCat = $pdo->prepare("SELECT category_id FROM categories WHERE name = ?");
        $checkCat->execute([$new_category_name]);
        $existingCat = $checkCat->fetchColumn();

        if ($existingCat) {
            $category_id = $existingCat;
        } else {
            $stmtCat = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmtCat->execute([$new_category_name]);
            $category_id = $pdo->lastInsertId();
        }
    } else {
        $category_id = !empty($_POST['category_id']) ? $_POST['category_id'] : null;
    }

    $vendor_id = !empty($_POST['vendor_id']) ? $_POST['vendor_id'] : null;
    $unit = $_POST['unit'];
    $min_stock = (int) $_POST['min_stock'];
    $barcode = trim($_POST['barcode']);
    $cost_price = floatval($_POST['cost_price'] ?? 0);
    $selling_price = floatval($_POST['selling_price'] ?? 0);

    // Image Upload
    $image_path = $product['image_path'] ?? null;

    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            if ($_FILES['image']['size'] <= 2 * 1024 * 1024) { // 2MB
                $new_name = uniqid() . "." . $ext;
                $dest = "uploads/" . $new_name;
                if (!is_dir('uploads'))
                    mkdir('uploads'); // Ensure dir exists
                if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                    $image_path = $dest;
                } else {
                    $error = "ไม่สามารถย้ายไฟล์ที่อัพโหลดได้";
                }
            } else {
                $error = "ไฟล์มีขนาดใหญ่เกินไป (สูงสุด 2MB)";
            }
        } else {
            $error = "ประเภทไฟล์ไม่ถูกต้อง รองรับเฉพาะ JPG, PNG, WEBP";
        }
    }

    if (!$error) {
        try {
            if ($id) {
                // Update
                $sql = "UPDATE products SET sku=?, name=?, description=?, category_id=?, vendor_id=?, unit=?, min_stock=?, barcode=?, image_path=?, cost_price=?, selling_price=? WHERE product_id=?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$sku, $name, $description, $category_id, $vendor_id, $unit, $min_stock, $barcode, $image_path, $cost_price, $selling_price, $id]);

                // Simple Log
                $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details) VALUES (?, ?, 'product', ?, ?)");
                $log->execute([$_SESSION['user_id'], 'update', $id, "อัพเดทสินค้า $sku"]);

                $success = "อัพเดทสินค้าเรียบร้อยแล้ว";
                // Refresh data
                $stmt = $pdo->prepare("SELECT * FROM products WHERE product_id = :id");
                $stmt->execute([':id' => $id]);
                $product = $stmt->fetch();

            } else {
                // Insert
                $sql = "INSERT INTO products (sku, name, description, category_id, vendor_id, unit, min_stock, barcode, image_path, cost_price, selling_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$sku, $name, $description, $category_id, $vendor_id, $unit, $min_stock, $barcode, $image_path, $cost_price, $selling_price]);
                $new_id = $pdo->lastInsertId();

                $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details) VALUES (?, ?, 'product', ?, ?)");
                $log->execute([$_SESSION['user_id'], 'create', $new_id, "สร้างสินค้า $sku"]);

                header("Location: products.php");
                exit;
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Duplicate entry
                $error = "รหัสสินค้าหรือบาร์โค้ดซ้ำ (ลองสร้างรหัสใหม่)";
            } else {
                $error = "เกิดข้อผิดพลาดฐานข้อมูล: " . $e->getMessage();
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="flex justify-between items-center mb-4">
    <h2><?php echo $id ? 'แก้ไขสินค้า' : 'เพิ่มสินค้าใหม่'; ?></h2>
    <a href="products.php" class="btn btn-secondary">กลับไปรายการ</a>
</div>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <?php if ($error): ?>
        <div class="badge badge-danger mb-4" style="display:block; padding: 1rem;"><?php echo h($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="badge badge-success mb-4" style="display:block; padding: 1rem;"><?php echo h($success); ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="form-group">
                <label class="form-label">รหัสสินค้า (ว่างไว้เพื่อสร้างอัตโนมัติ)</label>
                <div class="flex gap-2">
                    <input type="text" name="sku" class="form-control" value="<?php echo h($product['sku'] ?? ''); ?>"
                        placeholder="สร้างอัตโนมัติถ้าว่าง">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">บาร์โค้ด (สแกนหรือพิมพ์)</label>
                <div class="flex gap-2">
                    <input type="text" name="barcode" id="barcodeInput" class="form-control"
                        value="<?php echo h($product['barcode'] ?? ''); ?>">
                    <button type="button" class="btn btn-secondary" onclick="generateBarcode()"><i
                            class="fas fa-magic"></i></button>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">ชื่อสินค้า <span style="color:red">*</span></label>
            <input type="text" name="name" class="form-control" value="<?php echo h($product['name'] ?? ''); ?>"
                required>
        </div>

        <div class="form-group">
            <label class="form-label">รายละเอียด</label>
            <textarea name="description" class="form-control"
                rows="3"><?php echo h($product['description'] ?? ''); ?></textarea>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem;">
            <div class="form-group">
                <label class="form-label">หมวดหมู่</label>
                <select name="category_id" class="form-control" id="catSelect" onchange="toggleCategoryInput(this)">
                    <option value="">-- เลือก --</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?php echo $c['category_id']; ?>" <?php echo ($product['category_id'] ?? '') == $c['category_id'] ? 'selected' : ''; ?>>
                            <?php echo h($c['name']); ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="new">+ สร้างหมวดหมู่ใหม่</option>
                </select>
                <input type="text" name="new_category" id="newCatInput" class="form-control mt-2"
                    placeholder="ใส่ชื่อหมวดหมู่ใหม่" style="display:none; margin-top:0.5rem;">
            </div>

            <div class="form-group">
                <label class="form-label">หน่วย</label>
                <input type="text" name="unit" class="form-control" value="<?php echo h($product['unit'] ?? 'ชิ้น'); ?>"
                    required>
            </div>

            <div class="form-group">
                <label class="form-label">สต็อกขั้นต่ำ (แจ้งเตือน)</label>
                <input type="number" name="min_stock" class="form-control"
                    value="<?php echo h($product['min_stock'] ?? '0'); ?>">
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 1rem;">

        </div>

        <div class="form-group">
            <label class="form-label">รูปภาพสินค้า</label>
            <?php if (!empty($product['image_path'])): ?>
                <div class="mb-4">
                    <img src="<?php echo h($product['image_path']); ?>" alt="รูปปัจจุบัน"
                        style="max-height: 100px; border-radius: 4px;">
                </div>
            <?php endif; ?>
            <input type="file" name="image" class="form-control" accept="image/*">
            <small class="text-muted">สูงสุด 2MB รองรับ JPG, PNG, WEBP</small>
        </div>

        <div
            style="margin-top: 2rem; border-top: 1px solid var(--border-color); padding-top: 1.5rem; text-align: right;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> บันทึกสินค้า</button>
        </div>
    </form>
</div>

<script>
    function generateBarcode() {
        const random = Math.floor(100000000000 + Math.random() * 900000000000);
        document.getElementById('barcodeInput').value = random;
    }

    function toggleCategoryInput(select) {
        const input = document.getElementById('newCatInput');
        if (select.value === 'new') {
            input.style.display = 'block';
            input.required = true;
            input.focus();
        } else {
            input.style.display = 'none';
            input.required = false;
        }
    }
</script>

<?php include 'includes/footer.php'; ?>