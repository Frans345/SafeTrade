<?php
//Get user session data
$isLoggedIn = isset($_SESSION['user_id']);
$userName = $isLoggedIn ? $_SESSION['full_name'] : 'Guest';
$isVerified = $isLoggedIn ? ($_SESSION['is_verified'] ?? 0) : 0;
$userId = $isLoggedIn ? $_SESSION['user_id'] : null;

$currentPage = basename($_SERVER['PHP_SELF']);

$unreadMsgs = 0; $unreadComments = 0; $unreadBids = 0; $pendingDeliveries = 0;

if ($isLoggedIn && isset($pdo)) {
    //Count unread messages
    $msgStmt = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0');
    $msgStmt->execute([$userId]);
    $unreadMsgs = $msgStmt->fetchColumn();

    //Count unread comments 
    $comStmt = $pdo->prepare('SELECT COUNT(*) FROM comments c JOIN listings l ON c.listing_id = l.listing_id WHERE l.user_id = ? AND c.user_id != ? AND c.is_read = 0');
    $comStmt->execute([$userId, $userId]);
    $unreadComments = $comStmt->fetchColumn();

    //Count unread bids
    $bidNotifStmt = $pdo->prepare('SELECT COUNT(*) FROM bids b JOIN listings l ON b.listing_id = l.listing_id WHERE l.user_id = ? AND b.is_read = 0');
    $bidNotifStmt->execute([$userId]);
    $unreadBids = $bidNotifStmt->fetchColumn();

    //Count pending deliveries
    $pendingDelStmt = $pdo->prepare("SELECT COUNT(*) FROM bids WHERE user_id = ? AND status = 'Accepted' AND delivery_confirmed = 0");
    $pendingDelStmt->execute([$userId]);
    $pendingDeliveries = $pendingDelStmt->fetchColumn();
}

//Calculate total notifications for badge
$totalNotifications = $unreadMsgs + $unreadComments + $unreadBids + $pendingDeliveries;

//Keep search inputs active 
$currentSearchType = $_GET['search_type'] ?? 'items';
$currentSearchQuery = $_GET['search'] ?? '';
?>

