<?php
/**
 * Helper functions for Pixora
 * STEP 6 VERSION - Complete with likes and comments
 */

/**
 * Get user statistics (posts, followers, following)
 */
function getUserStats($user_id, $conn) {
    $stats = ['posts' => 0, 'followers' => 0, 'following' => 0];

    // Get posts count
    $posts_query = $conn->prepare("SELECT COUNT(*) as count FROM posts WHERE user_id = ?");
    $posts_query->bind_param("i", $user_id);
    $posts_query->execute();
    $posts_result = $posts_query->get_result();
    $stats['posts'] = $posts_result->fetch_assoc()['count'];
    $posts_query->close();

    // Get followers count
    $followers_query = $conn->prepare("SELECT COUNT(*) as count FROM follows WHERE following_id = ?");
    $followers_query->bind_param("i", $user_id);
    $followers_query->execute();
    $followers_result = $followers_query->get_result();
    $stats['followers'] = $followers_result->fetch_assoc()['count'];
    $followers_query->close();

    // Get following count
    $following_query = $conn->prepare("SELECT COUNT(*) as count FROM follows WHERE follower_id = ?");
    $following_query->bind_param("i", $user_id);
    $following_query->execute();
    $following_result = $following_query->get_result();
    $stats['following'] = $following_result->fetch_assoc()['count'];
    $following_query->close();

    return $stats;
}

/**
 * Get user information by ID
 */
