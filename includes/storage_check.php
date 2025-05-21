<?php
class StorageCheck {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function canUploadFile($user_id, $file_size) {
        // Get user's current plan and storage usage
        $stmt = $this->pdo->prepare("
            SELECT u.plan, u.used_space, u.storage_limit, p.max_file_size
            FROM users u
            JOIN plans p ON u.plan = p.name
            WHERE u.id = ?
        ");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch();

        if (!$user_data) {
            return [
                'can_upload' => false,
                'error' => 'User not found'
            ];
        }

        // Check if file size exceeds plan's max file size
        if ($file_size > $user_data['max_file_size']) {
            return [
                'can_upload' => false,
                'error' => 'File size exceeds your plan limit of ' . $this->formatBytes($user_data['max_file_size'])
            ];
        }

        // Check if user has enough storage space
        if (($user_data['used_space'] + $file_size) > $user_data['storage_limit']) {
            return [
                'can_upload' => false,
                'error' => 'Not enough storage space. You have used ' . 
                          $this->formatBytes($user_data['used_space']) . 
                          ' of ' . $this->formatBytes($user_data['storage_limit'])
            ];
        }

        return [
            'can_upload' => true,
            'used_space' => $user_data['used_space'],
            'storage_limit' => $user_data['storage_limit'],
            'remaining_space' => $user_data['storage_limit'] - $user_data['used_space']
        ];
    }

    public function updateStorageUsage($user_id, $file_size) {
        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET used_space = used_space + ? 
            WHERE id = ?
        ");
        return $stmt->execute([$file_size, $user_id]);
    }

    public function getStorageInfo($user_id) {
        // First check if user exists
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        if (!$stmt->fetch()) {
            error_log("User not found in database: " . $user_id);
            return null;
        }

        // Get storage info
        $stmt = $this->pdo->prepare("
            SELECT u.plan, u.used_space, u.storage_limit, p.max_file_size, p.description
            FROM users u
            LEFT JOIN plans p ON u.plan = p.name
            WHERE u.id = ?
        ");
        $stmt->execute([$user_id]);
        $data = $stmt->fetch();

        if (!$data) {
            error_log("Storage info not found for user: " . $user_id);
            return null;
        }

        // If plan info is not found, set default values
        if (!$data['max_file_size']) {
            $data['max_file_size'] = 10485760; // 10MB default
            $data['description'] = 'Free plan with 1GB storage and 10MB file size limit';
        }

        return [
            'plan' => $data['plan'] ?? 'free',
            'used_space' => $data['used_space'] ?? 0,
            'storage_limit' => $data['storage_limit'] ?? 1073741824, // 1GB default
            'max_file_size' => $data['max_file_size'],
            'description' => $data['description'],
            'used_percentage' => ($data['used_space'] / ($data['storage_limit'] ?? 1073741824)) * 100,
            'remaining_space' => ($data['storage_limit'] ?? 1073741824) - ($data['used_space'] ?? 0)
        ];
    }

    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
?> 