<?php
// admin.php - LEGACY STUB, all admin traffic goes through api.php?action=admin
// This file is kept to prevent 404 but does NOT expose any admin functionality.

// CORS-headers — samma logik som api.php för att preflight-requests inte ska misslyckas
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = [
    'http://localhost:4200',
    'http://localhost',
    'https://localhost',
];
$corsConfig = __DIR__ . '/cors_origins.php';
if (file_exists($corsConfig)) {
    $extraOrigins = require $corsConfig;
    if (is_array($extraOrigins)) {
        $allowedOrigins = array_merge($allowedOrigins, $extraOrigins);
    }
}
if ($origin) {
    $serverHost = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? '';
    $hostParts = explode('.', $serverHost);
    if (count($hostParts) >= 2) {
        $registeredDomain = implode('.', array_slice($hostParts, -2));
        $pattern = '#^https?://([\w-]+\.)?' . preg_quote($registeredDomain, '#') . '(:\d+)?$#';
        if (preg_match($pattern, $origin)) {
            $allowedOrigins[] = $origin;
        }
    }
}
$originAllowed = $origin && in_array($origin, $allowedOrigins, true);
if ($originAllowed) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
}

// Hantera preflight OPTIONS-request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

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
