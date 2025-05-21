<?php
// Define ENVIRONMENT if not already defined
if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', 'production'); // Default to production
}

require_once 'db.php';

class FileCrypto {
    private $pdo;
    private $private_key;
    private $public_key;
    private $keys_dir;
    private $hmac_key;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->keys_dir = __DIR__ . '/../keys';
        $this->initializeKeys();
        $this->initializeHmacKey();
    }

    private function initializeKeys() {
        try {
            // Create keys directory if it doesn't exist
            if (!file_exists($this->keys_dir)) {
                if (!@mkdir($this->keys_dir, 0700, true)) {
                    throw new Exception('Failed to create keys directory. Please check directory permissions.');
                }
            }

            // Check if directory is writable
            if (!is_writable($this->keys_dir)) {
                throw new Exception('Keys directory is not writable. Please check directory permissions.');
            }

            $private_key_path = $this->keys_dir . '/private.key';
            $public_key_path = $this->keys_dir . '/public.key';

            // If keys exist, try to load them
            if (file_exists($private_key_path) && file_exists($public_key_path)) {
                $this->private_key = @file_get_contents($private_key_path);
                $this->public_key = @file_get_contents($public_key_path);
                
                if ($this->private_key && $this->public_key) {
                    return; // Keys loaded successfully
                }
            }

            // Generate new key pair
            $config = array(
                "config" => "C:/xampp/php/extras/openssl/openssl.cnf", // Windows XAMPP path
                "private_key_bits" => 2048,
                "private_key_type" => OPENSSL_KEYTYPE_RSA,
            );

            // Try to generate new key pair
            $res = @openssl_pkey_new($config);
            if (!$res) {
                // If first attempt fails, try without config
                $config = array(
                    "private_key_bits" => 2048,
                    "private_key_type" => OPENSSL_KEYTYPE_RSA,
                );
                $res = @openssl_pkey_new($config);
                
                if (!$res) {
                    throw new Exception('Failed to generate RSA keys. OpenSSL error: ' . openssl_error_string());
                }
            }

            // Export private key
            if (!@openssl_pkey_export($res, $private_key, null, $config)) {
                throw new Exception('Failed to export private key: ' . openssl_error_string());
            }

            // Export public key
            $details = @openssl_pkey_get_details($res);
            if (!$details) {
                throw new Exception('Failed to get public key details: ' . openssl_error_string());
            }
            $public_key = $details['key'];

            // Save keys to files
            if (!@file_put_contents($private_key_path, $private_key) || 
                !@file_put_contents($public_key_path, $public_key)) {
                throw new Exception('Failed to save RSA keys. Please check file permissions.');
            }

            // Set proper permissions
            @chmod($private_key_path, 0600);
            @chmod($public_key_path, 0644);

            // Load the newly created keys
            $this->private_key = $private_key;
            $this->public_key = $public_key;

        } catch (Exception $e) {
            // Log the error
            error_log('FileCrypto initialization error: ' . $e->getMessage());
            
            // If we're in development environment, show detailed error
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                throw new Exception('FileCrypto initialization failed: ' . $e->getMessage());
            } else {
                throw new Exception('Failed to initialize encryption system. Please contact support.');
            }
        }
    }

    private function initializeHmacKey() {
        $hmac_key_file = $this->keys_dir . '/hmac.key';
        if (!file_exists($hmac_key_file)) {
            // Generate a random HMAC key
            $this->hmac_key = bin2hex(random_bytes(32));
            file_put_contents($hmac_key_file, $this->hmac_key);
            chmod($hmac_key_file, 0600);
        } else {
            $this->hmac_key = file_get_contents($hmac_key_file);
        }
    }

    private function calculateCRC32($data) {
        return hash('crc32b', $data);
    }

    private function calculateHMAC($data) {
        return hash_hmac('sha256', $data, $this->hmac_key);
    }

    /**
     * Calculates the HMAC of given data using the system's HMAC key.
     *
     * @param string $data The data to calculate HMAC for.
     * @return string The calculated HMAC.
     */
    public function calculateHmacForData($data) {
        return $this->calculateHMAC($data);
    }

    /**
     * Encrypts a file, saves the encrypted version, and calculates its HMAC.
     *
     * @param string $sourceFilePath The path to the temporary uploaded file.
     * @param string $password The user's encryption password.
     * @return array An array containing the encrypted file path, IV (hex), and HMAC.
     * @throws Exception If file operations or encryption fail.
     */
    public function encryptAndHmacFile($sourceFilePath, $password) {
        try {
            if (!file_exists($sourceFilePath)) {
                throw new Exception("Source file not found: " . $sourceFilePath);
            }

            // Read file content
            $content = file_get_contents($sourceFilePath);
            if ($content === false) {
                throw new Exception("Failed to read file content from " . $sourceFilePath);
            }

            // Generate encryption key from password
            $key = hash('sha256', $password, true);
            $ivlen = openssl_cipher_iv_length($cipher = "AES-256-CBC");
            $iv = openssl_random_pseudo_bytes($ivlen);

            // Encrypt the content
            $encrypted = openssl_encrypt($content, $cipher, $key, OPENSSL_RAW_DATA, $iv);
            if ($encrypted === false) {
                throw new Exception("Encryption failed");
            }

            // Save encrypted file to a temporary path first (including IV)
            $encrypted_path_temp = $sourceFilePath . '.enc_temp';
            if (file_put_contents($encrypted_path_temp, $iv . $encrypted) === false) {
                 throw new Exception("Failed to save temporary encrypted file");
            }

            // Calculate HMAC of the temporary encrypted file content
            $hmac = $this->calculateHMAC(file_get_contents($encrypted_path_temp));

            // Rename temporary file to the final encrypted path
            $final_encrypted_path = $sourceFilePath . '.enc';
            if (!rename($encrypted_path_temp, $final_encrypted_path)) {
                 // Clean up temporary file if rename fails
                 if(file_exists($encrypted_path_temp)) { unlink($encrypted_path_temp); }
                 throw new Exception("Failed to rename temporary encrypted file");
            }

            // Remove original file
            if(file_exists($sourceFilePath)) { unlink($sourceFilePath); }

            return [
                'encrypted_filepath' => $final_encrypted_path,
                'iv' => bin2hex($iv),
                'hmac' => $hmac
            ];

        } catch (Exception $e) {
            throw new Exception("Encryption failed: " . $e->getMessage());
        }
    }

    public function decryptFile($file_id, $password) {
        try {
            // Get file and encryption information
            $stmt = $this->pdo->prepare("
                SELECT f.*, e.* 
                FROM user_files f 
                JOIN encrypted_files e ON f.id = e.file_id 
                WHERE f.id = ?
            ");
            $stmt->execute([$file_id]);
            $file_info = $stmt->fetch();

            if (!$file_info) {
                throw new Exception("File not found or access denied");
            }

            $encrypted_path = __DIR__ . '/../uploads/' . $file_info['filename'] . '.enc';
            if (!file_exists($encrypted_path)) {
                throw new Exception("Encrypted file not found");
            }

            // Read encrypted content
            $encrypted_content = file_get_contents($encrypted_path);
            if ($encrypted_content === false) {
                throw new Exception("Failed to read encrypted file");
            }

            // Verify HMAC
            $calculated_hmac = $this->calculateHMAC($encrypted_content);
            if (!hash_equals($file_info['hmac'], $calculated_hmac)) {
                // Update integrity status
                $this->updateIntegrityStatus($file_id, 'hmac_failed');
                throw new Exception("File integrity check failed: HMAC verification failed");
            }

            // Decrypt the content
            $key = hash('sha256', $password, true);
            $iv = base64_decode($file_info['iv']);
            $decrypted = openssl_decrypt(
                $encrypted_content,
                'aes-256-cbc',
                $key,
                OPENSSL_RAW_DATA,
                $iv
            );

            if ($decrypted === false) {
                throw new Exception("Decryption failed");
            }

            // Verify CRC32
            $calculated_crc = $this->calculateCRC32($decrypted);
            if (!hash_equals($file_info['crc32'], $calculated_crc)) {
                // Update integrity status
                $this->updateIntegrityStatus($file_id, 'crc_failed');
                throw new Exception("File integrity check failed: CRC32 verification failed");
            }

            // Verify digital signature
            $file_hash = hash('sha256', $decrypted);
            $signature = base64_decode($file_info['digital_signature']);
            
            if (!openssl_verify(
                $file_hash,
                $signature,
                $this->public_key,
                OPENSSL_ALGO_SHA256
            )) {
                throw new Exception("Digital signature verification failed");
            }

            // Update integrity status to verified
            $this->updateIntegrityStatus($file_id, 'verified');

            return $decrypted;
        } catch (Exception $e) {
            throw new Exception("Decryption failed: " . $e->getMessage());
        }
    }

    private function updateIntegrityStatus($file_id, $status) {
        $stmt = $this->pdo->prepare("
            UPDATE encrypted_files 
            SET integrity_status = ?, 
                last_verified = CURRENT_TIMESTAMP 
            WHERE file_id = ?
        ");
        $stmt->execute([$status, $file_id]);
    }

    public function verifyFile($file_id) {
        try {
            // Get file and encryption information
            $stmt = $this->pdo->prepare("
                SELECT f.*, e.* 
                FROM user_files f 
                JOIN encrypted_files e ON f.id = e.file_id 
                WHERE f.id = ?
            ");
            $stmt->execute([$file_id]);
            $file_info = $stmt->fetch();

            if (!$file_info) {
                throw new Exception("File not found or access denied");
            }

            $encrypted_path = __DIR__ . '/../uploads/' . $file_info['filename'] . '.enc';
            if (!file_exists($encrypted_path)) {
                throw new Exception("Encrypted file not found");
            }

            // Read encrypted content
            $encrypted_content = file_get_contents($encrypted_path);
            if ($encrypted_content === false) {
                throw new Exception("Failed to read encrypted file");
            }

            // Verify HMAC
            $calculated_hmac = $this->calculateHMAC($encrypted_content);
            $hmac_verified = hash_equals($file_info['hmac'], $calculated_hmac);

            // Update verification status
            $status = 'verified';
            if (!$hmac_verified) {
                $status = 'hmac_failed';
            }

            $this->updateIntegrityStatus($file_id, $status);

            return [
                'verified' => $hmac_verified,
                'status' => $status,
                'last_verified' => date('Y-m-d H:i:s')
            ];
        } catch (Exception $e) {
            throw new Exception("Verification failed: " . $e->getMessage());
        }
    }

    private function getFileId($file_path) {
        $filename = basename($file_path);
        $stmt = $this->pdo->prepare("SELECT id FROM user_files WHERE filename = ?");
        $stmt->execute([$filename]);
        $result = $stmt->fetch();
        
        if (!$result) {
            throw new Exception('File not found in database');
        }
        
        return $result['id'];
    }

    /**
     * Saves encrypted data to a file and calculates its HMAC.
     *
     * @param string $encryptedData The data to save and calculate HMAC for.
     * @param string $filePath The path to save the encrypted data.
     * @return string The calculated HMAC.
     * @throws Exception If saving the file fails.
     */
    public function saveEncryptedFileAndCalculateHmac($encryptedData, $filePath) {
        if (file_put_contents($filePath, $encryptedData) === false) {
            throw new Exception("Failed to save encrypted file to " . $filePath);
        }
        // Calculate HMAC of the data that was just saved
        return $this->calculateHMAC(file_get_contents($filePath));
    }
}
?> 