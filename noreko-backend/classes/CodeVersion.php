<?php

// ============================================================================
// CodeVersion — cache-busting-token för deployad kodversion.
// ----------------------------------------------------------------------------
// Läser noreko-backend/CODE_VERSION (skrivs av deploy-skripten, t.ex.
// `git rev-parse --short HEAD`) och används som DEL AV cache-nycklar. När ny
// kod deployas ändras versionen → gamla cache-filer blir OÅTKOMLIGA direkt,
// utan att hela cachen måste rensas. Utan detta serveras gamla (felaktiga)
// värden upp till 7 dygn (SWR-TTL för avslutade perioder) och backend-fixar
// exekverar aldrig.
//
// Fallback när CODE_VERSION saknas: filens mtime (ändras vid deploy/redigering).
// Memoiseras per process (läser filen en gång).
// ============================================================================

class CodeVersion
{
    private static ?string $version = null;

    /** Deployad kodversion (kort git-sha eller timestamp). Filnamnssäker. Memoiserad. */
    public static function get(): string
    {
        if (self::$version !== null) {
            return self::$version;
        }
        $file = dirname(__DIR__) . '/CODE_VERSION';
        $v = '';
        if (is_file($file)) {
            $v = trim((string)@file_get_contents($file));
        }
        if ($v === '') {
            $v = (string)@filemtime(__FILE__);
        }
        // Håll nyckeln filnamnssäker (git-sha/timestamp påverkas inte).
        $v = preg_replace('/[^A-Za-z0-9_.-]/', '', $v);
        if ($v === '' || $v === null) {
            $v = '0';
        }
        self::$version = $v;
        return self::$version;
    }
}
