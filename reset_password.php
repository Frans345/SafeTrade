<?php
session_start();
require_once 'includes/db.php';

$error = '';
$token = $_GET['token'] ?? '';
$validToken = false;

// 1. Get token from URL and check database
if (empty($token)) {
    $error = "Invalid or missing password reset token.";
} else {
    // Fetch user and expiry time based on token
    $stmt = $pdo->prepare('SELECT user_id, reset_expires FROM users WHERE reset_token = ?');
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    // Check if token exists and hasn't expired yet
    if ($user && strtotime($user['reset_expires']) > time()) {
        $validToken = true;
    } else {
        $error = "This password reset link is invalid or has expired.";
    }
}

// 2. Process password update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $newPassword = $_POST['password'] ?? '';
    
    if (strlen($newPassword) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        // Securely hash the new password
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update password and clear the reset token fields
        // NOTE: Change 'password' to 'password_hash' below if that is your column name
        $update = $pdo->prepare('UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE reset_token = ?');
        
        if ($update->execute([$hash, $token])) {
            $_SESSION['flash_success'] = "Password reset successful! You can now log in.";
            header('Location: login.php');
            exit;
        } else {
            $error = "Error saving your new password. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password - SafeTrade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .auth-container { max-width: 450px; margin-top: 100px; }
        .card { border-radius: 15px; }
    </style>
</head>
<body>

<div class="container auth-container">
    <div class="card shadow-sm border-0 p-4">
        <div class="text-center mb-4">
            <h3 style="color: #0f172a; font-weight: 800;">Create New Password</h3>
            <p class="text-muted">Enter a secure password for your SafeTrade account.</p>
        </div>

        <?php if ($error): ?> 
            <div class="alert alert-danger text-center fw-bold"><?= htmlspecialchars($error) ?></div> 
            <div class="text-center mt-3">
                <a href="forgot_password.php" class="text-success text-decoration-none fw-bold">Request a new link</a>
            </div>
        <?php endif; ?>

        <?php if ($validToken): ?>
        <form action="reset_password.php?token=<?= htmlspecialchars($token) ?>" method="POST">
            <div class="mb-4">
                <label class="form-label text-muted fw-bold small">New Password</label>
                <input type="password" class="form-control form-control-lg border-0 bg-light" name="password" placeholder="Min. 6 characters" required autofocus>
            </div>
            
            <button type="submit" class="btn btn-success w-100 btn-lg shadow-sm" style="background-color: #10b981; border: none; font-weight: bold;">Update Password</button>
        </form>
        <?php endif; ?>
        
        <div class="text-center mt-4">
            <a href="login.php" class="text-secondary small text-decoration-none">&larr; Back to Login</a>
        </div>
    </div>
</div>

</body>
</html>