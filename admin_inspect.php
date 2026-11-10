<?php
session_start();
require_once 'includes/db.php';

//1. Closed door: Must be logged in and have is_admin = 1
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$stmt = $pdo->prepare('SELECT is_admin FROM users WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
if (!$stmt->fetchColumn()) { header('Location: index.php'); exit; }

//2. Get target user ID
$targetUserId = $_GET['id'] ?? null;
if (!$targetUserId) { header('Location: admin_dashboard.php'); exit; }

$successMsg = '';
$errorMsg = '';

//3. handle admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    //ssoft delete listing
    if ($action === 'delete_listing') {
        $listingId = $_POST['listing_id'] ?? null;
        if ($listingId) {
            $pdo->prepare("UPDATE listings SET is_deleted = 1 WHERE listing_id = ?")->execute([$listingId]);
            $successMsg = "Listing moved to Recycle Bin.";
        }
    } 
    //hard delete comments
    elseif ($action === 'delete_comment') {
        $commentId = $_POST['comment_id'] ?? null;
        if ($commentId) {
            $pdo->prepare("DELETE FROM comments WHERE comment_id = ?")->execute([$commentId]);
            $successMsg = "Comment removed from the platform.";
        }
    } 
    //Soft delete user
    elseif ($action === 'ban_user') {
        $checkAdmin = $pdo->prepare("SELECT email FROM users WHERE user_id = ?");
        $checkAdmin->execute([$targetUserId]);
        if ($checkAdmin->fetchColumn() === 'admin') {
            $errorMsg = "You cannot ban the master system admin.";
        } else {
            $pdo->prepare("UPDATE users SET is_deleted = 1 WHERE user_id = ?")->execute([$targetUserId]);
            header('Location: admin_dashboard.php?msg=user_banned');
            exit;
        }
    }
    //toggle verification badge
    elseif ($action === 'toggle_verify') {
        $pdo->prepare("UPDATE users SET is_verified = NOT is_verified WHERE user_id = ?")->execute([$targetUserId]);
        $successMsg = "User verification status updated.";
    }
    //dismiss report
    elseif ($action === 'dismiss_report') {
        $reportId = $_POST['report_id'] ?? null;
        if ($reportId) {
            $pdo->prepare("UPDATE reports SET status = 'Dismissed' WHERE report_id = ?")->execute([$reportId]);
            $successMsg = "Moderation report successfully dismissed.";
        }
    }
}

//4. fetch user sheet
$userStmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$userStmt->execute([$targetUserId]);
$targetUser = $userStmt->fetch();

if (!$targetUser) { header('Location: admin_dashboard.php'); exit; }

//5. fetch listing 
$listingsStmt = $pdo->prepare("SELECT * FROM listings WHERE user_id = ? AND is_deleted = 0 ORDER BY created_at DESC");
$listingsStmt->execute([$targetUserId]);
$userListings = $listingsStmt->fetchAll();

