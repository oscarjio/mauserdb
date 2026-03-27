<?php
/**
 * ErrorLogger — centraliserad felloggning till fil.
 *
 * Loggar till noreko-backend/logs/error.log med timestamp, klass, meddelande
 * och stack trace. Filen kräver inte root — Apache/PHP-användaren skapar den.
 *
 * Användning:
 *   ErrorLogger::log($exception);
 *   ErrorLogger::log($exception, 'Extra kontext');
 *   ErrorLogger::logMessage('Varning: något hände', 'WARNING');
 */
class ErrorLogger
{
    private static ?string $logFile = null;

    /**
     * Hämta sökväg till loggfilen.
     */
    private static function getLogFile(): string
    {
        if (self::$logFile === null) {
            self::$logFile = __DIR__ . '/../logs/error.log';
        }
        return self::$logFile;
    }

    /**
     * Logga ett undantag (Throwable) med timestamp och stack trace.
     */
    public static function log(\Throwable $e, string $context = ''): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $class     = get_class($e);
        $message   = $e->getMessage();
        $file      = $e->getFile();
        $line      = $e->getLine();
        $trace     = $e->getTraceAsString();

        $entry = "[{$timestamp}] {$class}: {$message}" . PHP_EOL
               . "  File: {$file}:{$line}" . PHP_EOL;

        if ($context !== '') {
            $entry .= "  Context: {$context}" . PHP_EOL;
        }

        $entry .= "  Stack trace:" . PHP_EOL
                . "  " . str_replace("\n", "\n  ", $trace) . PHP_EOL
                . str_repeat('-', 80) . PHP_EOL;

        self::writeToFile($entry);

        // Skriv också till PHP:s standardlogg för kompatibilitet
        error_log("[{$timestamp}] {$class}: {$message} in {$file}:{$line}");
    }

    /**
     * Logga ett enkelt meddelande (inte ett undantag).
     */
    public static function logMessage(string $message, string $level = 'ERROR'): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $entry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL
               . str_repeat('-', 80) . PHP_EOL;

        self::writeToFile($entry);
        error_log("[{$timestamp}] [{$level}] {$message}");
    }

    /**
     * Skriv till loggfilen. Skapar filen om den inte finns.
     */
    private static function writeToFile(string $entry): void
    {
        $logFile = self::getLogFile();

        // Skapa katalogen om den saknas
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }
}
