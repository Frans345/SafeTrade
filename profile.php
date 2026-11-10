<?php
session_start();
require_once 'includes/db.php';

//admin browsing site, bounce to dashboard
if (isset($_SESSION['user_id'])) {
    $adminCheck = $pdo->prepare('SELECT is_admin FROM users WHERE user_id = ?');
    $adminCheck->execute([$_SESSION['user_id']]);
    if ($adminCheck->fetchColumn() == 1) {
        header('Location: admin_dashboard.php');
        exit;
    }
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$successMsg = '';

// Handle Actions (Delivery & Review Replies)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'confirm_delivery') {
        $bidId = $_POST['bid_id'] ?? null;
        $listingId = $_POST['listing_id'] ?? null;
        
        if ($bidId && $listingId) {
            $pdo->prepare("UPDATE bids SET delivery_confirmed = 1 WHERE bid_id = ? AND user_id = ?")->execute([$bidId, $userId]);
            $pdo->prepare("UPDATE listings SET status = 'Sold' WHERE listing_id = ?")->execute([$listingId]);
            header("Location: view_listing.php?id=" . $listingId . "#review-box");
            exit;
        }
    } elseif ($_POST['action'] === 'reply_review') {
        $reviewId = $_POST['review_id'] ?? null;
        $replyText = trim($_POST['reply_text'] ?? '');
        
        if ($reviewId && !empty($replyText)) {
            $pdo->prepare("UPDATE reviews SET reply_text = ? WHERE review_id = ? AND reviewee_id = ?")->execute([$replyText, $reviewId, $userId]);
            $successMsg = "Your reply has been publicly posted!";
        }
    }
}

