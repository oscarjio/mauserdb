<?php
// admin.php - Hanterar administration av logins

// Exempel: GET/POST för att lista, lägga till eller ta bort användare
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Returnera lista på användare (hårdkodat exempel)
    echo json_encode([
        ['username' => 'admin'],
        ['username' => 'user1']
    ]);
} elseif ($method === 'POST') {
    // Lägg till ny användare (exempel)
    $data = json_decode(file_get_contents('php://input'), true);
    // Här kan du lägga till kod för att spara användaren
    echo json_encode(['success' => true, 'message' => 'Användare tillagd', 'user' => $data]);
} else {
    echo json_encode(['error' => 'Ogiltig metod']);
}