function getUserInfo($user_id, $conn) {
    $stmt = $conn->prepare("SELECT id, full_name, username, email, bio, profile_picture, created_at, is_private FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    return $user;
}

/**
 * Get all posts by a user
 * Uses 'image' column from database, aliases as 'image_path'
 */
function getUserPosts($user_id, $conn, $limit = 12) {
    $stmt = $conn->prepare("
        SELECT p.id, p.image, p.caption, p.created_at, 
               COALESCE((SELECT COUNT(*) FROM likes WHERE post_id = p.id), 0) as likes,
               COALESCE((SELECT COUNT(*) FROM comments WHERE post_id = p.id), 0) as comments
        FROM posts p
        WHERE p.user_id = ?
        ORDER BY p.created_at DESC
        LIMIT ?
    ");
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $posts = [];
    while ($row = $result->fetch_assoc()) {
        $posts[] = $row;
    }
    
    $stmt->close();
    return $posts;
}
/**
 * Get a single post by ID with user info
 */
function getPostById($post_id, $conn) {
    $stmt = $conn->prepare("
        SELECT 
            p.id,
            p.user_id,
            p.image as image_path,
            p.caption,
            p.created_at,
            u.full_name,
            u.username,
            u.profile_picture,
            (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as likes_count,
            (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comments_count
        FROM posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.id = ?
    ");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $post = $result->fetch_assoc();
    $stmt->close();
    return $post;
}

/**
 * Check if user A follows user B
 */
function isFollowing($follower_id, $following_id, $conn) {
    $stmt = $conn->prepare("SELECT id FROM follows WHERE follower_id = ? AND following_id = ?");
    $stmt->bind_param("ii", $follower_id, $following_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $is_following = $result->num_rows > 0;
    $stmt->close();
    return $is_following;
}

/**
 * Validate image file
 */
function validateImage($file) {
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
    $max_size = 5 * 1024 * 1024; // 5MB

    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    if ($file['size'] > $max_size) {
        return false;
    }

    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_ext, $allowed_extensions)) {
        return false;
    }

    return true;
}

/**
 * Upload profile picture
 */
function uploadProfilePicture($file, $user_id) {
    $upload_dir = 'uploads/profile/';

    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'profile_' . $user_id . '_' . time() . '.' . $file_ext;
    $file_path = $upload_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        return $filename;
    }

    return null;
}

/**
 * Upload post image
 */
function uploadPostImage($file, $user_id) {
    $upload_dir = 'uploads/posts/';

    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'post_' . $user_id . '_' . time() . '_' . uniqid() . '.' . $file_ext;
    $file_path = $upload_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        return $filename;
    }

    return null;
}

/**
 * Delete profile picture file
 */
function deleteProfilePicture($filename) {
    $file_path = 'uploads/profile/' . $filename;
    if (file_exists($file_path) && $filename !== 'default-profile.jpg') {
        unlink($file_path);
    }
}

/**
 * Delete post image file
 */
function deletePostImage($filename) {
    $file_path = 'uploads/posts/' . $filename;
    if (file_exists($file_path)) {
        unlink($file_path);
    }
}

/**
 * Format date to "X days ago" format
 */
function formatDate($date_string) {
    $date = strtotime($date_string);
    $now = time();
    $seconds = $now - $date;

    if ($seconds < 60) {
        return "just now";
    }

    $minutes = floor($seconds / 60);
    if ($minutes < 60) {
        return $minutes . " minute" . ($minutes > 1 ? "s" : "") . " ago";
    }

    $hours = floor($seconds / 3600);
    if ($hours < 24) {
        return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
    }

    $days = floor($seconds / 86400);
    if ($days < 7) {
        return $days . " day" . ($days > 1 ? "s" : "") . " ago";
    }

    $weeks = floor($days / 7);
    if ($weeks < 4) {
        return $weeks . " week" . ($weeks > 1 ? "s" : "") . " ago";
    }

    $months = floor($days / 30);
    if ($months < 12) {
        return $months . " month" . ($months > 1 ? "s" : "") . " ago";
    }

    $years = floor($days / 365);
    return $years . " year" . ($years > 1 ? "s" : "") . " ago";
}

/**
 * Sanitize HTML output
 */
function sanitize($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Like a post
 */
function likePost($user_id, $post_id, $conn) {
    // Check if already liked
    $check = $conn->prepare("SELECT id FROM likes WHERE user_id = ? AND post_id = ?");
    $check->bind_param("ii", $user_id, $post_id);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows === 0) {
        $stmt = $conn->prepare("INSERT INTO likes (user_id, post_id, created_at) VALUES (?, ?, NOW())");
        $stmt->bind_param("ii", $user_id, $post_id);
        return $stmt->execute();
    }
    $check->close();
    return false;
}

/**
 * Unlike a post
 */
function unlikePost($user_id, $post_id, $conn) {
    $stmt = $conn->prepare("DELETE FROM likes WHERE user_id = ? AND post_id = ?");
    $stmt->bind_param("ii", $user_id, $post_id);
    return $stmt->execute();
}

/**
 * Check if user likes a post
 */

/**
 * Add a comment to a post
 */
function addComment($user_id, $post_id, $comment, $conn) {
    $comment = trim($comment);
    
    if (empty($comment) || strlen($comment) > 500) {
        return false;
    }
    
    $stmt = $conn->prepare(
        "INSERT INTO comments (user_id, post_id, comment_text, created_at) VALUES (?, ?, ?, NOW())"
    );
    $stmt->bind_param("iis", $user_id, $post_id, $comment);
    return $stmt->execute();
}

/**
 * Get all comments on a post
 */
function getPostComments($post_id, $conn) {
    $stmt = $conn->prepare("
        SELECT 
            c.id,
            c.user_id,
            c.comment_text,
            c.created_at,
            u.full_name,
            u.username,
            u.profile_picture
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.post_id = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $comments = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $comments;
}

/**
 * Delete a comment
 */
function deleteComment($comment_id, $user_id, $conn) {
    // Verify comment belongs to user
    $verify = $conn->prepare("SELECT user_id FROM comments WHERE id = ?");
    $verify->bind_param("i", $comment_id);
    $verify->execute();
    $result = $verify->get_result();
    $comment = $result->fetch_assoc();
    $verify->close();
    
    if ($comment && $comment['user_id'] === $user_id) {
        $stmt = $conn->prepare("DELETE FROM comments WHERE id = ?");
        $stmt->bind_param("i", $comment_id);
        return $stmt->execute();
    }
    
    return false;
}

/**
 * Get comment count for a post
 */
function getCommentCount($post_id, $conn) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM comments WHERE post_id = ?");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    return $data['count'];
}

/**
 * Get like count for a post
 */
function getLikeCount($post_id, $conn) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM likes WHERE post_id = ?");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    return $data['count'];
}


// ========== CHECK IF USER LIKES POST ==========
function userLikesPost($user_id, $post_id, $conn) {
    $stmt = $conn->prepare("SELECT id FROM likes WHERE user_id = ? AND post_id = ?");
    $stmt->bind_param("ii", $user_id, $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $likes = $result->num_rows > 0;
    $stmt->close();
    return $likes;
}

?>