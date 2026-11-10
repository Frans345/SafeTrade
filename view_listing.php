<?php
session_start();
require_once 'includes/db.php';

// --- THE ADMIN QUARANTINE ---
// If the admin tries to browse the site, bounce them back to their dashboard.
if (isset($_SESSION['user_id'])) {
    $adminCheck = $pdo->prepare('SELECT is_admin FROM users WHERE user_id = ?');
    $adminCheck->execute([$_SESSION['user_id']]);
    if ($adminCheck->fetchColumn() == 1) {
        header('Location: admin_dashboard.php');
        exit;
    }
}

// 1. GET THE LISTING ID FROM THE URL
$listingId = $_GET['id'] ?? null;
if (!$listingId) { header('Location: index.php'); exit; }

$currentUserId = $_SESSION['user_id'] ?? null;
$isLoggedIn = $currentUserId !== null;
$successMsg = '';

// 2. FETCH LISTING & SELLER DATA (Now checking is_deleted = 0)
$stmt = $pdo->prepare('
    SELECT l.*, u.full_name, u.is_verified, u.rating, u.review_count, u.profile_image 
    FROM listings l 
    JOIN users u ON l.user_id = u.user_id 
    WHERE l.listing_id = ? AND l.is_deleted = 0 AND u.is_deleted = 0
');
$stmt->execute([$listingId]);
$listing = $stmt->fetch();
// Now rederict user to the home page if the listing do not exist or was deleted
if (!$listing) { header('Location: index.php'); exit; }
$isSeller = ($currentUserId === $listing['user_id']);

// 3. HANDLE FORM ACTIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isLoggedIn) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'make_bid') {
        // --- THE BUYER BOUNCER START ---
        $pfpCheck = $pdo->prepare('SELECT profile_image FROM users WHERE user_id = ?');
        $pfpCheck->execute([$currentUserId]);
        if (empty($pfpCheck->fetchColumn())) {
            header('Location: profile.php?msg=pfp_required');
            exit;
        }
        // --- THE BUYER BOUNCER END ---

        $bidAmount = $_POST['bid_amount'] ?? 0;
        if ($bidAmount > 0) {
            $pdo->prepare('INSERT INTO bids (user_id, listing_id, amount) VALUES (?, ?, ?)')->execute([$currentUserId, $listingId, $bidAmount]);
            $successMsg = "Your offer of R" . number_format($bidAmount, 2) . " has been sent!";
        }
    } elseif ($action === 'accept_bid') {
        $bidId = $_POST['bid_id'] ?? null;
        if ($bidId) {
            $pdo->prepare("UPDATE bids SET status = 'Accepted' WHERE bid_id = ?")->execute([$bidId]);
            $pdo->prepare("UPDATE bids SET status = 'Rejected' WHERE listing_id = ? AND bid_id != ?")->execute([$listingId, $bidId]);
            $successMsg = "Offer accepted! Please arrange delivery in your messages.";
        }
    } elseif ($action === 'reject_bid') {
        $bidId = $_POST['bid_id'] ?? null;
        if ($bidId) {
            $pdo->prepare("UPDATE bids SET status = 'Rejected' WHERE bid_id = ?")->execute([$bidId]);
            $successMsg = "Offer declined.";
        }
    } elseif ($action === 'confirm_delivery') {
        $bidId = $_POST['bid_id'] ?? null;
        if ($bidId) {
            $pdo->prepare("UPDATE bids SET delivery_confirmed = 1 WHERE bid_id = ? AND user_id = ?")->execute([$bidId, $currentUserId]);
            $pdo->prepare("UPDATE listings SET status = 'Sold' WHERE listing_id = ?")->execute([$listingId]);
            
            // AUTO REDIRECT TO REVIEW
            header("Location: view_listing.php?id=" . $listingId . "#review-box");
            exit;
        }
    } elseif ($action === 'submit_review') {
        $rating = (float)($_POST['rating'] ?? 5.0); // Now grabs floats!
        $reviewText = trim($_POST['review_text'] ?? '');
        if ($rating >= 0.5 && $rating <= 5.0) {
            $pdo->prepare('INSERT INTO reviews (reviewer_id, reviewee_id, listing_id, rating, review_text) VALUES (?, ?, ?, ?, ?)')
                ->execute([$currentUserId, $listing['user_id'], $listingId, $rating, $reviewText]);
            
            $pdo->prepare('
                UPDATE users 
                SET rating = (SELECT AVG(rating) FROM reviews WHERE reviewee_id = ?),
                    review_count = (SELECT COUNT(*) FROM reviews WHERE reviewee_id = ?)
                WHERE user_id = ?
            ')->execute([$listing['user_id'], $listing['user_id'], $listing['user_id']]);
            
            $successMsg = "Review successfully submitted! Thank you for keeping TradePlace safe.";
        }
    } elseif ($action === 'post_comment') {
        $commentText = trim($_POST['comment_text'] ?? '');
        if (!empty($commentText)) {
            $pdo->prepare('INSERT INTO comments (listing_id, user_id, comment_text) VALUES (?, ?, ?)')->execute([$listingId, $currentUserId, $commentText]);
            $successMsg = "Your question has been posted!";
        }
    } elseif ($action === 'delete_comment') {
        $commentIdToDelete = $_POST['comment_id'] ?? null;
        if ($commentIdToDelete) {
            $pdo->prepare('DELETE FROM comments WHERE comment_id = ? AND user_id = ?')->execute([$commentIdToDelete, $currentUserId]);
            $successMsg = "Your comment has been deleted.";
        }
    } elseif ($action === 'edit_listing') {
        $newTitle = trim($_POST['new_title'] ?? '');
        $newPrice = $_POST['new_price'] ?? 0;
        $newCategory = $_POST['new_category'] ?? '';
        $newLocation = trim($_POST['new_location'] ?? '');
        $newProvince = $_POST['new_province'] ?? '';
        $newDesc = trim($_POST['new_description'] ?? '');
        
        if ($newPrice > 0 && !empty($newDesc) && !empty($newTitle) && !empty($newCategory) && !empty($newLocation) && !empty($newProvince)) {
            $pdo->prepare('UPDATE listings SET title = ?, price = ?, category = ?, location = ?, province = ?, description = ? WHERE listing_id = ? AND user_id = ?')
                ->execute([$newTitle, $newPrice, $newCategory, $newLocation, $newProvince, $newDesc, $listingId, $currentUserId]);
            
            $listing['title'] = $newTitle;
            $listing['price'] = $newPrice;
            $listing['category'] = $newCategory;
            $listing['location'] = $newLocation;
            $listing['province'] = $newProvince;
            $listing['description'] = $newDesc;
            
            $successMsg = "Trade successfully updated!";
        }
    } elseif ($action === 'delete_listing') {
        if ($pdo->prepare('UPDATE listings SET is_deleted = 1 WHERE listing_id = ? AND user_id = ?')->execute([$listingId, $currentUserId])) {
            header('Location: profile.php'); exit;
        }
    } elseif ($action === 'toggle_save') {
        $checkSave = $pdo->prepare('SELECT save_id FROM saved_listings WHERE user_id = ? AND listing_id = ?');
        $checkSave->execute([$currentUserId, $listingId]);
        if ($checkSave->fetch()) {
            $pdo->prepare('DELETE FROM saved_listings WHERE user_id = ? AND listing_id = ?')->execute([$currentUserId, $listingId]);
            $successMsg = "Removed from your saved items.";
        } else {
            $pdo->prepare('INSERT INTO saved_listings (user_id, listing_id) VALUES (?, ?)')->execute([$currentUserId, $listingId]);
            $successMsg = "Trade saved to your profile!";
        }
    } elseif ($action === 'report_post') {
        $reportReasonSelection = $_POST['report_reason_select'] ?? '';
        if (!empty($reportReasonSelection)) {
            $constructedReason = "Listing Reported: [" . $listing['title'] . "] - Reason Category: " . $reportReasonSelection;
            $reportQuery = $pdo->prepare('INSERT INTO reports (reporter_id, reported_user_id, reason, status) VALUES (?, ?, ?, \'Pending\')');
            $reportQuery->execute([$currentUserId, $listing['user_id'], $constructedReason]);
            $successMsg = "The tracking report has been submitted for moderation triage verification successfully.";
        }
    }
}

