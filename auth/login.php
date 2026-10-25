<?php
session_start();

// If already logged in, redirect to home
if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require '../config/database.php';

$error = '';
$info = '';

if (isset($_GET['deactivated'])) {
    $info = "Your account has been deactivated. Sign in below anytime to reactivate it!";
} elseif (isset($_GET['deleted'])) {
    $info = "Your account and all associated data have been permanently deleted.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($login) || empty($password)) {
        $error = "Username/email and password are required";
    } else {
        // Prepare statement - check both username and email
        $stmt = $conn->prepare("SELECT id, username, email, full_name, password, is_active FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $login, $login);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Verify password
            if (password_verify($password, $user['password'])) {
                // If account was deactivated, reactivate it
                if (isset($user['is_active']) && (int)$user['is_active'] === 0) {
                    $reactivate_stmt = $conn->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
                    $reactivate_stmt->bind_param("i", $user['id']);
                    $reactivate_stmt->execute();
                    $reactivate_stmt->close();
                }

                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['full_name'] = $user['full_name'];

                // Redirect to home
                header("Location: ../index.php");
                exit();
            } else {
                $error = "Invalid password";
            }
        } else {
            $error = "User not found";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - Pixora</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <!-- LOGO -->
            <div class="logo">
                <h1>Pixora</h1>
                <p>Welcome back! Sign in to your account</p>
            </div>

            <!-- ALERTS -->
            <?php if (!empty($info)): ?>
                <div class="alert alert-success" style="background: rgba(16, 185, 129, 0.15); color: #065f46; border: 1px solid #10b981; margin-bottom: 20px; padding: 12px; border-radius: 8px;">
                    ℹ️ <?php echo htmlspecialchars($info); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    ✗ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- LOGIN FORM -->
            <form method="POST" action="">
                
                <!-- Username or Email -->
                <div class="form-group">
                    <label for="login">Username or Email</label>
                    <input type="text" id="login" name="login" 
                           value="<?php echo htmlspecialchars($_POST['login'] ?? ''); ?>"
                           placeholder="johndoe or john@example.com"
                           required autofocus>
                    <small class="text-muted">Enter your username or email address</small>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" 
                           placeholder="••••••••"
                           required>
                    <small class="text-muted">Enter your password</small>
                </div>

                <!-- Sign In Button -->
                <button type="submit" class="btn btn-primary">Sign In 🔓</button>
            </form>

            <!-- FOOTER -->
            <div class="auth-footer">
                <p>Don't have an account? <a href="register.php">Sign Up</a></p>
            </div>
        </div>
    </div>
</body>
</html>
