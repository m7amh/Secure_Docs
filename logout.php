<?php
session_start();
require 'db.php';

// حذف ملف تعريف الارتباط إذا كان موجودًا
if (isset($_COOKIE['auth_token'])) {
    $token = $_COOKIE['auth_token'];
    $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL WHERE remember_token = ?");
    $stmt->execute([$token]);
    setcookie('auth_token', '', time() - 3600, "/");
}

// إنهاء الجلسة
session_destroy();

// إعادة توجيه المستخدم إلى صفحة تسجيل الدخول مع رسالة نجاح
header("Location: login.php?message=Logged out successfully.");
exit;
?>