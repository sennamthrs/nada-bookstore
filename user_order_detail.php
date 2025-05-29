<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include file auth.php yang berisi fungsi-fungsi otentikasi
require_once 'auth.php';
require_once 'db.php';

// Fungsi untuk memeriksa apakah user login
function requireLogin()
{
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
}

// Jalankan pengecekan login
requireLogin();
$user = getLoggedUser();

// Validasi parameter ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: my_orders.php");
    exit;
}

$order_id = (int) $_GET['id'];

// Fungsi untuk mendapatkan badge status
function getStatusBadge($status)
{
    $badges = [
        'pending' => ['class' => 'badge-warning', 'text' => 'Menunggu', 'icon' => 'clock'],
        'paid' => ['class' => 'badge-info', 'text' => 'Dibayar', 'icon' => 'check-circle'],
        'processing' => ['class' => 'badge-primary', 'text' => 'Diproses', 'icon' => 'cog'],
        'shipped' => ['class' => 'badge-secondary', 'text' => 'Dikirim', 'icon' => 'shipping-fast'],
        'delivered' => ['class' => 'badge-success', 'text' => 'Selesai', 'icon' => 'check-double'],
        'cancelled' => ['class' => 'badge-danger', 'text' => 'Dibatalkan', 'icon' => 'times-circle']
    ];

    return $badges[$status] ?? ['class' => 'badge-default', 'text' => ucfirst($status), 'icon' => 'info-circle'];
}

// Fungsi untuk format payment method
function formatPaymentMethod($method)
{
    $methods = [
        'transfer_bank' => 'Transfer Bank',
        'credit_card' => 'Kartu Kredit',
        'e_wallet' => 'E-Wallet',
        'cod' => 'Bayar di Tempat (COD)'
    ];

    return $methods[$method] ?? ucfirst(str_replace('_', ' ', $method));
}

// Fungsi untuk mendapatkan badge payment method
function getPaymentMethodBadge($method)
{
    $badges = [
        'transfer_bank' => ['class' => 'payment-transfer', 'icon' => 'university'],
        'credit_card' => ['class' => 'payment-card', 'icon' => 'credit-card'],
        'e_wallet' => ['class' => 'payment-ewallet', 'icon' => 'mobile-alt'],
        'cod' => ['class' => 'payment-cod', 'icon' => 'money-bill-wave']
    ];

    return $badges[$method] ?? ['class' => 'payment-default', 'icon' => 'credit-card'];
}

// Query untuk mendapatkan detail pesanan
$stmt = $pdo->prepare("
    SELECT 
        o.*,
        so.name as shipping_name,
        so.description as shipping_description,
        so.estimated_days as shipping_estimated
    FROM orders o
    LEFT JOIN shipping_options so ON o.shipping_option_id = so.id
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$order_id, $user['id']]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

// Jika pesanan tidak ditemukan atau bukan milik user ini
if (!$order) {
    header("Location: my_orders.php");
    exit;
}

