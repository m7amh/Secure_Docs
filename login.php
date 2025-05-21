<?php
$title = "Login";
session_start();
require 'db.php';
require 'includes/two_factor.php';

$message = '';
$message_class = '';
$show_2fa_form = false;

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if this is a 2FA verification submission
    if (isset($_POST['2fa_code']) && isset($_SESSION['temp_user_id'])) {
        $code = trim($_POST['2fa_code']);
        $temp_user_id = $_SESSION['temp_user_id'];

        $twoFactor = new TwoFactorAuth($pdo);

        // Fetch the user based on the temporary ID
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$temp_user_id]);
        $user = $stmt->fetch();

        if ($user) {
             // Verify the 2FA code
             if ($twoFactor->verifyCode($user['id'], $code)) {
                // 2FA successful, complete login
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['avatar_url'] = $user['avatar_url'] ?? 'assets/css/defaultPFP.png';
                $_SESSION['is_admin'] = $user['is_admin'];

                 $_SESSION['user_data'] = [
                    'birthday' => $user['birthday'],
                    'gender' => $user['gender'],
                    'location' => $user['location']
                ];

                // Log successful login with 2FA
                $stmt = $pdo->prepare("INSERT INTO login_logs (user_id, ip_address, login_time, login_method) VALUES (?, ?, NOW(), ?)");
                $stmt->execute([$user['id'], $_SERVER['REMOTE_ADDR'], '2fa']);

                // Handle remember me if it was checked on the initial login form
                if (isset($_SESSION['temp_remember_me']) && $_SESSION['temp_remember_me']) {
                    $token = bin2hex(random_bytes(32));
                    setcookie('auth_token', $token, time() + (86400 * 7), "/");
                    $stmt = $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                    $stmt->execute([$token, $user['id']]);
                }

                // Clear temporary session data
                unset($_SESSION['temp_user_id']);
                unset($_SESSION['temp_remember_me']);
                unset($_SESSION['force_2fa_setup']); // Clear this in case it was set

                header("Location: index.php");
                exit;
            } else {
                // Log failed 2FA attempt
                $stmt = $pdo->prepare("INSERT INTO login_attempts (username, ip_address, attempt_time, success) VALUES (?, ?, NOW(), 0)");
                $stmt->execute([$user['username'], $_SERVER['REMOTE_ADDR']]);
                
                // 2FA verification failed
                $message = "Invalid 2FA code. Please try again.";
                $message_class = 'error';
                $show_2fa_form = true; // Stay on the 2FA form
                 // Restore temp user id to keep the 2fa form state if needed
                $_SESSION['temp_user_id'] = $temp_user_id; // Ensure temp user ID persists
            }
        } else {
             // User not found based on temp ID (shouldn't happen if session is intact)
             $message = "Session error. Please try logging in again.";
             $message_class = 'error';
             // Clear potentially stale session data
             unset($_SESSION['temp_user_id']);
             unset($_SESSION['temp_remember_me']);
             unset($_SESSION['force_2fa_setup']);
        }

    } else {
        // This is an initial login attempt (username/email and password)
        $identifier = trim($_POST['username']); // Assuming 'username' field is used for both
        $password = $_POST['password'];
        $remember_me = isset($_POST['remember']); // Assuming 'remember' field is used

        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $twoFactor = new TwoFactorAuth($pdo);

            // Check if 2FA is enabled for this user
            if ($twoFactor->is2FAEnabled($user['id'])) {
                // 2FA is enabled, show 2FA form
                $_SESSION['temp_user_id'] = $user['id']; // Store user ID temporarily
                $_SESSION['temp_remember_me'] = $remember_me; // Store remember me preference
                $show_2fa_form = true;
                // Do NOT proceed with full login yet. The HTML will render the 2FA form.
                goto end_of_script; // Jump to the end to prevent further PHP processing

            } else {
                // 2FA is NOT enabled, proceed with normal login
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['avatar_url'] = $user['avatar_url'] ?? 'assets/css/defaultPFP.png';
                $_SESSION['is_admin'] = $user['is_admin'];

                 $_SESSION['user_data'] = [
                    'birthday' => $user['birthday'],
                    'gender' => $user['gender'],
                    'location' => $user['location']
                ];

                // Log successful login
                $stmt = $pdo->prepare("INSERT INTO login_logs (user_id, ip_address, login_time, login_method) VALUES (?, ?, NOW(), ?)");
                $stmt->execute([$user['id'], $_SERVER['REMOTE_ADDR'], 'manual']);

                if ($remember_me) {
                    $token = bin2hex(random_bytes(32));
                    setcookie('auth_token', $token, time() + (86400 * 7), "/");
                    $stmt = $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                    $stmt->execute([$token, $user['id']]);
                }

                header("Location: index.php");
                exit;
            }
        } else {
            // Log failed login attempt
            $stmt = $pdo->prepare("INSERT INTO login_attempts (username, ip_address, attempt_time, success) VALUES (?, ?, NOW(), 0)");
            $stmt->execute([$identifier, $_SERVER['REMOTE_ADDR']]);
            
            $message = "Invalid username, email, or password.";
            $message_class = 'error';
             // Ensure 2FA form is not shown on failed authentication
            $show_2fa_form = false;
        }
    }
}

