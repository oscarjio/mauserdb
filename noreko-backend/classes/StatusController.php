<?php
class StatusController {
    public function handle() {
        session_start();
        if (isset($_SESSION['user_id'])) {
            echo json_encode([
                'loggedIn' => true,
                'user' => [
                    'id' => $_SESSION['user_id'],
                    'username' => $_SESSION['username']
                ]
            ]);
        } else {
            echo json_encode(['loggedIn' => false]);
        }
    }
}
