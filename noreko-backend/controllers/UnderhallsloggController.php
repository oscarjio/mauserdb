<?php
/**
 * UnderhallsloggController.php
 *
 * Proxy-fil: all logik finns i classes/UnderhallsloggController.php
 * Denna fil existerar i controllers/ enligt konventionen men delegerar till classes/.
 *
 * Endpoints via ?action=underhallslogg&run=XXX:
 *   GET  run=categories          -> Lista underhallskategorier
 *   GET  run=list                -> Lista underhallsposter (med filtrering)
 *   GET  run=stats               -> Sammanfattningsstatistik
 *   GET  run=lista               -> Lista rebotling-underhall (station, typ, period)
 *   GET  run=sammanfattning      -> KPI-kort: totalt, ratio, snitt tid, top station
 *   GET  run=per-station         -> Underhall grupperat per station
 *   GET  run=manadschart         -> Planerat vs oplanerat per manad (6 man)
 *   GET  run=stationer           -> Lista rebotling-stationer
 *   POST run=log                 -> Logga underhall (gamla tabellen)
 *   POST run=delete              -> Ta bort en post (admin-only)
 *   POST run=skapa               -> Registrera nytt rebotling-underhall
 *   POST run=ta-bort             -> Ta bort rebotling-underhallspost
 */

// Delegera till huvudcontrollern i classes/
require_once __DIR__ . '/../classes/UnderhallsloggController.php';
