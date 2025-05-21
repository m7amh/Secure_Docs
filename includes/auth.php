<?php
// includes/auth.php

function isAdmin()
{
    global $pdo;

    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    return $user && $user['is_admin'];
}

function requireAdmin()
{
    if (!isAdmin()) {
        header("Location: ../login.php?error=Unauthorized access");
        exit;
    }
}

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

function getFileIcon($filename)
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    $icons = [
        'jpg' => 'fa-file-image text-primary',
        'jpeg' => 'fa-file-image text-primary',
        'png' => 'fa-file-image text-primary',
        'gif' => 'fa-file-image text-primary',
        'pdf' => 'fa-file-pdf text-danger',
        'doc' => 'fa-file-word text-primary',
        'docx' => 'fa-file-word text-primary',
        'txt' => 'fa-file-alt text-secondary'
    ];

    $default = 'fa-file text-muted';
    $class = $icons[$ext] ?? $default;

    return '<i class="fas ' . $class . ' me-2"></i>';
}
?>