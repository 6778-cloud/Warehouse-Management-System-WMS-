<?php
require_once 'config/db.php';
requireLogin();
requireStaffWarehouse(); // เฉพาะพนักงานคลัง

$id = $_GET['id'] ?? null;
if (!$id)
    die("รหัสไม่ถูกต้อง");

// Fetch Order
$stmt = $pdo->prepare("SELECT * FROM inbound_orders WHERE inbound_id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order || $order['status'] != 'draft') {
    die("รายการไม่ถูกต้องหรือสถานะไม่ใช่ร่าง");
}

// Fetch Lines
$stmt = $pdo->prepare("SELECT l.*, p.sku, p.name as product_name, p.unit 
                       FROM inbound_lines l 
                       JOIN products p ON l.product_id = p.product_id 
                       WHERE l.inbound_id = ?");
$stmt->execute([$id]);
$lines = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verifyCsrfToken($_POST['csrf_token']);

    $receive_now = $_POST['receive_now'] ?? []; // Array of line_id => qty_to_add

    try {
        $pdo->beginTransaction();

        $all_fully_received = true;
        $has_discrepancy = false;
        $discrepancy_messages = [];

        foreach ($receive_now as $line_id => $qty_add) {
            $qty_add = intval($qty_add);
            if ($qty_add <= 0) continue;

            // Get current line details (including current received_qty)
            $lineStmt = $pdo->prepare("SELECT * FROM inbound_lines WHERE line_id = ? AND inbound_id = ? FOR UPDATE");
            $lineStmt->execute([$line_id, $id]);
            $lineData = $lineStmt->fetch();

            if (!$lineData) continue;

            $current_received = intval($lineData['received_qty']);
            $expected = intval($lineData['expected_qty']);
            
            $new_total = $current_received + $qty_add;

            // Update received quantity
            $update = $pdo->prepare("UPDATE inbound_lines SET received_qty = :qty WHERE line_id = :lid AND inbound_id = :inb");
            $update->execute([':qty' => $new_total, ':lid' => $line_id, ':inb' => $id]);

            // Check status for this line
            if ($new_total < $expected) {
                $all_fully_received = false;
            }

            // Check for OVER receipt
            if ($new_total > $expected) {
                $has_discrepancy = true;
                $variance = $new_total - $expected;
                
                // Log discrepancy if variance is new (simple logic: just log every overage event?)
                // Better: Check if we already logged? 
                // For simplicity: Insert discrepancy record for this overage event
                $discStmt = $pdo->prepare("INSERT INTO inbound_discrepancies 
                    (inbound_id, line_id, product_id, issue_type, expected_qty, received_qty, variance, created_by) 
                    VALUES (?, ?, ?, 'over', ?, ?, ?, ?)");
                $discStmt->execute([
                    $id,
                    $line_id,
                    $lineData['product_id'],
                    $expected,
                    $new_total,
                    $variance,
                    $_SESSION['user_id']
                ]);

                // Get product name for message
                $prodStmt = $pdo->prepare("SELECT sku FROM products WHERE product_id = ?");
                $prodStmt->execute([$lineData['product_id']]);
                $pSku = $prodStmt->fetchColumn();
                $discrepancy_messages[] = "$pSku: รับเกินผลิต ($new_total / $expected)";
            }
        }

        // Re-check ALL lines to ensure we didn't miss any that were ALREADY partial
        // (The loop only checks lines we just updated. What if a line was untouched but still partial?)
        // Fetch ALL lines status
        $checkAll = $pdo->prepare("SELECT expected_qty, received_qty FROM inbound_lines WHERE inbound_id = ?");
        $checkAll->execute([$id]);
        $allLines = $checkAll->fetchAll();
        
        foreach ($allLines as $l) {
            if ($l['received_qty'] < $l['expected_qty']) {
                $all_fully_received = false;
                break;
            }
        }

        // Determine Order Status
        $new_status = $all_fully_received ? 'received' : 'partial';

        // Update order status
        $pdo->prepare("UPDATE inbound_orders SET status = ? WHERE inbound_id = ?")->execute([$new_status, $id]);

        $pdo->commit();

        // Redirect
        if ($has_discrepancy) {
            $_SESSION['alert'] = [
                'type' => 'warning',
                'message' => '⚠️ บันทึกการรับแล้ว แต่มีรายการรับเกิน: ' . implode(', ', $discrepancy_messages)
            ];
        } else {
             $_SESSION['alert'] = [
                'type' => 'success',
                'message' => 'บันทึกการรับสินค้าเรียบร้อยแล้ว (' . ($new_status == 'received' ? 'รับครบแล้ว' : 'รับบางส่วน') . ')'
            ];
        }

        header("Location: inbound.php");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

include 'includes/header.php';
?>

<div class="flex justify-between items-center mb-4">
    <h2>รับสินค้าเข้า #<?php echo $order['inbound_id']; ?></h2>
    <a href="inbound.php" class="btn btn-secondary">ยกเลิก</a>
</div>

<div class="card">
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

        <table class="table">
            <thead>
                <tr>
                    <th>สินค้า</th>
                    <th class="text-center">คาดว่า</th>
                    <th class="text-center">รับแล้ว</th>
                    <th class="text-center">ค้างรับ</th>
                    <th class="text-center">รับครั้งนี้</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lines as $line):
                    $prev_received = $line['received_qty'] ?? 0;
                    $remaining = max(0, $line['expected_qty'] - $prev_received);
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight:600;"><?php echo h($line['sku']); ?></div>
                            <div class="text-muted text-sm"><?php echo h($line['product_name']); ?></div>
                        </td>
                        <td class="text-center"><?php echo number_format($line['expected_qty']); ?></td>
                        <td class="text-center"><?php echo number_format($prev_received); ?></td>
                        <td class="text-center"><?php echo number_format($remaining); ?></td>
                        <td class="text-center">
                            <input type="number" name="receive_now[<?php echo $line['line_id']; ?>]" class="form-control"
                                value="<?php echo $remaining; ?>" min="0"
                                style="width: 120px; margin: 0 auto; text-align:center;">
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="mt-4 flex justify-between">
            <div class="text-muted">
                * บันทึกยอดรับจะสะสมเพิ่มจากยอดเดิม
            </div>
            <div>
                <button type="submit" name="action" value="save" class="btn btn-primary">
                    <i class="fas fa-save"></i> บันทึกการรับ
                </button>
            </div>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>