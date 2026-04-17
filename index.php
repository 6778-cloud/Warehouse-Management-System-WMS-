<?php
require_once 'config/db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "กรุณากรอกชื่อผู้ใช้และรหัสผ่าน";
    } else {
        // Prepare statement
        $stmt = $pdo->prepare("SELECT user_id, username, full_name, password_hash, role, active FROM users WHERE username = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $user = $stmt->fetch();

            // Verify password
            if (password_verify($password, $user['password_hash'])) {
                if ($user['active'] == 1) {
                    // Password correct, start session
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role'];

                    // Update last login
                    $update = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = :uid");
                    $update->execute([':uid' => $user['user_id']]);

                    header("Location: dashboard.php");
                    exit;
                } else {
                    $error = "บัญชีของคุณถูกปิดใช้งาน";
                }
            } else {
                $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
            }
        } else {
            $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - WMS Smart</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
</head>

<body>
    <div class="login-page">
        <!-- Theme Toggle -->
        <div style="position: absolute; top: 20px; right: 20px;">
            <button id="theme-toggle" class="btn btn-secondary btn-sm"
                style="background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.2); color: white;">
                <i class="fas fa-sun"></i> Light Mode
            </button>
        </div>

        <div class="login-card">

            <div class="brand-logo">WMS Smart</div>

            <?php if ($error): ?>
                <div class="badge badge-danger"
                    style="display:block; margin-bottom:1rem; text-align:center; padding:0.5rem;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form action="" method="post">
                <div class="form-group">
                    <label class="form-label">ชื่อผู้ใช้</label>
                    <input type="text" name="username" class="form-control" required autofocus>
                </div>

                <div class="form-group">
                    <label class="form-label">รหัสผ่าน</label>
                    <input type="password" name="password" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%; margin-top:1rem;">เข้าสู่ระบบ</button>
            </form>
        </div>
    </div>
    <script>
        const themeBtn = document.getElementById('theme-toggle');
        const body = document.body;

        // Load saved theme
        if (localStorage.getItem('theme') === 'light') {
            body.classList.add('light-mode');
            updateButton(true);
        }

        themeBtn.addEventListener('click', () => {
            body.classList.toggle('light-mode');
            const isLight = body.classList.contains('light-mode');
            localStorage.setItem('theme', isLight ? 'light' : 'dark');
            updateButton(isLight);
        });

        function updateButton(isLight) {
            if (isLight) {
                themeBtn.innerHTML = '<i class="fas fa-moon"></i> Dark Mode';
                themeBtn.style.color = '#334155';
                themeBtn.style.background = 'white';
                themeBtn.style.borderColor = '#cbd5e1';
            } else {
                themeBtn.innerHTML = '<i class="fas fa-sun"></i> Light Mode';
                themeBtn.style.color = 'white';
                themeBtn.style.background = 'rgba(255,255,255,0.1)';
                themeBtn.style.borderColor = 'rgba(255,255,255,0.2)';
            }
        }
    </script>
</body>

</html>