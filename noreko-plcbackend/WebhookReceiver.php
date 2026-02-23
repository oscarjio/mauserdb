<?php

declare(strict_types=1);

class WebhookReceiver {
    private WebhookProcessor $processor;

    public function __construct(WebhookProcessor $processor) {
        $this->processor = $processor;
    }

    public function handleRequest(): void {
        // Verifiera att det är en GET-request (Shelly skickar GET med query-parametrar)
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
            exit;
        }

        // Hämta rå JSON-data från request body
        $input = file_get_contents('php://input');
        $data = json_decode($input, true) ?? [];

        // Kontrollera om webhook-typ finns
        if (!isset($_GET['type'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing webhook type']);
            exit;
        }

        try {
            // Bearbeta webhook baserat på typ
            $this->processor->process($_GET['type'], $data);
            http_response_code(200);
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Processing failed: ' . $e->getMessage()]);
        }
    }
}
