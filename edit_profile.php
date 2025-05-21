<?php
$title = "Edit Profile";
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?error=You must log in first.");
    exit;
}

$message = '';
$message_class = '';

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    die("User not found.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $birthday = trim($_POST['birthday']);
    $gender = trim($_POST['gender']);
    $location = trim($_POST['location']);
    $current_password = $_POST['current_password'] ?? '';

    $errors = [];

    if (empty($username)) {
        $errors[] = "Username is required.";
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $errors[] = "Username must be between 3 and 50 characters.";
    }

    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    if (!empty($birthday) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday)) {
        $errors[] = "Invalid birthday format. Use YYYY-MM-DD.";
    }

    if (!empty($gender) && !in_array($gender, ['male', 'female', 'other'])) {
        $errors[] = "Invalid gender selection.";
    }

    if ($username !== $user['username'] || $email !== $user['email']) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $stmt->execute([$username, $email, $_SESSION['user_id']]);
        if ($stmt->fetch()) {
            $errors[] = "Username or email already exists.";
        }
    }

    if ($email !== $user['email'] && empty($current_password)) {
        $errors[] = "Current password is required to change email.";
    }

    if (!empty($current_password)) {
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_check = $stmt->fetch();
        
        if (!$user_check || !password_verify($current_password, $user_check['password_hash'])) {
            $errors[] = "Current password is incorrect.";
        }
    }

    $avatar_url = $user['avatar_url'];
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB

        if (!in_array($_FILES['avatar']['type'], $allowed_types)) {
            $errors[] = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
        } elseif ($_FILES['avatar']['size'] > $max_size) {
            $errors[] = "File is too large. Maximum size is 5MB.";
        } else {
            $upload_dir = 'uploads/avatars/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $new_filename = $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
                if ($user['avatar_url'] && file_exists($user['avatar_url'])) {
                    unlink($user['avatar_url']);
                }
                $avatar_url = $upload_path;
            } else {
                $errors[] = "Failed to upload avatar.";
            }
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, birthday = ?, gender = ?, location = ?, avatar_url = ? WHERE id = ?");
            $stmt->execute([$username, $email, $birthday, $gender, $location, $avatar_url, $_SESSION['user_id']]);

            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;
            $_SESSION['avatar_url'] = $avatar_url;
            
            $_SESSION['user_data'] = [
                'birthday' => $birthday,
                'gender' => $gender,
                'location' => $location
            ];

            $message = "Profile updated successfully!";
            $message_class = 'success';

            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
        } catch (PDOException $e) {
            $message = "Failed to update profile. Please try again.";
            $message_class = 'error';
        }
    } else {
        $message = implode("<br>", $errors);
        $message_class = 'error';
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> | Secure Storage</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2B4D40;
            --secondary-color: #6a8279;
            --success-color: #10B981;
            --warning-color: #F59E0B;
            --danger-color: #EF4444;
            --dark-color: #1F2937;
            --light-color: #F3F4F6;
        }

        /* Navbar styles */
        .main-header, nav.bg-white {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)) !important;
            color: white;
        }

        .main-header .logo, .main-header .nav-links a,
        nav .text-gray-900, nav .text-gray-600 {
            color: white !important;
        }

        .main-header .btn-outline, nav .btn-outline {
            border: 2px solid white;
            color: white;
        }

        .main-header .btn-outline:hover, nav .btn-outline:hover {
            background-color: white;
            color: var(--primary-color);
        }

        .main-header .btn-primary, nav .btn-primary {
            background-color: white;
            color: var(--primary-color);
        }

        .main-header .btn-primary:hover, nav .btn-primary:hover {
            background-color: rgba(255, 255, 255, 0.9);
            color: var(--primary-color);
        }

        /* Existing styles for edit_profile.php */
        .edit-profile-box {
            max-width: 600px;
            margin: 30px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(43, 77, 64, 0.2);
        }

        .edit-profile-box h1 {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }

        .avatar-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .current-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-color); /* Use primary color for border */
            margin-bottom: 15px;
            display: block;
            margin: 0 auto 15px auto; /* Center block element */
        }

        .avatar-upload-label {
            display: inline-block;
            padding: 8px 16px;
            background: var(--primary-color); /* Use primary color for button */
            color: white;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .avatar-upload-label:hover {
            background: var(--secondary-color); /* Use secondary color on hover */
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: bold;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }

        .form-group small {
            display: block;
            color: #666;
            margin-top: 5px;
            font-size: 0.9em;
        }

        .form-actions {
            text-align: center;
            margin-top: 30px;
        }

        .save-btn,
        .cancel-btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 0 10px;
            border-radius: 5px;
            font-weight: bold;
            text-decoration: none;
            transition: background 0.3s, color 0.3s;
        }

        .save-btn {
            background-color: var(--primary-color); /* Use primary color for save button */
            color: white;
            border: none;
        }

        .save-btn:hover {
            background-color: var(--secondary-color); /* Use secondary color on hover */
        }

        .cancel-btn {
            background-color: #ccc; /* Gray background for cancel */
            color: #333;
            border: none;
        }

        .cancel-btn:hover {
            background-color: #bbb;
        }

        /* Message styles */
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-weight: bold;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col">
    <!-- Navbar -->
    <nav class="bg-white shadow-lg">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <i class="fas fa-shield-alt text-2xl text-white mr-2"></i>
                    <span class="text-xl font-bold text-white">Auth System</span>
                </div>
                <div class="flex items-center space-x-4">
                    <?php if (isset($_SESSION['user_id'])): // Check if user is logged in ?>
                        <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): // Check if admin ?>
                            <a href="admin.php" class="btn btn-primary">
                                <i class="fas fa-users-cog"></i> Admin Panel
                            </a>
                        <?php endif; ?>
                        <a href="logout.php" class="btn btn-outline">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </a>
                        <a href="signup.php" class="btn btn-outline">
                            <i class="fas fa-user-plus"></i> Sign Up
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8 flex-grow flex items-center justify-center">
        <div class="w-full">
            <div class="edit-profile-box">
                <h1><i class="fas fa-user-edit"></i> Edit Profile</h1>

                <?php if ($message): ?>
                    <div class="message <?php echo $message_class; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="edit_profile.php" enctype="multipart/form-data" class="edit-profile-form">
                    <div class="avatar-section">
                        <img src="<?php echo htmlspecialchars($user['avatar_url'] ?? 'assets/images/default-avatar.png'); ?>"
                            alt="Profile Picture" class="current-avatar" id="avatar-preview">
                        <div class="avatar-upload">
                            <label for="avatar" class="avatar-upload-label">
                                <i class="fas fa-camera"></i> Change Avatar
                            </label>
                            <input type="file" id="avatar" name="avatar" accept="image/*" style="display: none;">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="username"><i class="fas fa-user"></i> Username:</label>
                        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Email:</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="birthday"><i class="fas fa-birthday-cake"></i> Birthday:</label>
                        <input type="date" id="birthday" name="birthday" value="<?php echo htmlspecialchars($user['birthday'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="gender"><i class="fas fa-venus-mars"></i> Gender:</label>
                        <select id="gender" name="gender">
                            <option value="">Select Gender</option>
                            <option value="male" <?php echo ($user['gender'] === 'male') ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo ($user['gender'] === 'female') ? 'selected' : ''; ?>>Female</option>
                            <option value="other" <?php echo ($user['gender'] === 'other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="location"><i class="fas fa-map-marker-alt"></i> Location:</label>
                        <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($user['location'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="current_password"><i class="fas fa-lock"></i> Current Password (required for email change):</label>
                        <input type="password" id="current_password" name="current_password">
                        <small>Only required if you're changing your email address</small>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="save-btn"><i class="fas fa-save"></i> Save Changes</button>
                        <a href="profile.php" class="cancel-btn"><i class="fas fa-times"></i> Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Avatar preview
        document.getElementById('avatar').addEventListener('change', function(event) {
            const reader = new FileReader();
            reader.onload = function() {
                const output = document.getElementById('avatar-preview');
                output.src = reader.result;
            };
            reader.readAsDataURL(event.target.files[0]);
        });
    </script>
</body>
</html> 