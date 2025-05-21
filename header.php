<?php
// التحقق مما إذا كانت الجلسة قد بدأت بالفعل
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title ?? 'Auth System'; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/main.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <!-- شريط التنقل دائم الظهور -->
    <nav class="navbar">
        <div class="container">
            <a href="index.php" class="logo"><i class="fas fa-home"></i> <?php echo $title ?? 'Auth System'; ?></a>
            <div class="nav-links">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a>
                    <?php if ($_SESSION['is_admin'] ?? false): ?>
                        <a href="admin.php"><i class="fas fa-users-cog"></i> Admin Panel</a>
                    <?php endif; ?>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                <?php else: ?>
                    <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
                    <a href="signup.php"><i class="fas fa-user-plus"></i> Signup</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- محتوى الصفحة -->
    <div class="container">