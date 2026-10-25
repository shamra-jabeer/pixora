<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

require 'config/database.php';
require 'includes/helpers.php';

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $caption = trim($_POST['caption'] ?? '');
    $image_file = $_FILES['image'] ?? null;

    // Validation
    if (empty($image_file) || $image_file['error'] === UPLOAD_ERR_NO_FILE) {
        $error = "Please select an image to upload";
    } elseif (empty($caption)) {
        $error = "Please add a caption";
    } elseif (strlen($caption) > 500) {
        $error = "Caption cannot exceed 500 characters";
    } else {
        // Validate image
        if (validateImage($image_file)) {
            // Upload image
            $image_filename = uploadPostImage($image_file, $user_id);
            
            if ($image_filename) {
                // Save post to database
                $image_path = "uploads/posts/" . $image_filename;
                $stmt = $conn->prepare(
                    "INSERT INTO posts (user_id, image, caption, created_at, updated_at) 
                     VALUES (?, ?, ?, NOW(), NOW())"
                );
                $stmt->bind_param("iss", $user_id, $image_path, $caption);

                if ($stmt->execute()) {
                    $success = "Post created successfully! 🎉";
                    // Clear form
                    $caption = '';
                    // Redirect after 2 seconds
                    header("Refresh: 2; url=profile.php");
                } else {
                    $error = "Failed to save post. Please try again.";
                }
                $stmt->close();
            } else {
                $error = "Failed to upload image. Please try again.";
            }
        } else {
            $error = "Invalid image format or size. Allowed: JPG, PNG, GIF (Max 5MB)";
        }
    }
}

$full_name = htmlspecialchars($_SESSION['full_name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Post - Pixora</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .image-preview-container {
            position: relative;
            width: 100%;
            max-width: 500px;
            margin: 30px auto;
            background: var(--light);
            border-radius: 16px;
            padding: 40px 20px;
            text-align: center;
            border: 3px dashed var(--primary);
            transition: all 0.3s ease;
        }

        .image-preview-container:hover {
            border-color: var(--secondary);
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.05), rgba(236, 72, 153, 0.05));
        }

        .image-preview-container.has-image {
            border: none;
            padding: 0;
            overflow: hidden;
        }

        .image-preview {
            width: 100%;
            max-height: 400px;
            object-fit: cover;
            border-radius: 16px;
            display: none;
        }

        .image-preview.show {
            display: block;
        }

        .upload-icon {
            font-size: 48px;
            margin-bottom: 20px;
        }

        .upload-text {
            color: var(--gray);
            font-size: 14px;
            margin: 10px 0;
        }

        .upload-text strong {
            color: var(--dark);
            font-weight: 700;
        }

        .file-input-wrapper input[type="file"] {
            display: none;
        }

        .file-input-label {
            display: inline-block;
            padding: 12px 28px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 700;
            transition: all 0.3s ease;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 0.5px;
        }

        .file-input-label:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .remove-image-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--danger);
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 700;
            transition: all 0.3s ease;
            z-index: 10;
        }

        .remove-image-btn:hover {
            background: #dc2626;
            transform: scale(1.1);
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <!-- CREATE POST CONTAINER -->
    <div class="edit-container">
        <h2 class="edit-title">Create a New Post</h2>

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
                
                <!-- IMAGE UPLOAD SECTION -->
                <div class="image-preview-container" id="imageContainer">
                    <div id="uploadPrompt">
                        <div class="upload-icon">📸</div>
                        <p class="upload-text">
                            <strong>Click to upload your image</strong>
                        </p>
                        <p class="upload-text">or drag and drop</p>
                        <p class="upload-text">JPG, PNG, or GIF • Max 5MB</p>
                        
                        <div class="file-input-wrapper">
                            <input type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.gif" required>
                            <label for="image" class="file-input-label">📤 Choose Image</label>
                        </div>
                    </div>

                    <img id="imagePreview" class="image-preview" alt="Preview">
                    <button type="button" id="removeImageBtn" class="remove-image-btn" style="display: none;">✕ Remove</button>
                </div>

                <!-- CAPTION -->
                <div class="form-group">
                    <label for="caption">Caption</label>
                    <textarea id="caption" name="caption" rows="5" maxlength="500" 
                              placeholder="Write something about your post..."><?php echo htmlspecialchars($caption ?? ''); ?></textarea>
                    <small class="text-muted" id="caption-counter">
                        <span id="caption-length">0</span> / 500 characters
                    </small>
                </div>

                <!-- ACTION BUTTONS -->
                <div class="button-group">
                    <button type="submit" class="btn btn-success">🚀 Post Now</button>
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
        const imageInput = document.getElementById('image');
        const imagePreview = document.getElementById('imagePreview');
        const imageContainer = document.getElementById('imageContainer');
        const uploadPrompt = document.getElementById('uploadPrompt');
        const removeImageBtn = document.getElementById('removeImageBtn');
        const captionTextarea = document.getElementById('caption');

        // Image preview
        imageInput.addEventListener('change', function(e) {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.src = e.target.result;
                    imagePreview.classList.add('show');
                    uploadPrompt.style.display = 'none';
                    removeImageBtn.style.display = 'block';
                    imageContainer.classList.add('has-image');
                };
                reader.readAsDataURL(file);
            }
        });

        // Remove image
        removeImageBtn.addEventListener('click', function(e) {
            e.preventDefault();
            imageInput.value = '';
            imagePreview.src = '';
            imagePreview.classList.remove('show');
            uploadPrompt.style.display = 'block';
            removeImageBtn.style.display = 'none';
            imageContainer.classList.remove('has-image');
        });

        // Caption character counter
        if (captionTextarea) {
            captionTextarea.addEventListener('input', function() {
                document.getElementById('caption-length').textContent = this.value.length;
            });
            // Initialize counter on page load
            document.getElementById('caption-length').textContent = captionTextarea.value.length;
        }

        // Drag and drop
        const dropZone = imageContainer;
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });

        function highlight(e) {
            dropZone.style.borderColor = 'var(--secondary)';
            dropZone.style.backgroundColor = 'rgba(236, 72, 153, 0.1)';
        }

        function unhighlight(e) {
            dropZone.style.borderColor = 'var(--primary)';
            dropZone.style.backgroundColor = '';
        }

        dropZone.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            imageInput.files = files;
            
            // Trigger change event
            const event = new Event('change', { bubbles: true });
            imageInput.dispatchEvent(event);
        }
    </script>

</body>
</html>