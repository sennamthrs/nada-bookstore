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

// Dapatkan kategori untuk filter dropdown
$stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filterCategoryId = $_GET['category'] ?? '';
$searchKeyword = $_GET['search'] ?? '';

// Query produk berdasarkan filter dan pencarian
$sql = "SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.id";
$params = [];
$conditions = [];

// Tambahkan kondisi pencarian jika ada keyword
if (!empty($searchKeyword)) {
  $conditions[] = "(p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)";
  $searchParam = '%' . $searchKeyword . '%';
  $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
}

// Tambahkan kondisi filter kategori jika dipilih
if ($filterCategoryId && is_numeric($filterCategoryId)) {
  $conditions[] = "p.category_id = ?";
  $params[] = $filterCategoryId;
}

// Gabungkan kondisi WHERE jika ada
if (!empty($conditions)) {
  $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY p.name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$user = getLoggedUser();

?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Produk - NADA BookStore</title>
  <link rel="stylesheet" href="styles.css" />
  <link rel="stylesheet" href="product-style.css" />
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
        </div>
    </div>
    </nav>
    </div>
  </header>

  <main>
    <!-- Page Banner -->
    <section class="page-banner">
      <div class="banner-content">
        <h1>Jelajahi Koleksi Buku Kami</h1>
        <p>Temukan berbagai macam buku berkualitas dengan harga terbaik untuk menambah koleksi perpustakaan pribadi Anda
        </p>
      </div>
    </section>

    <div class="products-container">
      <!-- Search and Filter Section -->
      <div class="search-filter-container">
        <!-- Search Section -->
        <div class="search-section">
          <div class="filter-title">
            <i class="fas fa-search"></i>
            <h2>Cari Produk</h2>
          </div>
          <form method="GET" action="products.php" class="search-form">
            <input type="text" name="search" class="search-input"
              placeholder="Cari berdasarkan nama buku, deskripsi, atau kategori..."
              value="<?= htmlspecialchars($searchKeyword) ?>" />
            <input type="hidden" name="category" value="<?= htmlspecialchars($filterCategoryId) ?>">
            <button type="submit" class="search-btn">
              <i class="fas fa-search"></i>
            </button>
          </form>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
          <div class="filter-title">
            <i class="fas fa-filter"></i>
            <h2>Filter Kategori</h2>
          </div>
          <form method="GET" action="products.php" class="category-filter-form">
            <input type="hidden" name="search" value="<?= htmlspecialchars($searchKeyword) ?>">
            <select name="category" id="category-filter" onchange="this.form.submit()">
              <option value="">-- Semua Kategori --</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= ($filterCategoryId == $cat['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($cat['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <noscript><button type="submit" class="btn-filter">Filter</button></noscript>
          </form>
        </div>
      </div>

      <!-- Search Results Info -->
      <?php if (!empty($searchKeyword) || !empty($filterCategoryId)): ?>
        <div class="search-results-info">
          <strong>Hasil Pencarian:</strong>
          <?php if (!empty($searchKeyword)): ?>
            Menampilkan hasil untuk "<em><?= htmlspecialchars($searchKeyword) ?></em>"
          <?php endif; ?>
          <?php if (!empty($filterCategoryId)): ?>
            <?php
            $selectedCategory = array_filter($categories, function ($cat) use ($filterCategoryId) {
              return $cat['id'] == $filterCategoryId;
            });
            $categoryName = !empty($selectedCategory) ? reset($selectedCategory)['name'] : '';
            ?>
            <?= !empty($searchKeyword) ? ' dalam kategori ' : 'Kategori: ' ?>
            "<em><?= htmlspecialchars($categoryName) ?></em>"
          <?php endif; ?>
          - Ditemukan <?= count($products) ?> produk
          <a href="products.php" class="clear-filters">
            <i class="fas fa-times"></i> Hapus Filter
          </a>
        </div>
      <?php endif; ?>

      <!-- Products Section -->
      <section class="products-section">
        <?php if (count($products) === 0): ?>
          <div class="empty-state">
            <i class="fas fa-search"></i>
            <?php if (!empty($searchKeyword) || !empty($filterCategoryId)): ?>
              <p>Tidak ada produk yang sesuai dengan pencarian Anda.</p>
              <p>Coba gunakan kata kunci yang berbeda atau hapus filter.</p>
            <?php else: ?>
              <p>Tidak ada produk ditemukan.</p>
            <?php endif; ?>
            <a href="products.php" class="btn btn-primary">Lihat Semua Produk</a>
          </div>
        <?php else: ?>
          <div class="products-grid">
            <?php foreach ($products as $product): ?>
              <div class="product-card">
                <a href="product_detail.php?id=<?= $product['id'] ?>" class="product-link">
                  <div class="product-image">
                    <img src="<?= htmlspecialchars($product['image_url']) ?>"
                      alt="<?= htmlspecialchars($product['name']) ?>" />
                  </div>
                  <div class="product-info">
                    <h3 class="product-title"><?= htmlspecialchars($product['name']) ?></h3>
                    <span class="category-badge"><?= htmlspecialchars($product['category_name']) ?></span>
                    <div class="product-price">Rp. <?= number_format($product['price'], 0) ?></div>
                  </div>
                </a>
                <div class="product-actions">
                  <a href="product_detail.php?id=<?= $product['id'] ?>" class="btn-view">Detail Buku</a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
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
  <script src="mobile-menu.js"></script>
</body>

</html>