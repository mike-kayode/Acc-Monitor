<?php
/**
 * History Controller
 * Account history and analytics
 */

namespace NAPM\Controllers;

use NAPM\Core\App;

class HistoryController
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

        // Get snapshots with pagination
        $page = (int) ($_GET['page'] ?? 1);
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $stmt = $db->prepare("SELECT * FROM account_snapshots ORDER BY checked_at DESC LIMIT ? OFFSET ?");
        $stmt->execute([$limit, $offset]);
        $snapshots = $stmt->fetchAll();

        // Get stats
        $stats = [];
        $statQueries = [
            'total' => "SELECT COUNT(*) FROM account_snapshots",
            'min' => "SELECT MIN(available_accounts) FROM account_snapshots WHERE extraction_status = 'success'",
            'max' => "SELECT MAX(available_accounts) FROM account_snapshots WHERE extraction_status = 'success'",
            'latest' => "SELECT available_accounts FROM account_snapshots WHERE extraction_status = 'success' ORDER BY checked_at DESC LIMIT 1",
        ];
        foreach ($statQueries as $key => $query) {
            $stmt = $db->query($query);
            $value = $stmt->fetchColumn();
            $stats[$key] = $value === false || $value === null ? 0 : (int) $value;
        }

        $data = [
            'snapshots' => $snapshots,
            'stats' => $stats,
            'page' => $page,
            'csrf_token' => \NAPM\Core\Security::generateCsrfToken(),
        ];

        $this->render('history', $data);
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