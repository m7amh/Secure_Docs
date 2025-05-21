<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header("Location: login.php?error=Access denied.");
    exit;
}

// Fetch all users and their uploaded files
$stmt = $pdo->query("SELECT u.id as user_id, u.username, u.email, u.plan, u.used_space, f.* FROM user_files f JOIN users u ON f.user_id = u.id ORDER BY f.uploaded_at DESC");
$files = $stmt->fetchAll();

require 'header.php';
?>

<div class="container">
    <h1><i class="fas fa-tools"></i> Admin Dashboard</h1>

    <h2><i class="fas fa-file-alt"></i> User Files</h2>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Username</th>
                <th>Email</th>
                <th>Plan</th>
                <th>Used Space (MB)</th>
                <th>Filename</th>
                <th>Size (KB)</th>
                <th>Type</th>
                <th>Uploaded At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($files as $file): ?>
                <tr>
                    <td><?php echo htmlspecialchars($file['username']); ?></td>
                    <td><?php echo htmlspecialchars($file['email']); ?></td>
                    <td><?php echo htmlspecialchars($file['plan']); ?></td>
                    <td><?php echo round($file['used_space'] / 1024 / 1024, 2); ?></td>
                    <td><?php echo htmlspecialchars($file['filename']); ?></td>
                    <td><?php echo round($file['filesize'] / 1024, 2); ?></td>
                    <td><?php echo htmlspecialchars($file['filetype']); ?></td>
                    <td><?php echo htmlspecialchars($file['uploaded_at']); ?></td>
                    <td>
                        <a href="uploads/<?php echo htmlspecialchars($file['filename']); ?>" target="_blank">View</a> |
                        <a href="delete_file.php?id=<?php echo $file['id']; ?>&admin=1"
                            onclick="return confirm('Delete this file?');" style="color:red">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2><i class="fas fa-envelope"></i> Send Broadcast Message</h2>
    <form action="send_broadcast.php" method="POST">
        <textarea name="message" rows="4" class="form-control" placeholder="Write your message here..."
            required></textarea>
        <br>
        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send to All Users</button>
    </form>
</div>

<?php require 'footer.php'; ?>