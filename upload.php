<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/crypto.php';
require_once 'includes/storage_check.php';
require_once 'includes/file_validator.php';
require_once 'includes/user_keys.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?error=You must log in first.");
    exit;
}

$storage_check = new StorageCheck($pdo);
$file_validator = new FileValidator();

// Get user's storage info
$storage_info = $storage_check->getStorageInfo($_SESSION['user_id']);

// Check if storage info exists
if (!$storage_info) {
    // Set default values if storage info is not found
    $storage_info = [
        'plan' => 'free',
        'used_space' => 0,
        'storage_limit' => 1073741824, // 1GB default
        'max_file_size' => 10485760,   // 10MB default
        'description' => 'Free plan with 1GB storage and 10MB file size limit'
    ];
    
    // Log the error for debugging
    error_log("Storage info not found for user ID: " . $_SESSION['user_id']);
}

$max_file_size = $file_validator->getMaxSizeForPlan($storage_info['plan']);
$allowed_types = $file_validator->getAllowedTypes();

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// FEATURE: AES-256 Encryption
function encryptFile($sourcePath, $destPath, $password)
{
    $ivlen = openssl_cipher_iv_length($cipher = "AES-256-CBC");
    $iv = openssl_random_pseudo_bytes($ivlen);
    $key = hash('sha256', $password, true);

    $data = file_get_contents($sourcePath);
    $encrypted = openssl_encrypt($data, $cipher, $key, OPENSSL_RAW_DATA, $iv);

    file_put_contents($destPath, $iv . $encrypted);
}

// FEATURE: Document Hashing with SHA-256
function generateFileHash($filePath) {
    return hash_file('sha256', $filePath);
}

// FEATURE: Generate HMAC for integrity verification
function generateHMAC($data, $key) {
    return hash_hmac('sha256', $data, $key);
}

// FEATURE: Digital Signing using OpenSSL
function signDocument($data, $privateKey) {
    openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);
    return base64_encode($signature);
}

