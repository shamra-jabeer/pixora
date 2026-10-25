<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

require 'config/database.php';
require 'includes/helpers.php';

$current_user_id = $_SESSION['user_id'];
$current_user = getUserInfo($current_user_id, $conn);

// Get home feed (posts from people you follow + your own)
$feed_stmt = $conn->prepare("
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
    WHERE p.user_id IN (
        SELECT following_id FROM follows WHERE follower_id = ?
    )
    OR p.user_id = ?
    ORDER BY p.created_at DESC
    LIMIT 50
");
$feed_stmt->bind_param("ii", $current_user_id, $current_user_id);
$feed_stmt->execute();
$feed_result = $feed_stmt->get_result();
$feed_posts = $feed_result->fetch_all(MYSQLI_ASSOC);
$feed_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - Pixora</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .feed-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            padding: 40px 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .feed-card {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow-md);
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }

        .feed-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
        }

        .feed-card-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
            background: var(--light);
        }

        .feed-card-info {
            padding: 15px;
        }

        .feed-card-author {
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 4px;
            font-size: 14px;
        }

        .feed-card-username {
            font-size: 12px;
            color: var(--gray);
            margin-bottom: 10px;
        }

        .feed-card-stats {
            display: flex;
            gap: 15px;
            font-size: 12px;
        }

        .feed-card-stat {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .no-feed {
            text-align: center;
            padding: 80px 20px;
            color: var(--gray);
        }

        .no-feed h2 {
            color: var(--dark);
            margin-bottom: 20px;
        }

        .feed-title {
            text-align: center;
            color: var(--primary);
            font-size: 32px;
            margin: 40px 0 30px;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <!-- FEED -->
    <h1 class="feed-title">📱 Your Feed</h1>

    <?php if (count($feed_posts) > 0): ?>
        <div class="feed-container">
            <?php foreach ($feed_posts as $post): ?>
                <div class="feed-card" onclick="window.location='post.php?id=<?php echo $post['id']; ?>'">
                    <img src="<?php $p_img = $post['image_path']; if (strpos($p_img, 'uploads/') !== 0 && !file_exists($p_img)) { $p_img = 'uploads/posts/' . $p_img; } echo htmlspecialchars($p_img); ?>" alt="Post" class="feed-card-image">
                    <div class="feed-card-info">
                        <div class="feed-card-author"><?php echo htmlspecialchars($post['full_name']); ?></div>
                        <div class="feed-card-username">@<?php echo htmlspecialchars($post['username']); ?></div>
                        <div class="feed-card-stats">
                            <div class="feed-card-stat">
                                <span>❤️</span>
                                <span><?php echo $post['likes_count'] ?? 0; ?></span>
                            </div>
                            <div class="feed-card-stat">
                                <span>💬</span>
                                <span><?php echo $post['comments_count'] ?? 0; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="no-feed">
            <h2>🌟 Welcome to Pixora!</h2>
            <p>No posts in your feed yet.</p>
            <p style="margin-top: 10px;">Follow people to see their posts!</p>
            <a href="discover.php" class="btn btn-primary" style="margin-top: 20px;">Discover Users</a>
        </div>
    <?php endif; ?>

    <!-- FOOTER -->
    <footer style="background: var(--dark); color: white; padding: 40px 20px; text-align: center; margin-top: 60px;">
        <div class="container">
            <p style="margin-bottom: 10px;">© 2024 <strong>Pixora</strong> - Your Premium Social Media Platform</p>
            <p style="color: var(--gray); font-size: 13px;">Built with ❤️ using PHP & MySQL</p>
        </div>
    </footer>

</body>
</html>