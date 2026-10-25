<?php
session_start();

// Admin setup - only run once or if needed
require 'config/database.php';

echo "Setting up Direct Messaging system...\n\n";

// Create messages table
$create_messages = "
    CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        recipient_id INT NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_sender (sender_id),
        INDEX idx_recipient (recipient_id),
        INDEX idx_created_at (created_at)
    )
";

if ($conn->query($create_messages)) {
    echo "✅ Messages table created successfully!\n";
} else {
    echo "⚠️  Messages table already exists or error: " . $conn->error . "\n";
}

echo "\n✅ Direct Messaging system is ready!\n";
echo "\nYou can now:\n";
echo "1. Visit /messages.php to see all conversations\n";
echo "2. Click on a conversation to chat with that user\n";
echo "3. Visit a user's profile to start a new message\n";
echo "\nFiles added:\n";
echo "- messages.php (All conversations)\n";
echo "- chat.php (Individual chat view)\n";
echo "- includes/message_actions.php (Message functions)\n";

$conn->close();
?>