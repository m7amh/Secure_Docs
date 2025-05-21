<?php
session_start();
require 'includes/security.php';
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Get files from database
$stmt = $pdo->prepare("SELECT * FROM user_files WHERE user_id = ? ORDER BY uploaded_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$files = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Files | Secure Storage</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2B4D40;
            --secondary-color: #6a8279;
            --success-color: #10B981;
            --danger-color: #EF4444;
            --dark-color: #1F2937;
            --light-color: #F3F4F6;
        }

        body {
            background-color: #F9FAFB;
            font-family: 'Inter', sans-serif;
        }

        .file-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        .file-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .file-icon {
            font-size: 2rem;
            margin-right: 1rem;
            color: var(--primary-color);
        }

        .file-info {
            flex: 1;
        }

        .file-name {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.25rem;
        }

        .file-meta {
            color: #6B7280;
            font-size: 0.875rem;
        }

        .encrypted-badge {
            background-color: #EEF2FF;
            color: var(--primary-color);
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            margin-left: 0.5rem;
        }

        .encrypted-badge i {
            margin-right: 0.25rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn i {
            margin-right: 0.5rem;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
            transform: translateY(-1px);
        }

        .btn-outline {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
        }

        .btn-outline:hover {
            background-color: var(--primary-color);
            color: white;
            transform: translateY(-1px);
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background-color: #DC2626;
            transform: translateY(-1px);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .empty-state i {
            font-size: 4rem;
            color: #E5E7EB;
            margin-bottom: 1.5rem;
        }

        .empty-state h3 {
            color: var(--dark-color);
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: #6B7280;
            margin-bottom: 1.5rem;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .animate-fade-in {
            animation: fadeIn 0.5s ease-out;
        }
    </style>
</head>

<body class="min-h-screen">
    <!-- Navbar -->
    <nav class="bg-white shadow-lg">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <i class="fas fa-shield-alt text-2xl text-[#2B4D40] mr-2"></i>
                    <span class="text-xl font-bold text-gray-900">Secure Storage</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="index.php" class="btn btn-outline">
                        <i class="fas fa-home"></i> Home
                    </a>
                    <a href="upload.php" class="btn btn-primary">
                        <i class="fas fa-cloud-upload-alt"></i> Upload New File
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <div class="max-w-6xl mx-auto">
            <div class="flex justify-between items-center mb-8">
                <h1 class="text-3xl font-bold text-gray-900">
                    <i class="fas fa-folder-open mr-2"></i>My Files
                </h1>
                <div class="flex space-x-4">
                    <button class="btn btn-outline" onclick="toggleView()">
                        <i class="fas fa-th-large"></i> Toggle View
                    </button>
                </div>
            </div>

            <?php if (empty($files)): ?>
                <div class="empty-state animate-fade-in">
                    <i class="fas fa-folder-open"></i>
                    <h3>No Files Uploaded Yet</h3>
                    <p>You haven't uploaded any files yet. Click the button above to upload your first file.</p>
                    <a href="upload.php" class="btn btn-primary">
                        <i class="fas fa-cloud-upload-alt"></i> Upload Your First File
                    </a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 gap-6" id="files-grid">
                    <?php foreach ($files as $file):
                        $ext = strtolower(pathinfo($file['original_name'], PATHINFO_EXTENSION));
                        $icon = '';

                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                            $icon = '<i class="fas fa-file-image"></i>';
                        } elseif ($ext === 'pdf') {
                            $icon = '<i class="fas fa-file-pdf"></i>';
                        } elseif (in_array($ext, ['doc', 'docx', 'odt'])) {
                            $icon = '<i class="fas fa-file-word"></i>';
                        } elseif ($ext === 'txt') {
                            $icon = '<i class="fas fa-file-alt"></i>';
                        } else {
                            $icon = '<i class="fas fa-file"></i>';
                        }
                    ?>
                        <div class="file-card animate-fade-in">
                            <div class="flex items-center">
                                <div class="file-icon">
                                    <?php echo $icon; ?>
                                </div>
                                <div class="file-info">
                                    <div class="flex items-center">
                                        <h3 class="file-name">
                                            <?php echo htmlspecialchars($file['original_name']); ?>
                                        </h3>
                                        <span class="encrypted-badge">
                                            <i class="fas fa-lock"></i> Encrypted
                                        </span>
                                    </div>
                                    <div class="file-meta">
                                        <span class="mr-4">
                                            <i class="fas fa-weight-hanging mr-1"></i>
                                            <?php echo formatFileSize($file['filesize']); ?>
                                        </span>
                                        <span class="mr-4">
                                            <i class="fas fa-file mr-1"></i>
                                            <?php echo strtoupper($ext); ?>
                                        </span>
                                        <span>
                                            <i class="fas fa-clock mr-1"></i>
                                            <?php echo date('M d, Y H:i', strtotime($file['uploaded_at'])); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="flex space-x-2">
                                    <button onclick="editFileName(<?php echo $file['id']; ?>, '<?php echo addslashes(pathinfo($file['original_name'], PATHINFO_FILENAME)); ?>')" 
                                            class="btn btn-outline">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <a href="download.php?id=<?php echo $file['id']; ?>" 
                                       class="btn btn-outline">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                    <button onclick="confirmDelete(<?php echo $file['id']; ?>, '<?php echo addslashes($file['original_name']); ?>')"
                                            class="btn btn-danger">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function confirmDelete(fileId, filename) {
            if (confirm('Are you sure you want to delete "' + filename + '"? This action cannot be undone.')) {
                window.location.href = 'delete.php?id=' + fileId;
            }
        }

        function toggleView() {
            const grid = document.getElementById('files-grid');
            grid.classList.toggle('grid-cols-1');
            grid.classList.toggle('grid-cols-2');
            grid.classList.toggle('grid-cols-3');
        }

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
    </script>
</body>

</html>

<?php
function formatFileSize($bytes)
{
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        return $bytes . ' bytes';
    } elseif ($bytes == 1) {
        return '1 byte';
    } else {
        return '0 bytes';
    }
}
?>