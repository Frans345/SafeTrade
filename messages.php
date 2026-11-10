<?php
session_start();
require_once 'includes/db.php';

// Kick if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$currentUserId = $_SESSION['user_id'];
$chatWithId = $_GET['user'] ?? null; 

// ==========================================
// AJAX: SEND MESSAGE SILENTLY
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_send']) && $chatWithId) {
    $messageText = trim($_POST['message_text']);
    if (!empty($messageText)) {
        $sendStmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)');
        $sendStmt->execute([$currentUserId, $chatWithId, $messageText]);
    }
    exit; // Stop PHP here so the page doesn't reload
}

// ==========================================
// AJAX: FETCH NEW MESSAGES SILENTLY
// ==========================================
if (isset($_GET['ajax_fetch']) && $_GET['ajax_fetch'] == '1' && $chatWithId) {
    // Mark as read in the background
    $readStmt = $pdo->prepare('UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?');
    $readStmt->execute([$chatWithId, $currentUserId]);

    // Fetch the latest history
    $historyStmt = $pdo->prepare('SELECT * FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY created_at ASC');
    $historyStmt->execute([$currentUserId, $chatWithId, $chatWithId, $currentUserId]);
    $chatHistory = $historyStmt->fetchAll();

    // Output ONLY the HTML bubbles
    if (empty($chatHistory)) {
        echo '<div class="text-center text-muted small my-auto py-5"><i class="bi bi-chat-dots d-block mb-2 fs-3 opacity-25"></i>Open connection initialized. Write a note to begin.</div>';
    } else {
        foreach ($chatHistory as $msg) {
            $isMine = ($msg['sender_id'] === $currentUserId);
            $bubbleClass = $isMine ? 'msg-sent' : 'msg-received';
            echo '<div class="msg-bubble ' . $bubbleClass . ' shadow-sm">';
            echo nl2br(htmlspecialchars($msg['message']));
            echo '</div>';
        }
    }
    exit; // Stop PHP here
}

// ==========================================
// STANDARD PAGE LOAD LOGIC
// ==========================================

// Mark messages as read on initial load
if ($chatWithId) {
    $readStmt = $pdo->prepare('UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?');
    $readStmt->execute([$chatWithId, $currentUserId]);
}

