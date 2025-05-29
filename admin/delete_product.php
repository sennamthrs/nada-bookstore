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

// Cek apakah produk dengan ID tersebut ada
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    $_SESSION['error_msg'] = "Produk tidak ditemukan.";
    header("Location: dashboard.php");
    exit;
}

// Jika form konfirmasi di-submit atau parameter confirm=yes
if ($_SERVER['REQUEST_METHOD'] === 'POST' || (isset($_GET['confirm']) && $_GET['confirm'] === 'yes')) {
    try {
        // Hapus produk dari database
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $success = $stmt->execute([$product_id]);
        
        if ($success) {
            // Hapus file gambar jika ada
            if (!empty($product['image_url'])) {
                $imagePath = __DIR__ . '/../' . $product['image_url'];
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
            
            $_SESSION['success_msg'] = "Produk berhasil dihapus.";
        } else {
            $_SESSION['error_msg'] = "Gagal menghapus produk.";
        }
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Error database: " . $e->getMessage();
    }
    
    header("Location: dashboard.php");
    exit;
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Hapus Produk - NADA BookStore</title>
<link rel="stylesheet" href="../styles.css" />
<style>
    .delete-confirmation {
        max-width: 600px;
        margin: 0 auto;
        text-align: center;
    }
    .product-info {
        background-color: #f9f9f9;
        padding: 20px;
        margin: 20px 0;
        border-radius: 5px;
        text-align: left;
    }
    .product-image {
        max-width: 200px;
        max-height: 200px;
        margin: 10px auto;
        display: block;
    }
    .buttons {
        margin-top: 20px;
    }
    .btn-confirm {
        background-color: #f44336;
        color: white;
    }
    .btn-cancel {
        margin-left: 10px;
        background-color: #ccc;
        color: #333;
        text-decoration: none;
    }
</style>
</head>
<body>
<header>
  <h1>Hapus Produk - NADA BookStore</h1>
  <nav>
    <a href="dashboard.php">Dashboard</a> |
    <a href="../products.php">Produk</a> |
    <a href="../logout.php">Logout</a>
  </nav>
</header>
<main>
    <div class="delete-confirmation">
        <h2>Konfirmasi Penghapusan</h2>
        
        <div class="warning-msg">
            <p>Apakah Anda yakin ingin menghapus produk ini? Tindakan ini tidak dapat dibatalkan.</p>
        </div>
        
        <div class="product-info">
            <h3><?= htmlspecialchars($product['name']) ?></h3>
            
            <?php if (!empty($product['image_url'])): ?>
                <img src="../<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="product-image" />
            <?php endif; ?>
            
            <p><strong>Harga:</strong> Rp. <?= number_format($product['price'], 0) ?></p>
            <p><strong>Deskripsi:</strong> <?= htmlspecialchars($product['description']) ?></p>
        </div>
        
        <div class="buttons">
            <form method="POST" action="delete_product.php?id=<?= $product_id ?>" style="display: inline;">
                <button type="submit" class="btn-primary btn-confirm">Ya, Hapus Produk</button>
            </form>
            <a href="dashboard.php" class="btn-primary btn-cancel">Batal</a>
        </div>
    </div>
</main>
</body>
</html>