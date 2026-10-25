<?php
/**
 * Send a message from one user to another
 */
function sendMessage($sender_id, $recipient_id, $message, $conn) {
    $stmt = $conn->prepare("
        INSERT INTO messages (sender_id, recipient_id, message, is_read, created_at)
        VALUES (?, ?, ?, 0, NOW())
    ");
    $stmt->bind_param("iis", $sender_id, $recipient_id, $message);
    $result = $stmt->execute();
    $stmt->close();
    
    // Create notification for new message
    if ($result) {
        // Check if notification_helpers is already loaded, if not load it
        if (!function_exists('notifyNewMessage')) {
            require_once 'notification_helpers.php';
        }
        notifyNewMessage($sender_id, $recipient_id, $conn);
    }
    
    return $result;
}

/**
 * Mark messages as read from a specific sender
 */
function markMessagesAsRead($current_user_id, $sender_id, $conn) {
    $stmt = $conn->prepare("
        UPDATE messages 
        SET is_read = 1 
        WHERE recipient_id = ? AND sender_id = ? AND is_read = 0
    ");
    $stmt->bind_param("ii", $current_user_id, $sender_id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

/**
 * Get unread message count for current user
 */
function getUnreadMessageCount($user_id, $conn) {
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM messages WHERE recipient_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    return $data['cnt'] ?? 0;
}

/**
 * Get messages between two users
 */
function getMessagesBetweenUsers($user_id_1, $user_id_2, $limit, $conn) {
    $stmt = $conn->prepare("
        SELECT m.id, m.sender_id, m.message, m.created_at, m.is_read,
               u.full_name, u.username, u.profile_picture
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE (m.sender_id = ? AND m.recipient_id = ?) 
           OR (m.sender_id = ? AND m.recipient_id = ?)
        ORDER BY m.created_at ASC
        LIMIT ?
    ");
    $stmt->bind_param("iiiii", $user_id_1, $user_id_2, $user_id_2, $user_id_1, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $messages;
}

/**
 * Get all conversations for a user - SIMPLIFIED
 */
function getUserConversations($user_id, $conn) {
    $query = "
        SELECT DISTINCT
            CASE 
                WHEN sender_id = $user_id THEN recipient_id
                ELSE sender_id
            END as user_id
        FROM messages
        WHERE sender_id = $user_id OR recipient_id = $user_id
    ";
    
    $result = $conn->query($query);
    $user_ids = [];
    
    while ($row = $result->fetch_assoc()) {
        $user_ids[] = $row['user_id'];
    }
    
    $conversations = [];
    foreach ($user_ids as $other_user_id) {
        $user_stmt = $conn->prepare("SELECT id, full_name, username, profile_picture FROM users WHERE id = ?");
        $user_stmt->bind_param("i", $other_user_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        $user_data = $user_result->fetch_assoc();
        $user_stmt->close();
        
        if (!$user_data) continue;
        
        // Get last message
        $msg_stmt = $conn->prepare("
            SELECT message, created_at FROM messages 
            WHERE (sender_id = ? AND recipient_id = ?) 
               OR (sender_id = ? AND recipient_id = ?)
            ORDER BY created_at DESC LIMIT 1
        ");
        $msg_stmt->bind_param("iiii", $user_id, $other_user_id, $other_user_id, $user_id);
        $msg_stmt->execute();
        $msg_result = $msg_stmt->get_result();
        $msg_data = $msg_result->fetch_assoc();
        $msg_stmt->close();
        
        // Get unread count
        $unread_stmt = $conn->prepare("
            SELECT COUNT(*) as cnt FROM messages 
            WHERE sender_id = ? AND recipient_id = ? AND is_read = 0
        ");
        $unread_stmt->bind_param("ii", $other_user_id, $user_id);
        $unread_stmt->execute();
        $unread_result = $unread_stmt->get_result();
        $unread_data = $unread_result->fetch_assoc();
        $unread_stmt->close();
        
        $conversations[] = [
            'user_id' => $other_user_id,
            'full_name' => $user_data['full_name'],
            'username' => $user_data['username'],
            'profile_picture' => $user_data['profile_picture'],
            'last_message' => $msg_data['message'] ?? '',
            'last_message_time' => $msg_data['created_at'] ?? '',
            'unread_count' => $unread_data['cnt'] ?? 0
        ];
    }
    
    // Sort by last message time
    usort($conversations, function($a, $b) {
        return strtotime($b['last_message_time']) - strtotime($a['last_message_time']);
    });
    
    return $conversations;
}

/**
 * Delete a message
 */
function deleteMessage($message_id, $user_id, $conn) {
    $stmt = $conn->prepare("DELETE FROM messages WHERE id = ? AND sender_id = ?");
    $stmt->bind_param("ii", $message_id, $user_id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

/**
 * Check if messages table exists
 */
function messagesTableExists($conn) {
    $result = $conn->query("SHOW TABLES LIKE 'messages'");
    return $result->num_rows > 0;
}
?>