<?php
session_start();
require_once 'includes/db.php';

// 1. get ID from URL
$sellerId = $_GET['id'] ?? null;
if (!$sellerId) {
    header('Location: index.php');
    exit;
}

//report user, logic
$successMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'report_user' && isset($_SESSION['user_id'])) {
    $reportReasonSelection = $_POST['report_reason_select'] ?? '';
    $reportedUserId = $_POST['reported_user_id'] ?? null; 

    if (!empty($reportReasonSelection) && $reportedUserId) {
        //fetch name in admin panel
        $nameCheck = $pdo->prepare('SELECT full_name FROM users WHERE user_id = ?');
        $nameCheck->execute([$reportedUserId]);
        $sellerName = $nameCheck->fetchColumn() ?: 'Unknown User'; 
        
        $constructedReason = "User Reported: [" . $sellerName . "] - Reason Category: " . $reportReasonSelection;
        
        $reportQuery = $pdo->prepare('INSERT INTO reports (reporter_id, reported_user_id, reason, status) VALUES (?, ?, ?, \'Pending\')');
        $reportQuery->execute([$_SESSION['user_id'], $reportedUserId, $constructedReason]);
        
        $successMsg = "The user report has been securely submitted to administration for review.";
    }
}
// -------------------------

// 2. fetch seller details
$stmt = $pdo->prepare('SELECT user_id, full_name, is_verified, rating, review_count, town, profile_image, created_at FROM users WHERE user_id = ?');
$stmt->execute([$sellerId]);
$seller = $stmt->fetch();

if (!$seller) {
    header('Location: index.php');
    exit;
}

// 3. active items
$listingStmt = $pdo->prepare("SELECT * FROM listings WHERE user_id = ? AND status = 'Active' ORDER BY created_at DESC");
$listingStmt->execute([$sellerId]);
$activeListings = $listingStmt->fetchAll();

