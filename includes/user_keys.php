<?php
class UserKeys {
    private $pdo;
    private $keys_dir;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->keys_dir = 'keys/';
        if (!file_exists($this->keys_dir)) {
            mkdir($this->keys_dir, 0700, true);
        }
    }

    public function generateKeyPair($userId) {
        // Generate new key pair
        $config = array(
            "private_key_bits" => 2048,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        );
        
        $res = openssl_pkey_new($config);
        openssl_pkey_export($res, $privateKey);
        $publicKey = openssl_pkey_get_details($res)['key'];

        // Save keys
        $privateKeyPath = $this->keys_dir . $userId . '_private.pem';
        $publicKeyPath = $this->keys_dir . $userId . '_public.pem';
        
        file_put_contents($privateKeyPath, $privateKey);
        file_put_contents($publicKeyPath, $publicKey);
        
        // Set proper permissions
        chmod($privateKeyPath, 0600);
        chmod($publicKeyPath, 0644);

        return [
            'private_key' => $privateKey,
            'public_key' => $publicKey
        ];
    }

    public function getPrivateKey($userId) {
        $privateKeyPath = $this->keys_dir . $userId . '_private.pem';
        if (file_exists($privateKeyPath)) {
            return file_get_contents($privateKeyPath);
        }
        return null;
    }

    public function getPublicKey($userId) {
        $publicKeyPath = $this->keys_dir . $userId . '_public.pem';
        if (file_exists($publicKeyPath)) {
            return file_get_contents($publicKeyPath);
        }
        return null;
    }

    public function ensureUserKeys($userId) {
        if (!$this->getPrivateKey($userId)) {
            return $this->generateKeyPair($userId);
        }
        return [
            'private_key' => $this->getPrivateKey($userId),
            'public_key' => $this->getPublicKey($userId)
        ];
    }
} 