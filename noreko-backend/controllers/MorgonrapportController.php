<?php
/**
 * MorgonrapportController.php
 *
 * Proxy-fil: all logik finns i classes/MorgonrapportController.php
 * Denna fil existerar i controllers/ enligt konventionen men delegerar till classes/.
 *
 * Ruttning sker via api.php -> classes/ (autoloader).
 * Se classes/MorgonrapportController.php for full implementation med endpoints:
 *   - run=rapport  -> Fullstandig morgonrapport for ett datum
 */

// Delegera till huvudcontrollern i classes/
require_once __DIR__ . '/../classes/MorgonrapportController.php';
