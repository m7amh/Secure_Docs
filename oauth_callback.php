<?php
session_start();
require 'db.php';

$client_id = '';
$client_secret = '';
$redirect_uri = 'http://localhost/auth_system0/oauth_callback.php';
$domain = '';

if (!isset($_GET['code'])) {
    die("Authorization code not found.");
}

$code = $_GET['code'];

// Step 1: Get access token from Auth0
$token_url = "https://$domain/oauth/token";
$token_data = [
    'grant_type' => 'authorization_code',
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'code' => $code,
    'redirect_uri' => $redirect_uri,
];

$options = [
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json",
        'content' => json_encode($token_data),
    ]
];

$response = file_get_contents($token_url, false, stream_context_create($options));
if (!$response) {
    die("Failed to get access token from Auth0.");
}

$token_info = json_decode($response, true);
$access_token = $token_info['access_token'];
$id_token = $token_info['id_token'];

// Step 2: Get user info from Auth0
$userinfo_url = "https://$domain/userinfo";
$opts = [
    "http" => [
        "header" => "Authorization: Bearer " . $access_token
    ]
];
$userinfo = json_decode(file_get_contents($userinfo_url, false, stream_context_create($opts)), true);
if (!$userinfo) {
    die("Failed to fetch user info.");
}

// Extract basic info
$auth0_id = $userinfo['sub'];
$username_from_auth0 = $userinfo['nickname'] ?? $userinfo['name'] ?? 'User';
$email_from_auth0 = $userinfo['email'] ?? '';
$avatar = $userinfo['picture'] ?? null;

// Step 3: Extra data
$facebook_data = $userinfo['https://graph.facebook.com'] ?? null;
$github_data = $userinfo['https://api.github.com'] ?? null;

$facebook_permissions = '';
$github_permissions = '';
if (isset($token_info['scope'])) {
    $scopes = explode(' ', $token_info['scope']);
    $facebook_scopes = [];
    $github_scopes = [];

    foreach ($scopes as $scope) {
        if (
            strpos($scope, 'facebook') !== false ||
            in_array($scope, ['email', 'user_birthday', 'user_gender'])
        ) {
            $facebook_scopes[] = $scope;
        }
        if (
            strpos($scope, 'repo') !== false || strpos($scope, 'user') !== false
        ) {
            $github_scopes[] = $scope;
        }
    }

    $facebook_permissions = implode(',', array_unique($facebook_scopes));
    $github_permissions = implode(',', array_unique($github_scopes));
}

$extra_profile_data = json_encode([
    'facebook_data' => $facebook_data,
    'github_data' => $github_data,
    'raw_userinfo' => $userinfo,
]);

// Step 4: Check if user exists
$stmt = $pdo->prepare("SELECT * FROM users WHERE auth0_id = ?");
$stmt->execute([$auth0_id]);
$user = $stmt->fetch();

if (!$user) {
    // New user
    $is_admin = ($email_from_auth0 === 'admin@example.com') ? 1 : 0;

    $stmt = $pdo->prepare("INSERT INTO users (username, email, avatar_url, auth_method, auth0_id, is_admin, facebook_permissions, github_permissions, extra_profile_data)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->execute([
        $username_from_auth0,
        $email_from_auth0,
        $avatar,
        'auth0',
        $auth0_id,
        $is_admin,
        $facebook_permissions,
        $github_permissions,
        $extra_profile_data
    ]);

    $user_id = $pdo->lastInsertId();

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

} else {
    // ✅ Update only the fields we want (without overwriting manually edited username/email)
    $final_username = (!empty($user['username'])) ? $user['username'] : $username_from_auth0;
    $final_email = (!empty($user['email'])) ? $user['email'] : $email_from_auth0;

    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, avatar_url = ?, facebook_permissions = ?, github_permissions = ?, extra_profile_data = ? WHERE id = ?");
    $stmt->execute([
        $final_username,
        $final_email,
        $avatar,
        $facebook_permissions,
        $github_permissions,
        $extra_profile_data,
        $user['id']
    ]);

    // Ensure session uses updated info
    $user['username'] = $final_username;
    $user['email'] = $final_email;
    $user['avatar_url'] = $avatar;
}

// Step 5: Set session
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['email'] = $user['email'];
$_SESSION['avatar_url'] = $user['avatar_url'] ?? null;
$_SESSION['is_admin'] = $user['is_admin'] ?? 0;

// Redirect to homepage
header("Location: index.php");
exit;
