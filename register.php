<?php
require_once 'db.php';

$errorMsg = '';
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nama = isset($_POST['nama']) ? trim($_POST['nama']) : '';
  $no_telepon = isset($_POST['no_telepon']) ? trim($_POST['no_telepon']) : '';
  $email = filter_var(isset($_POST['email']) ? trim($_POST['email']) : '', FILTER_VALIDATE_EMAIL);
  $password = isset($_POST['password']) ? $_POST['password'] : '';
  $password_confirm = isset($_POST['password_confirm']) ? $_POST['password_confirm'] : '';

  if ($nama === '') {
    $errorMsg = "Nama lengkap wajib diisi.";
  } elseif (!$email) {
    $errorMsg = "Masukkan email valid.";
  } elseif (empty($password) || strlen($password) < 8) {
    $errorMsg = "Password minimal 8 karakter.";
  } elseif ($password !== $password_confirm) {
    $errorMsg = "Konfirmasi password tidak cocok.";
  } else {
    // Cek email sudah terdaftar belum
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() > 0) {
      $errorMsg = "Email sudah terdaftar.";
    } else {
      // Masukkan user baru sebagai member
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $stmt = $pdo->prepare("INSERT INTO users (nama, no_telepon, email, password, role) VALUES (?, ?, ?, ?, 'member')");
      $stmt->execute([$nama, $no_telepon ?: null, $email, $hash]);
      $successMsg = "Pendaftaran berhasil! Silakan <a href='login.php'>login</a>.";
    }
  }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Register Member - NADA BookStore</title>
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

  <!-- Registration Form Container -->
  <div class="auth-container">
    <div class="card-title" style="text-align: center;">
      <h1 style="text-align: center; width: 100%;">
        <span style="color: var(--primary-color); margin-right: 10px;">
          <i class="fas fa-user-plus"></i>
        </span>
        Daftar
      </h1>
    </div>

    <?php if ($errorMsg): ?>
      <div class="error-msg">
        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($errorMsg) ?>
      </div>
    <?php endif; ?>

    <?php if ($successMsg): ?>
      <div class="success-msg">
        <i class="fas fa-check-circle"></i> <?= $successMsg ?>
      </div>
    <?php else: ?>
      <form method="POST" action="register.php" class="auth-form">
        <div class="form-group">
          <label for="nama">
            <i class="fas fa-user"></i> Nama Lengkap:
          </label>
          <input type="text" name="nama" id="nama" class="form-control" required
            value="<?= isset($_POST['nama']) ? htmlspecialchars($_POST['nama']) : '' ?>" />
        </div>

        <div class="form-group">
          <label for="no_telepon">
            <i class="fas fa-phone"></i> No. Telepon:
          </label>
          <input type="text" name="no_telepon" id="no_telepon" class="form-control"
            value="<?= isset($_POST['no_telepon']) ? htmlspecialchars($_POST['no_telepon']) : '' ?>" />
        </div>

        <div class="form-group">
          <label for="email">
            <i class="fas fa-envelope"></i> Email:
          </label>
          <input type="email" name="email" id="email" class="form-control" required
            value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" />
        </div>

        <div class="form-group">
          <label for="password">
            <i class="fas fa-lock"></i> Password (minimal 8 karakter):
          </label>
          <div class="input-wrapper">
            <input type="password" id="password" name="password" class="form-control" required />
            <button type="button" class="toggle-password" data-target="password">
              <i class="fas fa-eye"></i>
            </button>
          </div>
        </div>

        <div class="form-group">
          <label for="password_confirm">
            <i class="fas fa-check-circle"></i> Konfirmasi Password:
          </label>
          <div class="input-wrapper">
            <input type="password" id="password_confirm" name="password_confirm" class="form-control" required />
            <button type="button" class="toggle-password" data-target="password_confirm">
              <i class="fas fa-eye"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%;">Daftar Sekarang</button>

        <div class="auth-links">
          Sudah punya akun? <a href="login.php">Login di sini</a>
        </div>
      </form>
    <?php endif; ?>
  </div>

  <!-- Password strength indicator -->
  <style>
    .password-strength {
      height: 5px;
      margin-top: 5px;
      width: 100%;
      background: #f0f0f0;
      border-radius: 3px;
      overflow: hidden;
    }

    .password-strength-meter {
      height: 100%;
      width: 0;
      transition: width 0.3s, background 0.3s;
    }

    .weak {
      width: 25%;
      background: #f44336;
    }

    .medium {
      width: 50%;
      background: #ff9800;
    }

    .good {
      width: 75%;
      background: #2196F3;
    }

    .strong {
      width: 100%;
      background: #4CAF50;
    }

    .password-feedback {
      margin-top: 5px;
      font-size: 0.75rem;
      color: #666;
    }
  </style>

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

  <!-- Mobile Menu and Password Strength JavaScript -->
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      // Mobile Menu Toggle
      const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
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

      // Password Strength Indicator
      const passwordInput = document.getElementById('password');
      if (passwordInput) {
        // Tambahkan indikator kekuatan password setelah input password
        const strengthDiv = document.createElement('div');
        strengthDiv.className = 'password-strength';
        const strengthMeter = document.createElement('div');
        strengthMeter.className = 'password-strength-meter';
        strengthDiv.appendChild(strengthMeter);

        const feedbackDiv = document.createElement('div');
        feedbackDiv.className = 'password-feedback';

        passwordInput.parentNode.insertBefore(strengthDiv, passwordInput.nextSibling);
        passwordInput.parentNode.insertBefore(feedbackDiv, strengthDiv.nextSibling);

        passwordInput.addEventListener('input', function () {
          const password = this.value;
          let strength = 0;
          let feedback = '';

          // Panjang minimal
          if (password.length >= 8) {
            strength += 1;
          } else {
            feedback = 'Password terlalu pendek';
          }

          // Kombinasi huruf besar dan kecil
          if (password.match(/[a-z]/) && password.match(/[A-Z]/)) {
            strength += 1;
          } else if (password.length > 0) {
            feedback = feedback || 'Tambahkan huruf besar dan kecil';
          }

          // Angka
          if (password.match(/\d/)) {
            strength += 1;
          } else if (password.length > 0) {
            feedback = feedback || 'Tambahkan angka';
          }

          // Karakter khusus
          if (password.match(/[^a-zA-Z\d]/)) {
            strength += 1;
          } else if (password.length > 0) {
            feedback = feedback || 'Tambahkan karakter khusus';
          }

          // Update indikator kekuatan
          strengthMeter.className = 'password-strength-meter';
          switch (strength) {
            case 0:
            case 1:
              strengthMeter.classList.add('weak');
              feedbackDiv.textContent = feedback || 'Password lemah';
              break;
            case 2:
              strengthMeter.classList.add('medium');
              feedbackDiv.textContent = feedback || 'Password sedang';
              break;
            case 3:
              strengthMeter.classList.add('good');
              feedbackDiv.textContent = feedback || 'Password bagus';
              break;
            case 4:
              strengthMeter.classList.add('strong');
              feedbackDiv.textContent = 'Password kuat';
              break;
          }
        });
      }
    });
  </script>
</body>

</html>