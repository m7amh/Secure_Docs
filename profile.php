<?php
$title = "Profile";
session_start();
require 'db.php';
require 'includes/two_factor.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?error=You must log in first.");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    die("User not found.");
}

$message = '';
$message_class = '';
$twoFactor = new TwoFactorAuth($pdo);
$is2FAEnabled = $twoFactor->is2FAEnabled($_SESSION['user_id']);

if (isset($_GET['setup_2fa']) && $_GET['setup_2fa'] === 'required') {
    $message = "Two-factor authentication is now required for your account. Please set it up to continue using the system.";
    $message_class = 'warning';
    $_SESSION['force_2fa_setup'] = true;
}

// Handle 2FA actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'setup_2fa':
                $result = $twoFactor->generateSecret($_SESSION['user_id'], $_SESSION['username']);
                $_SESSION['temp_2fa_secret'] = $result['secret'];
                $_SESSION['temp_2fa_qr'] = $result['qrCodeUrl'];
                break;

            case 'verify_2fa':
                $code = trim($_POST['2fa_code']);
                if ($twoFactor->verifyCode($_SESSION['user_id'], $code)) {
                    if ($twoFactor->enable2FA($_SESSION['user_id'])) {
                        $message = "Two-factor authentication has been enabled successfully!";
                        $message_class = 'success';
                        $is2FAEnabled = true;
                        unset($_SESSION['temp_2fa_secret']);
                        unset($_SESSION['temp_2fa_qr']);
                        unset($_SESSION['force_2fa_setup']);
                        if (isset($_SESSION['force_2fa_setup'])) {
                            header("Location: index.php");
                            exit;
                        }
                    } else {
                        $message = "Failed to enable two-factor authentication.";
                        $message_class = 'error';
                    }
                } else {
                    $message = "Invalid verification code. Please try again.";
                    $message_class = 'error';
                }
                break;

            case 'disable_2fa':
                if ($twoFactor->disable2FA($_SESSION['user_id'])) {
                    $message = "Two-factor authentication has been disabled.";
                    $message_class = 'success';
                    $is2FAEnabled = false;
                } else {
                    $message = "Failed to disable two-factor authentication.";
                    $message_class = 'error';
                }
                break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile | Secure Storage</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .fade-in { animation: fadeIn 0.6s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px);} to { opacity: 1; transform: none;} }
        
        /* Explicitly set header text color to purple for profile page */
        .main-header .logo,
        .main-header .nav-links a {
            color:rgb(197, 197, 197) !important; /* A distinct purple */
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <?php include 'includes/header.php'; ?>
    <div class="max-w-2xl mx-auto mt-10 p-6 bg-white rounded-2xl shadow-xl fade-in">
        <div class="flex items-center gap-4 mb-8">
            <div class="bg-gradient-to-tr from-indigo-500 to-purple-400 rounded-full p-2">
                <i class="fas fa-user text-3xl text-white"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Profile</h1>
                <p class="text-gray-500">Manage your account and security settings</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="mb-6 px-4 py-3 rounded-lg <?php
                echo $message_class === 'success' ? 'bg-green-100 border border-green-300 text-green-800' :
                     ($message_class === 'error' ? 'bg-red-100 border border-red-300 text-red-800' :
                     ($message_class === 'warning' ? 'bg-yellow-100 border border-yellow-300 text-yellow-800' : 'bg-gray-100 border border-gray-300 text-gray-800'));
            ?> fade-in">
                <i class="fas <?php echo $message_class === 'error' ? 'fa-exclamation-circle' : ($message_class === 'success' ? 'fa-check-circle' : 'fa-info-circle'); ?> mr-2"></i>
                <?php echo $message; ?>
                <?php if (isset($_SESSION['force_2fa_setup'])): ?>
                    <div class="mt-2 flex items-center gap-2 bg-red-50 border border-red-200 text-red-700 rounded px-2 py-1">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>You must enable 2FA to continue using the system.</span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['force_2fa_setup'])): ?>
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-6 rounded-lg mb-8 text-center fade-in">
                <h2 class="text-xl font-semibold text-yellow-700 mb-2"><i class="fas fa-shield-alt"></i> Two-Factor Authentication Required</h2>
                <p class="text-gray-700 mb-4">For your account security, you must enable two-factor authentication now.</p>
                <form method="POST" action="profile.php" class="inline-block">
                    <input type="hidden" name="action" value="setup_2fa">
                    <button type="submit" class="bg-gradient-to-tr from-pink-500 to-red-500 text-white px-6 py-2 rounded-lg font-semibold shadow hover:scale-105 transition-transform">
                        <i class="fas fa-shield-alt"></i> Set Up 2FA Now
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div class="mb-8">
                <div class="bg-gradient-to-tr from-indigo-100 to-purple-100 rounded-lg p-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4 shadow">
                    <div>
                        <h2 class="text-lg font-semibold text-indigo-700 mb-2"><i class="fas fa-user-circle"></i> Account Information</h2>
                        <div class="text-gray-700">
                            <p><strong>Username:</strong> <?php echo htmlspecialchars($_SESSION['username']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($_SESSION['email']); ?></p>
                            <p><strong>Account Type:</strong> <?php echo $_SESSION['is_admin'] ? 'Administrator' : 'User'; ?></p>
                        </div>
                    </div>
                    <div class="flex flex-col gap-2 mt-4 md:mt-0">
                        <a href="edit_profile.php" class="bg-indigo-500 hover:bg-indigo-600 text-white px-4 py-2 rounded-lg font-medium flex items-center gap-2 transition"><i class="fas fa-edit"></i> Edit Profile</a>
                        <a href="change_password.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium flex items-center gap-2 transition"><i class="fas fa-key"></i> Change Password</a>
                    </div>
                </div>
            </div>

            <div class="mb-8">
                <div class="bg-white border border-indigo-100 rounded-lg p-6 shadow">
                    <h2 class="text-lg font-semibold text-indigo-700 mb-4"><i class="fas fa-shield-alt"></i> Two-Factor Authentication</h2>
                    <?php if (isset($_SESSION['temp_2fa_qr'])): ?>
                        <div class="text-center">
                            <h3 class="text-md font-semibold mb-2">Setup 2FA</h3>
                            <p class="mb-2">1. Scan this QR code with your authenticator app:</p>
                            <img src="<?php echo htmlspecialchars($_SESSION['temp_2fa_qr']); ?>" alt="2FA QR Code" class="mx-auto mb-4 rounded shadow-lg border border-gray-200">
                            <p class="mb-2">2. Enter the 6-digit code from your authenticator app to verify:</p>
                            <form method="POST" action="profile.php" class="max-w-xs mx-auto">
                                <input type="hidden" name="action" value="verify_2fa">
                                <div class="mb-4">
                                    <label for="2fa_code" class="block text-sm font-medium text-gray-700 mb-1">Verification Code:</label>
                                    <input type="text" id="2fa_code" name="2fa_code" required pattern="[0-9]{6}" maxlength="6" placeholder="Enter 6-digit code" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-400">
                                </div>
                                <button type="submit" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg font-semibold flex items-center gap-2 transition"><i class="fas fa-check"></i> Verify and Enable 2FA</button>
                            </form>
                        </div>
                    <?php elseif ($is2FAEnabled): ?>
                        <div class="flex items-center gap-3 bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                            <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                            <span class="text-green-800 font-medium">Two-factor authentication is enabled</span>
                        </div>
                        <form method="POST" action="profile.php" class="inline-block" onsubmit="return confirm('Are you sure you want to disable 2FA? This will make your account less secure.');">
                            <input type="hidden" name="action" value="disable_2fa">
                            <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg font-semibold flex items-center gap-2 transition"><i class="fas fa-times"></i> Disable 2FA</button>
                        </form>
                    <?php else: ?>
                        <div class="flex items-center gap-3 bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                            <i class="fas fa-times-circle text-red-600 text-2xl"></i>
                            <span class="text-red-800 font-medium">Two-factor authentication is not enabled</span>
                        </div>
                        <form method="POST" action="profile.php" class="inline-block">
                            <input type="hidden" name="action" value="setup_2fa">
                            <button type="submit" class="bg-indigo-500 hover:bg-indigo-600 text-white px-4 py-2 rounded-lg font-semibold flex items-center gap-2 transition"><i class="fas fa-shield-alt"></i> Enable 2FA</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
