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
$action = isset($_POST['action']) ? trim($_POST['action']) : (isset($_GET['action']) ? trim($_GET['action']) : '');

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

// Check if already liked
$check_stmt = $conn->prepare("SELECT id FROM likes WHERE user_id = ? AND post_id = ?");
$check_stmt->bind_param("ii", $user_id, $post_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();
$is_already_liked = ($check_result->num_rows > 0);
$check_stmt->close();

// Determine actual action (support toggle if action not specified)
if (empty($action) || $action === 'toggle') {
    $action = $is_already_liked ? 'unlike' : 'like';
}

$liked = false;

// Handle like/unlike
if ($action === 'like') {
    if (!$is_already_liked) {
        $like_stmt = $conn->prepare("INSERT INTO likes (user_id, post_id, created_at) VALUES (?, ?, NOW())");
        $like_stmt->bind_param("ii", $user_id, $post_id);
        $like_stmt->execute();
        $like_stmt->close();

        // Notify post owner
        notifyPostLike($user_id, $post_id, $conn);
    }
    $liked = true;
} elseif ($action === 'unlike') {
    $unlike_stmt = $conn->prepare("DELETE FROM likes WHERE user_id = ? AND post_id = ?");
    $unlike_stmt->bind_param("ii", $user_id, $post_id);
    $unlike_stmt->execute();
    $unlike_stmt->close();
    $liked = false;
}

// Get updated like count
$likes_count = getLikeCount($post_id, $conn);

if ($is_ajax) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'liked' => $liked,
        'action' => $action,
        'likes_count' => $likes_count
    ]);
    exit();
}

// Redirect back to referring page or post
$referer = $_SERVER['HTTP_REFERER'] ?? '';
if (!empty($referer) && strpos($referer, 'like_actions.php') === false) {
    header("Location: " . $referer);
} else {
    header("Location: ../post.php?id=" . $post_id . "&t=" . time());
}
exit();
?>
