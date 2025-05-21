<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?error=You must log in first.");
    exit;
}

$file_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$file_id) {
    die("Invalid file ID.");
}

// التأكد أن الملف يخص المستخدم الحالي
$stmt = $pdo->prepare("SELECT * FROM user_files WHERE id = ? AND user_id = ?");
$stmt->execute([$file_id, $_SESSION['user_id']]);
$file = $stmt->fetch();

if (!$file) {
    die("File not found or you don't have permission to delete it.");
}

$file_path = __DIR__ . '/uploads/' . $file['filename'];

if (file_exists($file_path)) {
    unlink($file_path);
}

$stmt = $pdo->prepare("DELETE FROM user_files WHERE id = ?");
$stmt->execute([$file_id]);

header("Location: upload.php?message=File deleted successfully.");
exit;