end_of_script:

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Auth System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/login.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="login-container animate-fade-in">
        <div class="login-card">
            <?php if (!$show_2fa_form): ?>
                <!-- Initial Login Form Header -->
                <div class="login-header">
                    <i class="fas fa-user-circle"></i>
                    <h1>Welcome Back</h1>
                </div>
            <?php endif; ?>

            <div class="login-body">
                <?php if(isset($_SESSION['error'])): ?>
                    <div class="message error">
                        <?php
                            echo $_SESSION['error'];
                            unset($_SESSION['error']);
                        ?>
                    </div>
                <?php elseif ($message): // Display messages from POST handling ?>
                     <div class="message <?php echo $message_class; ?>">
                         <?php echo $message; ?>
                     </div>
                <?php endif; ?>

                <?php if ($show_2fa_form): ?>
                    <!-- 2FA Verification Form -->
                    <div class="login-header"> <?php /* Reuse header style */?>
                         <i class="fas fa-shield-alt"></i>
                         <h1>2FA Required</h1>
                    </div>
                    <p>Please enter the 6-digit code from your authenticator app.</p>
                    <form action="login.php" method="POST">
                        <div class="input-group">
                            <label for="2fa_code">6-Digit Code</label>
                            <input type="text" id="2fa_code" name="2fa_code" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check-circle"></i> Verify Code
                        </button>
                    </form>
                <?php else: ?>
                    <!-- Initial Login Form -->
                    <form action="login.php" method="POST">
                        <div class="input-group">
                            <label for="username">Username or Email</label>
                            <input type="text" id="username" name="username" required value="<?php echo isset($identifier) ? htmlspecialchars($identifier) : ''; ?>"> <?php /* Retain value on failed auth */ ?>
                        </div>

                        <div class="input-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" required>
                        </div>

                        <div class="remember-forgot">
                            <label class="remember-me">
                                <input type="checkbox" name="remember" <?php echo isset($remember_me) && $remember_me ? 'checked' : ''; ?>> <?php /* Retain checked state */?>
                                Remember me
                            </label>
                            <a href="forgot-password.php" class="forgot-link">Forgot Password?</a>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt"></i>
                            Login
                        </button>
                    </form>

                    <div class="social-login">
                        <a href="oauth_login.php">
                            <img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/google/google-original.svg" alt="Google" style="width:1.25rem;height:1.25rem;margin-right:0.5rem;vertical-align:middle;">
                            Login with Google
                        </a>
                        <a href="oauth_login.php?connection=github" class="github-login">
                            <img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/github/github-original.svg" alt="GitHub" style="width:1.25rem;height:1.25rem;margin-right:0.5rem;vertical-align:middle;">
                            Login with GitHub
                        </a>
                    </div>

                    <div class="links">
                        Don't have an account? <a href="signup.php">Sign up here</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>