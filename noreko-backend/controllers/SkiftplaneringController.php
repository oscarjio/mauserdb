<?php
/**
 * SkiftplaneringController.php (proxy)
 *
 * Proxy-fil: all logik finns i classes/SkiftplaneringController.php
 * Denna fil existerar i controllers/ enligt konventionen men delegerar till classes/.
 *
 * Ruttning sker via api.php -> classes/ (autoloader).
 * Se classes/SkiftplaneringController.php för full implementation med endpoints:
 *   - run=overview          -> KPI:er: operatörer totalt, bemanningsgrad, underbemanning, nästa skiftbyte
 *   - run=schedule          -> Veckoschema med operatörer per skift/dag
 *   - run=shift-detail      -> Detalj per skift: operatörer, kapacitet, produktion
 *   - run=assign   (POST)  -> Tilldela operatör till skift/dag
 *   - run=unassign (POST)  -> Ta bort operatör från skift/dag
 *   - run=capacity          -> Kapacitetsplanering med historisk IBC/h
 *   - run=operators         -> Lista tillgängliga operatörer
 */

// Delegera till huvudcontrollern i classes/
require_once __DIR__ . '/../classes/SkiftplaneringController.php';