//6. fetch comment history
$commentsStmt = $pdo->prepare("
    SELECT c.*, l.title as listing_title 
    FROM comments c 
    JOIN listings l ON c.listing_id = l.listing_id 
    WHERE c.user_id = ? 
    ORDER BY c.created_at DESC
");
$commentsStmt->execute([$targetUserId]);
$userComments = $commentsStmt->fetchAll();

//7. wiretap
$userMessages = [];
$wiretapError = false;
try {
    $msgQuery = $pdo->prepare("
        SELECT m.*, sender.full_name as sender_name, receiver.full_name as receiver_name 
        FROM messages m 
        JOIN users sender ON m.sender_id = sender.user_id 
        JOIN users receiver ON m.receiver_id = receiver.user_id 
        WHERE m.sender_id = ? OR m.receiver_id = ? 
        ORDER BY m.created_at DESC LIMIT 50
    ");
    $msgQuery->execute([$targetUserId, $targetUserId]);
    $userMessages = $msgQuery->fetchAll();
} catch (PDOException $e) {
    $wiretapError = true; 
}

//8. reports
$reportsStmt = $pdo->prepare("
    SELECT r.*, reporter.full_name as reporter_name 
    FROM reports r 
    LEFT JOIN users reporter ON r.reporter_id = reporter.user_id 
    WHERE r.reported_user_id = ? 
    ORDER BY r.created_at DESC
");
$reportsStmt->execute([$targetUserId]);
$userReports = $reportsStmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inspect User - SafeTrade Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .admin-header { background-color: #0f172a; color: white; padding: 15px 0; border-bottom: 5px solid #3b82f6; }
        .nav-pills .nav-link { color: #64748b; font-weight: 600; border-radius: 8px; }
        .nav-pills .nav-link.active { background-color: #3b82f6; color: white; }
        .content-card { background-color: white; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); padding: 20px; }
        .avatar-lg { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; background-color: #e2e8f0; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; color: #475569; font-size: 2.5rem; border: 3px solid #cbd5e1; }
        .danger-zone { border: 2px dashed #ef4444; background-color: #fef2f2; border-radius: 12px; padding: 20px; }
        .trust-zone { border: 2px dashed #10b981; background-color: #f0fdf4; border-radius: 12px; padding: 20px; }
    </style>
</head>
<body>

    <div class="admin-header mb-4">
        <div class="container d-flex align-items-center justify-content-between">
            <div>
                <span class="text-uppercase text-muted fw-bold small" style="letter-spacing: 1px;">Moderation Tools</span>
                <h4 class="fw-bold mb-0">Inspect User</h4>
            </div>
            <a href="admin_dashboard.php" class="btn btn-outline-light fw-bold btn-sm">← Back to Dashboard</a>
        </div>
    </div>

    <div class="container mb-5">
        
        <?php if ($successMsg): ?>
            <div class="alert alert-success fw-bold shadow-sm border-0">✓ <?= htmlspecialchars($successMsg) ?></div>
        <?php endif; ?>
        <?php if ($errorMsg): ?>
            <div class="alert alert-danger fw-bold shadow-sm border-0">⚠ <?= htmlspecialchars($errorMsg) ?></div>
        <?php endif; ?>

        <?php if ($targetUser['is_deleted'] == 1): ?>
            <div class="alert alert-danger fw-bold shadow-sm border-0 text-center mb-4">
                ⚠ THIS USER IS CURRENTLY BANNED (IN THE RECYCLE BIN)
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="content-card text-center mb-4 border-top border-4 <?= $targetUser['rating'] <= 2.0 && $targetUser['review_count'] > 0 ? 'border-danger' : 'border-primary' ?>">
                    
                    <?php if ($targetUser['profile_image']): ?>
                        <img src="<?= htmlspecialchars($targetUser['profile_image']) ?>" class="avatar-lg mb-3" style="<?= $targetUser['is_deleted'] ? 'filter: grayscale(100%); opacity: 0.6;' : '' ?>">
                    <?php else: ?>
                        <div class="avatar-lg mb-3" style="<?= $targetUser['is_deleted'] ? 'filter: grayscale(100%); opacity: 0.6;' : '' ?>"><?= strtoupper(substr($targetUser['full_name'], 0, 1)) ?></div>
                    <?php endif; ?>
                    
                    <h4 class="fw-bold text-dark mb-0 <?= $targetUser['is_deleted'] ? 'text-decoration-line-through text-muted' : '' ?>">
                        <?= htmlspecialchars($targetUser['full_name']) ?>
                        <?php if ($targetUser['is_verified'] && !$targetUser['is_deleted']): ?><span class="text-success" style="font-size: 1.2rem;">✓</span><?php endif; ?>
                    </h4>
                    <p class="text-muted mb-3"><?= htmlspecialchars($targetUser['email']) ?></p>

                    <div class="d-flex justify-content-around bg-light p-3 rounded mb-3">
                        <div>
                            <h5 class="fw-bold mb-0 <?= $targetUser['rating'] <= 2.0 && $targetUser['review_count'] > 0 ? 'text-danger' : 'text-warning' ?>">
                                <?= $targetUser['review_count'] > 0 ? number_format($targetUser['rating'], 1) . ' ★' : 'N/A' ?>
                            </h5>
                            <small class="text-muted text-uppercase" style="font-size: 0.7rem;">Rating</small>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0 text-dark"><?= count($userListings) ?></h5>
                            <small class="text-muted text-uppercase" style="font-size: 0.7rem;">Listings</small>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0 text-dark"><?= count($userComments) ?></h5>
                            <small class="text-muted text-uppercase" style="font-size: 0.7rem;">Comments</small>
                        </div>
                    </div>
                    
                    <div class="text-start small text-muted">
                        <strong>Joined:</strong> <?= date('F j, Y', strtotime($targetUser['created_at'])) ?><br>
                        <strong>Location:</strong> <?= htmlspecialchars($targetUser['town'] ?? 'N/A') ?><br>
                        <strong>Gender:</strong> <?= ucfirst(htmlspecialchars($targetUser['gender'] ?? 'N/A')) ?>
                    </div>
                </div>

                <div class="trust-zone text-center shadow-sm mb-4">
                    <h5 class="fw-bold text-success mb-2">Trust & Safety</h5>
                    <p class="small text-muted mb-3">Manually override this user's verification status on the platform.</p>
                    <form action="admin_inspect.php?id=<?= $targetUserId ?>" method="POST" class="m-0">
                        <input type="hidden" name="action" value="toggle_verify">
                        <button type="submit" class="btn <?= $targetUser['is_verified'] ? 'btn-outline-danger' : 'btn-success' ?> fw-bold w-100 shadow-sm">
                            <?= $targetUser['is_verified'] ? 'Revoke Verification Badge' : 'Grant Verification Badge ✓' ?>
                        </button>
                    </form>
                </div>

                <div class="danger-zone text-center shadow-sm">
                    <h5 class="fw-bold text-danger mb-2">Danger Zone</h5>
                    <p class="small text-muted mb-3">Ban this user and move their account to the Recycle Bin. You can restore them later from the dashboard.</p>
                    <form action="admin_inspect.php?id=<?= $targetUserId ?>" method="POST" onsubmit="return confirm('Move this user to the Recycle Bin?');">
                        <input type="hidden" name="action" value="ban_user">
                        <button type="submit" class="btn btn-danger fw-bold w-100 shadow-sm" <?= ($targetUser['email'] === 'admin' || $targetUser['is_deleted']) ? 'disabled' : '' ?>>Ban User</button>
                    </form>
                </div>
            </div>

            <div class="col-lg-8">
                
                <ul class="nav nav-pills mb-3 gap-2 bg-white p-2 rounded shadow-sm d-inline-flex" id="inspectTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active px-4" data-bs-toggle="pill" data-bs-target="#listings" type="button">Listing Inventory</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link px-4" data-bs-toggle="pill" data-bs-target="#comments" type="button">Public Comments</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link px-4" data-bs-toggle="pill" data-bs-target="#wiretap" type="button">Wiretap (Messages)</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link px-4 <?= !empty($userReports) ? 'text-warning' : '' ?>" data-bs-toggle="pill" data-bs-target="#reports" type="button">
                            Reports 
                            <?php $pendingCount = count(array_filter($userReports, fn($r) => $r['status'] === 'Pending')); ?>
                            <?php if($pendingCount > 0): ?>
                                <span class="badge bg-danger ms-1"><?= $pendingCount ?></span>
                            <?php endif; ?>
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="inspectTabsContent">
                    
                    <div class="tab-pane fade show active" id="listings" role="tabpanel">
                        <div class="content-card p-0 overflow-hidden">
                            <?php if (empty($userListings)): ?>
                                <div class="p-5 text-center text-muted">This user has no active listings.</div>
                            <?php else: ?>
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Item</th>
                                            <th>Price</th>
                                            <th>Status</th>
                                            <th class="text-end">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($userListings as $l): ?>
                                            <tr>
                                                <td class="fw-bold">
                                                    <a href="view_listing.php?id=<?= $l['listing_id'] ?>" target="_blank" class="text-decoration-none text-dark d-flex align-items-center">
                                                        <img src="<?= htmlspecialchars($l['image_path']) ?>" style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px; margin-right: 10px;">
                                                        <?= htmlspecialchars($l['title']) ?>
                                                    </a>
                                                </td>
                                                <td class="text-success fw-bold">R <?= number_format($l['price'], 2) ?></td>
                                                <td><span class="badge <?= $l['status'] === 'Active' ? 'bg-success' : 'bg-danger' ?>"><?= $l['status'] ?></span></td>
                                                <td class="text-end">
                                                    <form action="admin_inspect.php?id=<?= $targetUserId ?>" method="POST" class="m-0" onsubmit="return confirm('Move this listing to the Recycle Bin?');">
                                                        <input type="hidden" name="action" value="delete_listing">
                                                        <input type="hidden" name="listing_id" value="<?= $l['listing_id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger fw-bold">Remove</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="comments" role="tabpanel">
                        <div class="content-card">
                            <?php if (empty($userComments)): ?>
                                <div class="text-center text-muted py-4">This user has not posted any public questions or comments.</div>
                            <?php else: ?>
                                <div class="d-flex flex-column gap-3">
                                    <?php foreach ($userComments as $c): ?>
                                        <div class="p-3 border rounded bg-light position-relative">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <small class="text-muted fw-bold">On: <a href="view_listing.php?id=<?= $c['listing_id'] ?>" target="_blank" class="text-primary text-decoration-none"><?= htmlspecialchars($c['listing_title']) ?></a></small>
                                                <small class="text-muted"><?= date('M d, Y g:i A', strtotime($c['created_at'])) ?></small>
                                            </div>
                                            <p class="mb-3 text-dark">"<?= htmlspecialchars($c['comment_text']) ?>"</p>
                                            <form action="admin_inspect.php?id=<?= $targetUserId ?>" method="POST" class="text-end m-0" onsubmit="return confirm('Permanently delete this comment?');">
                                                <input type="hidden" name="action" value="delete_comment">
                                                <input type="hidden" name="comment_id" value="<?= $c['comment_id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger fw-bold">Delete Comment</button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="wiretap" role="tabpanel">
                        <div class="content-card bg-dark text-white">
                            <h5 class="fw-bold mb-3 border-bottom border-secondary pb-2">Communications Wiretap</h5>
                            
                            <?php if ($wiretapError): ?>
                                <div class="alert alert-warning text-dark fw-bold border-0 shadow-sm text-center py-4">
                                    <h4 class="mb-2">🚧 Messaging System Offline</h4>
                                    <p class="mb-0 fw-normal">The private messaging database table cannot be found.</p>
                                </div>
                            <?php elseif (empty($userMessages)): ?>
                                <div class="text-center text-muted py-4">No private messages found for this user.</div>
                            <?php else: ?>
                                <div class="d-flex flex-column gap-2" style="max-height: 500px; overflow-y: auto;">
                                    <?php foreach ($userMessages as $msg): ?>
                                        <?php $isSentByTarget = $msg['sender_id'] == $targetUserId; ?>
                                        <div class="p-2 border border-secondary rounded" style="background-color: <?= $isSentByTarget ? '#1e293b' : '#334155' ?>;">
                                            <div class="d-flex justify-content-between mb-1">
                                                <small class="fw-bold text-info">
                                                    <?= htmlspecialchars($msg['sender_name']) ?> → <?= htmlspecialchars($msg['receiver_name']) ?>
                                                </small>
                                                <small class="text-secondary"><?= date('m/d H:i', strtotime($msg['created_at'])) ?></small>
                                            </div>
                                            <p class="mb-0 small" style="color: #e2e8f0;">"<?= htmlspecialchars($msg['message']) ?>"</p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="reports" role="tabpanel">
                        <div class="content-card">
                            <?php if (empty($userReports)): ?>
                                <div class="text-center text-muted py-4">No moderation reports have been filed against this user.</div>
                            <?php else: ?>
                                <div class="d-flex flex-column gap-3">
                                    <?php foreach ($userReports as $rep): ?>
                                        <div class="p-3 border <?= $rep['status'] === 'Pending' ? 'border-warning' : 'border-secondary' ?> rounded bg-light">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <small class="fw-bold text-dark">Reported by: <?= htmlspecialchars($rep['reporter_name'] ?? 'Unknown User') ?></small>
                                                <span class="badge <?= $rep['status'] === 'Pending' ? 'bg-warning text-dark' : 'bg-secondary' ?>"><?= $rep['status'] ?></span>
                                            </div>
                                            <p class="mb-3 text-danger fw-bold">"<?= htmlspecialchars($rep['reason']) ?>"</p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted"><?= date('M d, Y', strtotime($rep['created_at'])) ?></small>
                                                <?php if ($rep['status'] === 'Pending'): ?>
                                                <form action="admin_inspect.php?id=<?= $targetUserId ?>" method="POST" class="m-0">
                                                    <input type="hidden" name="action" value="dismiss_report">
                                                    <input type="hidden" name="report_id" value="<?= $rep['report_id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary fw-bold">Dismiss Report</button>
                                                </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>