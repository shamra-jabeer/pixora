<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

require 'config/database.php';
require 'includes/helpers.php';

$current_user_id = $_SESSION['user_id'];

// Get all notifications for current user
$notif_stmt = $conn->prepare("
    SELECT 
        n.id,
        n.type,
        n.user_id,
        n.post_id,
        n.comment_id,
        n.is_read,
        n.created_at,
        u.full_name,
        u.username,
        u.profile_picture,
        p.caption as post_caption,
        c.comment_text
    FROM notifications n
    JOIN users u ON n.user_id = u.id
    LEFT JOIN posts p ON n.post_id = p.id
    LEFT JOIN comments c ON n.comment_id = c.id
    WHERE n.recipient_id = ?
    ORDER BY n.is_read ASC, n.created_at DESC
    LIMIT 100
");
$notif_stmt->bind_param("i", $current_user_id);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();
$notifications = $notif_result->fetch_all(MYSQLI_ASSOC);
$notif_stmt->close();

// Count unread notifications
$unread_count = 0;
foreach ($notifications as $notif) {
    if ($notif['is_read'] == 0) {
        $unread_count++;
    }
}

// Mark all as read if viewing
if (!empty($notifications)) {
    $mark_read = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE recipient_id = ? AND is_read = 0");
    $mark_read->bind_param("i", $current_user_id);
    $mark_read->execute();
    $mark_read->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Pixora</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .notifications-container {
            max-width: 800px;
            margin: 60px auto;
            padding: 0 20px;
        }

        .notifications-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            padding: 40px 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-lg);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notifications-header h1 {
            font-size: 32px;
            margin: 0;
            font-weight: 800;
        }

        .notif-count-badge {
            background: rgba(255, 255, 255, 0.3);
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 14px;
        }

        .notifications-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .notification-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow-md);
            transition: all 0.3s ease;
            border-left: 4px solid var(--primary);
            cursor: pointer;
            position: relative;
        }

        .notification-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateX(5px);
        }

        .notification-card.unread {
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.05), rgba(236, 72, 153, 0.05));
        }

        .notification-card.unread::before {
            content: '';
            position: absolute;
            top: 10px;
            right: 10px;
            width: 10px;
            height: 10px;
            background: var(--primary);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(124, 58, 237, 0.7);
            }
            50% {
                box-shadow: 0 0 0 8px rgba(124, 58, 237, 0);
            }
        }

        .notification-content {
            display: flex;
            gap: 15px;
            align-items: flex-start;
        }

        .notification-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary);
            flex-shrink: 0;
        }

        .notification-body {
            flex: 1;
        }

        .notification-title {
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 5px;
            font-size: 15px;
        }

        .notification-message {
            color: var(--gray);
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 8px;
        }

        .notification-time {
            color: var(--gray);
            font-size: 12px;
            font-weight: 600;
        }

        .notification-delete {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(239, 68, 68, 0.1);
            border: none;
            color: var(--danger);
            cursor: pointer;
            font-size: 18px;
            padding: 5px 10px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .notification-delete:hover {
            background: var(--danger);
            color: white;
        }

        .notification-type {
            display: inline-block;
            padding: 4px 10px;
            background: rgba(124, 58, 237, 0.1);
            color: var(--primary);
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            margin-top: 8px;
            text-transform: uppercase;
        }

        .notification-type.like {
            background: rgba(236, 72, 153, 0.1);
            color: #ec4899;
        }

        .notification-type.comment {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .notification-type.follow {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .notification-type.message {
            background: rgba(147, 51, 234, 0.1);
            color: #9333ea;
        }

        .empty-notifications {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: var(--shadow-md);
        }

        .empty-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }

        .empty-text {
            font-size: 20px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 10px;
        }

        .empty-subtext {
            color: var(--gray);
            margin-bottom: 25px;
        }

        .clear-all-btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700;
            transition: all 0.3s ease;
            text-decoration: none;
            margin-top: 20px;
        }

        .clear-all-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .notification-action {
            margin-top: 10px;
        }

        .notification-action a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 700;
            font-size: 13px;
            transition: all 0.2s;
        }

        .notification-action a:hover {
            color: var(--secondary);
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .notifications-header {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }

            .notifications-header h1 {
                font-size: 24px;
            }

            .notification-card {
                margin-right: 20px;
            }

            .notification-delete {
                position: static;
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <!-- NOTIFICATIONS CONTAINER -->
    <div class="notifications-container">
        <!-- HEADER -->
        <div class="notifications-header">
            <div>
                <h1>🔔 Notifications</h1>
            </div>
            <?php if (count($notifications) > 0): ?>
                <div class="notif-count-badge">
                    <?php echo count($notifications); ?> Total
                </div>
            <?php endif; ?>
        </div>

        <!-- NOTIFICATIONS LIST -->
        <?php if (count($notifications) > 0): ?>
            <div class="notifications-list">
                <?php foreach ($notifications as $notif): ?>
                    <div class="notification-card <?php echo $notif['is_read'] == 0 ? 'unread' : ''; ?>">
                        <img src="<?php 
                            $pic ="https://via.placeholder.com/150/cccccc/666666?text=User";
                            if (!empty($notif['profile_picture']) && file_exists("uploads/profile/" . $notif['profile_picture'])) {
                                $pic = "uploads/profile/" . $notif['profile_picture'];
                            }
                            echo htmlspecialchars($pic);
                        ?>" alt="Avatar" class="notification-avatar" onclick="window.location.href='profile.php?id=<?php echo $notif['user_id']; ?>'">

                        <div class="notification-body">
                            <div class="notification-title">
                                <?php echo htmlspecialchars($notif['full_name']); ?>
                                <span class="notification-type <?php echo strtolower($notif['type']); ?>">
                                    <?php 
                                    if ($notif['type'] === 'like') echo '❤️ Like';
                                    elseif ($notif['type'] === 'comment') echo '💬 Comment';
                                    elseif ($notif['type'] === 'follow') echo '👥 Follow';
                                    elseif ($notif['type'] === 'message') echo '💌 Message';
                                    ?>
                                </span>
                            </div>

                            <div class="notification-message">
                                <?php
                                if ($notif['type'] === 'like') {
                                    echo '@' . htmlspecialchars($notif['username']) . ' liked your post';
                                } elseif ($notif['type'] === 'comment') {
                                    echo '@' . htmlspecialchars($notif['username']) . ' commented: "' . htmlspecialchars(substr($notif['comment_text'], 0, 50)) . (strlen($notif['comment_text']) > 50 ? '..."' : '"');
                                } elseif ($notif['type'] === 'follow') {
                                    echo '@' . htmlspecialchars($notif['username']) . ' started following you';
                                } elseif ($notif['type'] === 'message') {
                                    echo '@' . htmlspecialchars($notif['username']) . ' sent you a message';
                                }
                                ?>
                            </div>

                            <div class="notification-time">
                                <?php echo formatDate($notif['created_at']); ?>
                            </div>

                            <?php if ($notif['post_id']): ?>
                                <div class="notification-action">
                                    <a href="post.php?id=<?php echo $notif['post_id']; ?>">View Post →</a>
                                </div>
                            <?php elseif ($notif['type'] === 'follow'): ?>
                                <div class="notification-action">
                                    <a href="profile.php?id=<?php echo $notif['user_id']; ?>">View Profile →</a>
                                </div>
                            <?php elseif ($notif['type'] === 'message'): ?>
                                <div class="notification-action">
                                    <a href="chat.php?user_id=<?php echo $notif['user_id']; ?>">Reply →</a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <form method="POST" action="includes/notification_actions.php" style="display: inline;">
                            <input type="hidden" name="notif_id" value="<?php echo $notif['id']; ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="notification-delete" title="Delete">×</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- CLEAR ALL BUTTON -->
            <form method="POST" action="includes/notification_actions.php" style="text-align: center; margin-top: 30px;">
                <input type="hidden" name="action" value="clear_all">
                <button type="submit" class="clear-all-btn" onclick="return confirm('Clear all notifications?');">
                    Clear All Notifications
                </button>
            </form>
        <?php else: ?>
            <div class="empty-notifications">
                <div class="empty-icon">🔔</div>
                <div class="empty-text">No Notifications</div>
                <p class="empty-subtext">You're all caught up! When someone likes your post, comments, follows you, or sends you a message, you'll see it here.</p>
                <a href="home.php" class="clear-all-btn" style="margin-top: 20px;">Go to Home Feed</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- FOOTER -->
    <footer style="background: var(--dark); color: white; padding: 40px 20px; text-align: center; margin-top: 60px;">
        <div class="container">
            <p style="margin-bottom: 10px;">© 2024 <strong>Pixora</strong> - Your Premium Social Media Platform</p>
            <p style="color: var(--gray); font-size: 13px;">Built with ❤️ using PHP & MySQL</p>
        </div>
    </footer>

</body>
</html>