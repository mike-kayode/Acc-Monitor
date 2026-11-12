<?php
/**
 * Authentication Controller
 * Admin login/logout for the monitoring dashboard
 */

namespace NAPM\Controllers;

use NAPM\Core\App;
use NAPM\Core\Security;

class AuthController
{
    private App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function login(): void
    {
        // If already logged in, redirect to dashboard
        if (!empty($_SESSION['admin_id'])) {
            $this->app->redirect('/dashboard');
        }

        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';

            $db = $this->app->db();
            $stmt = $db->prepare("SELECT * FROM admin_users WHERE username = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && Security::verifyPassword($password, $user['password_hash'])) {
                // Check if account is locked
                if ($user['locked_until'] && new \DateTime($user['locked_until']) > new \DateTime('now')) {
                    $error = 'Account is temporarily locked. Please try again later.';
                } else {
                    // Regenerate session ID
                    session_regenerate_id(true);

                    $_SESSION['admin_id'] = $user['id'];
                    $_SESSION['admin_username'] = $user['username'];
                    $_SESSION['admin_role'] = $user['role'];
                    $_SESSION['admin_full_name'] = $user['full_name'];
                    $_SESSION['admin_logged_in_at'] = time();

                    // Update last login
                    $db->prepare("UPDATE admin_users SET last_login_at = NOW(), failed_login_attempts = 0, locked_until = NULL WHERE id = ?")
                        ->execute([$user['id']]);

                    $this->app->logger()->info('Admin login successful', ['username' => $username]);
                    $this->app->redirect('/dashboard');
                }
            } else {
                // Increment failed attempts
                $db->prepare("UPDATE admin_users SET failed_login_attempts = failed_login_attempts + 1 WHERE username = ?")
                    ->execute([$username]);

                // Lock after 5 failed attempts
                $attemptQuery = $db->prepare("SELECT failed_login_attempts FROM admin_users WHERE username = ?");
                $attemptQuery->execute([$username]);
                $attempts = (int) ($attemptQuery->fetchColumn() ?: 0);

                if ($attempts >= 5) {
                    $lockUntil = new \DateTime('now');
                    $lockUntil->modify('+30 minutes');
                    $db->prepare("UPDATE admin_users SET locked_until = ? WHERE username = ?")
                        ->execute([$lockUntil->format('Y-m-d H:i:s'), $username]);
                }

                $error = 'Invalid username or password';
                $this->app->logger()->warning('Failed login attempt', ['username' => $username]);
            }
        }

        $this->render('login', ['error' => $error, 'csrf_token' => Security::generateCsrfToken()]);
    }

    public function doLogin(): void
    {
        $this->login();
    }

    public function logout(): void
    {
        $this->app->logger()->info('Admin logout', ['admin_id' => $_SESSION['admin_id'] ?? null]);
        session_destroy();
        $this->app->redirect('/login');
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