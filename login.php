<?php
session_start();
require_once 'includes/db.php';

//if logged in, check and sent to correct place
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare('SELECT is_admin FROM users WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    if ($stmt->fetchColumn() == 1) {
        header('Location: admin_dashboard.php');
    } else {
        header('Location: index.php');
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both your email and password.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        //verifyhashed password
        if ($user && password_verify($password, $user['password_hash'])) {
            
            //check user ban
            if (isset($user['is_deleted']) && $user['is_deleted'] == 1) {
                $error = 'Your account has been suspended by the Admin.';
            } else {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['is_verified'] = $user['is_verified'];
                
                //redirect
                if (isset($user['is_admin']) && $user['is_admin'] == 1) {
                    header('Location: admin_dashboard.php');
                } else {
                    header('Location: index.php');
                }
                exit;
            }
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log In - SafeTrade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { 
            background-color: #f8f9fa; 
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .login-card { 
            width: 100%; 
            max-width: 450px; 
            border-radius: 12px; 
            border: 1px solid #e2e8f0; 
            background-color: white; 
        }
        .brand-logo { 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-family: 'Arial', sans-serif; 
            font-weight: 900; 
            margin-bottom: 20px;
        }
        .password-toggle {
            cursor: pointer;
            border-left: none;
            user-select: none;
        }
    </style>
</head>
<body>

    <div class="login-card shadow-sm p-4 p-md-5">
        
        <a href="index.php" class="text-decoration-none">
            <div class="brand-logo">
                <div style="background-color: #d1fae5; padding: 5px 10px; border-radius: 8px; margin-right: 10px; font-size: 2rem; line-height: 1;">
                    <span style="color: #10b981;">S</span><span style="color: #047857;">T</span>
                </div>
                <div style="font-size: 1.8rem; font-weight: 800; color: #10b981;">Safe<span style="color: #047857;">Trade</span></div>
            </div>
        </a>

        <h4 class="fw-bold text-center mb-4" style="color: #0f172a;">Welcome back</h4>

        <?php if ($error): ?>
            <div class="alert alert-danger fw-bold shadow-sm" style="font-size: 0.9rem;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            
            <div class="mb-3">
                <label class="form-label fw-bold" style="color: #334155;">Email Address</label>
                <input type="text" class="form-control form-control-lg" name="email" placeholder="name@example.com" required>
            </div>

            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label fw-bold m-0" style="color: #334155;">Password</label>
                    <a href="forgot_password.php" class="text-success text-decoration-none small fw-bold">Forgot password?</a>
                </div>
                
                <div class="input-group">
                    <input type="password" class="form-control form-control-lg border-end-0" name="password" id="loginPassword" placeholder="Enter your password" required>
                    <span class="input-group-text bg-white password-toggle" id="togglePassword">
                        <i class="bi bi-eye" id="toggleIcon"></i>
                    </span>
                </div>
            </div>

            <div class="d-grid mb-3">
                <button type="submit" class="btn btn-success btn-lg fw-bold shadow-sm" style="background-color: #10b981; border: none;">Log In</button>
            </div>
            
            <div class="d-grid">
                <a href="index.php" class="btn btn-outline-secondary fw-bold">Continue as Guest</a>
            </div>

        </form>

        <p class="text-center mt-4 mb-0 text-muted small">
            Don't have an account? <a href="register.php" class="text-success fw-bold text-decoration-none">Sign up here</a>
        </p>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleButton = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('loginPassword');
            const icon = document.getElementById('toggleIcon');
            
            toggleButton.addEventListener('click', function() {
                const currentType = passwordInput.getAttribute('type');
                if (currentType === 'password') {
                    passwordInput.setAttribute('type', 'text');
                    icon.classList.remove('bi-eye');
                    icon.classList.add('bi-eye-slash');
                } else {
                    passwordInput.setAttribute('type', 'password');
                    icon.classList.remove('bi-eye-slash');
                    icon.classList.add('bi-eye');
                }
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>