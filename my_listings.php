<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$successMsg = '';

// Handle Bid Actions (Accept or Decline)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'accept_bid' || $_POST['action'] === 'decline_bid') {
        $targetBidId = $_POST['bid_id'] ?? null;
        $targetListingId = $_POST['listing_id'] ?? null;
        $newStatus = ($_POST['action'] === 'accept_bid') ? 'Accepted' : 'Declined';
        
        if ($targetBidId && $targetListingId) {
            // Update the bid status
            $updateBid = $pdo->prepare('UPDATE bids SET status = ? WHERE bid_id = ?');
            if ($updateBid->execute([$newStatus, $targetBidId])) {
                $successMsg = "Bid has been successfully " . strtolower($newStatus) . "!";
                
                // If the bid is accepted, you might want to automatically mark the listing as Sold
                if ($newStatus === 'Accepted') {
                    $updateListing = $pdo->prepare("UPDATE listings SET status = 'Sold' WHERE listing_id = ?");
                    $updateListing->execute([$targetListingId]);
                }
            }
        }
    }
}

// 1. fetch all listings for this user
$stmt = $pdo->prepare('SELECT * FROM listings WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$userId]);
$myListings = $stmt->fetchAll();

// 2. fetch all bids for these listings
$bidsStmt = $pdo->prepare('
    SELECT b.*, u.full_name 
    FROM bids b
    JOIN listings l ON b.listing_id = l.listing_id
    JOIN users u ON b.user_id = u.user_id
    WHERE l.user_id = ? 
    ORDER BY b.created_at DESC
');
$bidsStmt->execute([$userId]);
$allBids = $bidsStmt->fetchAll();

// Group bids by listing ID for easy display
$groupedBids = [];
foreach ($allBids as $bid) {
    $groupedBids[$bid['listing_id']][] = $bid;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Listings - TradePlace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .history-card { border: 1px solid #e2e8f0; border-radius: 12px; background-color: white; overflow: hidden; margin-bottom: 20px; transition: border-color 0.2s; }
        .history-header { background-color: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 15px 20px; display: flex; align-items: center; justify-content: space-between; }
        .listing-thumb { width: 60px; height: 60px; object-fit: cover; border-radius: 8px; border: 1px solid #cbd5e1; margin-right: 15px; }
        .bids-section { background-color: #ffffff; padding: 20px; }
        
        /* Table styling for bids */
        .table td { vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
        .table tr:last-child td { border-bottom: none; }
        
        /* sold styling */
        .sold-card { border: 2px solid #ef4444 !important; opacity: 0.9; }
        /* remove bottom border if bids are hidden */
        .sold-header { background-color: #fef2f2 !important; border-bottom: none !important; }
    </style>
</head>
<body>

    <?php include 'includes/navbar.php'; ?>

    <div class="container mb-5 mt-4">
        
        <?php if ($successMsg): ?>
            <div class="alert alert-success fw-bold shadow-sm" style="background-color: #d1fae5; color: #047857; border: none;">✓ <?= htmlspecialchars($successMsg) ?></div>
        <?php endif; ?>

        <h3 class="fw-bold mb-4" style="color: #0f172a;">My Trade History</h3>

        <?php if (empty($myListings)): ?>
            <div class="alert alert-light text-center border p-5">
                <h5 class="text-muted">You haven't posted any trades yet!</h5>
            </div>
        <?php else: ?>
            <?php foreach ($myListings as $listing): ?>
                <?php 
                    $listingBids = $groupedBids[$listing['listing_id']] ?? []; 
                    $isSold = ($listing['status'] === 'Sold');
                ?>
                
                <div class="history-card shadow-sm <?= $isSold ? 'sold-card' : '' ?>">
                    <div class="history-header <?= $isSold ? 'sold-header' : '' ?>">
                        <div class="d-flex align-items-center">
                            <img src="<?= htmlspecialchars($listing['image_path']) ?>" class="listing-thumb shadow-sm">
                            <div>
                                <h6 class="mb-1 fw-bold text-dark d-flex align-items-center gap-2">
                                    <?= htmlspecialchars($listing['title']) ?>
                                    <?php if ($isSold): ?>
                                        <span class="badge bg-danger shadow-sm">SOLD</span>
                                    <?php endif; ?>
                                </h6>
                                <span class="badge bg-light border text-dark">R <?= number_format($listing['price'], 2) ?></span>
                                <small class="text-muted ms-2">Listed on <?= date('M d, Y', strtotime($listing['created_at'])) ?></small>
                            </div>
                        </div>
                        <a href="view_listing.php?id=<?= $listing['listing_id'] ?>" class="btn btn-sm <?= $isSold ? 'btn-outline-danger bg-white fw-bold' : 'btn-success fw-bold' ?>" <?= !$isSold ? 'style="background-color: #10b981; border: none;"' : '' ?>>View Page</a>
                    </div>

                    <?php if (!$isSold): ?>
                    <div class="bids-section">
                        <h6 class="fw-bold text-muted mb-3" style="font-size: 0.9rem;">Active Bids (<?= count($listingBids) ?>)</h6>
                        
                        <?php if (empty($listingBids)): ?>
                            <p class="text-muted small fst-italic m-0">No bids on this trade yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <tbody>
                                        <?php foreach ($listingBids as $bid): ?>
                                            <tr>
                                                <td style="width: 30%;">
                                                    <a href="seller_profile.php?id=<?= $bid['user_id'] ?>" class="fw-bold text-dark text-decoration-none">
                                                        👤 <?= htmlspecialchars($bid['full_name']) ?>
                                                    </a>
                                                </td>
                                                <td style="width: 25%;">
                                                    <span class="fw-bold text-success">R <?= number_format($bid['bid_amount'], 2) ?></span>
                                                </td>
                                                <td style="width: 20%;">
                                                    <?php if ($bid['status'] === 'Pending'): ?>
                                                        <span class="badge bg-warning text-dark shadow-sm">Pending</span>
                                                    <?php elseif ($bid['status'] === 'Accepted'): ?>
                                                        <span class="badge bg-success shadow-sm">Accepted</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary shadow-sm"><?= htmlspecialchars($bid['status']) ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">
                                                    <?php if ($bid['status'] === 'Pending'): ?>
                                                        <form action="my_listings.php" method="POST" class="d-inline">
                                                            <input type="hidden" name="bid_id" value="<?= $bid['bid_id'] ?>">
                                                            <input type="hidden" name="listing_id" value="<?= $listing['listing_id'] ?>">
                                                            <button type="submit" name="action" value="accept_bid" class="btn btn-sm btn-success fw-bold me-1">Accept</button>
                                                            <button type="submit" name="action" value="decline_bid" class="btn btn-sm btn-outline-danger fw-bold">Decline</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>