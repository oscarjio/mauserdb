<?php
/**
 * OperatorOnboardingController.php
 *
 * Proxy-fil: all logik finns i classes/OperatorOnboardingController.php
 * Denna fil existerar i controllers/ enligt konventionen men delegerar till classes/.
 *
 * Ruttning sker via api.php -> classes/ (autoloader).
 * Se classes/OperatorOnboardingController.php for full implementation med endpoints:
 *   - run=overview          -> Alla operatörer med onboarding-status, KPI
 *   - run=operator-curve    -> Veckovis IBC/h de första 12 veckorna
 *   - run=team-stats        -> Teamsnitt IBC/h, antal aktiva
 */

// Delegera till huvudcontrollern i classes/
require_once __DIR__ . '/../classes/OperatorOnboardingController.php';
