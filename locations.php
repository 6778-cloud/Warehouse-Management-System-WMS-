<?php
require_once 'config/db.php';
requireLogin();

$search = $_GET['search'] ?? '';

// Build Query
$sql = "SELECT * FROM locations WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (code LIKE :search OR zone LIKE :search)";
    $params[':search'] = "%$search%";
}

$sql .= " ORDER BY zone, shelf, bin";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$locations = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="flex justify-between items-center mb-4">
    <h2>ตำแหน่งจัดเก็บในคลัง</h2>
    <a href="location_form.php" class="btn btn-primary"><i class="fas fa-plus"></i> เพิ่มตำแหน่ง</a>
</div>

<div class="card">
    <div class="flex justify-between items-center mb-4">
        <form method="get" class="flex gap-2" style="width: 100%; max-width: 400px;">
            <input type="text" name="search" class="form-control" placeholder="ค้นหารหัสหรือโซน..."
                value="<?php echo h($search); ?>">
            <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i></button>
        </form>
    </div>

    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>รหัสตำแหน่ง</th>
                    <th>โซน</th>
                    <th>ชั้น</th>
                    <th>ช่อง</th>
                    <th>ความจุ</th>
                    <th>จำนวนปัจจุบัน</th>
                    <th>การใช้งาน</th>
                    <th class="text-right">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($locations) > 0): ?>
                    <?php foreach ($locations as $l): ?>
                        <tr>
                            <td><span class="badge badge-info"><?php echo h($l['code']); ?></span></td>
                            <td><?php echo h($l['zone']); ?></td>
                            <td><?php echo h($l['shelf']); ?></td>
                            <td><?php echo h($l['bin']); ?></td>
                            <td><?php echo number_format($l['capacity']); ?></td>
                            <td><?php echo number_format($l['current_qty']); ?></td>
                            <td>
                                <?php
                                $percent = $l['capacity'] > 0 ? ($l['current_qty'] / $l['capacity']) * 100 : 0;
                                $color = $percent > 90 ? '#ef4444' : ($percent > 50 ? '#f59e0b' : '#10b981');
                                ?>
                                <div
                                    style="width: 100px; height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden;">
                                    <div
                                        style="width: <?php echo $percent; ?>%; height: 100%; background: <?php echo $color; ?>;">
                                    </div>
                                </div>
                                <small style="color: <?php echo $color; ?>"><?php echo round($percent); ?>%</small>
                            </td>
                            <td class="text-right">
                                <a href="location_form.php?id=<?php echo $l['location_id']; ?>" class="btn btn-secondary"
                                    style="padding: 0.25rem 0.5rem;"><i class="fas fa-edit"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center" style="padding: 2rem; color: #64748b;">ไม่พบตำแหน่งจัดเก็บ</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>