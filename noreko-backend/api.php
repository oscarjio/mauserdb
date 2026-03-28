<?php
// api.php - Tar emot API-anrop och routar till rätt hantering

// Polyfill mb_substr / mb_strlen om php-mbstring saknas
if (!function_exists('mb_substr')) {
    function mb_substr(string $s, int $start, ?int $length = null, ?string $enc = null): string {
        return $length === null ? substr($s, $start) : substr($s, $start, $length);
    }
}
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $s, ?string $enc = null): int { return strlen($s); }
}
if (!function_exists('mb_strtolower')) {
    function mb_strtolower(string $s, ?string $enc = null): string { return strtolower($s); }
}
if (!function_exists('mb_strtoupper')) {
    function mb_strtoupper(string $s, ?string $enc = null): string { return strtoupper($s); }
}
if (!function_exists('mb_detect_encoding')) {
    function mb_detect_encoding(string $s, $enc = null, bool $strict = false): string|false { return 'UTF-8'; }
}
if (!function_exists('mb_convert_encoding')) {
    function mb_convert_encoding(string $s, string $to, string|array|null $from = null): string { return $s; }
}
if (!function_exists('mb_internal_encoding')) {
    function mb_internal_encoding(?string $enc = null): string|bool { return $enc === null ? 'UTF-8' : true; }
}

// Säkerställ konsekvent timezone för alla date()/strtotime()-anrop.
// Servern ligger i Sverige — all datum-hantering ska använda CET/CEST.
date_default_timezone_set('Europe/Stockholm');

// CORS-headers - begränsa till tillåtna domäner
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = [
    'http://localhost:4200',
    'http://localhost',
    'https://localhost',
];
// Lägg till extra domäner via cors_origins.php (server-specifik fil, ej i git)
$corsConfig = __DIR__ . '/cors_origins.php';
if (file_exists($corsConfig)) {
    $extraOrigins = require $corsConfig;
    if (is_array($extraOrigins)) {
        $allowedOrigins = array_merge($allowedOrigins, $extraOrigins);
    }
}

// Tillåt automatiskt alla subdomäner av serverns egna domän.
// Hanterar t.ex. dev.mauserdb.com ↔ mauserdb.com utan att behöva lista dem i cors_origins.php.
if ($origin) {
    $serverHost = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? '';
    // Extrahera registered domain (sista två delar: "mauserdb.com")
    $hostParts = explode('.', $serverHost);
    if (count($hostParts) >= 2) {
        $registeredDomain = implode('.', array_slice($hostParts, -2));
        // Kontrollera om origin matchar http(s)://(subdomain.)?registeredDomain
        $pattern = '#^https?://([\w-]+\.)?' . preg_quote($registeredDomain, '#') . '(:\d+)?$#';
        if (preg_match($pattern, $origin)) {
            $allowedOrigins[] = $origin;
        }
    }
}

// Strip CRLF from origin to prevent header injection (defense-in-depth)
$origin = str_replace(["\r", "\n"], '', $origin);
$originAllowed = $origin && in_array($origin, $allowedOrigins, true);
if ($originAllowed) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
    header('Vary: Origin');
}

