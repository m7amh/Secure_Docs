<?php
session_start();
require_once 'includes/security.php';
require_once 'db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit();
}

// Handle admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'toggle_status':
                $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
                $stmt->execute([$_POST['user_id']]);
                break;
            case 'delete_user':
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$_POST['user_id']]);
                break;
            case 'update_plan':
                $stmt = $pdo->prepare("UPDATE users SET plan = ? WHERE id = ?");
                $stmt->execute([$_POST['plan'], $_POST['user_id']]);
                break;
            case 'create_user':
                $username = $_POST['username'];
                $email = $_POST['email'];
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $plan = $_POST['plan'];
                $is_admin = isset($_POST['is_admin']) ? 1 : 0;
                
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, plan, is_admin) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$username, $email, $password, $plan, $is_admin]);
                break;
            case 'edit_user':
                $user_id = $_POST['user_id'];
                $username = $_POST['username'];
                $email = $_POST['email'];
                $plan = $_POST['plan'];
                $is_admin = isset($_POST['is_admin']) ? 1 : 0;
                
                $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, plan = ?, is_admin = ? WHERE id = ?");
                $stmt->execute([$username, $email, $plan, $is_admin, $user_id]);
                
                // Update password if provided
                if (!empty($_POST['password'])) {
                    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $stmt->execute([$password, $user_id]);
                }
                break;
        }
    }
}

