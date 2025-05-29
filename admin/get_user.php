<?php
// Pastikan session sudah dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set header untuk JSON response
header('Content-Type: application/json');

try {
    // Include file auth.php dan db.php dengan error handling
    if (!file_exists('../auth.php')) {
        throw new Exception('auth.php file not found');
    }
    if (!file_exists('../db.php')) {
        throw new Exception('db.php file not found');
    }

    require_once '../auth.php';
    require_once '../db.php';

    // Cek apakah fungsi isLoggedIn ada
    if (!function_exists('isLoggedIn')) {
        throw new Exception('isLoggedIn function not found');
    }

    // Fungsi untuk memeriksa apakah user adalah admin
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized - Please login']);
        exit;
    }

    $user = getLoggedUser();
    if (!$user || $user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden - Admin access required']);
        exit;
    }

    // Cek apakah parameter ID ada
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid user ID parameter']);
        exit;
    }

    $userId = intval($_GET['id']);

    // Cek apakah koneksi database ada
    if (!isset($pdo)) {
        throw new Exception('Database connection not available');
    }

    // Ambil data user berdasarkan ID
    $stmt = $pdo->prepare("SELECT id, email, nama, no_telepon, address, kota, provinsi, kode_pos, role, updated_at FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$userData) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found with ID: ' . $userId]);
        exit;
    }

    // Return data user dalam format JSON
    echo json_encode($userData);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error: ' . $e->getMessage(),
        'file' => __FILE__,
        'line' => $e->getLine()
    ]);
}
?>