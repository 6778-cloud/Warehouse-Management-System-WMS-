<?php
require_once 'config/db.php';
requireLogin();

$page_title = "แดชบอร์ด";

include 'includes/header.php';
?>

<div class="flex justify-between items-center mb-4">
    <h2>แดชบอร์ด</h2>
    <div style="display: flex; align-items: center; gap: 1rem;">
        <label style="color: var(--text-muted);">กรองตามเดือน:</label>
        <select id="monthFilter" class="form-control" style="width: 200px;">
            <option value="all">ทั้งหมด</option>
            <option value="<?php echo date('Y-m'); ?>" selected>เดือนนี้ (<?php echo date('M Y'); ?>)</option>
            <?php
            // Last 12 months
            for ($i = 1; $i <= 11; $i++) {
                $month = date('Y-m', strtotime("-$i months"));
                $label = date('M Y', strtotime("-$i months"));
                echo "<option value='$month'>$label</option>";
            }
            ?>
        </select>
    </div>
</div>

<!-- Summary Cards -->
<div
    style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Total Products -->
    <div class="card" style="display: flex; align-items: center; padding: 1.5rem;">
        <div
            style="background-color: rgba(59, 130, 246, 0.1); padding: 1rem; border-radius: 50%; margin-right: 1rem; flex-shrink: 0;">
            <i class="fas fa-box fa-2x" style="color: var(--info-color);"></i>
        </div>
        <div style="min-width: 0; flex: 1;">
            <div
                style="font-size: 0.875rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                สินค้าทั้งหมด</div>
            <div style="font-size: 1.5rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"
                id="stat-products">-</div>
        </div>
    </div>

    <!-- Low Stock -->
    <div class="card" style="display: flex; align-items: center; padding: 1.5rem;">
        <div
            style="background-color: rgba(239, 68, 68, 0.1); padding: 1rem; border-radius: 50%; margin-right: 1rem; flex-shrink: 0;">
            <i class="fas fa-exclamation-triangle fa-2x" style="color: var(--danger-color);"></i>
        </div>
        <div style="min-width: 0; flex: 1;">
            <div
                style="font-size: 0.875rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                สินค้าใกล้หมด</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: var(--danger-color); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"
                id="stat-low-stock">-</div>
        </div>
    </div>

    <!-- Pending Inbound -->
    <div class="card" style="display: flex; align-items: center; padding: 1.5rem;">
        <div
            style="background-color: rgba(74, 222, 128, 0.1); padding: 1rem; border-radius: 50%; margin-right: 1rem; flex-shrink: 0;">
            <i class="fas fa-truck-loading fa-2x" style="color: var(--success-color);"></i>
        </div>
        <div style="min-width: 0; flex: 1;">
            <div
                style="font-size: 0.875rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                รอรับสินค้าเข้า</div>
            <div style="font-size: 1.5rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"
                id="stat-inbound">-</div>
        </div>
    </div>

    <!-- Pending Outbound -->
    <div class="card" style="display: flex; align-items: center; padding: 1.5rem;">
        <div
            style="background-color: rgba(251, 191, 36, 0.1); padding: 1rem; border-radius: 50%; margin-right: 1rem; flex-shrink: 0;">
            <i class="fas fa-boxes fa-2x" style="color: var(--warning-color);"></i>
        </div>
        <div style="min-width: 0; flex: 1;">
            <div
                style="font-size: 0.875rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                รอส่งสินค้าออก</div>
            <div style="font-size: 1.5rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"
                id="stat-outbound">-</div>
        </div>
    </div>

    <!-- Orders Shipped -->
    <div class="card" style="display: flex; align-items: center; padding: 1.5rem;">
        <div
            style="background-color: rgba(139, 92, 246, 0.1); padding: 1rem; border-radius: 50%; margin-right: 1rem; flex-shrink: 0;">
            <i class="fas fa-shipping-fast fa-2x" style="color: #8b5cf6;"></i>
        </div>
        <div style="min-width: 0; flex: 1;">
            <div
                style="font-size: 0.875rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                ส่งสินค้าแล้ว</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #8b5cf6; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"
                id="stat-shipped">-</div>
        </div>
    </div>

</div>

<!-- Charts Section -->
<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Orders Overview -->
    <div class="card">
        <h3>ภาพรวมคำสั่งซื้อ</h3>
        <canvas id="stockChart" style="max-height: 300px;"></canvas>
    </div>

    <!-- Movement History -->
    <div class="card">
        <h3>ประวัติการเคลื่อนไหวสินค้า</h3>
        <canvas id="movementChart" style="max-height: 300px;"></canvas>
    </div>

    <!-- Top Products -->
    <div class="card">
        <h3>สินค้าขายดี</h3>
        <canvas id="topProductsChart" style="max-height: 300px;"></canvas>
    </div>
