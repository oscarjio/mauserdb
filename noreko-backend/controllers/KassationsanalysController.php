<?php
/**
 * KassationsanalysController.php
 *
 * Proxy-fil: all logik finns i classes/KassationsanalysController.php
 * Denna fil existerar i controllers/ enligt konventionen men delegerar till classes/.
 *
 * Ruttning sker via api.php -> classes/ (autoloader).
 * Se classes/KassationsanalysController.php for full implementation med endpoints:
 *   - run=overview       -> KPI-sammanfattning (antal, grad, orsak, kostnad)
 *   - run=by-period      -> kassationer per vecka/manad per orsak (topp 5)
 *   - run=details        -> filtrbar detaljlista (orsak, operator)
 *   - run=trend-rate     -> kassationsgrad (%) over tid med trendlinje
 *   - run=summary        -> totala kassationer, rate, topp-orsak, trend
 *   - run=by-cause       -> kassationer per orsak med andel/trend
 *   - run=daily-stacked  -> daglig stackad data for Chart.js
 *   - run=drilldown      -> detaljdata for specifik orsak
 */

// Delegera till huvudcontrollern i classes/
require_once __DIR__ . '/../classes/KassationsanalysController.php';
