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

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status') {
        $orderId = $_POST['order_id'];
        $newStatus = $_POST['new_status'];
        $trackingNumber = $_POST['tracking_number'] ?? '';

        try {
            // Mulai transaction
            $pdo->beginTransaction();

            // Ambil data order saat ini
            $stmt = $pdo->prepare("SELECT status, payment_status FROM orders WHERE id = ?");
            $stmt->execute([$orderId]);
            $currentOrder = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$currentOrder) {
                throw new Exception("Pesanan tidak ditemukan.");
            }

            $currentStatus = $currentOrder['status'];
            $currentPaymentStatus = $currentOrder['payment_status'];

            // Tentukan payment_status berdasarkan status baru
            $newPaymentStatus = $currentPaymentStatus; // Default: tetap sama

            // Logika untuk mengubah payment_status
            switch ($newStatus) {
                case 'paid':
                    $newPaymentStatus = 'paid';
                    break;

                case 'processing':
                    // Jika dari pending ke processing, anggap sudah dibayar
                    if ($currentStatus === 'pending') {
                        $newPaymentStatus = 'paid';
                    }
                    break;

                case 'shipped':
                case 'delivered':
                    // Jika belum paid, ubah menjadi paid (karena barang sudah dikirim/diterima)
                    if ($currentPaymentStatus === 'pending') {
                        $newPaymentStatus = 'paid';
                    }
                    break;

                case 'cancelled':
                    // Jika dibatalkan dan belum dibayar, ubah ke failed
                    if ($currentPaymentStatus === 'pending') {
                        $newPaymentStatus = 'failed';
                    }
                    break;

                case 'pending':
                    // Jika dikembalikan ke pending, payment_status kembali ke pending
                    $newPaymentStatus = 'pending';
                    break;
            }

            // Build SQL query
            $sql = "UPDATE orders SET status = ?, payment_status = ?, updated_at = NOW()";
            $params = [$newStatus, $newPaymentStatus];

            // Add tracking number if status is shipped
            if ($newStatus === 'shipped' && !empty($trackingNumber)) {
                $sql .= ", tracking_number = ?, shipped_at = NOW()";
                $params[] = $trackingNumber;
            }

            // Set delivered date if status is delivered
            if ($newStatus === 'delivered') {
                $sql .= ", delivered_at = NOW()";
            }

            $sql .= " WHERE id = ?";
            $params[] = $orderId;

            // Execute update
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            // Log perubahan untuk audit trail (opsional)
            $logSql = "INSERT INTO order_status_log (order_id, old_status, new_status, old_payment_status, new_payment_status, changed_by, changed_at) 
                       VALUES (?, ?, ?, ?, ?, ?, NOW())";

            // Cek apakah tabel log ada, jika tidak skip logging
            try {
                $logStmt = $pdo->prepare($logSql);
                $logStmt->execute([$orderId, $currentStatus, $newStatus, $currentPaymentStatus, $newPaymentStatus, $user['id']]);
            } catch (Exception $logError) {
                // Jika tabel log tidak ada, lanjutkan tanpa error
            }

            // Commit transaction
            $pdo->commit();

            // Pesan sukses dengan detail perubahan
            $statusChanges = [];
            if ($currentStatus !== $newStatus) {
                $statusChanges[] = "Status: {$currentStatus} → {$newStatus}";
            }
            if ($currentPaymentStatus !== $newPaymentStatus) {
                $statusChanges[] = "Payment: {$currentPaymentStatus} → {$newPaymentStatus}";
            }

            $changeDetails = !empty($statusChanges) ? " (" . implode(", ", $statusChanges) . ")" : "";
            $message = "Status pesanan berhasil diperbarui" . $changeDetails . ".";
            $messageType = 'success';

        } catch (Exception $e) {
            // Rollback transaction jika ada error
            $pdo->rollBack();
            $message = "Error: " . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Filters
$statusFilter = $_GET['status'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$paymentMethodFilter = $_GET['payment_method'] ?? 'all';
$proofFilter = $_GET['proof_status'] ?? 'all';

// Pagination - pastikan integer
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Build query
$sql = "SELECT o.*, u.nama as customer_name, u.email as customer_email,
               COUNT(oi.id) as total_items,
               so.name as shipping_name
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        LEFT JOIN order_items oi ON o.id = oi.order_id 
        LEFT JOIN shipping_options so ON o.shipping_option_id = so.id
        WHERE 1=1";

$params = [];

if ($statusFilter !== 'all') {
    $sql .= " AND o.status = ?";
    $params[] = $statusFilter;
}

if ($paymentMethodFilter !== 'all') {
    $sql .= " AND o.payment_method = ?";
    $params[] = $paymentMethodFilter;
}

if ($proofFilter !== 'all') {
    if ($proofFilter === 'uploaded') {
        $sql .= " AND o.payment_proof IS NOT NULL AND o.payment_proof != ''";
    } else if ($proofFilter === 'not_uploaded') {
        $sql .= " AND (o.payment_proof IS NULL OR o.payment_proof = '')";
    }
}

if ($searchQuery) {
    $sql .= " AND (u.nama LIKE ? OR u.email LIKE ? OR o.order_number LIKE ?)";
    $searchParam = "%$searchQuery%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if ($dateFrom) {
    $sql .= " AND DATE(o.created_at) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $sql .= " AND DATE(o.created_at) <= ?";
    $params[] = $dateTo;
}

$sql .= " GROUP BY o.id ORDER BY o.created_at DESC LIMIT $perPage OFFSET $offset";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
} catch (Exception $e) {
    $orders = [];
    $message = "Error mengambil data pesanan: " . $e->getMessage();
    $messageType = 'danger';
}

// Count total for pagination
$countSql = "SELECT COUNT(DISTINCT o.id) as total 
             FROM orders o 
             JOIN users u ON o.user_id = u.id 
             WHERE 1=1";

$countParams = [];

if ($statusFilter !== 'all') {
    $countSql .= " AND o.status = ?";
    $countParams[] = $statusFilter;
}

if ($paymentMethodFilter !== 'all') {
    $countSql .= " AND o.payment_method = ?";
    $countParams[] = $paymentMethodFilter;
}

if ($proofFilter !== 'all') {
    if ($proofFilter === 'uploaded') {
        $countSql .= " AND o.payment_proof IS NOT NULL AND o.payment_proof != ''";
    } else if ($proofFilter === 'not_uploaded') {
        $countSql .= " AND (o.payment_proof IS NULL OR o.payment_proof = '')";
    }
}

if ($searchQuery) {
    $countSql .= " AND (u.nama LIKE ? OR u.email LIKE ? OR o.order_number LIKE ?)";
    $searchParam = "%$searchQuery%";
    $countParams[] = $searchParam;
    $countParams[] = $searchParam;
    $countParams[] = $searchParam;
}

if ($dateFrom) {
    $countSql .= " AND DATE(o.created_at) >= ?";
    $countParams[] = $dateFrom;
}

if ($dateTo) {
    $countSql .= " AND DATE(o.created_at) <= ?";
    $countParams[] = $dateTo;
}

try {
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $result = $countStmt->fetch(PDO::FETCH_ASSOC);
    $totalOrders = $result['total'] ?? 0;
    $totalPages = ceil($totalOrders / $perPage);
} catch (Exception $e) {
    $totalOrders = 0;
    $totalPages = 0;
    if (!$message) { // Jangan override pesan error sebelumnya
        $message = "Error menghitung total pesanan: " . $e->getMessage();
        $messageType = 'danger';
    }
}

// Status options
$statusOptions = [
    'pending' => 'Pending',
    'paid' => 'Dibayar',
    'processing' => 'Diproses',
    'shipped' => 'Dikirim',
    'delivered' => 'Selesai',
    'cancelled' => 'Dibatalkan'
];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pesanan - NADA BookStore Admin</title>
    <link rel="stylesheet" href="admin-styles.css">
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

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
                <li>
                    <a href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="active">
                    <a href="orders.php">
                        <i class="fas fa-shopping-bag"></i>
                        <span>Kelola Pesanan</span>
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
                    <h1>Kelola Pesanan & Bukti Pembayaran</h1>
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
                <?php if ($message): ?>
                    <div class="alert alert-<?= $messageType ?> fade-in">
                        <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <!-- Filter Bar -->
                <div class="filter-bar">
                    <form method="GET" action="orders.php">
                        <div class="filter-row">
                            <div class="form-group">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-control form-select">
                                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Semua Status
                                    </option>
                                    <?php foreach ($statusOptions as $value => $label): ?>
                                        <option value="<?= $value ?>" <?= $statusFilter === $value ? 'selected' : '' ?>>
                                            <?= $label ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Metode Pembayaran</label>
                                <select name="payment_method" class="form-control form-select">
                                    <option value="all" <?= $paymentMethodFilter === 'all' ? 'selected' : '' ?>>Semua
                                        Metode</option>
                                    <option value="transfer_bank" <?= $paymentMethodFilter === 'transfer_bank' ? 'selected' : '' ?>>Transfer Bank</option>
                                    <option value="cod" <?= $paymentMethodFilter === 'cod' ? 'selected' : '' ?>>COD
                                    </option>
                                    <option value="e_wallet" <?= $paymentMethodFilter === 'e_wallet' ? 'selected' : '' ?>>
                                        E-Wallet</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Bukti Pembayaran</label>
                                <select name="proof_status" class="form-control form-select">
                                    <option value="all" <?= $proofFilter === 'all' ? 'selected' : '' ?>>Semua</option>
                                    <option value="uploaded" <?= $proofFilter === 'uploaded' ? 'selected' : '' ?>>Sudah
                                        Upload</option>
                                    <option value="not_uploaded" <?= $proofFilter === 'not_uploaded' ? 'selected' : '' ?>>
                                        Belum Upload</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Pencarian</label>
                                <div class="search-box">
                                    <i class="fas fa-search"></i>
                                    <input type="text" name="search" class="form-control"
                                        placeholder="Cari customer, email, atau nomor pesanan"
                                        value="<?= htmlspecialchars($searchQuery) ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Dari Tanggal</label>
                                <input type="date" name="date_from" class="form-control"
                                    value="<?= htmlspecialchars($dateFrom) ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Sampai Tanggal</label>
                                <input type="date" name="date_to" class="form-control"
                                    value="<?= htmlspecialchars($dateTo) ?>">
                            </div>

                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                                <a href="orders.php" class="btn btn-outline">
                                    <i class="fas fa-times"></i> Reset
                                </a>
                            </div>
                        </div>
                    </form>
                </div>

                <style>
                    /* Export Menu Styles */
                    .export-menu {
                        position: absolute;
                        top: 100%;
                        right: 0;
                        background: white;
                        border: 1px solid var(--admin-border);
                        border-radius: 8px;
                        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
                        z-index: 1000;
                        min-width: 180px;
                        margin-top: 5px;
                    }

                    .export-menu a {
                        display: flex;
                        align-items: center;
                        gap: 10px;
                        padding: 12px 16px;
                        text-decoration: none;
                        color: var(--admin-secondary);
                        font-size: 0.9rem;
                        transition: all 0.2s ease;
                        border-bottom: 1px solid #f1f5f9;
                    }

                    .export-menu a:last-child {
                        border-bottom: none;
                    }

                    .export-menu a:hover {
                        background-color: #f8fafc;
                        color: var(--admin-primary);
                    }

                    .export-menu a:first-child {
                        border-radius: 8px 8px 0 0;
                    }

                    .export-menu a:last-child {
                        border-radius: 0 0 8px 8px;
                    }

                    .export-menu i {
                        width: 16px;
                        text-align: center;
                    }

                    /* Payment proof styles */
                    .payment-method-badge {
                        display: inline-flex;
                        align-items: center;
                        gap: 5px;
                        padding: 4px 8px;
                        border-radius: 12px;
                        font-size: 11px;
                        font-weight: 600;
                    }

                    .payment-method-badge.transfer_bank {
                        background: #e3f2fd;
                        color: #1976d2;
                    }

                    .payment-method-badge.cod {
                        background: #fff3e0;
                        color: #f57c00;
                    }

                    .payment-method-badge.e_wallet {
                        background: #f3e5f5;
                        color: #7b1fa2;
                    }

                    .proof-status {
                        display: flex;
                        align-items: center;
                        gap: 5px;
                        font-size: 12px;
                    }

                    .proof-status.uploaded {
                        color: #28a745;
                    }

                    .proof-status.not-uploaded {
                        color: #dc3545;
                    }

                    .proof-preview-btn {
                        padding: 2px 6px;
                        font-size: 10px;
                        margin-top: 3px;
                    }

                    /* Modal styles for payment proof */
                    .modal-payment-proof .modal-content {
                        max-width: 800px;
                    }

                    .proof-image-container {
                        text-align: center;
                        max-height: 70vh;
                        overflow-y: auto;
                    }

                    .proof-image-container img {
                        max-width: 100%;
                        height: auto;
                        border: 1px solid #dee2e6;
                        border-radius: 6px;
                    }

                    .proof-image-container iframe {
                        width: 100%;
                        height: 600px;
                        border: 1px solid #dee2e6;
                        border-radius: 6px;
                    }

                    .order-info-summary {
                        background: #f8f9fa;
                        padding: 15px;
                        border-radius: 6px;
                        margin-bottom: 20px;
                        font-size: 14px;
                    }

                    .order-info-summary strong {
                        color: #495057;
                    }
                </style>

                <!-- Orders Table -->
                <div class="content-card">
                    <div class="card-header">
                        <h3>
                            <i class="fas fa-shopping-bag"></i>
                            Daftar Pesanan
                        </h3>
                        <div class="action-buttons">
                            <div style="position: relative; display: inline-block;">
                                <button onclick="toggleExportMenu()" class="btn btn-outline btn-sm">
                                    <i class="fas fa-download"></i> Export
                                    <i class="fas fa-chevron-down" style="margin-left: 5px;"></i>
                                </button>
                                <div id="exportMenu" class="export-menu" style="display: none;">
                                    <a href="#" onclick="exportOrders('csv')">
                                        <i class="fas fa-file-csv"></i> Export CSV
                                    </a>
                                    <a href="#" onclick="exportOrders('xlsx')">
                                        <i class="fas fa-file-excel"></i> Export Excel
                                    </a>
                                    <a href="#" onclick="exportOrders('html')">
                                        <i class="fas fa-file-code"></i> Export HTML
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-body">
                        <?php if (empty($orders)): ?>
                            <div class="empty-state">
                                <i class="fas fa-shopping-bag"></i>
                                <p>Tidak ada pesanan ditemukan</p>
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
                                            <th>Aksi</th>
                                            <th>ID Pesanan</th>
                                            <th>Customer</th>
                                            <th>Items</th>
                                            <th>Total</th>
                                            <th>Pembayaran</th>
                                            <th>Status Bayar</th>
                                            <th>Bukti Bayar</th>
                                            <th>Status</th>
                                            <th>Tanggal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orders as $order): ?>
                                            <tr>
                                                <td>
                                                    <div style="display: flex; gap: 5px; flex-direction: column;">
                                                        <a href="order_detail.php?id=<?= $order['id'] ?>"
                                                            class="btn btn-sm btn-outline tooltip" data-tooltip="Detail">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <button
                                                            onclick="updateStatus(<?= $order['id'] ?>, '<?= $order['status'] ?>')"
                                                            class="btn btn-sm btn-primary tooltip" data-tooltip="Update Status">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                                <td>
                                                    <strong>#<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?></strong>
                                                    <?php if ($order['order_number']): ?>
                                                        <br><small><?= htmlspecialchars($order['order_number']) ?></small>
                                                    <?php else: ?>
                                                        <br><small>ORD-<?= date('Y', strtotime($order['created_at'])) ?>-<?= str_pad($order['id'], 3, '0', STR_PAD_LEFT) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="customer-info">
                                                        <strong><?= htmlspecialchars($order['customer_name']) ?></strong>
                                                        <small><?= htmlspecialchars($order['customer_email']) ?></small>
                                                    </div>
                                                </td>
                                                <td><?= $order['total_items'] ?> item</td>
                                                <td>
                                                    <strong>Rp
                                                        <?= number_format($order['total_amount'], 0, ',', '.') ?></strong>
                                                </td>
                                                <td>
                                                    <span class="payment-method-badge <?= $order['payment_method'] ?>">
                                                        <?php
                                                        switch ($order['payment_method']) {
                                                            case 'transfer_bank':
                                                                echo '<i class="fas fa-university"></i> Transfer';
                                                                break;
                                                            case 'cod':
                                                                echo '<i class="fas fa-money-bill-wave"></i> COD';
                                                                break;
                                                            case 'e_wallet':
                                                                echo '<i class="fas fa-mobile-alt"></i> E-Wallet';
                                                                break;
                                                            default:
                                                                echo htmlspecialchars($order['payment_method']);
                                                        }
                                                        ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $paymentStatusClasses = [
                                                        'pending' => 'payment-status-pending',
                                                        'paid' => 'payment-status-paid',
                                                        'failed' => 'payment-status-failed'
                                                    ];

                                                    $paymentStatusLabels = [
                                                        'pending' => 'Pending',
                                                        'paid' => 'Lunas',
                                                        'failed' => 'Gagal'
                                                    ];

                                                    $paymentStatusIcons = [
                                                        'pending' => 'fas fa-clock',
                                                        'paid' => 'fas fa-check-circle',
                                                        'failed' => 'fas fa-times-circle'
                                                    ];

                                                    $paymentStatus = $order['payment_status'] ?? 'pending';
                                                    ?>
                                                    <span
                                                        class="payment-status-badge <?= $paymentStatusClasses[$paymentStatus] ?>">
                                                        <i class="<?= $paymentStatusIcons[$paymentStatus] ?>"></i>
                                                        <?= $paymentStatusLabels[$paymentStatus] ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if (!empty($order['payment_proof'])): ?>
                                                        <div class="proof-status uploaded">
                                                            <i class="fas fa-check-circle"></i>
                                                            <span>Uploaded</span>
                                                        </div>
                                                        <button class="btn btn-sm btn-info proof-preview-btn"
                                                            onclick="viewPaymentProof('<?= htmlspecialchars($order['payment_proof']) ?>', '<?= htmlspecialchars($order['order_number']) ?>', '<?= number_format($order['total_amount'], 0, ',', '.') ?>')">
                                                            <i class="fas fa-eye"></i> Lihat
                                                        </button>
                                                        <?php if ($order['payment_proof_uploaded_at']): ?>
                                                            <small style="display: block; color: #6c757d; font-size: 10px;">
                                                                <?= date('d/m/Y H:i', strtotime($order['payment_proof_uploaded_at'])) ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <div class="proof-status not-uploaded">
                                                            <i class="fas fa-times-circle"></i>
                                                            <span>Belum upload</span>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusClasses = [
                                                        'pending' => 'status-warning',
                                                        'paid' => 'status-info',
                                                        'processing' => 'status-primary',
                                                        'shipped' => 'status-success',
                                                        'delivered' => 'status-success',
                                                        'cancelled' => 'status-danger'
                                                    ];
                                                    ?>
                                                    <span class="status-badge <?= $statusClasses[$order['status']] ?>">
                                                        <?= $statusOptions[$order['status']] ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?= date('d/m/Y', strtotime($order['created_at'])) ?>
                                                    <br>
                                                    <small><?= date('H:i', strtotime($order['created_at'])) ?></small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <div class="pagination">
                                    <?php if ($page > 1): ?>
                                        <a
                                            href="?page=<?= $page - 1 ?>&status=<?= $statusFilter ?>&payment_method=<?= $paymentMethodFilter ?>&proof_status=<?= $proofFilter ?>&search=<?= urlencode($searchQuery) ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    <?php endif; ?>

                                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                        <?php if ($i == $page): ?>
                                            <span class="active"><?= $i ?></span>
                                        <?php else: ?>
                                            <a
                                                href="?page=<?= $i ?>&status=<?= $statusFilter ?>&payment_method=<?= $paymentMethodFilter ?>&proof_status=<?= $proofFilter ?>&search=<?= urlencode($searchQuery) ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>">
                                                <?= $i ?>
                                            </a>
                                        <?php endif; ?>
                                    <?php endfor; ?>

                                    <?php if ($page < $totalPages): ?>
                                        <a
                                            href="?page=<?= $page + 1 ?>&status=<?= $statusFilter ?>&payment_method=<?= $paymentMethodFilter ?>&proof_status=<?= $proofFilter ?>&search=<?= urlencode($searchQuery) ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Update Status Pesanan</h3>
                <button type="button" class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" action="orders.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="order_id" id="modalOrderId">

                    <div class="form-group">
                        <label class="form-label">Status Baru</label>
                        <select name="new_status" id="modalStatus" class="form-control form-select" required>
                            <?php foreach ($statusOptions as $value => $label): ?>
                                <option value="<?= $value ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" id="trackingGroup" style="display: none;">
                        <label class="form-label">Nomor Resi (Opsional)</label>
                        <input type="text" name="tracking_number" id="modalTracking" class="form-control"
                            placeholder="Masukkan nomor resi">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal()">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Payment Proof Modal -->
    <div id="paymentProofModal" class="modal modal-payment-proof">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-receipt"></i> Bukti Pembayaran</h3>
                <button type="button" class="modal-close" onclick="closePaymentProofModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="orderInfoSummary" class="order-info-summary"></div>
                <div id="proofImageContainer" class="proof-image-container">
                    <img id="proofImage" src="" alt="Bukti Pembayaran" style="display: none;">
                    <iframe id="proofPdf" src="" style="display: none;"></iframe>
                    <div id="proofError" style="display: none; text-align: center; color: #dc3545; padding: 20px;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 48px; margin-bottom: 15px;"></i>
                        <p>File bukti pembayaran tidak dapat ditampilkan atau tidak ditemukan.</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closePaymentProofModal()">Tutup</button>
                <a id="downloadProofBtn" href="" download class="btn btn-primary" style="display: none;">
                    <i class="fas fa-download"></i> Download
                </a>
            </div>
        </div>
    </div>

    <script>
        // Update status modal
        function updateStatus(orderId, currentStatus) {
            document.getElementById('modalOrderId').value = orderId;
            document.getElementById('modalStatus').value = currentStatus;

            // Show tracking field only for shipped status
            const trackingGroup = document.getElementById('trackingGroup');
            const statusSelect = document.getElementById('modalStatus');

            function toggleTracking() {
                if (statusSelect.value === 'shipped') {
                    trackingGroup.style.display = 'block';
                } else {
                    trackingGroup.style.display = 'none';
                }
            }

            statusSelect.addEventListener('change', toggleTracking);
            toggleTracking();

            document.getElementById('statusModal').classList.add('show');
        }

        function closeModal() {
            document.getElementById('statusModal').classList.remove('show');
        }

        // Payment proof modal functions
        function viewPaymentProof(filename, orderNumber, totalAmount) {
            const modal = document.getElementById('paymentProofModal');
            const orderInfo = document.getElementById('orderInfoSummary');
            const proofImage = document.getElementById('proofImage');
            const proofPdf = document.getElementById('proofPdf');
            const proofError = document.getElementById('proofError');
            const downloadBtn = document.getElementById('downloadProofBtn');

            // Set order info
            orderInfo.innerHTML = `
                <strong>Pesanan:</strong> ${orderNumber} | 
                <strong>Total:</strong> Rp. ${totalAmount}
            `;

            // Hide all elements first
            proofImage.style.display = 'none';
            proofPdf.style.display = 'none';
            proofError.style.display = 'none';
            downloadBtn.style.display = 'none';

            // Construct file path
            const filePath = `../uploads/payment_proofs/${filename}`;

            // Check file extension
            const fileExtension = filename.split('.').pop().toLowerCase();

            if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) {
                // Handle image files
                proofImage.onload = function () {
                    proofImage.style.display = 'block';
                    downloadBtn.href = filePath;
                    downloadBtn.style.display = 'inline-flex';
                };

                proofImage.onerror = function () {
                    proofError.style.display = 'block';
                };

                proofImage.src = filePath;
            } else if (fileExtension === 'pdf') {
                // Handle PDF files
                proofPdf.onload = function () {
                    proofPdf.style.display = 'block';
                    downloadBtn.href = filePath;
                    downloadBtn.style.display = 'inline-flex';
                };

                proofPdf.onerror = function () {
                    proofError.style.display = 'block';
                };

                proofPdf.src = filePath;
            } else {
                // Unsupported file type
                proofError.style.display = 'block';
            }

            modal.classList.add('show');
        }

        function closePaymentProofModal() {
            const modal = document.getElementById('paymentProofModal');
            modal.classList.remove('show');

            // Clear sources to stop loading
            document.getElementById('proofImage').src = '';
            document.getElementById('proofPdf').src = '';
        }

        // Close modal when clicking outside
        document.getElementById('statusModal').addEventListener('click', function (e) {
            if (e.target === this) {
                closeModal();
            }
        });

        document.getElementById('paymentProofModal').addEventListener('click', function (e) {
            if (e.target === this) {
                closePaymentProofModal();
            }
        });

        // Export function
        function exportOrders(format = 'xlsx') {
            const params = new URLSearchParams(window.location.search);
            params.set('export', format);
            window.location.href = 'export_orders.php?' + params.toString();

            // Hide export menu
            document.getElementById('exportMenu').style.display = 'none';
        }

        // Toggle export menu
        function toggleExportMenu() {
            const menu = document.getElementById('exportMenu');
            menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
        }

        // Close export menu when clicking outside
        document.addEventListener('click', function (e) {
            const menu = document.getElementById('exportMenu');
            const button = e.target.closest('button');

            if (!button || !button.onclick || button.onclick.toString().indexOf('toggleExportMenu') === -1) {
                menu.style.display = 'none';
            }
        });

        // Auto refresh for real-time updates
        let autoRefreshInterval;
        function startAutoRefresh() {
            autoRefreshInterval = setTimeout(() => {
                // Only refresh if no modals are open
                if (!document.querySelector('.modal.show')) {
                    location.reload();
                } else {
                    startAutoRefresh(); // Try again later
                }
            }, 60000); // Refresh every minute
        }

        function stopAutoRefresh() {
            if (autoRefreshInterval) {
                clearTimeout(autoRefreshInterval);
            }
        }

        // Start auto refresh on page load
        startAutoRefresh();

        // Stop auto refresh when modal is open
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('show', stopAutoRefresh);
            modal.addEventListener('hide', startAutoRefresh);
        });

        // Add loading state to forms
        document.addEventListener('DOMContentLoaded', function () {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function () {
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<span class="loading-spinner"></span> Memproses...';
                    }
                });
            });

            // Auto-hide alerts
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(() => {
                        alert.remove();
                    }, 300);
                }, 5000);
            });

            // Enhanced search functionality
            let searchTimeout;
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
                searchInput.addEventListener('input', function () {
                    clearTimeout(searchTimeout);
                    // Add loading indicator
                    const searchBox = this.parentElement;
                    searchBox.classList.add('loading');

                    searchTimeout = setTimeout(() => {
                        searchBox.classList.remove('loading');
                        this.form.submit();
                    }, 1000);
                });
            }

            // Keyboard shortcuts
            document.addEventListener('keydown', function (e) {
                // Escape key to close modals
                if (e.key === 'Escape') {
                    closeModal();
                    closePaymentProofModal();
                }

                // Ctrl+F to focus search
                if (e.ctrlKey && e.key === 'f') {
                    e.preventDefault();
                    if (searchInput) {
                        searchInput.focus();
                        searchInput.select();
                    }
                }
            });

            // Add tooltips
            const tooltips = document.querySelectorAll('[data-tooltip]');
            tooltips.forEach(element => {
                element.addEventListener('mouseenter', function () {
                    const tooltip = document.createElement('div');
                    tooltip.className = 'tooltip-popup';
                    tooltip.textContent = this.dataset.tooltip;
                    tooltip.style.position = 'absolute';
                    tooltip.style.background = '#333';
                    tooltip.style.color = 'white';
                    tooltip.style.padding = '5px 10px';
                    tooltip.style.borderRadius = '4px';
                    tooltip.style.fontSize = '12px';
                    tooltip.style.zIndex = '1000';
                    tooltip.style.whiteSpace = 'nowrap';
                    tooltip.style.pointerEvents = 'none';

                    document.body.appendChild(tooltip);

                    const rect = this.getBoundingClientRect();
                    tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
                    tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';

                    this.tooltip = tooltip;
                });

                element.addEventListener('mouseleave', function () {
                    if (this.tooltip) {
                        this.tooltip.remove();
                        this.tooltip = null;
                    }
                });
            });

            // Status badge click to filter
            document.querySelectorAll('.status-badge').forEach(badge => {
                badge.style.cursor = 'pointer';
                badge.addEventListener('click', function () {
                    const status = this.textContent.trim().toLowerCase();
                    const statusMap = {
                        'pending': 'pending',
                        'dibayar': 'paid',
                        'diproses': 'processing',
                        'dikirim': 'shipped',
                        'selesai': 'delivered',
                        'dibatalkan': 'cancelled'
                    };

                    if (statusMap[status]) {
                        const url = new URL(window.location);
                        url.searchParams.set('status', statusMap[status]);
                        window.location.href = url.toString();
                    }
                });
            });

            // Payment method badge click to filter
            document.querySelectorAll('.payment-method-badge').forEach(badge => {
                badge.style.cursor = 'pointer';
                badge.addEventListener('click', function () {
                    const method = this.classList.contains('transfer_bank') ? 'transfer_bank' :
                        this.classList.contains('cod') ? 'cod' :
                            this.classList.contains('e_wallet') ? 'e_wallet' : '';

                    if (method) {
                        const url = new URL(window.location);
                        url.searchParams.set('payment_method', method);
                        window.location.href = url.toString();
                    }
                });
            });
        });

        // Bulk actions functionality
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('input[name="selected_orders[]"]');

            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });

            updateBulkActions();
        }

        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('input[name="selected_orders[]"]:checked');
            const bulkActions = document.getElementById('bulkActions');

            if (checkboxes.length > 0) {
                bulkActions.style.display = 'block';
                document.getElementById('selectedCount').textContent = checkboxes.length;
            } else {
                bulkActions.style.display = 'none';
            }
        }

        // Enhanced error handling
        window.addEventListener('error', function (e) {
            console.error('JavaScript Error:', e.error);

            // Show user-friendly error message
            const errorDiv = document.createElement('div');
            errorDiv.className = 'alert alert-danger';
            errorDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Terjadi kesalahan. Silakan refresh halaman.';

            const content = document.querySelector('.admin-content');
            if (content) {
                content.insertBefore(errorDiv, content.firstChild);

                setTimeout(() => {
                    errorDiv.remove();
                }, 5000);
            }
        });

        // Performance monitoring
        window.addEventListener('load', function () {
            const loadTime = performance.now();
            console.log(`Page loaded in ${loadTime.toFixed(2)}ms`);

            if (loadTime > 3000) {
                console.warn('Page load time is slower than expected');
            }
        });
    </script>

    <style>
        /* Additional responsive styles */
        @media (max-width: 1200px) {
            .filter-row {
                flex-wrap: wrap;
            }

            .form-group {
                min-width: 200px;
                flex: 1;
            }
        }

        @media (max-width: 768px) {

            .admin-table th:nth-child(3),
            .admin-table td:nth-child(3),
            .admin-table th:nth-child(6),
            .admin-table td:nth-child(6) {
                display: none;
            }

            .filter-row {
                flex-direction: column;
            }

            .form-group {
                min-width: 100%;
            }

            .proof-preview-btn {
                font-size: 9px;
                padding: 1px 4px;
            }
        }

        @media (max-width: 480px) {

            .admin-table th:nth-child(4),
            .admin-table td:nth-child(4),
            .admin-table th:nth-child(8),
            .admin-table td:nth-child(8) {
                display: none;
            }

            .customer-info small {
                display: none;
            }

            .payment-method-badge {
                font-size: 10px;
                padding: 2px 4px;
            }
        }

        /* Loading spinner */
        .loading-spinner {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 2px solid #ffffff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Search box loading state */
        .search-box.loading::after {
            content: '';
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 12px;
            height: 12px;
            border: 2px solid #007bff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s ease-in-out infinite;
        }

        /* Tooltip styles */
        .tooltip-popup {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            background-color: var(--admin-secondary);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;
            z-index: 1000;
        }

        @keyframes fadeInTooltip {
            to {
                opacity: 1;
            }
        }

        /* Bulk actions */
        #bulkActions {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 6px;
            padding: 10px 15px;
            margin-bottom: 15px;
            display: none;
        }

        #bulkActions .btn {
            margin-right: 10px;
        }

        /* Enhanced status badges with hover effects */
        .status-badge {
            transition: all 0.2s ease;
        }

        .status-badge:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        /* Enhanced payment method badges */
        .payment-method-badge {
            transition: all 0.2s ease;
        }

        .payment-method-badge:hover {
            transform: translateY(-1px);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        /* Improved proof status styling */
        .proof-status {
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .proof-status:hover {
            transform: scale(1.05);
        }

        /* Enhanced modal animations */
        .modal {
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .modal.show {
            opacity: 1;
            visibility: visible;
        }

        .modal.show .modal-content {
            transform: translateY(0);
        }

        .modal-content {
            transform: translateY(-50px);
            transition: transform 0.3s ease;
        }
    </style>
</body>

</html>