<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

require 'config/database.php';
require 'includes/helpers.php';

$current_user_id = (int)$_SESSION['user_id'];

// Get posts from followed users + own posts
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

// Get current user info
$current_user = getUserInfo($current_user_id, $conn);
$current_user_stats = getUserStats($current_user_id, $conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home Feed - Pixora</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .feed-container {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 30px;
            max-width: 1200px;
            margin: 50px auto;
            padding: 0 20px;
        }

        .feed-main {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        .feed-post {
            background: white;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            border: 1px solid var(--border);
        }

        .feed-post:hover {
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-light);
        }

        .post-header {
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid var(--border);
        }

        .post-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary);
            cursor: pointer;
        }

        .post-user-info {
            flex: 1;
        }

        .post-user-name {
            font-weight: 700;
            color: var(--dark);
            cursor: pointer;
            transition: color 0.2s;
            font-size: 15px;
        }

        .post-user-name:hover {
            color: var(--primary);
        }

        .post-user-username {
            color: var(--gray);
            font-size: 13px;
        }

        .post-time {
            color: var(--gray);
            font-size: 12px;
            margin-top: 2px;
        }

        .post-image {
            width: 100%;
            max-height: 520px;
            object-fit: cover;
            background: var(--light);
            cursor: pointer;
            display: block;
        }

        .post-content {
            padding: 20px;
        }

        .post-caption {
            color: var(--dark);
            line-height: 1.6;
            margin-bottom: 15px;
            cursor: pointer;
            font-size: 14px;
        }

        .post-caption:hover {
            color: var(--primary);
        }

        .post-stats {
            display: flex;
            gap: 25px;
            padding: 12px 0;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            margin-bottom: 15px;
        }

        .stat {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-size: 13px;
        }

        .stat-icon {
            font-size: 18px;
        }

        .stat-text {
            font-weight: 700;
            color: var(--dark);
        }

        .post-actions {
            display: flex;
            gap: 10px;
        }

        .action-btn {
            flex: 1;
            padding: 10px 14px;
            background: var(--light);
            border: 1.5px solid var(--border);
            border-radius: 10px;
            cursor: pointer;
            font-weight: 700;
            font-size: 13px;
            transition: all 0.2s ease;
            text-decoration: none;
            text-align: center;
            color: var(--dark);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .action-btn:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        .action-btn.liked {
            background: rgba(236, 72, 153, 0.12);
            border-color: var(--secondary);
            color: var(--secondary);
        }

        /* SIDEBAR */
        .feed-sidebar {
            position: sticky;
            top: 80px;
            height: fit-content;
        }

        .sidebar-card {
            background: white;
            border-radius: 18px;
            padding: 24px;
            box-shadow: var(--shadow);
            margin-bottom: 20px;
            border: 1px solid var(--border);
        }

        .sidebar-title {
            font-size: 16px;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .profile-summary {
            text-align: center;
        }

        .profile-summary-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 15px;
            border: 4px solid var(--primary);
            cursor: pointer;
        }

        .profile-summary-name {
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 4px;
            font-size: 16px;
        }

        .profile-summary-username {
            color: var(--gray);
            font-size: 13px;
            margin-bottom: 15px;
        }

        .stats-row {
            display: flex;
            justify-content: space-around;
            padding: 15px 0;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            margin-bottom: 18px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 18px;
            font-weight: 800;
            color: var(--primary);
        }

        .stat-label {
            font-size: 11px;
            color: var(--gray);
            text-transform: uppercase;
            font-weight: 700;
        }

        .sidebar-button {
            display: block;
            width: 100%;
            padding: 11px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 700;
            transition: all 0.2s ease;
            text-decoration: none;
            text-align: center;
            font-size: 13px;
            margin-top: 10px;
        }

        .sidebar-button:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .sidebar-button.secondary {
            background: var(--light);
            color: var(--dark);
            border: 1px solid var(--border);
        }

        .sidebar-button.secondary:hover {
            background: var(--border);
            color: var(--dark);
        }

        .empty-feed {
            text-align: center;
            padding: 70px 20px;
            background: white;
            border-radius: 18px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .empty-icon {
            font-size: 70px;
            margin-bottom: 20px;
        }

        .empty-text {
            font-size: 22px;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 8px;
        }

        .empty-subtext {
            color: var(--gray);
            margin-bottom: 25px;
            font-size: 14px;
        }

        .empty-cta {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s;
        }

        .empty-cta:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        @media (max-width: 1024px) {
            .feed-container {
                grid-template-columns: 1fr;
            }

            .feed-sidebar {
                position: static;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <!-- FEED CONTAINER -->
    <div class="feed-container">
        <!-- MAIN FEED -->
        <div class="feed-main">
            <?php if (count($feed_posts) > 0): ?>
                <?php foreach ($feed_posts as $post): ?>
                    <?php 
                        $is_liked = userLikesPost($current_user_id, $post['id'], $conn);
                        $post_pic = 'assets/images/default-profile.jpg';
                        if (!empty($post['profile_picture']) && file_exists("uploads/profile/" . $post['profile_picture']) && strpos($post['profile_picture'], 'default') === false) {
                            $post_pic = "uploads/profile/" . $post['profile_picture'];
                        }
                        $feed_img = $post['image_path'];
                        if (strpos($feed_img, 'uploads/') !== 0 && !file_exists($feed_img)) {
                            $feed_img = 'uploads/posts/' . $feed_img;
                        }
                    ?>
                    <div class="feed-post" id="post-card-<?php echo $post['id']; ?>">
                        <!-- POST HEADER -->
                        <div class="post-header">
                            <img src="<?php echo htmlspecialchars($post_pic); ?>" alt="Avatar" class="post-avatar" 
                                 onclick="window.location.href='profile.php?id=<?php echo $post['user_id']; ?>'">
                            
                            <div class="post-user-info">
                                <div class="post-user-name" onclick="window.location.href='profile.php?id=<?php echo $post['user_id']; ?>'">
                                    <?php echo htmlspecialchars($post['full_name']); ?>
                                </div>
                                <div class="post-user-username">
                                    @<?php echo htmlspecialchars($post['username']); ?>
                                </div>
                                <div class="post-time">
                                    <?php echo formatDate($post['created_at']); ?>
                                </div>
                            </div>
                        </div>

                        <!-- POST IMAGE -->
                        <img src="<?php echo htmlspecialchars($feed_img); ?>" 
                             alt="Post" class="post-image"
                             onclick="window.location.href='post.php?id=<?php echo $post['id']; ?>'">

                        <!-- POST CONTENT -->
                        <div class="post-content">
                            <div class="post-caption" onclick="window.location.href='post.php?id=<?php echo $post['id']; ?>'">
                                <?php echo nl2br(htmlspecialchars($post['caption'])); ?>
                            </div>

                            <!-- POST STATS -->
                            <div class="post-stats">
                                <div class="stat" onclick="window.location.href='post.php?id=<?php echo $post['id']; ?>'">
                                    <div class="stat-icon">❤️</div>
                                    <div class="stat-text"><span id="likes-count-<?php echo $post['id']; ?>"><?php echo $post['likes_count']; ?></span> Likes</div>
                                </div>
                                <div class="stat" onclick="window.location.href='post.php?id=<?php echo $post['id']; ?>'">
                                    <div class="stat-icon">💬</div>
                                    <div class="stat-text"><?php echo $post['comments_count'] ?? 0; ?> Comments</div>
                                </div>
                            </div>

                            <!-- POST ACTIONS (DIRECT LIKE, COMMENT, VIEW) -->
                            <div class="post-actions">
                                <button type="button" 
                                        class="action-btn feed-like-btn <?php echo $is_liked ? 'liked' : ''; ?>" 
                                        id="feed-like-btn-<?php echo $post['id']; ?>"
                                        data-post-id="<?php echo $post['id']; ?>"
                                        data-liked="<?php echo $is_liked ? '1' : '0'; ?>"
                                        onclick="handleFeedLike(event, <?php echo $post['id']; ?>, this)">
                                    <span class="like-btn-icon"><?php echo $is_liked ? '❤️' : '🤍'; ?></span>
                                    <span class="like-btn-text"><?php echo $is_liked ? 'Liked' : 'Like'; ?></span>
                                </button>
                                <a href="post.php?id=<?php echo $post['id']; ?>" class="action-btn">
                                    💬 Comment
                                </a>
                                <a href="post.php?id=<?php echo $post['id']; ?>" class="action-btn">
                                    👁️ View
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-feed">
                    <div class="empty-icon">📭</div>
                    <div class="empty-text">Your feed is empty</div>
                    <p class="empty-subtext">Follow other users or create a post to see content here!</p>
                    <a href="discover.php" class="empty-cta">Discover Users</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- SIDEBAR -->
        <div class="feed-sidebar">
            <!-- PROFILE CARD -->
            <div class="sidebar-card">
                <div class="profile-summary">
                    <img src="<?php 
                        $pic = 'assets/images/default-profile.jpg';
                        if (!empty($current_user['profile_picture']) && file_exists("uploads/profile/" . $current_user['profile_picture']) && strpos($current_user['profile_picture'], 'default') === false) {
                            $pic = "uploads/profile/" . $current_user['profile_picture'];
                        }
                        echo htmlspecialchars($pic);
                    ?>" alt="Avatar" class="profile-summary-avatar" onclick="window.location.href='profile.php'">
                    
                    <div class="profile-summary-name">
                        <?php echo htmlspecialchars($current_user['full_name']); ?>
                    </div>
                    <div class="profile-summary-username">
                        @<?php echo htmlspecialchars($current_user['username']); ?>
                    </div>
                </div>

                <div class="stats-row">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $current_user_stats['posts']; ?></div>
                        <div class="stat-label">Posts</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $current_user_stats['followers']; ?></div>
                        <div class="stat-label">Followers</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $current_user_stats['following']; ?></div>
                        <div class="stat-label">Following</div>
                    </div>
                </div>

                <a href="profile.php" class="sidebar-button">👤 View Profile</a>
                <a href="settings.php" class="sidebar-button secondary">⚙️ User Settings</a>
            </div>

            <!-- QUICK ACTIONS -->
            <div class="sidebar-card">
                <div class="sidebar-title">Quick Links</div>
                <a href="create_post.php" class="sidebar-button">📸 Create Post</a>
                <a href="search.php" class="sidebar-button secondary">🔍 Search</a>
                <a href="discover.php" class="sidebar-button secondary">🌟 Discover Users</a>
                <a href="trending.php" class="sidebar-button secondary">📈 Trending</a>
            </div>
        </div>
    </div>

    <!-- FOOTER -->
    <footer style="background: var(--dark); color: white; padding: 40px 20px; text-align: center; margin-top: 60px;">
        <div class="container">
            <p style="margin-bottom: 10px;">© 2024 <strong>Pixora</strong> - Your Premium Social Media Platform</p>
            <p style="color: var(--gray); font-size: 13px;">Built with ❤️ using PHP & MySQL</p>
        </div>
    </footer>

    <!-- INTERACTIVE SCRIPT -->
    <script>
        function handleFeedLike(event, postId, btn) {
            event.stopPropagation();
            const isLiked = btn.getAttribute('data-liked') === '1';
            const nextAction = isLiked ? 'unlike' : 'like';

            // Optimistic UI update
            const newLiked = !isLiked;
            btn.setAttribute('data-liked', newLiked ? '1' : '0');
            const icon = btn.querySelector('.like-btn-icon');
            const label = btn.querySelector('.like-btn-text');
            const countEl = document.getElementById(`likes-count-${postId}`);

            if (newLiked) {
                btn.classList.add('liked');
                if (icon) icon.textContent = '❤️';
                if (label) label.textContent = 'Liked';
            } else {
                btn.classList.remove('liked');
                if (icon) icon.textContent = '🤍';
                if (label) label.textContent = 'Like';
            }

            fetch(`includes/like_actions.php?ajax=1&post_id=${postId}&action=${nextAction}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    btn.setAttribute('data-liked', data.liked ? '1' : '0');
                    if (countEl) countEl.textContent = data.likes_count;
                    if (data.liked) {
                        btn.classList.add('liked');
                        if (icon) icon.textContent = '❤️';
                        if (label) label.textContent = 'Liked';
                    } else {
                        btn.classList.remove('liked');
                        if (icon) icon.textContent = '🤍';
                        if (label) label.textContent = 'Like';
                    }
                } else {
                    window.location.href = `includes/like_actions.php?post_id=${postId}&action=${nextAction}`;
                }
            })
            .catch(() => {
                window.location.href = `includes/like_actions.php?post_id=${postId}&action=${nextAction}`;
            });
        }
    </script>
</body>
</html>
