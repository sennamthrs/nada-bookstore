<?php
// Pastikan session sudah dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include file auth.php yang berisi fungsi-fungsi otentikasi
require_once '../auth.php';

// Fungsi untuk memeriksa apakah user adalah admin
function requireAdminLogin() {
    if (!isLoggedIn()) {
        // Jika belum login, redirect ke halaman login
        header("Location: ../login.php");
        exit;
    }
    
    // Jika sudah login tapi bukan admin, redirect ke halaman utama
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
        header("Location: ../index.php");
        exit;
    }
}

// Jalankan pengecekan admin
requireAdminLogin();

// Ambil daftar kategori untuk dropdown
$stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil data product untuk daftar product
$stmt = $pdo->query("SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pesan notifikasi dari operasi sebelumnya (jika ada)
$successMsg = $_SESSION['success_msg'] ?? '';
$errorMsg = $_SESSION['error_msg'] ?? '';

// Hapus pesan dari session setelah ditampilkan
unset($_SESSION['success_msg']);
unset($_SESSION['error_msg']);

// Handle submit tambah kategori baru
if (isset($_POST['add_category'])) {
    $newCategory = trim($_POST['category_name'] ?? '');
    if ($newCategory === '') {
        $errorMsg = "Nama kategori tidak boleh kosong.";
    } else {
        // Cek apakah kategori sudah ada
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = ?");
        $stmt->execute([$newCategory]);
        if ($stmt->fetchColumn() > 0) {
            $errorMsg = "Kategori sudah ada.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->execute([$newCategory]);
            $_SESSION['success_msg'] = "Kategori berhasil ditambahkan.";
            header("Location: dashboard.php");
            exit;
        }
    }
}

// Handle submit tambah produk baru
if (isset($_POST['add_product'])) {
    $name = trim($_POST['product_name'] ?? '');
    $desc = trim($_POST['product_desc'] ?? '');
    $price = $_POST['product_price'] ?? '';
    $category_id = $_POST['category_id'] ?? '';

    // Cek file upload
    if (!isset($_FILES['product_image']) || $_FILES['product_image']['error'] !== UPLOAD_ERR_OK) {
        $errorMsg = "Gagal upload gambar. Pastikan file gambar dipilih dengan benar.";
    } else {
        $fileTmpPath = $_FILES['product_image']['tmp_name'];
        $fileName = $_FILES['product_image']['name'];
        $fileSize = $_FILES['product_image']['size'];
        $fileType = $_FILES['product_image']['type'];

        // Periksa ekstensi file valid (misal jpg,jpeg,png,gif)
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($fileExtension, $allowedExtensions)) {
            $errorMsg = "Format file gambar tidak didukung. Gunakan JPG, PNG, atau GIF.";
        } elseif ($name === '' || $desc === '' || !is_numeric($price) || $category_id === '') {
            $errorMsg = "Semua field wajib diisi dengan benar.";
        } else {
            // Tentukan folder upload
            $uploadDir = __DIR__ . '/../uploads/product/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Generate nama file baru unik
            $newFileName = uniqid('shoe_', true) . '.' . $fileExtension;
            $destPath = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $destPath)) {
                // Simpan path relatif ke database
                $imageUrl = 'uploads/product/' . $newFileName;

                $price = floatval($price);
                if ($price <= 0) {
                    $errorMsg = "Harga harus lebih besar dari nol.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO products (name, description, price, category_id, image_url) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $desc, $price, $category_id, $imageUrl]);

                    $_SESSION['success_msg'] = "Produk berhasil ditambahkan.";
                    header("Location: dashboard.php");
                    exit;
                }
            } else {
                $errorMsg = "Gagal memindahkan file gambar ke folder upload.";
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Admin Dashboard - NADA BookStore</title>
<link rel="stylesheet" href="dashboard.css" />
<link rel="stylesheet" href="../styles.css" />
</head>
<body>
<header>
  <h1>Admin Dashboard - NADA BookStore</h1>
  <nav>
    <a href="../products.php">Lihat Toko</a> |
    <a href="../cart.php">Keranjang</a> |
    <a href="../logout.php">Logout</a>
  </nav>
</header>

<div class="admin-container">
    <?php if ($successMsg): ?>
        <div class="alert success-msg" style="width: 100%;"><?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="alert error-msg" style="width: 100%;"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <div class="admin-sidebar">
        <div class="admin-card">
            <h2>
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
                Tambah Kategori Baru
            </h2>
            <form method="POST" action="dashboard.php">
                <div class="form-group">
                    <label for="category_name">Nama Kategori:</label>
                    <input type="text" id="category_name" name="category_name" required />
                </div>
                <button type="submit" name="add_category" class="btn-primary">Tambah Kategori</button>
            </form>
        </div>

        <div class="admin-card">
            <h2>
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="12" y1="8" x2="12" y2="16"></line>
                    <line x1="8" y1="12" x2="16" y2="12"></line>
                </svg>
                Tambah Produk Baru
            </h2>
            <form method="POST" action="dashboard.php" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="product_name">Nama Produk:</label>
                    <input type="text" id="product_name" name="product_name" required />
                </div>

                <div class="form-group">
                    <label for="product_desc">Deskripsi:</label>
                    <textarea id="product_desc" name="product_desc" rows="3" required></textarea>
                </div>

                <div class="form-group">
                    <label for="product_price">Harga (Rupiah):</label>
                    <input type="number" step="0.01" id="product_price" name="product_price" min="0.01" required />
                </div>

                <div class="form-group">
                    <label for="category_id">Kategori:</label>
                    <select id="category_id" name="category_id" required>
                        <option value="">--Pilih kategori--</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="product_image">Upload Gambar Produk:</label>
                    <input type="file" id="product_image" name="product_image" accept="image/jpeg,image/png,image/gif" required />
                </div>
                        
                <button type="submit" name="add_product" class="btn-primary">Tambah Produk</button>
            </form>
        </div>
    </div>
    
    <div class="admin-content">
        <div class="table-container">
            <div class="table-header">
                <h2>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                        <line x1="12" y1="22.08" x2="12" y2="12"></line>
                    </svg>
                    Daftar Produk
                </h2>
            </div>
            
            <div class="table-responsive">
                <?php if (empty($products)): ?>
                    <div class="empty-state">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <path d="M8 15h8M9 9h.01M15 9h.01"></path>
                        </svg>
                        <p>Belum ada produk. Silakan tambahkan produk pertama Anda.</p>
                    </div>
                <?php else: ?>
                    <table class="product-table">
                        <thead>
                            <tr>
                                <th class="col-id">ID</th>
                                <th class="col-img">Gambar</th>
                                <th>Nama Produk</th>
                                <th>Kategori</th>
                                <th>Harga</th>
                                <th class="col-actions">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td class="col-id"><?= $product['id'] ?></td>
                                    <td class="col-img">
                                        <?php if (!empty($product['image_url'])): ?>
                                            <img src="../<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="product-image-preview" />
                                        <?php else: ?>
                                            <div class="no-image">No Image</div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($product['name']) ?></td>
                                    <td><span class="category-badge"><?= htmlspecialchars($product['category_name']) ?></span></td>
                                    <td class="price">Rp. <?= number_format($product['price'], 0) ?></td>
                                    <td class="col-actions">
                                        <div class="action-buttons">
                                            <a href="edit_product.php?id=<?= $product['id'] ?>" class="btn-action btn-edit">Edit</a>
                                            <a href="delete_product.php?id=<?= $product['id'] ?>" class="btn-action btn-delete" 
                                                onclick="return confirm('Apakah Anda yakin ingin menghapus produk ini?')">
                                                Hapus
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>