<style>
    /*Smooth hover*/
    .navbar-brand, .nav-link, .btn {
        transition: all 0.2s ease-in-out;
    }
    
    /*Search bar glow*/
    .custom-search-group {
        border: 1px solid #e2e8f0;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .custom-search-group:focus-within {
        border-color: #10b981;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15) !important;
    }
    
    /*Dropdown animation*/
    .nav-dropdown-menu {
        transform: translateY(10px);
        opacity: 0;
        visibility: hidden;
        display: block;
        transition: all 0.2s ease;
    }
    .dropdown:hover .nav-dropdown-menu,
    .dropdown .nav-dropdown-menu.show {
        transform: translateY(0);
        opacity: 1;
        visibility: visible;
    }
    
    /*Guest notice styling*/
    .guest-mode-banner {
        background: linear-gradient(90deg, #f8fafc 0%, #f1f5f9 100%);
        border-bottom: 1px solid #e2e8f0;
        font-size: 0.88rem;
        color: #475569;
    }

    /*Mobile responsiveness*/
    @media (max-width: 991.98px) {
        .responsive-search-form {
            width: 100% !important;
            margin: 1rem 0 !important;
        }
        .navbar-nav {
            padding-top: 0.5rem;
            width: 100%;
        }
        .nav-item-action {
            width: 100%;
            margin-bottom: 0.5rem;
            text-align: center;
        }
        .nav-item-action .btn {
            width: 100%;
        }
    }
</style>

<?php if (!$isLoggedIn): ?>
    <div class="guest-mode-banner py-2 text-center fw-medium shadow-sm position-relative animate__animated animate__fadeIn">
        <div class="container px-3">
            <i class="bi bi-eye-fill text-secondary me-1.5"></i> 
            <span>You are currently exploring SafeTrade in <strong>Guest Mode</strong>. <a href="login.php" class="text-success fw-bold text-decoration-none hover-underline ms-1">Log in</a> or <a href="register.php" class="text-success fw-bold text-decoration-none hover-underline">Sign up</a> to unlock custom offers, private message logs, and platform reports.</span>
        </div>
    </div>
<?php endif; ?>

<nav class="navbar navbar-expand-lg navbar-light py-3 bg-white border-bottom shadow-sm">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="index.php" style="gap: 10px;">
            <div style="background-color: #d1fae5; padding: 6px 12px; border-radius: 8px; font-weight: 800; letter-spacing: -1px; font-size: 1.5rem; line-height: 1.1;">
                <span style="color: #105c0e;">S</span><span style="color: #10b981;">T</span>
            </div>
            <div style="font-size: 1.35rem; font-weight: 700; color: #105c0e; letter-spacing: -0.5px;">
                Safe<span style="color: #10b981;">Trade</span>
            </div>
        </a>

        <button class="navbar-toggler border-0 p-2" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            
            <?php if ($currentPage === 'index.php'): ?>
            <form action="index.php" method="GET" class="responsive-search-form d-flex mx-auto w-50 my-2 my-lg-0">
                <div class="input-group custom-search-group rounded-3 overflow-hidden bg-light">
                    <select name="search_type" class="form-select border-0 bg-white fw-semibold text-secondary" style="max-width: 110px; font-size: 0.9rem;">
                        <option value="items" <?= $currentSearchType === 'items' ? 'selected' : '' ?>>🛍️ Items</option>
                        <option value="users" <?= $currentSearchType === 'users' ? 'selected' : '' ?>>👤 Users</option>
                    </select>
                    
                    <input class="form-control border-0 bg-white" type="search" name="search" placeholder="Search..." value="<?= htmlspecialchars($currentSearchQuery) ?>" style="font-size: 0.95rem;">
                    <button class="btn btn-success fw-bold px-4" type="submit" style="background-color: #10b981; border: none; font-size: 0.95rem;">Search</button>
                </div>
            </form>
            <?php else: ?>
            <div class="mx-auto"></div>
            <?php endif; ?>

            <ul class="navbar-nav ms-auto align-items-center gap-2 gap-lg-1">
                <?php if ($isLoggedIn): ?>
                    
                    <?php if ($currentPage !== 'index.php'): ?>
                        <li class="nav-item nav-item-action"><a class="btn btn-link text-decoration-none text-secondary fw-semibold px-3" href="index.php">TradePlace</a></li>
                    <?php endif; ?>

                    <?php if ($currentPage !== 'create_listing.php'): ?>
                        <li class="nav-item nav-item-action">
                            <a class="btn btn-success fw-bold px-4 shadow-sm" style="background-color: #10b981; border: none; border-radius: 8px; white-space: nowrap;" href="create_listing.php">+ Start a Trade</a>
                        </li>
                    <?php endif; ?>

                    <li class="nav-item dropdown w-100 text-center text-lg-start">
                        <a class="nav-link dropdown-toggle text-dark fw-bold position-relative px-3 d-inline-flex align-items-center" href="#" data-bs-toggle="dropdown">
                            <span class="me-1" style="color: #334155;"><?= htmlspecialchars($userName) ?></span>
                            <?php if ($isVerified): ?>
                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="#10b981" class="bi bi-patch-check-fill alignment-fix" viewBox="0 0 16 16"><path d="M10.067.87a2.89 2.89 0 0 0-4.134 0l-.622.638-.89-.011a2.89 2.89 0 0 0-2.924 2.924l.01.89-.636.622a2.89 2.89 0 0 0 0 4.134l.637.622-.011.89a2.89 2.89 0 0 0 2.924 2.924l.89-.01.622.636a2.89 2.89 0 0 0 4.134 0l.622-.637.89.011a2.89 2.89 0 0 0 2.924-2.924l-.01-.89.636-.622a2.89 2.89 0 0 0 0-4.134l-.637-.622.011-.89a2.89 2.89 0 0 0-2.924-2.924l-.89.01-.622-.636zm.287 5.984-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7 8.793l2.646-2.647a.5.5 0 0 1 .708.708z"/></svg>
                            <?php endif; ?>
                            <?php if ($totalNotifications > 0): ?>
                                <span class="position-absolute top-2 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem; padding: 4px 6px;">
                                    <?= $totalNotifications ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        
                        <ul class="dropdown-menu dropdown-menu-end nav-dropdown-menu shadow border-0 p-2 mt-2 rounded-3" style="min-width: 200px;">
                            <?php if ($currentPage !== 'profile.php'): ?>
                                <li><a class="dropdown-item py-2 fw-medium d-flex justify-content-between align-items-center rounded" href="profile.php">My Profile <?php if ($pendingDeliveries > 0): ?><span class="badge bg-danger rounded-pill">!</span><?php endif; ?></a></li>
                            <?php endif; ?>

                            <?php if ($currentPage !== 'my_listings.php'): ?>
                                <li><a class="dropdown-item py-2 fw-medium d-flex justify-content-between align-items-center rounded" href="my_listings.php">My Listings <?php if (($unreadComments + $unreadBids) > 0): ?><span class="badge bg-danger rounded-pill"><?= $unreadComments + $unreadBids ?></span><?php endif; ?></a></li>
                            <?php endif; ?>

                            <?php if ($currentPage !== 'messages.php'): ?>
                                <li><a class="dropdown-item py-2 fw-medium d-flex justify-content-between align-items-center rounded" href="messages.php">My Messages <?php if ($unreadMsgs > 0): ?><span class="badge bg-danger rounded-pill"><?= $unreadMsgs ?></span><?php endif; ?></a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider my-2 bg-light"></li>
                            <li><a class="dropdown-item py-2 text-danger fw-bold rounded" href="logout.php">Logout</a></li>
                        </ul>
                    </li>

                <?php else: ?>
                    <?php if ($currentPage !== 'index.php'): ?>
                        <li class="nav-item nav-item-action"><a class="btn btn-link text-decoration-none text-secondary fw-semibold px-3" href="index.php">TradePlace</a></li>
                    <?php endif; ?>
                    <li class="nav-item nav-item-action"><a class="nav-link text-dark fw-semibold px-3" href="login.php">Login</a></li>
                    <li class="nav-item nav-item-action"><a class="btn btn-success fw-bold px-4 shadow-sm" style="background-color: #10b981; border: none; border-radius: 8px; white-space: nowrap;" href="register.php">Sign Up</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>