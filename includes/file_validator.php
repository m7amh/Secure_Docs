<?php
class FileValidator {
    private $allowed_types = [
        'application/pdf' => 'PDF',
        'image/jpeg' => 'JPEG',
        'image/png' => 'PNG',
        'text/plain' => 'TXT'
    ];

    private $allowed_extensions = [
        'pdf' => 'PDF',
        'jpg' => 'JPEG',
        'jpeg' => 'JPEG',
        'png' => 'PNG',
        'txt' => 'TXT'
    ];

    public function validateFile($file, $max_size) {
        $errors = [];

        // Check if file was uploaded
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            return [
                'valid' => false,
                'error' => 'No file was uploaded'
            ];
        }

        // Check file size
        if ($file['size'] > $max_size) {
            return [
                'valid' => false,
                'error' => 'File size exceeds the limit of ' . $this->formatBytes($max_size)
            ];
        }

        // Get file info
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        // Get file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        // Validate file type
        if (!isset($this->allowed_types[$mime_type]) && !isset($this->allowed_extensions[$extension])) {
            $allowed_types = implode(', ', array_unique(array_values($this->allowed_types)));
            return [
                'valid' => false,
                'error' => 'File type not allowed. Allowed types: ' . $allowed_types
            ];
        }

        // Additional validation for images
        if (strpos($mime_type, 'image/') === 0) {
            $image_info = getimagesize($file['tmp_name']);
            if ($image_info === false) {
                return [
                    'valid' => false,
                    'error' => 'Invalid image file'
                ];
            }
        }

        return [
            'valid' => true,
            'mime_type' => $mime_type,
            'extension' => $extension,
            'type_name' => $this->allowed_types[$mime_type] ?? $this->allowed_extensions[$extension]
        ];
    }

    public function getAllowedTypes() {
        return array_unique(array_values($this->allowed_types));
    }

    public function getMaxSizeForPlan($plan_name) {
        $sizes = [
            'free' => 10485760,      // 10MB
            'premium' => 83886080,   // 80MB
            'enterprise' => 157286400 // 150MB
        ];
        return $sizes[$plan_name] ?? $sizes['free'];
    }

    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
?> 