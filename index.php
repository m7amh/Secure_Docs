<?php
session_start();
require_once 'db.php';

// التحقق من تسجيل الدخول
$isLoggedIn = isset($_SESSION['user_id']);
$username = $isLoggedIn ? $_SESSION['username'] : '';
$isAdmin = $isLoggedIn && isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
$avatarUrl = $_SESSION['avatar_url'] ?? 'assets/css/defaultPFP/default.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auth System - Home</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
        
        body {
            background-color: #F9FAFB;
            font-family: 'Inter', sans-serif;
        }
        
        .hero-section {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 4rem 0;
            margin-bottom: 4rem;
        }
        
        .action-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            padding: 1.5rem;
            text-align: center;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        
        .action-card i {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }
        
        .profile-section {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary-color);
            transition: transform 0.3s ease;
        }
        
        .avatar:hover {
            transform: scale(1.05);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.2s;
            margin: 0.5rem;
        }
        
        .btn i {
            margin-right: 0.5rem;
        }
        
        .btn-primary {
            background-color: white;
            color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: rgba(255, 255, 255, 0.9);
            color: var(--primary-color);
        }
        
        .btn-outline {
            border: 2px solid white;
            color: white;
        }
        
        .btn-outline:hover {
            background-color: white;
            color: var(--primary-color);
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #DC2626;
            transform: translateY(-2px);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in {
            animation: fadeIn 0.5s ease-out;
        }

        /* Explicitly set header text color to light gray for index page */
        .main-header .logo,
        .main-header .nav-links a {
            color: rgb(85, 19, 19) !important;
        }

        /* Navbar styles */
        nav.bg-white {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)) !important;
            color: white;
        }

        nav .text-gray-900,
        nav .text-gray-600 {
            color: white !important;
        }

        nav .btn-outline {
            border: 2px solid white;
            color: white;
        }

        nav .btn-outline:hover {
            background-color: white;
            color: var(--primary-color);
        }

        nav .btn-primary {
            background-color: white;
            color: var(--primary-color);
        }

        nav .btn-primary:hover {
            background-color: rgba(255, 255, 255, 0.9);
            color: var(--primary-color);
        }

        /* Footer styles */
        footer.main-footer {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1rem 0;
        }

        footer.main-footer h3 {
            color: white;
        }

        footer.main-footer a {
            color: rgba(255, 255, 255, 0.8);
            transition: color 0.3s ease;
        }

        footer.main-footer a:hover {
            color: white;
        }

        footer.main-footer .social-links a {
            color: white;
            margin-right: 1rem;
            font-size: 1.5rem;
            transition: color 0.3s ease;
        }

        footer.main-footer .social-links a:hover {
            color: rgba(255, 255, 255, 0.8);
        }

        footer.main-footer .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            text-align: center;
            color: rgba(255, 255, 255, 0.7);
        }

        footer.main-footer .footer-content {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            margin-bottom: 1.5rem;
        }

        footer.main-footer .footer-section {
            margin: 1rem;
            /*flex: 1;*/ /* Removed flex-grow to control width better*/
            min-width: 200px;
        }

        footer.main-footer ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        footer.main-footer ul li {
            margin-bottom: 0.5rem;
        }

        footer.main-footer ul li a {
            text-decoration: none;
        }

        /* Style for the Connect With Us section to push it right */
        footer.main-footer .footer-section:last-child {
            margin-left: auto; /* Push the last section to the right */
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
                    <?php if ($isLoggedIn): ?>
                        <?php if ($isAdmin): ?>
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
        <?php if ($isLoggedIn): ?>
            <!-- Wrapper for logged-in content to center vertically -->
            <div class="w-full">
                <!-- Profile Section -->
                <div class="profile-section animate-fade-in">
                    <div class="flex flex-col md:flex-row items-center justify-between">
                        <div class="flex items-center mb-4 md:mb-0">
                           <a href="profile.php"> <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="Profile Picture" class="avatar"></a>
                            <div class="ml-4">
                                <h2 class="text-2xl font-bold text-gray-900">Welcome back, <?php echo htmlspecialchars($username); ?>!</h2>
                                <p class="text-gray-600">You are now logged in successfully.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <a href="profile.php" class="action-card animate-fade-in" style="animation-delay: 0.1s">
                        <i class="fas fa-user-circle"></i>
                        <h3 class="text-xl font-bold mb-2">Profile</h3>
                        <p class="text-gray-600">Manage your account settings</p>
                    </a>

                    <a href="upload.php" class="action-card animate-fade-in" style="animation-delay: 0.2s">
                        <i class="fas fa-upload"></i>
                        <h3 class="text-xl font-bold mb-2">Upload Files</h3>
                        <p class="text-gray-600">Upload and manage your files</p>
                    </a>

                    <a href="files.php" class="action-card animate-fade-in" style="animation-delay: 0.3s">
                        <i class="fas fa-file"></i>
                        <h3 class="text-xl font-bold mb-2">My Files</h3>
                        <p class="text-gray-600">View and manage your files</p>
                    </a>

                    <?php if ($isAdmin): ?>
                    <a href="admin.php" class="action-card animate-fade-in" style="animation-delay: 0.4s">
                        <i class="fas fa-users-cog"></i>
                        <h3 class="text-xl font-bold mb-2">Admin Panel</h3>
                        <p class="text-gray-600">Manage system settings</p>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

        <?php else: ?>
            <!-- Hero Section for non-logged in users -->
            <section class="hero-section">
                <div class="container mx-auto px-4 text-center">
                    <h1 class="text-4xl md:text-6xl font-bold mb-4 animate-fade-in">Welcome to Auth System</h1>
                    <p class="text-xl md:text-2xl mb-8 opacity-90 animate-fade-in" style="animation-delay: 0.2s">
                        Secure authentication system with advanced features
                    </p>
                    <div class="animate-fade-in" style="animation-delay: 0.4s">
                        <a href="signup.php" class="btn btn-primary text-lg px-8 py-3">
                            <i class="fas fa-user-plus"></i> Get Started
                        </a>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <script>
        // Add smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });

        // Add hover effects to cards
        document.querySelectorAll('.action-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>
<?php include 'includes/footer.php'; ?>