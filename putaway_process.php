<?php
require_once 'config/db.php';
requireLogin();
requireStaffWarehouse(); // เฉพาะพนักงานคลัง

$id = $_GET['id'] ?? null;
if (!$id)
    die("รหัสไม่ถูกต้อง");

// Fetch Order
$stmt = $pdo->prepare("SELECT * FROM inbound_orders WHERE inbound_id = ? AND status = 'received'");
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    die("ไม่พบรายการหรือไม่อยู่ในสถานะรับแล้ว");
}

// Fetch Lines with Product Info and Category
$stmt = $pdo->prepare("SELECT l.*, p.sku, p.name as product_name, p.image_path, p.category_id, c.name as category_name
                       FROM inbound_lines l 
                       JOIN products p ON l.product_id = p.product_id 
                       LEFT JOIN categories c ON p.category_id = c.category_id
                       WHERE l.inbound_id = ?");
$stmt->execute([$id]);
$lines = $stmt->fetchAll();

// Fetch Locations for Dropdown
$locations = $pdo->query("SELECT location_id, code, zone, (capacity - current_qty) as free_space FROM locations ORDER BY code")->fetchAll();

// Group Locations by Zone for Smart Suggestion Logic
$zones = [];
foreach ($locations as $l) {
    $zones[$l['zone']][] = $l;
}

// Helper Function for Suggestion
function suggestLocation($productline, $allZones)
{
    // Logic Mapping based on Category or Product Attributes
    // 1. Check if Category suggests a zone
    $sugg = "B"; // Default General
    $reason = "จัดเก็บทั่วไป";

    // You would typically join Category Name here. For now, let's assume simple logic or fetched cat name.
    $catId = $productline['category_id'] ?? 0;

    // Hardcoded logic for demo (In real app, map Category -> Preferred Zone in DB)
    // Assuming we fetch Category Name in the main query
    $catName = $productline['category_name'] ?? '';

    if (stripos($catName, 'Food') !== false || stripos($catName, 'Beauty') !== false) {
        $sugg = "T";
        $reason = "ควบคุมอุณหภูมิ (อาหาร/ความงาม)";
    } elseif (stripos($catName, 'Electronics') !== false) {
        $sugg = "S";
        $reason = "จัดเก็บปลอดภัย (สินค้ามีค่า)";
    } elseif (stripos($catName, 'Bulk') !== false) {
        $sugg = "C";
        $reason = "จัดเก็บขนาดใหญ่";
    } elseif (stripos($catName, 'Fashion') !== false || stripos($catName, 'Toys') !== false) {
        $sugg = "A";
        $reason = "พื้นที่เคลื่อนไหวเร็ว";
    }

    return ['zone' => $sugg, 'reason' => $reason];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verifyCsrfToken($_POST['csrf_token']);

    $putaway_data = $_POST['putaway'] ?? []; // Array of line_id => location_id

    try {
        $pdo->beginTransaction();

        foreach ($lines as $line) {
            $lid = $line['line_id'];
            $target_loc_id = $putaway_data[$lid] ?? null;
            $qty = $line['received_qty'];

            if ($target_loc_id && $qty > 0) {
                // 1. Check if stock record exists for this product + location
                $check = $pdo->prepare("SELECT stock_id FROM stock WHERE product_id = ? AND location_id = ?");
                $check->execute([$line['product_id'], $target_loc_id]);
                $stock_id = $check->fetchColumn();

                if ($stock_id) {
                    // Update existing
                    $update = $pdo->prepare("UPDATE stock SET qty = qty + ?, updated_at = NOW() WHERE stock_id = ?");
                    $update->execute([$qty, $stock_id]);
                } else {
                    // Insert new
                    $insert = $pdo->prepare("INSERT INTO stock (product_id, location_id, qty, lot_no) VALUES (?, ?, ?, ?)");
                    $insert->execute([$line['product_id'], $target_loc_id, $qty, $line['lot_no']]);
                }

                // 2. Update Location Occupancy
                $updLoc = $pdo->prepare("UPDATE locations SET current_qty = current_qty + ? WHERE location_id = ?");
                $updLoc->execute([$qty, $target_loc_id]);

                // 3. Log
                $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details) VALUES (?, 'putaway', 'inbound', ?, ?)");
                $log->execute([$_SESSION['user_id'], $id, "จัดเก็บ {$line['sku']} จำนวน $qty ไปตำแหน่ง $target_loc_id"]);
            }
        }

        // 4. Update Order Status
        $pdo->prepare("UPDATE inbound_orders SET status = 'completed' WHERE inbound_id = ?")->execute([$id]);

        $pdo->commit();
        header("Location: putaway.php");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "เกิดข้อผิดพลาดในการจัดเก็บ: " . $e->getMessage();
    }
}

