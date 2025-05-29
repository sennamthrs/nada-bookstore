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

$errorMsg = '';
$successMsg = '';

// Handle form submission untuk update profile
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = isset($_POST['nama']) ? trim($_POST['nama']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $no_telepon = isset($_POST['no_telepon']) ? trim($_POST['no_telepon']) : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
    $kota = isset($_POST['kota']) ? trim($_POST['kota']) : '';
    $kode_pos = isset($_POST['kode_pos']) ? trim($_POST['kode_pos']) : '';
    $provinsi = isset($_POST['provinsi']) ? trim($_POST['provinsi']) : '';

    if (empty($nama)) {
        $errorMsg = "Nama wajib diisi.";
    } elseif (empty($email)) {
        $errorMsg = "Email wajib diisi.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMsg = "Format email tidak valid.";
    } else {
        // Cek apakah email sudah digunakan user lain
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user['id']]);

        if ($stmt->rowCount() > 0) {
            $errorMsg = "Email sudah digunakan oleh pengguna lain.";
        } else {
            // Update dengan nama kolom yang sesuai dengan struktur database
            $stmt = $pdo->prepare("UPDATE users SET nama = ?, email = ?, no_telepon = ?, address = ?, kota = ?, provinsi = ?, kode_pos = ?, updated_at = NOW() WHERE id = ?");

            if ($stmt->execute([$nama, $email, $no_telepon, $address, $kota, $provinsi, $kode_pos, $user['id']])) {
                // Update session data
                $_SESSION['user_name'] = $nama;
                $_SESSION['user_email'] = $email;

                $successMsg = "Profil berhasil diperbarui.";

                // Refresh user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user['id']]);
                $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $errorMsg = "Terjadi kesalahan saat memperbarui profil.";
            }
        }
    }
}

// Ambil data user terbaru dari database
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user['id']]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Profil Saya - NADA BookStore</title>
    <link rel="stylesheet" href="styles.css" />
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
        <div class="profile-container">
            <!-- Profile Header -->
            <div class="profile-header">

                <div class="profile-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <h1><?= htmlspecialchars($userData['nama']) ?></h1>
                <p><?= htmlspecialchars($userData['email']) ?></p>
            </div>

            <div class="profile-content">
                <!-- Sidebar Menu -->
                <div class="profile-sidebar">
                    <ul class="sidebar-menu">
                        <li><a href="profile.php" class="active"><i class="fas fa-user"></i> Profil Saya</a></li>
                        <li><a href="my_orders.php"><i class="fas fa-shopping-bag"></i> Pesanan Saya</a></li>
                        <li><a href="change_password.php"><i class="fas fa-lock"></i> Ganti Password</a></li>
                        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a></li>
                    </ul>
                </div>

                <!-- Main Content -->
                <div class="profile-main">
                    <div class="section-title">
                        <i class="fas fa-edit"></i>
                        <h2>Edit Profil</h2>
                    </div>

                    <div class="info-card">
                        <h4><i class="fas fa-info-circle"></i> Informasi</h4>
                        <p>Pastikan informasi profil Anda selalu terbaru untuk pengalaman berbelanja yang lebih baik.
                        </p>
                    </div>

                    <?php if ($errorMsg): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-triangle"></i>
                            <?= htmlspecialchars($errorMsg) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($successMsg): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <?= htmlspecialchars($successMsg) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="profile.php" id="profileForm">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">
                                    <i class="fas fa-user"></i>
                                    Nama Lengkap *
                                </label>
                                <input type="text" id="nama" name="nama" class="form-control"
                                    value="<?= htmlspecialchars($userData['nama'] ?? '') ?>"
                                    placeholder="Masukkan nama lengkap" required />
                            </div>

                            <div class="form-group">
                                <label for="email">
                                    <i class="fas fa-envelope"></i>
                                    Email *
                                </label>
                                <input type="email" id="email" name="email" class="form-control"
                                    value="<?= htmlspecialchars($userData['email'] ?? '') ?>"
                                    placeholder="Masukkan alamat email" required />
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone">
                                    <i class="fas fa-phone"></i>
                                    Nomor Telepon
                                </label>
                                <input type="text" id="no_telepon" name="no_telepon" class="form-control"
                                    value="<?= htmlspecialchars($userData['no_telepon'] ?? '') ?>"
                                    placeholder="Contoh: 08123456789" />
                            </div>

                            <div class="form-group">
                                <label for="city">
                                    <i class="fas fa-city"></i>
                                    Kota
                                </label>
                                <input type="text" id="kota" name="kota" class="form-control"
                                    value="<?= htmlspecialchars($userData['kota'] ?? '') ?>"
                                    placeholder="Masukkan nama kota" />
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="address">
                                <i class="fas fa-home"></i>
                                Alamat Lengkap
                            </label>
                            <textarea id="address" name="address" class="form-control" rows="3"
                                placeholder="Masukkan alamat lengkap"><?= htmlspecialchars($userData['address'] ?? '') ?></textarea>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="province">
                                    <i class="fas fa-map"></i>
                                    Provinsi
                                </label>
                                <input type="text" id="provinsi" name="provinsi" class="form-control"
                                    value="<?= htmlspecialchars($userData['provinsi'] ?? '') ?>"
                                    placeholder="Masukkan nama provinsi" />
                            </div>

                            <div class="form-group">
                                <label for="postal_code">
                                    <i class="fas fa-mail-bulk"></i>
                                    Kode Pos
                                </label>
                                <input type="text" id="kode_pos" name="kode_pos" class="form-control"
                                    value="<?= htmlspecialchars($userData['kode_pos'] ?? '') ?>"
                                    placeholder="Contoh: 12345" />
                            </div>
                        </div>

                        <div class="btn-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                Simpan Perubahan
                            </button>
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i>
                                Batal
                            </a>
                        </div>
                    </form>
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