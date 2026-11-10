<?php
session_start();
require_once 'includes/db.php';

//Closed door
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$stmt = $pdo->prepare('SELECT is_admin FROM users WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
if (!$stmt->fetchColumn()) { header('Location: index.php'); exit; }

$successMsg = ''; $errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    //Soft delete
    if ($action === 'delete_user') {
        $targetId = $_POST['target_user_id'] ?? null;
        if ($targetId && $targetId != $_SESSION['user_id']) {
            $pdo->prepare("UPDATE users SET is_deleted = 1 WHERE user_id = ?")->execute([$targetId]);
            $successMsg = "User moved to Recycle Bin.";
        }
    } elseif ($action === 'delete_listing') {
        $targetListingId = $_POST['target_listing_id'] ?? null;
        if ($targetListingId) {
            $pdo->prepare("UPDATE listings SET is_deleted = 1 WHERE listing_id = ?")->execute([$targetListingId]);
            $successMsg = "Listing moved to Recycle Bin.";
        }
    } 
    //Restore soft deletes
    elseif ($action === 'restore_user') {
        $targetId = $_POST['target_user_id'] ?? null;
        if ($targetId) {
            $pdo->prepare("UPDATE users SET is_deleted = 0 WHERE user_id = ?")->execute([$targetId]);
            $successMsg = "User account successfully restored!";
        }
    } elseif ($action === 'restore_listing') {
        $targetListingId = $_POST['target_listing_id'] ?? null;
        if ($targetListingId) {
            $pdo->prepare("UPDATE listings SET is_deleted = 0 WHERE listing_id = ?")->execute([$targetListingId]);
            $successMsg = "Listing successfully restored!";
        }
    }
    //Dismiss report
    elseif ($action === 'dismiss_report') {
        $reportId = $_POST['report_id'] ?? null;
        if ($reportId) {
            $pdo->prepare("UPDATE reports SET status = 'Dismissed' WHERE report_id = ?")->execute([$reportId]);
            $successMsg = "Report dismissed and archived.";
        }
    }
}

//fetch active data
$allUsers = $pdo->query("SELECT * FROM users WHERE is_deleted = 0 ORDER BY created_at DESC")->fetchAll();
$flaggedUsers = $pdo->query("SELECT * FROM users WHERE is_deleted = 0 AND review_count > 0 AND rating <= 2.0 ORDER BY rating ASC")->fetchAll();
$allListings = $pdo->query("SELECT l.*, u.full_name as seller_name FROM listings l JOIN users u ON l.user_id = u.user_id WHERE l.is_deleted = 0 ORDER BY l.created_at DESC")->fetchAll();

//fetch deleted data
$deletedUsers = $pdo->query("SELECT * FROM users WHERE is_deleted = 1 ORDER BY created_at DESC")->fetchAll();
$deletedListings = $pdo->query("SELECT l.*, u.full_name as seller_name FROM listings l JOIN users u ON l.user_id = u.user_id WHERE l.is_deleted = 1 ORDER BY l.created_at DESC")->fetchAll();

