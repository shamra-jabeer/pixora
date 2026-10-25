<?php
session_start();

$is_ajax = isset($_GET['ajax']) || isset($_POST['ajax']) || 
           (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
           (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if (!isset($_SESSION['user_id'])) {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit();
    }
    header("Location: ../auth/login.php");
    exit();
}

require '../config/database.php';
require '../includes/helpers.php';
require '../includes/notification_helpers.php';

$user_id = (int)$_SESSION['user_id'];
$post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : (isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0);
$action = isset($_POST['action']) ? trim($_POST['action']) : (isset($_GET['action']) ? trim($_GET['action']) : (isset($_POST['comment']) ? 'add' : ''));

if (!$post_id) {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid post ID']);
        exit();
    }
    header("Location: ../index.php");
    exit();
}

// Verify post exists
$verify_stmt = $conn->prepare("SELECT id, user_id FROM posts WHERE id = ?");
$verify_stmt->bind_param("i", $post_id);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();

if ($verify_result->num_rows === 0) {
    $verify_stmt->close();
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Post not found']);
        exit();
    }
    header("Location: ../index.php");
    exit();
}
$verify_stmt->close();

// ADD COMMENT
if ($action === 'add' || isset($_POST['comment'])) {
    $comment_text = trim($_POST['comment'] ?? $_POST['comment_text'] ?? '');
    
    // Validation
    if (strlen($comment_text) > 500) {
        $comment_text = substr($comment_text, 0, 500);
    }
    
    if (!empty($comment_text)) {
        $comment_stmt = $conn->prepare(
            "INSERT INTO comments (user_id, post_id, comment_text, created_at) VALUES (?, ?, ?, NOW())"
        );
        $comment_stmt->bind_param("iis", $user_id, $post_id, $comment_text);
        
        if ($comment_stmt->execute()) {
            $new_comment_id = $conn->insert_id;
            $comment_stmt->close();

            // Notify post owner
            notifyPostComment($user_id, $post_id, $new_comment_id, $conn);

            $comments_count = getCommentCount($post_id, $conn);
            $user_info = getUserInfo($user_id, $conn);

            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'action' => 'add',
                    'comments_count' => $comments_count,
                    'comment' => [
                        'id' => $new_comment_id,
                        'user_id' => $user_id,
                        'post_id' => $post_id,
                        'comment_text' => htmlspecialchars($comment_text),
                        'created_at' => date('Y-m-d H:i:s'),
                        'full_name' => htmlspecialchars($user_info['full_name'] ?? $_SESSION['full_name'] ?? 'User'),
                        'username' => htmlspecialchars($user_info['username'] ?? $_SESSION['username'] ?? 'user'),
                        'profile_picture' => $user_info['profile_picture'] ?? 'default-profile.jpg'
                    ]
                ]);
                exit();
            }
        } else {
            $comment_stmt->close();
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Failed to save comment']);
                exit();
            }
        }
    } elseif ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Comment cannot be empty']);
        exit();
    }
}

// DELETE COMMENT
elseif ($action === 'delete') {
    $comment_id = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : (isset($_GET['comment_id']) ? (int)$_GET['comment_id'] : 0);
    
    if ($comment_id) {
        // Verify comment belongs to current user
        $verify_comment = $conn->prepare("SELECT user_id FROM comments WHERE id = ?");
        $verify_comment->bind_param("i", $comment_id);
        $verify_comment->execute();
        $verify_comment_result = $verify_comment->get_result();
        $comment_data = $verify_comment_result->fetch_assoc();
        $verify_comment->close();
        
        // Only allow deletion by comment owner
        if ($comment_data && (int)$comment_data['user_id'] === $user_id) {
            $delete_stmt = $conn->prepare("DELETE FROM comments WHERE id = ?");
            $delete_stmt->bind_param("i", $comment_id);
            $delete_stmt->execute();
            $delete_stmt->close();

            $comments_count = getCommentCount($post_id, $conn);

            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'action' => 'delete',
                    'comment_id' => $comment_id,
                    'comments_count' => $comments_count
                ]);
                exit();
            }
        } elseif ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Permission denied']);
            exit();
        }
    }
}

// Non-AJAX redirect
$referer = $_SERVER['HTTP_REFERER'] ?? '';
if (!empty($referer) && strpos($referer, 'comment_actions.php') === false) {
    header("Location: " . $referer);
} else {
    header("Location: ../post.php?id=" . $post_id . "&t=" . time());
}
exit();
?>
