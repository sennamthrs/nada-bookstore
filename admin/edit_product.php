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

// Cek apakah ada ID produk yang valid
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_msg'] = "ID produk tidak valid.";
    header("Location: dashboard.php");
    exit;
}

$product_id = $_GET['id'];

// Ambil data produk yang akan diedit
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    $_SESSION['error_msg'] = "Produk tidak ditemukan.";
    header("Location: dashboard.php");
    exit;
}

// Ambil daftar kategori untuk dropdown
$stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$errorMsg = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['product_name'] ?? '');
    $desc = trim($_POST['product_desc'] ?? '');
    $price = $_POST['product_price'] ?? '';
    $category_id = $_POST['category_id'] ?? '';
    
    if ($name === '' || $desc === '' || !is_numeric($price) || $category_id === '') {
        $errorMsg = "Semua field wajib diisi dengan benar.";
    } else {
        $price = floatval($price);
        if ($price <= 0) {
            $errorMsg = "Harga harus lebih besar dari nol.";
        } else {
            // Cek apakah ada file gambar baru
            if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                // Proses upload gambar baru
                $fileTmpPath = $_FILES['product_image']['tmp_name'];
                $fileName = $_FILES['product_image']['name'];
                $fileSize = $_FILES['product_image']['size'];
                $fileType = $_FILES['product_image']['type'];

                // Periksa ekstensi file valid
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                if (!in_array($fileExtension, $allowedExtensions)) {
                    $errorMsg = "Format file gambar tidak didukung. Gunakan JPG, PNG, atau GIF.";
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
                        // Hapus gambar lama jika ada
                        if (!empty($product['image_url'])) {
                            $oldImagePath = __DIR__ . '/../' . $product['image_url'];
                            if (file_exists($oldImagePath)) {
                                unlink($oldImagePath);
                            }
                        }
                        
                        // Simpan path relatif ke database
                        $imageUrl = 'uploads/product/' . $newFileName;
                        
                        // Update produk dengan gambar baru
                        $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, price = ?, category_id = ?, image_url = ? WHERE id = ?");
                        $stmt->execute([$name, $desc, $price, $category_id, $imageUrl, $product_id]);
                    } else {
                        $errorMsg = "Gagal memindahkan file gambar ke folder upload.";
                    }
                }
            } else {
                // Update produk tanpa mengubah gambar
                $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, price = ?, category_id = ? WHERE id = ?");
                $stmt->execute([$name, $desc, $price, $category_id, $product_id]);
            }
            
            if (empty($errorMsg)) {
                $_SESSION['success_msg'] = "Produk berhasil diupdate.";
                header("Location: dashboard.php");
                exit;
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
<title>Edit Produk - NADA BookStore</title>
<link rel="stylesheet" href="../styles.css" />
<style>
    .product-image-preview {
        max-width: 200px;
        max-height: 200px;
        margin: 10px 0;
    }
    .edit-form {
        max-width: 600px;
        margin: 0 auto;
    }
    .form-group {
        margin-bottom: 15px;
    }
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
    }
    .form-group input[type="text"],
    .form-group input[type="number"],
    .form-group textarea,
    .form-group select {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    .buttons {
        margin-top: 20px;
    }
    .btn-cancel {
        margin-left: 10px;
        text-decoration: none;
    }
</style>
</head>
<body>
<header>
  <h1>Edit Produk - NADA BookStore</h1>
  <nav>
    <a href="dashboard.php">Dashboard</a> |
    <a href="../products.php">Produk</a> |
    <a href="../logout.php">Logout</a>
  </nav>
</header>
<main>
    <?php if ($errorMsg): ?>
        <div class="error-msg"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <div class="edit-form">
        <h2>Edit Produk: <?= htmlspecialchars($product['name']) ?></h2>
        
        <form method="POST" action="edit_product.php?id=<?= $product_id ?>" enctype="multipart/form-data">
            <div class="form-group">
                <label for="product_name">Nama Produk:</label>
                <input type="text" id="product_name" name="product_name" value="<?= htmlspecialchars($product['name']) ?>" required />
            </div>

            <div class="form-group">
                <label for="product_desc">Deskripsi:</label>
                <textarea id="product_desc" name="product_desc" rows="5" required><?= htmlspecialchars($product['description']) ?></textarea>
            </div>

            <div class="form-group">
                <label for="product_price">Harga (Rupiah):</label>
                <input type="number" step="0.01" id="product_price" name="product_price" min="0.01" value="<?= $product['price'] ?>" required />
            </div>

            <div class="form-group">
                <label for="category_id">Kategori:</label>
                <select id="category_id" name="category_id" required>
                    <option value="">--Pilih kategori--</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ($product['category_id'] == $cat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if (!empty($product['image_url'])): ?>
                <div class="form-group">
                    <label>Gambar Saat Ini:</label>
                    <img src="../<?= htmlspecialchars($product['image_url']) ?>" alt="Product Image" class="product-image-preview" />
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="product_image">Upload Gambar Baru (Biarkan kosong jika tidak ingin mengubah):</label>
                <input type="file" id="product_image" name="product_image" accept="image/jpeg,image/png,image/gif" />
                <small>Format yang didukung: JPG, JPEG, PNG, GIF</small>
            </div>

            <div class="buttons">
                <button type="submit" class="btn-primary">Simpan Perubahan</button>
                <a href="dashboard.php" class="btn-primary btn-cancel">Batal</a>
            </div>
        </form>
    </div>
</main>
</body>
</html>