// Get system statistics
$stats = [
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'active_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn(),
    'total_files' => $pdo->query("SELECT COUNT(*) FROM user_files")->fetchColumn(),
    'total_storage' => $pdo->query("SELECT SUM(filesize) FROM user_files")->fetchColumn() ?? 0,
    'failed_logins' => $pdo->query("SELECT COUNT(*) FROM login_attempts WHERE success = 0 AND attempt_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn(),
    'two_factor_users' => $pdo->query("SELECT COUNT(*) FROM two_factor_auth WHERE is_enabled = 1")->fetchColumn()
];

// Get recent users
$stmt = $pdo->query("
    SELECT u.*, 
           COUNT(f.id) as file_count,
           COALESCE(SUM(f.filesize), 0) as total_storage,
           CASE 
               WHEN u.auth0_id IS NULL THEN 'Manual'
               WHEN u.auth0_id LIKE 'google%' THEN 'Google'
               WHEN u.auth0_id LIKE 'facebook%' THEN 'Facebook'
               WHEN u.auth0_id LIKE 'github%' THEN 'GitHub'
               ELSE 'Other'
           END as auth_provider
    FROM users u
    LEFT JOIN user_files f ON u.id = f.user_id
    GROUP BY u.id, u.username, u.email, u.created_at, u.is_active, u.plan, u.auth0_id
    ORDER BY u.created_at DESC
    LIMIT 10
");
$recent_users = $stmt->fetchAll();

// Get recent failed logins
$stmt = $pdo->query("
    SELECT * FROM login_attempts 
    WHERE success = 0 
    ORDER BY attempt_time DESC 
    LIMIT 10
");
$failed_logins = $stmt->fetchAll();

// Get largest files
$stmt = $pdo->query("
    SELECT f.*, u.username 
    FROM user_files f
    JOIN users u ON f.user_id = u.id
    ORDER BY f.filesize DESC 
    LIMIT 10
");
$largest_files = $stmt->fetchAll();

// Get file logs
$stmt = $pdo->query("
    SELECT fl.*, u.username 
    FROM file_logs fl
    JOIN users u ON fl.user_id = u.id
    ORDER BY fl.timestamp DESC 
    LIMIT 10
");
$file_logs = $stmt->fetchAll();

// Get login logs
$stmt = $pdo->query("
    SELECT ll.*, u.username 
    FROM login_logs ll
    LEFT JOIN users u ON ll.user_id = u.id
    ORDER BY ll.login_time DESC 
    LIMIT 10
");
$login_logs = $stmt->fetchAll();

function formatBytes($bytes) {
    if ($bytes === null) return 'N/A';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Modern Control Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4F46E5;
            --secondary-color: #818CF8;
            --success-color: #10B981;
            --warning-color: #F59E0B;
            --danger-color: #EF4444;
            --dark-color: #1F2937;
            --light-color: #F3F4F6;
            --card-bg: #e5e7e9;
            --table-bg: #e5e7e9;
        }
        
        body {
            background-color: #F9FAFB;
            font-family: 'Inter', sans-serif;
        }
        
        .dashboard-card {
            background: var(--card-bg);
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .dashboard-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .stat-card {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 1rem;
            padding: 1.5rem;
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: scale(1.02);
        }
        
        .table-container {
            background: var(--table-bg);
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .table-header {
            background-color: #EDF2F7;
            padding: 1rem;
            font-weight: 600;
        }
        
        .table-row {
            transition: background-color 0.2s;
            background-color: var(--table-bg);
        }
        
        .table-row:hover {
            background-color: #EDF2F7;
        }
        
        .table-row td {
            border-bottom: 1px solid #E2E8F0;
        }
        
        .table-row:last-child td {
            border-bottom: none;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #DC2626;
        }
        
        .btn-success {
            background-color: var(--success-color);
            color: white;
        }
        
        .btn-success:hover {
            background-color: #059669;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .status-active {
            background-color: #D1FAE5;
            color: var(--success-color);
        }
        
        .status-inactive {
            background-color: #FEE2E2;
            color: var(--danger-color);
        }
        
        .plan-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .plan-free {
            background-color: #E0E7FF;
            color: var(--primary-color);
        }
        
        .plan-premium {
            background-color: #FEF3C7;
            color: var(--warning-color);
        }
        
        .plan-enterprise {
            background-color: #DBEAFE;
            color: #2563EB;
        }
        
        .auth-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .auth-manual {
            background-color: #E5E7EB;
            color: #374151;
        }
        
        .auth-google {
            background-color: #DBEAFE;
            color: #1D4ED8;
        }
        
        .auth-facebook {
            background-color: #E0E7FF;
            color: #4338CA;
        }
        
        .auth-github {
            background-color: #1F2937;
            color: #F9FAFB;
        }
        
        .auth-other {
            background-color: #F3F4F6;
            color: #6B7280;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in {
            animation: fadeIn 0.5s ease-out;
        }
        
        .action-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .action-upload {
            background-color: #D1FAE5;
            color: #059669;
        }
        
        .action-edit {
            background-color: #E0E7FF;
            color: #4338CA;
        }
        
        .action-delete {
            background-color: #FEE2E2;
            color: #DC2626;
        }
        
        .action-rename {
            background-color: #FEF3C7;
            color: #D97706;
        }
        
        .action-download {
            background-color: #DBEAFE;
            color: #2563EB;
        }
    </style>
</head>

<body class="min-h-screen">
        <?php include 'includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <header class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Admin Dashboard</h1>
            <p class="text-gray-600">Welcome to  control panel</p>
            
            <!-- Quick Navigation Links -->
            <div class="mt-4 flex flex-wrap gap-2">
                <a href="#statistics" class="btn btn-outline">
                    <i class="fas fa-chart-bar"></i> Statistics
                </a>
                <a href="#recent-users" class="btn btn-outline">
                    <i class="fas fa-users"></i> Recent Users
                </a>
                <a href="#login-activities" class="btn btn-outline">
                    <i class="fas fa-sign-in-alt"></i> Login Activities
                </a>
                <a href="#failed-logins" class="btn btn-outline">
                    <i class="fas fa-exclamation-triangle"></i> Failed Logins
                </a>
                <a href="#largest-files" class="btn btn-outline">
                    <i class="fas fa-file-alt"></i> Largest Files
                </a>
                <a href="#file-activities" class="btn btn-outline">
                    <i class="fas fa-history"></i> File Activities
                </a>
            </div>
        </header>

        <!-- Statistics Cards -->
        <div id="statistics" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <div class="stat-card animate-fade-in" style="animation-delay: 0.1s">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm opacity-80">Total Users</p>
                        <h3 class="text-2xl font-bold"><?php echo $stats['total_users']; ?></h3>
                    </div>
                    <i class="fas fa-users text-3xl opacity-80"></i>
                </div>
            </div>
            
            <div class="stat-card animate-fade-in" style="animation-delay: 0.2s">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm opacity-80">Active Users</p>
                        <h3 class="text-2xl font-bold"><?php echo $stats['active_users']; ?></h3>
                    </div>
                    <i class="fas fa-user-check text-3xl opacity-80"></i>
                </div>
            </div>
            
            <div class="stat-card animate-fade-in" style="animation-delay: 0.3s">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm opacity-80">Total Files</p>
                        <h3 class="text-2xl font-bold"><?php echo $stats['total_files']; ?></h3>
                    </div>
                    <i class="fas fa-file-alt text-3xl opacity-80"></i>
                </div>
            </div>
            
            <div class="stat-card animate-fade-in" style="animation-delay: 0.4s">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm opacity-80">Storage Used</p>
                        <h3 class="text-2xl font-bold"><?php echo formatBytes($stats['total_storage']); ?></h3>
                    </div>
                    <i class="fas fa-database text-3xl opacity-80"></i>
                </div>
            </div>
            
            <div class="stat-card animate-fade-in" style="animation-delay: 0.5s">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm opacity-80">2FA Enabled</p>
                        <h3 class="text-2xl font-bold"><?php echo $stats['two_factor_users']; ?></h3>
                    </div>
                    <i class="fas fa-shield-alt text-3xl opacity-80"></i>
                </div>
            </div>
            
            <div class="stat-card animate-fade-in" style="animation-delay: 0.6s">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm opacity-80">Failed Logins (24h)</p>
                        <h3 class="text-2xl font-bold"><?php echo $stats['failed_logins']; ?></h3>
                    </div>
                    <i class="fas fa-exclamation-triangle text-3xl opacity-80"></i>
                </div>
            </div>
        </div>

        <!-- Recent Users -->
        <div id="recent-users" class="dashboard-card mb-8 animate-fade-in" style="animation-delay: 0.7s">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-gray-900">Recent Users</h2>
                    <button onclick="showCreateUserModal()" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Add New Member
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="table-header">
                                <th class="text-left">Username</th>
                                <th class="text-left">Email</th>
                                <th class="text-left">Join Date</th>
                                <th class="text-left">Status</th>
                                <th class="text-left">Plan</th>
                                <th class="text-left">Auth Provider</th>
                                <th class="text-left">Files</th>
                                <th class="text-left">Storage</th>
                                <th class="text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_users as $user): ?>
                            <tr class="table-row">
                                <td class="p-4"><?php echo htmlspecialchars($user['username']); ?></td>
                                <td class="p-4"><?php echo htmlspecialchars($user['email']); ?></td>
                                <td class="p-4"><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                <td class="p-4">
                                    <span class="status-badge <?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td class="p-4">
                                    <span class="plan-badge plan-<?php echo strtolower($user['plan']); ?>">
                                        <?php echo ucfirst($user['plan']); ?>
                                    </span>
                                </td>
                                <td class="p-4">
                                    <span class="auth-badge auth-<?php echo strtolower($user['auth_provider']); ?>">
                                        <?php echo $user['auth_provider']; ?>
                                    </span>
                                </td>
                                <td class="p-4"><?php echo $user['file_count']; ?></td>
                                <td class="p-4"><?php echo formatBytes($user['total_storage']); ?></td>
                                <td class="p-4">
                                    <div class="flex space-x-2">
                                        <button onclick="showEditUserModal('<?php echo $user['id']; ?>', '<?php echo addslashes($user['username']); ?>', '<?php echo addslashes($user['email']); ?>', '<?php echo $user['plan']; ?>', '<?php echo $user['is_admin']; ?>')" 
                                                class="btn btn-outline">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="btn <?php echo $user['is_active'] ? 'btn-danger' : 'btn-success'; ?>">
                                                <?php echo $user['is_active'] ? 'Disable' : 'Enable'; ?>
                                            </button>
                                        </form>
                                        <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="btn btn-danger">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Login Logs -->
        <div id="login-activities" class="dashboard-card mb-8 animate-fade-in" style="animation-delay: 0.8s">
            <div class="p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Recent Login Activities</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="table-header">
                                <th class="text-left">User</th>
                                <th class="text-left">IP Address</th>
                                <th class="text-left">Login Time</th>
                                <th class="text-left">Method</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($login_logs as $log): ?>
                            <tr class="table-row">
                                <td class="p-4">
                                    <div class="flex items-center">
                                        <span class="font-medium text-gray-900">
                                            <?php echo $log['username'] ? htmlspecialchars($log['username']) : 'Unknown User'; ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="p-4"><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                <td class="p-4"><?php echo date('Y-m-d H:i:s', strtotime($log['login_time'])); ?></td>
                                <td class="p-4">
                                    <span class="auth-badge auth-<?php echo strtolower($log['login_method']); ?>">
                                        <?php echo ucfirst($log['login_method']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Failed Logins -->
        <div id="failed-logins" class="dashboard-card mb-8 animate-fade-in" style="animation-delay: 0.9s">
            <div class="p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Recent Failed Logins</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="table-header">
                                <th class="text-left">IP Address</th>
                                <th class="text-left">Username</th>
                                <th class="text-left">Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($failed_logins as $login): ?>
                            <tr class="table-row">
                                <td class="p-4"><?php echo htmlspecialchars($login['ip_address']); ?></td>
                                <td class="p-4"><?php echo htmlspecialchars($login['username']); ?></td>
                                <td class="p-4"><?php echo date('Y-m-d H:i:s', strtotime($login['attempt_time'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Largest Files -->
        <div id="largest-files" class="dashboard-card mb-8 animate-fade-in" style="animation-delay: 1.0s">
            <div class="p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Largest Files</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="table-header">
                                <th class="text-left">Filename</th>
                                <th class="text-left">User</th>
                                <th class="text-left">Size</th>
                                <th class="text-left">Type</th>
                                <th class="text-left">Upload Date</th>
                                <th class="text-left">Downloads</th>
                                <th class="text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($largest_files as $file): ?>
                            <tr class="table-row">
                                <td class="p-4">
                                    <div class="flex items-center">
                                        <span class="mr-2"><?php echo htmlspecialchars($file['original_name']); ?></span>
                                        <button onclick="editFileName(<?php echo $file['id']; ?>, '<?php echo addslashes(pathinfo($file['original_name'], PATHINFO_FILENAME)); ?>')" 
                                                class="text-gray-500 hover:text-gray-700">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                </td>
                                <td class="p-4"><?php echo htmlspecialchars($file['username']); ?></td>
                                <td class="p-4"><?php echo formatBytes($file['filesize']); ?></td>
                                <td class="p-4"><?php echo htmlspecialchars($file['filetype']); ?></td>
                                <td class="p-4"><?php echo date('Y-m-d', strtotime($file['uploaded_at'])); ?></td>
                                <td class="p-4"><?php echo $file['download_count']; ?></td>
                                <td class="p-4">
                                    <div class="flex space-x-2">
                                        <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this file?');">
                                            <input type="hidden" name="action" value="delete_file">
                                            <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                                            <button type="submit" class="btn btn-danger">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- File Logs -->
         
        <div id="file-activities" class="dashboard-card animate-fade-in" style="animation-delay: 1.0s">
            <div class="p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Recent File Activities</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="table-header">
                                <th class="text-left">User</th>
                                <th class="text-left">Action</th>
                                <th class="text-left">Filename</th>
                                <th class="text-left">Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($file_logs as $log): ?>
                            <tr class="table-row">
                                <td class="p-4">
                                    <div class="flex items-center">
                                        <span class="font-medium text-gray-900"><?php echo htmlspecialchars($log['username']); ?></span>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <span class="action-badge action-<?php echo strtolower($log['action']); ?>">
                                        <?php echo ucfirst($log['action']); ?>
                                    </span>
                                </td>
                                <td class="p-4"><?php echo htmlspecialchars($log['filename']); ?></td>
                                <td class="p-4"><?php echo date('Y-m-d H:i:s', strtotime($log['timestamp'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Create User Modal -->
    <div id="createUserModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Create New Member</h3>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="create_user">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Username</label>
                        <input type="text" name="username" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="email" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Password</label>
                        <input type="password" name="password" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Plan</label>
                        <select name="plan" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="free">Free</option>
                            <option value="premium">Premium</option>
                            <option value="enterprise">Enterprise</option>
                        </select>
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" name="is_admin" id="is_admin" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                        <label for="is_admin" class="ml-2 block text-sm text-gray-900">Admin privileges</label>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="hideCreateUserModal()" class="btn btn-outline">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Member</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Member</h3>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Username</label>
                        <input type="text" name="username" id="edit_username" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="email" id="edit_email" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">New Password (leave blank to keep current)</label>
                        <input type="password" name="password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Plan</label>
                        <select name="plan" id="edit_plan" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="free">Free</option>
                            <option value="premium">Premium</option>
                            <option value="enterprise">Enterprise</option>
                        </select>
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" name="is_admin" id="edit_is_admin" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                        <label for="edit_is_admin" class="ml-2 block text-sm text-gray-900">Admin privileges</label>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="hideEditUserModal()" class="btn btn-outline">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Add smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    targetElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add hover effects to cards
        document.querySelectorAll('.dashboard-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        function editFileName(fileId, currentName) {
            const newName = prompt('Enter new file name:', currentName);
            if (newName && newName !== currentName) {
                const formData = new FormData();
                formData.append('file_id', fileId);
                formData.append('new_name', newName);

                fetch('edit_filename.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Failed to rename file: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while renaming the file');
                });
            }
        }

        function showCreateUserModal() {
            document.getElementById('createUserModal').classList.remove('hidden');
        }

        function hideCreateUserModal() {
            document.getElementById('createUserModal').classList.add('hidden');
        }

        function showEditUserModal(userId, username, email, plan, isAdmin) {
            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_plan').value = plan;
            document.getElementById('edit_is_admin').checked = isAdmin === '1';
            document.getElementById('editUserModal').classList.remove('hidden');
        }

        function hideEditUserModal() {
            document.getElementById('editUserModal').classList.add('hidden');
        }
    </script>
</body>

</html>