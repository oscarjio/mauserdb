<?php

// ============================================================================
// agg_config.example.php — mall för Pi-aggregering (FAS 1)
// ----------------------------------------------------------------------------
// Kopiera till agg_config.php (git-ignorerad, deploy-exkluderad) per server.
//
//   'remote' => bool   Slå PÅ proxy till internal-api (VPS-edge). false = av (lokal kod).
//   'token'  => string Delad hemlighet, MÅSTE matcha Pi:ns internal_token.php.
//   'base'   => string URL till internal-api.php på Pi/loopback.
//
// I git ligger detta med remote=false. Den skarpa dev-filen (agg_config.php)
// sätter remote=true och ligger ALDRIG i git.
// ============================================================================

return [
    'remote' => false,
    'token'  => 'CHANGE_ME',
    'base'   => 'http://127.0.0.1:8091/internal-api.php',
];
