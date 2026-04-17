<?php
require_once 'config/db.php';
requireLogin();
requireStaffWarehouse(); // เฉพาะพนักงานคลัง

$id = $_GET['id'] ?? null;
if (!$id)
    die("รหัสไม่ถูกต้อง");

// Fetch Order
$stmt = $pdo->prepare("SELECT * FROM outbound_orders WHERE outbound_id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order || $order['status'] == 'shipped') {
    die("ไม่พบรายการหรือส่งไปแล้ว");
}

// Fetch Lines
$stmt = $pdo->prepare("SELECT l.*, p.sku, p.name as product_name, p.image_path 
                       FROM outbound_lines l 
                       JOIN products p ON l.product_id = p.product_id 
                       WHERE l.outbound_id = ?");
$stmt->execute([$id]);
$lines = $stmt->fetchAll();

// Logic for Suggesting Picking
// We need to find where these items are.
// Simple FIFO: Order by expiry_date or stock_id
$suggestions = [];
foreach ($lines as $line) {
    if ($line['qty'] > 0) {
        $stmtStock = $pdo->prepare("SELECT s.*, l.code as location_code, l.zone 
                                    FROM stock s 
                                    JOIN locations l ON s.location_id = l.location_id 
                                    WHERE s.product_id = ? AND s.qty > 0 
                                    ORDER BY s.expiry_date ASC, s.stock_id ASC");
        $stmtStock->execute([$line['product_id']]);
        $available_stock = $stmtStock->fetchAll();

        $needed = $line['qty'];
        $product_suggestions = [];

        foreach ($available_stock as $stk) {
            if ($needed <= 0)
                break;

            $take = min($needed, $stk['qty']);
            $product_suggestions[] = [
                'stock_id' => $stk['stock_id'],
                'location_code' => $stk['location_code'],
                'qty_to_pick' => $take,
                'available' => $stk['qty']
            ];
            $needed -= $take;
        }

        $suggestions[$line['line_id']] = [
            'stock_found' => ($needed < $line['qty']), // True if we found ANY stock
            'fully_stocked' => ($needed == 0),
            'picks' => $product_suggestions,
            'missing' => $needed
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verifyCsrfToken($_POST['csrf_token']);

    // Process Picking
    try {
        $pdo->beginTransaction();

        // Loop through POST picks to perform actual deduction
        // Structure: picks[line_id][stock_id] = qty

        $picks_input = $_POST['picks'] ?? [];

        foreach ($picks_input as $line_id => $stock_picks) {
            foreach ($stock_picks as $stock_id => $qty_picked) {
                if ($qty_picked > 0) {
                    // 1. Deduct Stock
                    $stmtUpdate = $pdo->prepare("UPDATE stock SET qty = qty - ? WHERE stock_id = ? AND qty >= ?");
                    $stmtUpdate->execute([$qty_picked, $stock_id, $qty_picked]);

                    if ($stmtUpdate->rowCount() == 0) {
                        throw new Exception("หักสต็อกไม่ได้ สินค้าอาจถูกหยิบไปแล้ว");
                    }

                    // 2. Decrement Location Occupancy
                    // First get location_id from stock_id (could do with join above but simple fetch here)
                    $locId = $pdo->query("SELECT location_id FROM stock WHERE stock_id = $stock_id")->fetchColumn();
                    $pdo->prepare("UPDATE locations SET current_qty = current_qty - ? WHERE location_id = ?")->execute([$qty_picked, $locId]);

                    // 3. Log
                    $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details) VALUES (?, 'pick', 'outbound', ?, ?)")
                        ->execute([$_SESSION['user_id'], $id, "หยิบ $qty_picked จากตำแหน่ง $locId สำหรับรายการ $line_id"]);
                }
            }
        }

        // 4. Update Order Status (Conditional based on order_type)
        // Internal orders skip verification and go directly to shipped
        $targetStatus = ($order['order_type'] == 'internal') ? 'shipped' : 'picked';
        $pdo->prepare("UPDATE outbound_orders SET status = ? WHERE outbound_id = ?")->execute([$targetStatus, $id]);

        $pdo->commit();
        header("Location: outbound.php");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "หยิบสินค้าไม่สำเร็จ: " . $e->getMessage();
    }
}

include 'includes/header.php';
?>

<div class="flex justify-between items-center mb-4">
    <h2>หยิบสินค้าสำหรับ #<?php echo h($order['order_no']); ?></h2>
    <a href="outbound.php" class="btn btn-secondary">ยกเลิก</a>
</div>

<div class="card">
    <?php if (isset($error)): ?>
        <div class="badge badge-danger mb-4" style="display:block; padding: 1rem;"><?php echo h($error); ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

        <?php foreach ($lines as $line): ?>
            <div class="card"
                style="background-color: rgba(255,255,255,0.02); border:1px solid var(--border-color); padding:1rem; margin-bottom:1rem;">
                <div class="flex justify-between items-center mb-2">
                    <div style="font-weight:bold; color:var(--primary-color);">
                        <?php echo h($line['sku']); ?> - <?php echo h($line['product_name']); ?>
                    </div>
                    <div class="badge badge-info text-white" style="font-size:1rem;">
                        จำนวนสั่ง: <?php echo number_format($line['qty']); ?>
                    </div>
                </div>

                <!-- Picking Suggestions Table -->
                <?php
                $sugg = $suggestions[$line['line_id']] ?? [];
                if (empty($sugg['picks'])) {
                    echo '<div class="text-danger">ไม่มีสต็อก! ไม่สามารถจัดสินค้าได้</div>';
                } else {
                    ?>
                    <table class="table" style="background:transparent;">
                        <thead>
                            <tr>
                                <th>ตำแหน่งแนะนำ</th>
                                <th>มีในช่อง</th>
                                <th>จำนวนหยิบ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sugg['picks'] as $pick): ?>
                                <tr>
                                    <td style="color:var(--success-color); font-weight:bold;">
                                        <?php echo h($pick['location_code']); ?>
                                    </td>
                                    <td><?php echo number_format($pick['available']); ?></td>
                                    <td>
                                        <input type="number"
                                            name="picks[<?php echo $line['line_id']; ?>][<?php echo $pick['stock_id']; ?>]"
                                            class="form-control" style="width:100px; padding:0.25rem;"
                                            value="<?php echo $pick['qty_to_pick']; ?>" max="<?php echo $pick['available']; ?>"
                                            min="0">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if ($sugg['missing'] > 0): ?>
                        <div style="color:var(--danger-color); margin-top:0.5rem;">
                            คำเตือน: สต็อกไม่พอ ขาด <?php echo $sugg['missing']; ?> ชิ้น
                        </div>
                    <?php endif; ?>
                <?php } ?>
            </div>
        <?php endforeach; ?>

        <div class="text-right mt-4">
            <button type="submit" class="btn btn-primary"
                onclick="return confirm('ตรวจสอบว่าหยิบสินค้าจริงแล้ว ยืนยันไหม?');">
                <i class="fas fa-truck"></i> ยืนยันหยิบและส่ง
            </button>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>