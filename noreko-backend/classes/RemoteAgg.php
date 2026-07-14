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
            'tvattlinje'          => ['statistics', 'oee-trend', 'status'],
            'tvattlinje-operator' => ['ranking'],
            'operator-ranking'    => ['*'],
            'bemanning'           => ['operator-stats', 'team-kombinationer'],
            'oee-trendanalys'     => ['*'],
            'lineskiftrapport'    => ['*'],
            'rebotling'           => ['status', 'rast', 'driftstopp', 'oee'],
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
        if ($base === '' || $token === '' || !function_exists('curl_init')) {
            return false;
        }

        // === FAS 2 steg 2: stale-while-revalidate VPS-cache ===
        // VPS = cachande spegel. Servera cache direkt (ingen länk-trafik); vid stale
        // refreshar bara EN request (flock NB) medan övriga servar stale; vid länkfel
        // servera stale (fryskillern — aldrig hänga/rå-SQL). Historisk period = 7 dygn.
        $cacheDir = dirname(__DIR__) . '/cache';
        if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0777, true); }
        // Versionerad nyckel: gammal cache blir oåtkomlig direkt vid deploy (annars serveras
        // gamla värden upp till 7 dygn och backend-fixar exekverar aldrig).
        $cf  = $cacheDir . '/swr_' . $action . '_' . CodeVersion::get() . '_' . md5(json_encode($_GET)) . '.json';
        $ttl = self::swrTtl($run, $_GET);

        // 1. Färsk VPS-cache → servera direkt, INGEN länk-trafik.
        if (is_file($cf) && (time() - filemtime($cf)) < $ttl) {
            return self::serveCache($cf, false);
        }

        // 2. Stale/miss → bara EN refreshar (flock NB); övriga servar stale direkt.
        $lock = @fopen($cf . '.lock', 'c');
        $haveLock = $lock && flock($lock, LOCK_EX | LOCK_NB);
        if (!$haveLock && is_file($cf)) {
            if ($lock) { fclose($lock); }
            return self::serveCache($cf, true);
        }

        // 3. Hämta färskt från Pi.
        $ch = curl_init($base . '?' . http_build_query($_GET));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER    => true,
            CURLOPT_CONNECTTIMEOUT_MS => 800,
            CURLOPT_TIMEOUT_MS        => 6000,
            CURLOPT_HTTPHEADER        => ['X-Internal-Token: ' . $token],
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $GLOBALS['__piProxyMs'] = curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000;
        $GLOBALS['__piTtfbMs']  = curl_getinfo($ch, CURLINFO_STARTTRANSFER_TIME) * 1000;
        curl_close($ch);

        $ok = ($body !== false && $code === 200 && isset($body[0]) && $body[0] === '{');
        if ($ok) {
            // Atomisk skrivning (temp+rename) → inga trasiga läsningar av stora svar.
            $tmp = $cf . '.tmp.' . getmypid();
            if (@file_put_contents($tmp, $body) !== false) { @rename($tmp, $cf); } else { @unlink($tmp); }
        }
        if ($lock) { if ($haveLock) { flock($lock, LOCK_UN); } fclose($lock); }

        if ($ok) {
            header('Content-Type: application/json; charset=utf-8');
            header('X-Data-Ts: ' . gmdate('c'));
            $GLOBALS['__dataSource'] = 'pi';
            echo $body;
            return true;
        }
        // 4. Pi misslyckades → servera stale om den finns (fryskillern).
        if (is_file($cf)) {
            return self::serveCache($cf, true);
        }
        // 5. Ingen cache + Pi nere → lokal fallback.
        return false;
    }

    /** Serverar en VPS-cache-fil; $stale => märk svaret inaktuellt (X-Data-Stale). */
    private static function serveCache(string $cf, bool $stale): bool
    {
        $body = @file_get_contents($cf);
        if ($body === false || $body === '') {
            return false;
        }
        header('Content-Type: application/json; charset=utf-8');
        header('X-Data-Ts: ' . gmdate('c', @filemtime($cf) ?: time()));
        if ($stale) { header('X-Data-Stale: 1'); }
        $GLOBALS['__dataSource'] = $stale ? 'stale' : 'cache';
        echo $body;
        return true;
    }

    /** SWR-TTL: avslutad period (immutabel) = 7 dygn, live-status = 5s, annars 5 min. */
    private static function swrTtl(string $run, array $get): int
    {
        $end = $get['end'] ?? null;
        if (is_string($end) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) && $end < date('Y-m-d')) {
            return 604800; // avslutad period ändras aldrig
        }
        if (in_array($run, ['status', 'rast', 'driftstopp', 'oee'], true)) {
            return 5; // live-status
        }
        return 300; // statistik/rapporter, löpande period
    }
}
