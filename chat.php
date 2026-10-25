<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

require 'config/database.php';
require 'includes/helpers.php';
require 'includes/message_actions.php';
require 'includes/notification_helpers.php';

$current_user_id = $_SESSION['user_id'];

// Get the user ID from query parameter FIRST
if (!isset($_GET['user_id'])) {
    header("Location: messages.php");
    exit();
}

$other_user_id = intval($_GET['user_id']);

// Get unread message count BEFORE marking as read (so badge shows correctly)
$unread_msg_count = getUnreadMessageCount($current_user_id, $conn);

// Get unread notification count
$unread_notif_count = getUnreadNotificationCount($current_user_id, $conn);

// Security: can't message yourself
if ($other_user_id === $current_user_id) {
    header("Location: messages.php");
    exit();
}

// Get the other user's info
$other_user = getUserInfo($other_user_id, $conn);
if (!$other_user) {
    header("Location: messages.php");
    exit();
}

// Handle deleting message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_message') {
    $message_id = intval($_POST['message_id'] ?? 0);
    
    if ($message_id > 0) {
        // Verify message belongs to current user before deleting
        $verify_stmt = $conn->prepare("SELECT sender_id FROM messages WHERE id = ?");
        $verify_stmt->bind_param("i", $message_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        $msg_owner = $verify_result->fetch_assoc();
        $verify_stmt->close();
        
        // Only allow sender to delete
        if ($msg_owner && $msg_owner['sender_id'] === $current_user_id) {
            $delete_stmt = $conn->prepare("DELETE FROM messages WHERE id = ?");
            $delete_stmt->bind_param("i", $message_id);
            $delete_stmt->execute();
            $delete_stmt->close();
        }
    }
    
    // Redirect to prevent resubmission
    header("Location: chat.php?user_id=" . $other_user_id);
    exit();
}

// Handle sending message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = sanitize($_POST['message']);
    
    if (!empty($message)) {
        sendMessage($current_user_id, $other_user_id, $message, $conn);
    }
    
    // Redirect to prevent resubmission
    header("Location: chat.php?user_id=" . $other_user_id);
    exit();
}

// Mark all messages from other_user as read
markMessagesAsRead($current_user_id, $other_user_id, $conn);

// Get all messages between these two users (in correct order: oldest first)
$all_messages = getMessagesBetweenUsers($current_user_id, $other_user_id, 100, $conn);
// Ensure messages are in chronological order (oldest first)
$messages = $all_messages;

