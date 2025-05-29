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

// Fungsi untuk mendapatkan badge status
function getStatusBadge($status)
{
    $badges = [
        'pending' => ['class' => 'badge-warning', 'text' => 'Menunggu'],
        'paid' => ['class' => 'badge-info', 'text' => 'Dibayar'],
        'processing' => ['class' => 'badge-primary', 'text' => 'Diproses'],
        'shipped' => ['class' => 'badge-secondary', 'text' => 'Dikirim'],
        'delivered' => ['class' => 'badge-success', 'text' => 'Selesai'],
        'cancelled' => ['class' => 'badge-danger', 'text' => 'Dibatalkan']
    ];

    return $badges[$status] ?? ['class' => 'badge-default', 'text' => ucfirst($status)];
}

// Fungsi untuk format payment method
function formatPaymentMethod($method)
{
    $methods = [
        'transfer_bank' => 'Transfer Bank',
        'credit_card' => 'Kartu Kredit',
        'e_wallet' => 'E-Wallet',
        'cod' => 'COD'
    ];

    return $methods[$method] ?? ucfirst(str_replace('_', ' ', $method));
}

// Query untuk mendapatkan pesanan user
$stmt = $pdo->prepare("
    SELECT 
        o.*,
        so.name as shipping_name,
        so.cost as shipping_cost_detail,
        COUNT(oi.id) as total_items,
        SUM(oi.quantity) as total_quantity
    FROM orders o
    LEFT JOIN shipping_options so ON o.shipping_option_id = so.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.user_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$stmt->execute([$user['id']]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Pesanan Saya - NADA BookStore</title>
    <link rel="stylesheet" href="styles.css" />
    <link rel="stylesheet" href="my_orders.css" />
    <link rel="stylesheet" href="profile.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
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

        <!-- CSS untuk styling alert messages -->
        <style>
            .alert {
                padding: 15px 20px;
                margin: 20px auto;
                border-radius: 8px;
                display: flex;
                align-items: center;
                gap: 10px;
                font-weight: 500;
                max-width: 1200px;
                animation: slideDown 0.3s ease;
            }

            .alert-success {
                background: linear-gradient(135deg, #d4edda, #c3e6cb);
                color: #155724;
                border: 1px solid #c3e6cb;
                box-shadow: 0 4px 15px rgba(40, 167, 69, 0.2);
            }

            .alert-error {
                background: linear-gradient(135deg, #f8d7da, #f5c6cb);
                color: #721c24;
                border: 1px solid #f5c6cb;
                box-shadow: 0 4px 15px rgba(220, 53, 69, 0.2);
            }

            .alert i {
                font-size: 18px;
            }

            @keyframes slideDown {
                from {
                    opacity: 0;
                    transform: translateY(-20px);
                }

                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            /* Auto-hide animation */
            .alert.fade-out {
                opacity: 0;
                transform: translateY(-10px);
                transition: all 0.3s ease;
            }
        </style>

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
                        <li><a href="my_orders.php" class="active"><i class="fas fa-shopping-bag"></i> Pesanan Saya</a>
                        </li>
                        <li><a href="change_password.php"><i class="fas fa-lock"></i> Ganti Password</a></li>
                        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a></li>
                    </ul>
                </div>

                <!-- Main Content -->
                <div class="profile-main">
                    <div class="section-title">
                        <i class="fas fa-shopping-bag"></i>
                        <h2>Pesanan Saya</h2>
                    </div>

                    <?php if (empty($orders)): ?>
                        <div class="orders-container">
                            <div class="empty-orders">
                                <i class="fas fa-shopping-cart"></i>
                                <h3>Belum ada pesanan</h3>
                                <p>Anda belum memiliki pesanan apapun. Mulai berbelanja sekarang!</p>
                                <a href="products.php" class="btn btn-primary">
                                    <i class="fas fa-shopping-cart"></i> Mulai Belanja
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="orders-container">
                            <?php foreach ($orders as $order): ?>
                                <div class="order-item">
                                    <div class="order-header">
                                        <div>
                                            <div class="order-number">
                                                Order #<?= htmlspecialchars($order['order_number']) ?>
                                            </div>
                                            <div class="order-date">
                                                <i class="fas fa-calendar"></i>
                                                <?= date('d F Y, H:i', strtotime($order['created_at'])) ?>
                                            </div>
                                        </div>
                                        <div class="order-status">
                                            <?php
                                            $statusBadge = getStatusBadge($order['status']);
                                            ?>
                                            <span class="badge <?= $statusBadge['class'] ?>">
                                                <?= $statusBadge['text'] ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="order-details">
                                        <div class="detail-item">
                                            <span class="detail-label">Metode Pembayaran</span>
                                            <span
                                                class="detail-value"><?= formatPaymentMethod($order['payment_method']) ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label">Pengiriman</span>
                                            <span
                                                class="detail-value"><?= htmlspecialchars($order['shipping_name'] ?? 'Reguler') ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label">Alamat Pengiriman</span>
                                            <span class="detail-value">
                                                <?= htmlspecialchars($order['shipping_address'] ?? '') ?>
                                                <?= $order['shipping_city'] ? ', ' . htmlspecialchars($order['shipping_city']) : '' ?>
                                                <?= $order['shipping_province'] ? ', ' . htmlspecialchars($order['shipping_province']) : '' ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="order-items-summary">
                                        <i class="fas fa-box"></i>
                                        <strong><?= $order['total_items'] ?></strong> produk
                                        (<strong><?= $order['total_quantity'] ?></strong> item)
                                    </div>

                                    <?php if ($order['tracking_number']): ?>
                                        <div class="tracking-info">
                                            <i class="fas fa-shipping-fast"></i>
                                            No. Resi: <strong><?= htmlspecialchars($order['tracking_number']) ?></strong>
                                        </div>
                                    <?php endif; ?>

                                    <div class="order-footer">
                                        <div class="order-total">
                                            Total: Rp <?= number_format($order['total_amount'], 0, ',', '.') ?>
                                        </div>
                                        <div class="order-actions">
                                            <a href="user_order_detail.php?id=<?= $order['id'] ?>" class="btn-view-detail">
                                                <i class="fas fa-eye"></i> Lihat Detail
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

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
            const form = document.getElementById('profileForm');
            const submitBtn = form.querySelector('.btn-primary');

            // Form submission with loading state
            form.addEventListener('submit', function () {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
            });

            // Auto-hide alerts after 5 seconds
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

            // Phone number formatting
            const phoneInput = document.getElementById('phone');
            phoneInput.addEventListener('input', function (e) {
                let value = e.target.value.replace(/\D/g, '');

                if (value.startsWith('62')) {
                    value = '0' + value.substring(2);
                }

                e.target.value = value;
            });

            // Postal code validation
            const postalCodeInput = document.getElementById('postal_code');
            postalCodeInput.addEventListener('input', function (e) {
                e.target.value = e.target.value.replace(/\D/g, '').substring(0, 5);
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
            })
        };
    </script>
</body>

</html>