<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

require '../config/database.php';
require '../includes/helpers.php';
require '../includes/notification_helpers.php';

$user_id = $_SESSION['user_id'];
$target_user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

if (!$target_user_id || !$action) {
    header("Location: ../index.php");
    exit();
}

// Can't follow yourself
if ($user_id === $target_user_id) {
    header("Location: ../profile.php?id=" . $target_user_id);
    exit();
}

if ($action === 'follow') {
    // Check if already following
    $check_stmt = $conn->prepare("SELECT id FROM follows WHERE follower_id = ? AND following_id = ?");
    $check_stmt->bind_param("ii", $user_id, $target_user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        // Add follow
        $follow_stmt = $conn->prepare("INSERT INTO follows (follower_id, following_id, created_at) VALUES (?, ?, NOW())");
        $follow_stmt->bind_param("ii", $user_id, $target_user_id);
        $follow_stmt->execute();
        $follow_stmt->close();
        
        // Send notification
        notifyUserFollow($user_id, $target_user_id, $conn);
    }
    $check_stmt->close();

} elseif ($action === 'unfollow') {
    // Remove follow
    $unfollow_stmt = $conn->prepare("DELETE FROM follows WHERE follower_id = ? AND following_id = ?");
    $unfollow_stmt->bind_param("ii", $user_id, $target_user_id);
    $unfollow_stmt->execute();
    $unfollow_stmt->close();
}

// Redirect back
$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : "../profile.php?id=" . $target_user_id;
header("Location: " . $referer);
exit();
?>