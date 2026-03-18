<?php
// admin.php - LEGACY STUB, all admin traffic goes through api.php?action=admin
// This file is kept to prevent 404 but does NOT expose any admin functionality.
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('Pragma: no-cache');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
$isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
           (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
if ($isHttps) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
header_remove('X-Powered-By');
http_response_code(410);
echo json_encode([
    'success' => false,
    'error' => 'Denna endpoint ar borttagen. Anvand api.php?action=admin istallet.'
], JSON_UNESCAPED_UNICODE);
