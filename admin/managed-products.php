<?php
// Pastikan session sudah dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include file auth.php yang berisi fungsi-fungsi otentikasi
require_once '../auth.php';
require_once '../db.php';

// Fungsi untuk memeriksa apakah user adalah admin
function requireAdminLogin()
{
    if (!isLoggedIn()) {
        header("Location: ../login.php");
        exit;
    }

    $user = getLoggedUser();
    if ($user['role'] !== 'admin') {
        header("Location: ../index.php");
        exit;
    }
}

// Jalankan pengecekan admin
requireAdminLogin();
$user = getLoggedUser();

$message = '';
$messageType = '';

// Ambil daftar kategori untuk dropdown
try {
    $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categories = [];
    $message = "Error loading categories: " . $e->getMessage();
    $messageType = 'danger';
}

// Handle submit tambah kategori baru - SAMA PERSIS DENGAN VERSI DEBUG YANG BERHASIL
if (isset($_POST['add_category'])) {
    $newCategory = trim($_POST['category_name'] ?? '');

    if ($newCategory === '') {
        $message = "Nama kategori tidak boleh kosong.";
        $messageType = 'danger';
    } else {
        try {
            // Cek apakah kategori sudah ada
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = ?");
            $stmt->execute([$newCategory]);
            $count = $stmt->fetchColumn();

            if ($count > 0) {
                $message = "Kategori '$newCategory' sudah ada.";
                $messageType = 'danger';
            } else {
                $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
                $result = $stmt->execute([$newCategory]);
                $rowCount = $stmt->rowCount();
                $lastInsertId = $pdo->lastInsertId();

                if ($result && $rowCount > 0) {
                    $message = "Kategori '$newCategory' berhasil ditambahkan dengan ID: " . $lastInsertId;
                    $messageType = 'success';

                    // Refresh categories
                    $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
                    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $message = "Gagal menambahkan kategori.";
                    $messageType = 'danger';
                }
            }
        } catch (PDOException $e) {
            $message = "Error database: " . $e->getMessage();
            $messageType = 'danger';
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Handle submit tambah produk baru - SAMA PERSIS DENGAN VERSI DEBUG YANG BERHASIL
if (isset($_POST['add_product'])) {
    $name = trim($_POST['product_name'] ?? '');
    $desc = trim($_POST['product_desc'] ?? '');
    $price = $_POST['product_price'] ?? '';
    $stock = $_POST['product_stock'] ?? 0;
    $category_id = $_POST['category_id'] ?? '';

    if ($name === '' || $desc === '' || !is_numeric($price) || $category_id === '') {
        $message = "Nama produk, deskripsi, harga, dan kategori wajib diisi dengan benar.";
        $messageType = 'danger';
    } else {
        $imageUrl = '';

        // Handle file upload jika ada
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['product_image']['tmp_name'];
            $fileName = $_FILES['product_image']['name'];
            $fileSize = $_FILES['product_image']['size'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

            if (!in_array($fileExtension, $allowedExtensions)) {
                $message = "Format file tidak didukung. Gunakan JPG, PNG, atau GIF.";
                $messageType = 'danger';
            } elseif ($fileSize > 5 * 1024 * 1024) {
                $message = "Ukuran file terlalu besar. Maksimal 5MB.";
                $messageType = 'danger';
            } else {
                $uploadDir = __DIR__ . '/../uploads/product/';

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $newFileName = uniqid('product_', true) . '.' . $fileExtension;
                $destPath = $uploadDir . $newFileName;

                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    $imageUrl = 'uploads/product/' . $newFileName;
                } else {
                    $message = "Gagal mengupload gambar.";
                    $messageType = 'danger';
                }
            }
        }

        // Jika tidak ada error dalam upload gambar, lanjutkan insert ke database
        if ($messageType !== 'danger') {
            $price = floatval($price);
            $stock = intval($stock);

            if ($price <= 0) {
                $message = "Harga harus lebih besar dari nol.";
                $messageType = 'danger';
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO products (name, description, price, category_id, image_url, stock) VALUES (?, ?, ?, ?, ?, ?)");
                    $result = $stmt->execute([$name, $desc, $price, $category_id, $imageUrl, $stock]);
                    $rowCount = $stmt->rowCount();
                    $lastInsertId = $pdo->lastInsertId();

                    if ($result && $rowCount > 0) {
                        $message = "Produk '$name' berhasil ditambahkan dengan ID: " . $lastInsertId;
                        $messageType = 'success';
                    } else {
                        $message = "Gagal menambahkan produk.";
                        $messageType = 'danger';
                    }
                } catch (PDOException $e) {
                    $message = "Error database: " . $e->getMessage();
                    $messageType = 'danger';
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = 'danger';
                }
            }
        }
    }
}

// Filters dan Pagination
$searchQuery = $_GET['search'] ?? '';
$categoryFilter = $_GET['category'] ?? 'all';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

// Build query untuk products
$sql = "SELECT p.*, c.name as category_name 
        FROM products p 
        JOIN categories c ON p.category_id = c.id 
        WHERE 1=1";

$params = [];

if ($searchQuery) {
    $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $searchParam = "%$searchQuery%";
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if ($categoryFilter !== 'all') {
    $sql .= " AND p.category_id = ?";
    $params[] = $categoryFilter;
}

$sql .= " ORDER BY p.id DESC LIMIT $perPage OFFSET $offset";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $products = [];
    $message = "Error mengambil data produk: " . $e->getMessage();
    $messageType = 'danger';
}

// Count total products
$countSql = "SELECT COUNT(*) as total FROM products p WHERE 1=1";
$countParams = [];

if ($searchQuery) {
    $countSql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $searchParam = "%$searchQuery%";
    $countParams[] = $searchParam;
    $countParams[] = $searchParam;
}

if ($categoryFilter !== 'all') {
    $countSql .= " AND p.category_id = ?";
    $countParams[] = $categoryFilter;
}

try {
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $result = $countStmt->fetch(PDO::FETCH_ASSOC);
    $totalProducts = $result['total'] ?? 0;
    $totalPages = ceil($totalProducts / $perPage);
} catch (Exception $e) {
    $totalProducts = 0;
    $totalPages = 0;
}

// Handle delete product
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $productId = (int) $_GET['delete'];

    try {
        // Get image path untuk dihapus
        $stmt = $pdo->prepare("SELECT image_url FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        // Delete from database
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $result = $stmt->execute([$productId]);

        if ($result && $stmt->rowCount() > 0) {
            // Delete image file jika ada
            if ($product && $product['image_url']) {
                $imagePath = __DIR__ . '/../' . $product['image_url'];
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }

            $message = "Produk berhasil dihapus.";
            $messageType = 'success';
        } else {
            $message = "Produk tidak ditemukan atau sudah dihapus.";
            $messageType = 'danger';
        }
    } catch (Exception $e) {
        $message = "Error menghapus produk: " . $e->getMessage();
        $messageType = 'danger';
    }
}

// Handle edit product
if (isset($_POST['edit_product'])) {
    $productId = $_POST['product_id'] ?? 0;
    $name = trim($_POST['product_name'] ?? '');
    $desc = trim($_POST['product_desc'] ?? '');
    $price = $_POST['product_price'] ?? '';
    $stock = $_POST['product_stock'] ?? 0;
    $category_id = $_POST['category_id'] ?? '';

    if ($name === '' || $desc === '' || !is_numeric($price) || $category_id === '' || !is_numeric($stock)) {
        $message = "Semua field wajib diisi dengan benar.";
        $messageType = 'danger';
    } else {
        // Ambil data produk lama
        $stmt = $pdo->prepare("SELECT image_url FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $oldProduct = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$oldProduct) {
            $message = "Produk tidak ditemukan.";
            $messageType = 'danger';
        } else {
            $imageUrl = $oldProduct['image_url'] ?? '';

            // Handle file upload jika ada
            if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['product_image']['tmp_name'];
                $fileName = $_FILES['product_image']['name'];
                $fileSize = $_FILES['product_image']['size'];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

                if (!in_array($fileExtension, $allowedExtensions)) {
                    $message = "Format file tidak didukung. Gunakan JPG, PNG, atau GIF.";
                    $messageType = 'danger';
                } elseif ($fileSize > 5 * 1024 * 1024) {
                    $message = "Ukuran file terlalu besar. Maksimal 5MB.";
                    $messageType = 'danger';
                } else {
                    $uploadDir = __DIR__ . '/../uploads/product/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $newFileName = uniqid('product_', true) . '.' . $fileExtension;
                    $destPath = $uploadDir . $newFileName;

                    if (move_uploaded_file($fileTmpPath, $destPath)) {
                        // Hapus gambar lama jika ada dan bukan default
                        if ($oldProduct && $oldProduct['image_url'] && $oldProduct['image_url'] !== '') {
                            $oldImagePath = __DIR__ . '/../' . $oldProduct['image_url'];
                            if (file_exists($oldImagePath)) {
                                unlink($oldImagePath);
                            }
                        }

                        $imageUrl = 'uploads/product/' . $newFileName;
                    } else {
                        $message = "Gagal mengupload gambar baru.";
                        $messageType = 'danger';
                    }
                }
            }

            // Jika tidak ada error, update database
            if ($messageType !== 'danger') {
                $price = floatval($price);
                $stock = intval($stock);

                if ($price <= 0) {
                    $message = "Harga harus lebih besar dari nol.";
                    $messageType = 'danger';
                } else {
                    try {
                        $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, price = ?, stock = ?, category_id = ?, image_url = ? WHERE id = ?");
                        $result = $stmt->execute([$name, $desc, $price, $stock, $category_id, $imageUrl, $productId]);

                        if ($result) {
                            $message = "Produk '$name' berhasil diperbarui.";
                            $messageType = 'success';
                        } else {
                            $message = "Gagal memperbarui produk.";
                            $messageType = 'danger';
                        }
                    } catch (Exception $e) {
                        $message = "Error memperbarui produk: " . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
            }
        }
    }
}

// Function to get product by ID
function getProductById($pdo, $id)
{
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Produk - NADA BookStore Admin</title>
    <link rel="stylesheet" href="admin-styles.css">
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<style>
    .modal-content {
        max-height: 90vh;
        overflow-y: auto;
    }

    .modal.show .modal-content {
        animation: modal-slide-in 0.3s ease;
    }

    @keyframes modal-slide-in {
        from {
            transform: translateY(-20px);
            opacity: 0;
        }

        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .modal .form-group {
        margin-bottom: 15px;
    }

    .modal label.form-label {
        font-weight: 500;
        margin-bottom: 5px;
        display: block;
    }

    #current_image_container {
        transition: all 0.2s ease;
    }

    #current_image_container:hover {
        border-color: var(--admin-primary);
    }

    .loading-state {
        text-align: center;
        padding: 30px;
        color: #64748b;
    }

    .loading-spinner {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 3px solid rgba(0, 0, 0, 0.1);
        border-radius: 50%;
        border-top-color: var(--admin-primary);
        animation: spin 1s ease-in-out infinite;
        margin-right: 10px;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    .image-preview-container {
        position: relative;
        cursor: pointer;
    }

    .image-preview-container:hover::after {
        content: "Klik untuk upload";
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: rgba(0, 0, 0, 0.7);
        color: white;
        padding: 5px;
        font-size: 12px;
        text-align: center;
        border-radius: 0 0 5px 5px;
    }
</style>

<body class="admin-body">
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <nav class="admin-sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="fas fa-book"></i>
                    <span>NADA Admin</span>
                </div>
            </div>

            <ul class="sidebar-menu">
                <li>
                    <a href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="orders.php">
                        <i class="fas fa-shopping-bag"></i>
                        <span>Kelola Pesanan</span>
                    </a>
                </li>
                <li class="active">
                    <a href="managed-products.php">
                        <i class="fas fa-box"></i>
                        <span>Kelola Produk</span>
                    </a>
                </li>
                <li>
                    <a href="shipping_management.php">
                        <i class="fas fa-truck"></i>
                        <span>Kelola Pengiriman</span>
                    </a>
                </li>
                <li>
                    <a href="users.php">
                        <i class="fas fa-users"></i>
                        <span>Kelola User</span>
                    </a>
                </li>
                <li class="sidebar-divider"></li>
                <li>
                    <a href="../index.php">
                        <i class="fas fa-home"></i>
                        <span>Ke Website</span>
                    </a>
                </li>
                <li>
                    <a href="../logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Main Content -->
        <div class="admin-main">
            <!-- Header -->
            <header class="admin-header">
                <div class="header-left">
                    <h1>Kelola Produk</h1>
                </div>
                <div class="header-right">
                    <div class="admin-user">
                        <i class="fas fa-user-shield"></i>
                        <span>Admin: <?= htmlspecialchars($user['nama']) ?></span>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <main class="admin-content">
                <?php if ($message): ?>
                    <div class="alert alert-<?= $messageType ?>"
                        style="background: <?= $messageType === 'success' ? '#d4edda' : '#f8d7da' ?>; color: <?= $messageType === 'success' ? '#155724' : '#721c24' ?>; padding: 15px; margin: 15px 0; border-radius: 5px;">
                        <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <!-- Quick Actions -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">

                    <!-- Add Category Card -->
                    <div style="background: #1e293b; padding: 20px; border-radius: 8px;">
                        <h3 style="color: white; margin-bottom: 15px;">
                            <i class="fas fa-plus-circle"></i>
                            Tambah Kategori
                        </h3>
                        <form method="POST" action="">
                            <div style="margin-bottom: 15px;">
                                <label style="color: white; display: block; margin-bottom: 5px;">Nama Kategori</label>
                                <input type="text" name="category_name"
                                    style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"
                                    placeholder="Masukkan nama kategori..." required>
                            </div>
                            <button type="submit" name="add_category"
                                style="background: #3b82f6; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">
                                <i class="fas fa-plus"></i> Tambah Kategori
                            </button>
                        </form>
                    </div>

                    <!-- Add Product Card -->
                    <div style="background: #1e293b; padding: 20px; border-radius: 8px;">
                        <h3 style="color: white; margin-bottom: 15px;">
                            <i class="fas fa-box"></i>
                            Tambah Produk
                        </h3>
                        <form method="POST" action="" enctype="multipart/form-data">
                            <div style="margin-bottom: 10px;">
                                <label style="color: white; display: block; margin-bottom: 5px;">Nama Produk</label>
                                <input type="text" name="product_name"
                                    style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"
                                    placeholder="Nama produk..." required>
                            </div>
                            <div style="margin-bottom: 10px;">
                                <label style="color: white; display: block; margin-bottom: 5px;">Deskripsi</label>
                                <textarea name="product_desc" rows="3"
                                    style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"
                                    placeholder="Deskripsi produk..." required></textarea>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                                <div>
                                    <label style="color: white; display: block; margin-bottom: 5px;">Harga</label>
                                    <input type="number" name="product_price" step="0.01" min="0.01"
                                        style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"
                                        placeholder="0" required>
                                </div>
                                <div>
                                    <label style="color: white; display: block; margin-bottom: 5px;">Stok</label>
                                    <input type="number" name="product_stock" min="0" value="0"
                                        style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                                </div>
                            </div>
                            <div style="margin-bottom: 10px;">
                                <label style="color: white; display: block; margin-bottom: 5px;">Kategori</label>
                                <select name="category_id"
                                    style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"
                                    required>
                                    <option value="">--Pilih kategori--</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label style="color: white; display: block; margin-bottom: 5px;">Gambar</label>
                                <input type="file" name="product_image" accept="image/jpeg,image/png,image/gif"
                                    style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; color: white;">
                            </div>
                            <button type="submit" name="add_product"
                                style="background: #10b981; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">
                                <i class="fas fa-save"></i> Simpan Produk
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Products List -->
                <div style="background: #1e293b; padding: 20px; border-radius: 8px;">
                    <h3 style="color: white; margin-bottom: 15px;">
                        <i class="fas fa-box"></i>
                        Daftar Produk (<?= $totalProducts ?> produk)
                    </h3>

                    <?php if (empty($products)): ?>
                        <p style="color: #64748b; text-align: center; padding: 40px;">
                            <i class="fas fa-box-open" style="font-size: 3rem; display: block; margin-bottom: 15px;"></i>
                            Tidak ada produk ditemukan
                        </p>
                    <?php else: ?>
                        <div
                            style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px;">
                            <?php foreach ($products as $product): ?>
                                <div style="background: #334155; padding: 15px; border-radius: 8px;">
                                    <?php if (!empty($product['image_url'])): ?>
                                        <img src="../<?= htmlspecialchars($product['image_url']) ?>"
                                            alt="<?= htmlspecialchars($product['name']) ?>"
                                            style="width: 100%; height: 150px; object-fit: cover; border-radius: 4px; margin-bottom: 10px;">
                                    <?php else: ?>
                                        <div
                                            style="width: 100%; height: 150px; background: #475569; display: flex; align-items: center; justify-content: center; border-radius: 4px; margin-bottom: 10px;">
                                            <i class="fas fa-image" style="font-size: 2rem; color: #64748b;"></i>
                                        </div>
                                    <?php endif; ?>

                                    <h4 style="color: white; margin: 0 0 8px 0;"><?= htmlspecialchars($product['name']) ?></h4>
                                    <p style="color: #94a3b8; font-size: 0.9rem; margin: 0 0 8px 0;">
                                        <?= htmlspecialchars(substr($product['description'], 0, 60)) ?>...
                                    </p>
                                    <p style="color: #10b981; font-weight: bold; margin: 0 0 5px 0;">
                                        Rp <?= number_format($product['price'], 0, ',', '.') ?>
                                    </p>
                                    <p style="color: #64748b; font-size: 0.8rem; margin: 0 0 10px 0;">
                                        Kategori: <?= htmlspecialchars($product['category_name']) ?> |
                                        Stok: <?= $product['stock'] ?? 0 ?> |
                                        ID: #<?= $product['id'] ?>
                                    </p>
                                    <div style="display: flex; gap: 8px;">
                                        <button onclick="editProduct(<?= $product['id'] ?>)"
                                            style="flex: 1; background: #f59e0b; color: white; padding: 8px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button
                                            onclick="deleteProduct(<?= $product['id'] ?>, '<?= addslashes(htmlspecialchars($product['name'])) ?>')"
                                            style="flex: 1; background: #ef4444; color: white; padding: 8px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">
                                            <i class="fas fa-trash"></i> Hapus
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div id="editProductModal"
        style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
        <div
            style="background-color: #1e293b; margin: 5% auto; padding: 20px; border-radius: 8px; width: 80%; max-width: 800px; color: white;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0;">Edit Produk</h3>
                <button onclick="closeEditProductModal()"
                    style="background: none; border: none; color: white; font-size: 24px; cursor: pointer;">&times;</button>
            </div>

            <form method="POST" action="" enctype="multipart/form-data" id="editProductForm">
                <input type="hidden" name="product_id" id="edit_product_id" value="">

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <!-- Kolom Kiri -->
                    <div>
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px;">Nama Produk</label>
                            <input type="text" name="product_name" id="edit_product_name"
                                style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"
                                required>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                            <div>
                                <label style="display: block; margin-bottom: 5px;">Harga</label>
                                <input type="number" name="product_price" id="edit_product_price" step="0.01" min="0.01"
                                    style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"
                                    required>
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 5px;">Stok</label>
                                <input type="number" name="product_stock" id="edit_product_stock" min="0"
                                    style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"
                                    required>
                            </div>
                        </div>

                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px;">Kategori</label>
                            <select name="category_id" id="edit_category_id"
                                style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"
                                required>
                                <option value="">--Pilih kategori--</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px;">Deskripsi</label>
                            <textarea name="product_desc" id="edit_product_desc" rows="4"
                                style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"
                                required></textarea>
                        </div>
                    </div>

                    <!-- Kolom Kanan -->
                    <div>
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px;">Gambar Produk Saat Ini</label>
                            <div id="current_image_container"
                                style="border: 1px dashed #ccc; padding: 10px; text-align: center; border-radius: 4px; background: #f8f9fa; height: 200px; display: flex; align-items: center; justify-content: center;">
                                <img id="current_product_image" src="" alt="Gambar Produk"
                                    style="max-width: 100%; max-height: 180px; border-radius: 4px; display: none; object-fit: contain;">
                                <p id="no_image_text" style="color: #64748b; font-style: italic; display: none;">Tidak
                                    ada gambar</p>
                            </div>
                        </div>

                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px;">Upload Gambar Baru (Opsional)</label>
                            <input type="file" name="product_image" accept="image/jpeg,image/png,image/gif"
                                style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; color: white;">
                            <small style="color: #94a3b8; font-size: 0.8rem; display: block; margin-top: 5px;">
                                Format: JPG, PNG, GIF. Maksimal 5MB. Biarkan kosong jika tidak ingin mengubah gambar.
                            </small>
                        </div>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" onclick="closeEditProductModal()"
                        style="background: #6b7280; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">
                        Batal
                    </button>
                    <button type="submit" name="edit_product"
                        style="background: #10b981; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">
                        <i class="fas fa-save"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Edit Product Modal Functions
        function showEditProductModal() {
            document.getElementById('editProductModal').style.display = 'block';
        }

        function closeEditProductModal() {
            document.getElementById('editProductModal').style.display = 'none';
        }

        // Edit Product Function
        function editProduct(productId) {
            // Reset form
            document.getElementById('editProductForm').reset();

            // Show modal
            showEditProductModal();

            // Show loading in modal
            const modalContent = document.querySelector('#editProductModal > div');
            const originalContent = modalContent.innerHTML;
            modalContent.innerHTML = '<div style="text-align: center; padding: 50px; color: white;"><i class="fas fa-spinner fa-spin"></i> Memuat data produk...</div>';

            // Fetch product data
            fetch('get_product.php?id=' + productId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(product => {
                    // Restore original content
                    modalContent.innerHTML = originalContent;

                    // Populate form
                    document.getElementById('edit_product_id').value = product.id;
                    document.getElementById('edit_product_name').value = product.name;
                    document.getElementById('edit_product_desc').value = product.description;
                    document.getElementById('edit_product_price').value = product.price;
                    document.getElementById('edit_product_stock').value = product.stock || 0;
                    document.getElementById('edit_category_id').value = product.category_id;

                    // Set image preview
                    const currentImageEl = document.getElementById('current_product_image');
                    const noImageTextEl = document.getElementById('no_image_text');

                    if (product.image_url && product.image_url !== '') {
                        currentImageEl.src = '../' + product.image_url;
                        currentImageEl.style.display = 'inline-block';
                        noImageTextEl.style.display = 'none';
                    } else {
                        currentImageEl.style.display = 'none';
                        noImageTextEl.style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    modalContent.innerHTML = '<div style="text-align: center; padding: 50px; color: #ef4444;">Error: Gagal memuat data produk. Silakan coba lagi.</div>';
                });
        }

        // Delete Product Function
        function deleteProduct(productId, productName) {
            if (confirm('Apakah Anda yakin ingin menghapus produk "' + productName + '"?\n\nTindakan ini tidak dapat dibatalkan.')) {
                window.location.href = '?delete=' + productId;
            }
        }

        // Close modal when clicking outside
        window.onclick = function (event) {
            const modal = document.getElementById('editProductModal');
            if (event.target == modal) {
                closeEditProductModal();
            }
        }

        // ESC key to close modal
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeEditProductModal();
            }
        });
    </script>
</body>

</html>