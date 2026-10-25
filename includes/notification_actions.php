<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

require '../config/database.php';

$user_id = $_SESSION['user_id'];
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($action === 'delete') {
    $notif_id = isset($_POST['notif_id']) ? (int)$_POST['notif_id'] : 0;
    
    if ($notif_id) {
        // Verify notification belongs to current user
        $verify = $conn->prepare("SELECT recipient_id FROM notifications WHERE id = ?");
        $verify->bind_param("i", $notif_id);
        $verify->execute();
        $verify_result = $verify->get_result();
        $notif = $verify_result->fetch_assoc();
        $verify->close();
        
        // Delete if belongs to user
        if ($notif && $notif['recipient_id'] === $user_id) {
            $delete = $conn->prepare("DELETE FROM notifications WHERE id = ?");
            $delete->bind_param("i", $notif_id);
            $delete->execute();
            $delete->close();
        }
    }
}

elseif ($action === 'clear_all') {
    // Delete all notifications for this user
    $delete_all = $conn->prepare("DELETE FROM notifications WHERE recipient_id = ?");
    $delete_all->bind_param("i", $user_id);
    $delete_all->execute();
    $delete_all->close();
}

// Redirect back to notifications
header("Location: ../notifications.php");
exit();
?>