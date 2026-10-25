<?php
session_start();

// If already logged in, redirect to home
if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require '../config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($full_name) || empty($username) || empty($email) || empty($password)) {
        $error = "All fields are required";
    } elseif (strlen($full_name) < 2 || strlen($full_name) > 100) {
        $error = "Full name must be 2-100 characters";
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $error = "Username must be 3-50 characters";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } else {
        // Check if username or email already exists
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check_stmt->bind_param("ss", $username, $email);
        $check_stmt->execute();
        $result = $check_stmt->get_result();

        if ($result->num_rows > 0) {
            $error = "Username or email already registered";
        } else {
            // Hash password and insert user
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $insert_stmt = $conn->prepare(
                "INSERT INTO users (full_name, username, email, password) VALUES (?, ?, ?, ?)"
            );
            $insert_stmt->bind_param("ssss", $full_name, $username, $email, $hashed_password);

            if ($insert_stmt->execute()) {
                $success = "Registration successful! Redirecting to login...";
                // Redirect to login after 2 seconds
                header("Refresh: 2; url=login.php");
            } else {
                $error = "Registration failed. Please try again.";
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Pixora</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <!-- LOGO -->
            <div class="logo">
                <h1>Pixora</h1>
                <p>Create your account and start sharing</p>
            </div>

            <!-- ALERTS -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    ✗ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    ✓ <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <!-- REGISTRATION FORM -->
            <form method="POST" action="">
                
                <!-- Full Name -->
                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" 
                           value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>"
                           placeholder="John Doe"
                           maxlength="100" required>
                    <small class="text-muted">2-100 characters</small>
                </div>

                <!-- Username -->
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                           placeholder="johndoe123"
                           maxlength="50" required>
                    <small class="text-muted">3-50 characters, no spaces</small>
                </div>

                <!-- Email -->
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                           placeholder="john@example.com"
                           required>
                    <small class="text-muted">We'll send a confirmation email</small>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" 
                           placeholder="••••••••"
                           minlength="6" required>
                    <small class="text-muted">Minimum 6 characters</small>
                </div>

                <!-- Confirm Password -->
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" 
                           placeholder="••••••••"
                           minlength="6" required>
                    <small class="text-muted">Must match your password</small>
                </div>

                <!-- Sign Up Button -->
                <button type="submit" class="btn btn-primary">Create Account 🚀</button>
            </form>

            <!-- Login Link -->
            <div class="auth-link">
                Already have an account? <a href="login.php">Sign in here</a>
            </div>
        </div>
    </div>
</body>
</html>