<?php
session_start();
require 'db.php';

$message = '';
$message_class = '';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?error=You must log in first.");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];

    // التحقق من صحة كلمة المرور الجديدة
    if (strlen($new_password) < 8 || !preg_match('/[A-Z]/', $new_password) || !preg_match('/[a-z]/', $new_password) || !preg_match('/[0-9]/', $new_password) || !preg_match('/[!@#$%^&*]/', $new_password)) {
        $message = "Password must be at least 8 characters long and include uppercase, lowercase, number, and special character.";
        $message_class = 'error';
    } else {
        // جلب بيانات المستخدم
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if ($user && password_verify($current_password, $user['password_hash'])) {
            // تحديث كلمة المرور الجديدة
            $new_password_hash = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$new_password_hash, $_SESSION['user_id']]);

            $message = "Password updated successfully!";
            $message_class = 'success';
        } else {
            $message = "Invalid current password.";
            $message_class = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password</title>
    <link rel="stylesheet" href="assets/css/change_password.css">
    <script src="assets/js/main.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <?php require 'header.php'; ?>

    <div class="container">
        <h1><i class="fas fa-key"></i> Change Password</h1>

        <?php if ($message): ?>
            <div class="message <?php echo $message_class; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="change_password.php">
            <label for="current_password"><i class="fas fa-lock"></i> Current Password:</label>
            <input type="password" id="current_password" name="current_password" required><br>

            <label for="new_password"><i class="fas fa-lock"></i> New Password:</label>
            <input type="password" id="new_password" name="new_password" required><br>
            <small>Password must be at least 8 characters long and include uppercase, lowercase, number, and special character.</small><br>

            <button type="submit"><i class="fas fa-save"></i> Save Changes</button>
        </form>

        <a href="profile.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Profile</a>
    </div>
</body>
</html>