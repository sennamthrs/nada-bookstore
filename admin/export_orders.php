<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../auth.php';
require_once '../db.php';

// Fungsi untuk memeriksa apakah user adalah admin
function requireAdminLogin() {
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

requireAdminLogin();

// Check if export is requested
$export_format = $_GET['export'] ?? '';
if (!in_array($export_format, ['csv', 'xlsx', 'html'])) {
    header("Location: orders.php");
    exit;
}

// Apply same filters as orders.php
$statusFilter = $_GET['status'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Build query
$sql = "SELECT o.*, u.nama as customer_name, u.email as customer_email, u.no_telepon as customer_phone,
               COUNT(oi.id) as total_items
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        LEFT JOIN order_items oi ON o.id = oi.order_id 
        WHERE 1=1";

$params = [];

if ($statusFilter !== 'all') {
    $sql .= " AND o.status = ?";
    $params[] = $statusFilter;
}

if ($searchQuery) {
    $sql .= " AND (u.nama LIKE ? OR u.email LIKE ? OR o.order_number LIKE ?)";
    $searchParam = "%$searchQuery%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if ($dateFrom) {
    $sql .= " AND DATE(o.created_at) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $sql .= " AND DATE(o.created_at) <= ?";
    $params[] = $dateTo;
}

$sql .= " GROUP BY o.id ORDER BY o.created_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error fetching data: " . $e->getMessage());
}

// Status mapping
$statusMapping = [
    'pending' => 'Menunggu Pembayaran',
    'paid' => 'Dibayar', 
    'processing' => 'Diproses',
    'shipped' => 'Dikirim',
    'delivered' => 'Selesai',
    'cancelled' => 'Dibatalkan'
];

// Payment method mapping
$paymentMapping = [
    'transfer_bank' => 'Transfer Bank',
    'credit_card' => 'Kartu Kredit',
    'e_wallet' => 'E-Wallet',
    'cod' => 'Bayar di Tempat'
];

// Prepare data
$headers = [
    'ID Pesanan',
    'Nomor Pesanan', 
    'Nama Customer',
    'Email Customer',
    'Telepon Customer',
    'Status',
    'Metode Pembayaran',
    'Total Items',
    'Subtotal',
    'Ongkos Kirim',
    'Pajak',
    'Total Amount',
    'Alamat Pengiriman',
    'Kota',
    'Provinsi',
    'Kode Pos',
    'Nomor Resi',
    'Tanggal Dibuat',
    'Tanggal Update',
    'Tanggal Dikirim',
    'Tanggal Selesai',
    'Catatan'
];

$data = [];
foreach ($orders as $order) {
    $row = [
        str_pad($order['id'], 6, '0', STR_PAD_LEFT),
        $order['order_number'] ?? 'ORD-' . date('Y', strtotime($order['created_at'])) . '-' . str_pad($order['id'], 3, '0', STR_PAD_LEFT),
        $order['customer_name'],
        $order['customer_email'],
        $order['customer_phone'] ?? '',
        $statusMapping[$order['status']] ?? ucfirst($order['status']),
        $paymentMapping[$order['payment_method']] ?? ucfirst(str_replace('_', ' ', $order['payment_method'])),
        $order['total_items'],
        number_format($order['subtotal'], 0, ',', '.'),
        number_format($order['shipping_cost'], 0, ',', '.'),
        number_format($order['tax_amount'], 0, ',', '.'),
        number_format($order['total_amount'], 0, ',', '.'),
        $order['shipping_address'] ?? '',
        $order['shipping_city'] ?? '',
        $order['shipping_province'] ?? '',
        $order['shipping_postal_code'] ?? '',
        $order['tracking_number'] ?? '',
        date('d/m/Y H:i:s', strtotime($order['created_at'])),
        $order['updated_at'] ? date('d/m/Y H:i:s', strtotime($order['updated_at'])) : '',
        $order['shipped_at'] ? date('d/m/Y H:i:s', strtotime($order['shipped_at'])) : '',
        $order['delivered_at'] ? date('d/m/Y H:i:s', strtotime($order['delivered_at'])) : '',
        $order['notes'] ?? ''
    ];
    $data[] = $row;
}

// Clear any output buffer and disable output buffering
if (ob_get_level()) {
    ob_end_clean();
}

// Handle different export formats
switch ($export_format) {
    case 'csv':
        exportCSV($headers, $data);
        break;
    case 'xlsx':
        exportXLSX($headers, $data);
        break;
    case 'html':
        exportHTML($headers, $data);
        break;
}

