<?php
$domain = 'dev-m7ame-h.us.auth0.com';
$client_id = 'QL895n8HCs4BfMj9ri9MOl5FQ9NjYtnt';
$redirect_uri = 'http://localhost/auth_system0/oauth_callback.php';

$params = [
    'client_id' => $client_id,
    'response_type' => 'code',
    'scope' => 'openid profile email',
    'redirect_uri' => $redirect_uri,
];

// Add connection parameter if specified
if (isset($_GET['connection'])) {
    $params['connection'] = $_GET['connection'];
}

$auth_url = "https://$domain/authorize?" . http_build_query($params);
header("Location: $auth_url");
exit;
?>