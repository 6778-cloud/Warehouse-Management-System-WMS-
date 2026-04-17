<?php
require_once 'config/db.php';
requireLogin();

$search = $_GET['search'] ?? '';
$location_filter = $_GET['location_id'] ?? '';

// Build Query
$sql = "SELECT s.*, p.sku, p.name as product_name, p.unit, p.image_path, l.code as location_code, l.zone 
        FROM stock s
        JOIN products p ON s.product_id = p.product_id
        JOIN locations l ON s.location_id = l.location_id
        WHERE s.qty > 0";
$params = [];

if ($search) {
    $sql .= " AND (p.sku LIKE :search OR p.name LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($location_filter) {
    $sql .= " AND s.location_id = :loc";
    $params[':loc'] = $location_filter;
}

$sql .= " ORDER BY p.sku, l.code";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$stocks = $stmt->fetchAll();

// Fetch Locations for filter
$locs = $pdo->query("SELECT location_id, code FROM locations ORDER BY code")->fetchAll();

include 'includes/header.php';
?>

<div class="flex justify-between items-center mb-4">
    <h2>สต็อกคลังสินค้า</h2>
    <div>
        <a href="cycle_count.php" class="btn btn-secondary"><i class="fas fa-clipboard-check"></i> ตรวจนับสต็อก</a>
        <a href="api/export_stock.php?search=<?php echo urlencode($search); ?>&location_id=<?php echo urlencode($location_filter); ?>"
            target="_blank" class="btn btn-primary"><i class="fas fa-file-export"></i> ส่งออก CSV</a>
    </div>
</div>

<div class="card">
    <div class="flex justify-between items-center mb-4">
        <form method="get" class="flex gap-2" style="width: 100%; max-width: 600px;">
            <input type="text" name="search" class="form-control" placeholder="ค้นหารหัสสินค้า/ชื่อสินค้า..."
                value="<?php echo h($search); ?>">
            <select name="location_id" class="form-control" style="width: 200px;">
                <option value="">ทุกตำแหน่ง</option>
                <?php foreach ($locs as $l): ?>
                    <option value="<?php echo $l['location_id']; ?>" <?php echo $location_filter == $l['location_id'] ? 'selected' : ''; ?>>
                        <?php echo h($l['code']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i></button>
        </form>
    </div>

    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>สินค้า</th>
                    <th>ตำแหน่ง</th>
                    <th>โซน</th>
                    <th class="text-center">จำนวน</th>

                    <th class="text-right">อัพเดทล่าสุด</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($stocks) > 0): ?>
                    <?php foreach ($stocks as $s): ?>
                        <tr>
                            <td style="display:flex; align-items:center; gap:10px;">
                                <?php if ($s['image_path']): ?>
                                    <img src="<?php echo h($s['image_path']); ?>"
                                        style="width:30px; height:30px; object-fit:cover; border-radius:4px;">
                                <?php else: ?>
                                    <i class="fas fa-box text-muted"></i>
                                <?php endif; ?>
                                <div>
                                    <div style="font-weight:600;"><?php echo h($s['sku']); ?></div>
                                    <div class="text-muted" style="font-size:0.75rem;"><?php echo h($s['product_name']); ?>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge badge-info"><?php echo h($s['location_code']); ?></span></td>
                            <td><?php echo h($s['zone']); ?></td>
                            <td class="text-center">
                                <span style="font-size:1.1rem; font-weight:700;"><?php echo number_format($s['qty']); ?></span>
                                <span class="text-muted" style="font-size:0.75rem;"><?php echo h($s['unit']); ?></span>
                            </td>

                            <td class="text-right text-muted" style="font-size:0.75rem;">
                                <?php echo date('d/m/Y H:i', strtotime($s['updated_at'])); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center" style="padding: 2rem; color: #64748b;">ไม่พบสต็อก</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>