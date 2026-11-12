<?php
/**
 * Log Controller
 * System logs display
 */

namespace NAPM\Controllers;

use NAPM\Core\App;

class LogController
{
    private App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->requireAuth();
    }

    private function requireAuth(): void
    {
        if (empty($_SESSION['admin_id'])) {
            $this->app->redirect('/login');
        }
    }

    public function index(): void
    {
        $db = $this->app->db();

        $page = (int) ($_GET['page'] ?? 1);
        $limit = 100;
        $offset = ($page - 1) * $limit;

        $stmt = $db->prepare("SELECT * FROM monitor_logs ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->execute([$limit, $offset]);
        $logs = $stmt->fetchAll();

        $data = [
            'logs' => $logs,
            'page' => $page,
            'csrf_token' => \NAPM\Core\Security::generateCsrfToken(),
        ];

        $this->render('logs', $data);
    }

    private function render(string $view, array $data = []): void
    {
        extract($data);
        $viewFile = __DIR__ . '/../../resources/views/' . $view . '.php';
        if (file_exists($viewFile)) {
            require_once __DIR__ . '/../../resources/views/layout.php';
        } else {
            http_response_code(404);
            echo 'View not found: ' . $view;
        }
    }
}