// Hantera preflight OPTIONS-request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('Pragma: no-cache');
// Content-Security-Policy — begränsa resursladdning till samma origin
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'");
// Dölj PHP-version från response headers
header_remove('X-Powered-By');
// HSTS — tvinga HTTPS i 1 år (aktiveras bara om anslutningen redan är HTTPS)
$isHttpsForHsts = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                  (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
if ($isHttpsForHsts) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// Konfigurera session-cookie-parametrar (måste göras innan session_start() anropas av respektive controller).
// Anropar INTE session_start() här — det gör varje controller som behöver sessioner själv.
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
               (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    session_set_cookie_params([
        'lifetime' => 28800,   // 8 timmar — matchar AuthHelper::SESSION_TIMEOUT
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    @ini_set('session.gc_maxlifetime', '28800');  // 8 timmar — matchar AuthHelper::SESSION_TIMEOUT
    @ini_set('session.use_strict_mode', '1');     // Avvisa oinitierade session-ID:n (skydd mot session fixation)
    @ini_set('session.use_only_cookies', '1');    // Förhindra session-ID i URL (skydd mot session fixation)
    @ini_set('session.use_trans_sid', '0');       // Förhindra session-ID i URL-parametrar
}

// Databasanslutning
global $pdo;
try {
    $dbConfig = __DIR__ . '/db_config.php';
    if (!file_exists($dbConfig)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Databaskonfiguration saknas (db_config.php)'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $db = require $dbConfig;
    $pdo = new PDO($db['dsn'], $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => true   // Återanvänd DB-anslutningar mellan requests (~5-10ms besparing per request)
    ]);
} catch (\Throwable $e) {
    require_once __DIR__ . '/classes/ErrorLogger.php';
    ErrorLogger::log($e, 'api.php: Databasanslutning misslyckades');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Databasanslutning misslyckades'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Enkel PSR-4-liknande autoloader
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/classes/' . $class . '.php';
    if (file_exists($file)) require $file;
});

// API Rate Limiting — sliding window 120 req/minut per IP (undantar login/register som har egen begränsning)
require_once __DIR__ . '/classes/RateLimiter.php';
$rateLimitIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
// Om X-Forwarded-For innehåller flera IP:er — ta den första (klientens IP)
if (str_contains($rateLimitIp, ',')) {
    $rateLimitIp = trim(explode(',', $rateLimitIp)[0]);
}
// Validera att det är ett rimligt IP-format (IPv4 eller IPv6)
if (!filter_var($rateLimitIp, FILTER_VALIDATE_IP)) {
    $rateLimitIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
RateLimiter::check($rateLimitIp);
// Rensa gamla rate limit-filer med ~1% sannolikhet per request
if (mt_rand(1, 100) === 1) {
    RateLimiter::cleanup();
}

$action = $_GET['action'] ?? '';

// Vitlistade actions → controller-klasser
$classNameMap = [
    'rebotling' => 'RebotlingController',
    'rebotlingproduct' => 'RebotlingProductController',
    'tvattlinje' => 'TvattlinjeController',
    'saglinje' => 'SaglinjeController',
    'klassificeringslinje' => 'KlassificeringslinjeController',
    'skiftrapport' => 'SkiftrapportController',
    'login' => 'LoginController',
    'register' => 'RegisterController',
    'profile' => 'ProfileController',
    'admin' => 'AdminController',
    'bonus' => 'BonusController',
    'bonusadmin' => 'BonusAdminController',
    'vpn' => 'VpnController',
    'stoppage' => 'StoppageController',
    'audit' => 'AuditController',
    'status' => 'StatusController',
    'operators' => 'OperatorController',
    'operator'  => 'OperatorController',
    'operator-dashboard' => 'OperatorDashboardController',
    'lineskiftrapport' => 'LineSkiftrapportController',
    'shift-plan' => 'ShiftPlanController',
    'certifications' => 'CertificationController',
    'certification'  => 'CertificationController',
    'shift-handover' => 'ShiftHandoverController',
    'andon' => 'AndonController',
    'news' => 'NewsController',
    'historik' => 'HistorikController',
    'operator-compare' => 'OperatorCompareController',
    'maintenance' => 'MaintenanceController',
    'weekly-report' => 'WeeklyReportController',
    'feedback'     => 'FeedbackController',
    'runtime'      => 'RuntimeController',
    'produktionspuls' => 'ProduktionspulsController',
    'narvaro'         => 'NarvaroController',
    'min-dag'         => 'MinDagController',
    'alerts'          => 'AlertsController',
    'kassationsanalys' => 'KassationsanalysController',
    'dashboard-layout' => 'DashboardLayoutController',
    'produkttyp-effektivitet' => 'ProduktTypEffektivitetController',
    'stopporsak-reg'          => 'StopporsakRegistreringController',
    'skiftoverlamning'        => 'SkiftoverlamningController',
    'underhallslogg'          => 'UnderhallsloggController',
    'cykeltid-heatmap'        => 'CykeltidHeatmapController',
    'oee-benchmark'           => 'OeeBenchmarkController',
    'skiftrapport-export'     => 'SkiftrapportExportController',
    'feedback-analys'         => 'FeedbackAnalysController',
    'ranking-historik'        => 'RankingHistorikController',
    'produktionskalender'     => 'ProduktionskalenderController',
    'daglig-sammanfattning'   => 'DagligSammanfattningController',
    'malhistorik'             => 'MalhistorikController',
    'skiftjamforelse'         => 'SkiftjamforelseController',
    'underhallsprognos'       => 'UnderhallsprognosController',
    'kvalitetstrend'          => 'KvalitetstrendController',
    'effektivitet'            => 'EffektivitetController',
    'stopporsak-trend'        => 'StopporsakTrendController',
    'produktionsmal'          => 'ProduktionsmalController',
    'utnyttjandegrad'         => 'UtnyttjandegradController',
    'produktionstakt'         => 'ProduktionsTaktController',
    'veckorapport'            => 'VeckorapportController',
    'alarm-historik'          => 'AlarmHistorikController',
    'operatorsportal'         => 'OperatorsportalController',
    'heatmap'                 => 'HeatmapController',
    'pareto'                  => 'ParetoController',
    'oee-waterfall'           => 'OeeWaterfallController',
    'morgonrapport'           => 'MorgonrapportController',
    'drifttids-timeline'      => 'DrifttidsTimelineController',
    'kassations-drilldown'    => 'KassationsDrilldownController',
    'forsta-timme-analys'     => 'ForstaTimmeAnalysController',
    'my-stats'                => 'MyStatsController',
    'produktionsprognos'      => 'ProduktionsPrognosController',
    'stopporsak-operator'     => 'StopporsakOperatorController',
    'operator-onboarding'     => 'OperatorOnboardingController',
    'operator-jamforelse'          => 'OperatorJamforelseController',
    'produktionseffektivitet'      => 'ProduktionseffektivitetController',
    'favoriter'                    => 'FavoriterController',
    'kvalitetstrendbrott'          => 'KvalitetsTrendbrottController',
    'maskinunderhall'              => 'MaskinunderhallController',
    'statistikdashboard'           => 'StatistikDashboardController',
    'batchsparning'                => 'BatchSparningController',
    'kassationsorsakstatistik'     => 'KassationsorsakController',
    'skiftplanering'               => 'SkiftplaneringController',
    'produktionssla'               => 'ProduktionsSlaController',
    'stopptidsanalys'              => 'StopptidsanalysController',
    'produktionskostnad'           => 'ProduktionskostnadController',
    'maskin-oee'                   => 'MaskinOeeController',
    'operatorsbonus'               => 'OperatorsbonusController',
    'leveransplanering'            => 'LeveransplaneringController',
    'kvalitetscertifikat'          => 'KvalitetscertifikatController',
    'historisk-produktion'         => 'HistoriskProduktionController',
    'avvikelselarm'                => 'AvvikelselarmController',
    'rebotling-sammanfattning'     => 'RebotlingSammanfattningController',
    'produktionsflode'             => 'ProduktionsflodeController',
    'kassationsorsak-per-station'  => 'KassationsorsakPerStationController',
    'oee-jamforelse'               => 'OeeJamforelseController',
    'maskin-drifttid'               => 'MaskinDrifttidController',
    'maskinhistorik'                => 'MaskinhistorikController',
    'kassationskvotalarm'           => 'KassationskvotAlarmController',
    'kapacitetsplanering'           => 'KapacitetsplaneringController',
    'produktionsdashboard'          => 'ProduktionsDashboardController',
    'rebotlingtrendanalys'          => 'RebotlingTrendanalysController',
    'operatorsprestanda'            => 'OperatorsPrestandaController',
    'rebotling-stationsdetalj'      => 'RebotlingStationsdetaljController',
    'vd-veckorapport'               => 'VDVeckorapportController',
    'stopporsak-dashboard'          => 'StopporsakController',
    'tidrapport'                    => 'TidrapportController',
    'oee-trendanalys'               => 'OeeTrendanalysController',
    'operator-ranking'              => 'OperatorRankingController',
    'vd-dashboard'                  => 'VdDashboardController',
    'historisk-sammanfattning'       => 'HistoriskSammanfattningController',
    'kvalitetstrendanalys'           => 'KvalitetstrendanalysController',
    'statistik-overblick'            => 'StatistikOverblickController',
    'daglig-briefing'                => 'DagligBriefingController',
    'gamification'                   => 'GamificationController',
    'prediktivt-underhall'           => 'PrediktivtUnderhallController',
    'feature-flags'                  => 'FeatureFlagController',
];

$actionKey = strtolower($action);
if (!isset($classNameMap[$actionKey])) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Endpoint hittades inte'], JSON_UNESCAPED_UNICODE);
    exit;
}
$className = $classNameMap[$actionKey];

// Centraliserad session-timeout-kontroll för state-ändrande requests (POST/PUT/DELETE).
// Login, register och status hanterar sessioner själva — hoppa över dem.
$publicActions = ['login', 'register', 'status'];
if (!in_array($actionKey, $publicActions, true) && in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'], true)) {
    require_once __DIR__ . '/classes/AuthHelper.php';
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!AuthHelper::checkSessionTimeout()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Sessionen har gått ut. Logga in igen.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    // CSRF-token-validering för alla state-ändrande requests (POST/PUT/DELETE).
    // Token skickas via X-CSRF-Token-headern och jämförs mot sessionen.
    if (!AuthHelper::validateCsrfToken()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Ogiltig CSRF-token. Ladda om sidan och försök igen.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    // Släpp session-låset nu — timeout och CSRF är validerade, last_activity uppdaterad.
    // Controllers som behöver skriva till sessionen (t.ex. ProfileController) öppnar den igen själva.
    // Utan detta blockerar tunga requests (SQL, nätverks-I/O) alla andra requests för samma session.
    session_write_close();
}

// Ladda klassen manuellt
$file = __DIR__ . '/classes/' . $className . '.php';
if (file_exists($file)) {
    require_once $file;
}

if (class_exists($className)) {
    try {
        $controller = new $className();
        $controller->handle();
    } catch (\Throwable $e) {
        // Fånga ALLA fel inkl. TypeError, ValueError, Error — inte bara Exception.
        // Förhindrar att interna felmeddelanden/stacktrace läcker till klienten.
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Internt serverfel'
        ], JSON_UNESCAPED_UNICODE);
        require_once __DIR__ . '/classes/ErrorLogger.php';
        ErrorLogger::log($e, "API Error [{$action}]");
    }
} else {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => 'Endpoint hittades inte'
    ], JSON_UNESCAPED_UNICODE);
}
