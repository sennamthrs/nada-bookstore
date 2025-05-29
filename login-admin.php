<?php
require_once 'auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = 'admin'; // role tetap
    $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (!$email) {
        $error = "Please enter a valid email.";
    } elseif (empty($password)) {
        $error = "Please enter your password.";
    } else {
        if (loginUser($email, $password, $role)) {
            header('Location: admin/dashboard.php'); // halaman admin
            exit;
        } else {
            $error = "Invalid email or password.";
        }
    }
} elseif (isLoggedIn()) {
    $user = getLoggedUser();
    if ($user['role'] === 'admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: .php');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Admin Login</title>
    <link rel="stylesheet" href="styles.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
</head>

<body>
    <div class="login-container">
        <div class="card-title" style="text-align: center;">
            <h1 style="text-align: center; width: 100%;">
                <span style="color: var(--primary-color); margin-right: 10px;">
                    <i class="fas fa-sign-in-alt"></i>
                </span>
                (ADMIN)
            </h1>
        </div>
        <?php if ($error): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST" class="login-form">
            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" class="form-control" required
                        value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" />
                </div>

                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" class="form-control" required />
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
                <div class="auth-links">
                    Anda bukan admin? <a href="login.php">Masuk di sini</a>
                </div>
            </form>
    </div>
</body>

</html>