<?php
/**
 * RateLimiter — filbaserad sliding window rate limiting per IP-adress.
 *
 * Implementerar ett sliding window (glidande fönster) för att begränsa
 * antalet requests per IP-adress. Ingen Redis-dependency — allt lagras
 * i PHP-sessioners delade minne via APCu eller filer i /tmp.
 *
 * Konfiguration:
 *   - Standard: 120 requests per 60 sekunder per IP
 *   - Autentiserade endpoints (login/register): strängare (se AuthHelper)
 *
 * Returnerar HTTP 429 Too Many Requests med Retry-After header om gränsen nås.
 */
class RateLimiter
{
    /** Max requests per tidsfönster */
    private const MAX_REQUESTS = 600;

    /** Tidsfönster i sekunder */
    private const WINDOW_SECONDS = 60;

    /** Katalog för rate limit-filer */
    private static string $cacheDir = '';

    /**
     * Returnera katalog för rate limit-filer (skapas om den saknas).
     */
    private static function getCacheDir(): string
    {
        if (self::$cacheDir === '') {
            self::$cacheDir = sys_get_temp_dir() . '/noreko_rl';
        }
        return self::$cacheDir;
    }

    /**
     * Returnera filsökväg för en given IP.
     * IP hashas för att undvika problematiska tecken i filnamn.
     */
    private static function getCacheFile(string $ip): string
    {
        return self::getCacheDir() . '/' . md5($ip) . '.rl';
    }

    /**
     * Kontrollera om IP är rate limited.
     * Om inte: registrera anropet och returnera true (OK att fortsätta).
     * Om ja: skicka HTTP 429 och avsluta.
     *
     * @param string $ip IP-adress att begränsa
     * @return void — avslutar scriptet med 429 om begränsad
     */
    public static function check(string $ip): void
    {
        // Undvik rate limiting för loopback/localhost
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return;
        }

        $cacheDir = self::getCacheDir();
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0700, true);
        }

        $file = self::getCacheFile($ip);
        $now  = time();
        $windowStart = $now - self::WINDOW_SECONDS;

        // Läs befintliga timestamps med fillock
        $fp = @fopen($file, 'c+');
        if ($fp === false) {
            // Om vi inte kan skapa filen — låt requestet passera (fail open)
            return;
        }

        flock($fp, LOCK_EX);

        $content = stream_get_contents($fp);
        $timestamps = [];
        if ($content !== '' && $content !== false) {
            $timestamps = array_map('intval', explode(',', trim($content)));
        }

        // Filtrera bort timestamps utanför fönstret (sliding window)
        $timestamps = array_filter($timestamps, fn(int $ts) => $ts > $windowStart);

        $count = count($timestamps);

        if ($count >= self::MAX_REQUESTS) {
            // Räkna ut när äldsta request lämnar fönstret
            $oldest = min($timestamps);
            $retryAfter = max(1, ($oldest + self::WINDOW_SECONDS) - $now);

            flock($fp, LOCK_UN);
            fclose($fp);

            http_response_code(429);
            header('Retry-After: ' . $retryAfter);
            header('X-RateLimit-Limit: ' . self::MAX_REQUESTS);
            header('X-RateLimit-Remaining: 0');
            header('X-RateLimit-Reset: ' . ($oldest + self::WINDOW_SECONDS));
            echo json_encode([
                'success'     => false,
                'error'       => 'För många förfrågningar. Försök igen om ' . $retryAfter . ' sekunder.',
                'retry_after' => $retryAfter,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Lägg till aktuell timestamp
        $timestamps[] = $now;

        // Skriv tillbaka
        fseek($fp, 0);
        ftruncate($fp, 0);
        fwrite($fp, implode(',', $timestamps));
        flock($fp, LOCK_UN);
        fclose($fp);

        // Skicka informationsheaders
        $remaining = self::MAX_REQUESTS - count($timestamps);
        header('X-RateLimit-Limit: ' . self::MAX_REQUESTS);
        header('X-RateLimit-Remaining: ' . max(0, $remaining));
    }

    /**
     * Rensa gamla cache-filer (anropas sporadiskt för att hålla /tmp rent).
     * Radering av filer äldre än 2 * WINDOW_SECONDS.
     */
    public static function cleanup(): void
    {
        $cacheDir = self::getCacheDir();
        if (!is_dir($cacheDir)) {
            return;
        }

        $cutoff = time() - (self::WINDOW_SECONDS * 2);
        foreach (glob($cacheDir . '/*.rl') ?: [] as $file) {
            if (filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }
}
