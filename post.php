<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

require 'config/database.php';
require 'includes/helpers.php';

$current_user_id = (int)$_SESSION['user_id'];
$post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$post_id) {
    header("Location: index.php");
    exit();
}

// Clear any caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Get post
$post = getPostById($post_id, $conn);

if (!$post) {
    echo "<!DOCTYPE html><html><head><title>Post Not Found - Pixora</title><link rel='stylesheet' href='assets/css/style.css'></head><body>
          <div class='container' style='text-align: center; padding: 100px 20px;'>
            <h2>Post not found</h2>
            <p style='color: var(--gray); margin: 15px 0;'>The post you are looking for does not exist or may have been deleted.</p>
            <a href='index.php' class='btn btn-primary' style='display: inline-block; padding: 10px 20px; text-decoration: none;'>Go to Feed</a>
          </div></body></html>";
    exit();
}

// Check if current user likes this post
$user_likes_post = userLikesPost($current_user_id, $post_id, $conn);

// Get comments
$comments_stmt = $conn->prepare("
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
$comments_stmt->bind_param("i", $post_id);
$comments_stmt->execute();
$comments_result = $comments_stmt->get_result();
$comments = $comments_result->fetch_all(MYSQLI_ASSOC);
$comments_stmt->close();

// Poster's profile picture
$poster_pic = 'assets/images/default-profile.jpg';
if (!empty($post['profile_picture']) && file_exists("uploads/profile/" . $post['profile_picture']) && strpos($post['profile_picture'], 'default') === false) {
    $poster_pic = "uploads/profile/" . $post['profile_picture'];
}

// Post image path
$post_img = $post['image_path'];
if (strpos($post_img, 'uploads/') !== 0 && !file_exists($post_img)) {
    $post_img = 'uploads/posts/' . $post_img;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($post['full_name']); ?>'s Post - Pixora</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .post-detail-container {
            max-width: 950px;
            margin: 50px auto;
            padding: 0;
            display: grid;
            grid-template-columns: 1.1fr 1fr;
            background: var(--white);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border);
        }

        .post-image-container {
            background: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 520px;
        }

        .post-image {
            width: 100%;
            height: 100%;
            max-height: 600px;
            object-fit: cover;
            display: block;
        }

        .post-info {
            padding: 30px;
            display: flex;
            flex-direction: column;
            max-height: 600px;
        }

        .post-header {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border);
            align-items: center;
        }

        .delete-post-btn {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .delete-post-btn:hover {
            background: rgba(239, 68, 68, 0.1);
            transform: scale(1.1);
        }

        .poster-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary);
            cursor: pointer;
        }

        .poster-info {
            flex: 1;
        }

        .poster-name {
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 2px;
        }

        .poster-username {
            color: var(--gray);
            font-size: 13px;
        }

        .post-time {
            color: var(--gray);
            font-size: 12px;
            margin-top: 2px;
        }

        .post-caption {
            margin-bottom: 20px;
            line-height: 1.6;
            color: var(--dark);
            font-size: 14px;
        }

        .post-stats {
            display: flex;
            gap: 25px;
            margin-bottom: 20px;
            padding: 12px 0;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }

        .stat {
            display: flex;
            align-items: baseline;
            gap: 6px;
        }

        .stat-number {
            font-size: 20px;
            font-weight: 800;
            color: var(--primary);
        }

        .stat-label {
            color: var(--gray);
            font-size: 13px;
            text-transform: uppercase;
            font-weight: 600;
        }

        .like-action-bar {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .like-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 18px;
            background: var(--light);
            border: 1.5px solid var(--border);
            border-radius: 25px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
            font-weight: 700;
            font-size: 14px;
            color: var(--dark);
        }

        .like-btn:hover {
            border-color: var(--secondary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        .like-btn.liked {
            background: rgba(236, 72, 153, 0.1);
            border-color: var(--secondary);
            color: var(--secondary);
        }

        .like-btn.liked .like-icon {
            transform: scale(1.15);
        }

        .comments-section {
            flex: 1;
            overflow-y: auto;
            margin-bottom: 15px;
            padding-right: 8px;
        }

        .comment {
            margin-bottom: 15px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-4px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .comment:last-child {
            border-bottom: none;
        }

        .comment-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 4px;
        }

        .comment-author {
            font-weight: 700;
            color: var(--dark);
            font-size: 13px;
        }

        .comment-author-username {
            color: var(--gray);
            font-size: 12px;
        }

        .comment-text {
            color: var(--dark);
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 4px;
            word-break: break-word;
        }

        .comment-time {
            color: var(--gray);
            font-size: 11px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .comment-delete {
            color: var(--danger);
            font-size: 11px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 600;
        }

        .comment-delete:hover {
            text-decoration: underline;
        }

        .add-comment {
            display: flex;
            gap: 10px;
            margin-top: auto;
            padding-top: 10px;
            border-top: 1px solid var(--border);
        }

        .add-comment input {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid var(--border);
            border-radius: 25px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.2s ease;
        }

        .add-comment input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
        }

        .add-comment button {
            padding: 10px 22px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 700;
            font-size: 13px;
            transition: all 0.3s ease;
        }

        .add-comment button:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .no-comments {
            text-align: center;
            color: var(--gray);
            padding: 30px 10px;
            font-size: 13px;
        }

        @media (max-width: 768px) {
            .post-detail-container {
                grid-template-columns: 1fr;
                margin: 20px auto;
                border-radius: 0;
            }

            .post-image-container {
                min-height: 320px;
            }

            .post-info {
                max-height: none;
                padding: 20px;
            }

            .comments-section {
                max-height: 280px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <!-- POST DETAIL -->
    <div class="post-detail-container">
        <!-- LEFT: Image -->
        <div class="post-image-container">
            <img src="<?php echo htmlspecialchars($post_img); ?>" alt="Post" class="post-image">
        </div>

        <!-- RIGHT: Info & Comments -->
        <div class="post-info">
            <!-- POST HEADER -->
            <div class="post-header">
                <img src="<?php echo htmlspecialchars($poster_pic); ?>" alt="Avatar" class="poster-avatar"
                     onclick="window.location.href='profile.php?id=<?php echo $post['user_id']; ?>'">
                <div class="poster-info">
                    <div class="poster-name">
                        <a href="profile.php?id=<?php echo $post['user_id']; ?>" 
                           style="color: inherit; text-decoration: none;">
                            <?php echo htmlspecialchars($post['full_name']); ?>
                        </a>
                    </div>
                    <div class="poster-username">@<?php echo htmlspecialchars($post['username']); ?></div>
                    <div class="post-time"><?php echo formatDate($post['created_at']); ?></div>
                </div>
                <?php if ((int)$post['user_id'] === $current_user_id): ?>
                    <form method="POST" action="includes/post_actions.php" style="margin-left: auto;">
                        <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                        <input type="hidden" name="action" value="delete">
                        <button type="submit" class="delete-post-btn" title="Delete Post" onclick="return confirm('Delete this post? This cannot be undone.');">
                            🗑️
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- CAPTION -->
            <div class="post-caption">
                <?php echo nl2br(htmlspecialchars($post['caption'])); ?>
            </div>

            <!-- STATS -->
            <div class="post-stats">
                <div class="stat">
                    <div class="stat-number" id="likes-count"><?php echo $post['likes_count']; ?></div>
                    <div class="stat-label">Likes</div>
                </div>
                <div class="stat">
                    <div class="stat-number" id="comments-count"><?php echo count($comments); ?></div>
                    <div class="stat-label">Comments</div>
                </div>
            </div>

            <!-- LIKE BUTTON -->
            <div class="like-action-bar">
                <a href="includes/like_actions.php?post_id=<?php echo $post_id; ?>&action=<?php echo $user_likes_post ? 'unlike' : 'like'; ?>" 
                   class="like-btn <?php echo $user_likes_post ? 'liked' : ''; ?>"
                   id="like-btn"
                   data-post-id="<?php echo $post_id; ?>"
                   data-liked="<?php echo $user_likes_post ? '1' : '0'; ?>">
                    <span class="like-icon" id="like-icon"><?php echo $user_likes_post ? '❤️' : '🤍'; ?></span>
                    <span class="like-label" id="like-label"><?php echo $user_likes_post ? 'Liked' : 'Like'; ?></span>
                </a>
            </div>

            <!-- COMMENTS SECTION -->
            <div class="comments-section" id="comments-section">
                <?php if (count($comments) > 0): ?>
                    <?php foreach ($comments as $comment): ?>
                        <div class="comment" id="comment-<?php echo $comment['id']; ?>">
                            <div class="comment-header">
                                <a href="profile.php?id=<?php echo $comment['user_id']; ?>" 
                                   class="comment-author" style="text-decoration: none;">
                                    <?php echo htmlspecialchars($comment['full_name']); ?>
                                </a>
                                <span class="comment-author-username">
                                    @<?php echo htmlspecialchars($comment['username']); ?>
                                </span>
                            </div>
                            <div class="comment-text">
                                <?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?>
                            </div>
                            <div class="comment-time">
                                <span><?php echo formatDate($comment['created_at']); ?></span>
                                <?php if ((int)$comment['user_id'] === $current_user_id): ?>
                                    <a href="includes/comment_actions.php?action=delete&comment_id=<?php echo $comment['id']; ?>&post_id=<?php echo $post_id; ?>" 
                                       class="comment-delete" 
                                       data-comment-id="<?php echo $comment['id']; ?>"
                                       onclick="return confirmDeleteComment(event, <?php echo $comment['id']; ?>);">Delete</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-comments" id="no-comments-msg">No comments yet. Be the first to share your thoughts!</div>
                <?php endif; ?>
            </div>

            <!-- ADD COMMENT -->
            <form method="POST" action="includes/comment_actions.php" class="add-comment" id="comment-form">
                <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                <input type="text" name="comment" id="comment-input" placeholder="Add a comment..." maxlength="500" required autocomplete="off">
                <button type="submit" id="comment-submit-btn">Post</button>
            </form>
        </div>
    </div>

    <!-- FOOTER -->
    <footer style="background: var(--dark); color: white; padding: 40px 20px; text-align: center; margin-top: 60px;">
        <div class="container">
            <p style="margin-bottom: 10px;">© 2024 <strong>Pixora</strong> - Your Premium Social Media Platform</p>
            <p style="color: var(--gray); font-size: 13px;">Built with ❤️ using PHP & MySQL</p>
        </div>
    </footer>

    <!-- INTERACTIVE SCRIPT (AJAX LIKES & COMMENTS) -->
    <script>
        // LIKE BUTTON INTERACTION
        const likeBtn = document.getElementById('like-btn');
        if (likeBtn) {
            likeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const postId = this.getAttribute('data-post-id');
                const isLiked = this.getAttribute('data-liked') === '1';
                const nextAction = isLiked ? 'unlike' : 'like';

                // Optimistic UI update
                const newLiked = !isLiked;
                likeBtn.setAttribute('data-liked', newLiked ? '1' : '0');
                if (newLiked) {
                    likeBtn.classList.add('liked');
                    document.getElementById('like-icon').textContent = '❤️';
                    document.getElementById('like-label').textContent = 'Liked';
                } else {
                    likeBtn.classList.remove('liked');
                    document.getElementById('like-icon').textContent = '🤍';
                    document.getElementById('like-label').textContent = 'Like';
                }

                fetch(`includes/like_actions.php?ajax=1&post_id=${postId}&action=${nextAction}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        likeBtn.setAttribute('data-liked', data.liked ? '1' : '0');
                        likeBtn.href = `includes/like_actions.php?post_id=${postId}&action=${data.liked ? 'unlike' : 'like'}`;
                        document.getElementById('likes-count').textContent = data.likes_count;
                        if (data.liked) {
                            likeBtn.classList.add('liked');
                            document.getElementById('like-icon').textContent = '❤️';
                            document.getElementById('like-label').textContent = 'Liked';
                        } else {
                            likeBtn.classList.remove('liked');
                            document.getElementById('like-icon').textContent = '🤍';
                            document.getElementById('like-label').textContent = 'Like';
                        }
                    } else {
                        // Revert on error
                        window.location.href = likeBtn.href;
                    }
                })
                .catch(() => {
                    window.location.href = likeBtn.href;
                });
            });
        }

        // COMMENT FORM INTERACTION
        const commentForm = document.getElementById('comment-form');
        const commentInput = document.getElementById('comment-input');
        const commentsSection = document.getElementById('comments-section');
        const commentsCountEl = document.getElementById('comments-count');

        if (commentForm) {
            commentForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const commentText = commentInput.value.trim();
                if (!commentText) return;

                const submitBtn = document.getElementById('comment-submit-btn');
                submitBtn.disabled = true;
                submitBtn.textContent = '...';

                const formData = new FormData(commentForm);
                formData.append('ajax', '1');

                fetch('includes/comment_actions.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(res => res.json())
                .then(data => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Post';

                    if (data.success && data.comment) {
                        commentInput.value = '';

                        // Remove "no comments" message if present
                        const noComments = document.getElementById('no-comments-msg');
                        if (noComments) noComments.remove();

                        // Add new comment to top
                        const c = data.comment;
                        const commentHtml = `
                            <div class="comment" id="comment-${c.id}">
                                <div class="comment-header">
                                    <a href="profile.php?id=${c.user_id}" class="comment-author" style="text-decoration: none;">
                                        ${c.full_name}
                                    </a>
                                    <span class="comment-author-username">@${c.username}</span>
                                </div>
                                <div class="comment-text">${c.comment_text}</div>
                                <div class="comment-time">
                                    <span>just now</span>
                                    <a href="includes/comment_actions.php?action=delete&comment_id=${c.id}&post_id=${c.post_id}" 
                                       class="comment-delete" 
                                       data-comment-id="${c.id}"
                                       onclick="return confirmDeleteComment(event, ${c.id});">Delete</a>
                                </div>
                            </div>
                        `;
                        commentsSection.insertAdjacentHTML('afterbegin', commentHtml);
                        if (commentsCountEl) commentsCountEl.textContent = data.comments_count;
                        commentsSection.scrollTop = 0;
                    } else {
                        commentForm.submit();
                    }
                })
                .catch(() => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Post';
                    commentForm.submit();
                });
            });
        }

        // DELETE COMMENT AJAX
        function confirmDeleteComment(event, commentId) {
            if (!confirm('Delete this comment?')) {
                return false;
            }
            event.preventDefault();
            const commentEl = document.getElementById(`comment-${commentId}`);

            fetch(`includes/comment_actions.php?ajax=1&action=delete&comment_id=${commentId}&post_id=<?php echo $post_id; ?>`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (commentEl) commentEl.remove();
                    if (commentsCountEl) commentsCountEl.textContent = data.comments_count;
                    if (commentsSection.children.length === 0) {
                        commentsSection.innerHTML = '<div class="no-comments" id="no-comments-msg">No comments yet. Be the first to share your thoughts!</div>';
                    }
                } else {
                    window.location.reload();
                }
            })
            .catch(() => {
                window.location.reload();
            });
            return false;
        }
    </script>
</body>
</html>
