<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'auth.php';
require_once 'db.php';

// Set response header untuk JSON
header('Content-Type: application/json');

// Fungsi untuk memeriksa apakah user login
function requireLogin()
{
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Anda harus login terlebih dahulu.']);
        exit;
    }
}

// Jalankan pengecekan login
requireLogin();
$user = getLoggedUser();

// Validasi request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metode request tidak valid.']);
    exit;
}

// Validasi input
$order_id = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
$rating = isset($_POST['rating']) ? (int) $_POST['rating'] : 0;
$review_text = isset($_POST['review_text']) ? trim($_POST['review_text']) : '';

// Validasi data
if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID pesanan tidak valid.']);
    exit;
}

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Rating harus antara 1-5 bintang.']);
    exit;
}

if (empty($review_text)) {
    echo json_encode(['success' => false, 'message' => 'Ulasan tidak boleh kosong.']);
    exit;
}

if (strlen($review_text) < 10) {
    echo json_encode(['success' => false, 'message' => 'Ulasan minimal 10 karakter.']);
    exit;
}

if (strlen($review_text) > 1000) {
    echo json_encode(['success' => false, 'message' => 'Ulasan maksimal 1000 karakter.']);
    exit;
}

try {
    // Mulai transaction
    $pdo->beginTransaction();

    // Periksa apakah pesanan exists, milik user ini, dan statusnya delivered
    $stmt = $pdo->prepare("
        SELECT id, order_number, status 
        FROM orders 
        WHERE id = ? AND user_id = ? AND status = 'delivered'
    ");
    $stmt->execute([$order_id, $user['id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception('Pesanan tidak ditemukan, bukan milik Anda, atau belum selesai.');
    }

    // Periksa apakah sudah pernah memberikan review untuk pesanan ini
    $stmt = $pdo->prepare("SELECT id FROM order_reviews WHERE order_id = ? AND user_id = ?");
    $stmt->execute([$order_id, $user['id']]);
    $existing_review = $stmt->fetch();

    if ($existing_review) {
        throw new Exception('Anda sudah memberikan ulasan untuk pesanan ini.');
    }

    // Insert review baru
    $stmt = $pdo->prepare("
        INSERT INTO order_reviews (order_id, user_id, rating, review_text) 
        VALUES (?, ?, ?, ?)
    ");
    $result = $stmt->execute([$order_id, $user['id'], $rating, $review_text]);

    if (!$result) {
        throw new Exception('Gagal menyimpan ulasan.');
    }

    // Update status review di tabel orders (jika kolom ada)
    $stmt = $pdo->prepare("UPDATE orders SET review_status = 'reviewed' WHERE id = ?");
    $stmt->execute([$order_id]);

    // Commit transaction
    $pdo->commit();

    // Response sukses
    echo json_encode([
        'success' => true,
        'message' => 'Terima kasih! Ulasan Anda telah berhasil disimpan.',
        'data' => [
            'order_number' => $order['order_number'],
            'rating' => $rating,
            'review_text' => $review_text,
            'review_date' => date('Y-m-d H:i:s')
        ]
    ]);

} catch (Exception $e) {
    // Rollback transaction jika ada error
    $pdo->rollBack();

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>