<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

require '../config/database.php';
require '../includes/helpers.php';

$user_id = (int)$_SESSION['user_id'];
$post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : (isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0);
$action = isset($_POST['action']) ? trim($_POST['action']) : (isset($_GET['action']) ? trim($_GET['action']) : '');

if (!$post_id || !$action) {
    header("Location: ../index.php");
    exit();
}

// Verify post exists and get its details
$verify_stmt = $conn->prepare("SELECT user_id, image FROM posts WHERE id = ?");
$verify_stmt->bind_param("i", $post_id);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();

if ($verify_result->num_rows === 0) {
    $verify_stmt->close();
    header("Location: ../index.php");
    exit();
}

$post = $verify_result->fetch_assoc();
$verify_stmt->close();

// Verify ownership
if ((int)$post['user_id'] !== $user_id) {
    header("Location: ../index.php");
    exit();
}

// DELETE POST
if ($action === 'delete') {
    // Delete image file from disk
    if (!empty($post['image'])) {
        $img = $post['image'];
        $paths_to_try = [
            '../' . $img,
            '../uploads/posts/' . $img,
            $img
        ];
        foreach ($paths_to_try as $p) {
            if (file_exists($p) && !is_dir($p)) {
                @unlink($p);
                break;
            }
        }
    }

    // Delete all notifications for this post and its comments
    $conn->query("DELETE FROM notifications WHERE post_id = $post_id OR comment_id IN (SELECT id FROM comments WHERE post_id = $post_id)");

    // Delete post hashtags
    $conn->query("DELETE FROM post_hashtags WHERE post_id = $post_id");

    // Delete all likes on this post
    $delete_likes = $conn->prepare("DELETE FROM likes WHERE post_id = ?");
    $delete_likes->bind_param("i", $post_id);
    $delete_likes->execute();
    $delete_likes->close();

    // Delete all comments on this post
    $delete_comments = $conn->prepare("DELETE FROM comments WHERE post_id = ?");
    $delete_comments->bind_param("i", $post_id);
    $delete_comments->execute();
    $delete_comments->close();

    // Delete the post
    $delete_post = $conn->prepare("DELETE FROM posts WHERE id = ?");
    $delete_post->bind_param("i", $post_id);
    $delete_post->execute();
    $delete_post->close();

    // Redirect to profile
    header("Location: ../profile.php");
    exit();
}

header("Location: ../index.php");
exit();
?>
