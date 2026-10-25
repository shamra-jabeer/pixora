<?php
/**
 * Notification Helper Functions
 * STEP 8: Notifications
 */

/**
 * Create a notification
 */
function createNotification($user_id, $recipient_id, $type, $post_id = null, $comment_id = null, $conn) {
    // Don't notify yourself
    if ($user_id === $recipient_id) {
        return false;
    }
    
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, recipient_id, type, post_id, comment_id, is_read, created_at)
        VALUES (?, ?, ?, ?, ?, 0, NOW())
    ");
    $stmt->bind_param("iisii", $user_id, $recipient_id, $type, $post_id, $comment_id);
    return $stmt->execute();
}

/**
 * Notify when someone likes a post
 */
function notifyPostLike($liker_id, $post_id, $conn) {
    // Get post owner
    $post_stmt = $conn->prepare("SELECT user_id FROM posts WHERE id = ?");
    $post_stmt->bind_param("i", $post_id);
    $post_stmt->execute();
    $post_result = $post_stmt->get_result();
    $post = $post_result->fetch_assoc();
    $post_stmt->close();
    
    if ($post && $post['user_id'] !== $liker_id) {
        return createNotification($liker_id, $post['user_id'], 'like', $post_id, null, $conn);
    }
    return false;
}

/**
 * Notify when someone comments on a post
 */
function notifyPostComment($commenter_id, $post_id, $comment_id, $conn) {
    // Get post owner
    $post_stmt = $conn->prepare("SELECT user_id FROM posts WHERE id = ?");
    $post_stmt->bind_param("i", $post_id);
    $post_stmt->execute();
    $post_result = $post_stmt->get_result();
    $post = $post_result->fetch_assoc();
    $post_stmt->close();
    
    if ($post && $post['user_id'] !== $commenter_id) {
        return createNotification($commenter_id, $post['user_id'], 'comment', $post_id, $comment_id, $conn);
    }
    return false;
}

/**
 * Notify when someone follows a user
 */
function notifyUserFollow($follower_id, $following_id, $conn) {
    if ($follower_id !== $following_id) {
        return createNotification($follower_id, $following_id, 'follow', null, null, $conn);
    }
    return false;
}

/**
 * Notify when someone sends a message
 */
function notifyNewMessage($sender_id, $recipient_id, $conn) {
    if ($sender_id !== $recipient_id) {
        return createNotification($sender_id, $recipient_id, 'message', null, null, $conn);
    }
    return false;
}

/**
 * Get unread notification count
 */
function getUnreadNotificationCount($user_id, $conn) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE recipient_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    return $data['count'];
}

/**
 * Get total notification count
 */
function getTotalNotificationCount($user_id, $conn) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE recipient_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    return $data['count'];
}

/**
 * Get notifications
 */
function getNotifications($user_id, $conn, $limit = 50) {
    $stmt = $conn->prepare("
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
            u.profile_picture
        FROM notifications n
        JOIN users u ON n.user_id = u.id
        WHERE n.recipient_id = ?
        ORDER BY n.is_read ASC, n.created_at DESC
        LIMIT ?
    ");
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $notifications;
}

/**
 * Delete notification
 */
function deleteNotification($notif_id, $user_id, $conn) {
    $verify = $conn->prepare("SELECT recipient_id FROM notifications WHERE id = ?");
    $verify->bind_param("i", $notif_id);
    $verify->execute();
    $result = $verify->get_result();
    $notif = $result->fetch_assoc();
    $verify->close();
    
    if ($notif && $notif['recipient_id'] === $user_id) {
        $delete = $conn->prepare("DELETE FROM notifications WHERE id = ?");
        $delete->bind_param("i", $notif_id);
        return $delete->execute();
    }
    return false;
}

/**
 * Clear all notifications
 */
function clearAllNotifications($user_id, $conn) {
    $stmt = $conn->prepare("DELETE FROM notifications WHERE recipient_id = ?");
    $stmt->bind_param("i", $user_id);
    return $stmt->execute();
}

/**
 * Mark notification as read
 */
function markNotificationAsRead($notif_id, $user_id, $conn) {
    $verify = $conn->prepare("SELECT recipient_id FROM notifications WHERE id = ?");
    $verify->bind_param("i", $notif_id);
    $verify->execute();
    $result = $verify->get_result();
    $notif = $result->fetch_assoc();
    $verify->close();
    
    if ($notif && $notif['recipient_id'] === $user_id) {
        $update = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        $update->bind_param("i", $notif_id);
        return $update->execute();
    }
    return false;
}

/**
 * Mark all notifications as read
 */
function markAllNotificationsAsRead($user_id, $conn) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE recipient_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    return $stmt->execute();
}
?>