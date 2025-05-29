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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        $email = trim($_POST['email']);
        $nama = trim($_POST['nama']);
        $no_telepon = trim($_POST['no_telepon']);
        $address = trim($_POST['address']);
        $kota = trim($_POST['kota']);
        $provinsi = trim($_POST['provinsi']);
        $kode_pos = intval($_POST['kode_pos']);
        $password = $_POST['password'];
        $role = $_POST['role'];

        // Validasi input
        if (empty($email) || empty($nama) || empty($password)) {
            $message = "Email, nama, dan password wajib diisi.";
            $messageType = 'danger';
        } else {
            // Cek apakah email sudah ada
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $count = $stmt->fetchColumn();

            if ($count > 0) {
                $message = "Email '$email' sudah terdaftar.";
                $messageType = 'danger';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                try {
                    $stmt = $pdo->prepare("INSERT INTO users (email, nama, no_telepon, address, kota, provinsi, kode_pos, password, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $result = $stmt->execute([$email, $nama, $no_telepon, $address, $kota, $provinsi, $kode_pos, $hashedPassword, $role]);

                    if ($result) {
                        $message = "User '$nama' berhasil ditambahkan.";
                        $messageType = 'success';
                    } else {
                        $message = "Gagal menambahkan user.";
                        $messageType = 'danger';
                    }
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = 'danger';
                }
            }
        }
    } elseif (isset($_POST['edit_user'])) {
        $userId = intval($_POST['user_id']);
        $email = trim($_POST['email']);
        $nama = trim($_POST['nama']);
        $no_telepon = trim($_POST['no_telepon']);
        $address = trim($_POST['address']);
        $kota = trim($_POST['kota']);
        $provinsi = trim($_POST['provinsi']);
        $kode_pos = intval($_POST['kode_pos']);
        $role = $_POST['role'];
        $password = trim($_POST['password']);

        if (empty($email) || empty($nama)) {
            $message = "Email dan nama wajib diisi.";
            $messageType = 'danger';
        } else {
            try {
                // Cek apakah email sudah digunakan user lain
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $userId]);
                $count = $stmt->fetchColumn();

                if ($count > 0) {
                    $message = "Email '$email' sudah digunakan user lain.";
                    $messageType = 'danger';
                } else {
                    // Update user data
                    if (!empty($password)) {
                        // Update dengan password baru
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET email = ?, nama = ?, no_telepon = ?, address = ?, kota = ?, provinsi = ?, kode_pos = ?, password = ?, role = ?, updated_at = NOW() WHERE id = ?");
                        $result = $stmt->execute([$email, $nama, $no_telepon, $address, $kota, $provinsi, $kode_pos, $hashedPassword, $role, $userId]);
                    } else {
                        // Update tanpa mengubah password
                        $stmt = $pdo->prepare("UPDATE users SET email = ?, nama = ?, no_telepon = ?, address = ?, kota = ?, provinsi = ?, kode_pos = ?, role = ?, updated_at = NOW() WHERE id = ?");
                        $result = $stmt->execute([$email, $nama, $no_telepon, $address, $kota, $provinsi, $kode_pos, $role, $userId]);
                    }

                    if ($result) {
                        $message = "Data user '$nama' berhasil diperbarui.";
                        $messageType = 'success';
                    } else {
                        $message = "Gagal memperbarui data user.";
                        $messageType = 'danger';
                    }
                }
            } catch (Exception $e) {
                $message = "Error: " . $e->getMessage();
                $messageType = 'danger';
            }
        }
    } elseif (isset($_POST['delete_user'])) {
        $userId = intval($_POST['user_id']);

        // Tidak bisa menghapus admin yang sedang login
        if ($userId == $user['id']) {
            $message = "Tidak dapat menghapus akun admin yang sedang aktif.";
            $messageType = 'danger';
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $result = $stmt->execute([$userId]);

                if ($result && $stmt->rowCount() > 0) {
                    $message = "User berhasil dihapus.";
                    $messageType = 'success';
                } else {
                    $message = "User tidak ditemukan atau sudah dihapus.";
                    $messageType = 'danger';
                }
            } catch (Exception $e) {
                $message = "Error: " . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }
}

