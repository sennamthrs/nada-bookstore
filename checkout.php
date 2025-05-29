<?php
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
$cart = $_SESSION['cart'] ?? [];

// Redirect jika keranjang kosong
if (empty($cart)) {
    header("Location: cart.php");
    exit;
}

// Ambil pilihan pengiriman dari session
$selected_shipping_id = $_SESSION['shipping_option_id'] ?? 1;

// Ambil data pengiriman yang dipilih
$stmt = $pdo->prepare("SELECT * FROM shipping_options WHERE id = ? AND is_active = 1");
$stmt->execute([$selected_shipping_id]);
$selected_shipping = $stmt->fetch(PDO::FETCH_ASSOC);

// Jika shipping option tidak valid, redirect ke cart
if (!$selected_shipping) {
    header("Location: cart.php");
    exit;
}

// Prepare product IDs untuk query database
$product_ids = [];
foreach ($cart as $item) {
    $product_ids[] = $item['product_id'];
}

// Ambil data produk
$products = [];
if (count($product_ids) > 0) {
    $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
    $stmt->execute($product_ids);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Create lookup array
$productsById = [];
foreach ($products as $product) {
    $productsById[$product['id']] = $product;
}

// Hitung total
$subtotal = 0;
foreach ($cart as $item) {
    $product_id = $item['product_id'];
    $qty = $item['quantity'];

    if (isset($productsById[$product_id])) {
        $price = floatval($productsById[$product_id]['price']);
        $subtotal += $price * $qty;
    }
}

$shipping_cost = floatval($selected_shipping['cost']);
$total = $subtotal + $shipping_cost;

// Function untuk handle upload file
function handleFileUpload($file, $order_id)
{
    $upload_dir = 'uploads/payment_proofs/';

    // Buat direktori jika belum ada
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

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

    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = 'payment_proof_' . $order_id . '_' . time() . '.' . $file_extension;
    $upload_path = $upload_dir . $new_filename;

    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        throw new Exception('Gagal menyimpan file');
    }

    return $new_filename;
}

