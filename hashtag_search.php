<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

require 'config/database.php';
require 'includes/helpers.php';
require 'includes/hashtag_functions.php';
require 'includes/notification_helpers.php';
require 'includes/message_actions.php';

$current_user_id = $_SESSION['user_id'];
$current_user = getUserInfo($current_user_id, $conn);

// Get hashtag from URL
$hashtag_slug = isset($_GET['tag']) ? sanitize($_GET['tag']) : '';

if (empty($hashtag_slug)) {
    header("Location: trending.php");
    exit();
}

// Get hashtag info
$hashtag = getHashtagBySlug($hashtag_slug, $conn);
if (!$hashtag) {
    header("Location: trending.php");
    exit();
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$posts_per_page = 10;
$offset = ($page - 1) * $posts_per_page;

// Get posts with this hashtag
$posts = getPostsByHashtag($hashtag_slug, $posts_per_page, $offset, $conn);

// Get total count for pagination
$count_stmt = $conn->prepare("
    SELECT COUNT(*) as total FROM posts p
    JOIN post_hashtags ph ON p.id = ph.post_id
    JOIN hashtags h ON ph.hashtag_id = h.id
    WHERE h.slug = ?
");
$count_stmt->bind_param("s", $hashtag_slug);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$count_data = $count_result->fetch_assoc();
$total_posts = $count_data['total'];
$total_pages = ceil($total_posts / $posts_per_page);
$count_stmt->close();

// Get unread counts for navbar
$unread_notif_count = getUnreadNotificationCount($current_user_id, $conn);
$unread_msg_count = getUnreadMessageCount($current_user_id, $conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>#<?php echo htmlspecialchars($hashtag['name']); ?> - Pixora</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .hashtag-container {
            max-width: 600px;
            margin: 60px auto;
            padding: 0 20px;
        }

        .hashtag-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-md);
            text-align: center;
        }

        .hashtag-header h1 {
            margin: 0;
            font-size: 36px;
        }

        .hashtag-stats {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        .stat {
            text-align: center;
        }

        .stat-number {
            font-size: 24px;
            font-weight: 700;
            display: block;
        }

        .stat-label {
            font-size: 12px;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .hashtag-breadcrumb {
            margin-bottom: 20px;
        }

        .hashtag-breadcrumb a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .hashtag-breadcrumb a:hover {
            color: var(--secondary);
            text-decoration: underline;
        }

        .posts-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray);
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
        }

        .empty-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .empty-text {
            font-size: 16px;
            font-weight: 600;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
            padding: 20px 0;
        }

        .pagination a, .pagination span {
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid var(--light);
            color: var(--primary);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .pagination a:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .pagination span.current {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        @media (max-width: 768px) {
            .hashtag-container {
                margin: 50px auto 0;
            }

            .hashtag-header {
                padding: 20px;
            }

            .hashtag-header h1 {
                font-size: 28px;
            }

            .hashtag-stats {
                gap: 20px;
            }

            .stat-number {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <!-- HASHTAG CONTAINER -->
    <div class="hashtag-container">
        <!-- BREADCRUMB -->
        <div class="hashtag-breadcrumb">
            <a href="trending.php">← Back to Trending</a>
        </div>

        <!-- HEADER -->
        <div class="hashtag-header">
            <h1>#<?php echo htmlspecialchars($hashtag['name']); ?></h1>
            <div class="hashtag-stats">
                <div class="stat">
                    <span class="stat-number"><?php echo $hashtag['usage_count']; ?></span>
                    <span class="stat-label">Posts</span>
                </div>
                <div class="stat">
                    <span class="stat-number">📈</span>
                    <span class="stat-label">Trending</span>
                </div>
            </div>
        </div>

        <!-- POSTS -->
        <div class="posts-container">
            <?php if (count($posts) === 0): ?>
                <div class="empty-state">
                    <div class="empty-icon">📭</div>
                    <div class="empty-text">No posts with this hashtag yet</div>
                </div>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <div class="post-card">
                        <!-- POST HEADER -->
                        <div class="post-header">
                            <div class="post-user-info">
                                <img src="<?php 
                                    $pic = "https://via.placeholder.com/150/cccccc/666666?text=User";
                                    if (!empty($post['profile_picture']) && file_exists("uploads/profile/" . $post['profile_picture'])) {
                                        $pic = "uploads/profile/" . $post['profile_picture'];
                                    }
                                    echo htmlspecialchars($pic);
                                ?>" alt="Avatar" class="post-avatar">
                                <div class="user-details">
                                    <a href="profile.php?user_id=<?php echo $post['user_id']; ?>" class="post-username">
                                        <strong><?php echo htmlspecialchars($post['full_name']); ?></strong>
                                    </a>
                                    <a href="profile.php?user_id=<?php echo $post['user_id']; ?>" class="post-handle">
                                        @<?php echo htmlspecialchars($post['username']); ?>
                                    </a>
                                </div>
                            </div>
                            <div class="post-time">
                                <?php echo formatDate($post['created_at']); ?>
                            </div>
                        </div>

                        <!-- POST CONTENT -->
                        <div class="post-content">
                            <?php if (!empty($post['image'])): ?>
                                <?php $h_img = $post['image']; if (strpos($h_img, 'uploads/') !== 0 && !file_exists($h_img)) { $h_img = 'uploads/posts/' . $h_img; } ?><img src="<?php echo htmlspecialchars($h_img); ?>" alt="Post" class="post-image">
                            <?php endif; ?>
                            
                            <div class="post-caption">
                                <?php echo linkifyHashtags($post['caption']); ?>
                            </div>
                        </div>

                        <!-- POST FOOTER (VIEW POST LINK) -->
                        <div class="post-footer">
                            <a href="post.php?id=<?php echo $post['id']; ?>" class="view-post-btn">
                                👁️ View Post
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- PAGINATION -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="hashtag_search.php?tag=<?php echo urlencode($hashtag_slug); ?>&page=<?php echo $page - 1; ?>">← Previous</a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="hashtag_search.php?tag=<?php echo urlencode($hashtag_slug); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="hashtag_search.php?tag=<?php echo urlencode($hashtag_slug); ?>&page=<?php echo $page + 1; ?>">Next →</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <style>
        /* POST CARD STYLES FROM style.css */
        .post-card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .post-card:hover {
            box-shadow: var(--shadow-md);
            border-color: var(--primary);
        }

        .post-header {
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--light);
        }

        .post-user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }

        .post-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary);
        }

        .user-details {
            display: flex;
            flex-direction: column;
        }

        .post-username {
            color: var(--dark);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }

        .post-handle {
            color: var(--gray);
            text-decoration: none;
            font-size: 13px;
        }

        .post-time {
            font-size: 12px;
            color: var(--gray);
        }

        .post-content {
            padding: 15px;
        }

        .post-image {
            width: 100%;
            border-radius: 8px;
            margin-bottom: 15px;
            max-height: 400px;
            object-fit: cover;
        }

        .post-caption {
            font-size: 14px;
            line-height: 1.5;
            color: var(--dark);
        }

        .hashtag-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .hashtag-link:hover {
            color: var(--secondary);
            text-decoration: underline;
        }

        .post-footer {
            padding: 15px;
            border-top: 1px solid var(--light);
            display: flex;
            justify-content: center;
        }

        .view-post-btn {
            background: var(--light);
            color: var(--primary);
            padding: 10px 20px;
            border-radius: 20px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .view-post-btn:hover {
            background: var(--primary);
            color: white;
        }

        @media (max-width: 768px) {
            .post-card {
                margin: 0;
            }

            .post-header {
                padding: 12px;
            }

            .post-content {
                padding: 12px;
            }

            .post-footer {
                padding: 12px;
            }
        }
    </style>

</body>
</html>