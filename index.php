<?php
session_start();
require_once 'includes/db.php';

//if admin browse, bounce to dashboard
if (isset($_SESSION['user_id'])) {
    $adminCheck = $pdo->prepare('SELECT is_admin FROM users WHERE user_id = ?');
    $adminCheck->execute([$_SESSION['user_id']]);
    if ($adminCheck->fetchColumn() == 1) {
        header('Location: admin_dashboard.php');
        exit;
    }
}

//check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userId = $isLoggedIn ? $_SESSION['user_id'] : null;

//search inputs
$searchType = $_GET['search_type'] ?? 'items'; //items/users
$searchQuery = trim($_GET['search'] ?? '');
$activeCategory = $_GET['category'] ?? 'All';
$selectedProvinces = $_GET['provinces'] ?? [];
if (!is_array($selectedProvinces)) {
    $selectedProvinces = [$selectedProvinces];
}

$listings = [];
$usersList = [];

//user search mode
if ($searchType === 'users') {
    $query = "SELECT user_id, full_name, profile_image, town, is_verified, rating, review_count FROM users WHERE is_deleted = 0";
    $params = [];
    
    //don't show the logged-in user to themselves
    if ($isLoggedIn) {
        $query .= " AND user_id != ?";
        $params[] = $userId;
    }
    
    //name or town
    if (!empty($searchQuery)) {
        $query .= " AND (full_name LIKE ? OR town LIKE ?)";
        $searchTerm = '%' . $searchQuery . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $query .= " ORDER BY created_at DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $usersList = $stmt->fetchAll();

//item search mode
} else {
    $query = 'SELECT l.*, u.full_name, u.is_verified, u.town FROM listings l JOIN users u ON l.user_id = u.user_id WHERE l.status = "Active" AND l.is_deleted = 0 AND u.is_deleted = 0';
    $params = [];

    if ($isLoggedIn) {
        $query .= ' AND l.user_id != ?';
        $params[] = $userId;
    }

    if ($activeCategory !== 'All') {
        $query .= ' AND l.category = ?';
        $params[] = $activeCategory;
    }

    if (!empty($selectedProvinces)) {
        $placeholders = str_repeat('?,', count($selectedProvinces) - 1) . '?';
        $query .= " AND l.province IN ($placeholders)";
        $params = array_merge($params, $selectedProvinces);
    }
    
    //inject the text search into the item query
    if (!empty($searchQuery)) {
        $query .= " AND (l.title LIKE ? OR l.description LIKE ?)";
        $searchTerm = '%' . $searchQuery . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    $query .= ' ORDER BY l.created_at DESC';
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $listings = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TradePlace - SafeTrade Marketplace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --safe-green: #10b981; --dark-green: #047857; --mint-green: #d1fae5; }
        body { background-color: #f8f9fa; }
        
        .product-card { border: 1px solid #e2e8f0; transition: transform 0.2s ease, box-shadow 0.2s ease; border-radius: 12px; overflow: hidden; background-color: white; text-decoration: none; color: inherit; }
        .product-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        .product-img { aspect-ratio: 1 / 1; object-fit: cover; border-bottom: 1px solid #e2e8f0; }
        
        .user-card { border: 1px solid #e2e8f0; border-radius: 12px; background: white; text-align: center; padding: 25px 15px; transition: transform 0.2s ease; }
        .user-card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .user-avatar { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid #d1fae5; margin: 0 auto 15px auto; background-color: #f1f5f9; display: flex; align-items: center; justify-content: center; font-size: 2rem; color: #94a3b8; font-weight: bold; }
        
        .category-scroll { display: flex; overflow-x: auto; gap: 10px; padding-bottom: 10px; }
        .category-scroll::-webkit-scrollbar { display: none; }
        .cat-pill { padding: 8px 16px; border-radius: 20px; background-color: white; border: 1px solid #cbd5e1; color: #475569; text-decoration: none; font-weight: 500; white-space: nowrap; }
        .cat-pill:hover { background-color: #f1f5f9; }
        .cat-pill.active { background-color: var(--safe-green); color: white; border-color: var(--safe-green); }
        .dropdown-menu .form-check-input:checked { background-color: var(--safe-green); border-color: var(--safe-green); }
    </style>
</head>
<body>

    <?php include 'includes/navbar.php'; ?>

    <div class="container mt-3">

        <?php if ($searchType === 'users'): ?>
            <div class="d-flex justify-content-between align-items-end mb-4 border-bottom pb-3">
                <h3 style="color: #0f172a; font-weight: bold; margin: 0;">
                    Community Search <?= !empty($searchQuery) ? 'for "' . htmlspecialchars($searchQuery) . '"' : '' ?>
                </h3>
                <a href="index.php" class="btn btn-sm btn-outline-secondary rounded-pill fw-bold">Back to Items</a>
            </div>

            <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-4 mb-5">
                <?php if (empty($usersList)): ?>
                    <div class="col-12 d-flex flex-column justify-content-center align-items-center text-center w-100" style="min-height: 30vh;">
                        <div style="font-size: 3rem; margin-bottom: 15px;">🤷‍♂️</div>
                        <h5 class="text-muted fw-bold">No users found.</h5>
                        <p class="text-muted">Check the spelling or try searching for a town instead.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($usersList as $userProfile): ?>
                        <div class="col">
                            <div class="user-card shadow-sm h-100 d-flex flex-column">
                                <div class="user-avatar shadow-sm">
                                    <?php if ($userProfile['profile_image']): ?>
                                        <img src="<?= htmlspecialchars($userProfile['profile_image']) ?>" style="width:100%; height:100%; border-radius:50%; object-fit:cover;">
                                    <?php else: ?>
                                        <?= strtoupper(substr($userProfile['full_name'], 0, 1)) ?>
                                    <?php endif; ?>
                                </div>
                                <h6 class="fw-bold mb-1 text-dark">
                                    <?= htmlspecialchars($userProfile['full_name']) ?>
                                    <?php if ($userProfile['is_verified']): ?><span style="color: #10b981;">✓</span><?php endif; ?>
                                </h6>
                                <p class="text-muted small mb-2">📍 <?= htmlspecialchars($userProfile['town']) ?></p>
                                
                                <div class="mb-3">
                                    <?php if ($userProfile['review_count'] > 0): ?>
                                        <span class="badge bg-warning text-dark fw-bold"><?= number_format($userProfile['rating'], 1) ?> ★</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border">New Seller</span>
                                    <?php endif; ?>
                                </div>
                                
                                <a href="seller_profile.php?id=<?= $userProfile['user_id'] ?>" class="btn btn-outline-dark fw-bold w-100 mt-auto rounded-pill" style="font-size: 0.9rem;">View Profile</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3 mb-4">
                
                <div class="category-scroll w-100 mb-0">
                    <?php
                    $categories = ['All', 'Vehicles', 'Electronics', 'Sports & Outdoors', 'Home & Garden', 'Clothing'];
                    $baseQuery = 'index.php?search_type=' . urlencode($searchType);
                    $sharedQuery = '';
                    if (!empty($searchQuery)) { $sharedQuery .= '&search=' . urlencode($searchQuery); }
                    foreach ($selectedProvinces as $p) { $sharedQuery .= '&provinces[]=' . urlencode($p); }

                    foreach ($categories as $cat) {
                        $activeClass = ($activeCategory === $cat) ? 'active' : '';
                        echo "<a href='$baseQuery&category=" . urlencode($cat) . $sharedQuery . "' class='cat-pill $activeClass'>$cat</a>";
                    }
                    ?>
                </div>

                <form method="GET" class="d-flex align-items-center gap-2 m-0 flex-shrink-0">
                    <input type="hidden" name="search_type" value="<?= htmlspecialchars($searchType) ?>">
                    <input type="hidden" name="category" value="<?= htmlspecialchars($activeCategory) ?>">
                    <?php if (!empty($searchQuery)): ?>
                        <input type="hidden" name="search" value="<?= htmlspecialchars($searchQuery) ?>">
                    <?php endif; ?>
                    
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle bg-white rounded-pill fw-bold shadow-sm" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                            📍 <?= empty($selectedProvinces) ? 'All Provinces' : count($selectedProvinces) . ' Selected' ?>
                        </button>
                        <div class="dropdown-menu shadow p-3" style="width: 250px; border-radius: 12px;">
                            <h6 class="dropdown-header text-dark fw-bold px-0 mb-2">Filter by Province:</h6>
                            <?php
                            $allProvinces = ['Eastern Cape', 'Free State', 'Gauteng', 'KwaZulu-Natal', 'Limpopo', 'Mpumalanga', 'Northern Cape', 'North West', 'Western Cape'];
                            foreach ($allProvinces as $prov) {
                                $isChecked = in_array($prov, $selectedProvinces) ? 'checked' : '';
                                $provId = md5($prov);
                                echo '
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="provinces[]" value="' . htmlspecialchars($prov) . '" id="prov_' . $provId . '" ' . $isChecked . '>
                                    <label class="form-check-label text-dark" style="cursor:pointer;" for="prov_' . $provId . '">' . htmlspecialchars($prov) . '</label>
                                </div>';
                            }
                            ?>
                            <hr class="my-2">
                            <button type="submit" class="btn btn-success w-100 fw-bold" style="background-color: var(--safe-green); border:none;">Apply Filter</button>
                        </div>
                    </div>

                    <?php if (!empty($selectedProvinces) || !empty($searchQuery)): ?>
                        <a href="index.php?search_type=<?= urlencode($searchType) ?>&category=<?= urlencode($activeCategory) ?>" class="btn btn-light text-danger fw-bold border rounded-pill shadow-sm">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="mb-4 border-bottom pb-2">
                <h3 style="color: #0f172a; font-weight: bold; margin: 0;">
                    <?= htmlspecialchars($activeCategory) ?> Trades
                    <?php if (!empty($searchQuery)): ?> <span class="text-muted fw-normal fs-5">matching "<?= htmlspecialchars($searchQuery) ?>"</span> <?php endif; ?>
                </h3>
            </div>

            <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-4 mb-5">
                <?php if (empty($listings)): ?>
                    <div class="col-12 d-flex flex-column justify-content-center align-items-center text-center w-100" style="min-height: 40vh;">
                        <div style="font-size: 4rem; color: #cbd5e1; margin-bottom: 15px;">🔍</div>
                        <h4 class="text-muted fw-bold">No trades found.</h4>
                        <p class="text-muted">Try removing some province filters, switching categories, or clearing your search.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($listings as $item): ?>
                        <div class="col">
                            <a href="view_listing.php?id=<?= $item['listing_id'] ?>" class="card h-100 product-card d-flex flex-column">
                                <img src="<?= htmlspecialchars($item['image_path']) ?>" class="card-img-top product-img" alt="Product Image">
                                <div class="card-body p-3 d-flex flex-column">
                                    <h6 class="card-title mb-1 fw-bold text-truncate"><?= htmlspecialchars($item['title']) ?></h6>
                                    <p class="text-success fw-bold mb-2 fs-5">R <?= number_format($item['price'], 2) ?></p>
                                    
                                    <div class="mt-auto pt-2 border-top">
                                        <small class="text-muted d-block mb-1 text-truncate">📍 <?= htmlspecialchars($item['location'] ?? $item['town']) ?>, <?= htmlspecialchars($item['province'] ?? 'SA') ?></small>
                                        <small class="text-muted d-block mb-1 text-truncate">
                                            Sold by <?= htmlspecialchars($item['full_name']) ?>
                                            <?php if ($item['is_verified']): ?><span style="color: #10b981;">✓</span><?php endif; ?>
                                        </small>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>