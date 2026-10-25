<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

require 'config/database.php';
require 'includes/helpers.php';

$current_user_id = $_SESSION['user_id'];
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$search_type = isset($_GET['type']) ? $_GET['type'] : 'users'; // users or posts

$users_results = [];
$posts_results = [];

if (!empty($search_query) && strlen($search_query) >= 2) {
    $search_term = '%' . $search_query . '%';
    
    // Search Users
    if ($search_type === 'users' || $search_type === 'all') {
        $users_stmt = $conn->prepare("
            SELECT 
                id,
                full_name,
                username,
                profile_picture,
                bio
            FROM users
            WHERE username LIKE ? OR full_name LIKE ?
            LIMIT 20
        ");
        $users_stmt->bind_param("ss", $search_term, $search_term);
        $users_stmt->execute();
        $users_result = $users_stmt->get_result();
        $users_results = $users_result->fetch_all(MYSQLI_ASSOC);
        $users_stmt->close();
    }
    
    // Search Posts
    if ($search_type === 'posts' || $search_type === 'all') {
        $posts_stmt = $conn->prepare("
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
            WHERE p.caption LIKE ?
            ORDER BY p.created_at DESC
            LIMIT 20
        ");
        $posts_stmt->bind_param("s", $search_term);
        $posts_stmt->execute();
        $posts_result = $posts_stmt->get_result();
        $posts_results = $posts_result->fetch_all(MYSQLI_ASSOC);
        $posts_stmt->close();
    }
}

// Get current user following list
$following_stmt = $conn->prepare("
    SELECT following_id FROM follows WHERE follower_id = ?
");
$following_stmt->bind_param("i", $current_user_id);
$following_stmt->execute();
$following_result = $following_stmt->get_result();
$following_list = [];
while ($row = $following_result->fetch_assoc()) {
    $following_list[] = $row['following_id'];
}
$following_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search - Pixora</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .search-container {
            max-width: 1200px;
            margin: 60px auto;
            padding: 0 20px;
        }

        .search-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            padding: 40px 30px;
            border-radius: 20px;
            margin-bottom: 40px;
            box-shadow: var(--shadow-lg);
        }

        .search-form {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .search-input {
            flex: 1;
            padding: 15px 20px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            background: white;
            font-family: inherit;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .search-input:focus {
            outline: none;
            box-shadow: 0 4px 25px rgba(124, 58, 237, 0.3);
        }

        .search-button {
            padding: 15px 30px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid white;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .search-button:hover {
            background: white;
            color: var(--primary);
        }

        .search-tabs {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .search-tab {
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.5);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            font-weight: 600;
        }

        .search-tab:hover,
        .search-tab.active {
            background: white;
            color: var(--primary);
            border-color: white;
        }

        .results-section {
            margin-bottom: 40px;
        }

        .section-title {
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 20px;
            color: var(--dark);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .user-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: var(--shadow-md);
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .user-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
        }

        .user-card-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 15px;
            border: 4px solid var(--primary);
        }

        .user-card-name {
            font-size: 18px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .user-card-username {
            color: var(--gray);
            font-size: 14px;
            margin-bottom: 12px;
        }

        .user-card-bio {
            color: var(--dark);
            font-size: 13px;
            line-height: 1.5;
            margin-bottom: 15px;
            min-height: 40px;
        }

        .user-card-actions {
            display: flex;
            gap: 10px;
        }

        .btn-view-profile {
            flex: 1;
            padding: 10px;
            background: var(--light);
            color: var(--dark);
            border: 2px solid var(--primary);
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.3s ease;
            text-align: center;
            font-size: 13px;
        }

        .btn-view-profile:hover {
            background: var(--primary);
            color: white;
        }

        .posts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }

        .post-thumbnail {
            position: relative;
            height: 250px;
            border-radius: 12px;
            overflow: hidden;
            cursor: pointer;
            background: var(--light);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-md);
        }

        .post-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .post-thumbnail:hover img {
            transform: scale(1.05);
        }

        .post-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .post-thumbnail:hover .post-overlay {
            opacity: 1;
        }

        .post-stat {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            color: white;
        }

        .post-stat-number {
            font-size: 20px;
            font-weight: 800;
        }

        .post-stat-label {
            font-size: 12px;
            text-transform: uppercase;
        }

        .no-results {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray);
        }

        .no-results-icon {
            font-size: 60px;
            margin-bottom: 20px;
        }

        .no-results-text {
            font-size: 18px;
            margin-bottom: 10px;
        }

        .search-hint {
            color: var(--gray);
            font-size: 14px;
            text-align: center;
            padding: 60px 20px;
        }

        @media (max-width: 768px) {
            .search-header {
                padding: 30px 20px;
            }

            .search-form {
                flex-direction: column;
            }

            .search-tabs {
                flex-wrap: wrap;
            }

            .users-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }

            .posts-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <!-- SEARCH CONTAINER -->
    <div class="search-container">
        <!-- SEARCH HEADER -->
        <div class="search-header">
            <form method="GET" class="search-form">
                <input type="text" name="q" class="search-input" 
                       placeholder="Search users, captions, hashtags..." 
                       value="<?php echo htmlspecialchars($search_query); ?>"
                       autofocus>
                <button type="submit" class="search-button">🔍 Search</button>
            </form>

            <div class="search-tabs">
                <a href="search.php?q=<?php echo urlencode($search_query); ?>&type=all" 
                   class="search-tab <?php echo $search_type === 'all' ? 'active' : ''; ?>">
                    All
                </a>
                <a href="search.php?q=<?php echo urlencode($search_query); ?>&type=users" 
                   class="search-tab <?php echo $search_type === 'users' ? 'active' : ''; ?>">
                    Users
                </a>
                <a href="search.php?q=<?php echo urlencode($search_query); ?>&type=posts" 
                   class="search-tab <?php echo $search_type === 'posts' ? 'active' : ''; ?>">
                    Posts
                </a>
            </div>
        </div>

        <!-- RESULTS -->
        <?php if (empty($search_query)): ?>
            <div class="search-hint">
                <p>🔍 Start typing to search for users or posts</p>
                <p style="font-size: 12px; margin-top: 10px;">Try searching: username, full name, or post caption</p>
            </div>
        <?php else: ?>
            <!-- USERS RESULTS -->
            <?php if (($search_type === 'users' || $search_type === 'all') && count($users_results) > 0): ?>
                <div class="results-section">
                    <h2 class="section-title">👥 Users (<?php echo count($users_results); ?>)</h2>
                    <div class="users-grid">
                        <?php foreach ($users_results as $user): ?>
                            <div class="user-card">
                                <img src="<?php 
                                    $pic = "https://via.placeholder.com/150/cccccc/666666?text=User";
                                    if (!empty($user['profile_picture']) && file_exists("uploads/profile/" . $user['profile_picture'])) {
                                        $pic = "uploads/profile/" . $user['profile_picture'];
                                    }
                                    echo htmlspecialchars($pic);
                                ?>" alt="Avatar" class="user-card-avatar">
                                
                                <div class="user-card-name">
                                    <?php echo htmlspecialchars($user['full_name']); ?>
                                </div>
                                <div class="user-card-username">
                                    @<?php echo htmlspecialchars($user['username']); ?>
                                </div>
                                <div class="user-card-bio">
                                    <?php echo htmlspecialchars($user['bio'] ?? 'No bio yet'); ?>
                                </div>
                                
                                <div class="user-card-actions">
                                    <a href="profile.php?id=<?php echo $user['id']; ?>" class="btn-view-profile">
                                        View Profile
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- POSTS RESULTS -->
            <?php if (($search_type === 'posts' || $search_type === 'all') && count($posts_results) > 0): ?>
                <div class="results-section">
                    <h2 class="section-title">📸 Posts (<?php echo count($posts_results); ?>)</h2>
                    <div class="posts-grid">
                        <?php foreach ($posts_results as $post): ?>
                            <a href="post.php?id=<?php echo $post['id']; ?>" class="post-thumbnail">
                                <img src="<?php echo htmlspecialchars($post['image_path']); ?>" alt="Post">
                                <div class="post-overlay">
                                    <div class="post-stat">
                                        <div class="post-stat-number">❤️ <?php echo $post['likes_count']; ?></div>
                                        <div class="post-stat-label">Likes</div>
                                    </div>
                                    <div class="post-stat">
                                        <div class="post-stat-number">💬 <?php echo $post['comments_count']; ?></div>
                                        <div class="post-stat-label">Comments</div>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- NO RESULTS -->
            <?php if (empty($users_results) && empty($posts_results)): ?>
                <div class="no-results">
                    <div class="no-results-icon">🔍</div>
                    <div class="no-results-text">No results found for "<?php echo htmlspecialchars($search_query); ?>"</div>
                    <p style="font-size: 13px;">Try a different search term</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- FOOTER -->
    <footer style="background: var(--dark); color: white; padding: 40px 20px; text-align: center; margin-top: 60px;">
        <div class="container">
            <p style="margin-bottom: 10px;">© 2024 <strong>Pixora</strong> - Your Premium Social Media Platform</p>
            <p style="color: var(--gray); font-size: 13px;">Built with ❤️ using PHP & MySQL</p>
        </div>
    </footer>

</body>
</html>