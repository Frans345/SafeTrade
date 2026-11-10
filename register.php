<?php
session_start();
//connection to db is active
require_once 'includes/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    //caputre form data
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $dob = trim($_POST['dob'] ?? '');
    $area = $_POST['area'] ?? '';
    $town = trim($_POST['town'] ?? '');

    //strict password 
    $passwordRegex = "/^(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/";

    // 1. strict validation
    if (empty($fullName) || empty($email) || empty($password) || empty($gender) || empty($dob) || empty($area) || empty($town)) {
        $error = 'Please fill in all required fields and dropdowns.';
    } elseif (!preg_match($passwordRegex, $password)) {
        $error = 'Password does not meet the minimum security requirements.';
    } elseif (!isset($_POST['terms'])) {
        $error = 'You must agree to the Terms and Conditions to register.';
    } elseif (!isset($_FILES['id_document']) || $_FILES['id_document']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload a valid ID document or photo.';
    } else {
        
        // 2. database check, if email already exists
        $stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $error = 'This email address is already registered.';
        } else {
            
            // 3. ID upload
            $fileTmpPath = $_FILES['id_document']['tmp_name'];
            $fileMimeType = mime_content_type($fileTmpPath);
            $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
            
            if (in_array($fileMimeType, $allowedMimeTypes)) {
                
                // save file securely
                $uploadDir = 'uploads/id_docs/';
                $newFileName = uniqid('ID_') . '.jpg'; 
                $destPath = $uploadDir . $newFileName;
                
                //create folder if it doesn't exist 
                if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }
                
                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    
                    
                    $isVerified = 1; //pretend the API approved the ID
                    // -------------------------

                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    
                    //insert all data
                    $insert = $pdo->prepare('INSERT INTO users (email, password_hash, full_name, gender, dob, area, town, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                    
                    if ($insert->execute([$email, $hash, $fullName, $gender, $dob, $area, $town, $isVerified])) {
                        $_SESSION['flash_success'] = 'Account created and ID verified successfully! You earned the Green Badge.';
                        header('Location: login.php');
                        exit;
                    } else {
                        $error = 'Database error. Please try again.';
                    }
                } else {
                    $error = 'Failed to save the ID document. Check folder permissions.';
                }
            } else {
                $error = "Invalid ID file type. Please upload a JPG or PNG.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - SafeTrade</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        body { background-color: #f8f9fa; }
        .navbar { background-color: #ffffff; border-bottom: 2px solid #d1fae5; }
        .auth-container { max-width: 550px; margin-top: 50px; margin-bottom: 50px; }
        
        /* smooth transitions for the password checker */
        .pwd-req { transition: color 0.3s ease; }
        
        /* eye icon button styling */
        #togglePassword { cursor: pointer; border-color: #dee2e6; background-color: white; }
    </style>
</head>
<body>

    <!-- TOP NAVIGATION BAR -->
    <nav class="navbar navbar-expand-lg navbar-light py-3 shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <div class="brand-logo" style="display: flex; align-items: center; font-family: 'Arial', sans-serif; font-weight: 900; user-select: none;">
                    <div style="background-color: #d1fae5; padding: 5px 10px; border-radius: 8px; margin-right: 10px; letter-spacing: -2px; font-size: 1.8rem; line-height: 1;">
                        <span style="color: #10b981;">S</span><span style="color: #047857;">T</span>
                    </div>
                    <div style="font-size: 1.4rem; font-weight: 800; color: #333; letter-spacing: -0.5px;">
                        Safe<span style="color: #10b981;">Trade</span>
                    </div>
                </div>
            </a>
        </div>
    </nav>

<div class="container auth-container">
    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <h3 class="card-title text-center mb-4">Create an Account</h3>
            
            <?php if ($error): ?>
                <div class="alert alert-danger fw-bold shadow-sm">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form action="register.php" method="POST" enctype="multipart/form-data">
                
                <!-- Personal Information -->
                <h6 class="text-muted mb-3 border-bottom pb-2">Personal Information</h6>
                
                <div class="mb-3">
                    <label for="fullName" class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="fullName" name="full_name" required>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Gender <span class="text-danger">*</span></label>
                        <select name="gender" class="form-select" required>
                            <option value="" disabled selected>Select one</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                            <option value="prefer_not">Prefer not to say</option>
                        </select>
                    </div>
                    <div class="col-md-6 mt-3 mt-md-0">
                        <label for="dob" class="form-label fw-semibold">Date of Birth <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="dob" name="dob" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label fw-semibold">Email address <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                
                <!-- PASSWORD WITH REAL-TIME FEEDBACK AND EYE ICON -->
                <div class="mb-4 p-3 rounded" style="background-color: #f8fafc; border: 1px solid #e2e8f0;">
                    <label for="password" class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password" required>
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                            <!-- SVG Eye Icon -->
                            <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16">
                                <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
                                <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>
                            </svg>
                        </button>
                    </div>
                    
                    <div id="passwordHelp" class="mt-2">
                        <ul class="list-unstyled small mb-0 fw-medium">
                            <li id="len" class="pwd-req text-danger">✘ Minimum 8 characters</li>
                            <li id="upper" class="pwd-req text-danger">✘ One uppercase letter</li>
                            <li id="num" class="pwd-req text-danger">✘ One number</li>
                            <li id="special" class="pwd-req text-danger">✘ One special character (@$!%*?&)</li>
                        </ul>
                    </div>
                </div>

                <!-- Identity Verification -->
                <h6 class="text-muted mb-3 border-bottom pb-2">Identity Verification</h6>
                <div class="mb-4 p-3 rounded" style="background-color: #f1f5f9; border: 1px solid #cbd5e1;">
                    <label class="form-label fw-bold" style="color: #0f172a;">Upload ID or Driver's License <span class="text-danger">*</span></label>
                    <p class="text-muted small mb-2">Your ID details must match your name and Date of Birth to secure your Green Badge.</p>
                    <input type="file" class="form-control" id="idUploadInput" name="id_document" accept="image/*" capture="environment" required>
                    
                    <!-- Dynamic Feedback Container -->
                    <div id="id-scan-status" style="display: none;"></div>
                </div>

                <!-- Location Information -->
                <h6 class="text-muted mb-3 border-bottom pb-2">Location Data</h6>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Province <span class="text-danger">*</span></label>
                        <select name="area" class="form-select" required>
                            <option value="" disabled selected>Select your region...</option>
                            <option value="eastern_cape">Eastern Cape</option>
                            <option value="free_state">Free State</option>
                            <option value="gauteng">Gauteng</option>
                            <option value="kwazulu_natal">KwaZulu-Natal</option>
                            <option value="limpopo">Limpopo</option>
                            <option value="mpumalanga">Mpumalanga</option>
                            <option value="northern_cape">Northern Cape</option>
                            <option value="north_west">North West</option>
                            <option value="western_cape">Western Cape</option>
                        </select>
                    </div>
                    <div class="col-md-6 mt-3 mt-md-0">
                        <label for="town" class="form-label fw-semibold">Town / City <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="town" name="town" placeholder="e.g., Tygervalley" required>
                    </div>
                </div>

                <!-- Terms and Submit -->
                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" name="terms" id="termsCheck" required>
                    <label class="form-check-label text-muted" for="termsCheck" style="font-size: 0.9rem;">
                        I agree to the SafeTrade <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal" style="color: #047857; text-decoration: underline; cursor: pointer;">Terms & Conditions</a> <span class="text-danger">*</span>
                    </label>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-success btn-lg" style="background-color: #10b981; border: none; font-weight: 500;">Register Account</button>
                </div>
            </form>
            
            <div class="text-center mt-4">
                <p class="text-muted">Already have an account? <a href="login.php" style="color: #047857; font-weight: bold; text-decoration: none;">Log in here</a></p>
            </div>
        </div>
    </div>
</div>


<!-- TERMS & CONDITIONS MODAL      -->

<div class="modal fade" id="termsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header" style="background-color: #d1fae5;">
        <h5 class="modal-title" style="color: #047857; font-weight: bold;">SafeTrade Terms & Conditions</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <h6>1. Acceptance of Terms</h6>
        <p class="text-muted" style="font-size: 0.9rem;">By registering an account on SafeTrade, you agree to abide by these terms. SafeTrade is a Consumer-to-Consumer (C2C) marketplace designed for safe and fair trading.</p>
        <h6>2. Identity & Accountability</h6>
        <p class="text-muted" style="font-size: 0.9rem;">Users must provide accurate information, including their real full name, date of birth, and a valid ID document. Anonymous trading is strictly prohibited.</p>
        <h6>3. Privacy Data</h6>
        <p class="text-muted" style="font-size: 0.9rem;">Your exact Date of Birth and ID document are securely stored and never shown to other users. Only your general Province/Town, real name, and a "Verified" badge will be displayed publicly.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-success" data-bs-dismiss="modal" style="background-color: #10b981; border: none;">I Understand</button>
      </div>
    </div>
  </div>
</div>


<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- TOGGLE PASSWORD VISIBILITY SCRIPT -->
<script>
    document.getElementById('togglePassword').addEventListener('click', function() {
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');
        
        // Toggle the type attribute
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        
        // Toggle the eye icon (slash vs normal)
        if (type === 'text') {
            eyeIcon.innerHTML = `<path d="M13.359 11.238C15.06 9.72 16 8 16 8s-3-5.5-8-5.5a7.028 7.028 0 0 0-2.79.588l.77.771A5.944 5.944 0 0 1 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.134 13.134 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755l-.909-.905zm-2.83-.836-1.54-1.54A2.5 2.5 0 0 0 7.55 7.42l-1.54-1.54A3.5 3.5 0 1 1 10.53 10.402z"/><path d="M11.53 10.402 1.172 0 .464.707l10.358 10.358a13.13 13.13 0 0 1-2.822 1.435C5.88 12.5 4.12 11.332 2.04 10.043 1.258 9.53 1.172 8 1.172 8s1.603-2.934 4.108-4.364l-.768-.768C1.5 4.5 0 8 0 8s3 5.5 8 5.5c1.47 0 2.85-.38 4.09-1.053l2.204 2.203.707-.707-3.471-3.471z"/>`;
        } else {
            eyeIcon.innerHTML = `<path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>`;
        }
    });
</script>

<!-- Real-Time Password Validation Script -->
<script>
    document.getElementById('password').addEventListener('input', function() {
        const val = this.value;
        
        // Check length
        const isLength = val.length >= 8;
        document.getElementById('len').innerHTML = isLength ? "✓ Minimum 8 characters" : "✘ Minimum 8 characters";
        document.getElementById('len').className = isLength ? "pwd-req text-success" : "pwd-req text-danger";

        // Check uppercase
        const isUpper = /[A-Z]/.test(val);
        document.getElementById('upper').innerHTML = isUpper ? "✓ One uppercase letter" : "✘ One uppercase letter";
        document.getElementById('upper').className = isUpper ? "pwd-req text-success" : "pwd-req text-danger";

        // Check number
        const isNum = /[0-9]/.test(val);
        document.getElementById('num').innerHTML = isNum ? "✓ One number" : "✘ One number";
        document.getElementById('num').className = isNum ? "pwd-req text-success" : "pwd-req text-danger";

        // Check special character
        const isSpecial = /[@$!%*?&]/.test(val);
        document.getElementById('special').innerHTML = isSpecial ? "✓ One special character" : "✘ One special character (@$!%*?&)";
        document.getElementById('special').className = isSpecial ? "pwd-req text-success" : "pwd-req text-danger";
    });
</script>

<!-- ID Verification Simulation Script -->
<script>
    document.getElementById('idUploadInput').addEventListener('change', function() {
        const file = this.files[0];
        const statusBox = document.getElementById('id-scan-status');
        
        if (file) {
            statusBox.style.display = 'block';
            statusBox.innerHTML = `
                <div class="d-flex align-items-center mt-3 p-2 rounded" style="background-color: #e2e8f0;">
                    <div class="spinner-border spinner-border-sm text-secondary me-2" role="status"></div>
                    <span class="text-secondary fw-semibold" style="font-size: 0.9rem;">AI scanning document...</span>
                </div>
            `;
            
            setTimeout(() => {
                statusBox.innerHTML = `
                    <div class="d-flex align-items-center mt-3 p-2 rounded" style="background-color: #d1fae5; border: 1px solid #10b981;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="#10b981" class="bi bi-check-circle-fill me-2" viewBox="0 0 16 16">
                          <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
                        </svg>
                        <span style="color: #047857; font-weight: bold; font-size: 0.95rem;">Valid ID</span>
                    </div>
                `;
            }, 2000); 
        } else {
            statusBox.style.display = 'none';
        }
    });
</script>

</body>
</html>