// 1. fetch user data
$stmt = $pdo->prepare('SELECT * FROM users WHERE user_id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

// 2. fetch active listings
$listingStmt = $pdo->prepare("SELECT * FROM listings WHERE user_id = ? AND status = 'Active' AND is_deleted = 0 ORDER BY created_at DESC");
$listingStmt->execute([$userId]);
$activeListings = $listingStmt->fetchAll();

// 3. fetch sold listings
$soldStmt = $pdo->prepare("SELECT * FROM listings WHERE user_id = ? AND status = 'Sold' AND is_deleted = 0 ORDER BY created_at DESC");
$soldStmt->execute([$userId]);
$soldListings = $soldStmt->fetchAll();

// 4. fetch saved listings
$savedStmt = $pdo->prepare('
    SELECT l.*, s.created_at as saved_at 
    FROM saved_listings s 
    JOIN listings l ON s.listing_id = l.listing_id 
    WHERE s.user_id = ? AND l.is_deleted = 0
    ORDER BY s.created_at DESC
');
$savedStmt->execute([$userId]);
$savedListings = $savedStmt->fetchAll();

// 5. fetch buyer bids and offers
$bidsStmt = $pdo->prepare("
    SELECT b.*, l.title, l.image_path, l.status as listing_status, u.full_name as seller_name, u.user_id as seller_id
    FROM bids b
    JOIN listings l ON b.listing_id = l.listing_id
    JOIN users u ON l.user_id = u.user_id
    WHERE b.user_id = ? AND b.status != 'Rejected' AND l.is_deleted = 0
    ORDER BY b.created_at DESC
");
$bidsStmt->execute([$userId]);
$allBids = $bidsStmt->fetchAll();

// 6. fetch reviews received by this user
$revStmt = $pdo->prepare("
    SELECT r.*, u.full_name as reviewer_name, u.profile_image as reviewer_img, l.title as listing_title
    FROM reviews r
    JOIN users u ON r.reviewer_id = u.user_id
    LEFT JOIN listings l ON r.listing_id = l.listing_id
    WHERE r.reviewee_id = ?
    ORDER BY r.created_at DESC
");
$revStmt->execute([$userId]);
$myReviews = $revStmt->fetchAll();

$pendingBids = [];
$acceptedBids = [];
$purchaseHistory = []; 

foreach ($allBids as $bid) {
    if ($bid['status'] === 'Pending') {
        $pendingBids[] = $bid;
    } elseif ($bid['status'] === 'Accepted' && $bid['delivery_confirmed'] == 0) {
        $acceptedBids[] = $bid;
    } elseif ($bid['status'] === 'Accepted' && $bid['delivery_confirmed'] == 1) {
        $purchaseHistory[] = $bid;
    }
}

// badge Logic
$badgeColor = '#10b981'; $badgeText = 'New Seller';
$rating = (float)$user['rating'];
$reviews = (int)$user['review_count'];
if ($reviews > 0) {
    if ($rating < 2.0) { $badgeColor = '#ef4444'; $badgeText = 'Flagged'; } 
    elseif ($rating < 3.0) { $badgeColor = '#cd7f32'; $badgeText = 'Bronze Tier'; } 
    elseif ($rating < 4.0) { $badgeColor = '#94a3b8'; $badgeText = 'Silver Tier'; } 
    else { $badgeColor = '#fbbf24'; $badgeText = 'Gold Tier'; } 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - TradePlace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; }
        .profile-img-container { width: 100px; height: 100px; margin: 0 auto 15px; border-radius: 50%; overflow: hidden; border: 3px solid <?= $badgeColor ?>; }
        .profile-img-container img { width: 100%; height: 100%; object-fit: cover; }
        .profile-img-placeholder { width: 100%; height: 100%; background-color: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; color: #94a3b8; font-weight: bold; }
        
        .product-card { border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; background-color: white; text-decoration: none; color: inherit; transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .product-card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        .product-img { aspect-ratio: 1 / 1; object-fit: cover; border-bottom: 1px solid #e2e8f0; }
        
        .saved-item-row { display: flex; align-items: center; padding: 10px; border-bottom: 1px solid #f1f5f9; text-decoration: none; color: inherit; transition: background-color 0.2s; }
        .saved-item-row:hover { background-color: #f8fafc; }
        .saved-item-row:last-child { border-bottom: none; }
        .saved-img { width: 50px; height: 50px; border-radius: 8px; object-fit: cover; margin-right: 15px; border: 1px solid #e2e8f0; }
        
        .offer-card { border: 1px solid #e2e8f0; border-radius: 12px; background: white; margin-bottom: 15px; display: flex; align-items: center; padding: 15px; transition: transform 0.2s ease; }
        .offer-card:hover { transform: translateY(-2px); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .offer-img { width: 80px; height: 80px; object-fit: cover; border-radius: 8px; border: 1px solid #e2e8f0; margin-right: 15px; }
        
        .nav-pills .nav-link { color: #64748b; font-weight: 600; border-radius: 30px; padding: 8px 20px; margin-right: 10px; }
        .nav-pills .nav-link.active { background-color: #10b981; color: white; }

        .review-card { border: 1px solid #e2e8f0; border-radius: 12px; background: white; margin-bottom: 15px; padding: 20px; }
        .review-stars { color: #10b981; font-size: 1.1rem; letter-spacing: 2px; }
        .reply-box { background-color: #f8fafc; border-left: 4px solid #10b981; padding: 15px; border-radius: 0 8px 8px 0; margin-top: 15px; }
    </style>
</head>
<body>

    <?php include 'includes/navbar.php'; ?>

    <div class="container mb-5 mt-4">
        
        <?php if (empty($user['profile_image']) || (isset($_GET['msg']) && $_GET['msg'] === 'pfp_required')): ?>
            <div class="alert shadow-sm border-0 d-flex align-items-center gap-3 mb-4" style="background-color: #fef3c7; color: #92400e; border-radius: 12px;">
                <div style="font-size: 1.5rem;">📸</div>
                <div class="flex-grow-1">
                    <strong>Profile Picture Required!</strong><br>
                    To keep the TradePlace community secure, you must upload a photo before you can post or buy/sell items.
                </div>
                <a href="edit_profile.php" class="btn btn-sm btn-warning fw-bold rounded-pill px-3 shadow-sm">Upload Now</a>
            </div>
        <?php endif; ?>

        <?php if ($successMsg): ?>
            <div class="alert alert-success fw-bold shadow-sm" style="background-color: #d1fae5; color: #047857; border: none;">✓ <?= htmlspecialchars($successMsg) ?></div>
        <?php endif; ?>

        <?php if (!empty($acceptedBids)): ?>
            <?php foreach ($acceptedBids as $notif): ?>
                <div class="alert alert-info shadow-sm d-flex justify-content-between align-items-center mb-4" style="border-left: 5px solid #3b82f6; background-color: #eff6ff;">
                    <div>
                        <strong class="text-primary fs-5">Action Required!</strong><br>
                        <span class="text-dark">Your offer for <b><?= htmlspecialchars($notif['title']) ?></b> was accepted. Once you meet the seller and get the item, please confirm below.</span>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="messages.php?user=<?= $notif['seller_id'] ?>" class="btn btn-primary fw-bold">Message Seller</a>
                        <form action="profile.php" method="POST" class="m-0">
                            <input type="hidden" name="action" value="confirm_delivery">
                            <input type="hidden" name="bid_id" value="<?= $notif['bid_id'] ?>">
                            <input type="hidden" name="listing_id" value="<?= $notif['listing_id'] ?>">
                            <button type="submit" class="btn btn-success fw-bold" onclick="return confirm('Only click this if you have the item in your hands!');">✓ I Received It</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="row">
            
            <div class="col-lg-4 mb-4">
                <div class="card border-0 shadow-sm text-center p-4 mb-4" style="border-radius: 12px;">
                    <div class="profile-img-container">
                        <?php if ($user['profile_image']): ?>
                            <img src="<?= htmlspecialchars($user['profile_image']) ?>">
                        <?php else: ?>
                            <div class="profile-img-placeholder"><?= strtoupper(substr($user['full_name'], 0, 1)) ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <h4 class="fw-bold mb-1" style="color: #0f172a;">
                        <?= htmlspecialchars($user['full_name']) ?>
                        <?php if ($user['is_verified']): ?><span style="color: #10b981;">✓</span><?php endif; ?>
                    </h4>
                    <span class="badge mb-3 shadow-sm" style="background-color: <?= $badgeColor ?>;"><?= $badgeText ?></span>
                    <p class="text-muted mb-3 small">📍 <?= htmlspecialchars($user['town']) ?></p>

                    <div class="d-flex justify-content-center gap-4 pt-3 border-top mb-3">
                        <div>
                            <h5 class="mb-0 fw-bold"><?= count($activeListings) ?></h5>
                            <small class="text-muted">Active</small>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold" style="color: <?= $badgeColor ?>;"><?= $reviews > 0 ? number_format($rating, 1) : 'N/A' ?> ★</h5>
                            <small class="text-muted"><?= $reviews ?> Reviews</small>
                        </div>
                    </div>
                    
                    <a href="edit_profile.php" class="btn btn-outline-secondary fw-bold w-100">Edit Profile</a>
                </div>

                <div class="card border-0 shadow-sm" style="border-radius: 12px;">
                    <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                        <h6 class="fw-bold text-dark mb-0">♡ My Saved Items</h6>
                    </div>
                    <div class="card-body p-2">
                        <?php if (empty($savedListings)): ?>
                            <p class="text-muted small text-center p-3 m-0">No saved items yet.</p>
                        <?php else: ?>
                            <?php foreach ($savedListings as $item): ?>
                                <a href="view_listing.php?id=<?= $item['listing_id'] ?>" class="saved-item-row">
                                    <img src="<?= htmlspecialchars($item['image_path']) ?>" class="saved-img">
                                    <div>
                                        <h6 class="mb-1 fw-bold text-dark text-truncate" style="font-size: 0.9rem; max-width: 180px;"><?= htmlspecialchars($item['title']) ?></h6>
                                        <p class="mb-0 text-success fw-bold" style="font-size: 0.85rem;">R <?= number_format($item['price'], 2) ?></p>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                
                <ul class="nav nav-pills mb-4 shadow-sm p-2 bg-white rounded-pill d-inline-flex flex-wrap" id="dashboardTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="selling-tab" data-bs-toggle="pill" data-bs-target="#selling" type="button" role="tab">My Shop</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="buying-tab" data-bs-toggle="pill" data-bs-target="#buying" type="button" role="tab">My Offers</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="history-tab" data-bs-toggle="pill" data-bs-target="#history" type="button" role="tab">History</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="reviews-tab" data-bs-toggle="pill" data-bs-target="#reviews-pane" type="button" role="tab">My Reviews</button>
                    </li>
                </ul>

                <div class="tab-content" id="dashboardTabsContent">
                    
                    <div class="tab-pane fade show active" id="selling" role="tabpanel">
                        <h4 class="fw-bold mb-3" style="color: #0f172a;">My Active Trades</h4>
                        <div class="row row-cols-1 row-cols-md-2 g-3">
                            <?php if (empty($activeListings)): ?>
                                <div class="col-12">
                                    <div class="alert alert-light text-center border p-4" style="border-radius: 12px;">
                                        <p class="text-muted mb-3">You don't have any active items.</p>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($activeListings as $item): ?>
                                    <div class="col">
                                        <a href="view_listing.php?id=<?= $item['listing_id'] ?>" class="card h-100 product-card d-flex flex-column">
                                            <img src="<?= htmlspecialchars($item['image_path']) ?>" class="card-img-top product-img">
                                            <div class="card-body p-3">
                                                <h6 class="card-title mb-1 fw-bold text-truncate"><?= htmlspecialchars($item['title']) ?></h6>
                                                <p class="text-success fw-bold mb-0">R <?= number_format($item['price'], 2) ?></p>
                                            </div>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="buying" role="tabpanel">
                        <h4 class="fw-bold mb-4" style="color: #0f172a;">Active Offers</h4>
                        
                        <h6 class="fw-bold text-success border-bottom pb-2 mb-3">Accepted (Awaiting Pickup)</h6>
                        <?php if (empty($acceptedBids)): ?>
                            <p class="text-muted small mb-4">No accepted offers currently waiting for pickup.</p>
                        <?php else: ?>
                            <?php foreach ($acceptedBids as $bid): ?>
                                <div class="offer-card shadow-sm border-success">
                                    <img src="<?= htmlspecialchars($bid['image_path']) ?>" class="offer-img">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1 fw-bold"><?= htmlspecialchars($bid['title']) ?></h6>
                                        <p class="mb-2 text-muted small">Seller: <?= htmlspecialchars($bid['seller_name']) ?> | Price: <span class="text-success fw-bold">R<?= number_format($bid['amount'], 2) ?></span></p>
                                        <div class="d-flex gap-2">
                                            <form action="profile.php" method="POST" class="m-0">
                                                <input type="hidden" name="action" value="confirm_delivery">
                                                <input type="hidden" name="bid_id" value="<?= $bid['bid_id'] ?>">
                                                <input type="hidden" name="listing_id" value="<?= $bid['listing_id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-success fw-bold" onclick="return confirm('Only click this if you have the item in your hands!');">✓ Confirm Received</button>
                                            </form>
                                            <a href="messages.php?user=<?= $bid['seller_id'] ?>" class="btn btn-sm btn-outline-dark fw-bold">Message</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <h6 class="fw-bold text-warning border-bottom pb-2 mb-3 mt-4">Pending Seller Response</h6>
                        <?php if (empty($pendingBids)): ?>
                            <p class="text-muted small mb-4">You have no pending offers.</p>
                        <?php else: ?>
                            <?php foreach ($pendingBids as $bid): ?>
                                <div class="offer-card shadow-sm opacity-75">
                                    <img src="<?= htmlspecialchars($bid['image_path']) ?>" class="offer-img">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1 fw-bold"><?= htmlspecialchars($bid['title']) ?></h6>
                                        <p class="mb-1 text-muted small">Seller: <?= htmlspecialchars($bid['seller_name']) ?></p>
                                        <p class="mb-0 text-dark fw-bold">Your Offer: R<?= number_format($bid['amount'], 2) ?></p>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-warning text-dark mb-2">Pending</span><br>
                                        <a href="view_listing.php?id=<?= $bid['listing_id'] ?>" class="btn btn-sm btn-outline-secondary fw-bold">View Item</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="tab-pane fade" id="history" role="tabpanel">
                        <h4 class="fw-bold mb-4" style="color: #0f172a;">History</h4>
                        
                        <?php if (empty($purchaseHistory) && empty($soldListings)): ?>
                            <div class="alert alert-light text-center border p-4" style="border-radius: 12px;">
                                <p class="text-muted m-0">You have no history of buying or selling yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-3">
                                
                                <?php foreach ($soldListings as $item): ?>
                                    <div class="offer-card shadow-sm position-relative overflow-hidden">
                                        <div style="position: absolute; left: 0; top: 0; bottom: 0; width: 5px; background-color: #3b82f6;"></div>
                                        <img src="<?= htmlspecialchars($item['image_path']) ?>" class="offer-img ms-2">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center mb-1 gap-2">
                                                <span class="badge bg-primary">SOLD</span>
                                                <h6 class="mb-0 fw-bold text-dark"><?= htmlspecialchars($item['title']) ?></h6>
                                            </div>
                                            <p class="mb-0 text-muted small">You successfully sold this item.</p>
                                        </div>
                                        <div class="text-end">
                                            <a href="view_listing.php?id=<?= $item['listing_id'] ?>" class="btn btn-sm btn-outline-primary fw-bold">View Listing</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                                <?php foreach ($purchaseHistory as $item): ?>
                                    <div class="offer-card shadow-sm position-relative overflow-hidden">
                                        <div style="position: absolute; left: 0; top: 0; bottom: 0; width: 5px; background-color: #10b981;"></div>
                                        <img src="<?= htmlspecialchars($item['image_path']) ?>" class="offer-img ms-2">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center mb-1 gap-2">
                                                <span class="badge bg-success">BOUGHT</span>
                                                <h6 class="mb-0 fw-bold text-dark"><?= htmlspecialchars($item['title']) ?></h6>
                                            </div>
                                            <p class="mb-0 text-muted small">Bought from <?= htmlspecialchars($item['seller_name']) ?> for R<?= number_format($item['amount'], 2) ?></p>
                                        </div>
                                        <div class="text-end">
                                            <a href="view_listing.php?id=<?= $item['listing_id'] ?>#review-box" class="btn btn-sm btn-outline-success fw-bold">Review Seller</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- NEW: MY REVIEWS TAB -->
                    <div class="tab-pane fade" id="reviews-pane" role="tabpanel">
                        <h4 class="fw-bold mb-4" style="color: #0f172a;">My Reviews</h4>
                        
                        <?php if (empty($myReviews)): ?>
                            <div class="alert alert-light text-center border p-4" style="border-radius: 12px;">
                                <p class="text-muted m-0">You have not received any reviews yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-3">
                                <?php foreach ($myReviews as $rev): ?>
                                    <div class="review-card shadow-sm">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div class="d-flex align-items-center gap-3">
                                                <?php if ($rev['reviewer_img']): ?>
                                                    <img src="<?= htmlspecialchars($rev['reviewer_img']) ?>" class="rounded-circle object-fit-cover" style="width: 45px; height: 45px;">
                                                <?php else: ?>
                                                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center text-secondary fw-bold border" style="width: 45px; height: 45px;">
                                                        <?= strtoupper(substr($rev['reviewer_name'], 0, 1)) ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <h6 class="mb-0 fw-bold text-dark"><?= htmlspecialchars($rev['reviewer_name']) ?></h6>
                                                    <small class="text-muted">Reviewed your item: <b><?= htmlspecialchars($rev['listing_title']) ?></b></small>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <div class="review-stars">
                                                    <?= str_repeat('★', floor($rev['rating'])) ?><?= str_repeat('☆', 5 - floor($rev['rating'])) ?>
                                                </div>
                                                <small class="text-muted" style="font-size: 0.75rem;"><?= date('M d, Y', strtotime($rev['created_at'])) ?></small>
                                            </div>
                                        </div>
                                        
                                        <p class="text-secondary mb-0" style="font-size: 0.95rem; line-height: 1.5;"><?= htmlspecialchars($rev['review_text']) ?></p>

                                        <?php if (!empty($rev['reply_text'])): ?>
                                            <div class="reply-box">
                                                <div class="d-flex align-items-center gap-2 mb-1">
                                                    <i class="bi bi-arrow-return-right text-success fw-bold"></i>
                                                    <span class="fw-bold text-dark small">Your Reply</span>
                                                </div>
                                                <p class="text-secondary mb-0" style="font-size: 0.9rem;"><?= nl2br(htmlspecialchars($rev['reply_text'])) ?></p>
                                            </div>
                                        <?php else: ?>
                                            <div class="mt-3 pt-3 border-top">
                                                <form action="profile.php" method="POST" class="d-flex gap-2">
                                                    <input type="hidden" name="action" value="reply_review">
                                                    <input type="hidden" name="review_id" value="<?= $rev['review_id'] ?>">
                                                    <input type="text" name="reply_text" class="form-control form-control-sm border-0 bg-light py-2 px-3 rounded-pill" placeholder="Write a public reply to this buyer..." required>
                                                    <button type="submit" class="btn btn-sm btn-dark rounded-pill px-4 fw-bold">Reply</button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>