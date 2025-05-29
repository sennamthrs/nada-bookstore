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

// Ambil ID pesanan dari URL
$order_id = $_GET['id'] ?? 0;

// Ambil detail pesanan dengan informasi shipping
$stmt = $pdo->prepare("
    SELECT o.*, u.nama as customer_name, u.email as customer_email, u.no_telepon as customer_phone,
           so.name as shipping_method_name, so.description as shipping_description
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    LEFT JOIN shipping_options so ON o.shipping_option_id = so.id
    WHERE o.id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: orders.php");
    exit;
}

// Ambil item pesanan
$stmt = $pdo->prepare("
    SELECT oi.*, p.image_url, p.category_id 
    FROM order_items oi 
    LEFT JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$message = '';
$messageType = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'update_status':
            $newStatus = $_POST['new_status'];
            $trackingNumber = $_POST['tracking_number'] ?? '';
            $notes = $_POST['admin_notes'] ?? '';

            try {
                $sql = "UPDATE orders SET status = ?, updated_at = NOW()";
                $params = [$newStatus];

                if (!empty($notes)) {
                    $sql .= ", notes = ?";
                    $params[] = $notes;
                }

                if ($newStatus === 'shipped' && !empty($trackingNumber)) {
                    $sql .= ", tracking_number = ?, shipped_at = NOW()";
                    $params[] = $trackingNumber;
                }

                if ($newStatus === 'delivered') {
                    $sql .= ", delivered_at = NOW()";
                }

                $sql .= " WHERE id = ?";
                $params[] = $order_id;

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                $message = "Status pesanan berhasil diperbarui.";
                $messageType = 'success';

                // Refresh order data
                $stmt = $pdo->prepare("
                    SELECT o.*, u.nama as customer_name, u.email as customer_email, u.no_telepon as customer_phone,
                           so.name as shipping_method_name, so.description as shipping_description
                    FROM orders o 
                    JOIN users u ON o.user_id = u.id 
                    LEFT JOIN shipping_options so ON o.shipping_option_id = so.id
                    WHERE o.id = ?
                ");
                $stmt->execute([$order_id]);
                $order = $stmt->fetch(PDO::FETCH_ASSOC);

            } catch (Exception $e) {
                $message = "Error: " . $e->getMessage();
                $messageType = 'danger';
            }
            break;

        case 'add_note':
            $adminNote = $_POST['admin_note'];

            try {
                $stmt = $pdo->prepare("UPDATE orders SET notes = CONCAT(COALESCE(notes, ''), '\n\n[Admin Note - " . date('Y-m-d H:i:s') . "]: ', ?) WHERE id = ?");
                $stmt->execute([$adminNote, $order_id]);

                $message = "Catatan admin berhasil ditambahkan.";
                $messageType = 'success';

                // Refresh order data
                $stmt = $pdo->prepare("
                    SELECT o.*, u.nama as customer_name, u.email as customer_email, u.no_telepon as customer_phone,
                           so.name as shipping_method_name, so.description as shipping_description
                    FROM orders o 
                    JOIN users u ON o.user_id = u.id 
                    LEFT JOIN shipping_options so ON o.shipping_option_id = so.id
                    WHERE o.id = ?
                ");
                $stmt->execute([$order_id]);
                $order = $stmt->fetch(PDO::FETCH_ASSOC);

            } catch (Exception $e) {
                $message = "Error: " . $e->getMessage();
                $messageType = 'danger';
            }
            break;
    }
}

// Status options
$statusOptions = [
    'pending' => 'Menunggu Pembayaran',
    'paid' => 'Dibayar',
    'processing' => 'Diproses',
    'shipped' => 'Dikirim',
    'delivered' => 'Selesai',
    'cancelled' => 'Dibatalkan'
];

function getStatusBadge($status)
{
    $badges = [
        'pending' => ['class' => 'status-warning', 'text' => 'Menunggu Pembayaran', 'icon' => 'fas fa-clock'],
        'paid' => ['class' => 'status-info', 'text' => 'Dibayar', 'icon' => 'fas fa-credit-card'],
        'processing' => ['class' => 'status-primary', 'text' => 'Diproses', 'icon' => 'fas fa-cog'],
        'shipped' => ['class' => 'status-success', 'text' => 'Dikirim', 'icon' => 'fas fa-truck'],
        'delivered' => ['class' => 'status-success', 'text' => 'Selesai', 'icon' => 'fas fa-check-circle'],
        'cancelled' => ['class' => 'status-danger', 'text' => 'Dibatalkan', 'icon' => 'fas fa-times-circle']
    ];

    return $badges[$status] ?? ['class' => 'status-secondary', 'text' => ucfirst($status), 'icon' => 'fas fa-question'];
}

function getPaymentMethodBadge($method)
{
    $badges = [
        'transfer_bank' => ['class' => 'payment-transfer', 'text' => 'Transfer Bank', 'icon' => 'fas fa-university'],
        'cod' => ['class' => 'payment-cod', 'text' => 'Bayar di Tempat', 'icon' => 'fas fa-money-bill-wave'],
        'e_wallet' => ['class' => 'payment-ewallet', 'text' => 'E-Wallet', 'icon' => 'fas fa-mobile-alt']
    ];

    return $badges[$method] ?? ['class' => 'payment-other', 'text' => ucfirst($method), 'icon' => 'fas fa-credit-card'];
}

$statusBadge = getStatusBadge($order['status']);
$paymentBadge = getPaymentMethodBadge($order['payment_method']);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan #<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?> - NADA BookStore Admin</title>
    <link rel="stylesheet" href="admin-styles.css">
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Payment proof specific styles */
        .payment-proof-card {
            background: transparent;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            color: white;
            box-shadow: 0 8px 32px rgba(102, 126, 234, 0.3);
        }

        .payment-method-info {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
        }

        .payment-method-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
        }

        .payment-transfer {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            color: #0d47a1;
            border: 2px solid #1976d2;
        }

        .payment-cod {
            background: linear-gradient(135deg, #fff3e0, #ffcc80);
            color: #e65100;
            border: 2px solid #f57c00;
        }

        .payment-ewallet {
            background: linear-gradient(135deg, #f3e5f5, #ce93d8);
            color: #4a148c;
            border: 2px solid #7b1fa2;
        }

        .proof-status {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 15px;
        }

        .proof-status.uploaded {
            color: #4caf50;
        }

        .proof-status.not-uploaded {
            color: #f44336;
        }

        .proof-preview-container {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 15px;
            backdrop-filter: blur(10px);
        }

        .proof-image-thumbnail {
            max-width: 200px;
            max-height: 150px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .proof-image-thumbnail:hover {
            transform: scale(1.05);
            border-color: white;
        }

        .proof-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn-white {
            background: white;
            color: #667eea;
            border: 2px solid white;
        }

        .btn-white:hover {
            background: rgba(255, 255, 255, 0.9);
            transform: translateY(-2px);
        }

        /* Bank account info */
        .bank-accounts-info {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            backdrop-filter: blur(10px);
        }

        .bank-account {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .bank-account:last-child {
            border-bottom: none;
        }

        .bank-name {
            font-weight: 600;
        }

        .account-number {
            font-family: 'Courier New', monospace;
            background: rgba(255, 255, 255, 0.2);
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .account-number:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        /* Modal enhancements for payment proof */
        .modal-payment-proof .modal-content {
            max-width: 900px;
        }

        .proof-modal-image {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .proof-modal-pdf {
            width: 100%;
            height: 600px;
            border: none;
            border-radius: 8px;
        }

        .order-info-header {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .upload-time-info {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.8);
            margin-top: 5px;
        }
    </style>
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
                    <h1>Detail Pesanan</h1>
                </div>
                <div class="header-right">
                    <a href="orders.php" class="btn btn-outline btn-sm">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
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

                <!-- Order Header -->
                <div class="order-header-card">
                    <div class="order-header-content">
                        <div class="order-info">
                            <h1>Pesanan #<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?></h1>
                            <?php if ($order['order_number']): ?>
                                <div class="order-date">Nomor: <?= htmlspecialchars($order['order_number']) ?></div>
                            <?php else: ?>
                                <div class="order-date">Nomor:
                                    ORD-<?= date('Y', strtotime($order['created_at'])) ?>-<?= str_pad($order['id'], 3, '0', STR_PAD_LEFT) ?>
                                </div>
                            <?php endif; ?>
                            <div class="order-date">
                                <i class="fas fa-calendar"></i>
                                <?= date('d F Y, H:i', strtotime($order['created_at'])) ?>
                            </div>
                        </div>
                        <div class="order-status-large">
                            <div class="status-badge-large">
                                <i class="<?= $statusBadge['icon'] ?>"></i>
                                <?= $statusBadge['text'] ?>
                            </div>
                            <div class="order-total-large">
                                Rp <?= number_format($order['total_amount'], 0, ',', '.') ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Proof Section -->
                <?php if ($order['payment_method'] === 'transfer_bank'): ?>
                    <div class="payment-proof-card">
                        <div class="payment-method-info">
                            <div class="payment-method-badge <?= $paymentBadge['class'] ?>">
                                <i class="<?= $paymentBadge['icon'] ?>"></i>
                                <?= $paymentBadge['text'] ?>
                            </div>
                        </div>

                        <?php if (!empty($order['payment_proof'])): ?>
                            <div class="proof-status uploaded">
                                <i class="fas fa-check-circle"></i>
                                <span><strong>Bukti pembayaran sudah diupload</strong></span>
                            </div>

                            <?php if ($order['payment_proof_uploaded_at']): ?>
                                <div class="upload-time-info">
                                    <i class="fas fa-clock"></i>
                                    Upload: <?= date('d F Y, H:i', strtotime($order['payment_proof_uploaded_at'])) ?>
                                </div>
                            <?php endif; ?>

                            <div class="proof-preview-container">
                                <?php
                                $file_extension = pathinfo($order['payment_proof'], PATHINFO_EXTENSION);
                                $proof_url = '../uploads/payment_proofs/' . $order['payment_proof'];
                                ?>

                                <?php if (in_array(strtolower($file_extension), ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                    <img src="<?= $proof_url ?>" alt="Bukti Pembayaran" class="proof-image-thumbnail"
                                        onclick="viewPaymentProof('<?= htmlspecialchars($order['payment_proof']) ?>', '<?= htmlspecialchars($order['order_number']) ?>', '<?= number_format($order['total_amount'], 0, ',', '.') ?>')">
                                <?php else: ?>
                                    <div style="text-align: center; padding: 20px;">
                                        <i class="fas fa-file-pdf" style="font-size: 48px; margin-bottom: 10px;"></i>
                                        <p>File PDF - Klik tombol di bawah untuk melihat</p>
                                    </div>
                                <?php endif; ?>

                                <div class="proof-actions">
                                    <button class="btn btn-white btn-sm"
                                        onclick="viewPaymentProof('<?= htmlspecialchars($order['payment_proof']) ?>', '<?= htmlspecialchars($order['order_number']) ?>', '<?= number_format($order['total_amount'], 0, ',', '.') ?>')">
                                        <i class="fas fa-eye"></i> Lihat Bukti
                                    </button>
                                    <a href="<?= $proof_url ?>" download class="btn btn-white btn-sm">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="proof-status not-uploaded">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span><strong>Belum ada bukti pembayaran</strong></span>
                            </div>

                            <div class="bank-accounts-info">
                                <h4 style="margin: 0 0 15px 0; color: white;">
                                    <i class="fas fa-university"></i> Rekening Tujuan Transfer
                                </h4>
                                <div class="bank-account">
                                    <div>
                                        <div class="bank-name">Bank BCA</div>
                                        <div style="font-size: 12px; opacity: 0.8;">a.n. NADA BookStore</div>
                                    </div>
                                    <div class="account-number" onclick="copyToClipboard('1234567890')">
                                        1234567890
                                    </div>
                                </div>
                                <div class="bank-account">
                                    <div>
                                        <div class="bank-name">Bank Mandiri</div>
                                        <div style="font-size: 12px; opacity: 0.8;">a.n. NADA BookStore</div>
                                    </div>
                                    <div class="account-number" onclick="copyToClipboard('9876543210')">
                                        9876543210
                                    </div>
                                </div>
                                <div class="bank-account">
                                    <div>
                                        <div class="bank-name">Bank BNI</div>
                                        <div style="font-size: 12px; opacity: 0.8;">a.n. NADA BookStore</div>
                                    </div>
                                    <div class="account-number" onclick="copyToClipboard('5555666677')">
                                        5555666677
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- Non-transfer payment methods -->
                    <div class="payment-proof-card">
                        <div class="payment-method-info">
                            <div class="payment-method-badge <?= $paymentBadge['class'] ?>">
                                <i class="<?= $paymentBadge['icon'] ?>"></i>
                                <?= $paymentBadge['text'] ?>
                            </div>
                        </div>

                        <?php if ($order['payment_method'] === 'cod'): ?>
                            <p><i class="fas fa-info-circle"></i> Pembayaran akan dilakukan saat barang diterima customer.</p>
                        <?php elseif ($order['payment_method'] === 'e_wallet'): ?>
                            <p><i class="fas fa-info-circle"></i> Pembayaran menggunakan e-wallet. Verifikasi manual oleh admin.
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="order-detail-grid">
                    <!-- Main Content -->
                    <div>
                        <!-- Order Items -->
                        <div class="content-card">
                            <div class="card-header">
                                <h3>
                                    <i class="fas fa-box"></i>
                                    Item Pesanan (<?= count($order_items) ?> item)
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="items-list">
                                    <?php foreach ($order_items as $item): ?>
                                        <div class="item-card">
                                            <img src="<?= '../' . $item['image_url'] ?: '../uploads/product/' ?>"
                                                alt="<?= htmlspecialchars($item['product_name']) ?>" class="item-image" />
                                            <div class="item-details">
                                                <div class="item-name"><?= htmlspecialchars($item['product_name']) ?></div>
                                                <div class="item-meta">
                                                    <span>Harga: Rp <?= number_format($item['price'], 0, ',', '.') ?></span>
                                                    <span>Qty: <?= $item['quantity'] ?></span>
                                                </div>
                                            </div>
                                            <div class="item-subtotal">
                                                Rp <?= number_format($item['subtotal'], 0, ',', '.') ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Customer & Shipping Info -->
                        <div class="content-card">
                            <div class="card-header">
                                <h3>
                                    <i class="fas fa-user"></i>
                                    Informasi Customer & Pengiriman
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="customer-card">
                                    <h4><i class="fas fa-user-circle"></i> Informasi Customer</h4>
                                    <p><strong>Nama:</strong> <?= htmlspecialchars($order['customer_name']) ?></p>
                                    <p><strong>Email:</strong> <?= htmlspecialchars($order['customer_email']) ?></p>
                                    <?php if ($order['customer_phone']): ?>
                                        <p><strong>Telepon:</strong> <?= htmlspecialchars($order['customer_phone']) ?></p>
                                    <?php endif; ?>
                                </div>

                                <?php if ($order['shipping_address']): ?>
                                    <div class="customer-card">
                                        <h4><i class="fas fa-truck"></i> Alamat Pengiriman</h4>
                                        <p><?= nl2br(htmlspecialchars($order['shipping_address'])) ?></p>
                                        <?php if ($order['shipping_city']): ?>
                                            <p><strong>Kota:</strong> <?= htmlspecialchars($order['shipping_city']) ?></p>
                                        <?php endif; ?>
                                        <?php if ($order['shipping_province']): ?>
                                            <p><strong>Provinsi:</strong> <?= htmlspecialchars($order['shipping_province']) ?>
                                            </p>
                                        <?php endif; ?>
                                        <?php if ($order['shipping_postal_code']): ?>
                                            <p><strong>Kode Pos:</strong>
                                                <?= htmlspecialchars($order['shipping_postal_code']) ?></p>
                                        <?php endif; ?>
                                        <?php if ($order['shipping_method_name']): ?>
                                            <p><strong>Metode Pengiriman:</strong>
                                                <?= htmlspecialchars($order['shipping_method_name']) ?></p>
                                            <?php if ($order['shipping_description']): ?>
                                                <p><strong>Deskripsi:</strong>
                                                    <?= htmlspecialchars($order['shipping_description']) ?></p>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($order['notes']): ?>
                                    <div class="customer-card">
                                        <h4><i class="fas fa-sticky-note"></i> Catatan</h4>
                                        <p><?= nl2br(htmlspecialchars($order['notes'])) ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div>
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
                                    <button onclick="showUpdateStatusModal()" class="btn btn-primary">
                                        <i class="fas fa-edit"></i> Update Status
                                    </button>

                                    <button onclick="showAddNoteModal()" class="btn btn-outline">
                                        <i class="fas fa-comment"></i> Tambah Catatan
                                    </button>

                                    <a href="mailto:<?= htmlspecialchars($order['customer_email']) ?>"
                                        class="btn btn-outline">
                                        <i class="fas fa-envelope"></i> Email Customer
                                    </a>

                                    <button onclick="window.print()" class="btn btn-outline">
                                        <i class="fas fa-print"></i> Cetak Invoice
                                    </button>

                                    <?php if ($order['payment_method'] === 'transfer_bank' && empty($order['payment_proof'])): ?>
                                        <a href="../upload_payment_proof.php?order_id=<?= $order['id'] ?>"
                                            class="btn btn-outline" target="_blank">
                                            <i class="fas fa-upload"></i> Upload Bukti
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Order Summary -->
                        <div class="content-card">
                            <div class="card-header">
                                <h3>
                                    <i class="fas fa-receipt"></i>
                                    Ringkasan Pesanan
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="customer-card">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                        <span>Subtotal:</span>
                                        <span>Rp <?= number_format($order['subtotal'], 0, ',', '.') ?></span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                        <span>Ongkos Kirim:</span>
                                        <span>Rp <?= number_format($order['shipping_cost'], 0, ',', '.') ?></span>
                                    </div>
                                    <?php if ($order['tax_amount'] > 0): ?>
                                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                            <span>Pajak:</span>
                                            <span>Rp <?= number_format($order['tax_amount'], 0, ',', '.') ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <hr>
                                    <div
                                        style="display: flex; justify-content: space-between; font-weight: bold; font-size: 1.1rem;">
                                        <span>Total:</span>
                                        <span>Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></span>
                                    </div>
                                </div>

                                <div style="margin-top: 15px; color: white;">
                                    <p><strong>Metode Pembayaran:</strong><br>
                                        <span class="payment-method-badge <?= $paymentBadge['class'] ?>"
                                            style="margin-top: 5px;">
                                            <i class="<?= $paymentBadge['icon'] ?>"></i>
                                            <?= $paymentBadge['text'] ?>
                                        </span>
                                    </p>

                                    <?php if ($order['tracking_number']): ?>
                                        <p><strong>Nomor Resi:</strong><br>
                                            <span
                                                style="font-family: 'Courier New', monospace; background: rgba(255,255,255,0.1); padding: 4px 8px; border-radius: 4px;">
                                                <?= htmlspecialchars($order['tracking_number']) ?>
                                            </span>
                                        </p>
                                    <?php endif; ?>

                                    <?php if ($order['payment_method'] === 'transfer_bank'): ?>
                                        <p><strong>Status Bukti Pembayaran:</strong><br>
                                            <?php if (!empty($order['payment_proof'])): ?>
                                                <span style="color: #4caf50;">
                                                    <i class="fas fa-check-circle"></i> Sudah diupload
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #f44336;">
                                                    <i class="fas fa-times-circle"></i> Belum diupload
                                                </span>
                                            <?php endif; ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Order Timeline -->
                        <div class="content-card">
                            <div class="card-header">
                                <h3>
                                    <i class="fas fa-history"></i>
                                    Timeline Pesanan
                                </h3>
                            </div>
                            <div class="card-body">
                                <style>
                                    .timeline-content h5 {
                                        color: white;
                                    }
                                </style>
                                <div class="timeline">
                                    <div
                                        class="timeline-item <?= in_array($order['status'], ['pending', 'paid', 'processing', 'shipped', 'delivered']) ? 'active' : '' ?>">
                                        <div class="timeline-content">
                                            <h5>Pesanan Dibuat</h5>
                                            <div style="color : white;">
                                                <div class="timeline-date">
                                                    <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <?php if (in_array($order['status'], ['paid', 'processing', 'shipped', 'delivered'])): ?>
                                        <div class="timeline-item active">
                                            <div class="timeline-content">
                                                <h5>Pembayaran Dikonfirmasi</h5>
                                                <div class="timeline-date">
                                                    <?= date('d/m/Y H:i', strtotime($order['updated_at'])) ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (in_array($order['status'], ['processing', 'shipped', 'delivered'])): ?>
                                        <div class="timeline-item active">
                                            <div class="timeline-content">
                                                <h5>Pesanan Diproses</h5>
                                                <div class="timeline-date">
                                                    <?= date('d/m/Y H:i', strtotime($order['updated_at'])) ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (in_array($order['status'], ['shipped', 'delivered'])): ?>
                                        <div class="timeline-item active">
                                            <div class="timeline-content">
                                                <h5>Pesanan Dikirim</h5>
                                                <div class="timeline-date">
                                                    <?= $order['shipped_at'] ? date('d/m/Y H:i', strtotime($order['shipped_at'])) : date('d/m/Y H:i', strtotime($order['updated_at'])) ?>
                                                </div>
                                                <?php if ($order['tracking_number']): ?>
                                                    <div style="font-size: 12px; margin-top: 5px;">
                                                        Resi: <?= htmlspecialchars($order['tracking_number']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($order['status'] === 'delivered'): ?>
                                        <div class="timeline-item active">
                                            <div class="timeline-content">
                                                <h5>Pesanan Selesai</h5>
                                                <div class="timeline-date">
                                                    <?= $order['delivered_at'] ? date('d/m/Y H:i', strtotime($order['delivered_at'])) : date('d/m/Y H:i', strtotime($order['updated_at'])) ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($order['status'] === 'cancelled'): ?>
                                        <div class="timeline-item active">
                                            <div class="timeline-content">
                                                <h5>Pesanan Dibatalkan</h5>
                                                <div class="timeline-date">
                                                    <?= date('d/m/Y H:i', strtotime($order['updated_at'])) ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($order['payment_method'] === 'transfer_bank' && !empty($order['payment_proof'])): ?>
                                        <div class="timeline-item active" style="border-left-color: #4caf50;">
                                            <div class="timeline-content">
                                                <h5>Bukti Pembayaran Diupload</h5>
                                                <div class="timeline-date">
                                                    <?= $order['payment_proof_uploaded_at'] ? date('d/m/Y H:i', strtotime($order['payment_proof_uploaded_at'])) : 'Tanggal tidak tersedia' ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div id="updateStatusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Update Status Pesanan</h3>
                <button type="button" class="modal-close" onclick="closeUpdateStatusModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_status">

                    <div class="form-group">
                        <label class="form-label">Status Baru</label>
                        <select name="new_status" id="newStatus" class="form-control form-select" required>
                            <?php foreach ($statusOptions as $value => $label): ?>
                                <option value="<?= $value ?>" <?= $order['status'] === $value ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" id="trackingGroup" style="display: none;">
                        <label class="form-label">Nomor Resi</label>
                        <input type="text" name="tracking_number" id="trackingNumber" class="form-control"
                            placeholder="Masukkan nomor resi"
                            value="<?= htmlspecialchars($order['tracking_number'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Catatan Admin (Opsional)</label>
                        <textarea name="admin_notes" class="form-control" rows="3"
                            placeholder="Tambahkan catatan untuk customer..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeUpdateStatusModal()">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Note Modal -->
    <div id="addNoteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Tambah Catatan Admin</h3>
                <button type="button" class="modal-close" onclick="closeAddNoteModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_note">

                    <div class="form-group">
                        <label class="form-label">Catatan</label>
                        <textarea name="admin_note" class="form-control" rows="4"
                            placeholder="Masukkan catatan admin..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeAddNoteModal()">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Catatan
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
                <div id="orderInfoSummary" class="order-info-header"></div>
                <div id="proofImageContainer" class="proof-image-container">
                    <img id="proofImage" src="" alt="Bukti Pembayaran" class="proof-modal-image" style="display: none;">
                    <iframe id="proofPdf" src="" class="proof-modal-pdf" style="display: none;"></iframe>
                    <div id="proofError" style="display: none; text-align: center; color: #dc3545; padding: 40px;">
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
        // Update Status Modal
        function showUpdateStatusModal() {
            const modal = document.getElementById('updateStatusModal');
            const statusSelect = document.getElementById('newStatus');
            const trackingGroup = document.getElementById('trackingGroup');

            function toggleTracking() {
                if (statusSelect.value === 'shipped') {
                    trackingGroup.style.display = 'block';
                } else {
                    trackingGroup.style.display = 'none';
                }
            }

            statusSelect.addEventListener('change', toggleTracking);
            toggleTracking();

            modal.classList.add('show');
        }

        function closeUpdateStatusModal() {
            document.getElementById('updateStatusModal').classList.remove('show');
        }

        // Add Note Modal
        function showAddNoteModal() {
            document.getElementById('addNoteModal').classList.add('show');
        }

        function closeAddNoteModal() {
            document.getElementById('addNoteModal').classList.remove('show');
        }

        // Payment Proof Modal
        function viewPaymentProof(filename, orderNumber, totalAmount) {
            const modal = document.getElementById('paymentProofModal');
            const orderInfo = document.getElementById('orderInfoSummary');
            const proofImage = document.getElementById('proofImage');
            const proofPdf = document.getElementById('proofPdf');
            const proofError = document.getElementById('proofError');
            const downloadBtn = document.getElementById('downloadProofBtn');

            // Set order info
            orderInfo.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong>Pesanan:</strong> ${orderNumber}<br>
                        <strong>Total:</strong> Rp. ${totalAmount}
                    </div>
                    <div style="color: #28a745;">
                        <i class="fas fa-check-circle"></i> Bukti Pembayaran
                    </div>
                </div>
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

        // Copy to clipboard function
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function () {
                showNotification('Nomor rekening berhasil disalin!', 'success');
            }).catch(function () {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                showNotification('Nomor rekening berhasil disalin!', 'success');
            });
        }

        // Show notification
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type}`;
            notification.style.position = 'fixed';
            notification.style.top = '20px';
            notification.style.right = '20px';
            notification.style.zIndex = '10000';
            notification.style.minWidth = '300px';
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
                ${message}
            `;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateY(-20px)';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Close modals when clicking outside
        document.addEventListener('click', function (e) {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('show');
            }
        });

        // Auto-hide alerts
        document.addEventListener('DOMContentLoaded', function () {
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

            // Add loading state to forms
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

            // Enhanced hover effects for bank accounts
            const accountNumbers = document.querySelectorAll('.account-number');
            accountNumbers.forEach(account => {
                account.addEventListener('mouseenter', function () {
                    this.style.transform = 'scale(1.05)';
                });

                account.addEventListener('mouseleave', function () {
                    this.style.transform = 'scale(1)';
                });
            });
        });

        // Print function
        function printInvoice() {
            window.print();
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function (e) {
            // ESC to close modals
            if (e.key === 'Escape') {
                closeUpdateStatusModal();
                closeAddNoteModal();
                closePaymentProofModal();
            }

            // Ctrl+P to print
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                printInvoice();
            }

            // Ctrl+E to edit status
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                showUpdateStatusModal();
            }
        });

        // Auto-refresh for payment proof updates
        let autoRefreshInterval = setInterval(() => {
            // Only refresh if no modals are open
            if (!document.querySelector('.modal.show')) {
                // Check if payment proof status has changed
                fetch(window.location.href)
                    .then(response => response.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const newDoc = parser.parseFromString(html, 'text/html');
                        const currentProofStatus = document.querySelector('.proof-status');
                        const newProofStatus = newDoc.querySelector('.proof-status');

                        if (currentProofStatus && newProofStatus &&
                            currentProofStatus.className !== newProofStatus.className) {
                            location.reload();
                        }
                    })
                    .catch(error => console.log('Auto-refresh error:', error));
            }
        }, 30000); // Check every 30 seconds

        // Stop auto-refresh when page is not visible
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                clearInterval(autoRefreshInterval);
            } else {
                autoRefreshInterval = setInterval(() => {
                    if (!document.querySelector('.modal.show')) {
                        fetch(window.location.href)
                            .then(response => response.text())
                            .then(html => {
                                const parser = new DOMParser();
                                const newDoc = parser.parseFromString(html, 'text/html');
                                const currentProofStatus = document.querySelector('.proof-status');
                                const newProofStatus = newDoc.querySelector('.proof-status');

                                if (currentProofStatus && newProofStatus &&
                                    currentProofStatus.className !== newProofStatus.className) {
                                    location.reload();
                                }
                            })
                            .catch(error => console.log('Auto-refresh error:', error));
                    }
                }, 30000);
            }
        });
    </script>

    <style>
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

        /* Print styles */
        @media print {

            .admin-sidebar,
            .btn,
            .modal,
            .quick-actions {
                display: none !important;
            }

            .admin-main {
                margin-left: 0 !important;
            }

            .payment-proof-card {
                background: white !important;
                color: black !important;
            }

            .payment-method-badge {
                border: 1px solid #333 !important;
                color: black !important;
                background: white !important;
            }
        }

        /* Responsive enhancements */
        @media (max-width: 768px) {
            .payment-proof-card {
                padding: 15px;
                margin-bottom: 15px;
            }

            .proof-image-thumbnail {
                max-width: 150px;
                max-height: 100px;
            }

            .bank-account {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }

            .account-number {
                font-size: 12px;
            }

            .order-detail-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
        }
    </style>
</body>

</html>