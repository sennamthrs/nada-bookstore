<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Include file auth.php yang berisi fungsi-fungsi otentikasi
require_once 'auth.php';

// Ganti requireAdminLogin dengan fungsi yang tidak memaksa
function checkUserLogin()
{
  return [
    'isLoggedIn' => isLoggedIn(),
    'user' => isLoggedIn() ? getLoggedUser() : null
  ];
}

$user = getLoggedUser();

// Cek jika user belum login
$loginRequired = false;
$loginMessage = '';

$product_id = $_GET['id'];

$stmt = $pdo->prepare("SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
  header('Location: products.php');
  exit;
}

$errorMsg = '';
$successMsg = '';

// Handle form tambah keranjang POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quantity'])) {
  // Cek jika user belum login
  if (!$user) {
    $loginRequired = true;
    $loginMessage = 'Silakan login terlebih dahulu untuk menambahkan produk ke keranjang.';
  } else {
    $qty = intval($_POST['quantity']);
    if ($qty < 1) {
      $errorMsg = 'Quantity harus minimal 1.';
    } else {
      // Simpan ke keranjang session
      if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
      }
      // Tambahkan produk ke cart session
      $found = false;
      foreach ($_SESSION['cart'] as &$item) {
        if ($item['product_id'] == $product_id) {
          $item['quantity'] += $qty;
          $found = true;
          break;
        }
      }
      if (!$found) {
        $_SESSION['cart'][] = ['product_id' => $product_id, 'quantity' => $qty];
      }
      $successMsg = 'Produk berhasil ditambahkan ke keranjang.';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Detail Produk - <?= htmlspecialchars($product['name']) ?></title>
  <link rel="stylesheet" href="styles.css" />
  <link rel="stylesheet" href="product-detail-style.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <style>
    .login-alert {
      background-color: #fff3cd;
      color: #856404;
      padding: 15px;
      margin-bottom: 20px;
      border-radius: 5px;
      border-left: 5px solid #ffeeba;
      display: flex;
      align-items: center;
    }

    .login-alert i {
      font-size: 24px;
      margin-right: 15px;
    }

    .login-alert-content {
      flex: 1;
    }

    .login-alert .alert-actions {
      margin-top: 10px;
    }

    .login-alert .alert-actions .btn {
      margin-right: 10px;
      display: inline-flex;
      align-items: center;
    }

    .login-alert .alert-actions .btn i {
      font-size: 14px;
      margin-right: 5px;
    }
  </style>
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
          <li><a href="products.php">Produk</a></li>
        </ul>

        <div class="auth-buttons">
          <?php if ($user): ?>
            <!-- Jika user sudah login -->
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

          <?php else: ?>
            <!-- Jika user belum login -->
            <a href="login.php" class="auth-btn login-btn">
              <i class="fas fa-sign-in-alt"></i> Masuk
            </a>

            <a href="register.php" class="auth-btn register-btn">
              <i class="fas fa-user-plus"></i> Daftar
            </a>

          <?php endif; ?>
        </div>
      </nav>
    </div>
  </header>

  <!-- Modern Breadcrumb Navigation -->
  <div class="breadcrumb-container">
    <div class="container">
      <ul class="breadcrumb">
        <li><a href="index.php">Beranda</a></li>
        <li><a href="products.php">Produk</a></li>
        <li><a
            href="products.php?category=<?= $product['category_id'] ?>"><?= htmlspecialchars($product['category_name']) ?></a>
        </li>
        <li class="active"><?= htmlspecialchars($product['name']) ?></li>
      </ul>
    </div>
  </div>

  <main>
    <div class="container">
      <div class="product-detail">
        <div class="product-detail-image">
          <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" />

          <!-- Additional Product Images (if available) -->
          <div class="product-thumbnails">
            <div class="thumbnail active">
              <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="Thumbnail 1" />
            </div>
            <!-- Uncomment and duplicate this for multiple images
                    <div class="thumbnail">
                        <img src="path_to_thumbnail_2" alt="Thumbnail 2" />
                    </div>
                    <div class="thumbnail">
                        <img src="path_to_thumbnail_3" alt="Thumbnail 3" />
                    </div>
                    -->
          </div>
        </div>

        <div class="product-detail-info">
          <h1><?= htmlspecialchars($product['name']) ?></h1>

          <div class="product-meta">
            <span class="category-badge"><?= htmlspecialchars($product['category_name']) ?></span>
          </div>

          <div class="price">Rp. <?= number_format($product['price'], 0, ',', '.') ?></div>
          <div class="stock-display">
            <?php
            $stock = (int) $product['stock'];

            if ($stock <= 0): ?>
              <div class="stock-status out-of-stock">
                <i class="fas fa-times-circle"></i>
                <span>Stok Habis</span>
              </div>
            <?php elseif ($stock <= 5): ?>
              <div class="stock-status low-stock">
                <i class="fas fa-exclamation-triangle"></i>
                <span>Sisa <?= $stock ?> unit</span>
              </div>
            <?php else: ?>
              <div class="stock-status in-stock">
                <i class="fas fa-check-circle"></i>
                <span>Stok: <?= $stock ?> unit</span>
              </div>
            <?php endif; ?>
          </div>
          <div class="product-description">
            <h3>Deskripsi Produk</h3>
            <div class="description-content" id="descriptionContent">
              <div class="description-text" id="descriptionText">
                <?= nl2br(htmlspecialchars($product['description'])) ?>
              </div>
              <div class="description-fade" id="descriptionFade"></div>
              <button type="button" class="read-more-btn" id="readMoreBtn">
                <span class="read-more-text">Baca</span>
                <i class="fas fa-chevron-down read-more-icon"></i>
              </button>
            </div>
          </div>

          <style>
            /* Read More Feature Styles */
            .product-description {
              margin-bottom: 25px;
            }

            .product-description h3 {
              margin-bottom: 15px;
              color: #333;
              font-size: 18px;
              font-weight: 600;
              display: flex;
              align-items: center;
              gap: 8px;
            }

            .product-description h3::before {
              content: '';
              width: 4px;
              height: 20px;
              background: linear-gradient(135deg, #667eea, #764ba2);
              border-radius: 2px;
            }

            .description-content {
              position: relative;
              background: #f8f9fa;
              border-radius: 12px;
              padding: 20px;
              border: 1px solid #e9ecef;
            }

            .description-text {
              line-height: 1.7;
              color: #555;
              font-size: 14px;
              text-align: justify;
              max-height: 120px;
              /* Batasi tinggi awal - sekitar 4-5 baris */
              overflow: hidden;
              transition: max-height 0.5s ease;
              position: relative;
            }

            .description-text.expanded {
              max-height: none;
            }

            .description-fade {
              position: absolute;
              bottom: 50px;
              left: 0;
              right: 0;
              height: 40px;
              background: linear-gradient(transparent, #f8f9fa);
              pointer-events: none;
              opacity: 1;
              transition: opacity 0.3s ease;
            }

            .description-fade.hidden {
              opacity: 0;
            }

            .read-more-btn {
              background: none;
              border: none;
              color: white;
              font-weight: 600;
              font-size: 14px;
              cursor: pointer;
              margin-top: 15px;
              padding: 8px 0;
              display: flex;
              align-items: center;
              gap: 8px;
              transition: all 0.3s ease;
              width: 100%;
              justify-content: center;
            }

            .read-more-btn:hover {
              color: var(--primary-color);
              transform: translateY(-1px);
            }

            .read-more-btn:active {
              transform: translateY(0);
            }

            .read-more-icon {
              transition: transform 0.3s ease;
              font-size: 12px;
            }

            .read-more-btn.expanded .read-more-icon {
              transform: rotate(180deg);
            }

            .read-more-btn.expanded .read-more-text::after {
              content: ' Sembunyikan';
            }

            .read-more-btn:not(.expanded) .read-more-text::after {
              content: ' Selengkapnya';
            }

            /* Animation untuk smooth transition */
            @keyframes fadeInUp {
              from {
                opacity: 0;
                transform: translateY(10px);
              }

              to {
                opacity: 1;
                transform: translateY(0);
              }
            }

            .description-text.expanding {
              animation: fadeInUp 0.5s ease;
            }

            /* Responsive adjustments */
            @media (max-width: 768px) {
              .description-content {
                padding: 15px;
                border-radius: 8px;
              }

              .description-text {
                font-size: 13px;
                max-height: 100px;
                /* Lebih kecil di mobile */
              }

              .product-description h3 {
                font-size: 16px;
              }
            }

            /* Dark mode support (jika diperlukan) */
            @media (prefers-color-scheme: dark) {
              .description-content {
                background: #2d3748;
                border-color: #4a5568;
              }

              .description-text {
                color: #e2e8f0;
              }

              .description-fade {
                background: linear-gradient(transparent, #2d3748);
              }
            }

            /* Styling untuk konten yang panjang */
            .description-text p {
              margin-bottom: 12px;
            }

            .description-text p:last-child {
              margin-bottom: 0;
            }

            /* Enhanced styling untuk tombol */
            .read-more-btn {
              position: relative;
              overflow: hidden;
            }

            .read-more-btn::before {
              content: '';
              position: absolute;
              top: 0;
              left: -100%;
              width: 100%;
              height: 100%;
              background: linear-gradient(90deg, transparent, rgba(102, 126, 234, 0.1), transparent);
              transition: left 0.5s ease;
            }

            .read-more-btn:hover::before {
              left: 100%;
            }

            /* Loading state untuk smooth transition */
            .description-loading {
              opacity: 0.7;
              pointer-events: none;
            }

            .description-loading .read-more-btn {
              cursor: not-allowed;
            }
          </style>


          <?php if ($loginRequired): ?>
            <div class="login-alert">
              <i class="fas fa-exclamation-circle"></i>
              <div class="login-alert-content">
                <div><?= htmlspecialchars($loginMessage) ?></div>
                <div class="alert-actions">
                  <a href="login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn-primary btn-sm">
                    <i class="fas fa-sign-in-alt"></i> Login Sekarang
                  </a>
                  <a href="register.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-user-plus"></i> Daftar
                  </a>
                </div>
              </div>
            </div>
          <?php elseif ($errorMsg): ?>
            <div class="error-msg">
              <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($errorMsg) ?>
            </div>
          <?php elseif ($successMsg): ?>
            <div class="success-msg-cart">
              <i class="fas fa-check-circle"></i> <?= htmlspecialchars($successMsg) ?>
              <div class="cart-action-btns">
                <a href="cart.php" class="btn btn-primary btn-sm">
                  <i class="fas fa-shopping-cart"></i> Lihat Keranjang
                </a>
                <a href="products.php" class="btn btn-secondary btn-sm">
                  <i class="fas fa-arrow-left"></i> Lanjut Belanja
                </a>
              </div>
            </div>
          <?php endif; ?>

          <form method="POST" action="product_detail.php?id=<?= $product['id'] ?>" class="add-to-cart-form">
            <div class="quantity-selector">
              <label for="quantity">Jumlah:</label>
              <div class="quantity-input-group">
                <button type="button" class="quantity-btn minus-btn">
                  <i class="fas fa-minus"></i>
                </button>
                <input type="number" id="quantity" name="quantity" value="1" min="1" required />
                <button type="button" class="quantity-btn plus-btn">
                  <i class="fas fa-plus"></i>
                </button>
              </div>
            </div>

            <button type="submit" class="btn btn-primary add-to-cart-btn">
              <i class="fas fa-shopping-cart"></i> Tambah ke Keranjang
            </button>
          </form>

          <div class="product-meta-info">
            <div class="meta-item">
              <i class="fas fa-shield-alt"></i>
              <span>Garansi Produk Asli</span>
            </div>
            <div class="meta-item">
              <i class="fas fa-truck"></i>
              <span>Pengiriman Cepat</span>
            </div>
            <div class="meta-item">
              <i class="fas fa-undo"></i>
              <span>30 Hari Pengembalian</span>
            </div>
          </div>
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

  <!-- JavaScript for Product Detail Page -->
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      // Mobile Menu Toggle
      const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
      const navLinks = document.querySelector('.nav-links');

      if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', function () {
          navLinks.style.display = navLinks.style.display === 'flex' ? 'none' : 'flex';
          mobileMenuBtn.classList.toggle('active');
        });
      }

      // Disable form jika stok habis
      document.addEventListener('DOMContentLoaded', function () {
        const addToCartForm = document.querySelector('.add-to-cart-form');
        const addToCartBtn = document.querySelector('.add-to-cart-btn');

        if (addToCartForm) {
          addToCartForm.style.opacity = '0.5';
          addToCartForm.style.pointerEvents = 'none';
        }

        if (addToCartBtn) {
          addToCartBtn.disabled = true;
          addToCartBtn.innerHTML = '<i class="fas fa-times-circle"></i> Stok Habis';
          addToCartBtn.style.background = '#e2e8f0';
          addToCartBtn.style.color = '#a0aec0';
          addToCartBtn.style.cursor = 'not-allowed';
        }
      });

      // Baca Selengkapnya untuk Diskripsi Produk
      const descriptionText = document.getElementById('descriptionText');
      const descriptionFade = document.getElementById('descriptionFade');
      const readMoreBtn = document.getElementById('readMoreBtn');

      console.log('Description elements:', { descriptionText, descriptionFade, readMoreBtn }); // Debug

      if (descriptionText && descriptionFade && readMoreBtn) {
        // Cek apakah deskripsi perlu dipotong
        function checkDescriptionHeight() {
          // Temporarily remove max-height to measure full height
          const originalMaxHeight = descriptionText.style.maxHeight;
          descriptionText.style.maxHeight = 'none';

          const textHeight = descriptionText.scrollHeight;
          const maxHeight = window.innerWidth <= 768 ? 100 : 120;

          console.log('Text height:', textHeight, 'Max height:', maxHeight); // Debug

          if (textHeight <= maxHeight) {
            // Jika teks pendek, sembunyikan tombol dan fade
            readMoreBtn.style.display = 'none';
            descriptionFade.style.display = 'none';
            descriptionText.style.maxHeight = 'none';
            console.log('Text is short, hiding read more button'); // Debug
          } else {
            // Jika teks panjang, tampilkan tombol dan fade
            readMoreBtn.style.display = 'flex';
            descriptionFade.style.display = 'block';
            descriptionText.style.maxHeight = maxHeight + 'px';
            console.log('Text is long, showing read more button'); // Debug
          }
        }

        // Jalankan pengecekan saat halaman dimuat
        setTimeout(checkDescriptionHeight, 100); // Delay sedikit untuk memastikan CSS loaded

        // Event listener untuk tombol read more
        readMoreBtn.addEventListener('click', function (e) {
          e.preventDefault();
          const isExpanded = this.classList.contains('expanded');

          console.log('Read more clicked, isExpanded:', isExpanded); // Debug

          if (isExpanded) {
            // Tutup deskripsi
            const maxHeight = window.innerWidth <= 768 ? 100 : 120;
            descriptionText.classList.remove('expanded');
            descriptionText.style.maxHeight = maxHeight + 'px';
            descriptionFade.classList.remove('hidden');
            descriptionFade.style.display = 'block';
            this.classList.remove('expanded');

            // Smooth scroll ke atas deskripsi
            descriptionText.scrollIntoView({
              behavior: 'smooth',
              block: 'start'
            });

            console.log('Description collapsed'); // Debug

          } else {
            // Buka deskripsi
            descriptionText.classList.add('expanded', 'expanding');
            const fullHeight = descriptionText.scrollHeight;
            descriptionText.style.maxHeight = fullHeight + 'px';
            descriptionFade.classList.add('hidden');
            this.classList.add('expanded');

            console.log('Description expanded to height:', fullHeight); // Debug

            // Hapus class animasi setelah selesai dan set max-height ke none
            setTimeout(() => {
              descriptionText.classList.remove('expanding');
              descriptionText.style.maxHeight = 'none';
            }, 500);
          }
        });

        // Responsive handling
        function handleResize() {
          // Re-check height setelah resize hanya jika tidak dalam keadaan expanded
          if (!readMoreBtn.classList.contains('expanded')) {
            checkDescriptionHeight();
          }
        }

        // Listen untuk resize window dengan debounce
        let resizeTimeout;
        window.addEventListener('resize', function () {
          clearTimeout(resizeTimeout);
          resizeTimeout = setTimeout(handleResize, 250);
        });

        // Keyboard accessibility
        readMoreBtn.addEventListener('keydown', function (e) {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            this.click();
          }
        });
      } else {
        console.error('Read more elements not found!'); // Debug
      }

      // Quantity Input
      const quantityInput = document.getElementById('quantity');
      const minusBtn = document.querySelector('.minus-btn');
      const plusBtn = document.querySelector('.plus-btn');

      if (minusBtn && plusBtn && quantityInput) {
        minusBtn.addEventListener('click', function () {
          let value = parseInt(quantityInput.value, 10);
          value = value > 1 ? value - 1 : 1;
          quantityInput.value = value;
        });

        plusBtn.addEventListener('click', function () {
          let value = parseInt(quantityInput.value, 10);
          value++;
          quantityInput.value = value;
        });
      }

      // Thumbnails functionality
      const thumbnails = document.querySelectorAll('.thumbnail');
      const mainImage = document.querySelector('.product-detail-image > img');

      if (thumbnails.length > 0 && mainImage) {
        thumbnails.forEach(thumbnail => {
          thumbnail.addEventListener('click', function () {
            // Remove active class from all thumbnails
            thumbnails.forEach(t => t.classList.remove('active'));

            // Add active class to clicked thumbnail
            this.classList.add('active');

            // Update main image src
            const imgSrc = this.querySelector('img').getAttribute('src');
            mainImage.setAttribute('src', imgSrc);
          });
        });
      }

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