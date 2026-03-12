<?php
/**
 * ForstaTimmeAnalysController.php
 *
 * Proxy-fil: all logik finns i classes/ForstaTimmeAnalysController.php
 * Denna fil existerar i controllers/ enligt konventionen men delegerar till classes/.
 *
 * Ruttning sker via api.php -> classes/ (autoloader).
 * Se classes/ForstaTimmeAnalysController.php for full implementation med endpoints:
 *   - run=analysis&period=7|30|90  — per-skiftstart-data + aggregerad genomsnitts-kurva
 *   - run=trend&period=30|90       — daglig trend av "tid till forsta IBC"
 */

// Delegera till huvudcontrollern i classes/
require_once __DIR__ . '/../classes/ForstaTimmeAnalysController.php';