// Filters dan Pagination
$searchQuery = $_GET['search'] ?? '';
$roleFilter = $_GET['role'] ?? 'all';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Build query untuk users
$sql = "SELECT * FROM users WHERE 1=1";
$params = [];

if ($searchQuery) {
    $sql .= " AND (nama LIKE ? OR email LIKE ? OR no_telepon LIKE ?)";
    $searchParam = "%$searchQuery%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if ($roleFilter !== 'all') {
    $sql .= " AND role = ?";
    $params[] = $roleFilter;
}

$sql .= " ORDER BY id DESC LIMIT $perPage OFFSET $offset";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $users = [];
    $message = "Error mengambil data user: " . $e->getMessage();
    $messageType = 'danger';
}

// Count total users
$countSql = "SELECT COUNT(*) as total FROM users WHERE 1=1";
$countParams = [];

if ($searchQuery) {
    $countSql .= " AND (nama LIKE ? OR email LIKE ? OR no_telepon LIKE ?)";
    $searchParam = "%$searchQuery%";
    $countParams[] = $searchParam;
    $countParams[] = $searchParam;
    $countParams[] = $searchParam;
}

if ($roleFilter !== 'all') {
    $countSql .= " AND role = ?";
    $countParams[] = $roleFilter;
}

try {
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $result = $countStmt->fetch(PDO::FETCH_ASSOC);
    $totalUsers = $result['total'] ?? 0;
    $totalPages = ceil($totalUsers / $perPage);
} catch (Exception $e) {
    $totalUsers = 0;
    $totalPages = 0;
}

