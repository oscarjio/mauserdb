<?php
// admin.php - LEGACY STUB, all admin traffic goes through api.php?action=admin
// This file is kept to prevent 404 but does NOT expose any admin functionality.
header('Content-Type: application/json; charset=utf-8');
http_response_code(410);
echo json_encode([
    'success' => false,
    'message' => 'Denna endpoint ar borttagen. Anvand api.php?action=admin istallet.'
]);