</div>

<!-- Quick Actions & System Status -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
    <div class="card">
        <h3>ดำเนินการด่วน</h3>
        <div style="display:flex; gap:1rem; margin-top:1rem; flex-wrap: wrap;">
            <a href="products.php" class="btn btn-primary"><i class="fas fa-plus"></i> เพิ่มสินค้า</a>
            <a href="inbound.php" class="btn btn-secondary"><i class="fas fa-arrow-down"></i> รับสินค้าเข้าใหม่</a>
            <a href="outbound.php" class="btn btn-secondary"><i class="fas fa-arrow-up"></i> ส่งสินค้าออกใหม่</a>
        </div>
    </div>

    <div class="card">
        <h3>สถานะระบบ <span class="live-indicator" style="float:right; margin-top:5px;"></span></h3>
        <ul style="list-style:none; margin-top:1rem;">
            <li
                style="padding:0.5rem 0; border-bottom:1px solid var(--border-color); display:flex; justify-content:space-between;">
                <span class="text-muted">ฐานข้อมูล</span> <span class="badge badge-success">เชื่อมต่อแล้ว</span>
            </li>
            <li
                style="padding:0.5rem 0; border-bottom:1px solid var(--border-color); display:flex; justify-content:space-between;">
                <span class="text-muted">อัพเดทล่าสุด</span> <span id="last-update"
                    style="font-family:monospace;">...</span>
            </li>
        </ul>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Chart Config
    Chart.defaults.color = '#94a3b8';
    Chart.defaults.borderColor = 'rgba(255,255,255,0.05)';

    // Initialize Charts
    const stockChart = new Chart(document.getElementById('stockChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['รับสินค้าเสร็จสิ้น', 'ส่งสินค้าแล้ว', 'รอดำเนินการ'],
            datasets: [{
                data: [0, 0, 0],
                backgroundColor: ['#4ade80', '#38bdf8', '#fbbf24'],
                borderWidth: 0
            }]
        },
        options: { cutout: '70%', responsive: true, maintainAspectRatio: true }
    });

    const movementChart = new Chart(document.getElementById('movementChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: [],
            datasets: [
                { label: 'รับเข้า', data: [], backgroundColor: '#4ade80' },
                { label: 'ส่งออก', data: [], backgroundColor: '#38bdf8' }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: { y: { beginAtZero: true } }
        }
    });



    const topProductsChart = new Chart(document.getElementById('topProductsChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: [],
            datasets: [{
                label: 'จำนวนที่ขาย',
                data: [],
                backgroundColor: '#8b5cf6'
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: true,
            scales: { x: { beginAtZero: true } }
        }
    });

    let currentFilter = '<?php echo date('Y-m'); ?>';

    function fetchStats() {
        fetch(`api/dashboard_stats.php?month=${currentFilter}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Card Updates
                    updateValue('stat-products', data.data.total_products);
                    updateValue('stat-low-stock', data.data.low_stock_count);
                    updateValue('stat-inbound', data.data.pending_inbound);
                    updateValue('stat-outbound', data.data.pending_outbound);
                    updateValue('stat-shipped', data.data.shipped_count || 0);



                    document.getElementById('last-update').textContent = data.data.timestamp;

                    // Chart Updates
                    if (data.data.charts) {
                        // Stock Distribution
                        stockChart.data.datasets[0].data = data.data.charts.stock_distribution;
                        stockChart.update();

                        // Movement History
                        movementChart.data.labels = data.data.charts.movement_labels;
                        movementChart.data.datasets[0].data = data.data.charts.movement_in;
                        movementChart.data.datasets[1].data = data.data.charts.movement_out;
                        movementChart.update();



                        // Top Products
                        topProductsChart.data.labels = data.data.charts.top_products_labels;
                        topProductsChart.data.datasets[0].data = data.data.charts.top_products_data;
                        topProductsChart.update();
                    }
                }
            })
            .catch(err => console.error('Fetch error:', err));
    }

    function updateValue(id, newValue) {
        const el = document.getElementById(id);
        if (el && el.textContent !== newValue.toString()) {
            el.textContent = newValue;
            el.classList.remove('fade-in');
            void el.offsetWidth;
            el.classList.add('fade-in');
        }
    }

    // Month Filter Change
    document.getElementById('monthFilter').addEventListener('change', function () {
        currentFilter = this.value;
        fetchStats();
    });

    // Initial fetch
    fetchStats();

    // Poll every 10 seconds
    setInterval(fetchStats, 10000);
</script>

<?php include 'includes/footer.php'; ?>