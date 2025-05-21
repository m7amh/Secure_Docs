<?php
require_once __DIR__ . '/../vendor/autoload.php';

class TwoFactorAuth {
    private $pdo;
    private $ga;
    private $issuer = 'Auth System';

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->ga = new PHPGangsta_GoogleAuthenticator();
    }

    public function generateSecret($user_id, $username) {
        // Generate a new secret key
        $secret = $this->ga->createSecret();
        
        // Store the secret in database
        $stmt = $this->pdo->prepare("
            INSERT INTO two_factor_auth (user_id, secret_key, is_enabled) 
            VALUES (?, ?, 0)
            ON DUPLICATE KEY UPDATE secret_key = ?
        ");
        $stmt->execute([$user_id, $secret, $secret]);

        // Generate QR code URL
        $qrCodeUrl = $this->ga->getQRCodeGoogleUrl($username, $secret, $this->issuer);
        
        return [
            'secret' => $secret,
            'qrCodeUrl' => $qrCodeUrl
        ];
    }

public function verifyCode($user_id, $code) {
    // Get user's secret key regardless of 2FA status
    $stmt = $this->pdo->prepare("
        SELECT secret_key
        FROM two_factor_auth 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();

    if (!$result) {
        return false;
    }

    $secret = $result['secret_key'];
    $isValid = $this->ga->verifyCode($secret, $code, 2);

    if ($isValid) {
        $stmt = $this->pdo->prepare("
            UPDATE two_factor_auth 
            SET last_used = CURRENT_TIMESTAMP 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
    }

    return $isValid;
}


    public function enable2FA($user_id) {
        $stmt = $this->pdo->prepare("
            UPDATE two_factor_auth 
            SET is_enabled = 1 
            WHERE user_id = ?
        ");
        return $stmt->execute([$user_id]);
    }

    public function disable2FA($user_id) {
        $stmt = $this->pdo->prepare("
            UPDATE two_factor_auth 
            SET is_enabled = 0 
            WHERE user_id = ?
        ");
        return $stmt->execute([$user_id]);
    }

    public function is2FAEnabled($user_id) {
        $stmt = $this->pdo->prepare("
            SELECT is_enabled 
            FROM two_factor_auth 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        return $result && $result['is_enabled'];
    }

    public function getSecret($user_id) {
        $stmt = $this->pdo->prepare("
            SELECT secret_key 
            FROM two_factor_auth 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        return $result ? $result['secret_key'] : null;
    }
}
?> 