<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title ?? 'Auth System'; ?></title>
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
             /* Add global body styles if needed, e.g., font-family */
            font-family: 'Inter', sans-serif;
             /* Prevent footer from jumping */
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .main-content {
            flex: 1; /* Allows the main content to grow and push the footer down */
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

        /* Optional: Style for the footer if it's included after main content */
        /* These styles might be in footer.php or a global CSS file */
        .main-footer {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1rem 0;
            margin-top: auto; /* Push footer to the bottom */
        }

        .main-footer h3 {
            color: white;
        }

        .main-footer a {
            color: rgba(255, 255, 255, 0.8);
            transition: color 0.3s ease;
        }

        .main-footer a:hover {
            color: white;
        }

        .main-footer .social-links a {
            color: white;
            margin-right: 1rem;
            font-size: 1.5rem;
            transition: color 0.3s ease;
        }

        .main-footer .social-links a:hover {
            color: rgba(255, 255, 255, 0.8);
        }

        .main-footer .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            text-align: center;
            color: rgba(255, 255, 255, 0.7);
        }

         .main-footer .footer-content {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            margin-bottom: 1.5rem;
        }

        .main-footer .footer-section {
            margin: 1rem;
            min-width: 200px;
        }

         .main-footer ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

         .main-footer ul li {
            margin-bottom: 0.5rem;
        }

         .main-footer ul li a {
            text-decoration: none;
        }

        /* Style for the Connect With Us section to push it right */
        .main-footer .footer-section:last-child {
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
                    <a href="index.php"><span class="text-xl font-bold text-white">Auth System</span></a>
                </div>
                <div class="flex items-center space-x-4">
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <?php if(isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
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
                            <i class="fas fa-user-plus"></i> SignUp
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav> 