// 4. WINNING BIDDER IDENTIFICATION
$winningBidStmt = $pdo->prepare("SELECT * FROM bids WHERE listing_id = ? AND status = 'Accepted'");
$winningBidStmt->execute([$listingId]);
$winningBid = $winningBidStmt->fetch();
$winningBuyerId = $winningBid ? $winningBid['user_id'] : null;
$deliveryConfirmed = $winningBid ? (bool)$winningBid['delivery_confirmed'] : false;

// HAS CURRENT USER REVIEWED YET?
$hasReviewed = false;
if ($isLoggedIn && $currentUserId === $winningBuyerId) {
    $revCheck = $pdo->prepare('SELECT 1 FROM reviews WHERE reviewer_id = ? AND listing_id = ?');
    $revCheck->execute([$currentUserId, $listingId]);
    $hasReviewed = (bool)$revCheck->fetch();
}

$isSaved = false;
if ($isLoggedIn && !$isSeller) {
    $saveCheck = $pdo->prepare('SELECT 1 FROM saved_listings WHERE user_id = ? AND listing_id = ?');
    $saveCheck->execute([$currentUserId, $listingId]);
    $isSaved = (bool)$saveCheck->fetch();
}

// 5. MARK BIDS & COMMENTS AS READ
if ($isSeller) {
    $pdo->prepare('UPDATE bids SET is_read = 1 WHERE listing_id = ?')->execute([$listingId]);
    $pdo->prepare('UPDATE comments SET is_read = 1 WHERE listing_id = ? AND user_id != ?')->execute([$listingId, $currentUserId]);
}

// 6. PREPARE IMAGES & COMMENTS
$allImages = [$listing['image_path']]; 
if (!empty($listing['additional_images'])) {
    $extraImages = explode(',', $listing['additional_images']);
    $allImages = array_merge($allImages, $extraImages);
}

$comStmt = $pdo->prepare('SELECT c.*, u.full_name, u.profile_image, u.user_id as commenter_id FROM comments c JOIN users u ON c.user_id = u.user_id WHERE c.listing_id = ? ORDER BY c.created_at DESC');
$comStmt->execute([$listingId]);
$comments = $comStmt->fetchAll();