// Process checkout
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    (isset($_POST['place_order']) ||
        isset($_POST['checkout_action']) ||
        isset($_POST['form_submitted']) ||
        isset($_POST['button_clicked']) ||
        (!empty($_POST['nama']) && !empty($_POST['payment_method'])))
) {

    try {
        $pdo->beginTransaction();

        // Ambil data dari form
        $payment_method = $_POST['payment_method'] ?? 'transfer_bank';
        $order_notes = $_POST['order_notes'] ?? null;
        $shipping_address = $_POST['address'] ?? '';
        $shipping_city = $_POST['kota'] ?? '';
        $shipping_province = $_POST['provinsi'] ?? '';
        $shipping_postal_code = $_POST['kode_pos'] ?? '';

        // Generate order number
        $order_number = 'ORD-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

        // Insert order dengan data lengkap
        $order_sql = "
            INSERT INTO orders (
                user_id, order_number, subtotal, shipping_cost, tax_amount, total_amount, 
                shipping_option_id, payment_method, payment_proof, payment_proof_uploaded_at,
                shipping_address, shipping_city, shipping_province, shipping_postal_code, 
                notes, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ";

        // Handle payment proof upload
        $payment_proof_filename = null;
        $payment_proof_uploaded_at = null;

        if ($payment_method === 'transfer_bank' && isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
            // Insert order dulu untuk mendapat order_id
            $temp_order_params = [
                $user['id'],
                $order_number,
                $subtotal,
                $shipping_cost,
                0, // tax_amount
                $total,
                $selected_shipping_id,
                $payment_method,
                null, // payment_proof akan diupdate setelah upload
                null, // payment_proof_uploaded_at
                $shipping_address,
                $shipping_city,
                $shipping_province,
                $shipping_postal_code,
                $order_notes
            ];

            $stmt = $pdo->prepare($order_sql);
            $result = $stmt->execute($temp_order_params);

            if (!$result) {
                throw new Exception("Gagal menyimpan pesanan. Silakan coba lagi.");
            }

            $order_id = $pdo->lastInsertId();

            // Upload file
            $payment_proof_filename = handleFileUpload($_FILES['payment_proof'], $order_id);
            $payment_proof_uploaded_at = date('Y-m-d H:i:s');

            // Update order dengan info payment proof
            $update_sql = "UPDATE orders SET payment_proof = ?, payment_proof_uploaded_at = ? WHERE id = ?";
            $stmt = $pdo->prepare($update_sql);
            $stmt->execute([$payment_proof_filename, $payment_proof_uploaded_at, $order_id]);

        } else {
            // Insert order tanpa payment proof
            $order_params = [
                $user['id'],
                $order_number,
                $subtotal,
                $shipping_cost,
                0, // tax_amount
                $total,
                $selected_shipping_id,
                $payment_method,
                $payment_proof_filename,
                $payment_proof_uploaded_at,
                $shipping_address,
                $shipping_city,
                $shipping_province,
                $shipping_postal_code,
                $order_notes
            ];

            $stmt = $pdo->prepare($order_sql);
            $result = $stmt->execute($order_params);

            if (!$result) {
                throw new Exception("Gagal menyimpan pesanan. Silakan coba lagi.");
            }

            $order_id = $pdo->lastInsertId();
        }

        // Insert order items
        foreach ($cart as $item) {
            $product_id = $item['product_id'];
            $qty = $item['quantity'];

            if (isset($productsById[$product_id])) {
                $product = $productsById[$product_id];
                $price = floatval($product['price']);
                $item_subtotal = $price * $qty;

                $item_sql = "
                    INSERT INTO order_items (order_id, product_id, product_name, product_image, quantity, price, subtotal) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ";

                $item_params = [
                    $order_id,
                    $product_id,
                    $product['name'],
                    $product['image_url'],
                    $qty,
                    $price,
                    $item_subtotal
                ];

                $stmt = $pdo->prepare($item_sql);
                $result = $stmt->execute($item_params);

                if (!$result) {
                    throw new Exception("Gagal menyimpan item pesanan. Silakan coba lagi.");
                }

                // Update stock produk (jika ada kolom stock)
                if (isset($product['stock']) && $product['stock'] !== null) {
                    $stmt = $pdo->prepare("UPDATE products SET stock = GREATEST(0, stock - ?) WHERE id = ?");
                    $stmt->execute([$qty, $product_id]);
                }
            }
        }

        $pdo->commit();

        // Clear cart dan shipping option
        unset($_SESSION['cart']);
        unset($_SESSION['shipping_option_id']);

        // Redirect ke halaman success
        header("Location: order_success.php?order_id=" . $order_id);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Terjadi kesalahan saat memproses pesanan: " . $e->getMessage();
        error_log("Checkout Error: " . $e->getMessage());
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
    <title>Checkout - NADA BookStore</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="checkout-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
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
    <div class="checkout-container">
        <div class="page-title">
            <i class="fas fa-credit-card"></i>
            <h1>Checkout</h1>
        </div>

        <?php if (isset($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="checkout-grid">
            <!-- Checkout Form -->
            <div class="checkout-form">
                <form method="POST" action="" enctype="multipart/form-data">
                    <!-- Shipping Information -->
                    <div class="form-section">
                        <h3><i class="fas fa-truck"></i> Informasi Pengiriman</h3>

                        <div class="shipping-info">
                            <h4><?= htmlspecialchars($selected_shipping['name']) ?></h4>
                            <div class="shipping-details">
                                <p><?= htmlspecialchars($selected_shipping['description']) ?></p>
                                <p><strong>Estimasi:
                                        <?= htmlspecialchars($selected_shipping['estimated_days']) ?></strong></p>
                                <p><strong>Biaya: Rp.
                                        <?= number_format($selected_shipping['cost'], 0, ',', '.') ?></strong></p>
                            </div>
                            <a href="cart.php" class="btn btn-outline" style="margin-top: 10px;">
                                <i class="fas fa-edit"></i> Ubah Pengiriman
                            </a>
                        </div>
                    </div>

                    <!-- Billing Information -->
                    <div class="form-section">
                        <h3><i class="fas fa-user"></i> Informasi Penerima</h3>

                        <div class="form-group">
                            <label for="nama">Nama Lengkap</label>
                            <input type="text" id="nama" name="nama" class="form-control"
                                value="<?= htmlspecialchars($user['nama']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control"
                                value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="no_telepon">Nomor Telepon</label>
                            <input type="text" id="no_telepon" name="no_telepon" class="form-control"
                                value="<?= htmlspecialchars($user['no_telepon']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="address">Alamat Lengkap</label>
                            <textarea id="address" name="address" class="form-control" rows="3"
                                placeholder="Masukkan alamat lengkap"
                                required><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="kota">Kota</label>
                                <input type="text" id="kota" name="kota" class="form-control"
                                    value="<?= htmlspecialchars($user['kota']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="provinsi">Provinsi</label>
                                <input type="text" id="provinsi" name="provinsi" class="form-control"
                                    value="<?= htmlspecialchars($user['provinsi']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="kode_pos">Kode Pos</label>
                                <input type="text" id="kode_pos" name="kode_pos" class="form-control"
                                    value="<?= htmlspecialchars($user['kode_pos']) ?>" required>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div class="form-section">
                        <h3><i class="fas fa-credit-card"></i> Metode Pembayaran</h3>

                        <div class="payment-options">
                            <div class="payment-option">
                                <label class="payment-label">
                                    <input type="radio" name="payment_method" value="transfer_bank" checked>
                                    <div class="payment-info">
                                        <i class="fas fa-university"></i>
                                        <span>Transfer Bank</span>
                                    </div>
                                </label>
                            </div>

                            <div class="payment-option">
                                <label class="payment-label">
                                    <input type="radio" name="payment_method" value="cod">
                                    <div class="payment-info">
                                        <i class="fas fa-money-bill-wave"></i>
                                        <span>Bayar di Tempat (COD)</span>
                                    </div>
                                </label>
                            </div>

                            <!-- Bank Transfer Information -->
                            <div class="bank-transfer-info" id="bankTransferInfo">
                                <div class="bank-accounts">
                                    <h4><i class="fas fa-university"></i> Pilih Bank Tujuan Transfer</h4>
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

                                <!-- Upload Bukti Pembayaran -->
                                <div class="payment-proof-section">
                                    <h4><i class="fas fa-receipt"></i> Upload Bukti Pembayaran</h4>
                                    <div class="form-group">
                                        <label for="payment_proof">Bukti Transfer (JPG, PNG, GIF, PDF - Max 5MB)</label>
                                        <div class="file-upload-wrapper">
                                            <input type="file" id="payment_proof" name="payment_proof"
                                                class="file-input" accept="image/*,.pdf">
                                            <div class="file-upload-display">
                                                <i class="fas fa-cloud-upload-alt"></i>
                                                <span class="file-upload-text">Pilih file atau drag & drop di
                                                    sini</span>
                                                <span class="file-name-display"></span>
                                            </div>
                                        </div>
                                        <small class="form-text">
                                            <i class="fas fa-info-circle"></i>
                                            Upload bukti transfer untuk mempercepat proses verifikasi
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Order Notes -->
                        <div class="form-section">
                            <h3><i class="fas fa-sticky-note"></i> Catatan Pesanan (Opsional)</h3>
                            <div class="form-group">
                                <textarea name="order_notes" class="form-control" rows="3"
                                    placeholder="Tambahkan catatan khusus untuk pesanan Anda..."></textarea>
                            </div>
                        </div>

                        <!-- Hidden field untuk memastikan form submission -->
                        <input type="hidden" name="checkout_action" value="place_order">

                        <button type="submit" name="place_order" value="1" class="btn-place-order">
                            <i class="fas fa-check-circle"></i> Buat Pesanan
                        </button>
                </form>
            </div>

            <!-- Order Summary -->
            <div class="order-summary">
                <h3><i class="fas fa-shopping-bag"></i> Ringkasan Pesanan</h3>

                <!-- Order Items -->
                <div class="order-items">
                    <?php foreach ($cart as $item):
                        $product_id = $item['product_id'];
                        $qty = $item['quantity'];

                        if (!isset($productsById[$product_id]))
                            continue;

                        $product = $productsById[$product_id];
                        $price = floatval($product['price']);
                        $item_total = $price * $qty;
                        ?>
                        <div class="order-item">
                            <img src="<?= htmlspecialchars($product['image_url']) ?>"
                                alt="<?= htmlspecialchars($product['name']) ?>" class="item-image">
                            <div class="item-details">
                                <div class="item-name"><?= htmlspecialchars($product['name']) ?></div>
                                <div class="item-price">
                                    <?= $qty ?> x Rp. <?= number_format($price, 0, ',', '.') ?>
                                </div>
                            </div>
                            <div class="item-total">
                                Rp. <?= number_format($item_total, 0, ',', '.') ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Summary Calculation -->
                <div class="summary-calculation">
                    <div class="summary-row">
                        <span>Subtotal (<?= count($cart) ?> item)</span>
                        <span>Rp. <?= number_format($subtotal, 0, ',', '.') ?></span>
                    </div>

                    <div class="summary-row">
                        <span>Pengiriman (<?= htmlspecialchars($selected_shipping['name']) ?>)</span>
                        <span>Rp. <?= number_format($shipping_cost, 0, ',', '.') ?></span>
                    </div>

                    <div class="summary-total">
                        <span>Total Pembayaran</span>
                        <span>Rp. <?= number_format($total, 0, ',', '.') ?></span>
                    </div>
                </div>

                <!-- Security Info -->
                <div class="security-info">
                    <p style="font-size: 12px; color: #6b7280; text-align: center; margin-top: 20px;">
                        <i class="fas fa-shield-alt"></i>
                        Transaksi Anda dijamin aman dan terlindungi
                    </p>
                </div>
            </div>
        </div>
    </div>

    <style>
        .bank-transfer-info {
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .bank-accounts h4 {
            margin-bottom: 15px;
            color: #495057;
            font-size: 16px;
        }

        .bank-account-card {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }

        .bank-account-card:hover {
            border-color: #007bff;
            box-shadow: 0 2px 8px rgba(0, 123, 255, 0.1);
        }

        .bank-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
            color: #007bff;
            font-weight: 600;
        }

        .bank-details .account-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }

        .account-info .label {
            color: #6c757d;
            font-size: 14px;
        }

        .account-number {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            color: #495057;
            cursor: pointer;
            padding: 4px 8px;
            background: #f8f9fa;
            border-radius: 4px;
            transition: background-color 0.3s;
        }

        .account-number:hover {
            background: #e9ecef;
        }

        .copy-icon {
            margin-left: 5px;
            color: #007bff;
            opacity: 0.7;
        }

        .account-name {
            font-weight: 600;
            color: #495057;
        }

        .payment-proof-section {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
        }

        .payment-proof-section h4 {
            margin-bottom: 15px;
            color: #495057;
            font-size: 16px;
        }

        .file-upload-wrapper {
            position: relative;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            background: white;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .file-upload-wrapper:hover {
            border-color: #007bff;
            background: #f8f9fa;
        }

        .file-upload-wrapper.dragover {
            border-color: #007bff;
            background: #e3f2fd;
        }

        .file-input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .file-upload-display {
            pointer-events: none;
        }

        .file-upload-display i {
            font-size: 24px;
            color: #007bff;
            margin-bottom: 10px;
        }

        .file-upload-text {
            display: block;
            color: #6c757d;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .file-name-display {
            display: none;
            color: #28a745;
            font-weight: 600;
            font-size: 14px;
            margin-top: 10px;
        }

        .file-name-display.show {
            display: block;
        }

        .form-text {
            color: #6c757d;
            font-size: 12px;
            margin-top: 8px;
        }

        /* Hide bank transfer info by default */
        .bank-transfer-info {
            display: none;
        }

        .bank-transfer-info.show {
            display: block;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .bank-account-card {
                padding: 12px;
            }

            .bank-details .account-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }

            .account-number {
                font-size: 14px;
            }
        }

        /* Copy notification */
        .copy-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #28a745;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            z-index: 1000;
            opacity: 0;
            transform: translateY(-20px);
            transition: all 0.3s ease;
        }

        .copy-notification.show {
            opacity: 1;
            transform: translateY(0);
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.querySelector('form');
            const placeOrderBtn = document.querySelector('.btn-place-order');
            const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
            const bankTransferInfo = document.getElementById('bankTransferInfo');
            const fileInput = document.getElementById('payment_proof');
            const fileUploadWrapper = document.querySelector('.file-upload-wrapper');
            const fileUploadText = document.querySelector('.file-upload-text');
            const fileNameDisplay = document.querySelector('.file-name-display');

            // Handle payment method change
            function toggleBankTransferInfo() {
                const selectedMethod = document.querySelector('input[name="payment_method"]:checked').value;
                if (selectedMethod === 'transfer_bank') {
                    bankTransferInfo.classList.add('show');
                } else {
                    bankTransferInfo.classList.remove('show');
                }
            }

            // Initialize payment method display
            toggleBankTransferInfo();

            // Add event listeners for payment method change
            paymentMethods.forEach(method => {
                method.addEventListener('change', toggleBankTransferInfo);
            });

            // Handle file upload
            if (fileInput && fileUploadWrapper) {
                // File input change event
                fileInput.addEventListener('change', function (e) {
                    handleFileSelect(e.target.files[0]);
                });

                // Drag and drop events
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
                    // Validate file type
                    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
                    if (!allowedTypes.includes(file.type)) {
                        alert('Format file tidak didukung. Gunakan JPG, PNG, GIF, atau PDF');
                        fileInput.value = '';
                        return;
                    }

                    // Validate file size (5MB)
                    if (file.size > 5 * 1024 * 1024) {
                        alert('Ukuran file terlalu besar. Maksimal 5MB');
                        fileInput.value = '';
                        return;
                    }

                    // Show file name
                    fileUploadText.style.display = 'none';
                    fileNameDisplay.textContent = file.name;
                    fileNameDisplay.classList.add('show');
                } else {
                    // Reset display
                    fileUploadText.style.display = 'block';
                    fileNameDisplay.classList.remove('show');
                    fileNameDisplay.textContent = '';
                }
            }

            // Pastikan button memiliki attributes yang benar
            if (placeOrderBtn && !placeOrderBtn.name) {
                placeOrderBtn.name = 'place_order';
                placeOrderBtn.value = '1';
            }

            // Form submission
            form.addEventListener('submit', function (e) {
                const selectedMethod = document.querySelector('input[name="payment_method"]:checked').value;

                // Validasi untuk transfer bank
                if (selectedMethod === 'transfer_bank') {
                    const paymentProofFile = fileInput.files[0];
                    if (!paymentProofFile) {
                        const confirmSubmit = confirm('Anda belum mengupload bukti pembayaran. Apakah Anda yakin ingin melanjutkan? Anda dapat mengupload bukti pembayaran nanti di halaman pesanan.');
                        if (!confirmSubmit) {
                            e.preventDefault();
                            return;
                        }
                    }
                }

                // Tambahkan backup hidden field
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'form_submitted';
                hiddenInput.value = '1';
                form.appendChild(hiddenInput);

                // Disable button dan ubah text
                placeOrderBtn.disabled = true;
                placeOrderBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
            });

            // Backup untuk button click
            placeOrderBtn.addEventListener('click', function (e) {
                let hiddenField = document.querySelector('input[name="button_clicked"]');
                if (!hiddenField) {
                    hiddenField = document.createElement('input');
                    hiddenField.type = 'hidden';
                    hiddenField.name = 'button_clicked';
                    form.appendChild(hiddenField);
                }
                hiddenField.value = 'place_order';
            });

            // User dropdown functionality
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

        // Function to copy account number to clipboard
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
    </script>
</body>

</html>