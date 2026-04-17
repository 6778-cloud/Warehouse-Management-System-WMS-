<?php
require_once 'config/db.php';
requireLogin();

// Logic for Stock Adjustment
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verifyCsrfToken($_POST['csrf_token']);

    $stock_id = $_POST['stock_id'];
    $actual_qty = (int) $_POST['actual_qty'];
    $remark = trim($_POST['remark']);

    // Fetch current
    $stmt = $pdo->prepare("SELECT * FROM stock WHERE stock_id = ?");
    $stmt->execute([$stock_id]);
    $current = $stmt->fetch();

    if ($current) {
        $old_qty = $current['qty'];
        $diff = $actual_qty - $old_qty;

        if ($diff != 0) {
            // Update Stock
            $upd = $pdo->prepare("UPDATE stock SET qty = ?, updated_at = NOW() WHERE stock_id = ?");
            $upd->execute([$actual_qty, $stock_id]);

            // Sync Location (Not perfect if multiple stocks in one location, but assumes tracking)
            // Ideally we recalculated location total
            $pdo->prepare("UPDATE locations SET current_qty = current_qty + ? WHERE location_id = ?")
                ->execute([$diff, $current['location_id']]);

            // Log
            $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details) VALUES (?, 'adjust_stock', 'stock', ?, ?)")
                ->execute([$_SESSION['user_id'], $stock_id, "ตรวจนับสต็อก: เปลี่ยนจาก $old_qty เป็น $actual_qty หมายเหตุ: $remark"]);

            $success = "ปรับปรุงสต็อกเรียบร้อยแล้ว";
        } else {
            $success = "ไม่มีการเปลี่ยนแปลง (จำนวนตรงกัน)";
        }
    }
}

// Fetch Stock for Counting
// Default: Filter by location or product if GET params present
$location_id = $_GET['location_id'] ?? '';
$where = "WHERE s.qty >= 0"; // Show all positive or zero stock entries
$params = [];

if ($location_id) {
    $where .= " AND s.location_id = :loc";
    $params[':loc'] = $location_id;
}

$sql = "SELECT s.*, p.sku, p.name as product_name, p.image_path, l.code as location_code 
        FROM stock s
        JOIN products p ON s.product_id = p.product_id
        JOIN locations l ON s.location_id = l.location_id
        $where
        ORDER BY l.code ASC, p.sku ASC";
$stocks = $pdo->prepare($sql);
$stocks->execute($params);
$rows = $stocks->fetchAll();

// Fetch Locations for Filter
$locations = $pdo->query("SELECT * FROM locations ORDER BY code")->fetchAll();

include 'includes/header.php';
?>

<div class="flex justify-between items-center mb-4">
    <h2>ตรวจนับสต็อก / ปรับปรุงสต็อก</h2>
    <a href="audit.php" class="btn btn-secondary">ดูประวัติ</a>
</div>

<div class="card mb-4">
    <form method="get" class="flex gap-2 items-end">
        <div class="form-group mb-0" style="flex:1; max-width:300px;">
            <label class="form-label">กรองตามตำแหน่ง</label>
            <select name="location_id" class="form-control" onchange="this.form.submit()">
                <option value="">-- ทุกตำแหน่ง --</option>
                <?php foreach ($locations as $l): ?>
                    <option value="<?php echo $l['location_id']; ?>" <?php echo $location_id == $l['location_id'] ? 'selected' : ''; ?>>
                        <?php echo h($l['code']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<?php if (isset($success)): ?>
    <div class="badge badge-success mb-4" style="display:block; padding:1rem;"><?php echo $success; ?></div>
<?php endif; ?>

<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>ตำแหน่ง</th>
                    <th>สินค้า</th>
                    <th>จำนวนในระบบ</th>
                    <th>จำนวนจริง</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td style="font-weight:bold; color:var(--primary-color);"><?php echo h($row['location_code']); ?>
                        </td>
                        <td>
                            <div class="flex items-center gap-2">
                                <?php if ($row['image_path']): ?>
                                    <img src="<?php echo h($row['image_path']); ?>"
                                        style="width:30px; height:30px; border-radius:4px;">
                                <?php endif; ?>
                                <span><?php echo h($row['sku']); ?></span>
                            </div>
                            <small class="text-muted"><?php echo h($row['product_name']); ?></small>
                        </td>
                        <td style="font-size:1.1rem;"><?php echo number_format($row['qty']); ?></td>

                        <!-- Inline Adjustment Form -->
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            <input type="hidden" name="stock_id" value="<?php echo $row['stock_id']; ?>">

                            <td>
                                <input type="number" name="actual_qty" class="form-control"
                                    style="width:100px; padding:0.25rem 0.5rem;" value="<?php echo $row['qty']; ?>"
                                    required>
                            </td>
                            <td>
                                <div class="flex gap-2">
                                    <input type="text" name="remark" class="form-control"
                                        placeholder="เหตุผล (เช่น เสียหาย, หาย)" style="width:150px; font-size:0.8rem;">
                                    <button type="submit" class="btn btn-primary btn-sm"
                                        onclick="return confirm('ยืนยันการปรับปรุงสต็อก?');">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </div>
                            </td>
                        </form>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>