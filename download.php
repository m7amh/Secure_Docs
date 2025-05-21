<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/user_keys.php';
require_once 'includes/crypto.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?error=You must log in first.");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: files.php?error=No file specified.");
    exit;
}

$fileId = $_GET['id'];
$password = $_POST['password'] ?? '';

// Get file information
$stmt = $pdo->prepare("
    SELECT uf.*, ef.* 
    FROM user_files uf 
    JOIN encrypted_files ef ON uf.id = ef.file_id 
    WHERE uf.id = ? AND uf.user_id = ?
");
$stmt->execute([$fileId, $_SESSION['user_id']]);
$file = $stmt->fetch();

if (!$file) {
    header("Location: files.php?error=File not found.");
    exit;
}

$verificationResults = [
    'hmac' => false,
    'crc32' => false,
    'signature' => false
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($password)) {
    $filepath = 'uploads/' . $_SESSION['user_id'] . '/' . $file['filename'];
    
    if (file_exists($filepath)) {
        // Read encrypted file
        $encryptedData = file_get_contents($filepath);
        
        
        // Extract IV and encrypted content
        $ivlen = openssl_cipher_iv_length($cipher = "AES-256-CBC");
        $iv = hex2bin($file['iv']);
        $encrypted = substr($encryptedData, $ivlen);
        
        // Initialize FileCrypto for HMAC verification
        $fileCrypto = new FileCrypto($pdo);
        
        // Verify HMAC using FileCrypto
        $calculatedHmac = $fileCrypto->calculateHmacForData($encryptedData);
        $verificationResults['hmac'] = hash_equals($file['hmac'], $calculatedHmac);
        
        // Decrypt file
        $key = hash('sha256', $password, true);
        $decrypted = openssl_decrypt($encrypted, $cipher, $key, OPENSSL_RAW_DATA, $iv);
        
        if ($decrypted !== false) {
            // Verify CRC32
            $calculatedCrc32 = hash('crc32', $decrypted);
            $verificationResults['crc32'] = hash_equals($file['crc32'], $calculatedCrc32);
            
            // Verify digital signature
            $userKeys = new UserKeys($pdo);
            $publicKey = $userKeys->getPublicKey($_SESSION['user_id']);
            if ($publicKey) {
                $decodedSignature = base64_decode($file['digital_signature']);
                $calculatedHash = hash('sha256', $decrypted);
                $verificationResults['signature'] = openssl_verify(
                    $calculatedHash,
                    $decodedSignature,
                    $publicKey,
                    OPENSSL_ALGO_SHA256
                ) === 1;
            }
            
            // Update verification status in database
            $integrityStatus = 'verified';
            if (!$verificationResults['hmac'] && !$verificationResults['crc32']) {
                $integrityStatus = 'both_failed';
            } elseif (!$verificationResults['hmac']) {
                $integrityStatus = 'hmac_failed';
            } elseif (!$verificationResults['crc32']) {
                $integrityStatus = 'crc_failed';
            }
            
            $stmt = $pdo->prepare("
                UPDATE encrypted_files 
                SET verification_status = ?, 
                    last_verified = CURRENT_TIMESTAMP,
                    integrity_status = ?
                WHERE file_id = ?
            ");
            $stmt->execute([
                $verificationResults['signature'] ? 1 : 0,
                $integrityStatus,
                $fileId
            ]);
            
            // If all verifications passed, serve the file
            if ($verificationResults['hmac'] && $verificationResults['crc32'] && $verificationResults['signature']) {
                // Update download count and last download time
                $stmt = $pdo->prepare("
                    UPDATE user_files 
                    SET download_count = download_count + 1,
                        last_download = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmt->execute([$fileId]);
                
                // Log the download
                $stmt = $pdo->prepare("
                    INSERT INTO file_logs (user_id, action, filename)
                    VALUES (?, 'download', ?)
                ");
                $stmt->execute([$_SESSION['user_id'], $file['filename']]);
                
                // Display success message with validation details
                echo '<div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full" id="successModal">
                    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                        <div class="mt-3 text-center">
                            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100">
                                <i class="fas fa-check text-green-600 text-xl"></i>
                            </div>
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mt-4">File Verification Successful</h3>
                            <div class="mt-4 px-7 py-3">
                                <div class="text-sm text-gray-500 text-left space-y-2">
                                    <p><strong>HMAC Verification:</strong> <span class="text-green-600">Valid</span></p>
                                    <p><strong>Database HMAC:</strong> <span class="font-mono text-xs">' . htmlspecialchars($file['hmac']) . '</span></p>
                                    <p><strong>Validated HMAC:</strong> <span class="font-mono text-xs">' . htmlspecialchars($calculatedHmac) . '</span></p>
                                    <p><strong>Digital Signature:</strong> <span class="text-green-600">Valid</span></p>
                                    <p><strong>CRC32:</strong> <span class="text-green-600">Valid</span></p>
                                </div>
                            </div>
                            <div class="items-center px-4 py-3">
                                <button onclick="window.location.href=\'files.php\'" class="px-4 py-2 bg-[#2B4D40] text-white text-base font-medium rounded-md shadow-sm hover:bg-[#6a8279] focus:outline-none focus:ring-2 focus:ring-[#2B4D40]">
                                    Return to Files
                                </button>
                            </div>
                        </div>
                    </div>
                </div>';
                
                // Serve the file
                header('Content-Type: ' . $file['filetype']);
                header('Content-Disposition: attachment; filename="' . $file['original_name'] . '"');
                header('Content-Length: ' . strlen($decrypted));
                echo $decrypted;
                exit;
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
    <title>Download File | Secure Storage</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2B4D40; /* Green theme primary */
            --secondary-color: #6a8279; /* Green theme secondary */
            --success-color: #10B981;
            --danger-color: #EF4444;
            --background-color: #F9FAFB; /* Light background */
            --card-background: #FFFFFF; /* White card background */
            --text-color: #1F2937; /* Dark gray text */
            --secondary-text-color: #6B7280; /* Medium gray text */
            --border-color: #E5E7EB; /* Light gray border */
        }

        body {
            background-color: var(--background-color);
            font-family: 'Inter', sans-serif;
            color: var(--text-color);
        }

        .header {
            background-color: var(--primary-color);
            padding: 1rem 0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .header-logo {
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .header-logo i {
            margin-right: 0.5rem;
        }

        .header-nav {
            display: flex;
            gap: 1.5rem;
        }

        .header-nav a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease;
        }

        .header-nav a:hover {
            color: var(--secondary-color);
        }

        .header-user {
            color: white;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .header-user i {
            font-size: 1.2rem;
        }

        .container {
            max-width: 640px;
        }

        .download-card {
            background: var(--card-background);
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            padding: 2rem;
        }

        .download-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .download-header h1 {
            font-size: 1.875rem; /* 3xl */
            font-weight: 700; /* bold */
            color: var(--text-color);
            margin-left: 0.5rem;
        }

        .file-info {
            margin-bottom: 1.5rem;
            padding: 1.5rem;
            background-color: #F3F4F6; /* Light gray background */
            border-radius: 0.75rem;
        }

        .file-info h2 {
            font-size: 1.25rem; /* xl */
            font-weight: 600; /* semibold */
            color: var(--text-color);
            margin-bottom: 0.75rem;
        }

        .file-info p {
            color: var(--secondary-text-color);
            margin-bottom: 0.5rem;
        }

        .verification-results {
            margin-bottom: 1.5rem;
            padding: 1.5rem;
            background-color: #F3F4F6; /* Light gray background */
            border-radius: 0.75rem;
        }

        .verification-results h2 {
            font-size: 1.25rem; /* xl */
            font-weight: 600; /* semibold */
            color: var(--text-color);
            margin-bottom: 0.75rem;
        }

        .verification-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
            color: var(--secondary-text-color);
        }

        .verification-icon {
            font-size: 1.25rem; /* Match text size */
            margin-right: 0.75rem;
        }

        .verification-success {
            color: var(--success-color);
        }

        .verification-failure {
            color: var(--danger-color);
        }

        .verification-failure-message {
            margin-top: 1.5rem;
            padding: 1rem;
            background-color: #FEF2F2; /* Red light background */
            color: #991B1B; /* Red dark text */
            border-left: 4px solid var(--danger-color); /* Red border */
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
        }

        .verification-failure-message i {
            margin-right: 0.75rem;
            font-size: 1.5rem;
        }

        .password-form label {
            display: block;
            font-size: 0.875rem; /* sm */
            font-weight: 500; /* medium */
            color: var(--text-color);
            margin-bottom: 0.5rem;
        }

        .password-form input[type="password"] {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05);
            transition: border-color 0.2s ease-in-out;
        }

        .password-form input[type="password"]:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.1rem rgba(43, 77, 64, 0.25);
        }

        .btn-download {
            display: inline-block;
            width: 100%;
            padding: 0.75rem;
            text-align: center;
            background: var(--primary-color);
            color: white;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: background-color 0.2s ease-in-out;
        }

        .btn-download:hover {
            background: var(--secondary-color);
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 1.5rem;
            color: var(--primary-color);
            transition: color 0.2s ease-in-out;
        }

        .back-link:hover {
            color: var(--secondary-color);
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Header Bar -->
    <header class="header">
        <div class="header-content">
            <a href="index.php" class="header-logo">
                <i class="fas fa-shield-alt"></i>
                Secure Storage
            </a>
            <nav class="header-nav">
                <a href="files.php"><i class="fas fa-file-alt"></i> Files</a>
                <a href="upload.php"><i class="fas fa-upload"></i> Upload</a>
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
            </nav>
            <div class="header-user">
                <i class="fas fa-user-circle"></i>
                <span><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
            </div>
        </div>
    </header>

    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h1 class="text-2xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-download mr-2"></i>Download File
                </h1>
                
                <div class="mb-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-2">File Information</h2>
                    <p class="text-gray-600">Name: <?php echo htmlspecialchars($file['original_name']); ?></p>
                    <p class="text-gray-600">Size: <?php echo number_format($file['filesize'] / 1024, 2) . ' KB'; ?></p>
                    <p class="text-gray-600">Type: <?php echo htmlspecialchars($file['filetype']); ?></p>
                </div>

                <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                    <div class="mb-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-2">Verification Results</h2>
                        
                        <div class="space-y-2">
                            <div class="flex items-center">
                                <i class="fas fa-shield-alt verification-icon <?php echo $verificationResults['hmac'] ? 'verification-success' : 'verification-failure'; ?>"></i>
                                <span>HMAC Verification: <?php echo $verificationResults['hmac'] ? 'Success' : 'Failed'; ?></span>
                            </div>
                            
                            <div class="flex items-center">
                                <i class="fas fa-fingerprint verification-icon <?php echo $verificationResults['crc32'] ? 'verification-success' : 'verification-failure'; ?>"></i>
                                <span>CRC32 Verification: <?php echo $verificationResults['crc32'] ? 'Success' : 'Failed'; ?></span>
                            </div>
                            
                            <div class="flex items-center">
                                <i class="fas fa-signature verification-icon <?php echo $verificationResults['signature'] ? 'verification-success' : 'verification-failure'; ?>"></i>
                                <span>Digital Signature: <?php echo $verificationResults['signature'] ? 'Valid' : 'Invalid'; ?></span>
                            </div>
                        </div>
                        
                        <?php if (!$verificationResults['hmac'] || !$verificationResults['crc32'] || !$verificationResults['signature']): ?>
                            <div class="mt-4 p-4 bg-red-50 text-red-700 rounded-lg">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                File integrity verification failed. The file may have been tampered with.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Debugging Section: HMAC and Signature Values -->
                    <div class="mb-6 p-4 bg-gray-100 rounded-lg">
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Debugging Information</h3>
                        <div class="space-y-2 text-sm text-gray-700">
                            <p><strong>Database HMAC:</strong> <?php echo htmlspecialchars($file['hmac']); ?></p>
                            <p><strong>Calculated HMAC:</strong> <?php echo htmlspecialchars($calculatedHmac ?? 'N/A'); ?></p>
                            <p><strong>HMAC Match:</strong> <?php echo $verificationResults['hmac'] ? 'Match' : 'No Match'; ?></p>
                            <p><strong>Database Digital Signature (Base64):</strong> <?php echo htmlspecialchars($file['digital_signature']); ?></p>
                            <p><strong>Signature Verification Result:</strong> <?php echo $verificationResults['signature'] ? 'Valid' : 'Invalid'; ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-4">
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                            Encryption Password
                        </label>
                        <input type="password" name="password" id="password" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#2B4D40] focus:border-[#2B4D40]"
                               placeholder="Enter the encryption password">
                    </div>
                    
                    <button type="submit" class="w-full bg-[#2B4D40] text-white py-2 px-4 rounded-md hover:bg-[#6a8279] transition-colors">
                        <i class="fas fa-download mr-2"></i>Download File
                    </button>
                </form>
                
                <div class="mt-4 text-center">
                    <a href="files.php" class="text-[#2B4D40] hover:text-[#6a8279]">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Files
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>