<?php
/**
 * PIXORA - Hashtag Functions
 * Functions for hashtag extraction, storage, and retrieval
 */

/**
 * Extract hashtags from text
 * Finds all #hashtags in the text
 * Returns array of hashtag names (without #)
 */
function extractHashtags($text) {
    $hashtags = [];
    
    // Find all #hashtags in text
    if (preg_match_all('/#(\w+)/u', $text, $matches)) {
        foreach ($matches[1] as $tag) {
            // Convert to lowercase and limit to 30 chars
            $tag = strtolower(substr($tag, 0, 30));
            if (!empty($tag) && !in_array($tag, $hashtags)) {
                $hashtags[] = $tag;
            }
        }
    }
    
    return $hashtags;
}

/**
 * Create or get hashtag ID
 * If hashtag doesn't exist, creates it
 * Returns hashtag ID
 */
function getOrCreateHashtag($name, $conn) {
    $name = strtolower(trim($name));
    $slug = str_replace(' ', '-', $name);
    
    // Check if hashtag exists
    $stmt = $conn->prepare("SELECT id FROM hashtags WHERE name = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['id'];
    }
    
    $stmt->close();
    
    // Create new hashtag
    $stmt = $conn->prepare("INSERT INTO hashtags (name, slug, usage_count) VALUES (?, ?, 0)");
    $stmt->bind_param("ss", $name, $slug);
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();
    
    return $id;
}

/**
 * Store hashtags for a post
 * Links post to extracted hashtags
 */
function storePostHashtags($post_id, $hashtags, $conn) {
    // Remove any existing hashtags for this post
    $delete_stmt = $conn->prepare("DELETE FROM post_hashtags WHERE post_id = ?");
    $delete_stmt->bind_param("i", $post_id);
    $delete_stmt->execute();
    $delete_stmt->close();
    
    // Add new hashtags
    foreach ($hashtags as $tag) {
        $hashtag_id = getOrCreateHashtag($tag, $conn);
        
        $insert_stmt = $conn->prepare("INSERT INTO post_hashtags (post_id, hashtag_id) VALUES (?, ?)");
        $insert_stmt->bind_param("ii", $post_id, $hashtag_id);
        $insert_stmt->execute();
        $insert_stmt->close();
    }
    
    // Update usage count
    updateHashtagCounts($conn);
}

/**
 * Update hashtag usage counts
 * Recalculates usage count for all hashtags
 */
function updateHashtagCounts($conn) {
    $sql = "
        UPDATE hashtags h
        SET h.usage_count = (
            SELECT COUNT(*) FROM post_hashtags ph
            WHERE ph.hashtag_id = h.id
        )
    ";
    $conn->query($sql);
}

/**
 * Get trending hashtags
 * Returns top hashtags by usage
 */
function getTrendingHashtags($limit = 10, $conn) {
    $stmt = $conn->prepare("
        SELECT id, name, slug, usage_count 
        FROM hashtags 
        WHERE usage_count > 0
        ORDER BY usage_count DESC 
        LIMIT ?
    ");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $hashtags = [];
    while ($row = $result->fetch_assoc()) {
        $hashtags[] = $row;
    }
    
    $stmt->close();
    return $hashtags;
}

/**
 * Get posts by hashtag
 * Returns all posts tagged with a specific hashtag
 */
function getPostsByHashtag($hashtag_slug, $limit = 20, $offset = 0, $conn) {
    $stmt = $conn->prepare("
        SELECT p.id, p.user_id, p.image, p.caption, p.created_at, 
               u.full_name, u.username, u.profile_picture
        FROM posts p
        JOIN users u ON p.user_id = u.id
        JOIN post_hashtags ph ON p.id = ph.post_id
        JOIN hashtags h ON ph.hashtag_id = h.id
        WHERE h.slug = ?
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("sii", $hashtag_slug, $limit, $offset);
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
 * Get hashtags for a post
 * Returns all hashtags for a specific post
 */
function getPostHashtags($post_id, $conn) {
    $stmt = $conn->prepare("
        SELECT h.id, h.name, h.slug, h.usage_count
        FROM hashtags h
        JOIN post_hashtags ph ON h.id = ph.hashtag_id
        WHERE ph.post_id = ?
        ORDER BY h.usage_count DESC
    ");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $hashtags = [];
    while ($row = $result->fetch_assoc()) {
        $hashtags[] = $row;
    }
    
    $stmt->close();
    return $hashtags;
}

/**
 * Convert caption text to include clickable hashtag links
 * Replaces #hashtags with links to hashtag search
 */
function linkifyHashtags($caption) {
    $linked = preg_replace(
        '/#(\w+)/u',
        '<a href="hashtag_search.php?tag=$1" class="hashtag-link">#$1</a>',
        htmlspecialchars($caption)
    );
    return $linked;
}

/**
 * Get hashtag info by slug
 * Returns hashtag details
 */
function getHashtagBySlug($slug, $conn) {
    $stmt = $conn->prepare("
        SELECT id, name, slug, usage_count 
        FROM hashtags 
        WHERE slug = ?
    ");
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row;
    }
    
    $stmt->close();
    return null;
}

/**
 * Search hashtags
 * Finds hashtags matching a query
 */
function searchHashtags($query, $limit = 10, $conn) {
    $search = "%{$query}%";
    $stmt = $conn->prepare("
        SELECT id, name, slug, usage_count
        FROM hashtags
        WHERE name LIKE ?
        ORDER BY usage_count DESC
        LIMIT ?
    ");
    $stmt->bind_param("si", $search, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $hashtags = [];
    while ($row = $result->fetch_assoc()) {
        $hashtags[] = $row;
    }
    
    $stmt->close();
    return $hashtags;
}

/**
 * Delete hashtag and its relationships
 * Used when deleting a post
 */
function deletePostHashtags($post_id, $conn) {
    $stmt = $conn->prepare("DELETE FROM post_hashtags WHERE post_id = ?");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $stmt->close();
    
    // Cleanup unused hashtags (optional)
    $conn->query("DELETE FROM hashtags WHERE usage_count = 0");
    
    // Update counts
    updateHashtagCounts($conn);
}

/**
 * Get count of posts with hashtag
 * Returns number of posts tagged with hashtag
 */
function getHashtagPostCount($hashtag_id, $conn) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM post_hashtags 
        WHERE hashtag_id = ?
    ");
    $stmt->bind_param("i", $hashtag_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row['count'];
}

?>