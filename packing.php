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

if (!$order || $order['status'] != 'picked') {
    die("ไม่พบรายการหรือไม่อยู่ในสถานะ 'หยิบแล้ว' (สถานะ: " . ($order['status'] ?? 'ไม่มี') . ")");
}

// Prevent access for internal orders (they skip verification)
if ($order['order_type'] == 'internal') {
    die("คำสั่งภายในไม่ต้องตรวจสอบ คำสั่งนี้ควรเสร็จสิ้นแล้ว");
}

// Fetch Lines with Product Info
$stmt = $pdo->prepare("SELECT l.*, p.sku, p.name as product_name, p.barcode 
                       FROM outbound_lines l 
                       JOIN products p ON l.product_id = p.product_id 
                       WHERE l.outbound_id = ?");
$stmt->execute([$id]);
$lines = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle POST completion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_ship'])) {
    verifyCsrfToken($_POST['csrf_token']);

    try {
        $pdo->prepare("UPDATE outbound_orders SET status = 'shipped' WHERE outbound_id = ?")->execute([$id]);

        // Log
        $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details) VALUES (?, 'pack', 'outbound', ?, ?)")
            ->execute([$_SESSION['user_id'], $id, "แพ็คและส่งคำสั่ง #$id"]);

        // กลับไปหน้า Outbound List (ไม่ redirect ไป shipment_form โดยตรง)
        // พนักงานคีย์ข้อมูลจะไปคีย์ shipment ในหน้า Shipments แยก
        header("Location: outbound.php");
        exit;
    } catch (Exception $e) {
        $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

include 'includes/header.php';
?>

<div class="flex justify-between items-center mb-4">
    <div>
        <h2><i class="fas fa-check-double"></i> แพ็คและตรวจสอบ: <?php echo h($order['order_no']); ?></h2>
        <p class="text-muted">สแกนสินค้าเพื่อตรวจสอบก่อนจัดส่ง</p>
    </div>
    <a href="outbound.php" class="btn btn-secondary">ยกเลิก</a>
</div>

<div class="card mb-4" style="background: var(--card-bg);">
    <div class="flex justify-between items-end">
        <div style="flex:1; margin-right: 1rem;">
            <label class="block text-sm font-medium mb-1">สแกน / ใส่บาร์โค้ดหรือ SKU</label>
            <input type="text" id="scanInput" class="form-control" placeholder="โฟกัสที่นี่แล้วสแกน..." autofocus
                autocomplete="off">
            <div id="scanMessage" style="height: 1.5rem; margin-top: 0.5rem; font-weight: bold;"></div>
        </div>
        <div class="text-right">
            <div class="text-2xl font-bold" id="progressText">0 / 0</div>
            <div class="text-sm text-muted">สินค้าตรวจสอบแล้ว</div>
        </div>
    </div>
</div>

<div class="card">
    <table class="table" id="packingTable">
        <thead>
            <tr>
                <th>สินค้า</th>
                <th>SKU</th>
                <th>บาร์โค้ด</th>
                <th>จำนวนสั่ง</th>
                <th>จำนวนสแกน</th>
                <th>สถานะ</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $totalItems = 0;
            foreach ($lines as $line):
                $totalItems += $line['qty'];
                ?>
                <tr data-sku="<?php echo h($line['sku']); ?>" data-barcode="<?php echo h($line['barcode']); ?>"
                    data-required="<?php echo $line['qty']; ?>" data-scanned="0" class="item-row">
                    <td><?php echo h($line['product_name']); ?></td>
                    <td class="font-mono text-primary"><?php echo h($line['sku']); ?></td>
                    <td class="font-mono text-muted"><?php echo h($line['barcode'] ?? '-'); ?></td>
                    <td class="font-bold text-center"><?php echo $line['qty']; ?></td>
                    <td class="text-center font-bold text-xl"><span class="scanned-display">0</span></td>
                    <td class="status-cell"><span class="badge badge-secondary">รอ</span></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <form method="post" id="completeForm" class="mt-4 hidden text-right">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
        <div class="p-4"
            style="background: rgba(16, 185, 129, 0.1); border: 1px solid var(--success-color); border-radius: 8px; display: inline-block;">
            <div class="mb-2 text-success font-bold"><i class="fas fa-check-circle"></i> ตรวจสอบครบแล้ว!</div>
            <button type="submit" name="confirm_ship" class="btn btn-success btn-lg">
                <i class="fas fa-truck"></i> ยืนยันและจัดส่ง
            </button>
        </div>
    </form>
</div>

<script>
    const rows = document.querySelectorAll('.item-row');
    const input = document.getElementById('scanInput');
    const msg = document.getElementById('scanMessage');
    const progressText = document.getElementById('progressText');
    const completeForm = document.getElementById('completeForm');
    const totalRequired = <?php echo $totalItems; ?>;
    let totalScanned = 0;

    // Map barcodes/SKUs to rows for O(1) lookup
    const itemMap = {};

    rows.forEach(row => {
        const sku = row.dataset.sku.toUpperCase();
        const barcode = row.dataset.barcode ? row.dataset.barcode.toUpperCase() : null;

        // Support lookup by both SKU and Barcode
        if (!itemMap[sku]) itemMap[sku] = [];
        itemMap[sku].push(row);

        if (barcode) {
            if (!itemMap[barcode]) itemMap[barcode] = [];
            itemMap[barcode].push(row);
        }
    });

    updateProgress();

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            const val = this.value.trim().toUpperCase();
            this.value = ''; // Clear immediately

            if (!val) return;

            processScan(val);
        }
    });

    function processScan(code) {
        const matchedRows = itemMap[code];

        if (!matchedRows) {
            showMsg("ไม่พบสินค้า! " + code, "text-danger");
            playErrorSound();
            return;
        }

        // Find the first row that isn't full yet
        let targetRow = null;
        for (let row of matchedRows) {
            const req = parseInt(row.dataset.required);
            const cur = parseInt(row.dataset.scanned);
            if (cur < req) {
                targetRow = row;
                break;
            }
        }

        if (!targetRow) {
            showMsg("สินค้านี้สแกนครบแล้ว!", "text-warning");
            playErrorSound();
            return;
        }

        // Update Row
        let current = parseInt(targetRow.dataset.scanned);
        current++;
        targetRow.dataset.scanned = current;
        targetRow.querySelector('.scanned-display').textContent = current;

        // Check row status
        const required = parseInt(targetRow.dataset.required);
        const statusCell = targetRow.querySelector('.status-cell');

        if (current >= required) {
            targetRow.style.backgroundColor = "rgba(16, 185, 129, 0.1)";
            statusCell.innerHTML = '<span class="badge badge-success">ตรวจแล้ว</span>';
        } else {
            statusCell.innerHTML = '<span class="badge badge-info">กำลังสแกน...</span>';
        }

        // Global Success
        showMsg("สแกน: " + code, "text-success");
        totalScanned++;
        updateProgress();
        playSuccessSound();

        checkCompletion();
    }

    function updateProgress() {
        progressText.textContent = totalScanned + " / " + totalRequired;
    }

    function checkCompletion() {
        if (totalScanned >= totalRequired) {
            completeForm.classList.remove('hidden');
            input.disabled = true;
            showMsg("สินค้าทั้งหมดตรวจสอบแล้ว!", "text-success");
        }
    }

    function showMsg(text, cls) {
        msg.className = cls;
        msg.textContent = text;
        // Clear message after 3s
        setTimeout(() => {
            if (msg.textContent === text) msg.textContent = '';
        }, 3000);
    }

    // Simple Audio Feedback
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    function beep(freq, type, duration) {
        if (ctx.state === 'suspended') ctx.resume();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = type;
        osc.frequency.value = freq;
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        gain.gain.exponentialRampToValueAtTime(0.00001, ctx.currentTime + duration);
        osc.stop(ctx.currentTime + duration);
    }

    function playSuccessSound() {
        beep(800, 'sine', 0.1);
    }

    function playErrorSound() {
        beep(200, 'sawtooth', 0.3);
    }

    // Keep focus (optional simple implementation)
    document.addEventListener('click', () => {
        if (!completeForm.classList.contains('hidden')) return;
        // input.focus(); // Optional: aggressive focus
    });
</script>

<style>
    .hidden {
        display: none !important;
    }
</style>

<?php include 'includes/footer.php'; ?>