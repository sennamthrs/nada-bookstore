<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include file auth.php yang berisi fungsi-fungsi otentikasi
require_once 'auth.php';

// Fungsi untuk memeriksa apakah user adalah admin
function requireAdminLogin()
{
    if (!isLoggedIn()) {
        // Jika belum login, redirect ke halaman login
        header("Location: login.php");
        exit;
    }
}

// Jalankan pengecekan admin
requireAdminLogin();

$user = getLoggedUser();

// Ambil isi keranjang dari session
$cart = $_SESSION['cart'] ?? [];

// Initialize shipping option in session if not set
if (!isset($_SESSION['shipping_option_id'])) {
    $_SESSION['shipping_option_id'] = 1; // Default ke reguler
}

// Proses update jumlah item di keranjang jika ada POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update'])) {
        // Create a new cart array
        $updatedCart = [];

        foreach ($_POST['quantities'] as $product_id => $qty) {
            $qty = intval($qty);
            if ($qty >= 1) {
                // Keep only items with valid quantities
                // Find the existing cart item and update quantity
                $found = false;
                foreach ($cart as $index => $item) {
                    if ($item['product_id'] == $product_id) {
                        $updatedCart[] = ['product_id' => $product_id, 'quantity' => $qty];
                        $found = true;
                        break;
                    }
                }

                // If not found, add it (shouldn't happen but just in case)
                if (!$found) {
                    $updatedCart[] = ['product_id' => $product_id, 'quantity' => $qty];
                }
            }
        }

        $_SESSION['cart'] = $updatedCart;
        header('Location: cart.php');
        exit;
    } elseif (isset($_POST['remove'])) {
        $remove_id = $_POST['remove'];

        // Filter out the item to be removed
        $updatedCart = [];
        foreach ($cart as $item) {
            if ($item['product_id'] != $remove_id) {
                $updatedCart[] = $item;
            }
        }

        $_SESSION['cart'] = $updatedCart;
        header('Location: cart.php');
        exit;
    } elseif (isset($_POST['update_shipping'])) {
        // Update shipping option
        $shipping_option_id = intval($_POST['shipping_option_id']);
        $_SESSION['shipping_option_id'] = $shipping_option_id;
        header('Location: cart.php');
        exit;
    }
}

// Prepare product IDs for database query
$product_ids = [];
foreach ($cart as $item) {
    $product_ids[] = $item['product_id'];
}

