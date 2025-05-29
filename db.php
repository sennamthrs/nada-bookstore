<?php
// db.php - Database connection using PDO
$host = 'localhost'; // change if needed
$dbname = 'bookstore'; // your database name
$username = 'root'; // your db user
$password = ''; // your db password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);

    // Set error mode to exceptions
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Set default fetch mode to associative array
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Disable emulated prepared statements for better security
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    // Set MySQL SQL mode to strict for better data integrity
    $pdo->exec("SET sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'");

} catch (PDOException $e) {
    // Log error instead of displaying it in production
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please check configuration.");
}

// Optional: Test connection function for debugging
function testDatabaseConnection($pdo)
{
    try {
        $stmt = $pdo->query("SELECT 1 as test");
        $result = $stmt->fetch();
        return $result['test'] === 1;
    } catch (Exception $e) {
        return false;
    }
}

// Optional: Function to check if table exists
function tableExists($pdo, $tableName)
{
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$tableName]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}
date_default_timezone_set('Asia/Jakarta');
$pdo->exec("SET time_zone = '+07:00'");
?>