<?php
require_once 'config/db.php';
requireLogin();

if ($_SESSION['role'] !== 'admin') {
    die("ไม่มีสิทธิ์เข้าถึง");
}

$id = $_GET['id'] ?? null;
$user = null;
$error = '';
$success = '';

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user)
        die("ไม่พบผู้ใช้");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verifyCsrfToken($_POST['csrf_token']);

    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password']; // Optional if editing
    $role = $_POST['role'];
    $active = isset($_POST['active']) ? 1 : 0;

    try {
        if ($id) {
            // Update
            $sql = "UPDATE users SET full_name=?, email=?, role=?, active=?, updated_at=NOW()";
            $params = [$full_name, $email, $role, $active];

            if (!empty($password)) {
                $sql .= ", password_hash=?";
                $params[] = password_hash($password, PASSWORD_DEFAULT);
            }

            $sql .= " WHERE user_id=?";
            $params[] = $id;

            $pdo->prepare($sql)->execute($params);
            $success = "อัพเดทผู้ใช้เรียบร้อย";

            // Refresh
            $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch();

        } else {
            // Create
            if (empty($password)) {
                $error = "รหัสผ่านจำเป็นสำหรับผู้ใช้ใหม่";
            } else {
                $stmt = $pdo->prepare("INSERT INTO users (username, full_name, email, password_hash, role, active) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $full_name, $email, password_hash($password, PASSWORD_DEFAULT), $role, $active]);
                header("Location: users.php");
                exit;
            }
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $error = "ชื่อผู้ใช้หรืออีเมลนี้มีอยู่แล้ว";
        } else {
            $error = "เกิดข้อผิดพลาดฐานข้อมูล: " . $e->getMessage();
        }
    }
}

include 'includes/header.php';
?>

<div class="flex justify-between items-center mb-4">
    <h2><?php echo $id ? 'แก้ไขผู้ใช้' : 'เพิ่มผู้ใช้ใหม่'; ?></h2>
    <a href="users.php" class="btn btn-secondary">กลับ</a>
</div>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <?php if ($error): ?>
        <div class="badge badge-danger mb-4" style="display:block; padding:1rem;"><?php echo h($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="badge badge-success mb-4" style="display:block; padding:1rem;"><?php echo h($success); ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

        <div class="form-group">
            <label class="form-label">ชื่อผู้ใช้ <span style="color:red">*</span></label>
            <input type="text" name="username" class="form-control" value="<?php echo h($user['username'] ?? ''); ?>"
                <?php echo $id ? 'readonly' : 'required'; ?>>
        </div>

        <div class="form-group">
            <label class="form-label">ชื่อ-นามสกุล <span style="color:red">*</span></label>
            <input type="text" name="full_name" class="form-control" value="<?php echo h($user['full_name'] ?? ''); ?>"
                required>
        </div>

        <div class="form-group">
            <label class="form-label">อีเมล <span style="color:red">*</span></label>
            <input type="email" name="email" class="form-control" value="<?php echo h($user['email'] ?? ''); ?>"
                required>
        </div>

        <div class="form-group">
            <label class="form-label">รหัสผ่าน
                <?php echo $id ? '(ว่างไว้เพื่อไม่เปลี่ยน)' : '<span style="color:red">*</span>'; ?></label>
            <input type="password" name="password" class="form-control" placeholder="รหัสผ่านใหม่">
        </div>

        <div class="flex gap-2">
            <div class="form-group flex-1">
                <label class="form-label">บทบาท</label>
                <select name="role" class="form-control">
                    <option value="office" <?php echo ($user['role'] ?? 'office') == 'office' ? 'selected' : ''; ?>>
                        สำนักงาน - พนักงานออฟฟิศ (คีย์ข้อมูล)
                    </option>
                    <option value="staff" <?php echo ($user['role'] ?? '') == 'staff' ? 'selected' : ''; ?>>
                        พนักงาน - พนักงานคลัง (รับเข้า/เบิกออก)
                    </option>
                    <option value="admin" <?php echo ($user['role'] ?? '') == 'admin' ? 'selected' : ''; ?>>
                        ผู้ดูแลระบบ - ทุกสิทธิ์
                    </option>
                </select>
                <small class="text-muted">
                    สำนักงาน=คีย์ข้อมูล | พนักงาน=ทำงานคลัง | ผู้ดูแล=ทุกอย่าง
                </small>
            </div>

            <div class="form-group flex-1">
                <label class="form-label">สถานะ</label>
                <div class="flex items-center" style="height:45px;">
                    <label class="flex gap-2 items-center cursor-pointer">
                        <input type="checkbox" name="active" value="1" <?php echo ($user['active'] ?? 1) ? 'checked' : ''; ?>>
                        ใช้งาน
                    </label>
                </div>
            </div>
        </div>

        <div class="text-right mt-4">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> บันทึกผู้ใช้</button>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>