<?php
/**
 * OeeWaterfallController.php
 *
 * Proxy-fil: all logik finns i classes/OeeWaterfallController.php
 * Denna fil existerar i controllers/ enligt konventionen men delegerar till classes/.
 *
 * Ruttning sker via api.php -> classes/ (autoloader).
 * Se classes/OeeWaterfallController.php for full implementation med endpoints:
 *   - run=waterfall-data  -> Vattenfall-segment med timmar + procent
 *   - run=summary         -> OEE-faktorer + trend
 */

// Delegera till huvudcontrollern i classes/
require_once __DIR__ . '/../classes/OeeWaterfallController.php';
