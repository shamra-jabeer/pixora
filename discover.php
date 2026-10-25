<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

require 'config/database.php';
require 'includes/helpers.php';

$current_user_id = $_SESSION['user_id'];

// Get suggested users (not following, not self)
$suggest_stmt = $conn->prepare("
    SELECT 
        u.id,
        u.full_name,
        u.username,
        u.profile_picture,
        u.bio,
        (SELECT COUNT(*) FROM posts WHERE user_id = u.id) as post_count,
        (SELECT COUNT(*) FROM follows WHERE following_id = u.id) as follower_count
    FROM users u
    WHERE u.id != ?
    AND u.id NOT IN (
        SELECT following_id FROM follows WHERE follower_id = ?
    )
    ORDER BY follower_count DESC
    LIMIT 12
");
$suggest_stmt->bind_param("ii", $current_user_id, $current_user_id);
$suggest_stmt->execute();
$suggest_result = $suggest_stmt->get_result();
$suggested_users = $suggest_result->fetch_all(MYSQLI_ASSOC);
$suggest_stmt->close();

// Get following status for each user
$following_status = [];
foreach ($suggested_users as $user) {
    $status = isFollowing($current_user_id, $user['id'], $conn);
    $following_status[$user['id']] = $status;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discover - Pixora</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .discover-container {
            max-width: 1200px;
            margin: 60px auto;
            padding: 0 20px;
        }

        .discover-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            padding: 40px 30px;
            border-radius: 20px;
            margin-bottom: 40px;
            box-shadow: var(--shadow-lg);
            color: white;
        }

        .discover-header h1 {
            font-size: 32px;
            margin: 0 0 10px 0;
            font-weight: 800;
        }

        .discover-header p {
            font-size: 16px;
            margin: 0;
            opacity: 0.9;
        }

        .discover-title {
            font-size: 28px;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 30px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .user-discover-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            box-shadow: var(--shadow-md);
            transition: all 0.3s ease;
            border: 3px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .user-discover-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }

        .user-discover-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
        }

        .user-discover-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 20px;
            border: 5px solid var(--primary);
            box-shadow: 0 4px 15px rgba(124, 58, 237, 0.3);
        }

        .user-discover-name {
            font-size: 20px;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .user-discover-username {
            color: var(--gray);
            font-size: 14px;
            margin-bottom: 15px;
        }

        .user-discover-bio {
            color: var(--dark);
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 20px;
            min-height: 50px;
        }

        .user-discover-stats {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-bottom: 20px;
            padding: 15px 0;
            border-top: 2px solid var(--border);
            border-bottom: 2px solid var(--border);
        }

        .discover-stat {
            text-align: center;
        }

        .discover-stat-number {
            font-size: 20px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .discover-stat-label {
            font-size: 11px;
            color: var(--gray);
            text-transform: uppercase;
            font-weight: 700;
            margin-top: 5px;
        }

        .discover-actions {
            display: flex;
            gap: 12px;
        }

        .btn-follow-discover {
            flex: 1;
            padding: 12px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 700;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .btn-follow-discover:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-follow-discover.following {
            background: var(--light);
            color: var(--primary);
            border: 2px solid var(--primary);
        }

        .btn-follow-discover.following:hover {
            background: var(--primary);
            color: white;
        }

        .btn-view-discover {
            flex: 1;
            padding: 12px;
            background: var(--light);
            color: var(--dark);
            border: 2px solid var(--primary);
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            font-weight: 700;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .btn-view-discover:hover {
            background: var(--primary);
            color: white;
        }

        .no-users {
            text-align: center;
            padding: 80px 20px;
            color: var(--gray);
        }

        .no-users-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }

        .no-users-text {
            font-size: 20px;
            margin-bottom: 10px;
            color: var(--dark);
        }

        @media (max-width: 768px) {
            .discover-header {
                padding: 30px 20px;
            }

            .discover-header h1 {
                font-size: 24px;
            }

            .users-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 15px;
            }

            .user-discover-card {
                padding: 20px;
            }

            .user-discover-avatar {
                width: 80px;
                height: 80px;
            }

            .discover-actions {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <!-- DISCOVER CONTAINER -->
    <div class="discover-container">
        <!-- HEADER -->
        <div class="discover-header">
            <h1>🌟 Discover</h1>
            <p>Find interesting people to follow and expand your network</p>
        </div>

        <!-- SUGGESTED USERS -->
        <?php if (count($suggested_users) > 0): ?>
            <h2 class="discover-title">Suggested for You</h2>
            <div class="users-grid">
                <?php foreach ($suggested_users as $user): ?>
                    <div class="user-discover-card">
                        <img src="<?php 
                            $pic ="https://via.placeholder.com/150/cccccc/666666?text=User";
                            if (!empty($user['profile_picture']) && file_exists("uploads/profile/" . $user['profile_picture'])) {
                                $pic = "uploads/profile/" . $user['profile_picture'];
                            }
                            echo htmlspecialchars($pic);
                        ?>" alt="Avatar" class="user-discover-avatar">
                        
                        <div class="user-discover-name">
                            <?php echo htmlspecialchars($user['full_name']); ?>
                        </div>
                        <div class="user-discover-username">
                            @<?php echo htmlspecialchars($user['username']); ?>
                        </div>
                        <div class="user-discover-bio">
                            <?php echo htmlspecialchars($user['bio'] ?? 'No bio yet'); ?>
                        </div>

                        <div class="user-discover-stats">
                            <div class="discover-stat">
                                <div class="discover-stat-number"><?php echo $user['post_count']; ?></div>
                                <div class="discover-stat-label">Posts</div>
                            </div>
                            <div class="discover-stat">
                                <div class="discover-stat-number"><?php echo $user['follower_count']; ?></div>
                                <div class="discover-stat-label">Followers</div>
                            </div>
                        </div>

                        <div class="discover-actions">
                            <a href="chat.php?user_id=<?php echo $user['id']; ?>" class="btn-view-discover" style="flex: 1;">
                                💬 Message
                            </a>
                            <form method="POST" action="includes/follow_actions.php" style="flex: 1;">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <input type="hidden" name="action" value="<?php echo $following_status[$user['id']] ? 'unfollow' : 'follow'; ?>">
                                <button type="submit" class="btn-follow-discover <?php echo $following_status[$user['id']] ? 'following' : ''; ?>">
                                    <?php echo $following_status[$user['id']] ? '✓ Following' : '+ Follow'; ?>
                                </button>
                            </form>
                            <a href="profile.php?id=<?php echo $user['id']; ?>" class="btn-view-discover">
                                View Profile
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-users">
                <div class="no-users-icon">😊</div>
                <div class="no-users-text">You're following everyone!</div>
                <p>Great job expanding your network. Check back later for more users.</p>
            </div>
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