include 'includes/header.php';
?>

<div class="flex justify-between items-center mb-4">
    <h2>ดำเนินการจัดเก็บ: รับเข้า #<?php echo $order['inbound_id']; ?></h2>
    <a href="putaway.php" class="btn btn-secondary">ยกเลิก</a>
</div>

<div class="card">
    <?php if (isset($error)): ?>
        <div class="badge badge-danger mb-4" style="display:block; padding: 1rem;"><?php echo h($error); ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>สินค้า</th>
                        <th>จำนวนที่รับ</th>
                        <th>เลือกตำแหน่ง <span style="color:red">*</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lines as $line):
                        $suggestion = suggestLocation($line, $zones);
                        $suggestedZone = $suggestion['zone'];
                        ?>
                        <tr>
                            <td style="display:flex; align-items:center; gap:10px;">
                                <?php if ($line['image_path']): ?>
                                    <img src="<?php echo h($line['image_path']); ?>"
                                        style="width:40px; height:40px; border-radius:4px; object-fit:cover;">
                                <?php endif; ?>
                                <div>
                                    <div style="font-weight:bold; color:var(--primary-color);">
                                        <?php echo h($line['sku']); ?>
                                    </div>
                                    <div class="text-muted" style="font-size:0.8rem;">
                                        <?php echo h($line['product_name']); ?>
                                    </div>
                                    <div class="text-xs" style="color:var(--info-color); margin-top:2px;">
                                        หมวดหมู่: <?php echo h($line['category_name'] ?? 'ไม่มี'); ?>
                                    </div>
                                </div>
                            </td>
                            <td style="font-size:1.1rem; font-weight:bold;">
                                <?php echo number_format($line['received_qty']); ?>
                            </td>
                            <td>
                                <div style="margin-bottom: 5px;">
                                    <span class="badge badge-success" style="font-size:0.8rem;">
                                        แนะนำ: โซน <?php echo $suggestedZone; ?>
                                    </span>
                                    <span class="text-muted text-xs">(<?php echo $suggestion['reason']; ?>)</span>
                                </div>
                                <select name="putaway[<?php echo $line['line_id']; ?>]" class="form-control" required>
                                    <option value="">-- เลือกตำแหน่ง --</option>

                                    <!-- Priority: Show Recommended Zone First -->
                                    <optgroup label="แนะนำ: โซน <?php echo $suggestedZone; ?>">
                                        <?php if (isset($zones[$suggestedZone])):
                                            foreach ($zones[$suggestedZone] as $loc): ?>
                                                <option value="<?php echo $loc['location_id']; ?>">
                                                    ★ <?php echo h($loc['code']); ?> (ว่าง:
                                                    <?php echo number_format($loc['free_space']); ?>)
                                                </option>
                                            <?php endforeach; endif; ?>
                                    </optgroup>

                                    <!-- Then Others -->
                                    <optgroup label="โซนอื่น">
                                        <?php foreach ($zones as $zName => $zLocs):
                                            if ($zName == $suggestedZone)
                                                continue;
                                            ?>
                                            <?php foreach ($zLocs as $loc): ?>
                                                <option value="<?php echo $loc['location_id']; ?>" style="color:#888;">
                                                    <?php echo h($loc['code']); ?> (ว่าง:
                                                    <?php echo number_format($loc['free_space']); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    </optgroup>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-4 text-right" style="margin-top:2rem;">
            <button type="submit" class="btn btn-primary"
                onclick="return confirm('ยืนยันการจัดเก็บ? สต็อกจะถูกอัพเดท');">
                <i class="fas fa-check-circle"></i> เสร็จสิ้นการจัดเก็บ
            </button>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>