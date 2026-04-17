<?php
require_once 'config/db.php';
requireLogin();

// Only Admin access
if ($_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

$users = $pdo->query("SELECT * FROM users ORDER BY username")->fetchAll();

include 'includes/header.php';
?>

<div class="flex justify-between items-center mb-4">
    <h2>จัดการผู้ใช้</h2>
    <a href="user_form.php" class="btn btn-primary"><i class="fas fa-user-plus"></i> เพิ่มผู้ใช้</a>
</div>

<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>รหัส</th>
                    <th>ชื่อผู้ใช้</th>
                    <th>ชื่อ-นามสกุล</th>
                    <th>อีเมล</th>
                    <th>บทบาท</th>
                    <th>สถานะ</th>
                    <th>เข้าสู่ระบบล่าสุด</th>
                    <th class="text-right">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <?php
                    // Translate role
                    $roleText = $u['role'];
                    if ($u['role'] == 'admin')
                        $roleText = 'ผู้ดูแลระบบ';
                    elseif ($u['role'] == 'staff')
                        $roleText = 'พนักงาน';
                    elseif ($u['role'] == 'office')
                        $roleText = 'สำนักงาน';
                    ?>
                    <tr>
                        <td>#<?php echo $u['user_id']; ?></td>
                        <td style="font-weight:bold; color:var(--primary-color);"><?php echo h($u['username']); ?></td>
                        <td><?php echo h($u['full_name']); ?></td>
                        <td><?php echo h($u['email']); ?></td>
                        <td>
                            <span class="badge <?php echo $u['role'] == 'admin' ? 'badge-warning' : 'badge-info'; ?>">
                                <?php echo $roleText; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($u['active']): ?>
                                <span class="badge badge-success">ใช้งาน</span>
                            <?php else: ?>
                                <span class="badge badge-danger">ปิดใช้งาน</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $u['last_login'] ? date('d/m/Y H:i', strtotime($u['last_login'])) : '-'; ?></td>
                        <td class="text-right">
                            <a href="user_form.php?id=<?php echo $u['user_id']; ?>" class="btn btn-secondary btn-sm"><i
                                    class="fas fa-edit"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>