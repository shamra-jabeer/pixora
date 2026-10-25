<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

require 'config/database.php';
require 'includes/helpers.php';

$user_id = $_SESSION['user_id'];
$user = getUserInfo($user_id, $conn);
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $profile_picture = null;

    // Validate inputs
    if (empty($full_name)) {
        $error = "Full name is required";
    } elseif (strlen($bio) > 500) {
        $error = "Bio cannot exceed 500 characters";
    } else {
        // Handle profile picture upload
        if (!empty($_FILES['profile_picture']['name'])) {
            if (validateImage($_FILES['profile_picture'])) {
                // Delete old profile picture if exists
                if (!empty($user['profile_picture']) && $user['profile_picture'] !== 'default-profile.jpg') {
                    deleteProfilePicture($user['profile_picture']);
                }
                // Upload new profile picture
                $profile_picture = uploadProfilePicture($_FILES['profile_picture'], $user_id);
                if (!$profile_picture) {
                    $error = "Failed to upload profile picture";
                }
            } else {
                $error = "Invalid image format or size. Allowed: JPG, PNG, GIF (Max 5MB)";
            }
        }

        // Update database if no errors
        if (empty($error)) {
            $query = "UPDATE users SET full_name = ?, bio = ?";
            $params = [$full_name, $bio];
            $types = "ss";

            if ($profile_picture) {
                $query .= ", profile_picture = ?";
                $params[] = $profile_picture;
                $types .= "s";
            }

            $query .= " WHERE id = ?";
            $params[] = $user_id;
            $types .= "i";

            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                // Update session
                $_SESSION['full_name'] = $full_name;
                $success = "Profile updated successfully! 🎉";
                $user = getUserInfo($user_id, $conn);
            } else {
                $error = "Database error: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

$profile_pic = 'assets/images/default-profile.jpg';
if (!empty($user['profile_picture']) && file_exists("uploads/profile/" . $user['profile_picture'])) {
    $profile_pic = "uploads/profile/" . $user['profile_picture'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Pixora</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <!-- EDIT PROFILE CONTAINER -->
    <div class="edit-container">
        <h2 class="edit-title">Edit Your Profile</h2>
        <div style="text-align: center; margin-bottom: 25px;"><a href="settings.php" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 22px; font-weight: 700; text-decoration: none; border-radius: 25px; border: 2px solid var(--primary); color: var(--primary); background: white;">⚙️ Manage Account, Password & Privacy in Settings →</a></div>

        <!-- ALERTS -->
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">✓ <?php echo $success; ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">✗ <?php echo $error; ?></div>
        <?php endif; ?>

        <!-- FORM BOX -->
        <div class="form-box">
            <form method="POST" enctype="multipart/form-data">
                
                <!-- PROFILE PICTURE SECTION -->
                <div class="profile-pic-edit">
                    <img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile Picture" class="profile-pic-preview">
                    
                    <div class="file-input-wrapper">
                        <input type="file" id="profile_picture" name="profile_picture" accept=".jpg,.jpeg,.png,.gif">
                        <label for="profile_picture" class="file-input-label">📷 Choose Photo</label>
                    </div>
                    <p class="file-name">JPG, PNG, or GIF • Max 5MB</p>
                </div>

                <!-- FULL NAME -->
                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" 
                           value="<?php echo htmlspecialchars($user['full_name']); ?>" 
                           required maxlength="100">
                    <small class="text-muted">Your display name (1-100 characters)</small>
                </div>

                <!-- BIO -->
                <div class="form-group">
                    <label for="bio">Bio</label>
                    <textarea id="bio" name="bio" rows="6" maxlength="500" 
                              placeholder="Tell us about yourself..."><?php echo htmlspecialchars($user['bio']); ?></textarea>
                    <small class="text-muted" id="bio-counter">
                        <span id="bio-length"><?php echo strlen($user['bio']); ?></span> / 500 characters
                    </small>
                </div>

                <!-- ACTION BUTTONS -->
                <div class="button-group">
                    <button type="submit" class="btn btn-success">💾 Save Changes</button>
                    <a href="profile.php" class="btn btn-secondary" style="color: var(--primary); background: var(--light); border: 2px solid var(--primary);">← Back to Profile</a>
                </div>

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

    <!-- JAVASCRIPT -->
    <script>
        // Profile picture preview
        const fileInput = document.getElementById('profile_picture');
        if (fileInput) {
            fileInput.addEventListener('change', function(e) {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        document.querySelector('.profile-pic-preview').src = e.target.result;
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        // Bio character counter
        const bioTextarea = document.getElementById('bio');
        if (bioTextarea) {
            bioTextarea.addEventListener('input', function() {
                document.getElementById('bio-length').textContent = this.value.length;
            });
        }
    </script>

</body>
</html>