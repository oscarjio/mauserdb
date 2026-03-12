<?php
/**
 * FavoriterController.php (proxy)
 *
 * Proxy-fil: all logik finns i classes/FavoriterController.php
 * Denna fil existerar i controllers/ enligt konventionen men delegerar till classes/.
 *
 * Ruttning sker via api.php -> classes/ (autoloader).
 * Se classes/FavoriterController.php för full implementation med endpoints:
 *   - run=list              -> Hämta användarens sparade favoriter
 *   - run=add    (POST)     -> Lägg till en favorit
 *   - run=remove (POST)     -> Ta bort en favorit
 *   - run=reorder (POST)    -> Ändra ordning
 */

// Delegera till huvudcontrollern i classes/
require_once __DIR__ . '/../classes/FavoriterController.php';