$bidStmt = $pdo->prepare('SELECT b.*, u.full_name, u.profile_image FROM bids b JOIN users u ON b.user_id = u.user_id WHERE b.listing_id = ? ORDER BY b.amount DESC');
$bidStmt->execute([$listingId]);
$bids = $bids['fetchAll'] ?? $bidStmt->fetchAll();
$highestBid = !empty($bids) ? $bids[0]['amount'] : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($listing['title']) ?> - TradePlace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8fafc; }
        
        /* Modern Flexible Carousel Sizing */
        .carousel-item img { 
            width: 100%; 
            height: 480px;
            max-height: 60vh;
            object-fit: contain; 
            background-color: #0f172a; 
        }
        
        .seller-card { 
            border: 1px solid #e2e8f0; 
            border-radius: 14px; 
            background-color: white; 
            padding: 24px; 
            transition: all 0.2s ease-in-out; 
        }
        .seller-card:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.04); }
        
        .seller-avatar { width: 56px; height: 56px; border-radius: 50%; object-fit: cover; background-color: #d1fae5; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; color: #047857; font-weight: 700; }
        .bidder-avatar { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; background-color: #f1f5f9; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; color: #475569; font-weight: 700; margin-right: 12px;}
        
        .action-box { border: 1px solid #10b981; border-radius: 14px; background-color: #f0fdf4; padding: 24px; margin-top: 16px; }
        .comment-bubble { background-color: #f8fafc; border-radius: 14px; padding: 18px; margin-bottom: 16px; border: 1px solid #e2e8f0; }
        .seller-reply-badge { font-size: 0.65rem; background-color: #10b981; color: white; padding: 3px 8px; border-radius: 6px; font-weight: 700; margin-left: 6px; }
        
        .sold-badge { 
            background-color: #ef4444; 
            color: white; 
            padding: 8px 16px; 
            border-radius: 8px; 
            font-weight: 900; 
            font-size: 1.05rem; 
            letter-spacing: 1px; 
            text-transform: uppercase; 
            display: inline-block; 
        }
        
        /* Interactive Star Matrix Engine */
        .star-container { position: relative; display: inline-block; cursor: ew-resize; user-select: none; }
        .star-bg { font-size: 3.5rem; color: #cbd5e1; line-height: 1; letter-spacing: 2px; }
        .star-fill { position: absolute; top: 0; left: 0; overflow: hidden; white-space: nowrap; color: #10b981; font-size: 3.5rem; line-height: 1; letter-spacing: 2px; pointer-events: none; width: 100%; transition: width 0.1s ease-out; }
        
        @media (max-width: 991.98px) {
            .carousel-item img { height: 340px; max-height: 45vh; border-radius: 0 !important; }
            .product-title-row { font-size: 1.8rem !important; }
            .price-display-tag { font-size: 2.4rem !important; }
            .sticky-container-adjustment { padding-left: 0 !important; padding-right: 0 !important; }
        }
    </style>
</head>
<body>

    <?php include 'includes/navbar.php'; ?>

    <div class="container mb-5 sticky-container-adjustment">
        <?php if ($successMsg): ?>
            <div class="alert alert-success fw-bold shadow-sm mx-3 mx-lg-0 border-0 mb-4 d-flex align-items-center gap-2" style="background-color: #d1fae5; color: #047857;">
                <i class="bi bi-check-circle-fill"></i> <span><?= htmlspecialchars($successMsg) ?></span>
            </div>
        <?php endif; ?>

        <div class="row g-4 px-3 px-lg-0">
            <div class="col-lg-6 order-0">
                
                <div id="productCarousel" class="carousel slide shadow-sm bg-dark overflow-hidden mb-4" data-bs-ride="false" style="border-radius: 14px;">
                    <div class="carousel-indicators">
                        <?php foreach ($allImages as $index => $img): ?>
                            <button type="button" data-bs-target="#productCarousel" data-bs-slide-to="<?= $index ?>" class="<?= $index === 0 ? 'active' : '' ?>"></button>
                        <?php endforeach; ?>
                    </div>
                    <div class="carousel-inner">
                        <?php foreach ($allImages as $index => $img): ?>
                            <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                                <img src="<?= htmlspecialchars($img) ?>" alt="Product Media File">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($allImages) > 1): ?>
                        <button class="carousel-control-prev" type="button" data-bs-target="#productCarousel" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon bg-dark rounded-circle p-3" aria-hidden="true"></span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#productCarousel" data-bs-slide="next">
                            <span class="carousel-control-next-icon bg-dark rounded-circle p-3" aria-hidden="true"></span>
                        </button>
                    <?php endif; ?>
                </div>

                <div class="card border-0 shadow-sm mb-4 rounded-3">
                    <div class="card-body p-4">
                        <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom"><i class="bi bi-text-left text-success me-1"></i> Description</h5>
                        <p class="text-secondary mb-0" style="white-space: pre-wrap; line-height: 1.7; font-size: 0.98rem;"><?= htmlspecialchars($listing['description']) ?></p>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-body p-4">
                        <h5 class="fw-bold text-dark mb-4 pb-2 border-bottom d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-chat-left-text text-success me-1"></i> Questions & Answers</span>
                            <span class="badge bg-light text-secondary border rounded-pill px-3" style="font-size: 0.8rem;"><?= count($comments) ?></span>
                        </h5>
                        
                        <?php if ($isLoggedIn && $listing['status'] === 'Active' && !$deliveryConfirmed): ?>
                            <form action="view_listing.php?id=<?= $listingId ?>" method="POST" class="mb-4">
                                <input type="hidden" name="action" value="post_comment">
                                <div class="input-group custom-search-group rounded-3 overflow-hidden border">
                                    <input type="text" class="form-control py-2 border-0 bg-light" name="comment_text" placeholder="<?= $isSeller ? 'Reply to buyers...' : 'Ask the seller a question publicly...' ?>" required style="font-size: 0.95rem;">
                                    <button class="btn btn-dark fw-bold px-4" type="submit">Post</button>
                                </div>
                            </form>
                        <?php elseif ($listing['status'] === 'Sold' || $deliveryConfirmed): ?>
                            <div class="alert alert-light text-center text-secondary border small py-3 mb-4 rounded-3 fw-medium">
                                <i class="bi bi-lock-fill me-1"></i> This trade transaction has successfully completed. Comment logs are sealed.
                            </div>
                        <?php endif; ?>

                        <?php if (empty($comments)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-chat-dots opacity-25 d-block mb-2 fs-3"></i>
                                <span class="small italic">No public inquiries initialized yet.</span>
                            </div>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-2">
                                <?php foreach ($comments as $comment): ?>
                                    <div class="comment-bubble d-flex gap-3 shadow-sm bg-white">
                                        <a href="seller_profile.php?id=<?= $comment['commenter_id'] ?>" class="flex-shrink-0">
                                            <?php if ($comment['profile_image']): ?>
                                                <img src="<?= htmlspecialchars($comment['profile_image']) ?>" class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="rounded-circle bg-light text-secondary d-flex align-items-center justify-content-center border fw-bold" style="width: 40px; height: 40px; font-size: 1.1rem;">
                                                    <?= strtoupper(substr($comment['full_name'], 0, 1)) ?>
                                                </div>
                                            <?php endif; ?>
                                        </a>
                                        <div class="w-100">
                                            <div class="d-flex flex-wrap align-items-center mb-1 gap-2">
                                                <a href="seller_profile.php?id=<?= $comment['commenter_id'] ?>" class="fw-bold text-decoration-none text-dark hover-underline" style="font-size: 0.95rem;">
                                                    <?= htmlspecialchars($comment['full_name']) ?>
                                                </a>
                                                <?php if ($comment['commenter_id'] === $listing['user_id']): ?>
                                                    <span class="seller-reply-badge">SELLER</span>
                                                <?php endif; ?>
                                                <span class="text-muted ms-auto" style="font-size: 0.75rem;"><?= date('M d, g:i A', strtotime($comment['created_at'])) ?></span>
                                                
                                                <?php if ($comment['commenter_id'] === $currentUserId): ?>
                                                    <form action="view_listing.php?id=<?= $listingId ?>" method="POST" class="m-0" onsubmit="return confirm('Are you sure you want to delete your comment?');">
                                                        <input type="hidden" name="action" value="delete_comment">
                                                        <input type="hidden" name="comment_id" value="<?= $comment['comment_id'] ?>">
                                                        <button type="submit" class="btn btn-link text-danger p-0 ms-2 border-0 text-decoration-none fw-bold" style="font-size: 0.8rem;"><i class="bi bi-trash3-fill"></i></button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                            <p class="mb-0 text-secondary" style="font-size: 0.92rem; line-height: 1.5;"><?= htmlspecialchars($comment['comment_text']) ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <div class="col-lg-6 order-1 order-lg-1 ps-lg-4">
                
                <div class="card border-0 shadow-sm mb-4 rounded-3">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="badge bg-light text-secondary border px-3 py-2 rounded-2 fw-semibold" style="font-size: 0.8rem;"><?= htmlspecialchars($listing['category']) ?></span>
                            <?php if ($listing['status'] === 'Sold' || $deliveryConfirmed): ?>
                                <div class="sold-badge shadow-sm">SOLD</div>
                            <?php endif; ?>
                        </div>
                        
                        <h2 class="fw-bold mb-1 text-dark product-title-row" style="letter-spacing: -0.5px; line-height: 1.25;"><?= htmlspecialchars($listing['title']) ?></h2>
                        <p class="text-muted small mb-4 d-flex align-items-center gap-2"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($listing['location']) ?> &bull; <i class="bi bi-calendar3"></i> Posted <?= date('M d, Y', strtotime($listing['created_at'])) ?></p>
                        
                        <h1 class="text-success fw-bold border-bottom pb-4 mb-0 price-display-tag" style="font-size: 2.8rem; letter-spacing: -1px;">R <?= number_format($listing['price'], 2) ?></h1>

                        <?php if ($isLoggedIn && $currentUserId === $winningBuyerId && !$deliveryConfirmed): ?>
                            <div class="action-box bg-white border border-primary mt-4 text-center">
                                <div class="text-primary mb-2"><i class="bi bi-award-fill fs-1"></i></div>
                                <h5 class="fw-bold text-dark mb-2">Your Offer Was Accepted!</h5>
                                <p class="text-secondary small mb-4 px-2">Coordinate processing meetups in secure public segments with the seller. Confirm distribution handovers right here below.</p>
                                <form action="view_listing.php?id=<?= $listingId ?>" method="POST">
                                    <input type="hidden" name="action" value="confirm_delivery">
                                    <input type="hidden" name="bid_id" value="<?= $winningBid['bid_id'] ?>">
                                    <button type="submit" class="btn btn-primary w-100 fw-bold py-2 shadow-sm rounded-3" onclick="return confirm('Confirm receipt tracking exclusively if you have items inside physical custody.');">✓ Confirm Collection Distribution</button>
                                </form>
                            </div>
                        <?php endif; ?>

                        <div id="review-box" style="scroll-margin-top: 120px;"></div>
                        
                        <?php if ($isLoggedIn && $currentUserId === $winningBuyerId && $deliveryConfirmed && !$hasReviewed): ?>
                            <div class="action-box border border-success rounded-3 mt-4" style="background-color: #f8fafc;">
                                <h5 class="fw-bold text-center text-dark mb-2">Rate Your Exchange Execution!</h5>
                                <p class="text-muted text-center small mb-3">Provide transparency analytics regarding <?= htmlspecialchars($listing['full_name']) ?>.</p>
                                
                                <form action="view_listing.php?id=<?= $listingId ?>" method="POST">
                                    <input type="hidden" name="action" value="submit_review">
                                    
                                    <div class="mb-3 text-center">
                                        <div class="star-container" id="star-container">
                                            <div class="star-bg">★★★★★</div>
                                            <div class="star-fill" id="star-fill">★★★★★</div>
                                        </div>
                                        <input type="hidden" name="rating" id="rating-input" value="5.0">
                                        <div id="rating-text" class="fw-bold mt-2 text-success" style="font-size: 1.15rem;">5.0 / 5.0</div>
                                    </div>

                                    <div class="mb-3">
                                        <textarea class="form-control shadow-sm rounded-3" name="review_text" rows="3" placeholder="Provide qualitative transaction records detailing swap behaviors..." required style="font-size: 0.9rem;"></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-success w-100 fw-bold py-2 shadow-sm rounded-3" style="background-color: #10b981; border: none;">Submit Review</button>
                                </form>
                            </div>

                            <script>
                                const container = document.getElementById('star-container');
                                const fill = document.getElementById('star-fill');
                                const input = document.getElementById('rating-input');
                                const text = document.getElementById('rating-text');
                                let lockedValue = 5.0;

                                container.addEventListener('mousemove', (e) => {
                                    const rect = container.getBoundingClientRect();
                                    const x = e.clientX - rect.left;
                                    const percent = Math.max(0, Math.min(1, x / rect.width));
                                    let rating = Math.ceil(percent * 10) / 2;
                                    if(rating < 0.5) rating = 0.5;
                                    fill.style.width = (rating / 5 * 100) + '%';
                                    text.innerText = rating.toFixed(1) + ' / 5.0';
                                });

                                container.addEventListener('mouseleave', () => {
                                    fill.style.width = (lockedValue / 5 * 100) + '%';
                                    text.innerText = lockedValue.toFixed(1) + ' / 5.0';
                                });

                                container.addEventListener('click', (e) => {
                                    const rect = container.getBoundingClientRect();
                                    const x = e.clientX - rect.left;
                                    const percent = Math.max(0, Math.min(1, x / rect.width));
                                    let rating = Math.ceil(percent * 10) / 2;
                                    if(rating < 0.5) rating = 0.5;
                                    lockedValue = rating;
                                    input.value = lockedValue;
                                });
                            </script>

                        <?php elseif ($isLoggedIn && $currentUserId === $winningBuyerId && $deliveryConfirmed && $hasReviewed): ?>
                            <div class="alert alert-success border-0 mt-4 py-3 fw-bold text-center shadow-sm rounded-3" style="background-color: #d1fae5; color: #047857;"><i class="bi bi-shield-check me-2"></i> Transaction Ledger Evaluated ✓</div>
                        <?php endif; ?>

                        <?php if ($listing['status'] === 'Active' && !$deliveryConfirmed): ?>
                            <div class="action-box border border-light shadow-sm bg-light rounded-3 p-4 mt-4">
                                <?php if (!$isLoggedIn): ?>
                                    <h6 class="fw-bold text-center text-dark mb-4">Initialize Exchange Offers?</h6>
                                    <a href="login.php" class="btn btn-success w-100 fw-bold py-2 rounded-3" style="background-color: #10b981; border: none;">Log In to Make an Offer</a>
                                
                                <?php elseif ($isSeller): ?>
                                    
                                    <?php if ($winningBuyerId && !$deliveryConfirmed): ?>
                                        <div class="alert alert-warning border-0 text-center fw-bold shadow-sm mb-4 rounded-3 small py-3">
                                            ⏳ Standing authorization pending matching receipt confirmations.
                                        </div>
                                    <?php endif; ?>

                                    <div class="d-flex gap-3 mb-4">
                                        <button class="btn btn-warning fw-bold flex-grow-1 py-2 rounded-3 text-dark" data-bs-toggle="modal" data-bs-target="#editListingModal">Edit Details</button>
                                        <form action="view_listing.php?id=<?= $listingId ?>" method="POST" onsubmit="return confirm('Move index pointer to soft archive structures?');" class="flex-grow-1 m-0">
                                            <input type="hidden" name="action" value="delete_listing">
                                            <button type="submit" class="btn btn-danger fw-bold py-2 rounded-3 w-100">Archive Item</button>
                                        </form>
                                    </div>

                                    <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom"><i class="bi bi-graph-up-arrow me-2 text-success"></i> Received Offers</h5>
                                    
                                    <?php if (empty($bids)): ?>
                                        <div class="text-center py-4 text-muted small"><i class="bi bi-inbox opacity-25 d-block fs-2 mb-2"></i> No inbound proposals tracked yet.</div>
                                    <?php else: ?>
                                        <div class="d-flex flex-column gap-3">
                                            <?php foreach ($bids as $bid): ?>
                                                <div class="card border-0 shadow-sm bg-white p-3 rounded-3">
                                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                                        <div class="d-flex align-items-center">
                                                            <?php if ($bid['profile_image']): ?>
                                                                <img src="<?= htmlspecialchars($bid['profile_image']) ?>" class="bidder-avatar">
                                                            <?php else: ?>
                                                                <div class="bidder-avatar"><?= strtoupper(substr($bid['full_name'], 0, 1)) ?></div>
                                                            <?php endif; ?>
                                                            <div>
                                                                <span class="fw-bold d-block text-dark small" style="line-height: 1.2;"><?= htmlspecialchars($bid['full_name']) ?></span>
                                                                <small class="text-muted" style="font-size: 0.7rem;"><?= date('M d, g:i A', strtotime($bid['created_at'])) ?></small>
                                                            </div>
                                                        </div>
                                                        <div class="text-end">
                                                            <h5 class="text-success fw-bold mb-0 small">R <?= number_format($bid['amount'], 2) ?></h5>
                                                            <?php if ($bid['status'] === 'Accepted'): ?>
                                                                <span class="badge bg-success py-1 mt-1" style="font-size:0.65rem;">Authorized</span>
                                                            <?php elseif ($bid['status'] === 'Rejected'): ?>
                                                                <span class="badge bg-secondary py-1 mt-1" style="font-size:0.65rem;">Declined</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-warning text-dark py-1 mt-1" style="font-size:0.65rem;">Pending</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php if ($bid['status'] === 'Pending'): ?>
                                                    <form action="view_listing.php?id=<?= $listingId ?>" method="POST" class="d-flex gap-2 mb-2">
                                                        <input type="hidden" name="bid_id" value="<?= $bid['bid_id'] ?>">
                                                        <button type="submit" name="action" value="accept_bid" class="btn btn-sm btn-success fw-bold flex-grow-1 py-2" style="background-color: #10b981; border: none; font-size:0.8rem;" <?= $winningBuyerId ? 'disabled' : '' ?>>Accept</button>
                                                        <button type="submit" name="action" value="reject_bid" class="btn btn-sm btn-outline-danger fw-bold flex-grow-1 py-2" style="font-size:0.8rem;">Decline</button>
                                                    </form>
                                                    <?php endif; ?>
                                                    
                                                    <div class="d-flex gap-2">
                                                        <a href="seller_profile.php?id=<?= $bid['user_id'] ?>" class="btn btn-sm btn-light border fw-bold flex-grow-1 py-2" style="font-size:0.78rem;">Profile</a>
                                                        <?php 
                                                            $firstName = explode(' ', $bid['full_name'])[0];
                                                            $msgText = "Hi $firstName, regarding your offer of R" . number_format($bid['amount'], 2) . " on '" . $listing['title'] . "': ";
                                                        ?>
                                                        <a href="messages.php?user=<?= $bid['user_id'] ?>&prefill=<?= urlencode($msgText) ?>" class="btn btn-sm btn-dark fw-bold flex-grow-1 py-2" style="font-size:0.78rem;"><i class="bi bi-chat-left-dots me-1"></i> Message</a>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                
                                <?php elseif ($currentUserId !== $winningBuyerId): ?>
                                    
                                    <form action="view_listing.php?id=<?= $listingId ?>" method="POST" class="mb-3" onsubmit="this.querySelector('button[type=submit]').disabled = true;">
                                        <input type="hidden" name="action" value="make_bid">
                                        <input type="hidden" name="bid_amount" value="<?= $listing['price'] ?>">
                                        <button type="submit" class="btn btn-dark w-100 fw-bold py-3 shadow-sm rounded-3" <?= $winningBuyerId ? 'disabled' : '' ?>><?= $winningBuyerId ? 'Sale Pending' : 'Buy Now for R ' . number_format($listing['price'], 2) ?></button>
                                    </form>
                                    
                                    <div class="position-relative text-center my-4">
                                        <hr class="text-muted my-0">
                                        <span class="position-absolute top-50 start-50 translate-middle bg-light px-3 text-secondary small fw-bold" style="font-size: 0.7rem; letter-spacing:0.5px;">OR PROPOSE VALUE EXCHANGE</span>
                                    </div>

                                    <form action="view_listing.php?id=<?= $listingId ?>" method="POST" onsubmit="this.querySelector('button[type=submit]').disabled = true;">
                                        <input type="hidden" name="action" value="make_bid">
                                        <div class="input-group mb-3 rounded-3 overflow-hidden border">
                                            <span class="input-group-text bg-white border-0 fw-bold text-secondary">R</span>
                                            <input type="number" class="form-control border-0 py-2" name="bid_amount" placeholder="0.00" min="1" required <?= $winningBuyerId ? 'disabled' : '' ?> style="font-size: 0.95rem;">
                                        </div>
                                        <button type="submit" class="btn btn-success w-100 fw-bold py-2 mb-3 rounded-3" style="background-color: #10b981; border: none;" <?= $winningBuyerId ? 'disabled' : '' ?>>Send Custom Offer</button>
                                        
                                        <?php $buyerMsg = "Hi, I am interested in your listing: '" . $listing['title'] . "'. Is it still available?"; ?>
                                        <a href="messages.php?user=<?= $listing['user_id'] ?>&prefill=<?= urlencode($buyerMsg) ?>" class="btn btn-outline-secondary w-100 fw-bold py-2 bg-white mb-2 rounded-3" style="border-color:#cbd5e1; color:#475569;"><i class="bi bi-chat-dots-fill me-2"></i> Send Private Message</a>
                                    </form>

                                    <form action="view_listing.php?id=<?= $listingId ?>" method="POST" class="mt-2">
                                        <input type="hidden" name="action" value="toggle_save">
                                        <?php if ($isSaved): ?>
                                            <button type="submit" class="btn btn-outline-danger w-100 fw-bold py-2 bg-white rounded-3"><i class="bi bi-heart-break-fill me-2"></i> Remove from Saved</button>
                                        <?php else: ?>
                                            <button type="submit" class="btn btn-outline-secondary w-100 fw-bold py-2 bg-white rounded-3" style="border-color:#cbd5e1; color:#475569;"><i class="bi bi-heart me-2"></i> Save for Later</button>
                                        <?php endif; ?>
                                    </form>

                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!$isSeller): ?>
                <div class="seller-card shadow-sm mt-4">
                    <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                        <h6 class="text-secondary small mb-0 fw-bold text-uppercase" style="letter-spacing:0.5px;"><i class="bi bi-person-badge-fill me-1 text-success"></i> About Vendor</h6>
                        <?php if ($isLoggedIn): ?>
                            <button type="button" class="btn btn-link text-danger text-decoration-none p-0 small fw-bold d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#reportPostModal" style="font-size: 0.8rem;">
                                <i class="bi bi-flag-fill"></i> Report Listing
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <a href="seller_profile.php?id=<?= $listing['user_id'] ?>" class="d-flex align-items-center mb-4 text-decoration-none">
                        <?php if ($listing['profile_image']): ?>
                            <img src="<?= htmlspecialchars($listing['profile_image']) ?>" class="seller-avatar me-3">
                        <?php else: ?>
                            <div class="seller-avatar me-3"><?= strtoupper(substr($listing['full_name'], 0, 1)) ?></div>
                        <?php endif; ?>
                        
                        <div class="text-start">
                            <h6 class="fw-bold mb-0 text-dark d-flex align-items-center gap-1" style="font-size:1.05rem;">
                                <?= htmlspecialchars($listing['full_name']) ?>
                                <?php if ($listing['is_verified']): ?>
                                    <i class="bi bi-patch-check-fill text-success fs-6"></i>
                                <?php endif; ?>
                            </h6>
                            
                            <?php if ($listing['review_count'] > 0): ?>
                                <small class="fw-bold" style="color: #f59e0b;"><i class="bi bi-star-fill"></i> <?= number_format($listing['rating'], 1) ?> <span class="text-muted">(<?= $listing['review_count'] ?> reviews)</span></small>
                            <?php else: ?>
                                <small class="text-muted d-block" style="font-size:0.8rem;">Newly Registered Trader</small>
                            <?php endif; ?>
                        </div>
                    </a>
                    
                    <a href="seller_profile.php?id=<?= $listing['user_id'] ?>" class="btn btn-outline-secondary w-100 fw-bold py-2 rounded-3" style="border-color:#cbd5e1; color:#475569;">View Profile & Review History</a>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <?php if ($isSeller && $listing['status'] === 'Active' && !$deliveryConfirmed): ?>
    <div class="modal fade" id="editListingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 overflow-hidden shadow-lg" style="border-radius:14px;">
                <div class="modal-header px-4 style-overwrite bg-light" style="border-bottom: 1px solid #e2e8f0;">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-pencil-square text-success me-2"></i> Edit Trade Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="view_listing.php?id=<?= $listingId ?>" method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="edit_listing">
                        
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label fw-bold text-secondary small">UPDATE TITLE</label>
                                <input type="text" class="form-control border rounded-3 py-2" name="new_title" value="<?= htmlspecialchars($listing['title']) ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold text-secondary small">UPDATE PRICE (R)</label>
                                <div class="input-group border rounded-3 overflow-hidden">
                                    <span class="input-group-text bg-white border-0 fw-bold text-muted">R</span>
                                    <input type="number" class="form-control border-0 py-2" name="new_price" value="<?= htmlspecialchars($listing['price']) ?>" min="1" step="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold text-secondary small">UPDATE CATEGORY</label>
                                <select name="new_category" class="form-select border rounded-3 py-2" required>
                                    <option value="Vehicles" <?= $listing['category'] === 'Vehicles' ? 'selected' : '' ?>>Vehicles</option>
                                    <option value="Electronics" <?= $listing['category'] === 'Electronics' ? 'selected' : '' ?>>Electronics</option>
                                    <option value="Sports & Outdoors" <?= $listing['category'] === 'Sports & Outdoors' ? 'selected' : '' ?>>Sports & Outdoors</option>
                                    <option value="Home & Garden" <?= $listing['category'] === 'Home & Garden' ? 'selected' : '' ?>>Home & Garden</option>
                                    <option value="Clothing" <?= $listing['category'] === 'Clothing' ? 'selected' : '' ?>>Clothing</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold text-secondary small">UPDATE TOWN</label>
                                <input type="text" class="form-control border rounded-3 py-2" name="new_location" value="<?= htmlspecialchars($listing['location']) ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold text-secondary small">UPDATE PROVINCE</label>
                                <select name="new_province" class="form-select border rounded-3 py-2" required>
                                    <option value="Eastern Cape" <?= $listing['province'] === 'Eastern Cape' ? 'selected' : '' ?>>Eastern Cape</option>
                                    <option value="Free State" <?= $listing['province'] === 'Free State' ? 'selected' : '' ?>>Free State</option>
                                    <option value="Gauteng" <?= $listing['province'] === 'Gauteng' ? 'selected' : '' ?>>Gauteng</option>
                                    <option value="KwaZulu-Natal" <?= $listing['province'] === 'KwaZulu-Natal' ? 'selected' : '' ?>>KwaZulu-Natal</option>
                                    <option value="Limpopo" <?= $listing['province'] === 'Limpopo' ? 'selected' : '' ?>>Limpopo</option>
                                    <option value="Mpumalanga" <?= $listing['province'] === 'Mpumalanga' ? 'selected' : '' ?>>Mpumalanga</option>
                                    <option value="Northern Cape" <?= $listing['province'] === 'Northern Cape' ? 'selected' : '' ?>>Northern Cape</option>
                                    <option value="North West" <?= $listing['province'] === 'North West' ? 'selected' : '' ?>>North West</option>
                                    <option value="Western Cape" <?= $listing['province'] === 'Western Cape' ? 'selected' : '' ?>>Western Cape</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-0 mt-2">
                            <label class="form-label fw-bold text-secondary small">UPDATE DESCRIPTION STATEMENT</label>
                            <textarea class="form-control border rounded-3 p-3" name="new_description" rows="5" required style="font-size:0.92rem; line-height:1.5;"><?= htmlspecialchars($listing['description']) ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer px-4 py-3 bg-light border-top">
                        <button type="button" class="btn btn-link text-secondary text-decoration-none fw-semibold px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success fw-bold px-4 rounded-3" style="background-color: #10b981; border: none;">Save Details</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($isLoggedIn && !$isSeller): ?>
    <div class="modal fade" id="reportPostModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 overflow-hidden shadow-lg" style="border-radius:14px;">
                <div class="modal-header px-4 bg-light" style="border-bottom: 1px solid #e2e8f0;">
                    <h5 class="modal-title fw-bold text-dark text-danger"><i class="bi bi-flag-fill me-2"></i> Report Listing</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="view_listing.php?id=<?= $listingId ?>" method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="report_post">
                        
                        <div class="alert alert-danger border-0 small rounded-3 d-flex align-items-start gap-2 mb-4 shadow-sm" style="background-color: #fef2f2; color: #991b1b;">
                            <i class="bi bi-exclamation-triangle-fill mt-0.5 flex-shrink-0"></i>
                            <div>
                                <span class="fw-bold">Community Guideline Warning:</span> Submitting false, malicious, or intentionally invalid moderation reports constitutes an abuse of platform security features. Unjustified filings may result in account penalties or permanent profile suspensions.
                            </div>
                        </div>

                        <div class="mb-0">
                            <label class="form-label fw-bold text-secondary small">SELECT REPORT REASON CATEGORY</label>
                            <select class="form-select border p-2.5 rounded-3 style-select-input" name="report_reason_select" required style="font-size: 0.95rem;">
                                <option value="" disabled selected>Choose a category...</option>
                                <option value="Spam / Deceptive Advertising">Spam / Deceptive Advertising</option>
                                <option value="Counterfeit / Illegal Items">Counterfeit / Illegal Items</option>
                                <option value="Harassment / Inappropriate Imagery">Harassment / Inappropriate Imagery</option>
                                <option value="Fraud / Suspicious Pricing Dynamics">Fraud / Suspicious Pricing Dynamics</option>
                                <option value="Other Policy Violation">Other Policy Violation</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer px-4 py-3 bg-light border-top">
                        <button type="button" class="btn btn-link text-secondary text-decoration-none fw-semibold px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger fw-bold px-4 rounded-3">Submit Verification Report</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>