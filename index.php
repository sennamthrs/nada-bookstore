<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once 'auth.php';

function checkUserLogin()
{
  return [
    'isLoggedIn' => isLoggedIn(),
    'user' => isLoggedIn() ? getLoggedUser() : null
  ];
}

$user = getLoggedUser();
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Beranda - NADA BookStore</title>
  <link rel="stylesheet" href="styles.css" />
  <link rel="stylesheet" href="index.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
</head>

<body>
  <!-- Modern Sticky Header -->
  <header class="main-header">
    <div class="header-content">
      <a href="index.php" class="logo">
        <i class="fas fa-book"></i>
        <h1>NADA BookStore</h1>
      </a>

      <button class="mobile-menu-btn">
        <i class="fas fa-bars"></i>
      </button>

      <nav class="main-nav">
        <ul class="nav-links">
          <li><a href="index.php">Beranda</a></li>
          <li><a href="products.php">Produk</a></li>
        </ul>

        <div class="auth-buttons">
          <?php if ($user): ?>
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

  <?php if (isset($_GET['page']) && $_GET['page'] === 'checkout'): ?>
    <div class="breadcrumb">
      <a href="index.php">Beranda</a> /
      <a href="cart.php">Keranjang Belanja</a> /
      <span>Checkout</span>
    </div>
  <?php endif; ?>

  <main>
    <!-- Enhanced Hero Slider Section -->
    <section>
      <div class="hero-slider">
        <button class="slider-btn prev">
          <i class="fas fa-chevron-left"></i>
        </button>
        <button class="slider-btn next">
          <i class="fas fa-chevron-right"></i>
        </button>

        <div class="slider">
          <div class="slide">
            <img src="uploads/slider/slider1.png" alt="Koleksi Buku Terbaru">
            <div class="slide-content">
              <h3>Koleksi Buku Terbaru</h3>
              <p>Temukan buku-buku terlaris di NADA BookStore</p>
              <a href="products.php" class="slide-button">Lihat Sekarang</a>
            </div>
          </div>
          <div class="slide">
            <img src="uploads/slider/slider2.png" alt="Buku Anak-Anak">
            <div class="slide-content">
              <h3>Buku Anak-Anak</h3>
              <p>Koleksi lengkap buku cerita dan edukasi untuk si kecil</p>
              <a href="products.php?search=&category=9" class="slide-button">Jelajahi Koleksi</a>
            </div>
          </div>
        </div>

        <div class="slider-nav">
          <div class="slider-dot active"></div>
          <div class="slider-dot"></div>
        </div>
      </div>
    </section>

    <!-- Product Section with Category Filtering -->
    <section class="products-section">
      <div class="section-title">
        <h2>Koleksi Buku di NADA BookStore</h2>
        <p>Temukan berbagai pilihan buku berkualitas untuk menambah koleksi bacaan Anda</p>
      </div>

      <?php
      require_once 'db.php';

      // Ambil semua kategori yang memiliki produk
      $categories_stmt = $pdo->query("SELECT DISTINCT c.id, c.name 
                                      FROM categories c 
                                      JOIN products p ON c.id = p.category_id 
                                      ORDER BY c.name");
      $categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

      // Ambil 5 produk dari setiap kategori
      $products_by_category = [];
      $all_products = [];

      foreach ($categories as $category) {
        $stmt = $pdo->prepare("SELECT p.*, c.name as category_name 
                                 FROM products p 
                                 JOIN categories c ON p.category_id = c.id 
                                 WHERE p.category_id = ? 
                                 ORDER BY p.id DESC 
                                 LIMIT 5");
        $stmt->execute([$category['id']]);
        $category_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $products_by_category[$category['id']] = $category_products;
        $all_products = array_merge($all_products, $category_products);
      }

      // Ambil produk terbaru untuk tampilan "Semua" (maksimal 20 produk)
      $stmt = $pdo->query("SELECT p.*, c.name as category_name 
                           FROM products p 
                           JOIN categories c ON p.category_id = c.id 
                           ORDER BY p.id DESC 
                           LIMIT 10");
      $all_recent_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
      ?>

      <div class="category-filter">
        <div class="category-pill active" data-category="all">Terbaru</div>
        <?php foreach ($categories as $category): ?>
          <div class="category-pill" data-category="<?= $category['id'] ?>">
            <?= htmlspecialchars($category['name']) ?>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="products-container">
        <!-- Loading indicator -->
        <div class="loading-indicator" style="display: none;">
          <i class="fas fa-spinner fa-spin"></i> Memuat produk...
        </div>

        <!-- Products Grid -->
        <div class="products-grid" id="products-grid">
          <!-- Produk akan dimuat di sini via JavaScript -->
        </div>

        <!-- Empty State -->
        <div class="empty-state" id="empty-state" style="display: none;">
          <i class="fas fa-books fa-3x"></i>
          <p>Tidak ada produk ditemukan untuk kategori ini.</p>
        </div>
      </div>

      <div style="text-align: center; margin-top: 30px;">
        <a href="products.php" class="btn btn-primary">Lihat Semua Produk</a>
      </div>
    </section>

    <!-- Features Section -->
    <section class="features-section">
      <div class="section-title">
        <h2>Mengapa Memilih Kami</h2>
        <p>NADA BookStore menawarkan pengalaman berbelanja buku terbaik dengan berbagai keunggulan</p>
      </div>

      <div class="features-grid">
        <div class="feature-card">
          <div class="feature-icon">
            <i class="fas fa-truck"></i>
          </div>
          <h3>Pengiriman Cepat</h3>
          <p>Nikmati pengiriman cepat ke seluruh Indonesia dengan pilihan kurir terpercaya</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">
            <i class="fas fa-book-open"></i>
          </div>
          <h3>Koleksi Lengkap</h3>
          <p>Ribuan judul buku dari berbagai kategori, dari fiksi hingga pendidikan</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">
            <i class="fas fa-tags"></i>
          </div>
          <h3>Harga Bersaing</h3>
          <p>Dapatkan buku berkualitas dengan harga terbaik dan promo menarik</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">
            <i class="fas fa-headset"></i>
          </div>
          <h3>Layanan Pelanggan</h3>
          <p>Tim kami siap membantu Anda 7 hari seminggu untuk pengalaman belanja terbaik</p>
        </div>
      </div>
    </section>
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

  <!-- JavaScript untuk data produk -->
  <script>
    // Data produk dari PHP
    const productsData = {
      all: <?= json_encode($all_recent_products) ?>,
      <?php foreach ($products_by_category as $cat_id => $products): ?>
                                                      <?= $cat_id ?>: <?= json_encode($products) ?>,
      <?php endforeach; ?>
    };
  </script>

  <script>
    // Enhanced Image Slider Functionality
    document.addEventListener('DOMContentLoaded', function () {
      // Mobile Menu Toggle
      const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
      const navLinks = document.querySelector('.nav-links');

      if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', () => {
          navLinks.style.display = navLinks.style.display === 'flex' ? 'none' : 'flex';
          mobileMenuBtn.classList.toggle('active');
        });
      }

      // Load initial products (Semua kategori)
      loadProducts('all');
    });

    // Function untuk memuat produk berdasarkan kategori
    function loadProducts(categoryId) {
      const productsGrid = document.getElementById('products-grid');
      const loadingIndicator = document.querySelector('.loading-indicator');
      const emptyState = document.getElementById('empty-state');

      // Show loading
      loadingIndicator.style.display = 'block';
      productsGrid.innerHTML = '';
      emptyState.style.display = 'none';

      // Simulate loading delay (bisa dihapus jika tidak perlu)
      setTimeout(() => {
        loadingIndicator.style.display = 'none';

        const products = productsData[categoryId] || [];

        if (products.length === 0) {
          emptyState.style.display = 'block';
          return;
        }

        // Generate HTML untuk produk
        let productsHTML = '';
        products.forEach(product => {
          productsHTML += `
            <div class="product-card">
              <a href="product_detail.php?id=${product.id}">
                <img src="${product.image_url}" alt="${product.name}" />
                <h3>${product.name}</h3>
              </a>
              <span class="category-badge">${product.category_name}</span>
              <p class="price">Rp. ${new Intl.NumberFormat('id-ID').format(product.price)}</p>
            </div>
          `;
        });

        productsGrid.innerHTML = productsHTML;
      }, 300); // 300ms delay untuk efek loading
    }

    // Category Pills Filtering
    const categoryPills = document.querySelectorAll('.category-pill');

    categoryPills.forEach(pill => {
      pill.addEventListener('click', () => {
        // Remove active class from all pills
        categoryPills.forEach(p => p.classList.remove('active'));

        // Add active class to clicked pill
        pill.classList.add('active');

        // Get category ID
        const categoryId = pill.getAttribute('data-category');

        // Load products for selected category
        loadProducts(categoryId);
      });
    });

    // Slider functionality
    let currentSlide = 0;
    const slides = document.querySelectorAll('.slide');
    const dots = document.querySelectorAll('.slider-dot');
    const slider = document.querySelector('.slider');
    const prevBtn = document.querySelector('.slider-btn.prev');
    const nextBtn = document.querySelector('.slider-btn.next');

    function updateSlider() {
      if (slider) {
        slider.style.transform = `translateX(-${currentSlide * 100}%)`;

        dots.forEach((dot, index) => {
          dot.classList.toggle('active', index === currentSlide);
        });
      }
    }

    function nextSlide() {
      currentSlide = (currentSlide + 1) % slides.length;
      updateSlider();
    }

    function prevSlide() {
      currentSlide = (currentSlide - 1 + slides.length) % slides.length;
      updateSlider();
    }

    let slideInterval = setInterval(nextSlide, 5000);

    function resetInterval() {
      clearInterval(slideInterval);
      slideInterval = setInterval(nextSlide, 5000);
    }

    if (dots.length > 0) {
      dots.forEach((dot, index) => {
        dot.addEventListener('click', () => {
          currentSlide = index;
          updateSlider();
          resetInterval();
        });
      });
    }

    if (prevBtn) {
      prevBtn.addEventListener('click', () => {
        prevSlide();
        resetInterval();
      });
    }

    if (nextBtn) {
      nextBtn.addEventListener('click', () => {
        nextSlide();
        resetInterval();
      });
    }

    updateSlider();

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
  </script>
</body>

</html>