<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../auth.php';
require_once '../db.php';

// Fungsi untuk memeriksa apakah user adalah admin
function requireAdminLogin()
{
    if (!isLoggedIn()) {
        header("Location: ../login.php");
        exit;
    }

    $user = getLoggedUser();
    if ($user['role'] !== 'admin') {
        header("Location: ../index.php");
        exit;
    }
}

requireAdminLogin();
$user = getLoggedUser();

// Ambil statistik dashboard
$stats = [];

// Total pesanan
$stmt = $pdo->query("SELECT COUNT(*) as total FROM orders");
$stats['total_orders'] = $stmt->fetch()['total'];

// Pesanan hari ini
$stmt = $pdo->query("SELECT COUNT(*) as today FROM orders WHERE DATE(created_at) = CURDATE()");
$stats['orders_today'] = $stmt->fetch()['today'];

// Pesanan pending
$stmt = $pdo->query("SELECT COUNT(*) as pending FROM orders WHERE status = 'pending'");
$stats['pending_orders'] = $stmt->fetch()['pending'];

// Total revenue
$stmt = $pdo->query("SELECT SUM(total_amount) as revenue FROM orders WHERE status IN ('paid', 'processing', 'shipped', 'delivered')");
$stats['total_revenue'] = $stmt->fetch()['revenue'] ?? 0;

// Pesanan terbaru (10 pesanan terakhir)
$stmt = $pdo->query("
    SELECT o.*, u.nama as customer_name, u.email as customer_email,
           COUNT(oi.id) as total_items
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    LEFT JOIN order_items oi ON o.id = oi.order_id 
    GROUP BY o.id 
    ORDER BY o.created_at DESC 
    LIMIT 10
");
$recent_orders = $stmt->fetchAll();

// Chart data - pesanan per hari (7 hari terakhir)
$stmt = $pdo->query("
    SELECT DATE(created_at) as order_date, COUNT(*) as count 
    FROM orders 
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY order_date ASC
");
$chart_data = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - NADA BookStore</title>
    <link rel="stylesheet" href="admin-styles.css">
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<style>
    .table-responsive {
        size: auto;
        overflow-x: scroll;
        overflow-y: auto;
        max-height: 360px;
    }
</style>

<body class="admin-body">
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <nav class="admin-sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="fas fa-book"></i>
                    <span>NADA Admin</span>
                </div>
            </div>

            <ul class="sidebar-menu">
                <li class="active">
                    <a href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="orders.php">
                        <i class="fas fa-shopping-bag"></i>
                        <span>Kelola Pesanan</span>
                        <?php if ($stats['pending_orders'] > 0): ?>
                            <span class="badge badge-warning"><?= $stats['pending_orders'] ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="managed-products.php">
                        <i class="fas fa-box"></i>
                        <span>Kelola Produk</span>
                    </a>
                </li>
                <li>
                    <a href="shipping_management.php">
                        <i class="fas fa-truck"></i>
                        <span>Kelola Pengiriman</span>
                    </a>
                </li>
                <li>
                    <a href="users.php">
                        <i class="fas fa-users"></i>
                        <span>Kelola User</span>
                    </a>
                </li>
                <li class="sidebar-divider"></li>
                <li>
                    <a href="../index.php">
                        <i class="fas fa-home"></i>
                        <span>Ke Website</span>
                    </a>
                </li>
                <li>
                    <a href="../logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Main Content -->
        <div class="admin-main">
            <!-- Header -->
            <header class="admin-header">
                <div class="header-left">
                    <h1>Dashboard</h1>
                </div>
                <div class="header-right">
                    <div class="admin-user">
                        <i class="fas fa-user-shield"></i>
                        <span>Admin: <?= htmlspecialchars($user['nama']) ?></span>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <main class="admin-content">
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-shopping-bag"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?= number_format($stats['total_orders']) ?></h3>
                            <p>Total Pesanan</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?= number_format($stats['orders_today']) ?></h3>
                            <p>Pesanan Hari Ini</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?= number_format($stats['pending_orders']) ?></h3>
                            <p>Menunggu Konfirmasi</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Rp <?= number_format($stats['total_revenue'], 0, ',', '.') ?></h3>
                            <p>Total Revenue</p>
                        </div>
                    </div>
                </div>

                <!-- Content Grid -->
                <div class="content-grid">
                    <!-- Recent Orders -->
                    <div class="content-card">
                        <div class="card-header">
                            <h3>
                                <i class="fas fa-shopping-bag"></i>
                                Pesanan Terbaru
                            </h3>
                            <a href="orders.php" class="btn btn-primary btn-sm">
                                Lihat Semua
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_orders)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <p>Belum ada pesanan</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <style>
                                        .table-responsive th,
                                        td {
                                            color: white;
                                        }
                                    </style>
                                    <table class="admin-table">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Customer</th>
                                                <th>Items</th>
                                                <th>Total</th>
                                                <th>Status</th>
                                                <th>Tanggal</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_orders as $order): ?>
                                                <tr>
                                                    <td>#<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?></td>
                                                    <td>
                                                        <div class="customer-info">
                                                            <strong><?= htmlspecialchars($order['customer_name']) ?></strong>
                                                            <small><?= htmlspecialchars($order['customer_email']) ?></small>
                                                        </div>
                                                    </td>
                                                    <td><?= $order['total_items'] ?> item</td>
                                                    <td>Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></td>
                                                    <td>
                                                        <?php
                                                        $statusClass = [
                                                            'pending' => 'status-warning',
                                                            'paid' => 'status-info',
                                                            'processing' => 'status-primary',
                                                            'shipped' => 'status-success',
                                                            'delivered' => 'status-success',
                                                            'cancelled' => 'status-danger'
                                                        ];
                                                        ?>
                                                        <span class="status-badge <?= $statusClass[$order['status']] ?>">
                                                            <?= ucfirst($order['status']) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= date('d/m/Y', strtotime($order['created_at'])) ?></td>
                                                    <td>
                                                        <a href="order_detail.php?id=<?= $order['id'] ?>"
                                                            class="btn btn-sm btn-outline">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="content-card">
                        <div class="card-header">
                            <h3>
                                <i class="fas fa-bolt"></i>
                                Quick Actions
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="quick-actions">
                                <a href="orders.php?status=pending" class="action-btn">
                                    <i class="fas fa-clock"></i>
                                    <span>Pesanan Pending</span>
                                    <span class="action-count"><?= $stats['pending_orders'] ?></span>
                                </a>

                                <a href="managed-products.php?action=add" class="action-btn">
                                    <i class="fas fa-plus"></i>
                                    <span>Tambah Produk</span>
                                </a>

                                <a href="users.php" class="action-btn">
                                    <i class="fas fa-users"></i>
                                    <span>Kelola User</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script src="admin-script.js"></script>
    <script>
        // Simple chart (you can replace with Chart.js for better charts)
        const chartData = <?= json_encode($chart_data) ?>;

        // Auto refresh dashboard every 30 seconds
        setTimeout(() => {
            location.reload();
        }, 30000);

        // Real-time clock
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('id-ID');
            const dateString = now.toLocaleDateString('id-ID', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });

            // You can add clock display to header if needed
        }

        setInterval(updateClock, 1000);
        updateClock();
    </script>
</body>

</html>