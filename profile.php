<?php
// View User Profile
// pixora/profile.php

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

// Include database connection and helpers
include 'config/database.php';
include 'includes/helpers.php';

$current_user_id = $_SESSION['user_id'];

// Get profile user ID from URL or use current user
$profile_user_id = isset($_GET['id']) ? intval($_GET['id']) : $current_user_id;

// Validate profile user ID
if ($profile_user_id <= 0) {
    header("Location: index.php");
    exit();
}

// Get profile user info
$profile_user = getUserInfo($profile_user_id, $conn);

if (!$profile_user) {
    // $conn auto-closes on script end
    die("User not found");
}

// Get user stats
$stats = getUserStats($profile_user_id, $conn);

// Check if current user is following this user
$is_following = isFollowing($current_user_id, $profile_user_id, $conn);

// Get user posts
$posts = getUserPosts($profile_user_id, $conn);

// Get profile picture path
$default_pic = 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22150%22 height=%22150%22%3E%3Crect fill=%22%23cccccc%22 width=%22150%22 height=%22150%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%23666666%22 font-family=%22Arial%22 font-size=%2216%22%3EUser%3C/text%3E%3C/svg%3E';
$profile_picture = ($profile_user['profile_picture'] && strpos($profile_user['profile_picture'], 'default') === false) ? 
    "uploads/profile/" . htmlspecialchars($profile_user['profile_picture']) : 
    $default_pic;

// $conn auto-closes on script end
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($profile_user['username']); ?> - Pixora</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .navbar {
            background: white;
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .navbar-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .navbar-logo {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color);
            text-decoration: none;
        }

        .navbar-links {
            display: flex;
            gap: 30px;
        }

        .navbar-links a {
            color: var(--text-dark);
            font-weight: 500;
            transition: color 0.3s;
        }

        .navbar-links a:hover {
            color: var(--primary-color);
        }

        .profile-header {
            background: white;
            padding: 40px 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .profile-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 50px;
            align-items: start;
        }

        .profile-pic-section {
            text-align: center;
        }

        .profile-pic {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary-color);
            margin-bottom: 20px;
        }

        .profile-info-section h1 {
            font-size: 28px;
            margin-bottom: 5px;
        }

        .profile-info-section .username {
            color: var(--text-light);
            font-size: 16px;
            margin-bottom: 15px;
        }

        .profile-info-section .bio {
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .profile-stats {
            display: flex;
            gap: 40px;
            margin-bottom: 20px;
        }

        .stat {
            text-align: center;
        }

        .stat-number {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color);
        }

        .stat-label {
            color: var(--text-light);
            font-size: 14px;
            margin-top: 5px;
        }

        .profile-buttons {
            display: flex;
            gap: 10px;
        }

        .profile-buttons .btn {
            flex: 1;
        }

        .posts-section {
            background: white;
            padding: 40px 20px;
        }

        .posts-title {
            text-align: center;
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 30px;
        }

        .posts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .post-card {
            position: relative;
            overflow: hidden;
            border-radius: 8px;
            aspect-ratio: 1;
            background: var(--bg-light);
            cursor: pointer;
            transition: transform 0.3s;
        }

        .post-card:hover {
            transform: scale(1.05);
        }

        .post-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .post-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .post-card:hover .post-overlay {
            opacity: 1;
        }

        .post-stat {
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
        }

        .post-stat-number {
            font-size: 20px;
            font-weight: 700;
        }

        .no-posts {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-light);
        }

        .no-posts p {
            font-size: 18px;
        }

        @media (max-width: 768px) {
            .profile-container {
                grid-template-columns: 1fr;
                gap: 30px;
            }

            .profile-stats {
                gap: 20px;
            }

            .posts-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <!-- Profile Header -->
    <div class="profile-header">
        <div class="profile-container">
            <!-- Profile Picture -->
            <div class="profile-pic-section">
                <img src="<?php echo $profile_picture; ?>" alt="<?php echo htmlspecialchars($profile_user['username']); ?>" class="profile-pic">
            </div>

            <!-- Profile Info -->
            <div class="profile-info-section">
                <h1><?php echo htmlspecialchars($profile_user['full_name']); ?></h1>
                <p class="username">@<?php echo htmlspecialchars($profile_user['username']); ?></p>
                
                <?php if ($profile_user['bio']): ?>
                    <p class="bio"><?php echo nl2br(htmlspecialchars($profile_user['bio'])); ?></p>
                <?php endif; ?>

                <!-- Stats -->
                <div class="profile-stats">
                    <div class="stat">
                        <div class="stat-number"><?php echo $stats['posts']; ?></div>
                        <div class="stat-label">Posts</div>
                    </div>
                    <div class="stat">
                        <div class="stat-number"><?php echo $stats['followers']; ?></div>
                        <div class="stat-label">Followers</div>
                    </div>
                    <div class="stat">
                        <div class="stat-number"><?php echo $stats['following']; ?></div>
                        <div class="stat-label">Following</div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="profile-buttons">
                    <?php if ($current_user_id === $profile_user_id): ?>
                        <a href="settings.php" class="btn btn-primary" style="flex: 1;">⚙️ Settings</a>
                        <a href="edit_profile.php" class="btn btn-secondary" style="flex: 1; border: 2px solid var(--primary-color);">Edit Profile</a>
                    <?php else: ?>
                        <a href="chat.php?user_id=<?php echo $profile_user_id; ?>" class="btn btn-primary" style="flex: 1;">💬 Message</a>
                        <?php if ($is_following): ?>
                            <form method="POST" action="includes/follow_actions.php" style="flex: 1;">
                                <input type="hidden" name="action" value="unfollow">
                                <input type="hidden" name="user_id" value="<?php echo $profile_user_id; ?>">
                                <button type="submit" class="btn btn-secondary" style="width: 100%;">Unfollow</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="includes/follow_actions.php" style="flex: 1;">
                                <input type="hidden" name="action" value="follow">
                                <input type="hidden" name="user_id" value="<?php echo $profile_user_id; ?>">
                                <button type="submit" class="btn btn-primary" style="width: 100%;">Follow</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Posts Section -->
    <div class="posts-section">
        <div class="posts-title">Posts</div>
        
        <?php if (count($posts) > 0): ?>
            <div class="posts-grid">
                <?php foreach ($posts as $post): ?>
                    <div class="post-card" onclick="window.location.href='post.php?id=<?php echo $post['id']; ?>'" style="cursor: pointer;">
                        <img src="<?php $img_src = $post['image']; if (strpos($img_src, 'uploads/') !== 0 && !file_exists($img_src)) { $img_src = 'uploads/posts/' . $img_src; } echo htmlspecialchars($img_src); ?>" alt="Post">
                        <div class="post-overlay">
                            <div class="post-stat">
                                <span>❤️</span>
                                <span class="post-stat-number"><?php echo $post['likes'] ?? 0; ?></span>
                            </div>
                            <div class="post-stat">
                                <span>💬</span>
                                <span class="post-stat-number"><?php echo $post['comments'] ?? 0; ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-posts">
                <p>No posts yet</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>