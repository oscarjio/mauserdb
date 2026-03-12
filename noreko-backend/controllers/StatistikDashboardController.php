<?php
/**
 * StatistikDashboardController.php
 *
 * Proxy-fil: all logik finns i classes/StatistikDashboardController.php
 * Denna fil existerar i controllers/ enligt konventionen men delegerar till classes/.
 *
 * Ruttning sker via api.php -> classes/ (autoloader).
 * Se classes/StatistikDashboardController.php for full implementation med endpoints:
 *   - run=summary           -> alla KPI:er samlade
 *   - run=production-trend  -> daglig produktionsdata senaste N dagar
 *   - run=daily-table       -> senaste 7 dagars detaljerad tabell
 *   - run=status-indicator  -> grön/gul/röd statusindikator
 */

// Delegera till huvudcontrollern i classes/
require_once __DIR__ . '/../classes/StatistikDashboardController.php';