// Query untuk mendapatkan item-item dalam pesanan
$stmt = $pdo->prepare("
    SELECT 
        oi.*,
        p.name as current_product_name,
        p.price as current_price,
        p.image_url as current_image
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fungsi untuk menghitung timeline status
function getOrderTimeline($order)
{
    $current_status = $order['status'];

    // Definisi semua langkah timeline
    $timeline = [];

    // 1. Pesanan Dibuat - SELALU completed
    $timeline[] = [
        'status' => 'pending',
        'text' => 'Pesanan Dibuat',
        'date' => $order['created_at'],
        'completed' => true // Selalu true karena pesanan sudah ada
    ];

    // 2. Bukti Pembayaran (hanya untuk transfer bank)
    if ($order['payment_method'] === 'transfer_bank') {
        $timeline[] = [
            'status' => 'payment_proof',
            'text' => 'Bukti Pembayaran Diupload',
            'date' => $order['payment_proof_uploaded_at'],
            'completed' => !empty($order['payment_proof'])
        ];
    }

    // 3. Pembayaran Dikonfirmasi
    $timeline[] = [
        'status' => 'paid',
        'text' => 'Pembayaran Dikonfirmasi',
        'date' => null, // Bisa ditambahkan field paid_at di database
        'completed' => in_array($current_status, ['paid', 'processing', 'shipped', 'delivered'])
    ];

    // 4. Pesanan Diproses
    $timeline[] = [
        'status' => 'processing',
        'text' => 'Pesanan Diproses',
        'date' => null, // Bisa ditambahkan field processing_at di database
        'completed' => in_array($current_status, ['processing', 'shipped', 'delivered'])
    ];

    // 5. Pesanan Dikirim
    $timeline[] = [
        'status' => 'shipped',
        'text' => 'Pesanan Dikirim',
        'date' => $order['shipped_at'],
        'completed' => in_array($current_status, ['shipped', 'delivered'])
    ];

    // 6. Pesanan Diterima
    $timeline[] = [
        'status' => 'delivered',
        'text' => 'Pesanan Diterima',
        'date' => $order['delivered_at'],
        'completed' => ($current_status === 'delivered')
    ];

    // Jika pesanan dibatalkan, hanya langkah pertama yang completed
    if ($current_status === 'cancelled') {
        foreach ($timeline as &$step) {
            if ($step['status'] !== 'pending') {
                $step['completed'] = false;
            }
        }
    }

    return $timeline;
}

$timeline = getOrderTimeline($order);

// Data rekening bank untuk transfer
$bank_accounts = [
    [
        'bank' => 'Bank BCA',
        'account_number' => '1234567890',
        'account_name' => 'NADA BookStore',
        'code' => 'BCA'
    ],
    [
        'bank' => 'Bank Mandiri',
        'account_number' => '9876543210',
        'account_name' => 'NADA BookStore',
        'code' => 'MANDIRI'
    ],
    [
        'bank' => 'Bank BNI',
        'account_number' => '5555666677',
        'account_name' => 'NADA BookStore',
        'code' => 'BNI'
    ]
];

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Detail Pesanan #<?= htmlspecialchars($order['order_number']) ?> - NADA BookStore</title>
    <link rel="stylesheet" href="styles.css" />
    <link rel="stylesheet" href="user_order_detail.css" />
    <link rel="stylesheet" href="profile.css" />
    <link rel="stylesheet" href="checkout-payment.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        /* Payment proof specific styles for user view */
        .payment-proof-section {
            background: #e65100;
            border-radius: 12px;
            padding: 25px;
            margin: 20px 0;
            color: white;
            box-shadow: 0 8px 32px rgba(102, 126, 234, 0.3);
        }

        .payment-method-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
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

        .payment-card {
            background: linear-gradient(135deg, #e8f5e8, #c8e6c9);
            color: #2e7d32;
            border: 2px solid #4caf50;
        }

        .proof-status-indicator {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            backdrop-filter: blur(10px);
        }

        .proof-uploaded {
            border-left: 4px solid #4caf50;
        }

        .proof-not-uploaded {
            border-left: 4px solid #f44336;
        }

        .proof-preview-container {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 20px;
            margin-top: 15px;
            backdrop-filter: blur(10px);
        }

        .proof-image-preview {
            max-width: 300px;
            max-height: 200px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid rgba(255, 255, 255, 0.3);
            display: block;
            margin: 0 auto;
        }

        .proof-image-preview:hover {
            transform: scale(1.05);
            border-color: white;
        }

        .proof-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-white {
            background: white;
            color: #007bff;
            border: 2px solid white;
            padding: 5px 10px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-white:hover {
            background: rgba(255, 255, 255, 0.9);
            transform: translateY(-2px);
            color: #667eea;
            text-decoration: none;
        }

        .bank-accounts-list {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 20px;
            margin-top: 15px;
            backdrop-filter: blur(10px);
        }

        .bank-account-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .bank-account-item:last-child {
            border-bottom: none;
        }

        .bank-info {
            display: flex;
            flex-direction: column;
        }

        .bank-name {
            font-weight: 600;
            font-size: 16px;
        }

        .bank-owner {
            font-size: 12px;
            opacity: 0.8;
        }

        .account-number {
            font-family: 'Courier New', monospace;
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .account-number:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.02);
        }

        .upload-instruction {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            text-align: center;
            backdrop-filter: blur(10px);
        }

        .upload-instruction p {
            margin: 0;
            font-size: 14px;
            opacity: 0.9;
        }

        /* Modal styles for payment proof */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(5px);
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            margin: 20px;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            background: #e65100;
            color: white;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .modal-body {
            padding: 20px;
            max-height: 62vh;
            overflow-y: auto;
        }

        .modal-image {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .modal-pdf {
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
            text-align: center;
            color: #495057;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .payment-proof-section {
                padding: 20px 15px;
                margin: 15px 0;
            }

            .bank-account-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .account-number {
                align-self: stretch;
                text-align: center;
            }

            .proof-actions {
                flex-direction: column;
            }

            .btn-white {
                justify-content: center;
            }

            .modal-content {
                width: 95%;
                margin: 10px;
            }
        }

        /* Copy notification */
        .copy-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #28a745;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            z-index: 1001;
            opacity: 0;
            transform: translateY(-20px);
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }

        .copy-notification.show {
            opacity: 1;
            transform: translateY(0);
        }

        /* Timeline enhancement for payment proof */
        .timeline-step.payment-proof {
            border-left-color: #4caf50;
        }

        .timeline-step.payment-proof .step-icon {
            background: #4caf50;
        }
    </style>
</head>

<body>
    <header class="main-header">
        <div class="header-content">
            <a href="index.php" class="logo">
                <i class="fas fa-book"></i>
                <h1>NADA BookStore</h1>
            </a>

            <nav class="main-nav">
                <ul class="nav-links">
                    <li><a href="index.php">Beranda</a></li>
                    <li><a href="products.php">Produk</a></li>
                </ul>

                <div class="auth-buttons">
                    <?php if ($user['role'] === 'admin'): ?>
                        <a href="admin/dashboard.php" class="auth-btn login-btn">
                            <i class="fas fa-cog"></i> Admin
                        </a>
                    <?php endif; ?>
                    <a href="cart.php" class="auth-btn login-btn">
                        <i class="fas fa-shopping-cart"></i> Keranjang
                    </a>
                    <div class="user-menu">
                        <button class="user-email-btn">
                            <i class="fas fa-user"></i> <?= htmlspecialchars($user['nama']) ?>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="user-dropdown">
                            <a href="profile.php"><i class="fas fa-user-circle"></i> Profil</a>
                            <a href="my_orders.php"><i class="fas fa-shopping-bag"></i> Pesanan Saya</a>
                            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </nav>
        </div>
    </header>

    <main>
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($_SESSION['success_message']) ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?= htmlspecialchars($_SESSION['error_message']) ?>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        <div class="profile-container">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <h1><?= htmlspecialchars($user['nama']) ?></h1>
                <p><?= htmlspecialchars($user['email']) ?></p>
            </div>

            <div class="profile-content">
                <!-- Sidebar Menu -->
                <div class="profile-sidebar">
                    <ul class="sidebar-menu">
                        <li><a href="profile.php"><i class="fas fa-user"></i> Profil Saya</a></li>
                        <li><a href="my_orders.php" class="active"><i class="fas fa-shopping-bag"></i> Pesanan
                                Saya</a>
                        </li>
                    </ul>
                </div>

                <!-- Main Content -->
                <div class="profile-main">
                    <div class="order-detail-container">
                        <!-- Order Header -->
                        <div class="order-detail-header">
                            <div class="order-title">
                                <h2>Order #<?= htmlspecialchars($order['order_number']) ?></h2>
                                <div class="order-date">
                                    <i class="fas fa-calendar"></i>
                                    <?= date('d F Y, H:i', strtotime($order['created_at'])) ?>
                                </div>
                            </div>
                            <div class="order-status-badge">
                                <?php
                                $statusBadge = getStatusBadge($order['status']);
                                ?>
                                <span class="badge <?= $statusBadge['class'] ?>">
                                    <i class="fas fa-<?= $statusBadge['icon'] ?>"></i>
                                    <?= $statusBadge['text'] ?>
                                </span>
                            </div>
                        </div>

                        <!-- Payment Proof Section -->
                        <?php if ($order['payment_method'] === 'transfer_bank'): ?>
                            <div class="payment-proof-section">
                                <div class="payment-method-header">
                                    <?php $paymentBadge = getPaymentMethodBadge($order['payment_method']); ?>
                                    <div class="payment-method-badge <?= $paymentBadge['class'] ?>">
                                        <i class="fas fa-<?= $paymentBadge['icon'] ?>"></i>
                                        <?= formatPaymentMethod($order['payment_method']) ?>
                                    </div>
                                </div>

                                <?php if (!empty($order['payment_proof'])): ?>
                                    <div class="proof-status-indicator proof-uploaded">
                                        <i class="fas fa-check-circle" style="color: #4caf50; font-size: 20px;"></i>
                                        <div>
                                            <strong>Bukti pembayaran sudah diupload</strong>
                                            <?php if ($order['payment_proof_uploaded_at']): ?>
                                                <div style="font-size: 12px; opacity: 0.8; margin-top: 2px;">
                                                    Upload:
                                                    <?= date('d F Y, H:i', strtotime($order['payment_proof_uploaded_at'])) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="proof-preview-container">
                                        <?php
                                        $file_extension = pathinfo($order['payment_proof'], PATHINFO_EXTENSION);
                                        $proof_url = 'uploads/payment_proofs/' . $order['payment_proof'];
                                        ?>

                                        <?php if (in_array(strtolower($file_extension), ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                            <img src="<?= $proof_url ?>" alt="Bukti Pembayaran" class="proof-image-preview"
                                                onclick="viewPaymentProof('<?= htmlspecialchars($order['payment_proof']) ?>', '<?= htmlspecialchars($order['order_number']) ?>', '<?= number_format($order['total_amount'], 0, ',', '.') ?>')">
                                        <?php else: ?>
                                            <div style="text-align: center; padding: 30px;">
                                                <i class="fas fa-file-pdf"
                                                    style="font-size: 64px; margin-bottom: 15px; opacity: 0.8;"></i>
                                                <p style="margin: 0;">File PDF - Klik tombol di bawah untuk melihat</p>
                                            </div>
                                        <?php endif; ?>

                                        <div class="proof-actions">
                                            <button class="btn-white"
                                                onclick="viewPaymentProof('<?= htmlspecialchars($order['payment_proof']) ?>', '<?= htmlspecialchars($order['order_number']) ?>', '<?= number_format($order['total_amount'], 0, ',', '.') ?>')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <a href="<?= $proof_url ?>" download class="btn-white">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="proof-status-indicator proof-not-uploaded">
                                        <i class="fas fa-exclamation-triangle" style="color: #f44336; font-size: 20px;"></i>
                                        <div>
                                            <strong>Bukti pembayaran belum diupload</strong>
                                            <div style="font-size: 12px; opacity: 0.8; margin-top: 2px;">
                                                Silakan upload bukti transfer untuk mempercepat proses verifikasi
                                            </div>
                                        </div>
                                    </div>

                                    <div class="bank-accounts-list">
                                        <h4 style="margin: 0 0 15px 0; text-align: center;">
                                            <i class="fas fa-university"></i> Rekening Tujuan Transfer
                                        </h4>
                                        <?php foreach ($bank_accounts as $bank): ?>
                                            <div class="bank-account-item">
                                                <div class="bank-info">
                                                    <div class="bank-name"><?= $bank['bank'] ?></div>
                                                    <div class="bank-owner">a.n. <?= $bank['account_name'] ?></div>
                                                </div>
                                                <div class="account-number"
                                                    onclick="copyToClipboard('<?= $bank['account_number'] ?>')">
                                                    <?= $bank['account_number'] ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <div class="upload-instruction">
                                        <p><i class="fas fa-info-circle"></i> Setelah melakukan transfer, silakan upload
                                            bukti
                                            pembayaran</p>
                                    </div>

                                    <div class="proof-actions">
                                        <a href="upload_payment_proof.php?order_id=<?= $order['id'] ?>" class="btn-white">
                                            <i class="fas fa-upload"></i> Upload Bukti Pembayaran
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <!-- Non-transfer payment methods -->
                            <div class="payment-proof-section">
                                <div class="payment-method-header">
                                    <?php $paymentBadge = getPaymentMethodBadge($order['payment_method']); ?>
                                    <div class="payment-method-badge <?= $paymentBadge['class'] ?>">
                                        <i class="fas fa-<?= $paymentBadge['icon'] ?>"></i>
                                        <?= formatPaymentMethod($order['payment_method']) ?>
                                    </div>
                                </div>

                                <div class="proof-status-indicator">
                                    <i class="fas fa-info-circle" style="color: #17a2b8; font-size: 20px;"></i>
                                    <div>
                                        <?php if ($order['payment_method'] === 'cod'): ?>
                                            <strong>Pembayaran di tempat saat barang diterima</strong>
                                            <div style="font-size: 12px; opacity: 0.8; margin-top: 2px;">
                                                Siapkan uang pas sesuai total pembayaran
                                            </div>
                                        <?php elseif ($order['payment_method'] === 'e_wallet'): ?>
                                            <strong>Pembayaran melalui E-Wallet</strong>
                                            <div style="font-size: 12px; opacity: 0.8; margin-top: 2px;">
                                                Verifikasi pembayaran akan dilakukan oleh admin
                                            </div>
                                        <?php else: ?>
                                            <strong>Metode pembayaran:
                                                <?= formatPaymentMethod($order['payment_method']) ?></strong>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Order Timeline -->
                        <?php if ($order['status'] !== 'cancelled'): ?>
                            <div class="order-timeline">
                                <h3 class="timeline-title">Status Pesanan</h3>
                                <div class="timeline-steps">
                                    <?php foreach ($timeline as $step): ?>
                                        <div
                                            class="timeline-step <?= $step['completed'] ? 'completed' : '' ?> <?= $step['status'] === 'payment_proof' ? 'payment-proof' : '' ?>">
                                            <div class="step-icon">
                                                <i class="fas fa-<?= $step['completed'] ? 'check' : 'circle' ?>"></i>
                                            </div>
                                            <div class="step-text"><?= $step['text'] ?></div>
                                            <?php if ($step['date']): ?>
                                                <div class="step-date"><?= date('d/m/Y', strtotime($step['date'])) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Tracking Info
                        <?php if ($order['tracking_number']): ?>
                            <div class="tracking-section">
                                <h4><i class="fas fa-shipping-fast"></i> Resi</h4>
                                <div>Nomor Resi:</div>
                                <div class="tracking-number"><?= htmlspecialchars($order['tracking_number']) ?></div>
                            </div>
                        <?php endif; ?> -->

                        <!-- Order Info Grid -->
                        <div class="order-info-grid">
                            <!-- Payment Info -->
                            <div class="info-section">
                                <h3><i class="fas fa-credit-card"></i> Informasi Pembayaran</h3>
                                <div class="info-item">
                                    <span class="info-label">Metode Pembayaran:</span>
                                    <span class="info-value">
                                        <?php $paymentBadge = getPaymentMethodBadge($order['payment_method']); ?>
                                        <span class="payment-method-badge <?= $paymentBadge['class'] ?>"
                                            style="font-size: 12px; padding: 4px 8px;">
                                            <i class="fas fa-<?= $paymentBadge['icon'] ?>"></i>
                                            <?= formatPaymentMethod($order['payment_method']) ?>
                                        </span>
                                    </span>
                                </div>
                                <?php if ($order['payment_method'] === 'transfer_bank'): ?>
                                    <div class="info-item">
                                        <span class="info-label">Status Bukti:</span>
                                        <span class="info-value">
                                            <?php if (!empty($order['payment_proof'])): ?>
                                                <span style="color: #28a745; font-weight: 600;">
                                                    <i class="fas fa-check-circle"></i> Sudah Upload
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #dc3545; font-weight: 600;">
                                                    <i class="fas fa-times-circle"></i> Belum Upload
                                                </span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Shipping Info -->
                            <div class="info-section">
                                <h3><i class="fas fa-truck"></i> Informasi Pengiriman</h3>
                                <div class="info-item">
                                    <span class="info-label">Metode Pengiriman:</span>
                                    <span
                                        class="info-value"><?= htmlspecialchars($order['shipping_name'] ?? 'Reguler') ?></span>
                                </div>
                                <?php if ($order['shipping_estimated']): ?>
                                    <div class="info-item">
                                        <span class="info-label">Estimasi:</span>
                                        <span
                                            class="info-value"><?= htmlspecialchars($order['shipping_estimated']) ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($order['tracking_number']): ?>
                                    <div class="info-item">
                                        <span class="info-label">Nomor Resi:</span>
                                        <span class="info-value"
                                            style="font-family: 'Courier New', monospace; font-weight: 600;">
                                            <?= htmlspecialchars($order['tracking_number']) ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Shipping Address -->
                        <div class="info-section">
                            <h3><i class="fas fa-map-marker-alt"></i> Alamat Pengiriman</h3>
                            <div class="address-text">
                                <?= nl2br(htmlspecialchars($order['shipping_address'] ?? '')) ?><br>
                                <?= htmlspecialchars($order['shipping_city'] ?? '') ?>,
                                <?= htmlspecialchars($order['shipping_province'] ?? '') ?>
                                <?= htmlspecialchars($order['shipping_postal_code'] ?? '') ?>
                            </div>
                        </div>

                        <!-- Order Items -->
                        <div class="order-items-section">
                            <div class="section-header">
                                <h3><i class="fas fa-shopping-basket"></i> Item Pesanan</h3>
                            </div>

                            <?php foreach ($order_items as $item): ?>
                                <div class="order-item">
                                    <img src="<?= htmlspecialchars($item['product_image'] ?? 'assets/no-image.jpg') ?>"
                                        alt="<?= htmlspecialchars($item['product_name']) ?>" class="item-image">
                                    <div class="item-details">
                                        <div class="item-name"><?= htmlspecialchars($item['product_name']) ?></div>
                                        <div class="item-info">Jumlah: <?= $item['quantity'] ?> item</div>
                                    </div>
                                    <div class="item-price">
                                        <div class="price-per-item">@ Rp
                                            <?= number_format($item['price'], 0, ',', '.') ?>
                                        </div>
                                        <div class="price-total">Rp <?= number_format($item['subtotal'], 0, ',', '.') ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Order Summary -->
                        <div class="order-summary">
                            <h3><i class="fas fa-calculator"></i> Ringkasan Pesanan</h3>
                            <div class="summary-row">
                                <span>Subtotal</span>
                                <span>Rp <?= number_format($order['subtotal'], 0, ',', '.') ?></span>
                            </div>
                            <div class="summary-row">
                                <span>Biaya Pengiriman</span>
                                <span>Rp <?= number_format($order['shipping_cost'], 0, ',', '.') ?></span>
                            </div>
                            <?php if ($order['tax_amount'] > 0): ?>
                                <div class="summary-row">
                                    <span>Pajak</span>
                                    <span>Rp <?= number_format($order['tax_amount'], 0, ',', '.') ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="summary-row total">
                                <span>Total</span>
                                <span>Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></span>
                            </div>
                        </div>

                        <?php
                        // Check if review exists for this order
                        $stmt = $pdo->prepare("
                         SELECT r.*, u.nama as reviewer_name
                         FROM order_reviews r
                         JOIN users u ON r.user_id = u.id
                          WHERE r.order_id = ? AND r.user_id = ?
                        ");
                        $stmt->execute([$order['id'], $user['id']]);
                        $review = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($review):
                            ?>

                            <!-- Review Section -->
                            <div class="review-section">
                                <h3><i class="fas fa-star"></i> Ulasan Anda</h3>
                                <div class="review-card">
                                    <div class="review-header">
                                        <div class="reviewer-info">
                                            <div class="reviewer-avatar">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <div class="reviewer-details">
                                                <div class="reviewer-name"><?= htmlspecialchars($review['reviewer_name']) ?>
                                                </div>
                                                <div class="review-date">
                                                    <i class="fas fa-calendar-alt"></i>
                                                    <?= date('d F Y, H:i', strtotime($review['review_date'])) ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="review-rating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star <?= $i <= $review['rating'] ? 'active' : '' ?>"></i>
                                            <?php endfor; ?>
                                            <span class="rating-text">(<?= $review['rating'] ?>/5)</span>
                                        </div>
                                    </div>
                                    <div class="review-content">
                                        <p><?= nl2br(htmlspecialchars($review['review_text'])) ?></p>
                                    </div>
                                    <?php if (strtotime($review['updated_at']) > strtotime($review['review_date'])): ?>
                                        <div class="review-updated">
                                            <i class="fas fa-edit"></i>
                                            Diperbarui: <?= date('d F Y, H:i', strtotime($review['updated_at'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <style>
                                /* Review Section Styles */
                                .review-section {
                                    background: #fff;
                                    border-radius: 12px;
                                    padding: 25px;
                                    margin: 25px 0;
                                    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                                    border: 1px solid #e9ecef;
                                }

                                .review-section h3 {
                                    margin: 0 0 20px 0;
                                    color: #333;
                                    font-size: 18px;
                                    font-weight: 600;
                                    display: flex;
                                    align-items: center;
                                    gap: 10px;
                                }

                                .review-section h3 i {
                                    color: #ffc107;
                                }

                                .review-card {
                                    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
                                    border-radius: 10px;
                                    padding: 20px;
                                    border-left: 4px solid #ffc107;
                                }

                                .review-header {
                                    display: flex;
                                    justify-content: space-between;
                                    align-items: center;
                                    margin-bottom: 15px;
                                    flex-wrap: wrap;
                                    gap: 15px;
                                }

                                .reviewer-info {
                                    display: flex;
                                    align-items: center;
                                    gap: 12px;
                                }

                                .reviewer-avatar {
                                    width: 45px;
                                    height: 45px;
                                    background: linear-gradient(135deg, #667eea, #764ba2);
                                    border-radius: 50%;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    color: white;
                                    font-size: 18px;
                                }

                                .reviewer-details {
                                    display: flex;
                                    flex-direction: column;
                                }

                                .reviewer-name {
                                    font-weight: 600;
                                    color: #333;
                                    font-size: 16px;
                                }

                                .review-date {
                                    font-size: 12px;
                                    color: #6c757d;
                                    display: flex;
                                    align-items: center;
                                    gap: 5px;
                                    margin-top: 2px;
                                }

                                .review-rating {
                                    display: flex;
                                    align-items: center;
                                    gap: 8px;
                                }

                                .review-rating .fas.fa-star {
                                    color: #ddd;
                                    font-size: 16px;
                                    transition: color 0.3s ease;
                                }

                                .review-rating .fas.fa-star.active {
                                    color: #ffc107;
                                    filter: drop-shadow(0 1px 2px rgba(255, 193, 7, 0.5));
                                }

                                .rating-text {
                                    font-size: 14px;
                                    font-weight: 600;
                                    color: #333;
                                    margin-left: 5px;
                                }

                                .review-content {
                                    margin: 15px 0;
                                }

                                .review-content p {
                                    margin: 0;
                                    line-height: 1.6;
                                    color: #333;
                                    font-size: 14px;
                                    background: white;
                                    padding: 15px;
                                    border-radius: 8px;
                                    border: 1px solid #e9ecef;
                                    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
                                }

                                .review-updated {
                                    font-size: 12px;
                                    color: #6c757d;
                                    text-align: right;
                                    margin-top: 10px;
                                    padding-top: 10px;
                                    border-top: 1px solid #dee2e6;
                                    display: flex;
                                    align-items: center;
                                    justify-content: flex-end;
                                    gap: 5px;
                                }

                                /* Responsive */
                                @media (max-width: 768px) {
                                    .review-header {
                                        flex-direction: column;
                                        align-items: flex-start;
                                    }

                                    .review-rating {
                                        align-self: flex-end;
                                    }

                                    .review-section {
                                        padding: 20px 15px;
                                    }

                                    .review-card {
                                        padding: 15px;
                                    }
                                }
                            </style>

                        <?php endif; ?>

                        <!-- Notes -->
                        <?php if ($order['notes']): ?>
                            <div class="notes-section">
                                <h4><i class="fas fa-sticky-note"></i> Catatan</h4>
                                <p><?= nl2br(htmlspecialchars($order['notes'])) ?></p>
                            </div>
                        <?php endif; ?>

                        <!-- Action Buttons -->
                        <div class="order-actions">
                            <a href="my_orders.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Kembali ke Daftar Pesanan
                            </a>

                            <?php if ($order['payment_method'] === 'transfer_bank' && empty($order['payment_proof']) && in_array($order['status'], ['pending', 'paid'])): ?>
                                <a href="upload_payment_proof.php?order_id=<?= $order['id'] ?>" class="btn btn-primary">
                                    <i class="fas fa-upload"></i> Upload Bukti Pembayaran
                                </a>
                            <?php endif; ?>

                            <?php if ($order['status'] === 'shipped'): ?>
                                <button type="button" class="btn btn-success"
                                    onclick="confirmCompleteOrder(<?= $order['id'] ?>, '<?= htmlspecialchars($order['order_number']) ?>')">
                                    <i class="fas fa-check-circle"></i> Selesai Pesanan
                                </button>
                            <?php endif; ?>

                            <?php if ($order['status'] === 'delivered'): ?>
                                <?php
                                // Check if review already exists
                                $stmt = $pdo->prepare("SELECT id FROM order_reviews WHERE order_id = ? AND user_id = ?");
                                $stmt->execute([$order['id'], $user['id']]);
                                $existing_review = $stmt->fetch();
                                ?>

                                <?php if (!$existing_review): ?>
                                    <button type="button" class="btn btn-primary"
                                        onclick="openReviewModal(<?= $order['id'] ?>, '<?= htmlspecialchars($order['order_number']) ?>')">
                                        <i class="fas fa-star"></i> Beri Ulasan
                                    </button>
                                <?php else: ?>
                                    <span class="btn btn-success disabled" style="cursor: default;">
                                        <i class="fas fa-check-circle"></i> Sudah Diulas
                                    </span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Complete Order Confirmation Modal -->
    <div id="completeOrderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-check-circle"></i> Konfirmasi Selesai Pesanan</h3>
                <button type="button" class="modal-close" onclick="closeCompleteOrderModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div style="text-align: center; padding: 20px;">
                    <div style="font-size: 64px; color: #28a745; margin-bottom: 20px;">
                        <i class="fas fa-box-open"></i>
                    </div>
                    <h4 style="margin-bottom: 15px; color: #333;">Apakah Anda sudah menerima pesanan ini?</h4>
                    <p style="color: #666; margin-bottom: 20px;">
                        Pesanan <strong id="confirmOrderNumber"></strong> akan ditandai sebagai
                        <strong>selesai</strong>.
                        <br>Pastikan Anda sudah menerima barang dengan kondisi baik.
                    </p>

                    <div
                        style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0; text-align: left;">
                        <h5 style="margin: 0 0 10px 0; color: #495057;">
                            <i class="fas fa-info-circle"></i> Catatan Penting:
                        </h5>
                        <ul style="margin: 0; padding-left: 20px; color: #6c757d;">
                            <li>Setelah pesanan diselesaikan, Anda tidak dapat mengubah statusnya lagi</li>
                            <li>Pastikan semua barang sudah diterima dengan lengkap</li>
                            <li>Jika ada masalah dengan pesanan, hubungi customer service sebelum menyelesaikan</li>
                        </ul>
                    </div>
                </div>

                <form id="completeOrderForm" method="POST" action="complete_order.php" style="display: none;">
                    <input type="hidden" name="order_id" id="completeOrderId">
                </form>
            </div>
            <div class="modal-footer" style="display: flex; gap: 10px; justify-content: center; padding: 20px;">
                <button type="button" class="btn btn-secondary" onclick="closeCompleteOrderModal()">
                    <i class="fas fa-times"></i> Batal
                </button>
                <button type="button" class="btn btn-success" onclick="submitCompleteOrder()">
                    <i class="fas fa-check-circle"></i> Ya, Selesaikan Pesanan
                </button>
            </div>
        </div>
    </div>

    <style>
        /* Complete Order Modal Styles */
        .modal-footer {
            border-top: 1px solid #e9ecef;
            background: #f8f9fa;
            border-radius: 0 0 12px 12px;
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            color: white;
            padding: 12px 24px;
            border-radius: 25px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #218838, #1ea080);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
            text-decoration: none;
            color: white;
        }

        .btn-success:active {
            transform: translateY(0);
        }

        /* Success/Error Message Styles */
        .alert {
            padding: 15px 20px;
            margin: 20px 0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert i {
            font-size: 18px;
        }

        /* Animation untuk tombol loading */
        .btn-loading {
            position: relative;
            pointer-events: none;
        }

        .btn-loading::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            margin: auto;
            border: 2px solid transparent;
            border-top-color: #ffffff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }
    </style>

    <!-- Review Modal -->
    <div id="reviewModal" class="modal">
        <div class="modal-content review-modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-star"></i> Beri Ulasan</h3>
                <button type="button" class="modal-close" onclick="closeReviewModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="reviewForm">
                    <input type="hidden" id="reviewOrderId" name="order_id">

                    <div class="review-order-info">
                        <div class="order-info-card">
                            <h4><i class="fas fa-shopping-bag"></i> Pesanan <span id="reviewOrderNumber"></span></h4>
                            <p>Bagaimana pengalaman Anda dengan pesanan ini?</p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="rating" class="form-label">
                            <i class="fas fa-star"></i> Rating
                            <span class="required">*</span>
                        </label>
                        <div class="rating-container">
                            <div class="star-rating">
                                <input type="radio" name="rating" value="5" id="star5" required>
                                <label for="star5" class="star" title="Sangat Baik"><i class="fas fa-star"></i></label>

                                <input type="radio" name="rating" value="4" id="star4" required>
                                <label for="star4" class="star" title="Baik"><i class="fas fa-star"></i></label>

                                <input type="radio" name="rating" value="3" id="star3" required>
                                <label for="star3" class="star" title="Cukup"><i class="fas fa-star"></i></label>

                                <input type="radio" name="rating" value="2" id="star2" required>
                                <label for="star2" class="star" title="Kurang"><i class="fas fa-star"></i></label>

                                <input type="radio" name="rating" value="1" id="star1" required>
                                <label for="star1" class="star" title="Buruk"><i class="fas fa-star"></i></label>
                            </div>
                            <div class="rating-text" id="ratingText">Pilih rating</div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="reviewText" class="form-label">
                            <i class="fas fa-comment"></i> Ulasan
                            <span class="required">*</span>
                        </label>
                        <textarea id="reviewText" name="review_text" class="form-control" rows="5"
                            placeholder="Ceritakan pengalaman Anda dengan produk dan layanan kami... (minimal 10 karakter)"
                            required minlength="10" maxlength="1000"></textarea>
                        <div class="char-counter">
                            <span id="charCount">0</span>/1000 karakter
                        </div>
                    </div>

                    <div class="review-tips">
                        <h5><i class="fas fa-lightbulb"></i> Tips menulis ulasan yang baik:</h5>
                        <ul>
                            <li>Ceritakan kualitas produk yang Anda terima</li>
                            <li>Bagikan pengalaman tentang kecepatan pengiriman</li>
                            <li>Berikan feedback tentang pelayanan customer service</li>
                            <li>Jelaskan apakah produk sesuai dengan ekspektasi</li>
                        </ul>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeReviewModal()">
                    <i class="fas fa-times"></i> Batal
                </button>
                <button type="button" class="btn btn-primary" onclick="submitReview()" id="submitReviewBtn">
                    <i class="fas fa-star"></i> Kirim Ulasan
                </button>
            </div>
        </div>
    </div>

    <style>
        /* Review Modal Styles */
        .review-modal-content {
            max-width: 600px;
            width: 95%;
        }

        .review-order-info {
            margin-bottom: 25px;
        }

        .order-info-card {
            background: #667eea;
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }

        .order-info-card h4 {
            color: white;
            margin: 0 0 8px 0;
            font-size: 18px;
        }

        .order-info-card p {
            margin: 0;
            opacity: 0.9;
            font-size: 14px;
        }

        /* Star Rating Styles */
        .rating-container {
            text-align: center;
            margin: 20px 0;
        }

        .star-rating {
            display: inline-flex;
            flex-direction: row-reverse;
            gap: 5px;
            margin-bottom: 10px;
        }

        .star-rating input[type="radio"] {
            display: none;
        }

        .star-rating .star {
            cursor: pointer;
            font-size: 32px;
            color: #ddd;
            transition: all 0.3s ease;
            display: inline-block;
            transform: scale(1);
        }

        .star-rating .star:hover,
        .star-rating .star:hover~.star,
        .star-rating input[type="radio"]:checked~.star {
            color: #ffc107;
            transform: scale(1.1);
            filter: drop-shadow(0 2px 4px rgba(255, 193, 7, 0.5));
        }

        .star-rating .star:hover {
            transform: scale(1.2);
        }

        .rating-text {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-top: 10px;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .required {
            color: #dc3545;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            resize: vertical;
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .char-counter {
            text-align: right;
            font-size: 12px;
            color: #6c757d;
            margin-top: 5px;
        }

        .char-counter.warning {
            color: #ffc107;
        }

        .char-counter.danger {
            color: #dc3545;
        }

        /* Review Tips */
        .review-tips {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .review-tips h5 {
            margin: 0 0 12px 0;
            color: #495057;
            font-size: 14px;
        }

        .review-tips ul {
            margin: 0;
            padding-left: 20px;
        }

        .review-tips li {
            margin-bottom: 5px;
            font-size: 13px;
            color: #6c757d;
            line-height: 1.4;
        }

        /* Modal Footer */
        .modal-footer {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            padding: 20px 25px;
            border-top: 1px solid #e9ecef;
            background: #f8f9fa;
            border-radius: 0 0 12px 12px;
        }

        /* Button Loading State */
        .btn-loading {
            position: relative;
            pointer-events: none;
        }

        .btn-loading::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            margin: auto;
            border: 2px solid transparent;
            border-top-color: #ffffff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        /* Success Animation */
        .review-success {
            text-align: center;
            padding: 40px 20px;
        }

        .success-icon {
            font-size: 64px;
            color: #28a745;
            margin-bottom: 20px;
            animation: successBounce 0.8s ease;
        }

        @keyframes successBounce {
            0% {
                transform: scale(0);
                opacity: 0;
            }

            50% {
                transform: scale(1.2);
                opacity: 1;
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .review-modal-content {
                width: 95%;
                margin: 10px;
            }

            .star-rating .star {
                font-size: 28px;
            }

            .modal-footer {
                flex-direction: column;
                gap: 8px;
            }

            .modal-footer .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>

    <!-- Payment Proof Modal -->
    <div id="paymentProofModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-receipt"></i> Bukti Pembayaran</h3>
                <button type="button" class="modal-close" onclick="closePaymentProofModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="orderInfoSummary" class="order-info-header"></div>
                <div id="proofImageContainer">
                    <img id="proofImage" src="" alt="Bukti Pembayaran" class="modal-image" style="display: none;">
                    <iframe id="proofPdf" src="" class="modal-pdf" style="display: none;"></iframe>
                    <div id="proofError" style="display: none; text-align: center; color: #dc3545; padding: 40px;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 48px; margin-bottom: 15px;"></i>
                        <p>File bukti pembayaran tidak dapat ditampilkan atau tidak ditemukan.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modern Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-column">
                <h3>NADA BookStore</h3>
                <p>Toko buku terlengkap dengan koleksi terbaik untuk memenuhi kebutuhan literasi Anda.</p>
                <div class="social-links">
                    <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-youtube"></i></a>
                </div>
            </div>

            <div class="footer-column">
                <h3>Kontak Kami</h3>
                <ul>
                    <li><i class="fas fa-map-marker-alt"></i> Jl. Raya Tengah, Kelurahan Gedong, Pasar Rebo, Jakarta
                        Timur 13760
                    </li>
                    <li><i class="fas fa-phone"></i> (021) 8779-7409</li>
                    <li><i class="fab fa-whatsapp" aria-hidden="true"></i> 0819-3298-1929</li>
                    <li><i class="fa fa-envelope"></i> senna.linda@nadabookstore.com</li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> NADA BookStore. All Rights Reserved.</p>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Mobile Menu Toggle
            const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
            const navLinks = document.querySelector('.nav-links');
            const body = document.body;

            if (mobileMenuBtn) {
                mobileMenuBtn.addEventListener('click', () => {
                    navLinks.style.display = navLinks.style.display === 'flex' ? 'none' : 'flex';
                    mobileMenuBtn.classList.toggle('active');
                });
            }

            // Quantity Buttons
            const minusBtns = document.querySelectorAll('.minus-btn');
            const plusBtns = document.querySelectorAll('.plus-btn');

            minusBtns.forEach(btn => {
                btn.addEventListener('click', function () {
                    const productId = this.getAttribute('data-id');
                    const inputField = document.getElementById('qty-' + productId);
                    let value = parseInt(inputField.value, 10);
                    value = value > 1 ? value - 1 : 1;
                    inputField.value = value;
                });
            });

            plusBtns.forEach(btn => {
                btn.addEventListener('click', function () {
                    const productId = this.getAttribute('data-id');
                    const inputField = document.getElementById('qty-' + productId);
                    let value = parseInt(inputField.value, 10);
                    value++;
                    inputField.value = value;
                });
            });

            // User Dropdown Menu
            const userEmailBtn = document.querySelector('.user-email-btn');
            const userDropdown = document.querySelector('.user-dropdown');

            if (userEmailBtn && userDropdown) {
                userEmailBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    userDropdown.classList.toggle('show');
                });

                document.addEventListener('click', function (e) {
                    if (!userEmailBtn.contains(e.target) && !userDropdown.contains(e.target)) {
                        userDropdown.classList.remove('show');
                    }
                });
            }
        });

        // Payment Proof Modal Functions
        function viewPaymentProof(filename, orderNumber, totalAmount) {
            const modal = document.getElementById('paymentProofModal');
            const orderInfo = document.getElementById('orderInfoSummary');
            const proofImage = document.getElementById('proofImage');
            const proofPdf = document.getElementById('proofPdf');
            const proofError = document.getElementById('proofError');

            // Set order info
            orderInfo.innerHTML = `
                <strong>Pesanan:</strong> ${orderNumber}<br>
                <strong>Total Pembayaran:</strong> Rp. ${totalAmount}
            `;

            // Hide all elements first
            proofImage.style.display = 'none';
            proofPdf.style.display = 'none';
            proofError.style.display = 'none';

            // Construct file path
            const filePath = `uploads/payment_proofs/${filename}`;

            // Check file extension
            const fileExtension = filename.split('.').pop().toLowerCase();

            if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) {
                // Handle image files
                proofImage.onload = function () {
                    proofImage.style.display = 'block';
                };

                proofImage.onerror = function () {
                    proofError.style.display = 'block';
                };

                proofImage.src = filePath;
            } else if (fileExtension === 'pdf') {
                // Handle PDF files
                proofPdf.onload = function () {
                    proofPdf.style.display = 'block';
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
                showCopyNotification('Nomor rekening berhasil disalin!');
            }).catch(function () {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                showCopyNotification('Nomor rekening berhasil disalin!');
            });
        }

        // Show copy notification
        function showCopyNotification(message) {
            // Remove existing notification if any
            const existingNotification = document.querySelector('.copy-notification');
            if (existingNotification) {
                existingNotification.remove();
            }

            // Create new notification
            const notification = document.createElement('div');
            notification.className = 'copy-notification';
            notification.innerHTML = '<i class="fas fa-check"></i> ' + message;
            document.body.appendChild(notification);

            // Show notification
            setTimeout(() => notification.classList.add('show'), 100);

            // Hide and remove notification
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Close modal when clicking outside
        document.addEventListener('click', function (e) {
            const modal = document.getElementById('paymentProofModal');
            if (e.target === modal) {
                closePaymentProofModal();
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function (e) {
            // ESC to close modal
            if (e.key === 'Escape') {
                closePaymentProofModal();
            }
        });

        // Auto-refresh for payment proof updates
        let autoRefreshInterval = setInterval(() => {
            // Only refresh if no modal is open
            if (!document.querySelector('.modal.show')) {
                // Check if payment proof status has changed
                fetch(window.location.href)
                    .then(response => response.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const newDoc = parser.parseFromString(html, 'text/html');
                        const currentProofStatus = document.querySelector('.proof-status-indicator');
                        const newProofStatus = newDoc.querySelector('.proof-status-indicator');

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
                                const currentProofStatus = document.querySelector('.proof-status-indicator');
                                const newProofStatus = newDoc.querySelector('.proof-status-indicator');

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

        // Complete Order Functions
        function confirmCompleteOrder(orderId, orderNumber) {
            const modal = document.getElementById('completeOrderModal');
            const orderNumberElement = document.getElementById('confirmOrderNumber');
            const orderIdInput = document.getElementById('completeOrderId');

            orderNumberElement.textContent = orderNumber;
            orderIdInput.value = orderId;

            modal.classList.add('show');
        }

        function closeCompleteOrderModal() {
            const modal = document.getElementById('completeOrderModal');
            modal.classList.remove('show');
        }

        function submitCompleteOrder() {
            const form = document.getElementById('completeOrderForm');
            const submitBtn = event.target;

            // Add loading state
            submitBtn.classList.add('btn-loading');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner"></i> Memproses...';

            // Submit form
            form.submit();
        }

        // Close modal when clicking outside
        document.addEventListener('click', function (e) {
            const modal = document.getElementById('completeOrderModal');
            if (e.target === modal) {
                closeCompleteOrderModal();
            }
        });

        // Review Modal Functions
        function openReviewModal(orderId, orderNumber) {
            const modal = document.getElementById('reviewModal');
            const orderIdInput = document.getElementById('reviewOrderId');
            const orderNumberSpan = document.getElementById('reviewOrderNumber');

            orderIdInput.value = orderId;
            orderNumberSpan.textContent = orderNumber;

            // Reset form
            document.getElementById('reviewForm').reset();
            document.getElementById('ratingText').textContent = 'Pilih rating';
            document.getElementById('charCount').textContent = '0';

            modal.classList.add('show');
        }

        function closeReviewModal() {
            const modal = document.getElementById('reviewModal');
            modal.classList.remove('show');
        }

        // Star Rating Handler
        document.addEventListener('DOMContentLoaded', function () {
            const starInputs = document.querySelectorAll('input[name="rating"]');
            const ratingText = document.getElementById('ratingText');

            const ratingLabels = {
                1: 'Buruk ⭐',
                2: 'Kurang ⭐⭐',
                3: 'Cukup ⭐⭐⭐',
                4: 'Baik ⭐⭐⭐⭐',
                5: 'Sangat Baik ⭐⭐⭐⭐⭐'
            };

            starInputs.forEach(input => {
                input.addEventListener('change', function () {
                    if (ratingText) {
                        ratingText.textContent = ratingLabels[this.value] || 'Pilih rating';
                    }
                });
            });

            // Character counter
            const reviewTextarea = document.getElementById('reviewText');
            const charCount = document.getElementById('charCount');

            if (reviewTextarea && charCount) {
                reviewTextarea.addEventListener('input', function () {
                    const currentLength = this.value.length;
                    charCount.textContent = currentLength;

                    // Update counter color based on length
                    const counter = charCount.parentElement;
                    counter.classList.remove('warning', 'danger');

                    if (currentLength > 900) {
                        counter.classList.add('danger');
                    } else if (currentLength > 800) {
                        counter.classList.add('warning');
                    }
                });
            }
        });

        // Submit Review Function
        async function submitReview() {
            const form = document.getElementById('reviewForm');
            const submitBtn = document.getElementById('submitReviewBtn');

            // Validate form
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            // Get form data
            const formData = new FormData(form);

            // Validate rating
            const rating = formData.get('rating');
            if (!rating) {
                showNotification('Silakan pilih rating terlebih dahulu.', 'error');
                return;
            }

            // Validate review text
            const reviewText = formData.get('review_text').trim();
            if (reviewText.length < 10) {
                showNotification('Ulasan minimal 10 karakter.', 'error');
                return;
            }

            // Set loading state
            submitBtn.classList.add('btn-loading');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner"></i> Mengirim...';

            try {
                const response = await fetch('submit_review.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    // Show success message
                    showSuccessReview(result.message, result.data);
                } else {
                    throw new Error(result.message || 'Terjadi kesalahan saat mengirim ulasan.');
                }

            } catch (error) {
                console.error('Review submission error:', error);
                showNotification(error.message || 'Terjadi kesalahan jaringan.', 'error');

                // Reset button state
                submitBtn.classList.remove('btn-loading');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-star"></i> Kirim Ulasan';
            }
        }

        // Show Success Review
        function showSuccessReview(message, data) {
            const modalBody = document.querySelector('#reviewModal .modal-body');
            const modalFooter = document.querySelector('#reviewModal .modal-footer');

            modalBody.innerHTML = `
        <div class="review-success">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h4 style="color: #28a745; margin-bottom: 15px;">Ulasan Berhasil Dikirim!</h4>
            <p style="margin-bottom: 20px;">${message}</p>
            
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: left; max-width: 400px; margin: 0 auto;">
                <h5 style="margin: 0 0 15px 0; color: #333;">Ringkasan Ulasan:</h5>
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <span>Pesanan:</span>
                    <strong>${data.order_number}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <span>Rating:</span>
                    <div style="color: #ffc107;">
                        ${'⭐'.repeat(data.rating)}
                    </div>
                </div>
                <div style="margin-bottom: 10px;">
                    <span>Ulasan:</span>
                    <div style="background: white; padding: 10px; border-radius: 4px; margin-top: 5px; font-style: italic;">
                        "${data.review_text}"
                    </div>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 12px; color: #6c757d;">
                    <span>Waktu:</span>
                    <span>${new Date(data.review_date).toLocaleString('id-ID')}</span>
                </div>
            </div>
        </div>
    `;

            modalFooter.innerHTML = `
        <button type="button" class="btn btn-primary" onclick="closeReviewModalAndRefresh()">
            <i class="fas fa-check"></i> Tutup
        </button>
    `;
        }

        // Close modal and refresh page
        function closeReviewModalAndRefresh() {
            closeReviewModal();

            // Show success notification on page
            setTimeout(() => {
                showNotification('Ulasan Anda telah tersimpan. Terima kasih!', 'success');

                // Refresh page after a short delay
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            }, 300);
        }

        // Show notification function
        function showNotification(message, type = 'info') {
            // Remove existing notifications
            const existingNotifications = document.querySelectorAll('.review-notification');
            existingNotifications.forEach(notification => notification.remove());

            // Create new notification
            const notification = document.createElement('div');
            notification.className = `review-notification ${type}`;
            notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'}"></i>
        ${message}
    `;

            // Add styles
            notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 8px;
        color: white;
        font-weight: 500;
        z-index: 1002;
        max-width: 400px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        transform: translateX(100%);
        transition: transform 0.3s ease;
        display: flex;
        align-items: center;
        gap: 10px;
    `;

            // Set background color based on type
            if (type === 'success') {
                notification.style.background = 'linear-gradient(135deg, #28a745, #20c997)';
            } else if (type === 'error') {
                notification.style.background = 'linear-gradient(135deg, #dc3545, #c82333)';
            } else {
                notification.style.background = 'linear-gradient(135deg, #17a2b8, #138496)';
            }

            document.body.appendChild(notification);

            // Show notification
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 100);

            // Hide notification after 4 seconds
            setTimeout(() => {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 4000);
        }

        // Close modal when clicking outside
        document.addEventListener('click', function (e) {
            const modal = document.getElementById('reviewModal');
            if (e.target === modal) {
                closeReviewModal();
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function (e) {
            const modal = document.getElementById('reviewModal');
            if (modal && modal.classList.contains('show')) {
                // ESC to close modal
                if (e.key === 'Escape') {
                    closeReviewModal();
                }
                // Ctrl+Enter to submit (if form is valid)
                if (e.ctrlKey && e.key === 'Enter') {
                    e.preventDefault();
                    submitReview();
                }
            }
        });

        // Keyboard shortcuts for complete order modal
        document.addEventListener('keydown', function (e) {
            const modal = document.getElementById('completeOrderModal');
            if (modal.classList.contains('show')) {
                // ESC to close modal
                if (e.key === 'Escape') {
                    closeCompleteOrderModal();
                }
                // Enter to confirm (if not focused on cancel button)
                if (e.key === 'Enter' && !e.target.classList.contains('btn-secondary')) {
                    e.preventDefault();
                    submitCompleteOrder();
                }
            }
        });

        // Auto-hide success/error messages
        document.addEventListener('DOMContentLoaded', function () {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(() => {
                        alert.remove();
                    }, 300);
                }, 5000); // Hide after 5 seconds
            });
        });

        // Enhanced interaction effects
        document.addEventListener('DOMContentLoaded', function () {
            // Add hover effect to account numbers
            const accountNumbers = document.querySelectorAll('.account-number');
            accountNumbers.forEach(account => {
                account.addEventListener('mouseenter', function () {
                    this.style.transform = 'scale(1.02)';
                });

                account.addEventListener('mouseleave', function () {
                    this.style.transform = 'scale(1)';
                });
            });

            // Add click effect to proof image
            const proofImage = document.querySelector('.proof-image-preview');
            if (proofImage) {
                proofImage.addEventListener('click', function () {
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        this.style.transform = 'scale(1.05)';
                    }, 100);
                });
            }
        });
    </script>
</body>

</html>