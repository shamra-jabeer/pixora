<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

require 'config/database.php';
require 'includes/helpers.php';
require 'includes/hashtag_functions.php';
require 'includes/message_actions.php';
require 'includes/notification_helpers.php';

$current_user_id = $_SESSION['user_id'];
$current_user = getUserInfo($current_user_id, $conn);

// Get trending hashtags
$trending_hashtags = getTrendingHashtags(50, $conn);

// Get unread counts for navbar
$unread_notif_count = getUnreadNotificationCount($current_user_id, $conn);
$unread_msg_count = getUnreadMessageCount($current_user_id, $conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trending Hashtags - Pixora</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .trending-container {
            max-width: 600px;
            margin: 60px auto;
            padding: 0 20px;
        }

        .trending-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-md);
        }

        .trending-header h1 {
            margin: 0 0 10px 0;
            font-size: 32px;
        }

        .trending-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 14px;
        }

        .trending-search {
            margin-bottom: 30px;
        }

        .trending-search input {
            width: 100%;
            padding: 15px;
            border: 2px solid var(--light);
            border-radius: 25px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .trending-search input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
        }

        .hashtag-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
        }

        .hashtag-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .hashtag-info {
            flex: 1;
        }

        .hashtag-name {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
            display: inline-block;
            margin-bottom: 5px;
        }

        .hashtag-name:hover {
            color: var(--secondary);
            text-decoration: underline;
        }

        .hashtag-count {
            font-size: 13px;
            color: var(--gray);
            margin: 5px 0 0 0;
        }

        .hashtag-posts {
            text-align: right;
        }

        .post-count {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 3px;
        }

        .post-label {
            font-size: 12px;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .rank-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-radius: 50%;
            font-weight: 700;
            font-size: 16px;
            margin-right: 15px;
        }

        .rank-badge.top {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray);
        }

        .empty-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .empty-text {
            font-size: 16px;
            font-weight: 600;
        }

        .trending-list {
            max-height: 800px;
            overflow-y: auto;
        }

        .trending-list::-webkit-scrollbar {
            width: 8px;
        }

        .trending-list::-webkit-scrollbar-track {
            background: var(--light);
            border-radius: 10px;
        }

        .trending-list::-webkit-scrollbar-thumb {
            background: var(--gray);
            border-radius: 10px;
        }

        .trending-list::-webkit-scrollbar-thumb:hover {
            background: var(--primary);
        }

        @media (max-width: 768px) {
            .trending-container {
                margin: 50px auto 0;
            }

            .trending-header {
                padding: 20px;
            }

            .trending-header h1 {
                font-size: 24px;
            }

            .hashtag-card {
                padding: 15px;
            }

            .hashtag-name {
                font-size: 16px;
            }

            .rank-badge {
                width: 35px;
                height: 35px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <!-- TRENDING CONTAINER -->
    <div class="trending-container">
        <!-- HEADER -->
        <div class="trending-header">
            <h1>📈 Trending Hashtags</h1>
            <p>Most popular hashtags on Pixora</p>
        </div>

        <!-- SEARCH -->
        <div class="trending-search">
            <input 
                type="text" 
                id="hashtag-search" 
                placeholder="Search hashtags..."
                autocomplete="off"
            >
        </div>

        <!-- HASHTAG LIST -->
        <div class="trending-list" id="trending-list">
            <?php if (count($trending_hashtags) === 0): ?>
                <div class="empty-state">
                    <div class="empty-icon">🔍</div>
                    <div class="empty-text">No trending hashtags yet</div>
                    <p>Be the first to use hashtags in your posts!</p>
                </div>
            <?php else: ?>
                <?php foreach ($trending_hashtags as $index => $hashtag): ?>
                    <div class="hashtag-card" onclick="window.location='hashtag_search.php?tag=<?php echo urlencode($hashtag['slug']); ?>'">
                        <div style="display: flex; align-items: center; width: 100%;">
                            <div class="rank-badge <?php echo $index < 3 ? 'top' : ''; ?>">
                                <?php echo $index + 1; ?>
                            </div>
                            <div class="hashtag-info">
                                <a href="hashtag_search.php?tag=<?php echo urlencode($hashtag['slug']); ?>" class="hashtag-name" onclick="event.stopPropagation();">
                                    #<?php echo htmlspecialchars($hashtag['name']); ?>
                                </a>
                                <div class="hashtag-count">
                                    Trending worldwide
                                </div>
                            </div>
                        </div>
                        <div class="hashtag-posts">
                            <div class="post-count"><?php echo $hashtag['usage_count']; ?></div>
                            <div class="post-label">Posts</div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Search hashtags in real-time
        const searchInput = document.getElementById('hashtag-search');
        const trendingList = document.getElementById('trending-list');

        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            const cards = trendingList.querySelectorAll('.hashtag-card');

            if (!query) {
                cards.forEach(card => card.style.display = '');
                return;
            }

            let matchCount = 0;
            cards.forEach(card => {
                const hashtag = card.querySelector('.hashtag-name').textContent.toLowerCase();
                if (hashtag.includes(query)) {
                    card.style.display = '';
                    matchCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            // Show "no results" if nothing matches
            if (matchCount === 0) {
                trendingList.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">🔍</div>
                        <div class="empty-text">No hashtags match "${query}"</div>
                    </div>
                `;
            }
        });
    </script>

</body>
</html>