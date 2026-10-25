<?php
// Unified Navbar Component for Pixora
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$nav_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$current_page = basename($_SERVER['PHP_SELF']);

// Load helper functions if not already present
if (!function_exists('getUnreadMessageCount') && file_exists(__DIR__ . '/message_actions.php')) {
    require_once __DIR__ . '/message_actions.php';
}
if (!function_exists('getUnreadNotificationCount') && file_exists(__DIR__ . '/notification_helpers.php')) {
    require_once __DIR__ . '/notification_helpers.php';
}

$nav_unread_msg = 0;
$nav_unread_notif = 0;

if ($nav_user_id && isset($conn)) {
    if (function_exists('getUnreadMessageCount')) {
        $nav_unread_msg = getUnreadMessageCount($nav_user_id, $conn);
    }
    if (function_exists('getUnreadNotificationCount')) {
        $nav_unread_notif = getUnreadNotificationCount($nav_user_id, $conn);
    }
}
?>

<!-- NAVBAR STYLES -->
<style>
    .pixora-navbar {
        background: linear-gradient(90deg, #ffffff 0%, #fafafa 100%);
        padding: 0.9rem 0;
        border-bottom: 2px solid var(--primary-light, #edd5ff);
        position: sticky;
        top: 0;
        z-index: 1000;
        box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        backdrop-filter: blur(10px);
    }

    .pixora-navbar-content {
        max-width: 1400px;
        margin: 0 auto;
        padding: 0 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
    }

    .pixora-logo {
        font-size: 26px;
        font-weight: 800;
        background: linear-gradient(135deg, var(--primary, #7c3aed), var(--secondary, #ec4899));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        text-decoration: none;
        letter-spacing: -0.5px;
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .pixora-nav-links {
        display: flex;
        gap: 24px;
        align-items: center;
        flex-wrap: wrap;
    }

    .pixora-nav-link {
        color: var(--dark, #1f2937);
        text-decoration: none;
        font-weight: 600;
        font-size: 13.5px;
        transition: all 0.2s ease;
        position: relative;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 0;
    }

    .pixora-nav-link:hover,
    .pixora-nav-link.active {
        color: var(--primary, #7c3aed);
    }

    .pixora-nav-link.active::after {
        content: '';
        position: absolute;
        bottom: -6px;
        left: 0;
        width: 100%;
        height: 3px;
        background: linear-gradient(90deg, var(--primary, #7c3aed), var(--secondary, #ec4899));
        border-radius: 2px;
    }

    .nav-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 18px;
        height: 18px;
        padding: 0 5px;
        background: #ef4444;
        color: white;
        font-size: 11px;
        font-weight: 800;
        border-radius: 10px;
        line-height: 1;
        box-shadow: 0 2px 4px rgba(239, 68, 68, 0.4);
    }

    /* FLOATING NOTIFICATION POPUP (MESSAGE TOAST) */
    .msg-popup-toast {
        position: fixed;
        bottom: 25px;
        right: 25px;
        max-width: 380px;
        width: calc(100% - 50px);
        background: white;
        border-radius: 16px;
        padding: 16px 18px;
        box-shadow: 0 12px 35px rgba(0, 0, 0, 0.18), 0 0 0 1px rgba(124, 58, 237, 0.2);
        display: none;
        align-items: flex-start;
        gap: 14px;
        z-index: 99999;
        animation: toastSlideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        border-left: 5px solid var(--primary, #7c3aed);
    }

    @keyframes toastSlideUp {
        from {
            opacity: 0;
            transform: translateY(30px) scale(0.95);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    .toast-avatar {
        width: 46px;
        height: 46px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--primary, #7c3aed);
        flex-shrink: 0;
    }

    .toast-body {
        flex: 1;
        min-width: 0;
    }

    .toast-header-row {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        margin-bottom: 3px;
    }

    .toast-sender {
        font-weight: 700;
        font-size: 14px;
        color: var(--dark, #1f2937);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .toast-time {
        font-size: 11px;
        color: var(--gray, #6b7280);
    }

    .toast-snippet {
        font-size: 13px;
        color: #4b5563;
        line-height: 1.4;
        margin-bottom: 8px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .toast-action-btn {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 12px;
        background: linear-gradient(135deg, var(--primary, #7c3aed), var(--secondary, #ec4899));
        color: white !important;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 700;
        text-decoration: none;
        transition: transform 0.2s;
    }

    .toast-action-btn:hover {
        transform: translateY(-1px);
    }

    .toast-close-btn {
        background: none;
        border: none;
        color: #9ca3af;
        font-size: 18px;
        cursor: pointer;
        padding: 0 4px;
        line-height: 1;
        transition: color 0.2s;
    }

    .toast-close-btn:hover {
        color: #1f2937;
    }

    @media (max-width: 900px) {
        .pixora-nav-links {
            gap: 14px;
            font-size: 12px;
        }
        .pixora-logo {
            font-size: 22px;
        }
    }
</style>

<!-- NAVBAR HTML -->
<nav class="navbar pixora-navbar">
    <div class="navbar-content pixora-navbar-content">
        <a href="index.php" class="navbar-logo pixora-logo">Pixora ✨</a>
        <div class="navbar-links pixora-nav-links">
            <a href="index.php" class="pixora-nav-link <?php echo ($current_page === 'index.php' || $current_page === 'home.php') ? 'active' : ''; ?>">Home</a>
            <a href="search.php" class="pixora-nav-link <?php echo ($current_page === 'search.php') ? 'active' : ''; ?>">Search</a>
            <a href="discover.php" class="pixora-nav-link <?php echo ($current_page === 'discover.php') ? 'active' : ''; ?>">Discover</a>
            <a href="create_post.php" class="pixora-nav-link <?php echo ($current_page === 'create_post.php') ? 'active' : ''; ?>">Create Post</a>
            <a href="trending.php" class="pixora-nav-link <?php echo ($current_page === 'trending.php') ? 'active' : ''; ?>">📈 Trending</a>
            
            <a href="notifications.php" class="pixora-nav-link <?php echo ($current_page === 'notifications.php') ? 'active' : ''; ?>">
                🔔 Notifications
                <?php if ($nav_unread_notif > 0): ?>
                    <span class="nav-badge" id="nav-notif-badge"><?php echo $nav_unread_notif; ?></span>
                <?php endif; ?>
            </a>

            <a href="messages.php" class="pixora-nav-link <?php echo ($current_page === 'messages.php' || $current_page === 'chat.php') ? 'active' : ''; ?>">
                💬 Messages
                <?php if ($nav_unread_msg > 0): ?>
                    <span class="nav-badge" id="nav-msg-badge"><?php echo $nav_unread_msg; ?></span>
                <?php endif; ?>
            </a>

            <a href="profile.php" class="pixora-nav-link <?php echo ($current_page === 'profile.php') ? 'active' : ''; ?>">Profile</a>
            <a href="settings.php" class="pixora-nav-link <?php echo ($current_page === 'settings.php' || $current_page === 'edit_profile.php') ? 'active' : ''; ?>">⚙️ Settings</a>
            <a href="auth/logout.php" class="pixora-nav-link" style="color: #ef4444;" onclick="return confirm('Log out of Pixora?');">Logout</a>
        </div>
    </div>
</nav>

<!-- FLOATING MESSAGE NOTIFICATION TOAST POPUP -->
<div class="msg-popup-toast" id="live-msg-toast">
    <img src="assets/images/default-profile.jpg" alt="Sender" class="toast-avatar" id="toast-sender-avatar">
    <div class="toast-body">
        <div class="toast-header-row">
            <span class="toast-sender" id="toast-sender-name">New Message</span>
            <span class="toast-time" id="toast-time">just now</span>
        </div>
        <div class="toast-snippet" id="toast-snippet">You have a new message</div>
        <div>
            <a href="messages.php" class="toast-action-btn" id="toast-chat-link">💬 Reply Now</a>
        </div>
    </div>
    <button type="button" class="toast-close-btn" onclick="dismissLiveToast()" title="Close">×</button>
</div>

<!-- AUTO NOTIFICATION CHECKER SCRIPT -->
<script>
    let lastSeenMsgId = 0;
    let toastDismissedForId = 0;

    function checkLiveNotifications() {
        fetch('includes/check_notifications.php')
            .then(res => res.json())
            .then(data => {
                if (!data.logged_in) return;

                // Update unread badges
                const notifBadge = document.getElementById('nav-notif-badge');
                if (notifBadge) {
                    if (data.unread_notifications > 0) {
                        notifBadge.textContent = data.unread_notifications;
                        notifBadge.style.display = 'inline-flex';
                    } else {
                        notifBadge.style.display = 'none';
                    }
                }

                const msgBadge = document.getElementById('nav-msg-badge');
                if (msgBadge) {
                    if (data.unread_messages > 0) {
                        msgBadge.textContent = data.unread_messages;
                        msgBadge.style.display = 'inline-flex';
                    } else {
                        msgBadge.style.display = 'none';
                    }
                }

                // Check for new message popup
                if (data.latest_message) {
                    const msg = data.latest_message;
                    
                    // Don't pop up if user is currently chatting with this exact user
                    const urlParams = new URLSearchParams(window.location.search);
                    const currentChatUserId = urlParams.get('user_id');
                    const isChattingWithSender = (window.location.pathname.includes('chat.php') && currentChatUserId == msg.sender_id);

                    if (!isChattingWithSender && msg.id !== toastDismissedForId) {
                        showLiveToast(msg);
                    }
                }
            })
            .catch(() => {});
    }

    function showLiveToast(msg) {
        lastSeenMsgId = msg.id;
        const toast = document.getElementById('live-msg-toast');
        const avatar = document.getElementById('toast-sender-avatar');
        const name = document.getElementById('toast-sender-name');
        const snippet = document.getElementById('toast-snippet');
        const time = document.getElementById('toast-time');
        const chatLink = document.getElementById('toast-chat-link');

        if (toast && avatar && name && snippet && chatLink) {
            avatar.src = msg.profile_picture;
            name.textContent = msg.full_name;
            snippet.textContent = '"' + msg.message + '"';
            if (time) time.textContent = msg.time;
            chatLink.href = 'chat.php?user_id=' + msg.sender_id;

            toast.style.display = 'flex';
        }
    }

    function dismissLiveToast() {
        const toast = document.getElementById('live-msg-toast');
        if (toast) {
            toast.style.display = 'none';
            toastDismissedForId = lastSeenMsgId;
        }
    }

    // Run check on page load and every 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        checkLiveNotifications();
        setInterval(checkLiveNotifications, 5000);
    });
</script>
