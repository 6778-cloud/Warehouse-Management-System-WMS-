<?php
require_once 'config/db.php';
requireLogin();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verifyCsrfToken($_POST['csrf_token']);

    $product_id = $_POST['product_id'];
    $from_location_id = $_POST['from_location_id'];
    $to_location_id = $_POST['to_location_id'];
    $qty = (int) $_POST['qty'];

    if ($from_location_id == $to_location_id) {
        $error = "ตำแหน่งต้นทางและปลายทางต้องไม่เหมือนกัน";
    } elseif ($qty <= 0) {
        $error = "จำนวนต้องมากกว่า 0";
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Check Source Stock
            $stmt = $pdo->prepare("SELECT * FROM stock WHERE product_id = ? AND location_id = ?");
            $stmt->execute([$product_id, $from_location_id]);
            $sourceStock = $stmt->fetch();

            if (!$sourceStock || $sourceStock['qty'] < $qty) {
                throw new Exception("สต็อกในตำแหน่งต้นทางไม่เพียงพอ");
            }

            // 2. Deduct from Source
            $newSourceQty = $sourceStock['qty'] - $qty;
            if ($newSourceQty == 0) {
                // Remove record if 0? Or keep as 0? Let's update to 0.
                $pdo->prepare("UPDATE stock SET qty = 0, updated_at = NOW() WHERE stock_id = ?")->execute([$sourceStock['stock_id']]);
            } else {
                $pdo->prepare("UPDATE stock SET qty = ?, updated_at = NOW() WHERE stock_id = ?")->execute([$newSourceQty, $sourceStock['stock_id']]);
            }
            // Update Source Location Occupancy
            $pdo->prepare("UPDATE locations SET current_qty = current_qty - ? WHERE location_id = ?")->execute([$qty, $from_location_id]);

            // 3. Add to Destination
            // Check if stock record exists
            $stmt = $pdo->prepare("SELECT * FROM stock WHERE product_id = ? AND location_id = ?");
            $stmt->execute([$product_id, $to_location_id]);
            $destStock = $stmt->fetch();

            if ($destStock) {
                $pdo->prepare("UPDATE stock SET qty = qty + ?, updated_at = NOW() WHERE stock_id = ?")->execute([$qty, $destStock['stock_id']]);
            } else {
                $pdo->prepare("INSERT INTO stock (product_id, location_id, qty, updated_at) VALUES (?, ?, ?, NOW())")->execute([$product_id, $to_location_id, $qty]);
            }
            // Update Dest Location Occupancy
            $pdo->prepare("UPDATE locations SET current_qty = current_qty + ? WHERE location_id = ?")->execute([$qty, $to_location_id]);

            // 4. Log
            $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details) VALUES (?, 'transfer', 'stock', ?, ?)")
                ->execute([$_SESSION['user_id'], $product_id, "โอน $qty หน่วย จากตำแหน่ง #$from_location_id ไปตำแหน่ง #$to_location_id"]);

            $pdo->commit();
            $success = "โอน $qty หน่วยเรียบร้อยแล้ว";

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "โอนไม่สำเร็จ: " . $e->getMessage();
        }
    }
}

// Fetch Logic for Form
$products = $pdo->query("SELECT product_id, sku, name FROM products ORDER BY sku")->fetchAll();
$locations = $pdo->query("SELECT location_id, code FROM locations ORDER BY code")->fetchAll();

include 'includes/header.php';
?>

<div class="flex justify-between items-center mb-4">
    <h2>โอนสต็อก</h2>
    <a href="stock.php" class="btn btn-secondary">ดูคลังสินค้า</a>
</div>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <?php if ($error): ?>
        <div class="badge badge-danger mb-4" style="display:block; padding:1rem;"><?php echo h($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="badge badge-success mb-4" style="display:block; padding:1rem;"><?php echo h($success); ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

        <div class="form-group">
            <label class="form-label">สินค้า</label>
            <select name="product_id" class="form-control" required id="productSelect">
                <option value="">-- เลือกสินค้า --</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?php echo $p['product_id']; ?>"><?php echo h($p['sku'] . ' - ' . $p['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="flex gap-2">
            <div class="form-group flex-1">
                <label class="form-label">จากตำแหน่ง</label>
                <select name="from_location_id" class="form-control" required id="fromLocation">
                    <option value="">-- เลือกต้นทาง --</option>
                    <!-- Populated via JS ideally, but let's list all for now or enhance later -->
                    <?php foreach ($locations as $l): ?>
                        <option value="<?php echo $l['location_id']; ?>"><?php echo h($l['code']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group flex-1">
                <label class="form-label">ไปตำแหน่ง</label>
                <select name="to_location_id" class="form-control" required>
                    <option value="">-- เลือกปลายทาง --</option>
                    <?php foreach ($locations as $l): ?>
                        <option value="<?php echo $l['location_id']; ?>"><?php echo h($l['code']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">จำนวน</label>
            <input type="number" name="qty" class="form-control" min="1" required>
        </div>

        <div class="text-right">
            <button type="submit" class="btn btn-primary"><i class="fas fa-exchange-alt"></i> โอนสต็อก</button>
        </div>
    </form>
</div>

<!-- Simple JS to filter locations based on product could be added here, 
     but for "MVP Scope" a direct select is acceptable provided error handling catches invalid moves. 
     For better UX, we could fetch available locations for a product via AJAX.
-->

<?php include 'includes/footer.php'; ?>