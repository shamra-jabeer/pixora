<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['logged_in' => false]);
    exit();
}

require '../config/database.php';
require '../includes/helpers.php';
require '../includes/message_actions.php';
require '../includes/notification_helpers.php';

$user_id = (int)$_SESSION['user_id'];

// Get latest unread message
$msg_stmt = $conn->prepare("
    SELECT m.id, m.sender_id, m.message, m.created_at,
           u.full_name, u.username, u.profile_picture
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.recipient_id = ? AND m.is_read = 0
    ORDER BY m.created_at DESC
    LIMIT 1
");
$msg_stmt->bind_param("i", $user_id);
$msg_stmt->execute();
$msg_res = $msg_stmt->get_result();
$latest_msg = $msg_res->fetch_assoc();
$msg_stmt->close();

$unread_msgs = getUnreadMessageCount($user_id, $conn);
$unread_notifs = getUnreadNotificationCount($user_id, $conn);

$pic = 'assets/images/default-profile.jpg';
if ($latest_msg && !empty($latest_msg['profile_picture']) && file_exists(__DIR__ . '/../uploads/profile/' . $latest_msg['profile_picture']) && strpos($latest_msg['profile_picture'], 'default') === false) {
    $pic = "uploads/profile/" . $latest_msg['profile_picture'];
}

echo json_encode([
    'logged_in' => true,
    'unread_messages' => $unread_msgs,
    'unread_notifications' => $unread_notifs,
    'latest_message' => $latest_msg ? [
        'id' => (int)$latest_msg['id'],
        'sender_id' => (int)$latest_msg['sender_id'],
        'full_name' => $latest_msg['full_name'],
        'username' => $latest_msg['username'],
        'profile_picture' => $pic,
        'message' => htmlspecialchars($latest_msg['message']),
        'time' => formatDate($latest_msg['created_at'])
    ] : null
]);
exit();
?>
