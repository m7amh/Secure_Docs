<?php
session_start();
require 'db.php';

// التحقق من أن المستخدم هو المسؤول
if (!isset($_SESSION['user_id']) || !($_SESSION['is_admin'] ?? false)) {
    header("Location: login.php?error=You are not authorized.");
    exit;
}

// التحقق من وجود ID صحيح
$user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$user_id) {
    die("Invalid user ID.");
}

// حماية الأدمن الأساسي أو الأدمن الحالي
if ($user_id == 1 || $user_id == $_SESSION['user_id']) {
    die("You cannot change this admin status.");
}

// الحصول على حالة is_admin الحالية
$stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($user) {
    $new_admin_status = !$user['is_admin']; // تبديل الحالة
    $stmt = $pdo->prepare("UPDATE users SET is_admin = ? WHERE id = ?");
    $stmt->execute([$new_admin_status, $user_id]);

    header("Location: admin.php?message=Admin status updated successfully.");
    exit;
} else {
    die("User not found.");
}
