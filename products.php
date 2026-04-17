<?php
require_once 'config/db.php';
requireLogin();

$search = $_GET['search'] ?? '';
$page = $_GET['page'] ?? 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build Query
$sql = "SELECT p.*, c.name as category_name, 
        (SELECT SUM(qty) FROM stock WHERE stock.product_id = p.product_id) as total_stock
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.category_id
        WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (p.name LIKE :search OR p.sku LIKE :search OR p.barcode LIKE :search)";
    $params[':search'] = "%$search%";
}

// Count for pagination
$count_sql = str_replace("p.*, c.name as category_name, (SELECT SUM(qty) FROM stock WHERE stock.product_id = p.product_id) as total_stock", "COUNT(*)", $sql);
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_rows = $stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Main Query
$sql .= " ORDER BY p.product_id DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="flex justify-between items-center mb-4">
    <h2>จัดการสินค้า</h2>
    <a href="product_form.php" class="btn btn-primary"><i class="fas fa-plus"></i> เพิ่มสินค้า</a>
</div>

<div class="card">
    <div class="flex justify-between items-center mb-4">
        <form method="get" class="flex gap-2" style="width: 100%; max-width: 400px;">
            <input type="text" name="search" class="form-control" placeholder="ค้นหา SKU, ชื่อ, บาร์โค้ด..."
                value="<?php echo h($search); ?>">
            <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i></button>
        </form>
    </div>

    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>รูปภาพ</th>
                    <th>รหัสสินค้า</th>
                    <th>ชื่อสินค้า</th>
                    <th>หมวดหมู่</th>
                    <th class="text-center">สต็อก</th>

                    <th class="text-right">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($products) > 0): ?>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td>
                                <?php if ($p['image_path']): ?>
                                    <img src="<?php echo h($p['image_path']); ?>" alt="img"
                                        style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                                <?php else: ?>
                                    <div
                                        style="width: 40px; height: 40px; background: #f1f5f9; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #cbd5e1;">
                                        <i class="fas fa-image"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-info"><?php echo h($p['sku']); ?></span></td>
                            <td><?php echo h($p['name']); ?></td>
                            <td><?php echo h($p['category_name'] ?? '-'); ?></td>
                            <td class="text-center">
                                <?php
                                $stock = $p['total_stock'] ?? 0;
                                $class = ($stock <= $p['min_stock']) ? 'badge-danger' : 'badge-success';
                                ?>
                                <span class="badge <?php echo $class; ?>"><?php echo number_format($stock); ?>
                                    <?php echo h($p['unit']); ?></span>
                            </td>

                            <td class="text-right">
                                <a href="product_form.php?id=<?php echo $p['product_id']; ?>" class="btn btn-secondary"
                                    style="padding: 0.25rem 0.5rem;"><i class="fas fa-edit"></i></a>
                                <a href="product_delete.php?id=<?php echo $p['product_id']; ?>&token=<?php echo generateCsrfToken(); ?>"
                                    class="btn btn-danger" style="padding: 0.25rem 0.5rem;"
                                    onclick="return confirm('คุณแน่ใจหรือไม่ที่จะลบ?');"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center" style="padding: 2rem; color: #64748b;">ไม่พบสินค้า</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div style="display: flex; justify-content: center; gap: 0.5rem; margin-top: 1.5rem;">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"
                    class="btn <?php echo ($i == $page) ? 'btn-primary' : 'btn-secondary'; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>