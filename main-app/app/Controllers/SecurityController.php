<?php
/**
 * Security Controller
 * Admin profile and security settings
 */

namespace NAPM\Controllers;

use NAPM\Core\App;
use NAPM\Core\Security;

class SecurityController
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
        $stmt = $db->prepare("SELECT id, username, email, full_name, role, last_login_at, created_at FROM admin_users WHERE id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch();

        $data = [
            'admin' => $admin,
            'csrf_token' => Security::generateCsrfToken(),
        ];

        $this->render('security', $data);
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