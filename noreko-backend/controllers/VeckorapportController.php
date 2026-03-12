<?php
/**
 * VeckorapportController.php
 *
 * Proxy-fil: all logik finns i classes/VeckorapportController.php
 * Denna fil existerar i controllers/ enligt konventionen men delegerar till classes/.
 *
 * Ruttning sker via api.php -> classes/ (autoloader).
 * Se classes/VeckorapportController.php for full implementation med endpoints:
 *   - run=report -> Komplett veckorapport med KPI:er
 */

// Delegera till huvudcontrollern i classes/
require_once __DIR__ . '/../classes/VeckorapportController.php';
