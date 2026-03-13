<?php
/**
 * SkiftoverlamningController.php
 *
 * Proxy-fil: all logik finns i classes/SkiftoverlamningController.php
 * Denna fil existerar i controllers/ enligt konventionen men delegerar till classes/.
 *
 * Ruttning sker via api.php -> classes/ (autoloader).
 * Se classes/SkiftoverlamningController.php for full implementation med endpoints:
 *   - run=list               -> Lista överlämningar (filtrerad)
 *   - run=detail             -> Fullständig vy av en överlämning
 *   - run=shift-kpis         -> Auto-hämta KPI:er för senaste skift
 *   - run=summary            -> Sammanfattnings-KPI:er (senaste, antal vecka, snitt, pågående)
 *   - run=operators          -> Lista operatörer (filter-dropdown)
 *   - run=aktuellt-skift     -> Info om pågående skift (realtid)
 *   - run=skift-sammanfattning -> Sammanfattning av förra skiftet med KPI-jämförelse mot mål
 *   - run=oppna-problem      -> Lista öppna/pågående problem med allvarlighetsgrad
 *   - run=checklista         -> Hämta standard-checklistepunkter
 *   - run=historik           -> Senaste 10 överlämningar med fullständigt innehåll
 *   - run=create (POST)      -> Skapa ny överlämning
 *   - run=skapa-overlamning (POST) -> Skapa ny överlämning (med checklista, mål)
 */

// Delegera till huvudcontrollern i classes/
require_once __DIR__ . '/../classes/SkiftoverlamningController.php';