$reports = $pdo->query("
    SELECT r.*, reporter.full_name as reporter_name, reported.full_name as reported_name 
    FROM reports r JOIN users reporter ON r.reporter_id = reporter.user_id JOIN users reported ON r.reported_user_id = reported.user_id 
    WHERE r.status = 'Pending' ORDER BY r.created_at ASC
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - SafeTrade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8fafc; }
        .admin-header { background-color: #0f172a; color: white; padding: 15px 0; border-bottom: 4px solid #10b981; }
        
        /*Mobile Tab Scrolling */
        .nav-scroll-wrapper { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: none; }
        .nav-scroll-wrapper::-webkit-scrollbar { display: none; }
        .nav-pills .nav-link { color: #64748b; font-weight: 600; border-radius: 8px; white-space: nowrap; }
        .nav-pills .nav-link.active { background-color: #10b981; color: white; }
        
        /*Table UI*/
        .table-card { background-color: white; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .table-responsive { border-radius: 12px; }
        .table thead th { background-color: #f1f5f9; color: #475569; border-bottom: 2px solid #e2e8f0; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .table tbody td { vertical-align: middle; }
        
        .avatar-sm { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; background-color: #e2e8f0; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; color: #475569; font-size: 0.95rem; margin-right: 12px; flex-shrink: 0; }
        
        /*Responsive overrides*/
        @media (max-width: 575.98px) {
            .admin-header-title { font-size: 1.15rem; }
            .god-mode-badge { font-size: 0.65rem; padding: 0.25em 0.5em; }
            .action-buttons-stack { display: flex; flex-direction: column; gap: 0.5rem; }
            .action-buttons-stack .btn { width: 100%; }
        }
    </style>
</head>
<body>

    <div class="admin-header mb-4 shadow-sm">
        <div class="container d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <div style="background-color: #d1fae5; padding: 6px 12px; border-radius: 8px; font-size: 1.3rem; line-height: 1; font-weight: 900; font-family: Arial;">
                    <span style="color: #105c0e;">S</span><span style="color: #10b981;">T</span>
                </div>
                <div>
                    <h5 class="fw-bold mb-0 admin-header-title">System Admin</h5>
                    <span class="badge bg-danger god-mode-badge"><i class="bi bi-shield-lock-fill"></i> God Mode</span>
                </div>
            </div>
            <a href="logout.php" class="btn btn-outline-light btn-sm fw-bold shadow-sm ms-auto px-3">Log Out</a>
        </div>
    </div>

    <div class="container mb-5">
        
        <?php if ($successMsg): ?>
            <div class="alert alert-success fw-bold shadow-sm border-0 d-flex align-items-center gap-2" style="background-color: #d1fae5; color: #047857;">
                <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($successMsg) ?>
            </div>
        <?php endif; ?>

        <div class="nav-scroll-wrapper mb-4">
            <ul class="nav nav-pills flex-nowrap gap-2 bg-white p-2 rounded-3 shadow-sm d-inline-flex w-100" id="adminTabs" role="tablist">
                <li class="nav-item" role="presentation"><button class="nav-link active px-3 px-md-4" data-bs-toggle="pill" data-bs-target="#directory" type="button"><i class="bi bi-people-fill me-1"></i> Directory</button></li>
                <li class="nav-item" role="presentation"><button class="nav-link px-3 px-md-4 text-danger" data-bs-toggle="pill" data-bs-target="#flagged" type="button"><i class="bi bi-flag-fill me-1"></i> Flagged <span class="badge bg-danger ms-1"><?= count($flaggedUsers) ?></span></button></li>
                <li class="nav-item" role="presentation"><button class="nav-link px-3 px-md-4 text-warning" data-bs-toggle="pill" data-bs-target="#reports" type="button" style="color: #d97706 !important;"><i class="bi bi-exclamation-triangle-fill me-1"></i> Reports <span class="badge bg-warning text-dark ms-1"><?= count($reports) ?></span></button></li>
                <li class="nav-item" role="presentation"><button class="nav-link px-3 px-md-4" data-bs-toggle="pill" data-bs-target="#listings" type="button"><i class="bi bi-box-seam-fill me-1"></i> Listings</button></li>
                <li class="nav-item ms-md-auto" role="presentation"><button class="nav-link px-3 px-md-4 bg-dark text-white" data-bs-toggle="pill" data-bs-target="#recycle" type="button"><i class="bi bi-trash3-fill me-1"></i> Recycle Bin</button></li>
            </ul>
        </div>

        <div class="tab-content" id="adminTabsContent">
            
            <div class="tab-pane fade show active" id="directory" role="tabpanel">
                <div class="mb-3">
                    <div class="input-group shadow-sm rounded-3 overflow-hidden border border-light">
                        <span class="input-group-text bg-white border-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" id="userSearchInput" class="form-control form-control-lg border-0 bg-white" placeholder="Search active users..." onkeyup="filterUsers()" style="font-size: 1rem;">
                    </div>
                </div>
                <div class="table-card border border-light">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 text-nowrap" id="userTable">
                            <thead><tr><th>User Info</th><th>Email</th><th>Rating</th><th>Action</th></tr></thead>
                            <tbody>
                                <?php foreach ($allUsers as $u): ?>
                                    <?php if ($u['email'] === 'admin') continue; ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if ($u['profile_image']): ?>
                                                    <img src="<?= htmlspecialchars($u['profile_image']) ?>" class="avatar-sm">
                                                <?php else: ?>
                                                    <div class="avatar-sm"><?= strtoupper(substr($u['full_name'], 0, 1)) ?></div>
                                                <?php endif; ?>
                                                <span class="fw-bold text-dark userNameSearch"><?= htmlspecialchars($u['full_name']) ?></span>
                                            </div>
                                        </td>
                                        <td class="userEmailSearch text-muted"><?= htmlspecialchars($u['email']) ?></td>
                                        <td><span class="badge <?= $u['rating'] > 2.0 ? 'bg-success' : 'bg-danger' ?> px-2 py-1"><?= $u['review_count'] > 0 ? number_format($u['rating'], 1) . ' ★' : 'N/A' ?></span></td>
                                        <td><a href="admin_inspect.php?id=<?= $u['user_id'] ?>" class="btn btn-sm btn-dark fw-bold px-3">Inspect</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="flagged" role="tabpanel">
                <div class="table-card border border-danger">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 text-nowrap">
                            <thead class="bg-danger bg-opacity-10"><tr><th class="text-danger">Flagged User</th><th class="text-danger">Rating</th><th class="text-danger">Action</th></tr></thead>
                            <tbody>
                                <?php if (empty($flaggedUsers)): ?>
                                    <tr><td colspan="3" class="text-center text-muted py-5"><i class="bi bi-shield-check fs-2 d-block mb-2 text-success opacity-50"></i>No flagged users at this time.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($flaggedUsers as $fu): ?>
                                        <tr class="bg-white">
                                            <td class="fw-bold text-danger d-flex align-items-center gap-2"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($fu['full_name']) ?></td>
                                            <td><h6 class="mb-0 text-danger fw-bold"><?= number_format($fu['rating'], 1) ?> ★</h6></td>
                                            <td>
                                                <div class="d-flex gap-2 action-buttons-stack">
                                                    <a href="admin_inspect.php?id=<?= $fu['user_id'] ?>" class="btn btn-sm btn-dark fw-bold">Investigate</a>
                                                    <form action="admin_dashboard.php" method="POST" onsubmit="return confirm('Move this user to the recycle bin?');" class="m-0">
                                                        <input type="hidden" name="action" value="delete_user">
                                                        <input type="hidden" name="target_user_id" value="<?= $fu['user_id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger fw-bold w-100">Ban User</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="reports" role="tabpanel">
                <div class="row">
                    <?php if (empty($reports)): ?>
                        <div class="col-12"><div class="alert alert-light text-center border py-5 text-muted shadow-sm rounded-3"><i class="bi bi-inbox fs-2 d-block mb-2 opacity-50"></i>No pending reports.</div></div>
                    <?php else: ?>
                        <?php foreach ($reports as $rep): ?>
                            <div class="col-12 mb-3">
                                <div class="card border-warning shadow-sm rounded-3">
                                    <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                                        <div>
                                            <span class="badge bg-warning text-dark mb-2 border border-warning">Pending Investigation</span>
                                            <h6 class="mb-1 text-dark"><strong><?= htmlspecialchars($rep['reporter_name']) ?></strong> reported <strong><?= htmlspecialchars($rep['reported_name']) ?></strong></h6>
                                            <p class="mb-0 text-secondary small bg-light p-2 rounded border mt-2">"<?= htmlspecialchars($rep['reason']) ?>"</p>
                                        </div>
                                        <div class="d-flex flex-wrap gap-2 mt-2 mt-md-0">
                                            <a href="admin_inspect.php?id=<?= $rep['reported_user_id'] ?>" class="btn btn-dark btn-sm fw-bold px-3">Inspect Accused</a>
                                            <form action="admin_dashboard.php" method="POST" class="m-0 flex-grow-1">
                                                <input type="hidden" name="action" value="dismiss_report">
                                                <input type="hidden" name="report_id" value="<?= $rep['report_id'] ?>">
                                                <button type="submit" class="btn btn-outline-secondary btn-sm fw-bold w-100 px-3">Dismiss</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="tab-pane fade" id="listings" role="tabpanel">
                <div class="table-card border border-light">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 text-nowrap">
                            <thead><tr><th>Item Title</th><th>Seller</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                            <tbody>
                                <?php foreach ($allListings as $l): ?>
                                    <tr>
                                        <td class="fw-bold"><a href="view_listing.php?id=<?= $l['listing_id'] ?>" target="_blank" class="text-decoration-none text-dark hover-underline"><?= htmlspecialchars($l['title']) ?></a></td>
                                        <td class="text-muted"><?= htmlspecialchars($l['seller_name']) ?></td>
                                        <td><span class="badge <?= $l['status'] === 'Active' ? 'bg-success' : 'bg-danger' ?> px-2 py-1"><?= $l['status'] ?></span></td>
                                        <td class="text-end">
                                            <form action="admin_dashboard.php" method="POST" onsubmit="return confirm('Move to Recycle Bin?');" class="m-0">
                                                <input type="hidden" name="action" value="delete_listing">
                                                <input type="hidden" name="target_listing_id" value="<?= $l['listing_id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger fw-bold px-3">Remove</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="recycle" role="tabpanel">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <h5 class="fw-bold text-muted mb-3 d-flex align-items-center gap-2"><i class="bi bi-person-x-fill"></i> Banned Users</h5>
                        <div class="table-card border border-secondary">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0 text-nowrap">
                                    <thead class="bg-light"><tr><th class="py-2">User</th><th class="text-end py-2">Action</th></tr></thead>
                                    <tbody>
                                        <?php if (empty($deletedUsers)): ?>
                                            <tr><td colspan="2" class="text-center text-muted py-4 small">No deleted users.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($deletedUsers as $du): ?>
                                                <tr>
                                                    <td class="fw-bold text-decoration-line-through text-muted py-2"><?= htmlspecialchars($du['full_name']) ?></td>
                                                    <td class="text-end py-2">
                                                        <form action="admin_dashboard.php" method="POST" class="m-0">
                                                            <input type="hidden" name="action" value="restore_user">
                                                            <input type="hidden" name="target_user_id" value="<?= $du['user_id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-success fw-bold px-3">Restore</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <h5 class="fw-bold text-muted mb-3 d-flex align-items-center gap-2"><i class="bi bi-archive-fill"></i> Removed Listings</h5>
                        <div class="table-card border border-secondary">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0 text-nowrap">
                                    <thead class="bg-light"><tr><th class="py-2">Listing</th><th class="text-end py-2">Action</th></tr></thead>
                                    <tbody>
                                        <?php if (empty($deletedListings)): ?>
                                            <tr><td colspan="2" class="text-center text-muted py-4 small">No deleted listings.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($deletedListings as $dl): ?>
                                                <tr>
                                                    <td class="fw-bold text-decoration-line-through text-muted py-2"><?= htmlspecialchars($dl['title']) ?></td>
                                                    <td class="text-end py-2">
                                                        <form action="admin_dashboard.php" method="POST" class="m-0">
                                                            <input type="hidden" name="action" value="restore_listing">
                                                            <input type="hidden" name="target_listing_id" value="<?= $dl['listing_id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-success fw-bold px-3">Restore</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        function filterUsers() {
            let input = document.getElementById("userSearchInput").value.toLowerCase();
            let table = document.getElementById("userTable");
            let tr = table.getElementsByTagName("tr");
            for (let i = 1; i < tr.length; i++) {
                let nameTd = tr[i].querySelector(".userNameSearch");
                let emailTd = tr[i].querySelector(".userEmailSearch");
                if (nameTd || emailTd) {
                    let nameText = nameTd.textContent || nameTd.innerText;
                    let emailText = emailTd.textContent || emailTd.innerText;
                    if (nameText.toLowerCase().indexOf(input) > -1 || emailText.toLowerCase().indexOf(input) > -1) {
                        tr[i].style.display = "";
                    } else {
                        tr[i].style.display = "none";
                    }
                }       
            }
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>