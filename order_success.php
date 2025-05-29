<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'auth.php';
require_once 'db.php';

// Pastikan user sudah login
if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

$order_id = $_GET['order_id'] ?? '';

// Ambil data order jika ada
$order = null;
if ($order_id) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Berhasil - NADA BookStore</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        .success-container {
            max-width: 600px;
            margin: 50px auto;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .success-icon {
            font-size: 64px;
            color: #22c55e;
            margin-bottom: 20px;
        }
        .success-title {
            font-size: 28px;
            color: #1f2937;
            margin-bottom: 10px;
        }
        .success-message {
            font-size: 16px;
            color: #6b7280;
            margin-bottom: 30px;
        }
        .order-info {
            background: #f9fafb;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: left;
        }
        .order-info h3 {
            margin-top: 0;
            color: #1f2937;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            padding: 5px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            margin: 10px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        .btn-primary:hover {
            background: #2563eb;
        }
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        .btn-secondary:hover {
            background: #4b5563;
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        
        <h1 class="success-title">Pesanan Berhasil Dibuat!</h1>
        
        <p class="success-message">
            Terima kasih atas pesanan Anda. Kami akan segera memproses pesanan Anda.
        </p>
        
        <?php if ($order): ?>
            <div class="order-info">
                <h3><i class="fas fa-receipt"></i> Detail Pesanan</h3>
                
                <div class="info-row">
                    <span><strong>Nomor Pesanan:</strong></span>
                    <span><?= htmlspecialchars($order['order_number']) ?></span>
                </div>
                
                <div class="info-row">
                    <span><strong>Total Pembayaran:</strong></span>
                    <span>Rp. <?= number_format($order['total_amount'], 0, ',', '.') ?></span>
                </div>
                
                <div class="info-row">
                    <span><strong>Status:</strong></span>
                    <span><?= ucfirst($order['status']) ?></span>
                </div>
                
                <div class="info-row">
                    <span><strong>Metode Pembayaran:</strong></span>
                    <span><?= ucfirst(str_replace('_', ' ', $order['payment_method'])) ?></span>
                </div>
                
                <div class="info-row">
                    <span><strong>Tanggal Pesanan:</strong></span>
                    <span><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></span>
                </div>
            </div>
        <?php else: ?>
            <div class="order-info">
                <p><strong>Order ID:</strong> <?= htmlspecialchars($order_id) ?></p>
                <p style="color: #dc2626;">Data pesanan tidak ditemukan. Silakan hubungi customer service.</p>
            </div>
        <?php endif; ?>
        
        <div style="margin-top: 30px;">
            <a href="index.php" class="btn btn-primary">
                <i class="fas fa-home"></i> Kembali ke Beranda
            </a>
            
            <?php if ($order): ?>
                <a href="my_orders.php" class="btn btn-secondary">
                    <i class="fas fa-list"></i> Lihat Semua Pesanan
                </a>
            <?php endif; ?>
        </div>
        
        <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
            <p style="font-size: 14px; color: #6b7280;">
                <i class="fas fa-info-circle"></i>
                Kami akan mengirimkan email konfirmasi ke alamat email Anda.
                Jika ada pertanyaan, silakan hubungi customer service kami.
            </p>
        </div>
    </div>
</body>
</html>