// 4. reviews (Now including reply_text)
$reviewStmt = $pdo->prepare('
    SELECT r.*, u.full_name as reviewer_name, u.profile_image as reviewer_image 
    FROM reviews r 
    JOIN users u ON r.reviewer_id = u.user_id 
    WHERE r.reviewee_id = ? 
    ORDER BY r.created_at DESC
');
$reviewStmt->execute([$sellerId]);
$reviewsList = $reviewStmt->fetchAll();

// 5. visual tier logic
$badgeColor = '#10b981'; $badgeText = 'New Seller';
$avgRating = (float)$seller['rating'];
$totalReviews = (int)$seller['review_count'];

if ($totalReviews > 0) {
    if ($avgRating < 2.0) { $badgeColor = '#ef4444'; $badgeText = 'Flagged'; } 
    elseif ($avgRating < 3.0) { $badgeColor = '#cd7f32'; $badgeText = 'Bronze Tier'; } 
    elseif ($avgRating < 4.0) { $badgeColor = '#94a3b8'; $badgeText = 'Silver Tier'; } 
    else { $badgeColor = '#fbbf24'; $badgeText = 'Gold Tier'; } 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($seller['full_name']) ?> - SafeTrade Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; }
        .profile-header-card { border-radius: 15px; border: none; overflow: hidden; }
        .seller-avatar-lg { width: 130px; height: 120px; border-radius: 50%; object-fit: cover; border: 5px solid white; background-color: #e2e8f0; }
        
        .stars-outer { display: inline-block; position: relative; font-family: "Font Awesome 5 Free"; font-weight: 900; font-size: 1.3rem; color: #cbd5e1; }
        .stars-inner { position: absolute; top: 0; left: 0; white-space: nowrap; overflow: hidden; color: #f59e0b; }
        
        .product-card { border: 1px solid #e2e8f0; border-radius: 12px; transition: 0.3s; background: white; text-decoration: none; color: inherit; }
        .product-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .product-img { aspect-ratio: 1/1; object-fit: cover; border-bottom: 1px solid #e2e8f0; }
        
        .review-bubble { background: white; border-radius: 15px; padding: 20px; border: 1px solid #e2e8f0; margin-bottom: 15px; }
        .seller-reply-box { background-color: #f8fafc; border-left: 3px solid #cbd5e1; padding: 12px 15px; border-radius: 0 8px 8px 0; margin-top: 12px; margin-left: 15px; }
        .sticky-sidebar { position: sticky; top: 20px; }
    </style>
</head>
<body>

    <?php include 'includes/navbar.php'; ?>

    <div class="container mt-4">
        
        <?php if ($successMsg): ?>
            <div class="alert alert-success fw-bold shadow-sm border-0 mb-4 d-flex align-items-center gap-2" style="background-color: #d1fae5; color: #047857; border-radius:12px;">
                <i class="bi bi-check-circle-fill"></i> <span><?= htmlspecialchars($successMsg) ?></span>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="card profile-header-card shadow-sm text-center p-4 sticky-sidebar">
                    <div class="mb-3">
                        <?php if ($seller['profile_image']): ?>
                            <img src="<?= htmlspecialchars($seller['profile_image']) ?>" class="seller-avatar-lg shadow-sm">
                        <?php else: ?>
                            <div class="seller-avatar-lg shadow-sm mx-auto d-flex align-items-center justify-content-center text-muted fw-bold" style="font-size: 3rem;">
                                <?= strtoupper(substr($seller['full_name'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <h3 class="fw-bold mb-1"><?= htmlspecialchars($seller['full_name']) ?> 
                        <?php if ($seller['is_verified']): ?>
                            <span class="text-success" style="font-size: 1.2rem;">✓</span>
                        <?php endif; ?>
                    </h3>
                    
                    <div class="mb-3">
                        <span class="badge shadow-sm px-3 py-2" style="background-color: <?= $badgeColor ?>; font-size: 0.85rem;">
                            <?= $badgeText ?>
                        </span>
                    </div>

                    <p class="text-muted small">
                        📍 Based in <?= htmlspecialchars($seller['town']) ?><br>
                        Member since <?= date('F Y', strtotime($seller['created_at'])) ?>
                    </p>

                    <div class="row border-top border-bottom py-3 my-3">
                        <div class="col-6 border-end">
                            <h5 class="mb-0 fw-bold"><?= count($activeListings) ?></h5>
                            <small class="text-muted">Items</small>
                        </div>
                        <div class="col-6">
                            <h5 class="mb-0 fw-bold text-warning"><?= $totalReviews > 0 ? number_format($avgRating, 1) : 'N/A' ?> ★</h5>
                            <small class="text-muted"><?= $totalReviews ?> Reviews</small>
                        </div>
                    </div>

                    <div class="d-flex flex-column gap-2">
                        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $seller['user_id']): ?>
                            <a href="messages.php?user=<?= $seller['user_id'] ?>" class="btn btn-success w-100 fw-bold py-2 shadow-sm rounded-pill"><i class="bi bi-chat-dots-fill me-1"></i> Message Seller</a>
                            
                            <button type="button" class="btn btn-outline-danger w-100 fw-bold py-2 shadow-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#reportUserModal">
                                <i class="bi bi-flag-fill me-1"></i> Report Profile
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="fw-bold m-0">Active Trades</h4>
                    <span class="text-muted small"><?= count($activeListings) ?> items found</span>
                </div>

                <div class="row row-cols-1 row-cols-md-2 g-3 mb-5">
                    <?php if (empty($activeListings)): ?>
                        <div class="col-12">
                            <div class="alert alert-light text-center border p-5">
                                <p class="text-muted m-0">This seller currently has no active items for sale.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($activeListings as $item): ?>
                            <div class="col">
                                <a href="view_listing.php?id=<?= $item['listing_id'] ?>" class="card h-100 product-card d-flex flex-column">
                                    <img src="<?= htmlspecialchars($item['image_path']) ?>" class="card-img-top product-img">
                                    <div class="card-body p-3">
                                        <h6 class="card-title mb-1 fw-bold text-truncate text-dark"><?= htmlspecialchars($item['title']) ?></h6>
                                        <p class="text-success fw-bold mb-0">R <?= number_format($item['price'], 2) ?></p>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <h4 class="fw-bold mb-3 border-top pt-4">Buyer Feedback</h4>
                
                <?php if (empty($reviewsList)): ?>
                    <div class="alert alert-light text-center border py-4">
                        <p class="text-muted m-0">No reviews yet for this seller.</p>
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column">
                        <?php foreach ($reviewsList as $rev): ?>
                            <div class="review-bubble shadow-sm">
                                <div class="d-flex justify-content-between mb-2">
                                    <div class="d-flex align-items-center">
                                        <div class="fw-bold text-dark me-2"><?= htmlspecialchars($rev['reviewer_name']) ?></div>
                                        <div class="stars-outer">
                                            ★★★★★
                                            <div class="stars-inner" style="width: <?= ($rev['rating'] / 5) * 100 ?>%;">★★★★★</div>
                                        </div>
                                        <span class="ms-2 small fw-bold"><?= number_format($rev['rating'], 1) ?></span>
                                    </div>
                                    <small class="text-muted"><?= date('M d, Y', strtotime($rev['created_at'])) ?></small>
                                </div>
                                <p class="mb-0 text-muted fst-italic" style="font-size: 0.95rem;">
                                    "<?= nl2br(htmlspecialchars($rev['review_text'])) ?>"
                                </p>
                                
                                <?php if (!empty($rev['reply_text'])): ?>
                                    <div class="seller-reply-box mt-3">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <i class="bi bi-arrow-return-right text-secondary"></i>
                                            <span class="fw-bold text-dark small">Reply from <?= htmlspecialchars($seller['full_name']) ?></span>
                                        </div>
                                        <p class="text-secondary mb-0 small" style="line-height: 1.4;"><?= nl2br(htmlspecialchars($rev['reply_text'])) ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $seller['user_id']): ?>
    <div class="modal fade" id="reportUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 overflow-hidden shadow-lg" style="border-radius:14px;">
                <div class="modal-header px-4 bg-light" style="border-bottom: 1px solid #e2e8f0;">
                    <h5 class="modal-title fw-bold text-dark text-danger"><i class="bi bi-flag-fill me-2"></i> Report User Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="seller_profile.php?id=<?= $seller['user_id'] ?>" method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="report_user">
                        <input type="hidden" name="reported_user_id" value="<?= $seller['user_id'] ?>">
                        
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
                                <option value="Fake Account / Impersonation">Fake Account / Impersonation</option>
                                <option value="History of Fraudulent Trades">History of Fraudulent Trades</option>
                                <option value="Abusive / Harassing Behavior">Abusive / Harassing Behavior</option>
                                <option value="Scammer / Phishing Attempts">Scammer / Phishing Attempts</option>
                                <option value="Other Policy Violation">Other Policy Violation</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer px-4 py-3 bg-light border-top">
                        <button type="button" class="btn btn-link text-secondary text-decoration-none fw-semibold px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger fw-bold px-4 rounded-3">Submit User Report</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>