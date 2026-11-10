<?php
session_start();
require_once 'includes/db.php';

//1. security: must be logged in
if (!isset($_SESSION['user_id'])) { 
    header('Location: login.php'); 
    exit; 
}
$userId = $_SESSION['user_id'];

//2. check for pfp
$pfpCheck = $pdo->prepare('SELECT profile_image FROM users WHERE user_id = ?');
$pfpCheck->execute([$userId]);
$userPfp = $pfpCheck->fetchColumn();

//no pfp, redirection
if (empty($userPfp)) {
    header('Location: profile.php?msg=pfp_required');
    exit;
}

$error = $success = '';
$title = $price = $category = $description = $location = $province = '';

//fetch default town from user profile
$userStmt = $pdo->prepare('SELECT town FROM users WHERE user_id = ?');
$userStmt->execute([$userId]);
$uData = $userStmt->fetch();
$defaultTown = $uData ? $uData['town'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $price = $_POST['price'] ?? '';
    $category = $_POST['category'] ?? '';
    $location = trim($_POST['location'] ?? '');
    $province = $_POST['province'] ?? '';
    $description = trim($_POST['description'] ?? '');

    if (empty($title) || empty($price) || empty($category) || empty($location) || empty($province) || empty($description)) {
        $error = 'Please fill in all required fields.';
    } elseif (empty($_FILES['product_images']['name'][0])) {
        $error = 'Please upload at least one image.';
    } else {
        $uploadDir = 'uploads/products/';
        if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }
        $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
        $uploadedPaths = [];
        
        //count files uploaded
        $totalFiles = count($_FILES['product_images']['name']);

        if ($totalFiles > 10) { 
            $error = 'Max 10 images allowed.'; 
        } else {
            for ($i = 0; $i < $totalFiles; $i++) {
                if ($_FILES['product_images']['error'][$i] === UPLOAD_ERR_OK) {
                    $tmpPath = $_FILES['product_images']['tmp_name'][$i];
                    if (in_array(mime_content_type($tmpPath), $allowedMime)) {
                        $newFile = $uploadDir . uniqid('PROD_') . '_' . $i . '.jpg';
                        if (move_uploaded_file($tmpPath, $newFile)) { 
                            $uploadedPaths[] = $newFile; 
                        }
                    }
                }
            }
            
            if (empty($uploadedPaths)) { 
                $error = "Invalid images. JPG/PNG only."; 
            } else {
                //the first image in the array is the cover
                $cover = $uploadedPaths[0];
                $extra = count($uploadedPaths) > 1 ? implode(',', array_slice($uploadedPaths, 1)) : null;
                
                $stmt = $pdo->prepare('INSERT INTO listings (user_id, title, description, price, category, location, province, image_path, additional_images, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "Active")');
                
                if ($stmt->execute([$userId, $title, $description, $price, $category, $location, $province, $cover, $extra])) {
                    $success = 'Trade is live!';
                    $title = $price = $category = $description = ''; $location = $defaultTown;
                } else { 
                    $error = 'Database error.'; 
                }
            }
        }
    }
} else { 
    $location = $defaultTown; 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Start a Trade - SafeTrade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <style>
        body { background-color: #f8f9fa; }
        .form-container { max-width: 900px; margin: 40px auto; }
        
        .upload-area { 
            border: 2px dashed #cbd5e1; 
            border-radius: 12px; 
            padding: 20px; 
            background: #fff; 
            min-height: 220px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: 0.2s;
        }
        .upload-area:hover { border-color: #10b981; }

        #imageGallery { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); 
            gap: 15px; 
            width: 100%;
            margin-top: 15px;
        }

        .thumb-wrapper { 
            position: relative; 
            aspect-ratio: 1/1; 
            border-radius: 10px; 
            overflow: hidden; 
            border: 2px solid #e2e8f0; 
            background: white;
            cursor: grab;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .thumb-wrapper:active { cursor: grabbing; }
        .thumb-wrapper img { width: 100%; height: 100%; object-fit: cover; pointer-events: none; }
        
        .remove-btn { 
            position: absolute; 
            top: 5px; 
            right: 5px; 
            background: rgba(239, 68, 68, 0.9); 
            color: white; 
            border: none; 
            border-radius: 50%; 
            width: 22px; 
            height: 22px; 
            font-size: 14px; 
            line-height: 1;
            cursor: pointer; 
            z-index: 10;
        }

        .cover-label { 
            position: absolute; 
            bottom: 0; 
            left: 0; 
            right: 0; 
            background: #10b981; 
            color: white; 
            font-size: 10px; 
            text-align: center; 
            padding: 4px 0; 
            font-weight: 800;
            letter-spacing: 0.5px;
        }

        .upload-prompt { text-align: center; cursor: pointer; padding: 20px; width: 100%; }
        .commitment-box { background-color: #f1f5f9; border-radius: 12px; border-left: 5px solid #10b981 !important; }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    <div class="container form-container">
        <div class="card shadow-sm border-0" style="border-radius: 16px;">
            <div class="card-body p-4 p-md-5">
                <h3 class="fw-bold text-center mb-4">Start a Trade</h3>
                
                <?php if ($error): ?><div class="alert alert-danger fw-bold"><?= $error ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success fw-bold">✓ <?= $success ?> <a href="index.php">View Marketplace</a></div><?php endif; ?>
                
                <form id="listingForm" action="create_listing.php" method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-12 mb-4">
                            <label class="form-label fw-bold">Photos (Max 10)</label>
                            <div class="upload-area shadow-sm">
                                <div class="upload-prompt" onclick="document.getElementById('pImg').click();">
                                    <span style="font-size: 2rem;">📸</span><br>
                                    <strong>Click here to add images</strong><br>
                                    <small class="text-muted">Drag to reorder. The first photo will be your main cover photo.</small>
                                </div>
                                <input type="file" id="pImg" name="product_images[]" accept="image/*" multiple style="display:none;">
                                <div id="imageGallery"></div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Listing Title</label>
                            <input type="text" class="form-control" name="title" value="<?= htmlspecialchars($title) ?>" placeholder="" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold">Price</label>
                            <div class="input-group"><span class="input-group-text fw-bold">R</span><input type="number" class="form-control" name="price" step="0.01" value="<?= htmlspecialchars($price) ?>" required></div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold">Category</label>
                            <select name="category" class="form-select" required>
                                <option value="" disabled <?= empty($category) ? 'selected' : '' ?>>Select Category...</option>
                                <option value="Vehicles" <?= $category === 'Vehicles' ? 'selected' : '' ?>>Vehicles</option>
                                <option value="Electronics" <?= $category === 'Electronics' ? 'selected' : '' ?>>Electronics</option>
                                <option value="Sports & Outdoors" <?= $category === 'Sports & Outdoors' ? 'selected' : '' ?>>Sports</option>
                                <option value="Home & Garden" <?= $category === 'Home & Garden' ? 'selected' : '' ?>>Home</option>
                                <option value="Clothing" <?= $category === 'Clothing' ? 'selected' : '' ?>>Clothing</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Town</label>
                            <input type="text" class="form-control" name="location" value="<?= htmlspecialchars($location) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Province</label>
                            <select name="province" class="form-select" required>
                                <option value="" disabled <?= empty($province) ? 'selected' : '' ?>>Select Province...</option>
                                <option value="Eastern Cape" <?= $province === 'Eastern Cape' ? 'selected' : '' ?>>Eastern Cape</option>
                                <option value="Free State" <?= $province === 'Free State' ? 'selected' : '' ?>>Free State</option>
                                <option value="Gauteng" <?= $province === 'Gauteng' ? 'selected' : '' ?>>Gauteng</option>
                                <option value="KwaZulu-Natal" <?= $province === 'KwaZulu-Natal' ? 'selected' : '' ?>>KwaZulu-Natal</option>
                                <option value="Limpopo" <?= $province === 'Limpopo' ? 'selected' : '' ?>>Limpopo</option>
                                <option value="Mpumalanga" <?= $province === 'Mpumalanga' ? 'selected' : '' ?>>Mpumalanga</option>
                                <option value="Northern Cape" <?= $province === 'Northern Cape' ? 'selected' : '' ?>>Northern Cape</option>
                                <option value="North West" <?= $province === 'North West' ? 'selected' : '' ?>>North West</option>
                                <option value="Western Cape" <?= $province === 'Western Cape' ? 'selected' : '' ?>>Western Cape</option>
                            </select>
                        </div>
                        <div class="col-12 mb-4">
                            <label class="form-label fw-bold">Description</label>
                            <textarea class="form-control" name="description" rows="4" placeholder="Describe the condition, size, and any flaws..." required><?= htmlspecialchars($description) ?></textarea>
                        </div>

                        <div class="col-12 mb-4">
                            <div class="p-3 commitment-box shadow-sm border-0">
                                <h6 class="fw-bold mb-2" style="color: #0f172a;">📝 Seller's Commitment</h6>
                                <ul class="mb-0 ps-3 small text-muted">
                                    <li class="mb-1">Ensure that you are ready to sell this item at the listed price.</li>
                                    <li class="mb-1">Please be attentive to messages; buyers value quick responses.</li>
                                    <li>Ensure device notifications are enabled for the SafeTrade app to avoid missing offers.</li>
                                </ul>
                            </div>
                        </div>

                        <div class="col-12">
                            <button type="submit" class="btn btn-success btn-lg fw-bold w-100 py-3 shadow-sm" style="background:#10b981; border:none;">
                                Publish Trade
                            </button>
                            <p class="text-center text-muted mt-3 small">By clicking Publish, you agree to our Community Trading Guidelines.</p>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let selectedFiles = [];
        const fileInput = document.getElementById('pImg');
        const gallery = document.getElementById('imageGallery');

        //initialize sortableJS for drag and drop
        new Sortable(gallery, {
            animation: 150,
            ghostClass: 'bg-light',
            onEnd: function() {
                reorderFiles();
                renderGallery();
            }
        });

        fileInput.addEventListener('change', function(e) {
            const files = Array.from(e.target.files);
            if (selectedFiles.length + files.length > 10) {
                alert("Maximum 10 images allowed.");
                return;
            }

            files.forEach(file => {
                file.id = Math.random().toString(36).substr(2, 9); //tracking ID
                selectedFiles.push(file);
            });
            
            renderGallery();
            syncFileInput();
        });

        function renderGallery() {
            gallery.innerHTML = '';
            selectedFiles.forEach((file, index) => {
                const div = document.createElement('div');
                div.className = 'thumb-wrapper';
                div.dataset.id = file.id;
                
                // Using URL.createObjectURL creates an instant synchronous link to the image,
                // completely eliminating the jumping/sorting race condition caused by FileReader.
                div.innerHTML = `
                    <img src="${URL.createObjectURL(file)}">
                    <button type="button" class="remove-btn">×</button>
                    ${index === 0 ? '<div class="cover-label">COVER</div>' : ''}
                `;
                
                div.querySelector('.remove-btn').onclick = (event) => {
                    event.stopPropagation();
                    selectedFiles = selectedFiles.filter(f => f.id !== file.id);
                    renderGallery();
                    syncFileInput();
                };
                gallery.appendChild(div);
            });
        }

        function reorderFiles() {
            const newOrderIds = Array.from(gallery.children).map(child => child.dataset.id);
            const newFileList = [];
            newOrderIds.forEach(id => {
                const file = selectedFiles.find(f => f.id === id);
                if (file) newFileList.push(file);
            });
            selectedFiles = newFileList;
            syncFileInput();
        }

        function syncFileInput() {
            const dataTransfer = new DataTransfer();
            selectedFiles.forEach(file => dataTransfer.items.add(file));
            fileInput.files = dataTransfer.files;
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>