// Ambil data produk untuk item di keranjang
$products = [];
if (count($product_ids) > 0) {
    $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
    $stmt->execute($product_ids);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Create a lookup array for products by ID
$productsById = [];
foreach ($products as $product) {
    $productsById[$product['id']] = $product;
}

// Ambil opsi pengiriman dari database
$stmt = $pdo->query("SELECT * FROM shipping_options WHERE is_active = 1 ORDER BY cost ASC");
$shipping_options = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil opsi pengiriman yang dipilih
$selected_shipping_id = $_SESSION['shipping_option_id'];
$selected_shipping = null;
foreach ($shipping_options as $option) {
    if ($option['id'] == $selected_shipping_id) {
        $selected_shipping = $option;
        break;
    }
}

// Jika tidak ada yang dipilih, gunakan yang pertama
if (!$selected_shipping && count($shipping_options) > 0) {
    $selected_shipping = $shipping_options[0];
    $_SESSION['shipping_option_id'] = $selected_shipping['id'];
}

// Hitung subtotal produk
$subtotal = 0;
foreach ($cart as $item) {
    $product_id = $item['product_id'];
    $qty = $item['quantity'];

    if (isset($productsById[$product_id])) {
        $price = floatval($productsById[$product_id]['price']);
        $subtotal += $price * $qty;
    }
}

// Hitung total dengan biaya pengiriman
$shipping_cost = $selected_shipping ? floatval($selected_shipping['cost']) : 0;
$total = $subtotal + $shipping_cost;

// Ambil kategori untuk sidebar
$stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Keranjang - NADA BookStore</title>
    <link rel="stylesheet" href="cart-style.css" />
    <link rel="stylesheet" href="styles.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
</head>

<body>
    <!-- Modern Sticky Header -->
    <header class="main-header">
        <div class="header-content">
            <a href="index.php" class="logo">
                <i class="fas fa-book"></i>
                <h1>NADA BookStore</h1>
            </a>

            <nav class="main-nav">
                <ul class="nav-links">
                    <li><a href="index.php">Beranda</a></li>
                    <li><a href="products.php" class="active">Produk</a></li>
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

    <!-- Modern Breadcrumb Navigation -->
    <div class="breadcrumb-container">
        <div class="container">
            <ul class="breadcrumb">
                <li><a href="index.php">Beranda</a></li>
                <li class="active">Keranjang Belanja</li>
            </ul>
        </div>
    </div>

    <main>
        <div class="container">
            <!-- Shopping Cart Section -->
            <div class="cart-page">
                <!-- Page Title with Icon -->
                <div class="page-title">
                    <i class="fas fa-shopping-cart"></i>
                    <h1>Keranjang Belanja</h1>
                </div>

                <?php if (empty($cart)): ?>
                    <div class="empty-cart">
                        <div class="empty-cart-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <h3>Keranjang Anda Kosong</h3>
                        <p>Anda belum menambahkan produk apapun ke keranjang belanja.</p>
                        <a href="products.php" class="btn btn-primary">Mulai Belanja</a>
                    </div>
                <?php else: ?>
                    <div class="cart-content">
                        <div class="cart-items">
                            <form method="POST" action="cart.php">
                                <table class="cart-table">
                                    <thead>
                                        <tr>
                                            <th class="product-col">Produk</th>
                                            <th>Harga</th>
                                            <th>Jumlah</th>
                                            <th>Subtotal</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cart as $item):
                                            $product_id = $item['product_id'];
                                            $qty = $item['quantity'];

                                            // Skip if product doesn't exist
                                            if (!isset($productsById[$product_id]))
                                                continue;

                                            $product = $productsById[$product_id];
                                            $price = floatval($product['price']);
                                            $item_subtotal = $price * $qty;
                                            ?>
                                            <tr>
                                                <td class="product-col">
                                                    <div class="cart-product">
                                                        <img src="<?= htmlspecialchars($product['image_url']) ?>"
                                                            alt="<?= htmlspecialchars($product['name']) ?>"
                                                            class="cart-product-img" />
                                                        <div class="cart-product-info">
                                                            <h3><a
                                                                    href="product_detail.php?id=<?= $product_id ?>"><?= htmlspecialchars($product['name']) ?></a>
                                                            </h3>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="cart-price">Rp. <?= number_format($price, 0, ',', '.') ?></div>
                                                </td>
                                                <td>
                                                    <div class="quantity-input-group">
                                                        <button type="button" class="quantity-btn minus-btn"
                                                            data-id="<?= $product_id ?>">
                                                            <i class="fas fa-minus"></i>
                                                        </button>
                                                        <input type="number" name="quantities[<?= $product_id ?>]"
                                                            value="<?= $qty ?>" min="1" id="qty-<?= $product_id ?>"
                                                            class="quantity-input" />
                                                        <button type="button" class="quantity-btn plus-btn"
                                                            data-id="<?= $product_id ?>">
                                                            <i class="fas fa-plus"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="cart-subtotal">Rp.
                                                        <?= number_format($item_subtotal, 0, ',', '.') ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <button type="submit" name="remove" value="<?= $product_id ?>"
                                                        class="btn-remove"
                                                        onclick="return confirm('Hapus produk ini dari keranjang?');">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>

                                <div class="cart-actions">
                                    <div class="cart-action-buttons">
                                        <button type="submit" name="update" class="btn btn-secondary">
                                            <i class="fas fa-sync-alt"></i> Update Keranjang
                                        </button>
                                        <a href="products.php" class="btn btn-outline">
                                            <i class="fas fa-arrow-left"></i> Lanjut Belanja
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <div class="cart-summary">
                            <!-- Shipping Options -->
                            <div class="shipping-card">
                                <h3><i class="fas fa-truck"></i> Pilihan Pengiriman</h3>
                                <form method="POST" action="cart.php" id="shipping-form">
                                    <div class="shipping-options">
                                        <?php foreach ($shipping_options as $option): ?>
                                            <div class="shipping-option">
                                                <label class="shipping-label">
                                                    <input type="radio" name="shipping_option_id" value="<?= $option['id'] ?>"
                                                        <?= ($option['id'] == $selected_shipping_id) ? 'checked' : '' ?>
                                                        onchange="document.getElementById('shipping-form').submit();" />
                                                    <div class="shipping-info">
                                                        <div class="shipping-name">
                                                            <strong><?= htmlspecialchars($option['name']) ?></strong>
                                                            <span class="shipping-cost">Rp.
                                                                <?= number_format($option['cost'], 0, ',', '.') ?></span>
                                                        </div>
                                                        <div class="shipping-desc">
                                                            <?= htmlspecialchars($option['description']) ?>
                                                        </div>
                                                        <div class="shipping-time">
                                                            <i class="fas fa-clock"></i>
                                                            <?= htmlspecialchars($option['estimated_days']) ?>
                                                        </div>
                                                    </div>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <input type="hidden" name="update_shipping" value="1" />
                                </form>
                            </div>

                            <div class="summary-card">
                                <h3>Ringkasan Belanja</h3>

                                <div class="summary-row">
                                    <span>Subtotal (<?= count($cart) ?> barang)</span>
                                    <span>Rp. <?= number_format($subtotal, 0, ',', '.') ?></span>
                                </div>

                                <div class="summary-row">
                                    <span>Biaya Pengiriman
                                        (<?= $selected_shipping ? htmlspecialchars($selected_shipping['name']) : 'Belum dipilih' ?>)</span>
                                    <span>Rp. <?= number_format($shipping_cost, 0, ',', '.') ?></span>
                                </div>

                                <div class="summary-total">
                                    <span>Total Belanja</span>
                                    <span>Rp. <?= number_format($total, 0, ',', '.') ?></span>
                                </div>

                                <a href="checkout.php" class="btn btn-primary checkout-btn">
                                    <i class="fas fa-credit-card"></i> Lanjut ke Pembayaran
                                </a>

                                <div class="payment-methods">
                                    <p>Metode Pembayaran</p>
                                    <div class="payment-icons">
                                        <i class="fas fa-university"></i>
                                        <i class="fas fa-money-bill-wave"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="promotion-card">
                                <h3>Kode Promo</h3>
                                <div class="promo-form">
                                    <input type="text" placeholder="Masukkan kode promo" class="form-control" />
                                    <button class="btn btn-secondary">Gunakan</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
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

    <!-- JavaScript for Cart Page -->
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
    </script>
</body>

</html>