function deleteFile($fileId, $userId, $pdo)
{
    // Get file info from database
    $stmt = $pdo->prepare("SELECT * FROM user_files WHERE id = ? AND user_id = ?");
    $stmt->execute([$fileId, $userId]);
    $file = $stmt->fetch();

    if ($file) {
        // Delete physical file
        $filePath = 'uploads/' . $file['filename'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Delete database record
        $stmt = $pdo->prepare("DELETE FROM user_files WHERE id = ? AND user_id = ?");
        $stmt->execute([$fileId, $userId]);

        return true;
    }
    return false;
}

$error = '';
$success = '';

// Handle file deletion
if (isset($_GET['delete'])) {
    $fileId = $_GET['delete'];
    if (deleteFile($fileId, $_SESSION['user_id'], $pdo)) {
        $success = "File deleted successfully!";
    } else {
        $error = "Failed to delete file or file not found.";
    }
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Error uploading file';
    } else {
        $file = $_FILES['file'];
        $password = $_POST['password'] ?? '';
        
        if (empty($password)) {
            $error = 'Please enter an encryption password';
        } else {
            try {
                // Validate file
                $validation = $file_validator->validateFile($file, $max_file_size);
                
                if ($validation['valid']) {
                    // Check storage space
                    $storage_check_result = $storage_check->canUploadFile($_SESSION['user_id'], $file['size']);
                    
                    if ($storage_check_result['can_upload']) {
                        // Process file upload
                        $upload_dir = 'uploads/' . $_SESSION['user_id'] . '/';
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }

                        $filename = uniqid() . '_' . basename($file['name']);
                        $filepath = $upload_dir . $filename;

                        if (move_uploaded_file($file['tmp_name'], $filepath)) {
                            // Generate SHA-256 hash of the original file
                            $fileHash = hash_file('sha256', $filepath);
                            
                            // Generate CRC32 checksum
                            $crc32 = hash_file('crc32', $filepath);
                            
                            // Get or generate user keys for digital signature
                            $userKeys = new UserKeys($pdo);
                            $keys = $userKeys->ensureUserKeys($_SESSION['user_id']);
                            
                            // Generate digital signature
                            $privateKey = openssl_pkey_get_private($keys['private_key']);
                            openssl_sign($fileHash, $signature, $privateKey, OPENSSL_ALGO_SHA256);
                            $digitalSignature = base64_encode($signature);
                            
                            // Insert into user_files table first to get the file ID
                            $stmt = $pdo->prepare("
                                INSERT INTO user_files (
                                    user_id, filename, original_name, filesize, filetype, sha256_hash
                                ) VALUES (?, ?, ?, ?, ?, ?)
                            ");
                            // Initially save with the original unique filename, will update later with .enc
                            $stmt->execute([
                                $_SESSION['user_id'],
                                $filename,
                                $file['name'],
                                $file['size'],
                                $validation['mime_type'],
                                $fileHash
                            ]);
                            
                            $fileId = $pdo->lastInsertId();

                            // Use FileCrypto to encrypt the file, save it, and get HMAC
                            // encryptAndHmacFile takes the source path and password
                            $fileCrypto = new FileCrypto($pdo);
                            $encryptionResult = $fileCrypto->encryptAndHmacFile($filepath, $password);
                            // $encryptionResult contains 'encrypted_filepath', 'iv', 'hmac'
                            $encryptedFilepath = $encryptionResult['encrypted_filepath'];
                            $iv = $encryptionResult['iv']; // IV is hex encoded
                            $hmac = $encryptionResult['hmac'];

                            // Now update the filename in the user_files table to the encrypted filename (.enc)
                            $encryptedFilename = basename($encryptedFilepath);
                            $stmt = $pdo->prepare("UPDATE user_files SET filename = ? WHERE id = ?");
                            $stmt->execute([$encryptedFilename, $fileId]);

                            // Insert metadata into encrypted_files table
                            $stmt = $pdo->prepare("
                                INSERT INTO encrypted_files (
                                    file_id, file_hash, digital_signature, signature_algorithm,
                                    encryption_algorithm, iv, hmac, crc32, integrity_status
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'verified')
                            ");
                            $stmt->execute([
                                $fileId,
                                $fileHash,
                                $digitalSignature,
                                'SHA256withRSA',
                                'AES-256-CBC',
                                $iv, // Use the hex encoded IV from encryptAndHmacFile result
                                $hmac,
                                $crc32
                            ]);

                            // Update storage usage
                            $storage_check->updateStorageUsage($_SESSION['user_id'], $file['size']);

                            // Log the upload
                            $stmt = $pdo->prepare("
                                INSERT INTO file_logs (user_id, action, filename)
                                VALUES (?, 'upload', ?)
                            ");
                            // Log with the original unique filename for clarity
                            $stmt->execute([$_SESSION['user_id'], $filename]);

                            $success = 'File uploaded and secured successfully!';
                        } else {
                            $error = 'Error uploading file. Please try again.';
                        }
                    } else {
                        $error = $storage_check_result['error'];
                    }
                } else {
                    $error = $validation['error'];
                }
            } catch (Exception $e) {
                $error = $e->getMessage();
                // Delete file if error occurs
                if (isset($filepath) && file_exists($filepath)) {
                    unlink($filepath);
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload File | Secure Storage</title>
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

        .upload-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .upload-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .upload-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1.5rem;
            font-weight: 600;
        }

        .upload-body {
            padding: 2rem;
        }

        .storage-info {
            background: #F3F4F6;
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .storage-bar {
            height: 0.5rem;
            background: #E5E7EB;
            border-radius: 9999px;
            overflow: hidden;
            margin: 1rem 0;
        }

        .storage-progress {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            border-radius: 9999px;
            transition: width 0.3s ease;
        }

        .drop-zone {
            border: 2px dashed #E5E7EB;
            border-radius: 1rem;
            padding: 3rem 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #F9FAFB;
        }

        .drop-zone:hover, .drop-zone.dragover {
            border-color: var(--primary-color);
            background: #EEF2FF;
        }

        .drop-zone i {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .password-input {
            position: relative;
            margin: 1.5rem 0;
        }

        .password-input input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #E5E7EB;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }

        .password-input input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(43, 77, 64, 0.1);
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6B7280;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: var(--primary-color);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn i {
            margin-right: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .file-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .file-table th {
            background: #F3F4F6;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--dark-color);
        }

        .file-table td {
            padding: 1rem;
            border-bottom: 1px solid #E5E7EB;
        }

        .file-table tr:hover {
            background: #F9FAFB;
        }

        .file-icon {
            font-size: 1.5rem;
            margin-right: 0.5rem;
        }

        .action-btn {
            padding: 0.5rem;
            border-radius: 0.375rem;
            transition: all 0.2s;
        }

        .action-btn:hover {
            transform: translateY(-1px);
        }

        .action-btn.download {
            color: var(--primary-color);
        }

        .action-btn.delete {
            color: var(--danger-color);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .animate-fade-in {
            animation: fadeIn 0.5s ease-out;
        }

        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: #ECFDF5;
            color: #065F46;
            border: 1px solid #A7F3D0;
        }

        .alert-error {
            background: #FEF2F2;
            color: #991B1B;
            border: 1px solid #FECACA;
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
                    <a href="files.php" class="btn btn-outline">
                        <i class="fas fa-folder"></i> My Files
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="upload-container">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Upload New File</h1>
            <p class="text-gray-600 mt-2">Upload and encrypt your files with AES-256 encryption</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error animate-fade-in">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php elseif ($success): ?>
            <div class="alert alert-success animate-fade-in">
                <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <div class="upload-card animate-fade-in">
            <div class="upload-header">
                <i class="fas fa-cloud-upload-alt mr-2"></i>Upload New File
            </div>
            <div class="upload-body">
                <div class="storage-info">
                    <h3 class="text-lg font-semibold text-gray-900">Storage Information</h3>
                    <div class="mt-2">
                        <div class="flex justify-between text-sm text-gray-600">
                            <span>Used: <?php echo formatBytes($storage_info['used_space']); ?></span>
                            <span>Total: <?php echo formatBytes($storage_info['storage_limit']); ?></span>
                        </div>
                        <div class="storage-bar">
                            <div class="storage-progress" style="width: <?php echo ($storage_info['used_space'] / $storage_info['storage_limit']) * 100; ?>%"></div>
                        </div>
                    </div>
                    <p class="text-sm text-gray-600 mt-2">
                        Max file size: <?php echo formatBytes($max_file_size); ?>
                    </p>
                </div>

                <form method="POST" enctype="multipart/form-data" class="upload-form">
                    <div class="drop-zone" id="dropZone">
                        <input type="file" name="file" id="fileInput" class="hidden" accept="<?php echo implode(',', $allowed_types); ?>">
                        <div class="upload-content">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p class="text-lg font-medium text-gray-900 mt-2">Drag & drop files here</p>
                            <p class="text-sm text-gray-600 mt-1">or click to browse</p>
                            <p class="text-xs text-gray-500 mt-2">
                                Allowed types: <?php echo implode(', ', $allowed_types); ?>
                            </p>
                        </div>
                    </div>

                    <div class="password-input">
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                            Encryption Password
                        </label>
                        <input type="password" name="password" id="password" 
                               class="w-full" placeholder="Enter a strong password for encryption" required>
                        <span class="password-toggle" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </span>
                        <p class="text-xs text-gray-500 mt-1">
                            This password will be required to decrypt the file. Keep it safe!
                        </p>
                    </div>

                    <button type="submit" class="btn btn-primary w-full">
                        <i class="fas fa-lock"></i> Upload & Encrypt File
                    </button>
                </form>
            </div>
        </div>

        <div class="upload-card animate-fade-in" style="animation-delay: 0.2s">
            <div class="upload-header">
                <i class="fas fa-folder mr-2"></i>Your Encrypted Files
            </div>
            <div class="upload-body p-0">
                <div class="overflow-x-auto">
                    <table class="file-table">
                        <thead>
                            <tr>
                                <th>File Name</th>
                                <th>Size</th>
                                <th>Type</th>
                                <th>Uploaded</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $pdo->prepare("SELECT * FROM user_files WHERE user_id = ? ORDER BY uploaded_at DESC");
                            $stmt->execute([$_SESSION['user_id']]);
                            $files = $stmt->fetchAll();

                            if ($files) {
                                foreach ($files as $file) {
                                    $icon = '';
                                    $ext = strtolower(pathinfo($file['original_name'], PATHINFO_EXTENSION));

                                    if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                                        $icon = '<i class="fas fa-image text-blue-500"></i>';
                                    } elseif ($ext === 'pdf') {
                                        $icon = '<i class="fas fa-file-pdf text-red-500"></i>';
                                    } elseif ($ext === 'docx') {
                                        $icon = '<i class="fas fa-file-word text-blue-500"></i>';
                                    } elseif ($ext === 'txt') {
                                        $icon = '<i class="fas fa-file-alt text-gray-500"></i>';
                                    } else {
                                        $icon = '<i class="fas fa-file text-gray-400"></i>';
                                    }

                                    echo "<tr>";
                                    echo "<td class='flex items-center'>" . $icon . " " . htmlspecialchars($file['original_name']) . "</td>";
                                    echo "<td>" . formatBytes($file['filesize']) . "</td>";
                                    echo "<td>" . strtoupper($ext) . "</td>";
                                    echo "<td>" . date('M d, Y H:i', strtotime($file['uploaded_at'])) . "</td>";
                                    echo "<td class='flex space-x-2'>";
                                    echo '<a href="download.php?id=' . $file['id'] . '" class="action-btn download" title="Download"><i class="fas fa-download"></i></a>';
                                    echo '<a href="upload.php?delete=' . $file['id'] . '" class="action-btn delete" title="Delete" onclick="return confirm(\'Are you sure you want to delete this file? This action cannot be undone.\')"><i class="fas fa-trash-alt"></i></a>';
                                    echo "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo '<tr><td colspan="5" class="text-center py-8 text-gray-500">No files uploaded yet.</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dropZone = document.getElementById('dropZone');
            const fileInput = document.getElementById('fileInput');
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');

            // Handle click on drop zone
            dropZone.addEventListener('click', () => fileInput.click());

            // Handle file selection
            fileInput.addEventListener('change', () => {
                if (fileInput.files.length > 0) {
                    handleFiles(fileInput.files);
                }
            });

            // Handle drag and drop
            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropZone.classList.add('dragover');
            });

            dropZone.addEventListener('dragleave', () => {
                dropZone.classList.remove('dragover');
            });

            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZone.classList.remove('dragover');
                if (e.dataTransfer.files.length > 0) {
                    fileInput.files = e.dataTransfer.files;
                    handleFiles(e.dataTransfer.files);
                }
            });

            // Toggle password visibility
            togglePassword.addEventListener('click', () => {
                const type = passwordInput.type === 'password' ? 'text' : 'password';
                passwordInput.type = type;
                togglePassword.querySelector('i').classList.toggle('fa-eye');
                togglePassword.querySelector('i').classList.toggle('fa-eye-slash');
            });

            function handleFiles(files) {
                const file = files[0];
                const maxSize = <?php echo $max_file_size; ?>;
                
                if (file.size > maxSize) {
                    alert('File size exceeds the limit of <?php echo formatBytes($max_file_size); ?>');
                    return;
                }

                const content = dropZone.querySelector('.upload-content');
                content.innerHTML = `
                    <i class="fas fa-file"></i>
                    <p class="text-lg font-medium text-gray-900 mt-2">${file.name}</p>
                    <p class="text-sm text-gray-600 mt-1">${formatBytes(file.size)}</p>
                `;
            }

            function formatBytes(bytes, decimals = 2) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const dm = decimals < 0 ? 0 : decimals;
                const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
            }
        });
    </script>
</body>
</html>