<?php
/**
 * ParetoController.php
 *
 * Proxy-fil: all logik finns i classes/ParetoController.php
 * Denna fil existerar i controllers/ enligt konventionen men delegerar till classes/.
 *
 * Ruttning sker via api.php -> classes/ (autoloader).
 * Se classes/ParetoController.php for full implementation med endpoints:
 *   - run=pareto-data  -> Pareto-sorterad lista med kumulativ % och 80%-markering
 *   - run=summary      -> KPI-sammanfattning
 */

// Delegera till huvudcontrollern i classes/
require_once __DIR__ . '/../classes/ParetoController.php';
