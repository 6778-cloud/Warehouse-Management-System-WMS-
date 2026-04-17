<?php
require_once 'config/db.php';
requireLogin();

$search = $_GET['search'] ?? '';
$sql = "SELECT * FROM vendors WHERE name LIKE :search ORDER BY name";
$stmt = $pdo->prepare($sql);
$stmt->execute([':search' => "%$search%"]);
$vendors = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="flex justify-between items-center mb-4">
    <h2>ผู้จำหน่าย</h2>
    <a href="vendor_form.php" class="btn btn-primary"><i class="fas fa-plus"></i> เพิ่มผู้จำหน่าย</a>
</div>

<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>ชื่อ</th>
                    <th>ผู้ติดต่อ</th>
                    <th>โทรศัพท์</th>
                    <th>อีเมล</th>
                    <th class="text-right">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vendors as $v): ?>
                    <tr>
                        <td><?php echo h($v['name']); ?></td>
                        <td><?php echo h($v['contact']); ?></td>
                        <td><?php echo h($v['phone']); ?></td>
                        <td><?php echo h($v['email']); ?></td>
                        <td class="text-right">
                            <a href="vendor_form.php?id=<?php echo $v['vendor_id']; ?>" class="btn btn-secondary btn-sm"><i
                                    class="fas fa-edit"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>