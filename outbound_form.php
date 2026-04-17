<?php
require_once 'config/db.php';
requireLogin();

$id = $_GET['id'] ?? null;
$order = null;
$existing_lines = [];

// Fetch existing order if editing
if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM outbound_orders WHERE outbound_id = ?");
    $stmt->execute([$id]);
    $order = $stmt->fetch();

    if (!$order || !in_array($order['status'], ['draft', 'allocated'])) {
        die("ไม่พบรายการหรือไม่สามารถแก้ไขได้ (สถานะ: " . ($order['status'] ?? 'ไม่มี') . ")");
    }

    // Fetch existing lines
    $stmt = $pdo->prepare("SELECT * FROM outbound_lines WHERE outbound_id = ?");
    $stmt->execute([$id]);
    $existing_lines = $stmt->fetchAll();
}

$products = $pdo->query("SELECT product_id, sku, name, (SELECT SUM(qty) FROM stock WHERE stock.product_id = products.product_id) as stock_qty FROM products ORDER BY name")->fetchAll();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verifyCsrfToken($_POST['csrf_token']);

    $order_no = trim($_POST['order_no']);
    $order_type = $_POST['order_type'];
    $items = $_POST['items'] ?? [];

    if (empty($order_no) || empty($items)) {
        $error = "กรุณาใส่เลขที่และรายการอย่างน้อยหนึ่งรายการ";
    } else {
        try {
            $pdo->beginTransaction();

            if ($id) {
                // Update existing order
                $stmt = $pdo->prepare("UPDATE outbound_orders SET order_no = ?, order_type = ? WHERE outbound_id = ?");
                $stmt->execute([$order_no, $order_type, $id]);

                // Delete existing lines
                $pdo->prepare("DELETE FROM outbound_lines WHERE outbound_id = ?")->execute([$id]);

                $outbound_id = $id;
                $success = "อัพเดทรายการเรียบร้อยแล้ว";
            } else {
                // Insert new order
                $stmt = $pdo->prepare("INSERT INTO outbound_orders (order_no, order_type, created_by, status) VALUES (?, ?, ?, 'draft')");
                $stmt->execute([$order_no, $order_type, $_SESSION['user_id']]);
                $outbound_id = $pdo->lastInsertId();
            }

            // Insert lines
            $stmtLine = $pdo->prepare("INSERT INTO outbound_lines (outbound_id, product_id, qty) VALUES (?, ?, ?)");
            foreach ($items as $item) {
                if (!empty($item['product_id']) && $item['qty'] > 0) {
                    $stmtLine->execute([$outbound_id, $item['product_id'], $item['qty']]);
                }
            }

            $pdo->commit();

            if (!$id) {
                header("Location: outbound.php");
                exit;
            } else {
                // Refresh data after update
                $stmt = $pdo->prepare("SELECT * FROM outbound_orders WHERE outbound_id = ?");
                $stmt->execute([$id]);
                $order = $stmt->fetch();

                $stmt = $pdo->prepare("SELECT * FROM outbound_lines WHERE outbound_id = ?");
                $stmt->execute([$id]);
                $existing_lines = $stmt->fetchAll();
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() == 23000) {
                $error = "เลขที่ซ้ำ";
            } else {
                $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="flex justify-between items-center mb-4">
    <h2><?php echo $id ? 'แก้ไขรายการส่งออก #' . $id : 'สร้างรายการส่งสินค้าออก'; ?></h2>
    <a href="outbound.php" class="btn btn-secondary">ยกเลิก</a>
</div>

<div class="card">
    <?php if ($error): ?>
        <div class="badge badge-danger mb-4" style="display:block; padding: 1rem;"><?php echo h($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="badge badge-success mb-4" style="display:block; padding: 1rem;"><?php echo h($success); ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

        <div class="flex gap-2 mb-4">
            <div class="form-group" style="flex:1">
                <label class="form-label">เลขที่ <span style="color:red">*</span></label>
                <input type="text" name="order_no" class="form-control" required placeholder="เช่น SO-2025-001"
                    value="<?php echo h($order['order_no'] ?? ''); ?>">
            </div>
            <div class="form-group" style="flex:1">
                <label class="form-label">ประเภท</label>
                <select name="order_type" class="form-control">
                    <option value="sale" <?php echo ($order['order_type'] ?? '') == 'sale' ? 'selected' : ''; ?>>ขาย</option>
                    <option value="internal" <?php echo ($order['order_type'] ?? '') == 'internal' ? 'selected' : ''; ?>>ใช้ภายใน</option>
                    <option value="return" <?php echo ($order['order_type'] ?? '') == 'return' ? 'selected' : ''; ?>>คืนผู้จำหน่าย</option>
                </select>
            </div>
        </div>

        <h4 class="mb-4">รายการสินค้า</h4>
        <div class="table-container mb-4">
            <table class="table" id="itemsTable">
                <thead>
                    <tr>
                        <th>สินค้า</th>
                        <th width="150">จำนวน</th>
                        <th width="50"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($id && count($existing_lines) > 0): ?>
                        <?php foreach ($existing_lines as $index => $line): ?>
                            <tr class="item-row">
                                <td>
                                    <select name="items[<?php echo $index; ?>][product_id]" class="form-control product-select" required
                                        onchange="checkStock(this)">
                                        <option value="" data-stock="0">-- เลือกสินค้า --</option>
                                        <?php foreach ($products as $p): ?>
                                            <option value="<?php echo $p['product_id']; ?>"
                                                data-stock="<?php echo $p['stock_qty'] ?? 0; ?>"
                                                <?php echo $line['product_id'] == $p['product_id'] ? 'selected' : ''; ?>>
                                                <?php echo h($p['sku'] . ' - ' . $p['name']); ?> (สต็อก:
                                                <?php echo number_format($p['stock_qty']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" name="items[<?php echo $index; ?>][qty]" class="form-control" min="1" 
                                        value="<?php echo $line['qty']; ?>" required>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-danger btn-sm remove-row"><i
                                            class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr class="item-row">
                            <td>
                                <select name="items[0][product_id]" class="form-control product-select" required
                                    onchange="checkStock(this)">
                                    <option value="" data-stock="0">-- เลือกสินค้า --</option>
                                    <?php foreach ($products as $p): ?>
                                        <option value="<?php echo $p['product_id']; ?>"
                                            data-stock="<?php echo $p['stock_qty'] ?? 0; ?>">
                                            <?php echo h($p['sku'] . ' - ' . $p['name']); ?> (สต็อก:
                                            <?php echo number_format($p['stock_qty']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input type="number" name="items[0][qty]" class="form-control" min="1" value="1" required>
                            </td>
                            <td>
                                <button type="button" class="btn btn-danger btn-sm remove-row"><i
                                        class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="flex justify-between">
            <button type="button" class="btn btn-secondary" id="addRowBtn"><i class="fas fa-plus"></i> เพิ่มรายการ</button>
            <button type="submit" class="btn btn-primary">
                <?php echo $id ? 'อัพเดทรายการ' : 'สร้างรายการ'; ?>
            </button>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const tableBody = document.querySelector('#itemsTable tbody');
        let rowCount = tableBody.querySelectorAll('.item-row').length; // Start from existing row count
        const addRowBtn = document.getElementById('addRowBtn');

        // PHP rendered options for JS
        const productOptions = `<?php foreach ($products as $p): ?><option value="<?php echo $p['product_id']; ?>" data-stock="<?php echo $p['stock_qty'] ?? 0; ?>"><?php echo addslashes($p['sku'] . ' - ' . $p['name'] . ' (สต็อก: ' . number_format($p['stock_qty'] ?? 0) . ')'); ?></option><?php endforeach; ?>`;

        addRowBtn.addEventListener('click', function () {
            const tr = document.createElement('tr');
            tr.className = 'item-row';
            tr.innerHTML = `
            <td>
                <select name="items[${rowCount}][product_id]" class="form-control product-select" required onchange="checkStock(this)">
                    <option value="" data-stock="0">-- เลือกสินค้า --</option>
                    ${productOptions}
                </select>
            </td>
            <td>
                <input type="number" name="items[${rowCount}][qty]" class="form-control" min="1" value="1" required>
            </td>
            <td>
                <button type="button" class="btn btn-danger btn-sm remove-row"><i class="fas fa-trash"></i></button>
            </td>
        `;
            tableBody.appendChild(tr);
            rowCount++;
        });

        tableBody.addEventListener('click', function (e) {
            if (e.target.closest('.remove-row')) {
                const rows = tableBody.querySelectorAll('.item-row');
                if (rows.length > 1) {
                    e.target.closest('tr').remove();
                } else {
                    alert("ต้องมีอย่างน้อยหนึ่งรายการ");
                }
            }
        });
    });

    function checkStock(select) {
        // Optional: Add visual warning if stock is 0
        const option = select.options[select.selectedIndex];
        const stock = parseInt(option.getAttribute('data-stock'));
        if (stock <= 0) {
            alert("คำเตือน: สินค้านี้ไม่มีสต็อก");
        }
    }
</script>

<?php include 'includes/footer.php'; ?>