// Function to get user by ID
function getUserById($pdo, $id)
{
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola User - NADA BookStore Admin</title>
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

    .user-card {
        background: #334155;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 15px;
        color: white;
    }

    .user-card h4 {
        margin: 0 0 10px 0;
        color: #f8fafc;
        font-size: 1.1rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .user-card p {
        margin: 5px 0;
        color: #94a3b8;
        font-size: 0.9rem;
    }

    .user-card .role-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }

    .user-card .role-admin {
        background: #ef4444;
        color: white;
    }

    .role-member {
        background: #10b981;
        color: white;
    }

    /* Fix untuk input fields di modal */
    #editUserModal input,
    #editUserModal textarea,
    #editUserModal select {
        background: white !important;
        color: black !important;
        border: 1px solid #ccc !important;
        outline: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
    }

    #editUserModal input:focus,
    #editUserModal textarea:focus,
    #editUserModal select:focus {
        border-color: #3b82f6 !important;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2) !important;
    }

    #editUserModal input:disabled,
    #editUserModal textarea:disabled,
    #editUserModal select:disabled {
        background: #f3f4f6 !important;
        color: #6b7280 !important;
        cursor: not-allowed;
    }

    .btn-group {
        display: flex;
        gap: 8px;
        margin-top: 10px;
    }

    .btn-edit {
        background: #f59e0b;
        color: white;
        padding: 6px 12px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.8rem;
        flex: 1;
    }

    .btn-delete {
        background: #ef4444;
        color: white;
        padding: 6px 12px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.8rem;
        flex: 1;
    }

    .search-filter-section {
        background: #1e293b;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }

    .filter-row {
        display: grid;
        grid-template-columns: 2fr 1fr auto;
        gap: 15px;
        align-items: end;
    }

    @media (max-width: 768px) {
        .users-grid {
            grid-template-columns: 1fr !important;
        }

        .filter-row {
            grid-template-columns: 1fr;
            gap: 10px;
        }
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
                <li>
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
                <li class="active">
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
                    <h1>Kelola User</h1>
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

                <!-- Search and Filter -->
                <div class="search-filter-section">
                    <h3 style="color: white; margin-bottom: 15px;">
                        <i class="fas fa-search"></i>
                        Cari & Filter User
                        <!-- Debug button sementara -->
                        <button onclick="testModal()"
                            style="float: right; background: #f59e0b; color: white; padding: 5px 10px; border: none; border-radius: 4px; font-size: 12px;">
                            Test Modal
                        </button>
                    </h3>
                    <form method="GET" action="">
                        <div class="filter-row">
                            <div>
                                <label style="color: white; display: block; margin-bottom: 5px;">Cari User</label>
                                <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>"
                                    style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"
                                    placeholder="Cari berdasarkan nama, email, atau telepon...">
                            </div>
                            <div>
                                <label style="color: white; display: block; margin-bottom: 5px;">Filter Role</label>
                                <select name="role"
                                    style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                                    <option value="all" <?= $roleFilter === 'all' ? 'selected' : '' ?>>Semua Role</option>
                                    <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
                                    <option value="member" <?= $roleFilter === 'member' ? 'selected' : '' ?>>Member
                                    </option>
                                </select>
                            </div>
                            <div>
                                <button type="submit"
                                    style="background: #3b82f6; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">
                                    <i class="fas fa-search"></i> Cari
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Main Grid Layout -->
                <div class="users-grid" style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px;">
                    <!-- Add New User -->
                    <div style="background: #1e293b; padding: 20px; border-radius: 8px;">
                        <h3 style="color: white; margin-bottom: 20px;">
                            <i class="fas fa-user-plus"></i>
                            Tambah User Baru
                        </h3>
                        <form method="POST" action="">
                            <div style="margin-bottom: 15px;">
                                <label style="color: white; display: block; margin-bottom: 5px;">Email *</label>
                                <input type="email" name="email"
                                    style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"
                                    placeholder="user@example.com" required>
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label style="color: white; display: block; margin-bottom: 5px;">Nama Lengkap *</label>
                                <input type="text" name="nama"
                                    style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"
                                    placeholder="Nama lengkap..." required>
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label style="color: white; display: block; margin-bottom: 5px;">No. Telepon</label>
                                <input type="text" name="no_telepon"
                                    style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"
                                    placeholder="08xxxxxxxxxx">
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label style="color: white; display: block; margin-bottom: 5px;">Alamat</label>
                                <textarea name="address" rows="3"
                                    style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"
                                    placeholder="Alamat lengkap..."></textarea>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                                <div>
                                    <label style="color: white; display: block; margin-bottom: 5px;">Kota</label>
                                    <input type="text" name="kota"
                                        style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"
                                        placeholder="Kota">
                                </div>
                                <div>
                                    <label style="color: white; display: block; margin-bottom: 5px;">Kode Pos</label>
                                    <input type="number" name="kode_pos"
                                        style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"
                                        placeholder="12345">
                                </div>
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label style="color: white; display: block; margin-bottom: 5px;">Provinsi</label>
                                <input type="text" name="provinsi"
                                    style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"
                                    placeholder="Provinsi">
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label style="color: white; display: block; margin-bottom: 5px;">Password *</label>
                                <input type="password" name="password"
                                    style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"
                                    placeholder="Password..." required>
                            </div>
                            <div style="margin-bottom: 20px;">
                                <label style="color: white; display: block; margin-bottom: 5px;">Role</label>
                                <select name="role"
                                    style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                                    <option value="member">Member</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            <button type="submit" name="add_user"
                                style="background: #10b981; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; width: 100%;">
                                <i class="fas fa-user-plus"></i> Tambah User
                            </button>
                        </form>
                    </div>

                    <!-- Users List -->
                    <div style="background: #1e293b; padding: 20px; border-radius: 8px;">
                        <h3 style="color: white; margin-bottom: 20px;">
                            <i class="fas fa-users"></i>
                            Daftar User (<?= $totalUsers ?> user)
                        </h3>

                        <?php if (empty($users)): ?>
                            <p style="color: #64748b; text-align: center; padding: 40px;">
                                <i class="fas fa-user-slash"
                                    style="font-size: 3rem; display: block; margin-bottom: 15px;"></i>
                                Tidak ada user ditemukan
                            </p>
                        <?php else: ?>
                            <div style="max-height: 600px; overflow-y: auto;">
                                <?php foreach ($users as $userData): ?>
                                    <div class="user-card">
                                        <h4>
                                            <i class="fas fa-<?= $userData['role'] === 'admin' ? 'user-shield' : 'user' ?>"></i>
                                            <?= htmlspecialchars($userData['nama']) ?>
                                            <span class="role-badge role-<?= $userData['role'] ?>">
                                                <?= ucfirst($userData['role']) ?>
                                            </span>
                                        </h4>
                                        <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($userData['email']) ?></p>
                                        <?php if (!empty($userData['no_telepon'])): ?>
                                            <p><i class="fas fa-phone"></i> <?= htmlspecialchars($userData['no_telepon']) ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($userData['address'])): ?>
                                            <p><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($userData['address']) ?>
                                            </p>
                                        <?php endif; ?>
                                        <?php if (!empty($userData['kota']) && !empty($userData['provinsi'])): ?>
                                            <p><i class="fas fa-city"></i> <?= htmlspecialchars($userData['kota']) ?>,
                                                <?= htmlspecialchars($userData['provinsi']) ?>
                                                <?= $userData['kode_pos'] ? $userData['kode_pos'] : '' ?>
                                            </p>
                                        <?php endif; ?>
                                        <p style="color: #64748b; font-size: 0.8rem;">
                                            ID: #<?= $userData['id'] ?> |
                                            Dibuat: <?= date('d/m/Y H:i', strtotime($userData['updated_at'] ?? 'now')) ?>
                                        </p>

                                        <div class="btn-group">
                                            <button class="btn-edit"
                                                onclick="editUser(<?= $userData['id'] ?>, <?= htmlspecialchars(json_encode($userData), ENT_QUOTES, 'UTF-8') ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <?php if ($userData['id'] != $user['id']): ?>
                                                <form method="POST" style="flex: 1; display: inline;"
                                                    onsubmit="return confirm('Yakin ingin menghapus user <?= addslashes(htmlspecialchars($userData['nama'])) ?>?')">
                                                    <input type="hidden" name="user_id" value="<?= $userData['id'] ?>">
                                                    <button type="submit" name="delete_user" class="btn-delete"
                                                        style="width: 100%;">
                                                        <i class="fas fa-trash"></i> Hapus
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <button class="btn-delete" style="flex: 1; opacity: 0.5;" disabled>
                                                    <i class="fas fa-lock"></i> Aktif
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <div style="margin-top: 20px; text-align: center;">
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <?php
                                        $params = [];
                                        if ($searchQuery)
                                            $params['search'] = $searchQuery;
                                        if ($roleFilter !== 'all')
                                            $params['role'] = $roleFilter;
                                        $params['page'] = $i;
                                        $queryString = http_build_query($params);
                                        ?>
                                        <a href="?<?= $queryString ?>"
                                            style="display: inline-block; padding: 8px 12px; margin: 0 5px; background: <?= $i === $page ? '#3b82f6' : '#475569' ?>; color: white; text-decoration: none; border-radius: 4px;">
                                            <?= $i ?>
                                        </a>
                                    <?php endfor; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal"
        style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
        <div
            style="background-color: #1e293b; margin: 2% auto; padding: 20px; border-radius: 8px; width: 80%; max-width: 800px; color: white;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0;">
                    <i class="fas fa-user-edit"></i> Edit User
                </h3>
                <button onclick="closeEditUserModal()"
                    style="background: none; border: none; color: white; font-size: 24px; cursor: pointer;">&times;</button>
            </div>

            <form method="POST" action="" id="editUserForm">
                <input type="hidden" name="user_id" id="edit_user_id" value="">

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <!-- Kolom Kiri -->
                    <div>
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; color: white;">Email *</label>
                            <input type="email" name="email" id="edit_email"
                                style="width: calc(100% - 22px); padding: 10px; border: 1px solid #ccc; border-radius: 4px; background: white; color: black;"
                                required>
                        </div>

                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; color: white;">Nama Lengkap *</label>
                            <input type="text" name="nama" id="edit_nama"
                                style="width: calc(100% - 22px); padding: 10px; border: 1px solid #ccc; border-radius: 4px; background: white; color: black;"
                                required>
                        </div>

                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; color: white;">No. Telepon</label>
                            <input type="text" name="no_telepon" id="edit_no_telepon"
                                style="width: calc(100% - 22px); padding: 10px; border: 1px solid #ccc; border-radius: 4px; background: white; color: black;">
                        </div>

                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; color: white;">Role</label>
                            <select name="role" id="edit_role"
                                style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; background: white; color: black;">
                                <option value="member">Member</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>

                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; color: white;">Password Baru (Kosongkan
                                jika tidak ingin mengubah)</label>
                            <input type="password" name="password" id="edit_password"
                                style="width: calc(100% - 22px); padding: 10px; border: 1px solid #ccc; border-radius: 4px; background: white; color: black;"
                                placeholder="Password baru...">
                        </div>
                    </div>

                    <!-- Kolom Kanan -->
                    <div>
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; color: white;">Alamat</label>
                            <textarea name="address" id="edit_address" rows="3"
                                style="width: calc(100% - 22px); padding: 10px; border: 1px solid #ccc; border-radius: 4px; background: white; color: black; resize: vertical;"
                                placeholder="Alamat lengkap..."></textarea>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                            <div>
                                <label style="display: block; margin-bottom: 5px; color: white;">Kota</label>
                                <input type="text" name="kota" id="edit_kota"
                                    style="width: calc(100% - 22px); padding: 10px; border: 1px solid #ccc; border-radius: 4px; background: white; color: black;"
                                    placeholder="Kota">
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 5px; color: white;">Kode Pos</label>
                                <input type="number" name="kode_pos" id="edit_kode_pos"
                                    style="width: calc(100% - 22px); padding: 10px; border: 1px solid #ccc; border-radius: 4px; background: white; color: black;"
                                    placeholder="12345">
                            </div>
                        </div>

                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; color: white;">Provinsi</label>
                            <input type="text" name="provinsi" id="edit_provinsi"
                                style="width: calc(100% - 22px); padding: 10px; border: 1px solid #ccc; border-radius: 4px; background: white; color: black;"
                                placeholder="Provinsi">
                        </div>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" onclick="closeEditUserModal()"
                        style="background: #6b7280; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">
                        Batal
                    </button>
                    <button type="submit" name="edit_user"
                        style="background: #10b981; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">
                        <i class="fas fa-save"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Edit User Modal Functions
        function showEditUserModal() {
            const modal = document.getElementById('editUserModal');
            modal.style.display = 'block';

            // Ensure all form fields are enabled
            setTimeout(() => {
                const form = document.getElementById('editUserForm');
                const inputs = form.querySelectorAll('input, textarea, select');
                inputs.forEach(input => {
                    input.disabled = false;
                    input.readOnly = false;
                    input.style.pointerEvents = 'auto';
                    input.style.userSelect = 'text';
                });
            }, 100);
        }

        function closeEditUserModal() {
            document.getElementById('editUserModal').style.display = 'none';

            // Clear any error messages
            const errorAlerts = document.querySelectorAll('.error-alert');
            errorAlerts.forEach(alert => alert.remove());
        }

        // Edit User Function dengan data langsung dari PHP (lebih reliable)
        function editUser(userId, fallbackData = null) {
            console.log('Edit user called with ID:', userId, 'and data:', fallbackData);

            // Reset form terlebih dulu
            document.getElementById('editUserForm').reset();

            // Show modal
            showEditUserModal();

            // Set ID user
            document.getElementById('edit_user_id').value = userId;

            // Jika ada fallback data dari PHP, gunakan langsung
            if (fallbackData) {
                console.log('Using fallback data:', fallbackData);
                populateUserForm(fallbackData);
                return;
            }

            // Jika tidak ada fallback data, coba fetch dari server
            const modalContent = document.querySelector('#editUserModal > div');
            const originalContent = modalContent.innerHTML;

            // Show loading
            modalContent.innerHTML = '<div style="text-align: center; padding: 50px; color: white;"><i class="fas fa-spinner fa-spin"></i> Memuat data user...</div>';

            // Try to fetch data
            fetch('get_user.php?id=' + userId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }
                    return response.json();
                })
                .then(userData => {
                    // Restore content and populate
                    modalContent.innerHTML = originalContent;
                    populateUserForm(userData);
                })
                .catch(error => {
                    console.error('Fetch failed:', error);
                    // Restore content and show empty form
                    modalContent.innerHTML = originalContent;
                    document.getElementById('edit_user_id').value = userId;
                    showErrorMessage('Data tidak dapat dimuat otomatis. Silakan isi form manual.');
                });
        }

        // Fungsi untuk mengisi form user (simplified)
        function populateUserForm(userData) {
            console.log('Populating form with:', userData);

            // Tunggu sebentar untuk memastikan DOM ready
            setTimeout(() => {
                try {
                    document.getElementById('edit_user_id').value = userData.id || '';
                    document.getElementById('edit_email').value = userData.email || '';
                    document.getElementById('edit_nama').value = userData.nama || '';
                    document.getElementById('edit_no_telepon').value = userData.no_telepon || '';
                    document.getElementById('edit_address').value = userData.address || '';
                    document.getElementById('edit_kota').value = userData.kota || '';
                    document.getElementById('edit_provinsi').value = userData.provinsi || '';
                    document.getElementById('edit_kode_pos').value = userData.kode_pos || '';
                    document.getElementById('edit_role').value = userData.role || 'member';

                    console.log('Form populated successfully');

                    // Verify population
                    console.log('Nama field value:', document.getElementById('edit_nama').value);
                    console.log('Email field value:', document.getElementById('edit_email').value);

                } catch (error) {
                    console.error('Error populating form:', error);
                    showErrorMessage('Error mengisi form: ' + error.message);
                }
            }, 100);
        }

        // Fungsi untuk menampilkan pesan error
        function showErrorMessage(message) {
            const alertDiv = document.createElement('div');
            alertDiv.style.cssText = 'background: #fbbf24; color: #92400e; padding: 10px; border-radius: 4px; margin-bottom: 15px; text-align: center;';
            alertDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + message;

            const form = document.getElementById('editUserForm');
            const existingAlert = form.querySelector('.error-alert');
            if (existingAlert) {
                existingAlert.remove();
            }

            alertDiv.className = 'error-alert';
            form.insertBefore(alertDiv, form.firstChild);

            // Auto-hide setelah 5 detik
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }

        // Fungsi fallback untuk load data user secara langsung
        function loadUserDataDirectly(userId) {
            const modalContent = document.querySelector('#editUserModal > div');
            const originalContent = modalContent.innerHTML;

            // Tampilkan form kosong dan biarkan user mengisi manual
            modalContent.innerHTML = originalContent;

            // Set ID user
            document.getElementById('edit_user_id').value = userId;

            // Tampilkan pesan bahwa data harus diisi manual
            showErrorMessage('Data tidak dapat dimuat otomatis. Silakan isi form secara manual.');
        }

        // Close modal when clicking outside
        window.onclick = function (event) {
            const modal = document.getElementById('editUserModal');
            if (event.target == modal) {
                closeEditUserModal();
            }
        }

        // ESC key to close modal
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeEditUserModal();
            }
        });

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function () {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.remove();
                    }, 500);
                }, 5000);
            });

            // Debug: Test modal form
            console.log('Page loaded, checking modal form elements...');
            const formElements = {
                modal: document.getElementById('editUserModal'),
                form: document.getElementById('editUserForm'),
                edit_user_id: document.getElementById('edit_user_id'),
                edit_email: document.getElementById('edit_email'),
                edit_nama: document.getElementById('edit_nama'),
                edit_no_telepon: document.getElementById('edit_no_telepon'),
                edit_address: document.getElementById('edit_address'),
                edit_kota: document.getElementById('edit_kota'),
                edit_provinsi: document.getElementById('edit_provinsi'),
                edit_kode_pos: document.getElementById('edit_kode_pos'),
                edit_role: document.getElementById('edit_role')
            };

            Object.keys(formElements).forEach(key => {
                if (formElements[key]) {
                    console.log(`✓ ${key} found`);
                } else {
                    console.error(`✗ ${key} NOT FOUND`);
                }
            });
        });

        // Debug function untuk test modal
        function testModal() {
            const testData = {
                id: 1,
                email: 'test@example.com',
                nama: 'Test User',
                no_telepon: '081234567890',
                address: 'Test Address',
                kota: 'Test City',
                provinsi: 'Test Province',
                kode_pos: '12345',
                role: 'member'
            };

            editUser(1, testData);
        }
    </script>
</body>