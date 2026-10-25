<?php
/**
 * PIXORA - Hashtags & Trending Setup
 * Creates hashtags table and post_hashtags junction table
 * Run this once to initialize hashtags feature
 */

require 'config/database.php';

echo "<h1>🏷️ Pixora Hashtags Setup</h1>";

// ========================================
// CREATE HASHTAGS TABLE
// ========================================
$hashtags_sql = "
CREATE TABLE IF NOT EXISTS hashtags (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    usage_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_name (name),
    INDEX idx_slug (slug),
    INDEX idx_usage (usage_count DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if ($conn->query($hashtags_sql) === TRUE) {
    echo "✅ <b>hashtags</b> table created successfully!<br>";
} else {
    echo "❌ Error creating hashtags table: " . $conn->error . "<br>";
}

// ========================================
// CREATE POST_HASHTAGS JUNCTION TABLE
// ========================================
$post_hashtags_sql = "
CREATE TABLE IF NOT EXISTS post_hashtags (
    id INT PRIMARY KEY AUTO_INCREMENT,
    post_id INT NOT NULL,
    hashtag_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (hashtag_id) REFERENCES hashtags(id) ON DELETE CASCADE,
    UNIQUE KEY unique_post_hashtag (post_id, hashtag_id),
    
    INDEX idx_post_id (post_id),
    INDEX idx_hashtag_id (hashtag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if ($conn->query($post_hashtags_sql) === TRUE) {
    echo "✅ <b>post_hashtags</b> table created successfully!<br>";
} else {
    echo "❌ Error creating post_hashtags table: " . $conn->error . "<br>";
}

// ========================================
// VERIFY TABLES
// ========================================
echo "<br><h2>📊 Table Verification</h2>";

// Check hashtags table
$check_hashtags = $conn->query("SHOW TABLES LIKE 'hashtags'");
if ($check_hashtags->num_rows > 0) {
    echo "✅ hashtags table exists<br>";
    $columns = $conn->query("DESCRIBE hashtags");
    echo "<ul>";
    while ($col = $columns->fetch_assoc()) {
        echo "<li>{$col['Field']} ({$col['Type']})</li>";
    }
    echo "</ul>";
} else {
    echo "❌ hashtags table not found<br>";
}

// Check post_hashtags table
$check_post_hashtags = $conn->query("SHOW TABLES LIKE 'post_hashtags'");
if ($check_post_hashtags->num_rows > 0) {
    echo "✅ post_hashtags table exists<br>";
    $columns = $conn->query("DESCRIBE post_hashtags");
    echo "<ul>";
    while ($col = $columns->fetch_assoc()) {
        echo "<li>{$col['Field']} ({$col['Type']})</li>";
    }
    echo "</ul>";
} else {
    echo "❌ post_hashtags table not found<br>";
}

echo "<br><h2>✨ Setup Complete!</h2>";
echo "<p>Hashtags feature is ready to use.</p>";
echo "<p><a href='index.php'>← Back to Pixora</a></p>";

$conn->close();
?>