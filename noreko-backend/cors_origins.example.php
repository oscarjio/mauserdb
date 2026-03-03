<?php
// cors_origins.php — server-specifik CORS-konfiguration
// Kopiera den här filen till cors_origins.php på servern och lägg till dina domäner.
// OBS: cors_origins.php är INTE i git (se .gitignore).
//
// Notera: subdomäner av serverns egna domän tillåts automatiskt av api.php,
// så den här filen behövs bara om frontenden körs på en HELT annan domän.

return [
    // 'https://mauserdb.com',
    // 'https://dev.mauserdb.com',
    // 'https://app.annanserver.com',
];
