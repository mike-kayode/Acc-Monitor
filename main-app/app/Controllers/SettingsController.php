<?php
/**
 * Settings Controller
 * General application settings
 */

namespace NAPM\Controllers;

use NAPM\Core\App;
use NAPM\Core\Security;

class SettingsController
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
        $stmt = $db->prepare("SELECT * FROM monitor_settings ORDER BY setting_key ASC");
        $stmt->execute();
        $settings = $stmt->fetchAll();

        $data = [
            'settings' => $settings,
            'csrf_token' => Security::generateCsrfToken(),
        ];

        $this->render('settings', $data);
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