$current_user = getUserInfo($current_user_id, $conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat with <?php echo htmlspecialchars($other_user['full_name']); ?> - Pixora</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .chat-container {
            max-width: 700px;
            margin: 60px auto;
            padding: 0 20px;
            height: calc(100vh - 120px);
            display: flex;
            flex-direction: column;
            gap: 0;
        }

        .chat-header {
            background: white;
            padding: 20px;
            border-bottom: 2px solid var(--light);
            border-radius: 15px 15px 0 0;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: var(--shadow-md);
        }

        .chat-header-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary);
        }

        .chat-header-info h2 {
            margin: 0;
            font-size: 18px;
            color: var(--dark);
        }

        .chat-header-info p {
            margin: 5px 0 0 0;
            color: var(--gray);
            font-size: 13px;
        }

        .chat-header-back {
            margin-left: auto;
            background: var(--light);
            border: none;
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700;
            color: var(--primary);
            transition: all 0.3s ease;
        }

        .chat-header-back:hover {
            background: var(--primary);
            color: white;
        }

        .messages-area {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: linear-gradient(180deg, #f9fafb 0%, #ffffff 100%);
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .message {
            display: flex;
            gap: 10px;
            align-items: flex-end;
            animation: slideIn 0.3s ease;
            flex-wrap: wrap;
            min-width: 0;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message.sent {
            justify-content: flex-end;
        }

        .message-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary);
        }

        .message.sent .message-avatar {
            order: 2;
        }

        .message-bubble {
            max-width: 500px !important;
            min-width: 150px !important;
            padding: 12px 16px;
            border-radius: 18px;
            line-height: 1.5;
            font-size: 14px;
            display: inline-block !important;
            text-align: left;
            word-wrap: break-word;
            white-space: normal;
        }

        .message.received .message-bubble {
            background: white;
            color: var(--dark);
            border: 1px solid var(--light);
            box-shadow: var(--shadow-sm);
            white-space: normal !important;
            word-wrap: break-word !important;
        }

        .message.sent .message-bubble {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            box-shadow: var(--shadow-md);
            white-space: normal !important;
            word-wrap: break-word !important;
        }

        .message-time {
            font-size: 12px;
            color: var(--gray);
            margin-top: 5px;
        }

        .message.sent .message-time {
            text-align: right;
        }

        .empty-chat {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            gap: 15px;
        }

        .empty-icon {
            font-size: 60px;
        }

        .empty-text {
            color: var(--gray);
            font-size: 16px;
            text-align: center;
        }

        .message-input-area {
            background: white;
            padding: 15px 20px;
            border-top: 2px solid var(--light);
            border-radius: 0 0 15px 15px;
            box-shadow: var(--shadow-md);
            display: flex;
            gap: 10px;
        }

        .message-input {
            flex: 1;
            border: 1px solid var(--light);
            padding: 12px 15px;
            border-radius: 25px;
            font-size: 14px;
            font-family: inherit;
            resize: none;
            max-height: 100px;
            background: var(--light);
            transition: all 0.3s ease;
        }

        .message-input:focus {
            outline: none;
            background: white;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
        }

        .message-send-btn {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 700;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .message-send-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .message-send-btn:active {
            transform: scale(0.95);
        }

        @media (max-width: 768px) {
            .chat-container {
                margin: 50px auto;
                height: calc(100vh - 100px);
            }

            .chat-header {
                padding: 15px;
            }

            .messages-area {
                padding: 15px;
            }

            .message-bubble {
                max-width: 85%;
                word-wrap: break-word;
                overflow-wrap: break-word;
                word-break: normal;
            }

            .message-input-area {
                padding: 10px 15px;
            }

            .message-input {
                padding: 10px 12px;
                font-size: 13px;
            }

            .message-send-btn {
                padding: 10px 15px;
            }
        }

        .messages-area::-webkit-scrollbar {
            width: 8px;
        }

        .messages-area::-webkit-scrollbar-track {
            background: var(--light);
            border-radius: 10px;
        }

        .messages-area::-webkit-scrollbar-thumb {
            background: var(--gray);
            border-radius: 10px;
        }

        .messages-area::-webkit-scrollbar-thumb:hover {
            background: var(--primary);
        }

        .message-delete-btn {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(239, 68, 68, 0.1);
            border: none;
            color: #ef4444;
            cursor: pointer;
            font-size: 16px;
            padding: 5px 8px;
            border-radius: 50%;
            opacity: 0;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
        }

        .message.sent:hover .message-delete-btn {
            opacity: 1;
        }

        .message-delete-btn:hover {
            background: #ef4444;
            color: white;
        }

        .message-wrapper {
            position: relative;
            flex: 0 0 auto;
            min-width: 0;
        }

        /* Message badge animation */
        @keyframes pulse-badge {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.05); }
        }

        /* Message flow indicator */
        .message-flow-info {
            text-align: center;
            padding: 15px 0;
            color: var(--gray);
            font-size: 13px;
            font-weight: 600;
        }

        .message-flow-start {
            color: var(--gray);
        }

        .message-flow-end {
            color: var(--primary);
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <!-- CHAT CONTAINER -->
    <div class="chat-container">
        <!-- CHAT HEADER -->
        <div class="chat-header">
            <img src="<?php 
                $pic = 'assets/images/default-profile.jpg';
                $default_pic = 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22150%22 height=%22150%22%3E%3Crect fill=%22%23cccccc%22 width=%22150%22 height=%22150%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%23666666%22 font-family=%22Arial%22 font-size=%2216%22%3EUser%3C/text%3E%3C/svg%3E';
if (!empty($other_user['profile_picture']) && file_exists("uploads/profile/" . $other_user['profile_picture']) && strpos($other_user['profile_picture'], 'default') === false) {
                    $pic = "uploads/profile/" . $other_user['profile_picture'];
                }
                echo htmlspecialchars($pic);
            ?>" alt="Avatar" class="chat-header-avatar">

            <div class="chat-header-info">
                <h2><?php echo htmlspecialchars($other_user['full_name']); ?></h2>
                <p>@<?php echo htmlspecialchars($other_user['username']); ?></p>
            </div>

            <a href="messages.php" class="chat-header-back">← Back</a>
        </div>

        <!-- MESSAGES AREA -->
        <div class="messages-area" id="messagesArea">
            <?php if (count($messages) === 0): ?>
                <div class="empty-chat">
                    <div class="empty-icon">💬</div>
                    <div class="empty-text">No messages yet. Start the conversation!</div>
                </div>
            <?php else: ?>
                <!-- START OF CONVERSATION (OLDEST MESSAGES) -->
                <div class="message-flow-info message-flow-start">
                    ↑ Earlier messages ↑
                </div>
                
                <?php foreach ($messages as $msg): ?>
                    <div class="message <?php echo $msg['sender_id'] === $current_user_id ? 'sent' : 'received'; ?>">
                        <?php if ($msg['sender_id'] !== $current_user_id): ?>
                            <img src="<?php 
                                $pic = 'assets/images/default-profile.jpg';
                                if (!empty($msg['profile_picture']) && file_exists("uploads/profile/" . $msg['profile_picture']) && strpos($msg['profile_picture'], 'default') === false) {
                                    $pic = "uploads/profile/" . $msg['profile_picture'];
                                }
                                echo htmlspecialchars($pic);
                            ?>" alt="Avatar" class="message-avatar">
                        <?php endif; ?>

                        <div class="message-wrapper">
                            <div class="message-bubble">
                                <?php echo htmlspecialchars($msg['message']); ?>
                                <?php if ($msg['sender_id'] === $current_user_id): ?>
                                    <form method="POST" style="display: inline; margin: 0;" onsubmit="return confirm('Delete this message?');">
                                        <input type="hidden" name="action" value="delete_message">
                                        <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                        <button type="submit" class="message-delete-btn" title="Delete message">×</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                            <div class="message-time">
                                <?php echo formatDate($msg['created_at']); ?>
                            </div>
                        </div>

                        <?php if ($msg['sender_id'] === $current_user_id): ?>
                            <img src="<?php 
                                $pic = "https://via.placeholder.com/150/cccccc/666666?text=User";
                               if (!empty($current_user['profile_picture']) && file_exists("uploads/profile/" . $current_user['profile_picture']) && strpos($current_user['profile_picture'], 'default') === false) {
                                    $pic = "uploads/profile/" . $current_user['profile_picture'];
                                }
                                echo htmlspecialchars($pic);
                            ?>" alt="Your Avatar" class="message-avatar">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                
                <!-- END OF CONVERSATION (NEWEST MESSAGES) -->
                <div class="message-flow-info message-flow-end">
                    ↓ Newest messages ↓
                </div>
            <?php endif; ?>
        </div>

        <!-- MESSAGE INPUT AREA -->
        <div class="message-input-area">
            <form method="POST" style="display: flex; gap: 10px; width: 100%;">
                <textarea
                    name="message"
                    class="message-input"
                    placeholder="Type your message..."
                    rows="1"
                    required
                ></textarea>
                <button type="submit" class="message-send-btn">Send ➤</button>
            </form>
        </div>
    </div>

    <script>
        // Auto-scroll to latest message (with delay for reliability)
        function scrollToBottom() {
            const messagesArea = document.getElementById('messagesArea');
            if (messagesArea) {
                setTimeout(() => {
                    messagesArea.scrollTop = messagesArea.scrollHeight;
                }, 100);
            }
        }

        // Scroll on page load
        scrollToBottom();

        // Auto-expand textarea
        const textarea = document.querySelector('.message-input');
        if (textarea) {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 100) + 'px';
            });
        }
    </script>

</body>
</html>