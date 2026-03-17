<?php
// login.php - LEGACY STUB, all login traffic goes through api.php?action=login
// This file is kept to prevent 404 but does NOT process logins.
header('Content-Type: application/json; charset=utf-8');
http_response_code(410);
echo json_encode([
    'success' => false,
    'error' => 'Denna endpoint ar borttagen. Anvand api.php?action=login istallet.'
]);
