<?php
/**
 * KassationsDrilldownController.php
 *
 * Proxy-fil: all logik finns i classes/KassationsDrilldownController.php
 * Denna fil existerar i controllers/ enligt konventionen men delegerar till classes/.
 *
 * Ruttning sker via api.php -> classes/ (autoloader).
 * Se classes/KassationsDrilldownController.php for full implementation med endpoints:
 *   - run=overview        -> totalt kasserade, kassationsgrad, trend, per-orsak-aggregering
 *   - run=reason-detail   -> enskilda kassationshändelser för en viss orsak
 *   - run=trend           -> daglig kassationstrend
 */

// Delegera till huvudcontrollern i classes/
require_once __DIR__ . '/../classes/KassationsDrilldownController.php';