// Fetch contacts list
$contactsStmt = $pdo->prepare("
    SELECT u.user_id, u.full_name, u.profile_image,
           (SELECT COUNT(*) FROM messages WHERE sender_id = u.user_id AND receiver_id = ? AND is_read = 0) as unread_count
    FROM users u
    WHERE u.user_id IN (
        SELECT sender_id FROM messages WHERE receiver_id = ?
        UNION
        SELECT receiver_id FROM messages WHERE sender_id = ?
    ) AND u.user_id != ?
");
$contactsStmt->execute([$currentUserId, $currentUserId, $currentUserId, $currentUserId]);
$contacts = $contactsStmt->fetchAll();

$chatHistory = [];
$chatPartner = null;

if ($chatWithId) { 
    $partnerStmt = $pdo->prepare('SELECT full_name, profile_image, is_verified FROM users WHERE user_id = ?');
    $partnerStmt->execute([$chatWithId]);
    $chatPartner = $partnerStmt->fetch();

    if ($chatPartner) {
        $historyStmt = $pdo->prepare('
            SELECT * FROM messages 
            WHERE (sender_id = ? AND receiver_id = ?) 
               OR (sender_id = ? AND receiver_id = ?) 
            ORDER BY created_at ASC
        ');
        $historyStmt->execute([$currentUserId, $chatWithId, $chatWithId, $currentUserId]);
        $chatHistory = $historyStmt->fetchAll();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Messages - SafeTrade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8fafc; }
        
        .messenger-box { 
            height: calc(100vh - 140px); 
            min-height: 500px;
            border: 1px solid #e2e8f0; 
            border-radius: 16px; 
            overflow: hidden; 
            display: flex; 
            background-color: white; 
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05); 
        }
        
        .sidebar { 
            width: 340px; 
            border-right: 1px solid #e2e8f0; 
            display: flex; 
            flex-direction: column; 
            background-color: #ffffff; 
            flex-shrink: 0;
        }
        
        .contact-list { overflow-y: auto; flex-grow: 1; }
        
        .contact-item { 
            padding: 16px 20px; 
            border-bottom: 1px solid #f1f5f9; 
            text-decoration: none; 
            color: inherit; 
            display: flex; 
            align-items: center;
            transition: background-color 0.2s;
        }
        .contact-item:hover { background-color: #f8fafc; }
        .contact-item.active { background-color: #f0fdf4; border-left: 4px solid #10b981; }
        
        .avatar { 
            width: 48px; 
            height: 48px; 
            border-radius: 50%; 
            object-fit: cover; 
            background-color: #d1fae5; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-weight: 700; 
            color: #047857; 
            margin-right: 14px; 
            flex-shrink: 0;
        }
        
        .chat-area { flex-grow: 1; display: flex; flex-direction: column; background-color: #ffffff; }
        
        .chat-header { 
            padding: 16px 24px; 
            border-bottom: 1px solid #e2e8f0; 
            display: flex; 
            align-items: center; 
            background-color: #ffffff; 
            gap: 12px;
        }
        
        .chat-messages { 
            flex-grow: 1; 
            padding: 24px; 
            overflow-y: auto; 
            background-color: #f8fafc; 
            display: flex; 
            flex-direction: column; 
        }
        
        #messageBubbles {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .msg-bubble { 
            max-width: 70%; 
            padding: 12px 18px; 
            border-radius: 20px; 
            font-size: 0.95rem; 
            line-height: 1.45;
            word-wrap: break-word;
        }
        
        .msg-sent { background-color: #10b981; color: white; align-self: flex-end; border-bottom-right-radius: 4px; }
        .msg-received { background-color: #e2e8f0; color: #1e293b; align-self: flex-start; border-bottom-left-radius: 4px; }
        
        .chat-input-area { padding: 18px 24px; border-top: 1px solid #e2e8f0; background-color: #ffffff; }
        
        .safety-banner { 
            background-color: #fffbeb; 
            border: 1px solid #fef3c7; 
            border-radius: 12px; 
            padding: 16px; 
            margin-bottom: 16px; 
            font-size: 0.85rem; 
            color: #92400e; 
        }
        .safety-banner h6 { color: #b45309; font-weight: 700; font-size: 0.9rem; margin-bottom: 8px; }
        
        /* Mobile breakpoint refinements */
        @media (max-width: 767.98px) {
            .messenger-box { height: calc(100vh - 120px); border-radius: 0; border: none; }
            
            <?php if ($chatWithId): ?>
                .sidebar { display: none !important; }
                .chat-area { display: flex !important; }
            <?php else: ?>
                .sidebar { width: 100% !important; }
                .chat-area { display: none !important; }
            <?php endif; ?>
            
            .msg-bubble { max-width: 85%; }
            .chat-header { padding: 12px 16px; }
            .chat-messages { padding: 16px; }
            .chat-input-area { padding: 12px 16px; }
        }
    </style>
</head>
<body>

    <?php include 'includes/navbar.php'; ?>

    <div class="container mb-md-5 px-0 px-md-3">
        <div class="messenger-box">
            
            <div class="sidebar">
                <div class="p-3 px-4 border-bottom bg-white d-flex align-items-center justify-content-between">
                    <h5 class="mb-0 fw-bold text-dark">Inbox</h5>
                </div>
                <div class="contact-list" id="contactList">
                    <?php if (empty($contacts)): ?>
                        <div class="p-5 text-center text-muted small">
                            <i class="bi bi-chat-left-dots d-block mb-2 fs-3 opacity-50"></i>
                            No conversations yet.
                        </div>
                    <?php else: ?>
                        <?php foreach ($contacts as $contact): ?>
                            <a href="messages.php?user=<?= $contact['user_id'] ?>" class="contact-item <?= ($chatWithId == $contact['user_id']) ? 'active' : '' ?>">
                                <?php if ($contact['profile_image']): ?>
                                    <img src="<?= htmlspecialchars($contact['profile_image']) ?>" class="avatar" alt="Avatar">
                                <?php else: ?>
                                    <div class="avatar"><?= strtoupper(substr($contact['full_name'], 0, 1)) ?></div>
                                <?php endif; ?>
                                <div class="flex-grow-1 overflow-hidden">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0 fw-bold text-truncate text-dark" style="font-size: 0.95rem;"><?= htmlspecialchars($contact['full_name']) ?></h6>
                                        <?php if ($contact['unread_count'] > 0): ?>
                                            <span class="badge bg-danger rounded-pill px-2 py-1" style="font-size: 0.75rem;"><?= $contact['unread_count'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="chat-area">
                <?php if (!$chatWithId): ?>
                    <div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted bg-light p-4 text-center">
                        <div class="bg-white p-4 rounded-circle shadow-sm mb-3">
                            <i class="bi bi-chat-square-text text-success" style="font-size: 3rem;"></i>
                        </div>
                        <h5 class="fw-bold text-dark">Your Chat Space</h5>
                        <p class="small text-secondary max-width-300">Select an active contact dialogue window to securely process your platform exchanges.</p>
                    </div>
                <?php else: ?>
                    <div class="chat-header shadow-sm z-1">
                        <a href="messages.php" class="btn btn-light btn-sm rounded-circle d-md-none me-2 p-2 px-3 fw-bold border-0">
                            <i class="bi bi-arrow-left"></i>
                        </a>
                        
                        <?php if ($chatPartner['profile_image']): ?>
                            <img src="<?= htmlspecialchars($chatPartner['profile_image']) ?>" class="avatar my-0" style="width: 38px; height: 38px;" alt="Avatar">
                        <?php else: ?>
                            <div class="avatar my-0" style="width: 38px; height: 38px; font-size: 0.9rem;"><?= strtoupper(substr($chatPartner['full_name'], 0, 1)) ?></div>
                        <?php endif; ?>

                        <div class="overflow-hidden">
                            <h6 class="mb-0 fw-bold text-dark text-truncate d-flex align-items-center gap-1">
                                <?= htmlspecialchars($chatPartner['full_name']) ?>
                                <?php if ($chatPartner['is_verified']): ?>
                                    <i class="bi bi-patch-check-fill text-success fs-6"></i>
                                <?php endif; ?>
                            </h6>
                        </div>
                        <a href="seller_profile.php?id=<?= $chatWithId ?>" class="ms-auto btn btn-sm btn-outline-secondary px-3 rounded-pill fw-semibold" style="font-size: 0.85rem;">View Profile</a>
                    </div>

                    <div class="chat-messages" id="chatContainer">
                        
                        <div class="safety-banner shadow-sm position-relative">
                            <h6 class="d-flex align-items-center gap-2"><i class="bi bi-shield-check fs-5"></i> SafeTrade Safe Operations Guideline</h6>
                            <ul class="mb-0 mt-2 ps-3 small d-flex flex-column gap-1">
                                <li><b>Public Meetups:</b> Meet inside busy environments like open commercial malls or adjacent public service sectors.</li>
                                <li><b>Verification Protocols:</b> Verify payment allocations reflect live inside your personal banking terminal directly before handing over items.</li>
                                <li><b>Companionship:</b> Arrange product collections accompanied by friends or peers wherever applicable.</li>
                            </ul>
                        </div>

                        <div id="messageBubbles">
                            <?php if (empty($chatHistory)): ?>
                                <div class="text-center text-muted small my-auto py-5">
                                    <i class="bi bi-chat-dots d-block mb-2 fs-3 opacity-25"></i>
                                    Open connection initialized. Write a note to begin.
                                </div>
                            <?php else: ?>
                                <?php foreach ($chatHistory as $msg): ?>
                                    <?php $isMine = ($msg['sender_id'] === $currentUserId); ?>
                                    <div class="msg-bubble <?= $isMine ? 'msg-sent' : 'msg-received' ?> shadow-sm">
                                        <?= nl2br(htmlspecialchars($msg['message'])) ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="chat-input-area">
                        <?php $prefilledMsg = $_GET['prefill'] ?? ''; ?>
                        <form id="chatForm" class="d-flex gap-2">
                            <input type="text" id="msgInput" name="message_text" class="form-control py-2.5 px-3 rounded-3" placeholder="Type a message..." value="<?= htmlspecialchars($prefilledMsg) ?>" autocomplete="off" autofocus style="font-size: 0.95rem; border-color: #cbd5e1;">
                            <button type="submit" class="btn btn-success fw-bold px-4 rounded-3" style="background-color: #10b981; border: none;">Send</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        const chatContainer = document.getElementById("chatContainer");
        const msgBubbles = document.getElementById("messageBubbles");
        const chatForm = document.getElementById("chatForm");
        const msgInput = document.getElementById("msgInput");

        // Helper function to keep chat scrolled to the bottom
        function scrollToBottom() {
            if (chatContainer) {
                chatContainer.scrollTop = chatContainer.scrollHeight;
            }
        }

        // Scroll to bottom immediately on page load
        scrollToBottom();

        <?php if ($chatWithId): ?>
            // 1. Send Messages via AJAX
            if (chatForm) {
                chatForm.addEventListener('submit', function(e) {
                    e.preventDefault(); // Stop page refresh
                    const text = msgInput.value.trim();
                    if (!text) return;

                    // Prepare the data
                    const formData = new FormData();
                    formData.append('message_text', text);
                    formData.append('ajax_send', '1');

                    // Instantly clear the input box for good UX
                    msgInput.value = '';

                    // Send to server
                    fetch('messages.php?user=<?= $chatWithId ?>', {
                        method: 'POST',
                        body: formData
                    }).then(() => {
                        fetchMessages(true); // Fetch the new messages instantly and force scroll
                    });
                });
            }

            // 2. Fetch Messages via AJAX (Polling)
            function fetchMessages(forceScroll = false) {
                fetch('messages.php?user=<?= $chatWithId ?>&ajax_fetch=1')
                .then(response => response.text())
                .then(html => {
                    // Check if the user is currently scrolled near the bottom
                    const isAtBottom = chatContainer.scrollHeight - chatContainer.clientHeight <= chatContainer.scrollTop + 50;

                    // Update the HTML
                    msgBubbles.innerHTML = html;

                    // If they were at the bottom (or if they just sent a message), pull them down to see the new text
                    if (forceScroll || isAtBottom) {
                        scrollToBottom();
                    }
                });
            }

            // Tell the browser to check for new messages every 2.5 seconds
            setInterval(fetchMessages, 2500);
        <?php endif; ?>
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>