<?php
session_start();
require 'db.php';

// التحقق من أن المستخدم هو المسؤول
if (!isset($_SESSION['user_id']) || !($_SESSION['is_admin'] ?? false)) {
    header("Location: login.php?error=You are not authorized.");
    exit;
}

if (isset($_GET['id'])) {
    $user_id = $_GET['id'];

    try {
        // بدء Transaction
        $pdo->beginTransaction();

        // حذف السجلات المرتبطة من الجداول الأخرى
        $stmt = $pdo->prepare("DELETE FROM login_logs WHERE user_id = ?");
        $stmt->execute([$user_id]);

        // حذف المستخدم
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);

        // إتمام Transaction
        $pdo->commit();

        header("Location: admin.php?message=User deleted successfully.");
    } catch (Exception $e) {
        // إلغاء Transaction في حالة حدوث خطأ
        $pdo->rollBack();
        header("Location: admin.php?error=Failed to delete user.");
    }
    exit;
} else {
    header("Location: admin.php?error=Invalid user ID.");
    exit;
}
?>