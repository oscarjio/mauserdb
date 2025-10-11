<?php
// Exempel på användning
require_once 'autoloader.php';
require_once 'vendor/autoload.php';

ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);

try {
    $db = new PDO('mysql:host=localhost:33061;dbname=mauserdb', 'aiab', 'Noreko2025');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $processor = new WebhookProcessor($db);
    $receiver = new WebhookReceiver($processor);
    $receiver->handleRequest();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}