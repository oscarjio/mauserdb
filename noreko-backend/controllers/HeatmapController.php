<?php
/**
 * HeatmapController.php
 *
 * Proxy-fil: all logik finns i classes/HeatmapController.php
 * Denna fil existerar i controllers/ enligt konventionen men delegerar till classes/.
 *
 * Ruttning sker via api.php -> classes/ (autoloader).
 * Se classes/HeatmapController.php for full implementation med endpoints:
 *   - run=heatmap-data → Matrisdata {date, hour, count} + skalvarden
 *   - run=summary      → Totalt IBC, basta timme, samsta timme, basta veckodag
 */

// Delegera till huvudcontrollern i classes/
require_once __DIR__ . '/../classes/HeatmapController.php';
