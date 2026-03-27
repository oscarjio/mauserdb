#!/usr/bin/env node
/**
 * MauserDB E2E-test
 * Testar alla API-endpoints mot dev.mauserdb.com
 */

const https = require('https');
const BASE = 'https://dev.mauserdb.com/noreko-backend/api.php';

let pass = 0;
let fail = 0;
const failures = [];

function testEndpoint(action, expectedCodes = [200, 400, 401, 403, 404, 405]) {
  return new Promise((resolve) => {
    const url = `${BASE}?action=${action}`;
    const req = https.get(url, { timeout: 10000 }, (res) => {
      let body = '';
      res.on('data', d => body += d);
      res.on('end', () => {
        const code = res.statusCode;
        if (code === 500) {
          fail++;
          failures.push(`${action}: HTTP ${code}`);
          console.log(`  FAIL  ${action} => ${code}`);
        } else {
          pass++;
          console.log(`  OK    ${action} => ${code}`);
        }
        resolve();
      });
    });
    req.on('error', (err) => {
      fail++;
      failures.push(`${action}: ${err.message}`);
      console.log(`  FAIL  ${action} => ${err.message}`);
      resolve();
    });
    req.on('timeout', () => {
      req.destroy();
      fail++;
      failures.push(`${action}: TIMEOUT`);
      console.log(`  FAIL  ${action} => TIMEOUT`);
      resolve();
    });
  });
}

async function runTests() {
  console.log('=== MauserDB E2E Test ===\n');

  const endpoints = [
    // Public endpoints
    'rebotling', 'tvattlinje', 'saglinje', 'klassificeringslinje',
    'historik', 'status', 'feature-flags', 'stoppage',
    // Auth-protected endpoints (expect 401/403)
    'rebotlingproduct', 'skiftrapport', 'login', 'register', 'profile',
    'admin', 'bonus', 'bonusadmin', 'vpn', 'audit', 'operators',
    'operator', 'operator-dashboard', 'lineskiftrapport', 'shift-plan',
    'certifications', 'certification', 'shift-handover', 'andon', 'news',
    'operator-compare', 'maintenance', 'weekly-report', 'feedback',
    'runtime', 'produktionspuls', 'narvaro', 'min-dag', 'alerts',
    'kassationsanalys', 'dashboard-layout', 'produkttyp-effektivitet',
    'stopporsak-reg', 'skiftoverlamning', 'underhallslogg',
    'cykeltid-heatmap', 'oee-benchmark', 'skiftrapport-export',
    'feedback-analys', 'ranking-historik', 'produktionskalender',
    'daglig-sammanfattning', 'malhistorik', 'skiftjamforelse',
    'underhallsprognos', 'kvalitetstrend', 'effektivitet',
    'stopporsak-trend', 'produktionsmal', 'utnyttjandegrad',
    'produktionstakt', 'veckorapport', 'alarm-historik',
    'operatorsportal', 'heatmap', 'pareto', 'oee-waterfall',
    'morgonrapport', 'drifttids-timeline', 'kassations-drilldown',
    'forsta-timme-analys', 'my-stats', 'produktionsprognos',
    'stopporsak-operator', 'operator-onboarding', 'operator-jamforelse',
    'produktionseffektivitet', 'favoriter', 'kvalitetstrendbrott',
    'maskinunderhall', 'statistikdashboard', 'batchsparning',
    'kassationsorsakstatistik', 'skiftplanering', 'produktionssla',
    'stopptidsanalys', 'produktionskostnad', 'maskin-oee',
    'operatorsbonus', 'leveransplanering', 'kvalitetscertifikat',
    'historisk-produktion', 'avvikelselarm', 'rebotling-sammanfattning',
    'produktionsflode', 'kassationsorsak-per-station', 'oee-jamforelse',
    'maskin-drifttid', 'maskinhistorik', 'kassationskvotalarm',
    'kapacitetsplanering', 'produktionsdashboard', 'rebotlingtrendanalys',
    'operatorsprestanda', 'rebotling-stationsdetalj', 'vd-veckorapport',
    'stopporsak-dashboard', 'tidrapport', 'oee-trendanalys',
    'operator-ranking', 'vd-dashboard', 'historisk-sammanfattning',
    'kvalitetstrendanalys', 'statistik-overblick', 'daglig-briefing',
    'gamification', 'prediktivt-underhall',
  ];

  // Run in batches of 10 to avoid overwhelming the server
  for (let i = 0; i < endpoints.length; i += 10) {
    const batch = endpoints.slice(i, i + 10);
    await Promise.all(batch.map(ep => testEndpoint(ep)));
  }

  console.log(`\n=== RESULTAT ===`);
  console.log(`PASS: ${pass}/${pass + fail}`);
  console.log(`FAIL: ${fail}`);

  if (failures.length > 0) {
    console.log(`\n500-fel:`);
    failures.forEach(f => console.log(`  ${f}`));
  }

  process.exit(fail > 0 ? 1 : 0);
}

runTests();
