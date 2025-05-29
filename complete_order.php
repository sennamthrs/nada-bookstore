<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'auth.php';
require_once 'db.php';

// Fungsi untuk memeriksa apakah user login
function requireLogin()
{
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
}

// Jalankan pengecekan login
requireLogin();
$user = getLoggedUser();

// Validasi request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: my_orders.php");
    exit;
}

// Validasi parameter order_id
if (!isset($_POST['order_id']) || !is_numeric($_POST['order_id'])) {
    $_SESSION['error_message'] = 'ID pesanan tidak valid.';
    header("Location: my_orders.php");
    exit;
}

$order_id = (int) $_POST['order_id'];

try {
    // Mulai transaction
    $pdo->beginTransaction();

    // Periksa apakah pesanan exists dan milik user ini
    $stmt = $pdo->prepare("
        SELECT id, status, order_number 
        FROM orders 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$order_id, $user['id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception('Pesanan tidak ditemukan atau Anda tidak memiliki akses ke pesanan ini.');
    }

    // Periksa apakah status pesanan adalah 'shipped'
    if ($order['status'] !== 'shipped') {
        throw new Exception('Pesanan hanya dapat diselesaikan jika status adalah "Dikirim".');
    }

    // Update status pesanan menjadi 'delivered' dan set delivered_at
    $stmt = $pdo->prepare("
        UPDATE orders 
        SET status = 'delivered', 
            delivered_at = NOW(),
            updated_at = NOW()
        WHERE id = ? AND user_id = ?
    ");

    $result = $stmt->execute([$order_id, $user['id']]);

    if (!$result) {
        throw new Exception('Gagal mengupdate status pesanan.');
    }

    // Commit transaction
    $pdo->commit();

    // Set success message
    $_SESSION['success_message'] = 'Pesanan #' . $order['order_number'] . ' telah berhasil diselesaikan. Terima kasih telah berbelanja!';

    // Redirect ke halaman my_orders.php
    header("Location: my_orders.php");
    exit;

} catch (Exception $e) {
    // Rollback transaction jika ada error
    $pdo->rollBack();

    // Set error message
    $_SESSION['error_message'] = $e->getMessage();

    // Redirect ke halaman my_orders.php
    header("Location: my_orders.php");
    exit;
}
?>