<?php
// login.php - Hanterar inloggning

// Exempel: POST { username, password }
$data = json_decode(file_get_contents('php://input'), true);
$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

// Här kan du lägga till riktig användarvalidering mot databas
if ($username === 'admin' && $password === 'admin123') {
    echo json_encode(['success' => true, 'message' => 'Inloggning lyckades']);
} else {
    echo json_encode(['success' => false, 'message' => 'Felaktigt användarnamn eller lösenord']);
}
