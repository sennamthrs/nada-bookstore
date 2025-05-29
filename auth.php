<?php
// auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db.php';

function loginUser($email, $password, $role = null)
{
    global $pdo;

    try {
        // Query untuk mendapatkan user dari database
        $query = "SELECT * FROM users WHERE email = ?";
        $params = [$email];

        // Jika role ditentukan, tambahkan ke query
        if ($role) {
            $query .= " AND role = ?";
            $params[] = $role;
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Simpan informasi user ke session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['nama'];
            $_SESSION['user_role'] = $user['role'];

            // Debug - log data yang disimpan
            error_log("Login successful, session data: " . json_encode($_SESSION));

            return true;
        }
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
    }

    return false;
}

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

function getLoggedUser()
{
    if (!isLoggedIn()) {
        return null;
    }

    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function logout()
{
    // Hapus semua data session
    session_unset();
    session_destroy();
}
?>