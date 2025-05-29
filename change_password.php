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

// Handle form submission untuk update password
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = isset($_POST['current_password']) ? trim($_POST['current_password']) : '';
    $new_password = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
    $confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';

    // Validasi input
    if (empty($current_password)) {
        $errorMsg = "Password saat ini wajib diisi.";
    } elseif (empty($new_password)) {
        $errorMsg = "Password baru wajib diisi.";
    } elseif (strlen($new_password) < 8) {
        $errorMsg = "Password baru minimal 8 karakter.";
    } elseif ($new_password !== $confirm_password) {
        $errorMsg = "Konfirmasi password tidak sesuai.";
    } else {
        // Verifikasi password saat ini
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$userData || !password_verify($current_password, $userData['password'])) {
            $errorMsg = "Password saat ini tidak sesuai.";
        } else {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");

            if ($stmt->execute([$hashed_password, $user['id']])) {
                $successMsg = "Password berhasil diperbarui.";
            } else {
                $errorMsg = "Terjadi kesalahan saat memperbarui password.";
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
    <title>Ganti Password - NADA BookStore</title>
    <link rel="stylesheet" href="styles.css" />
    <link rel="stylesheet" href="profile.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
</head>

<style>
    /* Enhanced Toggle Password Button */
    .toggle-password {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        cursor: pointer;
        padding: 8px;
        border-radius: 50%;
        color: #7f8c8d;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
    }

    .toggle-password:hover {
        background-color: rgba(52, 152, 219, 0.1);
        color: var(--primary-color);
        transform: translateY(-50%) scale(1.1);
    }

    .toggle-password:active {
        transform: translateY(-50%) scale(0.95);
    }

    .toggle-password i {
        transition: all 0.3s ease;
        font-size: 16px;
    }

    .toggle-password.active {
        color: var(--primary-color);
        background-color: rgba(52, 152, 219, 0.1);
    }

    .input-wrapper {
        position: relative;
        display: block;
    }

    .input-wrapper .form-control {
        padding-right: 45px;
    }

    .input-wrapper .toggle-password {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
    }
</style>

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
                        <li><a href="profile.php"><i class="fas fa-user"></i> Profil Saya</a></li>
                        <li><a href="my_orders.php"><i class="fas fa-shopping-bag"></i> Pesanan Saya</a></li>
                        <li><a href="change_password.php" class="active"><i class="fas fa-lock"></i> Ganti Password</a>
                        </li>
                        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a></li>
                    </ul>
                </div>

                <!-- Main Content -->
                <div class="profile-main">
                    <div class="section-title">
                        <i class="fas fa-key"></i>
                        <h2>Ganti Password</h2>
                    </div>

                    <div class="info-card">
                        <h4><i class="fas fa-info-circle"></i> Informasi</h4>
                        <p>Untuk keamanan akun Anda, gunakan password yang kuat dengan kombinasi huruf, angka, dan
                            simbol.</p>
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

                    <form method="POST" action="change_password.php" id="passwordForm">
                        <div class="form-group">
                            <label for="current_password">
                                <i class="fas fa-lock"></i>
                                Password Saat Ini *
                            </label>
                            <div class="input-wrapper">
                                <input type="password" id="current_password" name="current_password"
                                    class="form-control" placeholder="Masukkan password saat ini" required />
                                <button type="button" class="toggle-password" data-target="current_password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="new_password">
                                <i class="fas fa-key"></i>
                                Password Baru *
                            </label>
                            <div class="input-wrapper">
                                <input type="password" id="new_password" name="new_password" class="form-control"
                                    placeholder="Minimal 8 karakter" required minlength="8" />
                                <button type="button" class="toggle-password" data-target="new_password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="password-strength-meter">
                                <div class="strength-bar"></div>
                            </div>
                            <div class="password-tip">Password harus minimal 8 karakter</div>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">
                                <i class="fas fa-check-circle"></i>
                                Konfirmasi Password Baru *
                            </label>
                            <div class="input-wrapper">
                                <input type="password" id="confirm_password" name="confirm_password"
                                    class="form-control" placeholder="Ulangi password baru" required />
                                <button type="button" class="toggle-password" data-target="confirm_password">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <div class="match-status"></div>
                            </div>
                        </div>
                        <div class="btn-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                Simpan Password Baru
                            </button>
                            <a href="profile.php" class="btn btn-secondary">
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
            const form = document.getElementById('passwordForm');
            const submitBtn = form.querySelector('.btn-primary');
            const newPasswordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const strengthBar = document.querySelector('.strength-bar');
            const matchStatus = document.querySelector('.match-status');

            // Enhanced Toggle Password dengan visual feedback (SATU-SATUNYA)
            const toggleButtons = document.querySelectorAll('.toggle-password');
            toggleButtons.forEach(button => {
                button.addEventListener('click', function () {
                    const targetId = this.getAttribute('data-target');
                    const passwordInput = document.getElementById(targetId);
                    const icon = this.querySelector('i');

                    if (passwordInput.type === 'password') {
                        passwordInput.type = 'text';
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                        this.classList.add('active');

                        // Visual feedback
                        this.style.transform = 'translateY(-50%) scale(1.2)';
                        setTimeout(() => {
                            this.style.transform = 'translateY(-50%) scale(1)';
                        }, 150);
                    } else {
                        passwordInput.type = 'password';
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                        this.classList.remove('active');

                        // Visual feedback
                        this.style.transform = 'translateY(-50%) scale(0.8)';
                        setTimeout(() => {
                            this.style.transform = 'translateY(-50%) scale(1)';
                        }, 150);
                    }
                });
            });

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

            // Password strength meter
            newPasswordInput.addEventListener('input', function () {
                const password = this.value;
                let strength = 0;

                if (password.length >= 8) strength += 25;
                if (password.match(/[A-Z]/)) strength += 25;
                if (password.match(/[0-9]/)) strength += 25;
                if (password.match(/[^a-zA-Z0-9]/)) strength += 25;

                strengthBar.style.width = strength + '%';

                if (strength <= 25) {
                    strengthBar.style.backgroundColor = '#ff4d4d'; // Weak
                } else if (strength <= 50) {
                    strengthBar.style.backgroundColor = '#ffa64d'; // Medium
                } else if (strength <= 75) {
                    strengthBar.style.backgroundColor = '#ffff4d'; // Good
                } else {
                    strengthBar.style.backgroundColor = '#4dff4d'; // Strong
                }

                // Check matching with confirm password
                checkPasswordMatch();
            });

            // Password match checker
            confirmPasswordInput.addEventListener('input', checkPasswordMatch);

            function checkPasswordMatch() {
                const newPassword = newPasswordInput.value;
                const confirmPassword = confirmPasswordInput.value;

                if (!confirmPassword) {
                    matchStatus.textContent = '';
                    return;
                }

                if (newPassword === confirmPassword) {
                    matchStatus.textContent = 'Password cocok!';
                    matchStatus.style.color = '#4dff4d';
                } else {
                    matchStatus.textContent = 'Password tidak cocok';
                    matchStatus.style.color = '#ff4d4d';
                }
            }
        });
    </script>
</body>

</html>