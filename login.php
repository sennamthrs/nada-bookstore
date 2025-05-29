<?php
require_once 'auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $role = 'member'; // role tetap
  $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
  $password = $_POST['password'] ?? '';

  if (!$email) {
    $error = "Please enter a valid email.";
  } elseif (empty($password)) {
    $error = "Please enter your password.";
  } else {
    if (loginUser($email, $password, $role)) {
      header('Location: index.php'); // halaman member
      exit;
    } else {
      $error = "Invalid email or password.";
    }
  }
} elseif (isLoggedIn()) {
  $user = getLoggedUser();
  if ($user['role'] === 'member') {
    header('Location: index.php');
  } else {
    header('Location: admin/dashboard.php');
  }
  exit;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Member Login - NADA BookStore</title>
  <link rel="stylesheet" href="styles.css" />
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
          <li><a href="products.php">Produk</a></li>
        </ul>

        <div class="auth-buttons">

          <!-- Jika user belum login -->
          <a href="login.php" class="auth-btn login-btn">
            <i class="fas fa-sign-in-alt"></i> Masuk
          </a>

          <a href="register.php" class="auth-btn register-btn">
            <i class="fas fa-user-plus"></i> Daftar
          </a>
        </div>
    </div>
    </div>
    </nav>
  </header>

  <!-- Login Form Container -->
  <div class="auth-container">
    <div class="card-title" style="text-align: center;">
      <h1 style="text-align: center; width: 100%;">
        <span style="color: var(--primary-color); margin-right: 10px;">
          <i class="fas fa-sign-in-alt"></i>
        </span>
        Masuk
      </h1>
    </div>

    <?php if ($error): ?>
      <div class="error-msg">
        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" class="auth-form">
      <div class="form-group">
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" class="form-control" required
          value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" />
      </div>

      <div class="form-group">
        <label for="password">Password:</label>
        <div class="input-wrapper">
          <input type="password" id="password" name="password" class="form-control" required />
          <button type="button" class="toggle-password" data-target="password">
            <i class="fas fa-eye"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>

      <div class="auth-links">
        Belum punya akun? <a href="register.php">Daftar di sini</a>
      </div>
    </form>
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
          <li><i class="fas fa-map-marker-alt"></i> Jl. Raya Tengah, Kelurahan Gedong, Pasar Rebo, Jakarta Timur 13760
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

  <!-- Mobile Menu JavaScript -->
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
      const PasswordInput = document.getElementById('password');
      const navLinks = document.querySelector('.nav-links');
      const body = document.body;

      // Tambahkan property display secara default jika belum ada
      if (window.innerWidth <= 992) {
        navLinks.style.display = 'none';
      }

      // Enhanced Toggle Password dengan visual feedback
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

      // Toggle menu saat tombol diklik
      if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', function (e) {
          e.preventDefault();

          if (navLinks.style.display === 'none' || navLinks.style.display === '') {
            navLinks.style.display = 'flex';
            navLinks.classList.add('menu-open');
            mobileMenuBtn.classList.add('active');
            body.classList.add('menu-active');
          } else {
            navLinks.style.display = 'none';
            navLinks.classList.remove('menu-open');
            mobileMenuBtn.classList.remove('active');
            body.classList.remove('menu-active');
          }
        });
      }

      // Tutup menu saat mengklik di luar menu
      document.addEventListener('click', function (e) {
        if (window.innerWidth <= 992) {
          if (!navLinks.contains(e.target) &&
            !mobileMenuBtn.contains(e.target) &&
            navLinks.style.display === 'flex') {
            navLinks.style.display = 'none';
            navLinks.classList.remove('menu-open');
            mobileMenuBtn.classList.remove('active');
            body.classList.remove('menu-active');
          }
        }
      });

      // Tangani perubahan ukuran layar
      window.addEventListener('resize', function () {
        if (window.innerWidth > 992) {
          // Desktop view
          navLinks.style.display = 'flex';
          navLinks.classList.remove('menu-open');
          mobileMenuBtn.classList.remove('active');
          body.classList.remove('menu-active');
        } else {
          // Mobile view - hide menu by default if not already open
          if (!navLinks.classList.contains('menu-open')) {
            navLinks.style.display = 'none';
          }
        }
      });
    });
  </script>
</body>

</html>