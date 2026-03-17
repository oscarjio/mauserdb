<?php
/**
 * SkiftjamforelseController.php
 *
 * Proxy-fil: all logik finns i classes/SkiftjamforelseController.php
 * Denna fil existerar i controllers/ enligt konventionen men delegerar till classes/.
 *
 * Ruttning sker via api.php -> classes/ (autoloader).
 * Se classes/SkiftjamforelseController.php for full implementation med endpoints:
 *   - run=sammanfattning   -> KPI-kort: mest produktiva skiftet, snitt-OEE per skift, trend
 *   - run=jamforelse       -> FM vs EM vs Natt tabell + radardata (5 axlar)
 *   - run=trend            -> OEE per skift per dag senaste 30d
 *   - run=best-practices   -> identifiera styrkor per skift och station
 *   - run=detaljer         -> detaljlista alla skift
 */

// Delegera till huvudcontrollern i classes/
require_once __DIR__ . '/../classes/SkiftjamforelseController.php';
