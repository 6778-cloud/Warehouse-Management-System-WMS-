<?php
require_once 'config/db.php';
requireLogin();

// Filter
$search = $_GET['search'] ?? '';
$limit = 20;
$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (u.username LIKE :s OR a.action LIKE :s OR a.details LIKE :s)";
    $params[':s'] = "%$search%";
}

// Pagination
$page = $_GET['page'] ?? 1;
$offset = ($page - 1) * $limit;

// Count Total
$countSql = "SELECT COUNT(*) FROM audit_logs a LEFT JOIN users u ON a.user_id = u.user_id $where";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total_rows = $stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Main Query
$sql = "SELECT a.*, u.username, u.role 
            FROM audit_logs a
            LEFT JOIN users u ON a.user_id = u.user_id
            $where
            ORDER BY a.created_at DESC
            LIMIT $limit OFFSET $offset";
$logs = $pdo->prepare($sql);
$logs->execute($params);
$rows = $logs->fetchAll();

include 'includes/header.php';
?>

<div class="flex justify-between items-center mb-4">
    <div>
        <h2>ประวัติการใช้งานระบบ</h2>
        <div class="text-muted" style="font-size:0.85rem;">ติดตามกิจกรรมและเหตุการณ์ความปลอดภัยของระบบ</div>
    </div>
    <div class="flex gap-2">
        <a href="cycle_count.php" class="btn btn-primary"><i class="fas fa-clipboard-check"></i> ตรวจนับสต็อก</a>
    </div>
</div>

<div class="card">
    <div class="flex justify-between items-center mb-4">
        <form class="flex gap-2" style="width: 100%; max-width: 400px;">
            <input type="text" name="search" class="form-control" placeholder="ค้นหา (ผู้ใช้, การกระทำ, รายละเอียด)..."
                value="<?php echo h($search); ?>">
            <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i></button>
        </form>
        <div class="text-muted" style="font-size:0.85rem;">
            ทั้งหมด: <span
                style="color:var(--primary-color); font-weight:bold;"><?php echo number_format($total_rows); ?></span>
            รายการ
        </div>
    </div>

    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th width="140">เวลา</th>
                    <th width="150">ผู้ใช้</th>
                    <th width="120">การกระทำ</th>
                    <th width="150">ข้อมูล</th>
                    <th>รายละเอียด</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($rows) > 0): ?>
                    <?php foreach ($rows as $r): ?>
                        <?php
                        // Translate actions
                        $actionText = $r['action'];
                        if (strpos(strtolower($r['action']), 'create') !== false)
                            $actionText = 'สร้าง';
                        elseif (strpos(strtolower($r['action']), 'delete') !== false)
                            $actionText = 'ลบ';
                        elseif (strpos(strtolower($r['action']), 'update') !== false)
                            $actionText = 'แก้ไข';
                        elseif (strpos(strtolower($r['action']), 'login') !== false)
                            $actionText = 'เข้าสู่ระบบ';
                        elseif (strpos(strtolower($r['action']), 'receive') !== false)
                            $actionText = 'รับสินค้า';
                        elseif (strpos(strtolower($r['action']), 'pick') !== false)
                            $actionText = 'หยิบสินค้า';
                        elseif (strpos(strtolower($r['action']), 'adjust') !== false)
                            $actionText = 'ปรับปรุง';
                        ?>
                        <tr>
                            <td>
                                <div style="font-family: 'Courier New', monospace; font-size:0.8rem; color:var(--info-color);">
                                    <?php echo date('Y-m-d', strtotime($r['created_at'])); ?>
                                </div>
                                <div
                                    style="font-family: 'Courier New', monospace; font-size:0.9rem; font-weight:bold; color:var(--text-main);">
                                    <?php echo date('H:i:s', strtotime($r['created_at'])); ?>
                                </div>
                            </td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <div
                                        style="width:24px; height:24px; background:rgba(255,255,255,0.1); border-radius:50%; display:flex; align-items:center; justify-content:center;">
                                        <i class="fas fa-user" style="font-size:0.7rem;"></i>
                                    </div>
                                    <div>
                                        <span style="font-weight:600; color:var(--text-main);">
                                            <?php echo h($r['username'] ?? 'ระบบ'); ?>
                                        </span>
                                        <?php if (($r['role'] ?? '') == 'admin'): ?>
                                            <i class="fas fa-shield-alt"
                                                style="font-size:0.7rem; color:var(--warning-color); margin-left:4px;"
                                                title="ผู้ดูแลระบบ"></i>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php
                                $act = strtolower($r['action']);
                                $color = 'badge-info';
                                $icon = 'fa-info-circle';

                                if (strpos($act, 'delete') !== false) {
                                    $color = 'badge-danger';
                                    $icon = 'fa-trash';
                                } elseif (strpos($act, 'adjust') !== false) {
                                    $color = 'badge-warning';
                                    $icon = 'fa-sliders-h';
                                } elseif (strpos($act, 'create') !== false) {
                                    $color = 'badge-success';
                                    $icon = 'fa-plus';
                                } elseif (strpos($act, 'receive') !== false) {
                                    $color = 'badge-success';
                                    $icon = 'fa-box-open';
                                } elseif (strpos($act, 'pick') !== false) {
                                    $color = 'badge-info text-white';
                                    $icon = 'fa-hand-holding-box';
                                } elseif (strpos($act, 'login') !== false) {
                                    $color = 'badge-warning';
                                    $icon = 'fa-key';
                                }
                                ?>
                                <span class="badge <?php echo $color; ?>">
                                    <i class="fas <?php echo $icon; ?>" style="margin-right:4px;"></i><?php echo $actionText; ?>
                                </span>
                            </td>
                            <td>
                                <span
                                    style="background:rgba(255,255,255,0.05); padding:2px 6px; border-radius:4px; font-size:0.8rem; font-family:monospace;">
                                    <?php echo h(strtoupper($r['entity_type'])); ?> #<?php echo h($r['entity_id']); ?>
                                </span>
                            </td>
                            <td style="color:var(--text-muted); font-size:0.9rem;">
                                <?php echo h($r['details']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center" style="padding:3rem;">
                            <i class="fas fa-history"
                                style="font-size:3rem; color:rgba(255,255,255,0.1); margin-bottom:1rem;"></i>
                            <div class="text-muted">ไม่พบประวัติการใช้งานที่ตรงกับเงื่อนไข</div>
                        </td>
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