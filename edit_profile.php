<?php
session_start();
require_once 'includes/db.php';

//kick out, if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$error = '';
$success = '';

//fetch user data
$stmt = $pdo->prepare('SELECT full_name, email, gender, dob, area, town, profile_image FROM users WHERE user_id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

//handle from submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $area = $_POST['area'] ?? '';
    $town = trim($_POST['town'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';

    //profile image upload
    $profileImagePath = $user['profile_image']; 
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['profile_image']['tmp_name'];
        $fileMimeType = mime_content_type($fileTmpPath);
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];

        if (in_array($fileMimeType, $allowed)) {
            $uploadDir = 'uploads/profiles/';
            if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }
            
            $newFileName = uniqid('PFP_') . '_' . $userId . '.jpg';
            $destPath = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $profileImagePath = $destPath;
                $_SESSION['profile_image'] = $destPath; //update session
            } else {
                $error = 'Failed to upload profile picture.';
            }
        } else {
            $error = 'Invalid image format. Please use JPG, PNG, or WebP.';
        }
    }

    if (empty($email) || empty($gender) || empty($area) || empty($town)) {
        $error = "Please fill in all the editable fields.";
    } elseif (empty($error)) {
        //check if email is already used
        $emailCheck = $pdo->prepare('SELECT user_id FROM users WHERE email = ? AND user_id != ?');
        $emailCheck->execute([$email, $userId]);
        
        if ($emailCheck->fetch()) {
            $error = "That email address is already in use by another account.";
        } else {
            //update info + pfp
            $updateQuery = "UPDATE users SET email = ?, gender = ?, area = ?, town = ?, profile_image = ? WHERE user_id = ?";
            $updateParams = [$email, $gender, $area, $town, $profileImagePath, $userId];
            
            //check if password neeeds to update
            if (!empty($newPassword)) {
                $passwordRegex = "/^(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/";
                if (!preg_match($passwordRegex, $newPassword)) {
                    $error = "New password does not meet security requirements.";
                } else {
                    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $updateQuery = "UPDATE users SET email = ?, gender = ?, area = ?, town = ?, profile_image = ?, password_hash = ? WHERE user_id = ?";
                    $updateParams = [$email, $gender, $area, $town, $profileImagePath, $hash, $userId];
                }
            }

            //execute update, if no errors
            if (empty($error)) {
                $updateStmt = $pdo->prepare($updateQuery);
                if ($updateStmt->execute($updateParams)) {
                    $success = "Profile updated successfully!";
                    //refresh the $user variable so the form shows the new data
                    $user['email'] = $email;
                    $user['gender'] = $gender;
                    $user['area'] = $area;
                    $user['town'] = $town;
                    $user['profile_image'] = $profileImagePath;
                } else {
                    $error = "Failed to update profile. Please try again.";
                }
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
    <title>Edit Profile - SafeTrade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .navbar { background-color: #ffffff; border-bottom: 2px solid #d1fae5; }
        .form-container { max-width: 600px; margin-top: 40px; margin-bottom: 60px; }
        .locked-field { background-color: #f1f5f9; cursor: not-allowed; color: #64748b; }
        
        /* profile img upload styles */
        .profile-img-container { width: 120px; height: 120px; margin: 0 auto 20px; border-radius: 50%; overflow: hidden; border: 3px solid #10b981; position: relative; cursor: pointer; background-color: #e2e8f0; }
        .profile-img-container img { width: 100%; height: 100%; object-fit: cover; }
        .profile-img-placeholder { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 3rem; color: #94a3b8; font-weight: bold; }
        .img-overlay { position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.6); color: white; font-size: 0.8rem; font-weight: bold; padding: 6px 0; text-align: center; opacity: 0; transition: opacity 0.2s; }
        .profile-img-container:hover .img-overlay { opacity: 1; }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-light py-3 shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <div class="brand-logo" style="display: flex; align-items: center; font-family: 'Arial', sans-serif; font-weight: 900;">
                    <div style="background-color: #d1fae5; padding: 5px 10px; border-radius: 8px; margin-right: 10px; letter-spacing: -2px; font-size: 1.8rem; line-height: 1;">
                        <span style="color: #10b981;">S</span><span style="color: #047857;">T</span>
                    </div>
                    <div style="font-size: 1.4rem; font-weight: 800; color: #333; letter-spacing: -0.5px;">
                        Safe<span style="color: #10b981;">Trade</span>
                    </div>
                </div>
            </a>
            <div class="ms-auto">
                <a href="profile.php" class="btn btn-outline-secondary fw-bold">Back to Profile</a>
            </div>
        </div>
    </nav>

    <div class="container form-container">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 p-md-5">
                <h3 class="card-title fw-bold text-center mb-4" style="color: #0f172a;">Edit Profile</h3>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger fw-bold shadow-sm"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success fw-bold shadow-sm" style="background-color: #d1fae5; color: #047857; border: none;">✓ <?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <form action="edit_profile.php" method="POST" enctype="multipart/form-data">
                    
                    <div class="text-center mb-4 border-bottom pb-4">
                        <div class="profile-img-container shadow-sm" onclick="document.getElementById('pfpInput').click();">
                            <?php if ($user['profile_image']): ?>
                                <img src="<?= htmlspecialchars($user['profile_image']) ?>" id="pfpPreview">
                                <div class="profile-img-placeholder" id="pfpInitial" style="display: none;"><?= strtoupper(substr($user['full_name'], 0, 1)) ?></div>
                            <?php else: ?>
                                <img src="" id="pfpPreview" style="display: none;">
                                <div class="profile-img-placeholder" id="pfpInitial"><?= strtoupper(substr($user['full_name'], 0, 1)) ?></div>
                            <?php endif; ?>
                            <div class="img-overlay">📸 Upload</div>
                        </div>
                        <input type="file" id="pfpInput" name="profile_image" accept="image/*" style="display: none;">
                        <small class="text-muted d-block mt-2">Click the image to upload a new profile picture.</small>
                    </div>

                    <div class="p-3 mb-4 rounded border" style="background-color: #f8fafc; border-color: #e2e8f0;">
                        <div class="d-flex align-items-center mb-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#ef4444" class="bi bi-lock-fill me-2" viewBox="0 0 16 16">
                              <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2m3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2"/>
                            </svg>
                            <span class="text-danger fw-bold" style="font-size: 0.85rem;">Verified Identity Locked</span>
                        </div>
                        <p class="text-muted small mb-3">To prevent fraud, your verified Name and Date of Birth cannot be changed here. Contact an admin for ID re-verification.</p>
                        
                        <div class="row">
                            <div class="col-md-6 mb-2 mb-md-0">
                                <label class="form-label fw-semibold text-muted">Full Name</label>
                                <input type="text" class="form-control locked-field" value="<?= htmlspecialchars($user['full_name']) ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-muted">Date of Birth</label>
                                <input type="date" class="form-control locked-field" value="<?= htmlspecialchars($user['dob']) ?>" readonly>
                            </div>
                        </div>
                    </div>

                    <h6 class="text-muted mb-3 border-bottom pb-2">Contact & Location</h6>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email address</label>
                        <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Province</label>
                            <select name="area" class="form-select" required>
                                <option value="eastern_cape" <?= $user['area'] == 'eastern_cape' ? 'selected' : '' ?>>Eastern Cape</option>
                                <option value="free_state" <?= $user['area'] == 'free_state' ? 'selected' : '' ?>>Free State</option>
                                <option value="gauteng" <?= $user['area'] == 'gauteng' ? 'selected' : '' ?>>Gauteng</option>
                                <option value="kwazulu_natal" <?= $user['area'] == 'kwazulu_natal' ? 'selected' : '' ?>>KwaZulu-Natal</option>
                                <option value="limpopo" <?= $user['area'] == 'limpopo' ? 'selected' : '' ?>>Limpopo</option>
                                <option value="mpumalanga" <?= $user['area'] == 'mpumalanga' ? 'selected' : '' ?>>Mpumalanga</option>
                                <option value="northern_cape" <?= $user['area'] == 'northern_cape' ? 'selected' : '' ?>>Northern Cape</option>
                                <option value="north_west" <?= $user['area'] == 'north_west' ? 'selected' : '' ?>>North West</option>
                                <option value="western_cape" <?= $user['area'] == 'western_cape' ? 'selected' : '' ?>>Western Cape</option>
                            </select>
                        </div>
                        <div class="col-md-6 mt-3 mt-md-0">
                            <label class="form-label fw-semibold">Town / City</label>
                            <input type="text" class="form-control" name="town" value="<?= htmlspecialchars($user['town']) ?>" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Gender</label>
                        <select name="gender" class="form-select" required>
                            <option value="male" <?= $user['gender'] == 'male' ? 'selected' : '' ?>>Male</option>
                            <option value="female" <?= $user['gender'] == 'female' ? 'selected' : '' ?>>Female</option>
                            <option value="other" <?= $user['gender'] == 'other' ? 'selected' : '' ?>>Other</option>
                            <option value="prefer_not" <?= $user['gender'] == 'prefer_not' ? 'selected' : '' ?>>Prefer not to say</option>
                        </select>
                    </div>

                    <h6 class="text-muted mb-3 border-bottom pb-2">Change Password</h6>
                    <div class="mb-4">
                        <label class="form-label fw-semibold">New Password (Optional)</label>
                        <input type="password" class="form-control" name="new_password" placeholder="Leave blank to keep current password">
                        <small class="text-muted mt-1 d-block">If changing, must be 8+ characters with an uppercase letter, number, and special character.</small>
                    </div>

                    <hr class="mb-4">
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success btn-lg fw-bold" style="background-color: #10b981; border: none;">Save Changes</button>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('pfpInput').addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('pfpPreview');
                    const initial = document.getElementById('pfpInitial');
                    
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    if (initial) initial.style.display = 'none';
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>