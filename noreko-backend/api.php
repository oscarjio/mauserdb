<?php
// api.php - Tar emot API-anrop och routar till rätt hantering

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

$originAllowed = $origin && in_array($origin, $allowedOrigins, true);
if ($originAllowed) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
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

// Konfigurera session-cookie-parametrar (måste göras innan session_start() anropas av respektive controller).
// Anropar INTE session_start() här — det gör varje controller som behöver sessioner själv.
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
               (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    session_set_cookie_params([
        'lifetime' => 86400,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    @ini_set('session.gc_maxlifetime', '86400');
}

// Databasanslutning
global $pdo;
try {
    $dbConfig = __DIR__ . '/db_config.php';
    if (!file_exists($dbConfig)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Databaskonfiguration saknas (db_config.php)']);
        exit;
    }
    $db = require $dbConfig;
    $pdo = new PDO($db['dsn'], $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Databasanslutning misslyckades']);
    exit;
}

// Enkel PSR-4-liknande autoloader
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/classes/' . $class . '.php';
    if (file_exists($file)) require $file;
});

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
];

$actionKey = strtolower($action);
if (!isset($classNameMap[$actionKey])) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Endpoint hittades inte']);
    exit;
}
$className = $classNameMap[$actionKey];

// Ladda klassen manuellt
$file = __DIR__ . '/classes/' . $className . '.php';
if (file_exists($file)) {
    require_once $file;
}

if (class_exists($className)) {
    try {
        $controller = new $className();
        $controller->handle();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Internt serverfel'
        ]);
        error_log("API Error [{$action}]: " . $e->getMessage());
    }
} else {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => 'Endpoint hittades inte'
    ]);
}
