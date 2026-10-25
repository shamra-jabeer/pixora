<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

require 'config/database.php';
require 'includes/helpers.php';

$user_id = (int)$_SESSION['user_id'];
$error = '';
$success = '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'profile';

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ==========================================
    // 1. UPDATE PROFILE INFO
    // ==========================================
    if ($action === 'update_profile') {
        $active_tab = 'profile';
        $full_name = trim($_POST['full_name'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $current_user = getUserInfo($user_id, $conn);

        if (empty($full_name)) {
            $error = "Full name cannot be empty.";
        } elseif (strlen($full_name) > 100) {
            $error = "Full name must be under 100 characters.";
        } elseif (strlen($bio) > 500) {
            $error = "Bio cannot exceed 500 characters.";
        } else {
            $new_profile_picture = null;

            // Handle file upload
            if (!empty($_FILES['profile_picture']['name'])) {
                if (validateImage($_FILES['profile_picture'])) {
                    // Delete old profile picture if not default
                    if (!empty($current_user['profile_picture']) && strpos($current_user['profile_picture'], 'default') === false) {
                        deleteProfilePicture($current_user['profile_picture']);
                    }
                    $new_profile_picture = uploadProfilePicture($_FILES['profile_picture'], $user_id);
                    if (!$new_profile_picture) {
                        $error = "Failed to upload profile picture. Please try again.";
                    }
                } else {
                    $error = "Invalid image file. Please upload JPG, PNG, or GIF (Max 5MB).";
                }
            }

            if (empty($error)) {
                if ($new_profile_picture) {
                    $stmt = $conn->prepare("UPDATE users SET full_name = ?, bio = ?, profile_picture = ? WHERE id = ?");
                    $stmt->bind_param("sssi", $full_name, $bio, $new_profile_picture, $user_id);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET full_name = ?, bio = ? WHERE id = ?");
                    $stmt->bind_param("ssi", $full_name, $bio, $user_id);
                }

                if ($stmt->execute()) {
                    $_SESSION['full_name'] = $full_name;
                    $success = "Profile updated successfully! ✨";
                } else {
                    $error = "Database error: " . $conn->error;
                }
                $stmt->close();
            }
        }
    }

    // ==========================================
    // 2. REMOVE PROFILE PICTURE
    // ==========================================
    elseif ($action === 'remove_picture') {
        $active_tab = 'profile';
        $current_user = getUserInfo($user_id, $conn);
        if (!empty($current_user['profile_picture']) && strpos($current_user['profile_picture'], 'default') === false) {
            deleteProfilePicture($current_user['profile_picture']);
        }
        $stmt = $conn->prepare("UPDATE users SET profile_picture = 'default-profile.jpg' WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        $success = "Profile photo removed.";
    }

    // ==========================================
    // 3. UPDATE ACCOUNT (USERNAME & EMAIL)
    // ==========================================
    elseif ($action === 'update_account') {
        $active_tab = 'account';
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (empty($username) || empty($email)) {
            $error = "Username and Email are required.";
        } elseif (strlen($username) < 3 || strlen($username) > 50) {
            $error = "Username must be between 3 and 50 characters.";
        } elseif (!preg_match('/^[a-zA-Z0-9_.]+$/', $username)) {
            $error = "Username can only contain letters, numbers, dots, and underscores.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            // Check username uniqueness
            $u_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $u_stmt->bind_param("si", $username, $user_id);
            $u_stmt->execute();
            if ($u_stmt->get_result()->num_rows > 0) {
                $error = "Username '@" . htmlspecialchars($username) . "' is already taken. Please pick another.";
            }
            $u_stmt->close();

            if (empty($error)) {
                // Check email uniqueness
                $e_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $e_stmt->bind_param("si", $email, $user_id);
                $e_stmt->execute();
                if ($e_stmt->get_result()->num_rows > 0) {
                    $error = "Email '" . htmlspecialchars($email) . "' is already associated with another account.";
                }
                $e_stmt->close();
            }

            if (empty($error)) {
                $stmt = $conn->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
                $stmt->bind_param("ssi", $username, $email, $user_id);
                if ($stmt->execute()) {
                    $_SESSION['username'] = $username;
                    $_SESSION['email'] = $email;
                    $success = "Account settings updated successfully! ✅";
                } else {
                    $error = "Failed to update account: " . $conn->error;
                }
                $stmt->close();
            }
        }
    }

    // ==========================================
    // 4. CHANGE PASSWORD
    // ==========================================
    elseif ($action === 'change_password') {
        $active_tab = 'security';
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = "Please fill in all password fields.";
        } elseif (strlen($new_password) < 6) {
            $error = "New password must be at least 6 characters long.";
        } elseif ($new_password !== $confirm_password) {
            $error = "New password and confirmation do not match.";
        } else {
            // Verify current password
            $pwd_stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $pwd_stmt->bind_param("i", $user_id);
            $pwd_stmt->execute();
            $pwd_res = $pwd_stmt->get_result()->fetch_assoc();
            $pwd_stmt->close();

            if (!$pwd_res || !password_verify($current_password, $pwd_res['password'])) {
                $error = "Your current password is incorrect.";
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                $update_pwd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update_pwd->bind_param("si", $hashed_password, $user_id);
                if ($update_pwd->execute()) {
                    $success = "Password changed successfully! 🔒";
                } else {
                    $error = "Failed to update password: " . $conn->error;
                }
                $update_pwd->close();
            }
        }
    }

    // ==========================================
    // 5. PRIVACY & PREFERENCES
    // ==========================================
    elseif ($action === 'update_privacy') {
        $active_tab = 'privacy';
        $is_private = isset($_POST['is_private']) && $_POST['is_private'] === '1' ? 1 : 0;
        
        $priv_stmt = $conn->prepare("UPDATE users SET is_private = ? WHERE id = ?");
        $priv_stmt->bind_param("ii", $is_private, $user_id);
        if ($priv_stmt->execute()) {
            $success = "Privacy preferences saved successfully! 🛡️";
        } else {
            $error = "Failed to save privacy settings.";
        }
        $priv_stmt->close();
    }

    // ==========================================
    // 6. DEACTIVATE ACCOUNT
    // ==========================================
    elseif ($action === 'deactivate_account') {
        $active_tab = 'danger';
        $deactivate_confirm = isset($_POST['confirm_deactivate']);
        
        if (!$deactivate_confirm) {
            $error = "Please check the confirmation box to deactivate your account.";
        } else {
            $deact_stmt = $conn->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
            $deact_stmt->bind_param("i", $user_id);
            $deact_stmt->execute();
            $deact_stmt->close();

            // Destroy session and redirect to login
            session_unset();
            session_destroy();
            header("Location: auth/login.php?deactivated=1");
            exit();
        }
    }

    // ==========================================
    // 7. DELETE ACCOUNT PERMANENTLY
    // ==========================================
    elseif ($action === 'delete_account') {
        $active_tab = 'danger';
        $delete_password = $_POST['delete_password'] ?? '';

        if (empty($delete_password)) {
            $error = "Password confirmation is required to delete your account.";
        } else {
            // Verify password
            $pwd_stmt = $conn->prepare("SELECT password, profile_picture FROM users WHERE id = ?");
            $pwd_stmt->bind_param("i", $user_id);
            $pwd_stmt->execute();
            $u_data = $pwd_stmt->get_result()->fetch_assoc();
            $pwd_stmt->close();

            if (!$u_data || !password_verify($delete_password, $u_data['password'])) {
                $error = "Incorrect password. Account deletion aborted.";
            } else {
                // Delete user's post images from disk
                $posts_stmt = $conn->prepare("SELECT image FROM posts WHERE user_id = ?");
                $posts_stmt->bind_param("i", $user_id);
                $posts_stmt->execute();
                $posts_res = $posts_stmt->get_result();
                while ($p = $posts_res->fetch_assoc()) {
                    deletePostImage($p['image']);
                }
                $posts_stmt->close();

                // Delete custom profile picture
                if (!empty($u_data['profile_picture']) && strpos($u_data['profile_picture'], 'default') === false) {
                    deleteProfilePicture($u_data['profile_picture']);
                }

                // Delete messages involving user
                $del_msg = $conn->prepare("DELETE FROM messages WHERE sender_id = ? OR recipient_id = ?");
                $del_msg->bind_param("ii", $user_id, $user_id);
                $del_msg->execute();
                $del_msg->close();

                // Delete notifications involving user
                $del_notif = $conn->prepare("DELETE FROM notifications WHERE user_id = ? OR recipient_id = ?");
                $del_notif->bind_param("ii", $user_id, $user_id);
                $del_notif->execute();
                $del_notif->close();

                // Delete user (posts, likes, comments, follows cascade automatically)
                $del_user = $conn->prepare("DELETE FROM users WHERE id = ?");
                $del_user->bind_param("i", $user_id);
                $del_user->execute();
                $del_user->close();

                // Clear session
                session_unset();
                session_destroy();
                header("Location: auth/login.php?deleted=1");
                exit();
            }
        }
    }
}

// Reload fresh user info
$user = getUserInfo($user_id, $conn);
$stats = getUserStats($user_id, $conn);

// Avatar path
$profile_pic = 'assets/images/default-profile.jpg';
if (!empty($user['profile_picture']) && file_exists("uploads/profile/" . $user['profile_picture']) && strpos($user['profile_picture'], 'default') === false) {
    $profile_pic = "uploads/profile/" . $user['profile_picture'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Settings - Pixora</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .settings-container {
            max-width: 1050px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .settings-header {
            text-align: center;
            margin-bottom: 35px;
        }

        .settings-title {
            font-size: 32px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary, #7c3aed), var(--secondary, #ec4899));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
        }

        .settings-subtitle {
            color: var(--gray, #6b7280);
            font-size: 15px;
        }

        .settings-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
            overflow: hidden;
            display: grid;
            grid-template-columns: 260px 1fr;
            border: 1px solid var(--border, #e5e7eb);
        }

        /* Sidebar Tabs */
        .settings-nav {
            background: #fafafa;
            border-right: 1px solid var(--border, #e5e7eb);
            padding: 25px 15px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .nav-tab {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 13px 18px;
            border-radius: 12px;
            color: var(--dark, #1f2937);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s ease;
            cursor: pointer;
            border: none;
            background: transparent;
            width: 100%;
            text-align: left;
        }

        .nav-tab:hover {
            background: var(--light, #f3f4f6);
            color: var(--primary, #7c3aed);
        }

        .nav-tab.active {
            background: linear-gradient(135deg, var(--primary, #7c3aed), var(--secondary, #ec4899));
            color: white;
            box-shadow: 0 4px 12px rgba(124, 58, 237, 0.25);
        }

        .nav-tab.danger-tab:hover {
            color: #ef4444;
            background: rgba(239, 68, 68, 0.08);
        }

        .nav-tab.danger-tab.active {
            background: linear-gradient(135deg, #ef4444, #b91c1c);
            color: white;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .nav-tab-icon {
            font-size: 18px;
        }

        /* Content Area */
        .settings-content {
            padding: 40px;
        }

        .tab-pane {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .tab-pane.active {
            display: block;
        }

        .section-title {
            font-size: 22px;
            font-weight: 800;
            color: var(--dark, #1f2937);
            margin-bottom: 8px;
        }

        .section-desc {
            color: var(--gray, #6b7280);
            font-size: 14px;
            margin-bottom: 30px;
            line-height: 1.5;
        }

        /* Form elements */
        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            font-weight: 700;
            font-size: 13px;
            color: var(--dark, #1f2937);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control {
            width: 100%;
            padding: 13px 16px;
            border: 2px solid var(--border, #e5e7eb);
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary, #7c3aed);
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
        }

        .form-help {
            font-size: 12px;
            color: var(--gray, #6b7280);
            margin-top: 6px;
            display: block;
        }

        /* Profile picture edit */
        .avatar-edit-box {
            display: flex;
            align-items: center;
            gap: 25px;
            margin-bottom: 35px;
            padding: 20px;
            background: var(--light, #f3f4f6);
            border-radius: 15px;
        }

        .avatar-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary, #7c3aed);
            box-shadow: var(--shadow-sm);
        }

        .avatar-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .btn-upload-avatar {
            position: relative;
            display: inline-block;
            padding: 10px 20px;
            background: linear-gradient(135deg, var(--primary, #7c3aed), var(--secondary, #ec4899));
            color: white;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700;
            font-size: 13px;
            text-align: center;
            transition: all 0.2s;
        }

        .btn-upload-avatar:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(124, 58, 237, 0.25);
        }

        .btn-upload-avatar input[type="file"] {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .btn-remove-avatar {
            background: none;
            border: 1px solid var(--border, #e5e7eb);
            padding: 8px 16px;
            border-radius: 8px;
            color: var(--danger, #ef4444);
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
            transition: all 0.2s;
        }

        .btn-remove-avatar:hover {
            background: rgba(239, 68, 68, 0.1);
            border-color: var(--danger, #ef4444);
        }

        .btn-save {
            padding: 13px 30px;
            background: linear-gradient(135deg, var(--primary, #7c3aed), var(--secondary, #ec4899));
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(124, 58, 237, 0.3);
        }

        .btn-danger-action {
            padding: 12px 26px;
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-danger-action:hover {
            background: #dc2626;
            box-shadow: 0 4px 14px rgba(239, 68, 68, 0.35);
        }

        .btn-warning-action {
            padding: 12px 26px;
            background: #f59e0b;
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-warning-action:hover {
            background: #d97706;
            box-shadow: 0 4px 14px rgba(245, 158, 11, 0.35);
        }

        /* Danger Box */
        .danger-panel {
            border: 2px dashed #fca5a5;
            background: #fef2f2;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 25px;
        }

        .danger-panel h3 {
            color: #991b1b;
            font-size: 17px;
            margin-bottom: 8px;
        }

        .danger-panel p {
            color: #7f1d1d;
            font-size: 13.5px;
            line-height: 1.5;
            margin-bottom: 18px;
        }

        /* Toggle switch */
        .toggle-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 0;
            border-bottom: 1px solid var(--border, #e5e7eb);
        }

        .toggle-info h4 {
            font-size: 15px;
            color: var(--dark, #1f2937);
            margin-bottom: 4px;
        }

        .toggle-info p {
            color: var(--gray, #6b7280);
            font-size: 13px;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 28px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .3s;
            border-radius: 28px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background: linear-gradient(135deg, var(--primary, #7c3aed), var(--secondary, #ec4899));
        }

        input:checked + .slider:before {
            transform: translateX(22px);
        }

        /* Summary Badges */
        .account-badge-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }

        .account-badge {
            background: var(--light, #f3f4f6);
            border-radius: 12px;
            padding: 15px;
            text-align: center;
        }

        .badge-value {
            font-size: 22px;
            font-weight: 800;
            color: var(--primary, #7c3aed);
        }

        .badge-label {
            font-size: 11px;
            text-transform: uppercase;
            font-weight: 700;
            color: var(--gray, #6b7280);
            margin-top: 4px;
        }

        @media (max-width: 800px) {
            .settings-card {
                grid-template-columns: 1fr;
            }

            .settings-nav {
                border-right: none;
                border-bottom: 1px solid var(--border, #e5e7eb);
                flex-direction: row;
                overflow-x: auto;
            }

            .nav-tab {
                white-space: nowrap;
            }

            .settings-content {
                padding: 25px 20px;
            }
        }
    </style>
</head>
<body>
    <!-- UNIFIED NAVBAR -->
    <?php include 'includes/navbar.php'; ?>

    <!-- SETTINGS CONTAINER -->
    <div class="settings-container">
        <div class="settings-header">
            <h1 class="settings-title">Account Settings</h1>
            <p class="settings-subtitle">Manage your profile, security, and account preferences</p>
        </div>

        <!-- ALERTS -->
        <?php if (!empty($success)): ?>
            <div class="alert alert-success" style="margin-bottom: 25px;">
                ✓ <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" style="margin-bottom: 25px;">
                ✗ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="settings-card">
            <!-- SIDEBAR NAV -->
            <div class="settings-nav">
                <button type="button" class="nav-tab <?php echo $active_tab === 'profile' ? 'active' : ''; ?>" onclick="switchTab('profile')">
                    <span class="nav-tab-icon">👤</span>
                    <span>Edit Profile</span>
                </button>
                <button type="button" class="nav-tab <?php echo $active_tab === 'account' ? 'active' : ''; ?>" onclick="switchTab('account')">
                    <span class="nav-tab-icon">✉️</span>
                    <span>Account & Username</span>
                </button>
                <button type="button" class="nav-tab <?php echo $active_tab === 'security' ? 'active' : ''; ?>" onclick="switchTab('security')">
                    <span class="nav-tab-icon">🔒</span>
                    <span>Password & Security</span>
                </button>
                <button type="button" class="nav-tab <?php echo $active_tab === 'privacy' ? 'active' : ''; ?>" onclick="switchTab('privacy')">
                    <span class="nav-tab-icon">🛡️</span>
                    <span>Privacy & Info</span>
                </button>
                <button type="button" class="nav-tab danger-tab <?php echo $active_tab === 'danger' ? 'active' : ''; ?>" onclick="switchTab('danger')">
                    <span class="nav-tab-icon">⚠️</span>
                    <span>Deactivate / Delete</span>
                </button>
            </div>

            <!-- TAB CONTENT -->
            <div class="settings-content">
                <!-- ================= TAB 1: PROFILE ================= -->
                <div class="tab-pane <?php echo $active_tab === 'profile' ? 'active' : ''; ?>" id="tab-profile">
                    <h2 class="section-title">Edit Profile Information</h2>
                    <p class="section-desc">Update your display name, bio, and profile picture shown to other users.</p>

                    <!-- Avatar Box -->
                    <div class="avatar-edit-box">
                        <img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="Avatar" class="avatar-preview" id="avatar-preview-img">
                        <div class="avatar-actions">
                            <label class="btn-upload-avatar">
                                📷 Change Photo
                                <input type="file" name="profile_picture" id="avatar-input" form="profile-form" accept=".jpg,.jpeg,.png,.gif">
                            </label>
                            <?php if (!empty($user['profile_picture']) && strpos($user['profile_picture'], 'default') === false): ?>
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="action" value="remove_picture">
                                    <button type="submit" class="btn-remove-avatar" onclick="return confirm('Remove your custom profile photo?');">
                                        🗑️ Remove Photo
                                    </button>
                                </form>
                            <?php endif; ?>
                            <small class="form-help">JPG, PNG, GIF up to 5MB</small>
                        </div>
                    </div>

                    <form method="POST" enctype="multipart/form-data" id="profile-form">
                        <input type="hidden" name="action" value="update_profile">

                        <div class="form-group">
                            <label for="full_name">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" 
                                   value="<?php echo htmlspecialchars($user['full_name']); ?>" required maxlength="100">
                            <small class="form-help">Your public display name</small>
                        </div>

                        <div class="form-group">
                            <label for="bio">Bio</label>
                            <textarea class="form-control" id="bio" name="bio" rows="4" maxlength="500" 
                                      placeholder="Write a short bio about yourself..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                            <small class="form-help" style="display: flex; justify-content: space-between;">
                                <span>Tell others about yourself or what you post</span>
                                <span><span id="bio-chars"><?php echo strlen($user['bio'] ?? ''); ?></span> / 500</span>
                            </small>
                        </div>

                        <button type="submit" class="btn-save">
                            💾 Save Profile Changes
                        </button>
                    </form>
                </div>

                <!-- ================= TAB 2: ACCOUNT ================= -->
                <div class="tab-pane <?php echo $active_tab === 'account' ? 'active' : ''; ?>" id="tab-account">
                    <h2 class="section-title">Account Details</h2>
                    <p class="section-desc">Manage your unique username handle and registered email address.</p>

                    <!-- Account Overview -->
                    <div class="account-badge-grid">
                        <div class="account-badge">
                            <div class="badge-value"><?php echo $stats['posts']; ?></div>
                            <div class="badge-label">Posts Shared</div>
                        </div>
                        <div class="account-badge">
                            <div class="badge-value"><?php echo $stats['followers']; ?></div>
                            <div class="badge-label">Followers</div>
                        </div>
                        <div class="account-badge">
                            <div class="badge-value"><?php echo $stats['following']; ?></div>
                            <div class="badge-label">Following</div>
                        </div>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="action" value="update_account">

                        <div class="form-group">
                            <label for="username">Username Handle</label>
                            <div style="position: relative;">
                                <span style="position: absolute; left: 16px; top: 13px; font-weight: 700; color: var(--gray, #6b7280);">@</span>
                                <input type="text" class="form-control" style="padding-left: 36px;" id="username" name="username" 
                                       value="<?php echo htmlspecialchars($user['username']); ?>" required minlength="3" maxlength="50"
                                       pattern="[a-zA-Z0-9_.]+" title="Letters, numbers, dots, and underscores only">
                            </div>
                            <small class="form-help">Your unique handle on Pixora (e.g. @pixora_user)</small>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            <small class="form-help">Used for logging in and account notifications</small>
                        </div>

                        <button type="submit" class="btn-save">
                            ✅ Update Account Info
                        </button>
                    </form>
                </div>

                <!-- ================= TAB 3: SECURITY ================= -->
                <div class="tab-pane <?php echo $active_tab === 'security' ? 'active' : ''; ?>" id="tab-security">
                    <h2 class="section-title">Password & Security</h2>
                    <p class="section-desc">Ensure your account is secure with a strong password.</p>

                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">

                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" 
                                   placeholder="••••••••" required>
                            <small class="form-help">Enter your current password to authorize changes</small>
                        </div>

                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" 
                                   placeholder="••••••••" required minlength="6">
                            <small class="form-help">Must be at least 6 characters long</small>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                   placeholder="••••••••" required minlength="6">
                            <small class="form-help">Re-type your new password to verify</small>
                        </div>

                        <button type="submit" class="btn-save">
                            🔒 Change Password
                        </button>
                    </form>
                </div>

                <!-- ================= TAB 4: PRIVACY ================= -->
                <div class="tab-pane <?php echo $active_tab === 'privacy' ? 'active' : ''; ?>" id="tab-privacy">
                    <h2 class="section-title">Privacy & Preferences</h2>
                    <p class="section-desc">Control your account visibility and view account metadata.</p>

                    <form method="POST">
                        <input type="hidden" name="action" value="update_privacy">

                        <div class="toggle-row">
                            <div class="toggle-info">
                                <h4>Private Account</h4>
                                <p>When your account is private, only users you approve can view your posts and details.</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="is_private" value="1" <?php echo !empty($user['is_private']) ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div class="toggle-row">
                            <div class="toggle-info">
                                <h4>Account Created</h4>
                                <p><?php echo date("F j, Y, g:i a", strtotime($user['created_at'])); ?> (<?php echo formatDate($user['created_at']); ?>)</p>
                            </div>
                        </div>

                        <div style="margin-top: 30px;">
                            <button type="submit" class="btn-save">
                                💾 Save Preferences
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ================= TAB 5: DANGER ZONE (DEACTIVATE / DELETE) ================= -->
                <div class="tab-pane <?php echo $active_tab === 'danger' ? 'active' : ''; ?>" id="tab-danger">
                    <h2 class="section-title" style="color: #991b1b;">⚠️ Account Management</h2>
                    <p class="section-desc">Temporarily deactivate your account or permanently delete your account and all associated data.</p>

                    <!-- Deactivate Panel -->
                    <div class="danger-panel" style="background: #fffbeb; border-color: #fcd34d;">
                        <h3 style="color: #92400e;">⏸️ Deactivate Account</h3>
                        <p style="color: #78350f;">
                            Temporarily disable your account. Your profile and posts will be hidden from other users. 
                            You can easily reactivate your account at any time simply by logging back in.
                        </p>
                        <form method="POST">
                            <input type="hidden" name="action" value="deactivate_account">
                            <div style="margin-bottom: 16px;">
                                <label style="display: flex; align-items: center; gap: 8px; font-size: 13.5px; color: #92400e; cursor: pointer; text-transform: none; font-weight: 600;">
                                    <input type="checkbox" name="confirm_deactivate" value="1" required style="width: 17px; height: 17px;">
                                    I understand that my account will be hidden and I will be logged out.
                                </label>
                            </div>
                            <button type="submit" class="btn-warning-action" onclick="return confirm('Are you sure you want to deactivate your account?');">
                                ⏸️ Deactivate My Account
                            </button>
                        </form>
                    </div>

                    <!-- Permanent Delete Panel -->
                    <div class="danger-panel">
                        <h3>🗑️ Delete Account Permanently</h3>
                        <p>
                            <strong>WARNING:</strong> This action is permanent and cannot be undone. All your posts, photos, comments, 
                            likes, followers, and direct messages will be deleted forever.
                        </p>
                        <form method="POST">
                            <input type="hidden" name="action" value="delete_account">
                            
                            <div class="form-group" style="max-width: 360px;">
                                <label for="delete_password" style="color: #991b1b;">Enter Password to Confirm</label>
                                <input type="password" class="form-control" id="delete_password" name="delete_password" 
                                       placeholder="Your current password" required style="border-color: #fca5a5;">
                            </div>

                            <button type="submit" class="btn-danger-action" onclick="return confirm('CRITICAL WARNING: This will permanently delete your account and all your data. This CANNOT be undone. Are you absolutely certain?');">
                                🗑️ Delete My Account Permanently
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- FOOTER -->
    <footer style="background: var(--dark, #1f2937); color: white; padding: 40px 20px; text-align: center; margin-top: 60px;">
        <div class="container">
            <p style="margin-bottom: 10px;">© 2024 <strong>Pixora</strong> - Your Premium Social Media Platform</p>
            <p style="color: var(--gray, #6b7280); font-size: 13px;">Built with ❤️ using PHP & MySQL</p>
        </div>
    </footer>

    <!-- SCRIPT -->
    <script>
        // Tab Switching
        function switchTab(tabName) {
            document.querySelectorAll('.tab-pane').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.nav-tab').forEach(el => el.classList.remove('active'));

            const targetPane = document.getElementById('tab-' + tabName);
            if (targetPane) {
                targetPane.classList.add('active');
            }

            const tabs = document.querySelectorAll('.nav-tab');
            tabs.forEach(tab => {
                if (tab.getAttribute('onclick') && tab.getAttribute('onclick').includes(tabName)) {
                    tab.classList.add('active');
                }
            });

            // Update URL without reload
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.replaceState({}, '', url);
        }

        // Live bio character count
        const bioInput = document.getElementById('bio');
        const bioChars = document.getElementById('bio-chars');
        if (bioInput && bioChars) {
            bioInput.addEventListener('input', function() {
                bioChars.textContent = this.value.length;
            });
        }

        // Instant Avatar Preview
        const avatarInput = document.getElementById('avatar-input');
        const avatarPreviewImg = document.getElementById('avatar-preview-img');
        if (avatarInput && avatarPreviewImg) {
            avatarInput.addEventListener('change', function(e) {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        avatarPreviewImg.src = e.target.result;
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
    </script>
</body>
</html>
