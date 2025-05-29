<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../auth.php';
require_once '../db.php';

// Check if user is admin
function requireAdminAccess()
{
    if (!isLoggedIn() || getLoggedUser()['role'] !== 'admin') {
        header("Location: ../login.php");
        exit;
    }
}

requireAdminAccess();

$user = getLoggedUser();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_shipping'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $cost = floatval($_POST['cost']);
        $estimated_days = trim($_POST['estimated_days']);

        $stmt = $pdo->prepare("INSERT INTO shipping_options (name, description, cost, estimated_days) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $description, $cost, $estimated_days]);

        $_SESSION['success'] = "Opsi pengiriman berhasil ditambahkan!";
        header("Location: shipping_management.php");
        exit;
    } elseif (isset($_POST['update_shipping'])) {
        $id = intval($_POST['id']);
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $cost = floatval($_POST['cost']);
        $estimated_days = trim($_POST['estimated_days']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        $stmt = $pdo->prepare("UPDATE shipping_options SET name = ?, description = ?, cost = ?, estimated_days = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$name, $description, $cost, $estimated_days, $is_active, $id]);

        $_SESSION['success'] = "Opsi pengiriman berhasil diupdate!";
        header("Location: shipping_management.php");
        exit;
    } elseif (isset($_POST['delete_shipping'])) {
        $id = intval($_POST['id']);

        $stmt = $pdo->prepare("DELETE FROM shipping_options WHERE id = ?");
        $stmt->execute([$id]);

        $_SESSION['success'] = "Opsi pengiriman berhasil dihapus!";
        header("Location: shipping_management.php");
        exit;
    }
}

// Get all shipping options
$stmt = $pdo->query("SELECT * FROM shipping_options ORDER BY cost ASC");
$shipping_options = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Jasa Pengiriman - NADA BookStore Admin</title>
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

    .shipping-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }

    .shipping-table th,
    .shipping-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #475569;
        color: white;
    }

    .shipping-table th {
        background: #334155;
        font-weight: 600;
        color: #f8fafc;
    }

    .shipping-table td {
        background: #475569;
    }

    .badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }

    .badge-success {
        background: #10b981;
        color: white;
    }

    .badge-danger {
        background: #ef4444;
        color: white;
    }

    .shipping-card {
        background: #334155;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 15px;
        color: white;
    }

    .shipping-card h4 {
        margin: 0 0 10px 0;
        color: #f8fafc;
        font-size: 1.1rem;
    }

    .shipping-card p {
        margin: 5px 0;
        color: #94a3b8;
        font-size: 0.9rem;
    }

    .shipping-card .price {
        color: #10b981;
        font-weight: bold;
        font-size: 1rem;
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

    @media (max-width: 768px) {
        .shipping-grid {
            grid-template-columns: 1fr !important;
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
                <li class="active">
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
                    <h1>Kelola Jasa Pengiriman</h1>
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
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"
                        style="background: #d4edda; color: #155724; padding: 15px; margin: 15px 0; border-radius: 5px;">
                        <i class="fas fa-check-circle"></i>
                        <?= $_SESSION['success'] ?>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <!-- Main Grid Layout -->
                <div class="shipping-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                    <!-- Add New Shipping Option -->
                    <div style="background: #1e293b; padding: 20px; border-radius: 8px;">
                        <h3 style="color: white; margin-bottom: 20px;">
                            <i class="fas fa-plus-circle"></i>
                            Tambah Opsi Pengiriman
                        </h3>
                        <form method="POST" action="">
                            <div style="margin-bottom: 15px;">
                                <label style="color: white; display: block; margin-bottom: 5px;">Nama Pengiriman</label>
                                <input type="text" name="name"
                                    style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"
                                    placeholder="Contoh: Reguler, Express, Kargo" required>
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label style="color: white; display: block; margin-bottom: 5px;">Biaya (Rp)</label>
                                <input type="number" name="cost" step="0.01"
                                    style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"
                                    placeholder="15000" required>
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label style="color: white; display: block; margin-bottom: 5px;">Deskripsi</label>
                                <textarea name="description" rows="3"
                                    style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"
                                    placeholder="Deskripsi layanan pengiriman..." required></textarea>
                            </div>
                            <div style="margin-bottom: 20px;">
                                <label style="color: white; display: block; margin-bottom: 5px;">Estimasi Waktu</label>
                                <input type="text" name="estimated_days"
                                    style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"
                                    placeholder="Contoh: 3-5 hari kerja" required>
                            </div>
                            <button type="submit" name="add_shipping"
                                style="background: #10b981; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; width: 100%;">
                                <i class="fas fa-plus"></i> Tambah Opsi Pengiriman
                            </button>
                        </form>
                    </div>

                    <!-- Shipping Options List -->
                    <div style="background: #1e293b; padding: 20px; border-radius: 8px;">
                        <h3 style="color: white; margin-bottom: 20px;">
                            <i class="fas fa-list"></i>
                            Daftar Opsi Pengiriman (<?= count($shipping_options) ?>)
                        </h3>

                        <?php if (empty($shipping_options)): ?>
                            <p style="color: #64748b; text-align: center; padding: 40px;">
                                <i class="fas fa-truck" style="font-size: 3rem; display: block; margin-bottom: 15px;"></i>
                                Belum ada opsi pengiriman
                            </p>
                        <?php else: ?>
                            <div style="max-height: 500px; overflow-y: auto;">
                                <?php foreach ($shipping_options as $option): ?>
                                    <div class="shipping-card">
                                        <div style="display: flex; justify-content: space-between; align-items: start;">
                                            <div style="flex: 1;">
                                                <h4><?= htmlspecialchars($option['name']) ?></h4>
                                                <p><?= htmlspecialchars($option['description']) ?></p>
                                                <p class="price">Rp <?= number_format($option['cost'], 0, ',', '.') ?></p>
                                                <p>Estimasi: <?= htmlspecialchars($option['estimated_days']) ?></p>
                                            </div>
                                            <div style="margin-left: 15px;">
                                                <?php if ($option['is_active']): ?>
                                                    <span class="badge badge-success">Aktif</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">Nonaktif</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="btn-group">
                                            <button class="btn-edit"
                                                onclick="editShipping(<?= htmlspecialchars(json_encode($option)) ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <form method="POST" style="flex: 1; display: inline;"
                                                onsubmit="return confirm('Yakin ingin menghapus opsi pengiriman ini?')">
                                                <input type="hidden" name="id" value="<?= $option['id'] ?>">
                                                <button type="submit" name="delete_shipping" class="btn-delete"
                                                    style="width: 100%;">
                                                    <i class="fas fa-trash"></i> Hapus
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal"
        style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
        <div
            style="background-color: #1e293b; margin: 5% auto; padding: 20px; border-radius: 8px; width: 80%; max-width: 600px; color: white;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0;">
                    <i class="fas fa-edit"></i> Edit Opsi Pengiriman
                </h3>
                <button onclick="closeEditModal()"
                    style="background: none; border: none; color: white; font-size: 24px; cursor: pointer;">&times;</button>
            </div>

            <form method="POST" action="">
                <input type="hidden" id="edit_id" name="id">
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px;">Nama Pengiriman</label>
                    <input type="text" id="edit_name" name="name"
                        style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;" required>
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px;">Biaya (Rp)</label>
                    <input type="number" id="edit_cost" name="cost" step="0.01"
                        style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;" required>
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px;">Deskripsi</label>
                    <textarea id="edit_description" name="description" rows="3"
                        style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"
                        required></textarea>
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px;">Estimasi Waktu</label>
                    <input type="text" id="edit_estimated_days" name="estimated_days"
                        style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;" required>
                </div>
                <div style="margin-bottom: 20px;">
                    <label style="display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" id="edit_is_active" name="is_active">
                        <span>Aktif</span>
                    </label>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" onclick="closeEditModal()"
                        style="background: #6b7280; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">
                        Batal
                    </button>
                    <button type="submit" name="update_shipping"
                        style="background: #10b981; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">
                        <i class="fas fa-save"></i> Update
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function editShipping(option) {
            document.getElementById('edit_id').value = option.id;
            document.getElementById('edit_name').value = option.name;
            document.getElementById('edit_cost').value = option.cost;
            document.getElementById('edit_description').value = option.description;
            document.getElementById('edit_estimated_days').value = option.estimated_days;
            document.getElementById('edit_is_active').checked = option.is_active == 1;

            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Close modal when clicking outside
        document.getElementById('editModal').addEventListener('click', function (e) {
            if (e.target === this) {
                closeEditModal();
            }
        });

        // ESC key to close modal
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeEditModal();
            }
        });
    </script>
</body>

</html>