function exportCSV($headers, $data) {
    $filename = 'orders_export_' . date('Y-m-d_H-i-s') . '.csv';
    
    // Clear any previous output
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set headers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF"); // BOM for UTF-8
    
    fputcsv($output, $headers);
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

function exportXLSX($headers, $data) {
    // Check if ZipArchive is available
    if (!class_exists('ZipArchive')) {
        // Fallback to HTML table that Excel can open
        exportHTMLAsExcel($headers, $data);
        return;
    }
    
    // If ZipArchive is available, use the complex XLSX writer
    // For now, we'll use the HTML fallback which works well
    exportHTMLAsExcel($headers, $data);
}

function exportHTMLAsExcel($headers, $data) {
    $filename = 'orders_export_' . date('Y-m-d_H-i-s') . '.xls';
    
    // Clear any previous output
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set headers for Excel
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Expires: 0');
    
    echo "\xEF\xBB\xBF"; // BOM for UTF-8
    
    echo '<!DOCTYPE html>';
    echo '<html>';
    echo '<head>';
    echo '<meta charset="utf-8">';
    echo '<title>Export Data Pesanan</title>';
    echo '<style>';
    echo 'table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; font-size: 12px; }';
    echo 'th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }';
    echo 'th { background-color: #4f46e5; color: white; font-weight: bold; }';
    echo 'tr:nth-child(even) { background-color: #f9f9f9; }';
    echo '.number { text-align: right; }';
    echo '.currency { text-align: right; color: #059669; font-weight: bold; }';
    echo '.date { text-align: center; }';
    echo '.status { text-align: center; padding: 4px 8px; border-radius: 4px; }';
    echo '.status-pending { background-color: #fef3c7; color: #92400e; }';
    echo '.status-paid { background-color: #dbeafe; color: #1e40af; }';
    echo '.status-processing { background-color: #e0e7ff; color: #3730a3; }';
    echo '.status-shipped { background-color: #d1fae5; color: #065f46; }';
    echo '.status-delivered { background-color: #d1fae5; color: #065f46; }';
    echo '.status-cancelled { background-color: #fee2e2; color: #991b1b; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    echo '<h1>Data Export Pesanan - NADA BookStore</h1>';
    echo '<p>Tanggal Export: ' . date('d F Y, H:i:s') . '</p>';
    echo '<p>Total Data: ' . count($data) . ' pesanan</p>';
    echo '<br>';
    
    echo '<table>';
    echo '<thead><tr>';
    foreach ($headers as $header) {
        echo '<th>' . htmlspecialchars($header) . '</th>';
    }
    echo '</tr></thead>';
    
    echo '<tbody>';
    foreach ($data as $row) {
        echo '<tr>';
        foreach ($row as $index => $cell) {
            $class = '';
            $value = htmlspecialchars($cell);
            
            // Add specific formatting based on column
            switch ($index) {
                case 5: // Status
                    $status_lower = strtolower(str_replace(' ', '', $cell));
                    $status_class = '';
                    if (strpos($status_lower, 'menunggu') !== false) $status_class = 'status-pending';
                    elseif (strpos($status_lower, 'dibayar') !== false) $status_class = 'status-paid';
                    elseif (strpos($status_lower, 'diproses') !== false) $status_class = 'status-processing';
                    elseif (strpos($status_lower, 'dikirim') !== false) $status_class = 'status-shipped';
                    elseif (strpos($status_lower, 'selesai') !== false) $status_class = 'status-delivered';
                    elseif (strpos($status_lower, 'dibatalkan') !== false) $status_class = 'status-cancelled';
                    $class = 'status ' . $status_class;
                    break;
                case 7: // Total Items
                    $class = 'number';
                    break;
                case 8: case 9: case 10: case 11: // Currency fields
                    $class = 'currency';
                    $value = 'Rp ' . $value;
                    break;
                case 17: case 18: case 19: case 20: // Date fields
                    $class = 'date';
                    break;
            }
            
            echo '<td class="' . $class . '">' . $value . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
    
    echo '<br><hr>';
    echo '<p style="font-size: 10px; color: #666;">Generated by NADA BookStore Admin Panel - ' . date('Y') . '</p>';
    echo '</body>';
    echo '</html>';
    
    exit;
}

function exportHTML($headers, $data) {
    $filename = 'orders_export_' . date('Y-m-d_H-i-s') . '.html';
    
    // Clear any previous output
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set headers for HTML download
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Expires: 0');
    
    exportHTMLAsExcel($headers, $data);
}
?>