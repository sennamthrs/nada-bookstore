<?php
// upload_payment_proof.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'auth.php';
require_once 'db.php';

// Pastikan user sudah login
if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

$user = getLoggedUser();
$order_id = $_GET['order_id'] ?? null;

if (!$order_id) {
    header("Location: my_orders.php");
    exit;
}

// Ambil data order dan pastikan milik user yang login
$stmt = $pdo->prepare("
    SELECT o.*, so.name as shipping_name 
    FROM orders o 
    LEFT JOIN shipping_options so ON o.shipping_option_id = so.id 
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$order_id, $user['id']]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: my_orders.php");
    exit;
}

// Handle upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['payment_proof'])) {
    try {
        $upload_dir = 'uploads/payment_proofs/';

        // Buat direktori jika belum ada
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file = $_FILES['payment_proof'];
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
        $max_size = 5 * 1024 * 1024; // 5MB

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Error saat upload file');
        }

        if (!in_array($file['type'], $allowed_types)) {
            throw new Exception('Format file tidak didukung. Gunakan JPG, PNG, GIF, atau PDF');
        }

        if ($file['size'] > $max_size) {
            throw new Exception('Ukuran file terlalu besar. Maksimal 5MB');
        }

        // Hapus file lama jika ada
        if (!empty($order['payment_proof']) && file_exists($upload_dir . $order['payment_proof'])) {
            unlink($upload_dir . $order['payment_proof']);
        }

        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new_filename = 'payment_proof_' . $order_id . '_' . time() . '.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;

        if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
            throw new Exception('Gagal menyimpan file');
        }

        // Update database
        $stmt = $pdo->prepare("UPDATE orders SET payment_proof = ?, payment_proof_uploaded_at = NOW() WHERE id = ?");
        $stmt->execute([$new_filename, $order_id]);

        $success_message = "Bukti pembayaran berhasil diupload!";

        // Refresh order data
        $stmt = $pdo->prepare("
            SELECT o.*, so.name as shipping_name 
            FROM orders o 
            LEFT JOIN shipping_options so ON o.shipping_option_id = so.id 
            WHERE o.id = ? AND o.user_id = ?
        ");
        $stmt->execute([$order_id, $user['id']]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Bukti Pembayaran - NADA BookStore</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="checkout-payment.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<style>
    /* checkout-payment.css - Tambahan CSS untuk fitur pembayaran */

    /* Payment Options Enhancement */
    .payment-options {
        display: grid;
        gap: 15px;
        margin-bottom: 20px;
    }

    .payment-option {
        border: 2px solid #e9ecef;
        border-radius: 8px;
        transition: all 0.3s ease;
        overflow: hidden;
    }

    .payment-option:hover {
        border-color: #007bff;
        box-shadow: 0 2px 8px rgba(0, 123, 255, 0.1);
    }

    .payment-label {
        display: block;
        padding: 15px 20px;
        cursor: pointer;
        margin: 0;
        position: relative;
    }

    .payment-label input[type="radio"] {
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
    }

    .payment-info {
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 600;
        color: #495057;
    }

    .payment-info i {
        font-size: 18px;
        color: #6c757d;
        transition: color 0.3s ease;
    }

    /* Selected payment option */
    .payment-option:has(input:checked) {
        border-color: #007bff;
        background: linear-gradient(135deg, #f8f9ff 0%, #e3f2fd 100%);
    }

    .payment-option:has(input:checked) .payment-info {
        color: #007bff;
    }

    .payment-option:has(input:checked) .payment-info i {
        color: #007bff;
    }

    /* Bank Transfer Section */
    .bank-transfer-info {
        margin-top: 20px;
        padding: 25px;
        background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        border-radius: 12px;
        border: 1px solid #e9ecef;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        display: none;
        animation: fadeInUp 0.3s ease;
    }

    .bank-transfer-info.show {
        display: block;
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .bank-accounts h4 {
        margin-bottom: 20px;
        color: #2c3e50;
        font-size: 18px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .bank-accounts h4 i {
        color: #007bff;
    }

    /* Bank Account Cards */
    .bank-account-card {
        background: white;
        border: 2px solid #e9ecef;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 15px;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .bank-account-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: linear-gradient(135deg, #007bff, #0056b3);
        transition: width 0.3s ease;
    }

    .bank-account-card:hover {
        border-color: #007bff;
        box-shadow: 0 4px 15px rgba(0, 123, 255, 0.15);
        transform: translateY(-2px);
    }

    .bank-account-card:hover::before {
        width: 8px;
    }

    .bank-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 15px;
        color: #007bff;
        font-weight: 700;
        font-size: 16px;
    }

    .bank-header i {
        font-size: 20px;
    }

    .bank-details .account-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
        padding: 8px 0;
    }

    .account-info .label {
        color: #6c757d;
        font-size: 14px;
        font-weight: 500;
    }

    .account-number {
        font-family: 'Courier New', 'Consolas', monospace;
        font-weight: bold;
        color: #2c3e50;
        cursor: pointer;
        padding: 8px 12px;
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        border-radius: 6px;
        transition: all 0.3s ease;
        border: 1px solid transparent;
        position: relative;
    }

    .account-number:hover {
        background: linear-gradient(135deg, #e3f2fd, #bbdefb);
        border-color: #007bff;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(0, 123, 255, 0.2);
    }

    .copy-icon {
        margin-left: 8px;
        color: #007bff;
        opacity: 0.7;
        transition: all 0.3s ease;
    }

    .account-number:hover .copy-icon {
        opacity: 1;
        transform: scale(1.1);
    }

    .account-name {
        font-weight: 600;
        color: #2c3e50;
        font-size: 15px;
    }

    /* Payment Proof Upload Section */
    .payment-proof-section {
        margin-top: 30px;
        padding-top: 25px;
        border-top: 2px solid #e9ecef;
    }

    .payment-proof-section h4 {
        margin-bottom: 20px;
        color: #2c3e50;
        font-size: 18px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .payment-proof-section h4 i {
        color: #28a745;
    }

    /* File Upload Styling */
    .file-upload-wrapper {
        position: relative;
        border: 3px dashed #dee2e6;
        border-radius: 12px;
        padding: 30px 20px;
        text-align: center;
        background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        transition: all 0.3s ease;
        cursor: pointer;
        min-height: 120px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
    }

    .file-upload-wrapper:hover {
        border-color: #007bff;
        background: linear-gradient(135deg, #f8f9ff 0%, #e3f2fd 100%);
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(0, 123, 255, 0.1);
    }

    .file-upload-wrapper.dragover {
        border-color: #28a745;
        background: linear-gradient(135deg, #f0fff4 0%, #d4edda 100%);
        transform: scale(1.02);
    }

    .file-input {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: pointer;
        z-index: 2;
    }

    .file-upload-display {
        pointer-events: none;
        z-index: 1;
    }

    .file-upload-display i {
        font-size: 32px;
        color: #007bff;
        margin-bottom: 15px;
        transition: all 0.3s ease;
    }

    .file-upload-wrapper:hover .file-upload-display i {
        transform: scale(1.1);
        color: #0056b3;
    }

    .file-upload-text {
        display: block;
        color: #6c757d;
        font-size: 16px;
        font-weight: 500;
        margin-bottom: 8px;
    }

    .file-name-display {
        display: none;
        color: #28a745;
        font-weight: 700;
        font-size: 16px;
        margin-top: 10px;
        padding: 8px 15px;
        background: rgba(40, 167, 69, 0.1);
        border-radius: 20px;
        border: 1px solid rgba(40, 167, 69, 0.3);
    }

    .file-name-display.show {
        display: block;
        animation: fadeInScale 0.3s ease;
    }

    @keyframes fadeInScale {
        from {
            opacity: 0;
            transform: scale(0.8);
        }

        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    .form-text {
        color: #6c757d;
        font-size: 13px;
        margin-top: 12px;
        display: flex;
        align-items: center;
        gap: 6px;
        justify-content: center;
    }

    .form-text i {
        color: #17a2b8;
    }

    /* Copy Notification */
    .copy-notification {
        position: fixed;
        top: 20px;
        right: 20px;
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        z-index: 1000;
        opacity: 0;
        transform: translateY(-20px);
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .copy-notification.show {
        opacity: 1;
        transform: translateY(0);
    }

    .copy-notification i {
        font-size: 16px;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .bank-transfer-info {
            padding: 20px 15px;
            margin-top: 15px;
        }

        .bank-account-card {
            padding: 15px;
            margin-bottom: 12px;
        }

        .bank-details .account-info {
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
        }

        .account-number {
            font-size: 14px;
            padding: 6px 10px;
            word-break: break-all;
        }

        .file-upload-wrapper {
            padding: 25px 15px;
            min-height: 100px;
        }

        .file-upload-display i {
            font-size: 28px;
        }

        .file-upload-text {
            font-size: 14px;
        }

        .copy-notification {
            right: 10px;
            left: 10px;
            text-align: center;
        }
    }

    @media (max-width: 480px) {
        .bank-accounts h4 {
            font-size: 16px;
        }

        .payment-proof-section h4 {
            font-size: 16px;
        }

        .bank-header {
            font-size: 14px;
        }

        .account-info .label {
            font-size: 13px;
        }

        .account-name {
            font-size: 14px;
        }
    }
</style>
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

<body>
    <div class="container">
        <div class="page-header">
            <div class="breadcrumb">
                <a href="index.php">Beranda</a>
                <i class="fas fa-chevron-right"></i>
                <a href="my_orders.php">Pesanan Saya</a>
                <i class="fas fa-chevron-right"></i>
                <span>Upload Bukti Pembayaran</span>
            </div>

            <h1><i class="fas fa-receipt"></i> Upload Bukti Pembayaran</h1>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <div class="upload-container">
            <!-- Order Info -->
            <div class="order-info-card">
                <h3><i class="fas fa-shopping-bag"></i> Informasi Pesanan</h3>

                <div class="order-details">
                    <div class="detail-row">
                        <span class="label">Nomor Pesanan:</span>
                        <span class="value"><?= htmlspecialchars($order['order_number']) ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="label">Total Pembayaran:</span>
                        <span class="value total-amount">Rp.
                            <?= number_format($order['total_amount'], 0, ',', '.') ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="label">Metode Pembayaran:</span>
                        <span class="value">
                            <?php
                            switch ($order['payment_method']) {
                                case 'transfer_bank':
                                    echo '<i class="fas fa-university"></i> Transfer Bank';
                                    break;
                                case 'cod':
                                    echo '<i class="fas fa-money-bill-wave"></i> Bayar di Tempat (COD)';
                                    break;
                                case 'e_wallet':
                                    echo '<i class="fas fa-mobile-alt"></i> E-Wallet';
                                    break;
                                default:
                                    echo htmlspecialchars($order['payment_method']);
                            }
                            ?>
                        </span>
                    </div>

                    <div class="detail-row">
                        <span class="label">Status:</span>
                        <span class="value">
                            <span class="status-badge <?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span>
                        </span>
                    </div>

                    <div class="detail-row">
                        <span class="label">Tanggal Pesanan:</span>
                        <span class="value"><?= date('d F Y, H:i', strtotime($order['created_at'])) ?></span>
                    </div>
                </div>
            </div>

            <?php if ($order['payment_method'] === 'transfer_bank'): ?>
                <!-- Bank Transfer Info -->
                <div class="bank-transfer-section">
                    <h3><i class="fas fa-university"></i> Informasi Transfer Bank</h3>

                    <div class="bank-accounts">
                        <?php foreach ($bank_accounts as $bank): ?>
                            <div class="bank-account-card">
                                <div class="bank-header">
                                    <i class="fas fa-building"></i>
                                    <strong><?= $bank['bank'] ?></strong>
                                </div>
                                <div class="bank-details">
                                    <div class="account-info">
                                        <span class="label">No. Rekening:</span>
                                        <span class="account-number"
                                            onclick="copyToClipboard('<?= $bank['account_number'] ?>')">
                                            <?= $bank['account_number'] ?>
                                            <i class="fas fa-copy copy-icon"></i>
                                        </span>
                                    </div>
                                    <div class="account-info">
                                        <span class="label">Atas Nama:</span>
                                        <span class="account-name"><?= $bank['account_name'] ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Current Payment Proof Status -->
                <div class="current-proof-section">
                    <h3><i class="fas fa-image"></i> Status Bukti Pembayaran</h3>

                    <?php if (!empty($order['payment_proof'])): ?>
                        <div class="current-proof">
                            <div class="proof-info">
                                <i class="fas fa-check-circle text-success"></i>
                                <span>Bukti pembayaran sudah diupload</span>
                                <small>Upload: <?= date('d F Y, H:i', strtotime($order['payment_proof_uploaded_at'])) ?></small>
                            </div>

                            <div class="proof-preview">
                                <?php
                                $file_extension = pathinfo($order['payment_proof'], PATHINFO_EXTENSION);
                                $proof_url = 'uploads/payment_proofs/' . $order['payment_proof'];
                                ?>

                                <?php if (in_array(strtolower($file_extension), ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                    <img src="<?= $proof_url ?>" alt="Bukti Pembayaran" class="proof-image"
                                        onclick="openImageModal('<?= $proof_url ?>')">
                                <?php else: ?>
                                    <div class="pdf-preview">
                                        <i class="fas fa-file-pdf"></i>
                                        <span>File PDF</span>
                                        <a href="<?= $proof_url ?>" target="_blank" class="btn btn-sm btn-outline">
                                            <i class="fas fa-external-link-alt"></i> Lihat PDF
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <p class="upload-note">
                                <i class="fas fa-info-circle"></i>
                                Anda dapat mengganti bukti pembayaran dengan mengupload file baru di bawah ini.
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="no-proof">
                            <i class="fas fa-exclamation-triangle text-warning"></i>
                            <span>Belum ada bukti pembayaran yang diupload</span>
                            <p>Silakan upload bukti transfer Anda untuk mempercepat proses verifikasi.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Upload Form -->
                <div class="upload-form-section">
                    <h3><i class="fas fa-cloud-upload-alt"></i> Upload Bukti Pembayaran</h3>

                    <form method="POST" enctype="multipart/form-data" class="upload-form">
                        <div class="form-group">
                            <label for="payment_proof">Pilih File Bukti Transfer</label>
                            <div class="file-upload-wrapper">
                                <input type="file" id="payment_proof" name="payment_proof" class="file-input"
                                    accept="image/*,.pdf" required>
                                <div class="file-upload-display">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span class="file-upload-text">Pilih file atau drag & drop di sini</span>
                                    <span class="file-name-display"></span>
                                </div>
                            </div>
                            <small class="form-text">
                                <i class="fas fa-info-circle"></i>
                                Format yang didukung: JPG, PNG, GIF, PDF (Maksimal 5MB)
                            </small>
                        </div>

                        <div class="upload-instructions">
                            <h4><i class="fas fa-lightbulb"></i> Tips Upload Bukti Pembayaran:</h4>
                            <ul>
                                <li>Pastikan foto/scan jelas dan dapat dibaca</li>
                                <li>Sertakan informasi transfer yang lengkap (tanggal, waktu, jumlah)</li>
                                <li>Gunakan format JPG, PNG, atau PDF</li>
                                <li>Ukuran file maksimal 5MB</li>
                            </ul>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload"></i> Upload Bukti Pembayaran
                            </button>
                            <a href="my_orders.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Kembali ke Pesanan
                            </a>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <!-- Non-transfer payment methods -->
                <div class="payment-info-section">
                    <div class="info-card">
                        <i class="fas fa-info-circle"></i>
                        <h4>Informasi Pembayaran</h4>
                        <?php if ($order['payment_method'] === 'cod'): ?>
                            <p>Pesanan Anda menggunakan metode <strong>Bayar di Tempat (COD)</strong>.</p>
                            <p>Pembayaran akan dilakukan saat barang diterima.</p>
                        <?php elseif ($order['payment_method'] === 'e_wallet'): ?>
                            <p>Pesanan Anda menggunakan metode <strong>E-Wallet</strong>.</p>
                            <p>Silakan hubungi customer service untuk informasi pembayaran lebih lanjut.</p>
                        <?php endif; ?>

                        <a href="my_orders.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left"></i> Kembali ke Pesanan
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal untuk preview gambar -->
    <div id="imageModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-image"></i> Preview Bukti Pembayaran</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <img id="modalImage" src="" alt="Bukti Pembayaran">
            </div>
        </div>
    </div>

    <style>
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .breadcrumb a {
            color: #007bff;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .breadcrumb i {
            color: #6c757d;
            font-size: 12px;
        }

        .page-header h1 {
            margin: 0;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .upload-container {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .order-info-card,
        .bank-transfer-section,
        .current-proof-section,
        .upload-form-section,
        .payment-info-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border: 1px solid #e9ecef;
        }

        .order-info-card h3,
        .bank-transfer-section h3,
        .current-proof-section h3,
        .upload-form-section h3 {
            margin: 0 0 20px 0;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 700;
        }

        .order-details {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f1f3f4;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-row .label {
            color: #6c757d;
            font-weight: 500;
        }

        .detail-row .value {
            font-weight: 600;
            color: #2c3e50;
        }

        .total-amount {
            color: #007bff !important;
            font-size: 18px;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-badge.paid {
            background: #d4edda;
            color: #155724;
        }

        .current-proof {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .proof-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            background: #d4edda;
            border-radius: 8px;
            border: 1px solid #c3e6cb;
        }

        .proof-info i {
            color: #155724;
        }

        .proof-info small {
            display: block;
            color: #6c757d;
            font-size: 12px;
            margin-top: 2px;
        }

        .proof-preview {
            text-align: center;
        }

        .proof-image {
            max-width: 300px;
            max-height: 200px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .proof-image:hover {
            border-color: #007bff;
            transform: scale(1.02);
        }

        .pdf-preview {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .pdf-preview i {
            font-size: 48px;
            color: #dc3545;
        }

        .upload-note {
            color: #6c757d;
            font-size: 14px;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .no-proof {
            text-align: center;
            padding: 30px;
            background: #fff3cd;
            border-radius: 8px;
            border: 1px solid #ffeaa7;
        }

        .no-proof i {
            font-size: 48px;
            color: #856404;
            margin-bottom: 15px;
        }

        .no-proof span {
            display: block;
            font-weight: 600;
            color: #856404;
            margin-bottom: 10px;
        }

        .upload-instructions {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }

        .upload-instructions h4 {
            margin: 0 0 15px 0;
            color: #495057;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .upload-instructions ul {
            margin: 0;
            padding-left: 20px;
        }

        .upload-instructions li {
            margin-bottom: 8px;
            color: #6c757d;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 25px;
        }

        .info-card {
            text-align: center;
            padding: 30px;
        }

        .info-card i {
            font-size: 48px;
            color: #17a2b8;
            margin-bottom: 20px;
        }

        .info-card h4 {
            margin-bottom: 15px;
            color: #2c3e50;
        }

        .info-card p {
            color: #6c757d;
            margin-bottom: 10px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .text-success {
            color: #28a745 !important;
        }

        .text-warning {
            color: #ffc107 !important;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background: #0056b3;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #545b62;
        }

        .btn-outline {
            background: transparent;
            color: #007bff;
            border: 1px solid #007bff;
        }

        .btn-outline:hover {
            background: #007bff;
            color: white;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 8px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow: hidden;
        }

        .modal-header {
            background: #007bff;
            color: white;
            padding: 15px 20px;
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

        .close {
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
        }

        .modal-body {
            padding: 20px;
            text-align: center;
        }

        .modal-body img {
            max-width: 100%;
            max-height: 70vh;
            border-radius: 8px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            .detail-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                justify-content: center;
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const fileInput = document.getElementById('payment_proof');
            const fileUploadWrapper = document.querySelector('.file-upload-wrapper');
            const fileUploadText = document.querySelector('.file-upload-text');
            const fileNameDisplay = document.querySelector('.file-name-display');
            const modal = document.getElementById('imageModal');
            const modalImage = document.getElementById('modalImage');
            const closeBtn = document.querySelector('.close');

            // File upload handling
            if (fileInput && fileUploadWrapper) {
                fileInput.addEventListener('change', function (e) {
                    handleFileSelect(e.target.files[0]);
                });

                fileUploadWrapper.addEventListener('dragover', function (e) {
                    e.preventDefault();
                    fileUploadWrapper.classList.add('dragover');
                });

                fileUploadWrapper.addEventListener('dragleave', function (e) {
                    e.preventDefault();
                    fileUploadWrapper.classList.remove('dragover');
                });

                fileUploadWrapper.addEventListener('drop', function (e) {
                    e.preventDefault();
                    fileUploadWrapper.classList.remove('dragover');

                    const files = e.dataTransfer.files;
                    if (files.length > 0) {
                        fileInput.files = files;
                        handleFileSelect(files[0]);
                    }
                });
            }

            function handleFileSelect(file) {
                if (file) {
                    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
                    if (!allowedTypes.includes(file.type)) {
                        alert('Format file tidak didukung. Gunakan JPG, PNG, GIF, atau PDF');
                        fileInput.value = '';
                        return;
                    }

                    if (file.size > 5 * 1024 * 1024) {
                        alert('Ukuran file terlalu besar. Maksimal 5MB');
                        fileInput.value = '';
                        return;
                    }

                    fileUploadText.style.display = 'none';
                    fileNameDisplay.textContent = file.name;
                    fileNameDisplay.classList.add('show');
                } else {
                    fileUploadText.style.display = 'block';
                    fileNameDisplay.classList.remove('show');
                    fileNameDisplay.textContent = '';
                }
            }

            // Modal handling
            if (closeBtn) {
                closeBtn.addEventListener('click', function () {
                    modal.style.display = 'none';
                });
            }

            window.addEventListener('click', function (event) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });

            // User dropdown
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

        // Function to open image modal
        function openImageModal(imageSrc) {
            const modal = document.getElementById('imageModal');
            const modalImage = document.getElementById('modalImage');
            modalImage.src = imageSrc;
            modal.style.display = 'block';
        }

        // Function to copy account number
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function () {
                showCopyNotification('Nomor rekening berhasil disalin!');
            }).catch(function () {
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                showCopyNotification('Nomor rekening berhasil disalin!');
            });
        }

        function showCopyNotification(message) {
            const existingNotification = document.querySelector('.copy-notification');
            if (existingNotification) {
                existingNotification.remove();
            }

            const notification = document.createElement('div');
            notification.className = 'copy-notification';
            notification.innerHTML = '<i class="fas fa-check"></i> ' + message;
            document.body.appendChild(notification);

            setTimeout(() => notification.classList.add('show'), 100);
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }
    </script>
</body>

</html>