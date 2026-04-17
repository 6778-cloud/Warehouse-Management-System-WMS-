<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WMS Smart - ระบบจัดการคลังสินค้าอัจฉริยะ</title>

    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
</head>

<body>
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="toggle-sidebar">
                    <i class="fas fa-bars"></i>
                </div>

                <div class="user-menu" style="display: flex; align-items: center; gap: 15px;">
                    <button id="theme-toggle" class="btn btn-secondary btn-sm">
                        <i class="fas fa-sun"></i> Light Mode
                    </button>

                    <?php if (isset($_SESSION['user_id'])): ?>
                        <span>สวัสดี, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                        <a href="logout.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-sign-out-alt"></i> ออกจากระบบ
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if (isset($_SESSION['alert'])): ?>
                <div class="alert alert-<?php echo $_SESSION['alert']['type']; ?>" style="margin: 1rem;">
                    <?php echo $_SESSION['alert']['message']; ?>
                </div>
                <?php unset($_SESSION['alert']); // Clear after display ?>
            <?php endif; ?>

            <div class="content-area">