<?php
/**
 * SkiftoverlamningController.php
 *
 * Proxy-fil: all logik finns i classes/SkiftoverlamningController.php
 * Denna fil existerar i controllers/ enligt konventionen men delegerar till classes/.
 *
 * Ruttning sker via api.php -> classes/ (autoloader).
 * Se classes/SkiftoverlamningController.php for full implementation med endpoints:
 *   - run=list           -> Lista överlämningar (filtrerad)
 *   - run=detail         -> Fullständig vy av en överlämning
 *   - run=shift-kpis     -> Auto-hämta KPI:er för senaste skift
 *   - run=summary        -> Sammanfattnings-KPI:er (senaste, antal vecka, snitt, pågående)
 *   - run=operators      -> Lista operatörer (filter-dropdown)
 *   - run=create (POST)  -> Skapa ny överlämning
 */

// Delegera till huvudcontrollern i classes/
require_once __DIR__ . '/../classes/SkiftoverlamningController.php';
