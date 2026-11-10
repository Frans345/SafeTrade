<?php
session_start();
require_once 'includes/db.php';

$error = '';
$success = '';
$simulationLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $error = 'Please enter your email address.';
    } else {
        // 1. check if email exists in the database
        $stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            // 2. generate secure random token
            $token = bin2hex(random_bytes(32));
            // 3. set expiration time to 1 hour from now
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // 4. save token to database
            $update = $pdo->prepare('UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?');
            $update->execute([$token, $expires, $email]);

            // 5. THE SIMULATION: no email, generate link to click
            $resetLink = "http://localhost/SafeTrade/reset_password.php?token=" . $token;
            
            $success = "If an account matches that email, a password reset link has been sent.";
            
            //local testing
            $simulationLink = "
                <div class='alert alert-warning mt-4 shadow-sm border-warning'>
                    <strong>🛠️ Developer Simulation Mode:</strong><br>
                    In production, this link would be emailed. For local testing, click here to reset:<br>
                    <a href='$resetLink' class='fw-bold' style='word-break: break-all;'>$resetLink</a>
                </div>";
        } else {
            //success message
            $success = "If an account matches that email, a password reset link has been sent.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password - SafeTrade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .auth-container { max-width: 450px; margin-top: 80px; }
    </style>
</head>
<body>
<div class="container auth-container">
    <div class="card shadow-sm border-0 p-4">
        <h3 class="text-center mb-3" style="color: #0f172a; font-weight: bold;">Reset Password</h3>
        <p class="text-muted text-center mb-4">Enter your email and we will send you a secure reset link.</p>

        <?php if ($error): ?> <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div> <?php endif; ?>
        <?php if ($success): ?> <div class="alert alert-success"><?= htmlspecialchars($success) ?></div> <?php endif; ?>
        
        <?= $simulationLink ?> <!--developer link -->

        <form action="forgot_password.php" method="POST">
            <div class="mb-3">
                <label class="form-label text-muted fw-semibold">Email address</label>
                <input type="email" class="form-control form-control-lg" name="email" required>
            </div>
            <button type="submit" class="btn btn-success w-100 btn-lg" style="background-color: #10b981; border: none;">Send Reset Link</button>
        </form>
        <div class="text-center mt-3">
            <a href="login.php" style="color: #047857; font-weight: bold; text-decoration: none;">Back to Login</a>
        </div>
    </div>
</div>
</body>
</html>