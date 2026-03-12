<?php
/**
 * StopporsakOperatorController.php
 *
 * Proxy-fil: all logik finns i classes/StopporsakOperatorController.php
 * Denna fil existerar i controllers/ enligt konventionen men delegerar till classes/.
 *
 * Ruttning sker via api.php -> classes/ (autoloader).
 * Se classes/StopporsakOperatorController.php for full implementation med endpoints:
 *   - run=overview          -> Alla operatörer med stopptid, antal stopp, teamsnitt, "hög"-flagga
 *   - run=operator-detail   -> En operatörs stopporsaker i detalj
 *   - run=reasons-summary   -> Aggregerade stopporsaker för pie/donut-chart
 */

// Delegera till huvudcontrollern i classes/
require_once __DIR__ . '/../classes/StopporsakOperatorController.php';
