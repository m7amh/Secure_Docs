<?php
// Force HTTPS
if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit();
}

// Set security headers
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload"); // HSTS
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header( "X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self' https: 'unsafe-inline' 'unsafe-eval'; img-src 'self' https: data:; font-src 'self' https: data:;");

// Set secure cookie parameters
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Strict');

// Set secure session parameters
ini_set('session.use_only_cookies', 1);
ini_set('session.use_strict_mode', 1);

// Set secure PHP settings
ini_set('expose_php', 'Off');
ini_set('session.cookie_lifetime', 0);
ini_set('session.gc_maxlifetime', 3600); 