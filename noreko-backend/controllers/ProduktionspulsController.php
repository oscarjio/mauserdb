<?php
/**
 * ProduktionspulsController.php
 *
 * Proxy-fil: all logik finns i classes/ProduktionspulsController.php
 * Denna fil existerar i controllers/ enligt konventionen men delegerar till classes/.
 *
 * Ruttning sker via api.php -> classes/ (autoloader).
 * Se classes/ProduktionspulsController.php for full implementation med endpoints:
 *   - run=latest          -> Senaste IBC:er (bakatkompat)
 *   - run=hourly-stats    -> Timstatistik + trend (bakatkompat)
 *   - run=pulse           -> Kronologisk handelsefeed (IBC + stopp + on/off)
 *   - run=live-kpi        -> Realtids-KPI:er: IBC idag, IBC/h, driftstatus, tid sedan senaste stopp
 */

// Delegera till huvudcontrollern i classes/
require_once __DIR__ . '/../classes/ProduktionspulsController.php';
