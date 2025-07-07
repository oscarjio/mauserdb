<?php
// tvattlinje.php - Hanterar anrop från tvättlinje

// Exempel: POST { data: ... }
$data = json_decode(file_get_contents('php://input'), true);

// Här kan du lägga till logik för att spara eller bearbeta data
if (isset($data['data'])) {
    // Spara eller bearbeta $data['data']
    echo json_encode(['success' => true, 'message' => 'Data mottagen', 'received' => $data['data']]);
} else {
    echo json_encode(['success' => false, 'message' => 'Ingen data mottagen']);
}
