<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

require 'config/database.php';
require 'includes/helpers.php';
require 'includes/message_actions.php';

$current_user_id = $_SESSION['user_id'];

// Get unread message count for navbar badge
$unread_msg_count = getUnreadMessageCount($current_user_id, $conn);

// Get all conversations
$conversations = getUserConversations($current_user_id, $conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Pixora</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .messages-container {
            max-width: 600px;
            margin: 60px auto;
            padding: 0 20px;
        }

        .messages-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            padding: 40px 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-lg);
            color: white;
        }

        .messages-header h1 {
            font-size: 32px;
            margin: 0 0 10px 0;
            font-weight: 800;
        }

        .messages-header p {
            margin: 0;
            opacity: 0.9;
        }

        .conversations-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .conversation-card {
            background: white;
            border-radius: 12px;
            padding: 15px;
            box-shadow: var(--shadow-md);
            transition: all 0.3s ease;
            border-left: 4px solid var(--primary);
            cursor: pointer;
            display: flex;
            gap: 15px;
            align-items: center;
            text-decoration: none;
            color: inherit;
        }

        .conversation-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateX(5px);
        }

        .conversation-card.unread {
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.05), rgba(236, 72, 153, 0.05));
        }

        .conversation-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary);
            flex-shrink: 0;
        }

        .conversation-info {
            flex: 1;
            min-width: 0;
        }

        .conversation-name {
            font-weight: 700;
            color: var(--dark);
            font-size: 15px;
            margin-bottom: 5px;
        }

        .conversation-username {
            color: var(--gray);
            font-size: 13px;
            margin-bottom: 5px;
        }

        .conversation-preview {
            color: var(--gray);
            font-size: 13px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .conversation-time {
            color: var(--gray);
            font-size: 12px;
            text-align: right;
            flex-shrink: 0;
        }

        .unread-badge {
            display: inline-block;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 12px;
            flex-shrink: 0;
        }

        .empty-messages {
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

        .start-chat-btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .start-chat-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .message-badge {
            display: inline-block;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
            position: absolute;
            top: -8px;
            right: -8px;
        }

        .navbar-links a {
            position: relative;
        }

        @media (max-width: 768px) {
            .messages-header {
                padding: 30px 20px;
            }

            .messages-header h1 {
                font-size: 24px;
            }

            .conversation-card {
                padding: 12px;
            }

            .conversation-avatar {
                width: 50px;
                height: 50px;
            }

            .conversation-name {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <!-- MESSAGES CONTAINER -->
    <div class="messages-container">
        <!-- HEADER -->
        <div class="messages-header">
            <h1>💬 Messages</h1>
            <p>Your conversations</p>
        </div>

        <!-- CONVERSATIONS LIST -->
        <?php if (count($conversations) > 0): ?>
            <div class="conversations-list">
                <?php foreach ($conversations as $conv): ?>
                    <a href="chat.php?user_id=<?php echo $conv['user_id']; ?>" class="conversation-card <?php echo $conv['unread_count'] > 0 ? 'unread' : ''; ?>">
                        <img src="<?php 
                            $pic = "https://via.placeholder.com/150/cccccc/666666?text=User";
                            $default_pic = 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22150%22 height=%22150%22%3E%3Crect fill=%22%23cccccc%22 width=%22150%22 height=%22150%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%23666666%22 font-family=%22Arial%22 font-size=%2216%22%3EUser%3C/text%3E%3C/svg%3E';
if (!empty($conv['profile_picture']) && file_exists("uploads/profile/" . $conv['profile_picture']) && strpos($conv['profile_picture'], 'default') === false) {
                                $pic = "uploads/profile/" . $conv['profile_picture'];
                            }
                            echo htmlspecialchars($pic);
                        ?>" alt="Avatar" class="conversation-avatar">

                        <div class="conversation-info">
                            <div class="conversation-name">
                                <?php echo htmlspecialchars($conv['full_name']); ?>
                            </div>
                            <div class="conversation-username">
                                @<?php echo htmlspecialchars($conv['username']); ?>
                            </div>
                            <div class="conversation-preview">
                                <?php echo htmlspecialchars(substr($conv['last_message'], 0, 50)); ?>
                            </div>
                        </div>

                        <div style="text-align: right; flex-shrink: 0;">
                            <div class="conversation-time">
                                <?php echo formatDate($conv['last_message_time']); ?>
                            </div>
                            <?php if ($conv['unread_count'] > 0): ?>
                                <div class="unread-badge">
                                    <?php echo $conv['unread_count']; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-messages">
                <div class="empty-icon">💬</div>
                <div class="empty-text">No Messages Yet</div>
                <p class="empty-subtext">Start a conversation by visiting someone's profile and clicking message!</p>
                <a href="discover.php" class="start-chat-btn">Discover Users</a>
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