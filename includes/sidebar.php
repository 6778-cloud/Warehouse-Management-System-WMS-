<?php
// Get current page to set active class
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? 'staff';
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <i class="fas fa-box-open" style="margin-right: 10px;"></i> WMS Smart
    </div>

    <ul class="nav-links">
        <li class="nav-item">
            <a href="dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                <div class="nav-icon"><i class="fas fa-chart-line"></i></div>
                <span>แดชบอร์ด</span>
            </a>
        </li>

        <li class="nav-item">
            <a href="products.php" class="<?php echo $current_page == 'products.php' ? 'active' : ''; ?>">
                <div class="nav-icon"><i class="fas fa-boxes"></i></div>
                <span>สินค้า</span>
            </a>
        </li>

        <li class="nav-item">
            <a href="vendors.php" class="<?php echo $current_page == 'vendors.php' ? 'active' : ''; ?>">
                <div class="nav-icon"><i class="fas fa-store"></i></div>
                <span>ผู้จำหน่าย</span>
            </a>
        </li>

        <li class="nav-item">
            <a href="inbound.php" class="<?php echo $current_page == 'inbound.php' ? 'active' : ''; ?>">
                <div class="nav-icon"><i class="fas fa-truck-loading"></i></div>
                <span>รับสินค้าเข้า</span>
            </a>
        </li>

        <?php if (hasRole(['admin', 'staff'])): ?>
            <li class="nav-item">
                <a href="putaway.php" class="<?php echo $current_page == 'putaway.php' ? 'active' : ''; ?>">
                    <div class="nav-icon"><i class="fas fa-dolly"></i></div>
                    <span>จัดเก็บสินค้า</span>
                </a>
            </li>
        <?php endif; ?>

        <li class="nav-item">
            <a href="discrepancies.php" class="<?php echo $current_page == 'discrepancies.php' ? 'active' : ''; ?>">
                <div class="nav-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <span>รายการไม่ตรง</span>
                <?php
                // Show badge if there are pending discrepancies
                $pending_disc = $pdo->query("SELECT COUNT(*) FROM inbound_discrepancies WHERE resolution='pending'")->fetchColumn();
                if ($pending_disc > 0):
                    ?>
                    <span class="badge badge-danger" style="margin-left: auto; font-size: 0.75rem;">
                        <?php echo $pending_disc; ?>
                    </span>
                <?php endif; ?>
            </a>
        </li>

        <li class="nav-item">
            <a href="outbound.php" class="<?php echo $current_page == 'outbound.php' ? 'active' : ''; ?>">
                <div class="nav-icon"><i class="fas fa-shipping-fast"></i></div>
                <span>ส่งสินค้าออก</span>
            </a>
        </li>

        <?php if (hasRole(['admin', 'office'])): ?>
            <li class="nav-item">
                <a href="shipments.php" class="<?php echo $current_page == 'shipments.php' ? 'active' : ''; ?>">
                    <div class="nav-icon"><i class="fas fa-truck"></i></div>
                    <span>การจัดส่ง</span>
                </a>
            </li>
        <?php endif; ?>



        <li class="nav-item">
            <a href="locations.php" class="<?php echo $current_page == 'locations.php' ? 'active' : ''; ?>">
                <div class="nav-icon"><i class="fas fa-map-marker-alt"></i></div>
                <span>ตำแหน่งจัดเก็บ</span>
            </a>
        </li>

        <li class="nav-item">
            <a href="stock.php" class="<?php echo $current_page == 'stock.php' ? 'active' : ''; ?>">
                <div class="nav-icon"><i class="fas fa-clipboard-list"></i></div>
                <span>คลังสินค้า</span>
            </a>
        </li>

        <?php if ($role === 'admin'): ?>
            <li class="nav-item">
                <a href="users.php" class="<?php echo $current_page == 'users.php' ? 'active' : ''; ?>">
                    <div class="nav-icon"><i class="fas fa-users-cog"></i></div>
                    <span>จัดการผู้ใช้</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="audit.php" class="<?php echo $current_page == 'audit.php' ? 'active' : ''; ?>">
                    <div class="nav-icon"><i class="fas fa-history"></i></div>
                    <span>ประวัติการใช้งาน</span>
                </a>
            </li>
        <?php endif; ?>
    </ul>
</aside>