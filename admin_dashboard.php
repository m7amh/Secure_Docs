<?php
$title = "Admin Dashboard";
session_start();
require 'db.php';

// التحقق من صلاحيات المدير
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: login.php?error=Access denied. Admin privileges required.");
    exit;
}

$message = '';
$message_class = '';

// معالجة إجراءات المدير
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'toggle_user_status':
                $user_id = (int)$_POST['user_id'];
                $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
                $stmt->execute([$user_id]);
                $message = "User status updated successfully.";
                $message_class = 'success';
                break;

            case 'delete_user':
                $user_id = (int)$_POST['user_id'];
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND id != ?");
                $stmt->execute([$user_id, $_SESSION['user_id']]);
                $message = "User deleted successfully.";
                $message_class = 'success';
                break;

            case 'update_user_plan':
                $user_id = (int)$_POST['user_id'];
                $new_plan = $_POST['plan'];
                $stmt = $pdo->prepare("UPDATE users SET plan = ? WHERE id = ?");
                $stmt->execute([$new_plan, $user_id]);
                $message = "User plan updated successfully.";
                $message_class = 'success';
                break;
        }
    }
}

// جلب إحصائيات النظام
$stats = [
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'active_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn(),
    'total_files' => $pdo->query("SELECT COUNT(*) FROM user_files")->fetchColumn(),
    'total_storage' => $pdo->query("SELECT SUM(filesize) FROM user_files")->fetchColumn(),
    'failed_logins' => $pdo->query("SELECT COUNT(*) FROM failed_logins WHERE attempt_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn(),
    'users_with_2fa' => $pdo->query("SELECT COUNT(*) FROM two_factor_auth WHERE is_enabled = 1")->fetchColumn()
];

// جلب آخر المستخدمين المسجلين
$stmt = $pdo->query("
    SELECT id, username, email, created_at, is_active, plan, 
           (SELECT COUNT(*) FROM user_files WHERE user_id = users.id) as file_count
    FROM users 
    ORDER BY created_at DESC 
    LIMIT 10
");
$recent_users = $stmt->fetchAll();

// جلب آخر محاولات تسجيل الدخول الفاشلة
$stmt = $pdo->query("
    SELECT ip_address, username, attempt_time 
    FROM failed_logins 
    ORDER BY attempt_time DESC 
    LIMIT 10
");
$recent_failed_logins = $stmt->fetchAll();

// جلب أكبر الملفات
$stmt = $pdo->query("
    SELECT uf.*, u.username 
    FROM user_files uf 
    JOIN users u ON uf.user_id = u.id 
    ORDER BY uf.filesize DESC 
    LIMIT 10
");
$largest_files = $stmt->fetchAll();

// جلب معلومات الخطط
$stmt = $pdo->query("
    SELECT p.*, 
           COUNT(u.id) as user_count,
           SUM(u.used_space) as total_used_space
    FROM plans p
    LEFT JOIN users u ON p.name = u.plan
    GROUP BY p.id
");
$plans_info = $stmt->fetchAll();

require 'header.php';
?>

<div class="container">
    <div class="admin-dashboard">
        <h1><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>

        <?php if ($message): ?>
            <div class="message <?php echo $message_class; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- إحصائيات النظام -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-users"></i>
                <h3>Total Users</h3>
                <p><?php echo number_format($stats['total_users']); ?></p>
            </div>
            <div class="stat-card">
                <i class="fas fa-user-check"></i>
                <h3>Active Users</h3>
                <p><?php echo number_format($stats['active_users']); ?></p>
            </div>
            <div class="stat-card">
                <i class="fas fa-file"></i>
                <h3>Total Files</h3>
                <p><?php echo number_format($stats['total_files']); ?></p>
            </div>
            <div class="stat-card">
                <i class="fas fa-hdd"></i>
                <h3>Total Storage</h3>
                <p><?php echo formatBytes($stats['total_storage']); ?></p>
            </div>
            <div class="stat-card">
                <i class="fas fa-shield-alt"></i>
                <h3>2FA Enabled</h3>
                <p><?php echo number_format($stats['users_with_2fa']); ?></p>
            </div>
            <div class="stat-card">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Failed Logins (24h)</h3>
                <p><?php echo number_format($stats['failed_logins']); ?></p>
            </div>
        </div>

        <!-- إضافة قسم معلومات الخطط بعد قسم الإحصائيات -->
        <div class="dashboard-section">
            <h2><i class="fas fa-cubes"></i> Storage Plans</h2>
            <div class="plans-grid">
                <?php foreach ($plans_info as $plan): ?>
                    <div class="plan-card">
                        <div class="plan-header">
                            <h3><?php echo ucfirst($plan['name']); ?> Plan</h3>
                            <span class="plan-price">$<?php echo number_format($plan['price'], 2); ?>/month</span>
                        </div>
                        <div class="plan-details">
                            <p><i class="fas fa-hdd"></i> Storage: <?php echo formatBytes($plan['storage_limit']); ?></p>
                            <p><i class="fas fa-file"></i> Max File Size: <?php echo formatBytes($plan['max_file_size']); ?></p>
                            <p><i class="fas fa-users"></i> Users: <?php echo number_format($plan['user_count']); ?></p>
                            <p><i class="fas fa-chart-pie"></i> Total Used: <?php echo formatBytes($plan['total_used_space'] ?? 0); ?></p>
                        </div>
                        <div class="plan-description">
                            <?php echo htmlspecialchars($plan['description']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- آخر المستخدمين المسجلين -->
        <div class="dashboard-section">
            <h2><i class="fas fa-user-plus"></i> Recent Users</h2>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Joined</th>
                            <th>Status</th>
                            <th>Plan</th>
                            <th>Files</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" class="inline-form">
                                        <input type="hidden" name="action" value="update_user_plan">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <select name="plan" onchange="this.form.submit()">
                                            <option value="free" <?php echo $user['plan'] == 'free' ? 'selected' : ''; ?>>Free</option>
                                            <option value="premium" <?php echo $user['plan'] == 'premium' ? 'selected' : ''; ?>>Premium</option>
                                            <option value="enterprise" <?php echo $user['plan'] == 'enterprise' ? 'selected' : ''; ?>>Enterprise</option>
                                        </select>
                                    </form>
                                </td>
                                <td><?php echo number_format($user['file_count']); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <form method="POST" class="inline-form">
                                            <input type="hidden" name="action" value="toggle_user_status">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="btn-small <?php echo $user['is_active'] ? 'btn-warning' : 'btn-success'; ?>">
                                                <i class="fas <?php echo $user['is_active'] ? 'fa-ban' : 'fa-check'; ?>"></i>
                                            </button>
                                        </form>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <form method="POST" class="inline-form" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="btn-small btn-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- آخر محاولات تسجيل الدخول الفاشلة -->
        <div class="dashboard-section">
            <h2><i class="fas fa-exclamation-circle"></i> Recent Failed Logins</h2>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>IP Address</th>
                            <th>Username</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_failed_logins as $login): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($login['ip_address']); ?></td>
                                <td><?php echo htmlspecialchars($login['username']); ?></td>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($login['attempt_time'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- أكبر الملفات -->
        <div class="dashboard-section">
            <h2><i class="fas fa-file-alt"></i> Largest Files</h2>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Filename</th>
                            <th>User</th>
                            <th>Size</th>
                            <th>Type</th>
                            <th>Uploaded</th>
                            <th>Downloads</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($largest_files as $file): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($file['original_name']); ?></td>
                                <td><?php echo htmlspecialchars($file['username']); ?></td>
                                <td><?php echo formatBytes($file['filesize']); ?></td>
                                <td><?php echo htmlspecialchars($file['filetype']); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($file['uploaded_at'])); ?></td>
                                <td><?php echo number_format($file['download_count']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.admin-dashboard {
    padding: 2rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    text-align: center;
}

.stat-card i {
    font-size: 2rem;
    color: #2196f3;
    margin-bottom: 0.5rem;
}

.stat-card h3 {
    margin: 0.5rem 0;
    color: #666;
    font-size: 0.9rem;
}

.stat-card p {
    margin: 0;
    font-size: 1.5rem;
    font-weight: bold;
    color: #333;
}

.dashboard-section {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.dashboard-section h2 {
    margin-bottom: 1rem;
    color: #333;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.dashboard-section h2 i {
    color: #2196f3;
}

.table-responsive {
    overflow-x: auto;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}

.admin-table th,
.admin-table td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.admin-table th {
    background-color: #f8f9fa;
    font-weight: 600;
}

.status-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.8rem;
}

.status-badge.active {
    background-color: #e8f5e9;
    color: #2e7d32;
}

.status-badge.inactive {
    background-color: #ffebee;
    color: #c62828;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
}

.btn-small {
    padding: 0.25rem 0.5rem;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    color: white;
}

.btn-small.btn-success {
    background-color: #4caf50;
}

.btn-small.btn-warning {
    background-color: #ff9800;
}

.btn-small.btn-danger {
    background-color: #f44336;
}

.inline-form {
    display: inline;
}

select {
    padding: 0.25rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    background-color: white;
}

.plans-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-top: 1rem;
}

.plan-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    padding: 1.5rem;
    transition: transform 0.2s;
}

.plan-card:hover {
    transform: translateY(-5px);
}

.plan-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #eee;
}

.plan-header h3 {
    margin: 0;
    color: #2196f3;
    font-size: 1.2rem;
}

.plan-price {
    background: #e3f2fd;
    color: #1976d2;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: bold;
}

.plan-details {
    margin-bottom: 1rem;
}

.plan-details p {
    margin: 0.5rem 0;
    color: #666;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.plan-details i {
    color: #2196f3;
    width: 20px;
}

.plan-description {
    color: #666;
    font-size: 0.9rem;
    line-height: 1.4;
}
</style>

<?php
// دالة مساعدة لتنسيق حجم الملفات
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

require 'footer.php';
?>