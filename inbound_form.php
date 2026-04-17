<?php
require_once 'config/db.php';
requireLogin();

// Fetch Vendors and Products for dropdowns
$vendors = $pdo->query("SELECT * FROM vendors ORDER BY name")->fetchAll();
$products = $pdo->query("SELECT product_id, sku, name FROM products ORDER BY name")->fetchAll();

$id = $_GET['id'] ?? null;
$order = null;
$order_items = [];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM inbound_orders WHERE inbound_id = ?");
    $stmt->execute([$id]);
    $order = $stmt->fetch();

    if (!$order)
        die("ไม่พบรายการ");
    if ($order['status'] != 'draft')
        die("ไม่สามารถแก้ไขรายการที่ไม่ใช่ร่างได้");

    $stmtLines = $pdo->prepare("SELECT * FROM inbound_lines WHERE inbound_id = ?");
    $stmtLines->execute([$id]);
    $order_items = $stmtLines->fetchAll();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verifyCsrfToken($_POST['csrf_token']);

    $vendor_id = null;
    $new_vendor_name = trim($_POST['new_vendor'] ?? '');

    if (!empty($new_vendor_name)) {
        // Create new vendor
        $checkVen = $pdo->prepare("SELECT vendor_id FROM vendors WHERE name = ?");
        $checkVen->execute([$new_vendor_name]);
        $existingVen = $checkVen->fetchColumn();

        if ($existingVen) {
            $vendor_id = $existingVen;
        } else {
            $stmtVen = $pdo->prepare("INSERT INTO vendors (name) VALUES (?)");
            $stmtVen->execute([$new_vendor_name]);
            $vendor_id = $pdo->lastInsertId();
        }
    } else {
        $vendor_id = !empty($_POST['vendor_id']) ? $_POST['vendor_id'] : null;
    }

    $po_no = $_POST['po_no'];
    $receive_date = $_POST['receive_date'];
    $items = $_POST['items'] ?? []; // Array of [product_id, qty]

    if (empty($vendor_id) || empty($items)) {
        $error = "กรุณาเลือกผู้จำหน่ายและรายการอย่างน้อยหนึ่งรายการ";
    } else {
        try {
            $pdo->beginTransaction();

            if ($id) {
                // UPDATE
                $stmt = $pdo->prepare("UPDATE inbound_orders SET vendor_id=?, po_no=?, receive_date=? WHERE inbound_id=?");
                $stmt->execute([$vendor_id, $po_no, $receive_date, $id]);
                $inbound_id = $id;

                // Clear old lines to re-insert (simplest way for update)
                $pdo->prepare("DELETE FROM inbound_lines WHERE inbound_id=?")->execute([$id]);
            } else {
                // INSERT
                $stmt = $pdo->prepare("INSERT INTO inbound_orders (vendor_id, po_no, receive_date, created_by, status) VALUES (?, ?, ?, ?, 'draft')");
                $stmt->execute([$vendor_id, $po_no, $receive_date, $_SESSION['user_id']]);
                $inbound_id = $pdo->lastInsertId();
            }

            $stmtLine = $pdo->prepare("INSERT INTO inbound_lines (inbound_id, product_id, expected_qty) VALUES (?, ?, ?)");
            foreach ($items as $item) {
                if (!empty($item['product_id']) && $item['qty'] > 0) {
                    $stmtLine->execute([$inbound_id, $item['product_id'], $item['qty']]);
                }
            }

            $pdo->commit();
            header("Location: inbound.php");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}

include 'includes/header.php';
?>

<div class="flex justify-between items-center mb-4">
    <h2><?php echo $id ? 'แก้ไขรายการรับเข้า #' . $id : 'สร้างรายการรับสินค้าเข้า'; ?></h2>
    <a href="inbound.php" class="btn btn-secondary">ยกเลิก</a>
</div>

<div class="card">
    <?php if ($error): ?>
        <div class="badge badge-danger mb-4" style="display:block; padding: 1rem;"><?php echo h($error); ?></div>
    <?php endif; ?>

    <form method="post" id="inboundForm">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

        <div class="flex gap-2 mb-4">
            <div class="form-group" style="flex:1">
                <label class="form-label">ผู้จำหน่าย <span style="color:red">*</span></label>
                <select name="vendor_id" class="form-control" required onchange="toggleVendorInput(this)">
                    <option value="">-- เลือกผู้จำหน่าย --</option>
                    <?php foreach ($vendors as $v): ?>
                        <option value="<?php echo $v['vendor_id']; ?>" <?php echo ($order && $order['vendor_id'] == $v['vendor_id']) ? 'selected' : ''; ?>>
                            <?php echo h($v['name']); ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="new">+ สร้างผู้จำหน่ายใหม่</option>
                </select>
                <input type="text" name="new_vendor" id="newVendorInput" class="form-control mt-2"
                    placeholder="ใส่ชื่อผู้จำหน่ายใหม่" style="display:none; margin-top:0.5rem;">
            </div>
            <div class="form-group" style="flex:1">
                <label class="form-label">เลขที่ PO</label>
                <input type="text" name="po_no" class="form-control" value="<?php echo h($order['po_no'] ?? ''); ?>">
            </div>
            <div class="form-group" style="flex:1">
                <label class="form-label">วันที่คาดว่าจะรับ</label>
                <input type="date" name="receive_date" class="form-control"
                    value="<?php echo h($order['receive_date'] ?? date('Y-m-d')); ?>">
            </div>
        </div>

        <h4 class="mb-4">รายการสินค้า</h4>
        <div class="table-container mb-4">
            <table class="table" id="itemsTable">
                <thead>
                    <tr>
                        <th>สินค้า</th>
                        <th width="150">จำนวนที่คาด</th>
                        <th width="50"></th>
                    </tr>
                </thead>
                <tbody>
                    <!-- JS will populate -->
                </tbody>
            </table>
        </div>

        <div class="flex justify-between">
            <button type="button" class="btn btn-secondary" id="addRowBtn"><i class="fas fa-plus"></i>
                เพิ่มรายการ</button>
            <button type="submit" class="btn btn-primary"><?php echo $id ? 'อัพเดทรายการ' : 'สร้างรายการ'; ?></button>
        </div>
    </form>
</div>

<script>
    function toggleVendorInput(select) {
        const input = document.getElementById('newVendorInput');
        if (select.value === 'new') {
            input.style.display = 'block';
            input.required = true;
            input.focus();
        } else {
            input.style.display = 'none';
            input.required = false;
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const tableBody = document.querySelector('#itemsTable tbody');
        const addRowBtn = document.getElementById('addRowBtn');
        let rowCount = 0;

        // Product options HTML
        const productOptions = `<?php foreach ($products as $p): ?><option value="<?php echo $p['product_id']; ?>"><?php echo addslashes($p['sku'] . ' - ' . $p['name']); ?></option><?php endforeach; ?>`;

        // Pre-loaded items from PHP (for Edit mode)
        const initialItems = <?php echo json_encode($order_items); ?>;

        function addRow(item = null) {
            const tr = document.createElement('tr');
            tr.className = 'item-row';

            const prodId = item ? item.product_id : '';
            const qty = item ? item.expected_qty : 1;

            tr.innerHTML = `
            <td>
                <select name="items[${rowCount}][product_id]" class="form-control product-select" required>
                    <option value="">-- เลือกสินค้า --</option>
                    ${productOptions}
                </select>
            </td>
            <td>
                <input type="number" name="items[${rowCount}][qty]" class="form-control" min="1" value="${qty}" required>
            </td>
            <td>
                <button type="button" class="btn btn-danger btn-sm remove-row"><i class="fas fa-trash"></i></button>
            </td>
            `;

            tableBody.appendChild(tr);

            // Set Selected Product
            if (prodId) {
                const select = tr.querySelector('.product-select');
                select.value = prodId;
            }

            rowCount++;
        }

        // Init: Load existing items OR add 1 empty row
        if (initialItems.length > 0) {
            initialItems.forEach(item => addRow(item));
        } else {
            addRow();
        }

        addRowBtn.addEventListener('click', () => addRow());

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
</script>

<?php include 'includes/footer.php'; ?>