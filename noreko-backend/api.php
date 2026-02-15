<?php
// api.php - Tar emot API-anrop och routar till rätt hantering

header('Content-Type: application/json');

// Databasanslutning (byt till dina värden)
global $pdo;
$pdo = new PDO('mysql:host=localhost:33061;dbname=mauserdb;charset=utf8mb4', 'aiab', 'Noreko2025');

// Enkel PSR-4-liknande autoloader
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/classes/' . $class . '.php';
    if (file_exists($file)) require $file;
});

$action = $_GET['action'] ?? '';
// Mapping för actions som inte följer standardnamngivning
$classNameMap = [
    'rebotlingproduct' => 'RebotlingProductController',
    'skiftrapport' => 'SkiftrapportController',
    'vpn' => 'VpnController',
    'bonusadmin' => 'BonusAdminController'
];
$className = $classNameMap[strtolower($action)] ?? ucfirst(strtolower($action)) . 'Controller';

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
        echo json_encode([
            'error' => 'Fel vid instansiering av controller',
            'message' => $e->getMessage(),
            'debug' => [
                'action' => $action,
                'className' => $className
            ]
        ]);
    }
} else {
    echo json_encode([
        'error' => 'Ogiltig action eller klass saknas'
    ]);
}
 