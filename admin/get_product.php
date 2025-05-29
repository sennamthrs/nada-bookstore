<?php
// Pastikan session sudah dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include file auth.php dan db.php
require_once '../auth.php';
require_once '../db.php';

// Set header untuk JSON response
header('Content-Type: application/json');

// Fungsi untuk memeriksa apakah user adalah admin
function requireAdminLogin() {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $user = getLoggedUser();
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
}

// Jalankan pengecekan admin
requireAdminLogin();

// Validasi method request
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Validasi parameter ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid product ID']);
    exit;
}

$productId = (int)$_GET['id'];

try {
    // Query untuk mengambil data produk berdasarkan ID
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.id = ?
    ");
    
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found']);
        exit;
    }
    
    // Pastikan semua field ada nilai default
    $product['stock'] = $product['stock'] ?? 0;
    $product['image_url'] = $product['image_url'] ?? '';
    $product['category_name'] = $product['category_name'] ?? 'Tidak ada kategori';
    
    // Return data produk dalam format JSON
    echo json_encode($product);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>