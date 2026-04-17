<?php
require_once 'config/db.php';
requireLogin();

$id = $_GET['id'] ?? null;
if (!$id)
    die("ต้องระบุรหัสสินค้า");

$stmt = $pdo->prepare("SELECT p.*, c.name as category_name, v.name as vendor_name 
                       FROM products p 
                       LEFT JOIN categories c ON p.category_id = c.category_id 
                       LEFT JOIN vendors v ON p.vendor_id = v.vendor_id 
                       WHERE p.product_id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product)
    die("ไม่พบสินค้า");

// Current Stock
$stmtStock = $pdo->prepare("SELECT s.*, l.code as location_code 
                            FROM stock s 
                            JOIN locations l ON s.location_id = l.location_id 
                            WHERE s.product_id = ? AND s.qty > 0");
$stmtStock->execute([$id]);
$stocks = $stmtStock->fetchAll();

include 'includes/header.php';
?>

<div class="flex justify-between items-center mb-4">
    <h2>รายละเอียดสินค้า</h2>
    <a href="products.php" class="btn btn-secondary">กลับไปรายการ</a>
</div>

<div class="card">
    <div class="flex gap-2" style="align-items: flex-start;">
        <div style="flex: 0 0 200px;">
            <?php if ($product['image_path']): ?>
                <img src="<?php echo h($product['image_path']); ?>"
                    style="width:100%; border-radius:var(--radius-md); border:1px solid var(--border-color);">
            <?php else: ?>
                <div
                    style="width:100%; height:200px; background:rgba(255,255,255,0.05); display:flex; align-items:center; justify-content:center; color:var(--text-muted);">
                    ไม่มีรูป
                </div>
            <?php endif; ?>
        </div>

        <div style="flex: 1; padding-left: 2rem;">
            <h1 style="color:var(--primary-color); margin-bottom:0.5rem;"><?php echo h($product['name']); ?></h1>
            <div class="text-muted mb-4"><?php echo h($product['sku']); ?></div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem; margin-bottom:1rem;">
                <div><strong>หมวดหมู่:</strong> <?php echo h($product['category_name'] ?? '-'); ?></div>
                <div><strong>ผู้จำหน่าย:</strong> <?php echo h($product['vendor_name'] ?? '-'); ?></div>
                <div><strong>สต็อกขั้นต่ำ:</strong> <?php echo number_format($product['min_stock']); ?></div>
                <div><strong>หน่วย:</strong> <?php echo h($product['unit']); ?></div>
                <div><strong>ขนาด:</strong>
                    <?php echo h($product['length'] . 'x' . $product['width'] . 'x' . $product['height']); ?> ซม.</div>
            </div>

            <div class="mt-4 p-4" style="background:rgba(0,0,0,0.2); border-radius:var(--radius-md);">
                <h4>บาร์โค้ด / QR Code</h4>
                <div class="flex items-center gap-2">
                    <!-- Simple Barcode Generation using distinct visual -->
                    <div style="background:white; padding:10px; display:inline-block;">
                        <img src="https://barcode.tec-it.com/barcode.ashx?data=<?php echo urlencode($product['sku']); ?>&code=Code128&dpi=96"
                            alt="บาร์โค้ด">
                    </div>
                </div>
                <div class="mt-2 text-muted" style="font-size:0.8rem;">
                    *สร้างอัตโนมัติจาก SKU
                </div>
                <button class="btn btn-sm btn-primary mt-2" onclick="printBarcode()"><i class="fas fa-print"></i>
                    พิมพ์ฉลาก</button>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4">
    <h3>สต็อกปัจจุบัน</h3>
    <table class="table">
        <thead>
            <tr>
                <th>ตำแหน่ง</th>
                <th>จำนวน</th>
                <th>Lot / วันหมดอายุ</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($stocks as $s): ?>
                <tr>
                    <td style="color:var(--primary-color); font-weight:bold;"><?php echo h($s['location_code']); ?></td>
                    <td><?php echo number_format($s['qty']); ?></td>
                    <td><?php echo h($s['lot_no']); ?> / <?php echo h($s['expiry_date']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
    function printBarcode() {
        const w = window.open('', '_blank');
        const barcodeUrl = "https://barcode.tec-it.com/barcode.ashx?data=<?php echo urlencode($product['sku']); ?>&code=Code128&dpi=96";
        const name = "<?php echo addslashes($product['name']); ?>";
        const sku = "<?php echo addslashes($product['sku']); ?>";

        w.document.write(`
        <html>
        <body style="text-align:center; font-family:sans-serif;">
            <div style="border:1px solid #000; padding:20px; display:inline-block;">
                <h2 style="margin:0 0 10px 0;">${name}</h2>
                <img src="${barcodeUrl}">
                <p style="margin:10px 0 0 0;">${sku}</p>
            </div>
            <script>window.print();<\/script>
        </body>
        </html>
    `);
        w.document.close();
    }
</script>

<?php include 'includes/footer.php'; ?>