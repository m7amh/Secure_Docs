<?php
session_start();
require 'includes/security.php';
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $file_id = $_POST['file_id'] ?? null;
    $new_name = $_POST['new_name'] ?? null;
    
    if ($file_id && $new_name) {
        // Get file info - different query based on user role
        if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
            // Admin can edit any file
            $stmt = $pdo->prepare("SELECT * FROM user_files WHERE id = ?");
            $stmt->execute([$file_id]);
        } else {
            // Regular users can only edit their own files
            $stmt = $pdo->prepare("SELECT * FROM user_files WHERE id = ? AND user_id = ?");
            $stmt->execute([$file_id, $_SESSION['user_id']]);
        }
        
        $file = $stmt->fetch();
        
        if ($file) {
            // Get file extension
            $ext = pathinfo($file['original_name'], PATHINFO_EXTENSION);
            $new_name = $new_name . '.' . $ext;
            
            // Update file name in database
            $stmt = $pdo->prepare("UPDATE user_files SET original_name = ? WHERE id = ?");
            if ($stmt->execute([$new_name, $file_id])) {
                // Log the rename action
                $stmt = $pdo->prepare("INSERT INTO file_logs (user_id, action, filename) VALUES (?, 'rename', ?)");
                $stmt->execute([$_SESSION['user_id'], $new_name]);
                
                echo json_encode(['success' => true]);
                exit;
            }
        }
    }
    
    echo json_encode(['success' => false, 'error' => 'Failed to rename file']);
    exit;
} 