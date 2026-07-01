<?php

// ============================================================================
// RemoteAgg — FAS 1 Pi-aggregering (KLIENT-SIDA, VPS-edge)
// ----------------------------------------------------------------------------
// Proxar tunga read-actions från VPS-edge till den interna API:n (internal-api.php)
// på Pi:n, så VPS-backenden fetchar kompakt JSON i stället för att dra rå SQL
// över den strypta 33061-tunneln. Aktiveras via agg_config.php ['remote'].
// Lokala controllern ligger kvar som fallback: om proxyn misslyckas (nere,
// timeout, icke-200, icke-JSON) returnerar passthru() false och controllern
// kör sin lokala kod som vanligt.
// ============================================================================

class RemoteAgg
{
    /** @var array<string,mixed>|null */
    private static $config = null;

    /** Läser agg_config.php en gång per process. */
    private static function config(): array
    {
        if (self::$config === null) {
            $f = dirname(__DIR__) . '/agg_config.php';
            $cfg = is_file($f) ? (require $f) : [];
            self::$config = is_array($cfg) ? $cfg : [];
        }
        return self::$config;
    }

    /**
     * Är remote-proxy aktiverad? Alltid AV när vi själva kör inuti internal-api
     * (PIAGG_INTERNAL) — annars skulle Pi:n proxa vidare till sig själv (loop).
     */
    public static function enabled(): bool
    {
        if (defined('PIAGG_INTERNAL')) {
            return false;
        }
        $c = self::config();
        return !empty($c['remote']);
    }

    /**
     * HEAVY-allowlist per (action, run). '*' = alla runs för den actionen.
     * OBS: bemanning-teamvyn har run-värdet 'team-kombinationer' (inte 'team').
     */
    private static function isHeavy(string $action, string $run): bool
    {
        static $heavy = [
            'tvattlinje'          => ['statistics', 'oee-trend'],
            'tvattlinje-operator' => ['ranking'],
            'operator-ranking'    => ['*'],
            'bemanning'           => ['operator-stats', 'team-kombinationer'],
            'oee-trendanalys'     => ['*'],
            'lineskiftrapport'    => ['*'],
        ];
        if (!isset($heavy[$action])) {
            return false;
        }
        $allowed = $heavy[$action];
        if (in_array('*', $allowed, true)) {
            return true;
        }
        return in_array($run, $allowed, true);
    }

    /**
     * Försök proxa den aktuella requesten till internal-api.
     * Returnerar true om svaret skickades (då ska controllern `return`),
     * annars false (då ska controllern köra sin lokala kod som fallback).
     */
    public static function passthru(string $expectedAction): bool
    {
        if (!self::enabled()) {
            return false;
        }
        $action = strtolower(trim($_GET['action'] ?? ''));
        $run    = trim($_GET['run'] ?? '');
        if ($action !== $expectedAction) {
            return false;
        }
        if (!self::isHeavy($action, $run)) {
            return false;
        }

        $c     = self::config();
        $base  = isset($c['base'])  ? (string)$c['base']  : '';
        $token = isset($c['token']) ? (string)$c['token'] : '';
        if ($base === '' || $token === '') {
            return false;
        }

        if (!function_exists('curl_init')) {
            return false;
        }

        $url = $base . '?' . http_build_query($_GET);
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER    => true,
            CURLOPT_TIMEOUT_MS        => 1500,
            CURLOPT_CONNECTTIMEOUT_MS => 800,
            CURLOPT_HTTPHEADER        => ['X-Internal-Token: ' . $token],
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Acceptera bara ett giltigt JSON-svar med HTTP 200 — annars fallback.
        if ($body === false || $code !== 200) {
            return false;
        }
        if (!isset($body[0]) || $body[0] !== '{') {
            return false;
        }

        header('Content-Type: application/json; charset=utf-8');
        echo $body;
        return true;
    }
}
