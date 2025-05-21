<?php
$title = "Signup";
session_start();
require 'db.php';

$message = '';
$message_class = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Perform initial validation checks
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $message = "All fields are required.";
        $message_class = 'error';
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";
        $message_class = 'error';
    } elseif (!validate_password($password)) {
        $message = "Password must be at least 8 characters long, 
                    contain at least one uppercase letter, one lowercase letter, 
                    one number, and one special character.";
        $message_class = 'error';
    }

    // If there were no validation errors, proceed with database checks
    if (empty($message)) {
        // التحقق مما إذا كان اسم المستخدم أو البريد الإلكتروني موجودًا بالفعل
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        $user = $stmt->fetch();

        if ($user) {
            $message = "Username or email already exists.";
            $message_class = 'error';
        } else {
            // إنشاء حساب جديد
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            $twofa_deadline = date('Y-m-d H:i:s', strtotime('+3 days')); // Set 2FA deadline to 3 days from now

            $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, auth_method, 2fa_required_by) 
                                  VALUES (?, ?, ?, 'manual', ?)");
            $stmt->execute([$username, $email, $password_hash, $twofa_deadline]);

            // Set success message in session before redirecting
            $_SESSION['success_message'] = "Account created successfully! Please login.";
            header("Location: login.php");
            exit();
        }
    }
}

// password policy
function validate_password($password)
{
    if (strlen($password) < 8) {
        return false;
    }
    if (!preg_match('/[A-Z]/', $password)) { // حرف كبير
        return false;
    }
    if (!preg_match('/[a-z]/', $password)) { // حرف صغير
        return false;
    }
    if (!preg_match('/[0-9]/', $password)) { // رقم
        return false;
    }
    if (!preg_match('/[\W_]/', $password)) { // حرف خاص (رمز)
        return false;
    }
    return true;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Auth System</title>
    <link rel="stylesheet" href="assets/css/signup.css">
  
   
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="signup-container animate-fade-in">
        <div class="signup-card">
            <div class="signup-header">
                <i class="fas fa-user-plus"></i>
                <h1>Create Account</h1>
            </div>
            <div class="signup-body">
                <?php if(isset($_SESSION['error'])): // Display errors from session ?>
                    <div class="message error">
                        <?php
                            echo $_SESSION['error'];
                            unset($_SESSION['error']);
                        ?>
                    </div>
                <?php elseif (!empty($message)): // Display messages from current POST submission ?>
                     <div class="message <?php echo $message_class; ?>">
                         <?php echo $message; ?>
                     </div>
                <?php endif; ?>

                <form action="signup.php" method="POST"> <?php /* Form submits back to signup.php */?>
                    <div class="input-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required value="<?php echo htmlspecialchars($username ?? ''); ?>"> <?php /* Retain username value */?>
                        <small>Choose a unique username (3-20 characters)</small>
                    </div>

                    <div class="input-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($email ?? ''); ?>"> <?php /* Retain email value */?>

                    </div>

                    <div class="input-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                        <small>Must be at least 8 characters long</small>
                    </div>

                    <div class="input-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                        <small>Please confirm your password</small>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i>
                        Sign Up
                    </button>
                </form>

                <div class="social-signup">
                    <a href="oauth_login.php">
                        <img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/google/google-original.svg" alt="Google" style="width:1.25rem;height:1.25rem;margin-right:0.5rem;vertical-align:middle;">
                        Sign up with Google
                    </a>
                </div>

                <div class="links">
                    Already have an account? <a href="login.php">Login here</a>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>