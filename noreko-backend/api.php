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
$className = ucfirst(strtolower($action)) . 'Controller';

if (class_exists($className)) {
    $controller = new $className();
    $controller->handle();
} else {
    echo json_encode(['error' => 'Ogiltig action eller klass saknas']);
}
 