<?php
/**
 * ProduktionsmalController.php
 *
 * Proxy-fil: all logik finns i classes/ProduktionsmalController.php
 * Denna fil existerar i controllers/ enligt konventionen men delegerar till classes/.
 *
 * Endpoints via ?action=produktionsmal&run=XXX:
 *   GET  run=aktuellt-mal     -> Hamta aktivt mal (vecka eller manad)
 *   GET  run=progress         -> Aktuell progress mot malet
 *   GET  run=mal-historik     -> Historiska mal och utfall
 *   GET  run=sammanfattning   -> KPI-kort: dagens mal, utfall, uppfyllnad%, veckotrend
 *   GET  run=per-skift        -> Utfall per skift idag
 *   GET  run=veckodata        -> Mal vs utfall per dag, senaste 4 veckorna
 *   GET  run=historik         -> Daglig historik senaste 30d
 *   GET  run=per-station      -> Utfall per station idag
 *   GET  run=hamta-mal        -> Hamta aktuella mal (dag/vecka)
 *   POST run=satt-mal         -> Spara nytt mal (vecka/manad)
 *   POST run=spara-mal        -> Spara/uppdatera mal (dag/vecka)
 *   GET  run=summary          -> Legacy: dag/vecka/manad sammanfattning
 *   GET  run=daily            -> Legacy: daglig tidsserie
 *   GET  run=weekly           -> Legacy: veckovis data
 */

// Delegera till huvudcontrollern i classes/
require_once __DIR__ . '/../classes/ProduktionsmalController.php';
