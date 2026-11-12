<?php
/**
 * Monitoring Controller
 * Monitoring configuration and manual check
 */

namespace NAPM\Controllers;

use NAPM\Core\App;
use NAPM\Core\Security;

class MonitoringController
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
        $session = $db->query(
            'SELECT is_valid, updated_at, expires_at FROM monitor_sessions ORDER BY updated_at DESC LIMIT 1'
        )->fetch();

        $data = [
            'settings' => $settings,
            'monitorSession' => $session,
            'csrf_token' => Security::generateCsrfToken(),
        ];

        $this->render('monitoring', $data);
    }

    public function save(): void
    {
        $db = $this->app->db();
        $updatableKeys = [
            'monitoring_enabled', 'monitoring_interval_minutes', 'warning_threshold',
            'critical_threshold', 'emergency_threshold', 'exhaustion_threshold',
            'whatsapp_enabled', 'telegram_enabled', 'connection_timeout_seconds',
            'request_timeout_seconds', 'extraction_retry_attempts',
            'notification_retry_attempts', 'duplicate_alert_prevention',
            'recovery_notifications', 'reminder_interval_minutes',
        ];

        try {
            $db->beginTransaction();
            $numericSettings = [
                'monitoring_interval_minutes', 'warning_threshold', 'critical_threshold',
                'emergency_threshold', 'exhaustion_threshold', 'connection_timeout_seconds',
                'request_timeout_seconds', 'extraction_retry_attempts',
                'notification_retry_attempts', 'reminder_interval_minutes',
            ];
            foreach ($updatableKeys as $key) {
                if (isset($_POST[$key])) {
                    $value = trim((string) $_POST[$key]);
                    $type = 'string';
                    if (in_array($key, ['monitoring_enabled', 'whatsapp_enabled', 'telegram_enabled', 'duplicate_alert_prevention', 'recovery_notifications'])) {
                        $type = 'boolean';
                        $value = $value === 'on' || $value === '1' || $value === true ? 'true' : 'false';
                    } elseif (in_array($key, $numericSettings, true)) {
                        if (!ctype_digit($value)) {
                            throw new \InvalidArgumentException('Monitoring settings must be non-negative integers.');
                        }
                        if (in_array($key, ['monitoring_interval_minutes', 'connection_timeout_seconds', 'request_timeout_seconds', 'extraction_retry_attempts', 'notification_retry_attempts', 'reminder_interval_minutes'], true) && (int) $value < 1) {
                            throw new \InvalidArgumentException('Intervals and retry limits must be at least one.');
                        }
                        $type = 'integer';
                    }

                    $db->prepare("UPDATE monitor_settings SET setting_value = ?, setting_type = ?, updated_at = NOW() WHERE setting_key = ?")
                        ->execute([$value, $type, $key]);
                }
            }
            $db->commit();

            $this->app->logger()->info('Monitoring settings updated');
            $this->app->jsonResponse(['success' => true, 'message' => 'Settings saved']);
        } catch (\InvalidArgumentException $e) {
            $db->rollBack();
            $this->app->jsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            if ($db->getConnection()->inTransaction()) {
                $db->rollBack();
            }
            $this->app->jsonResponse(['success' => false, 'error' => 'Failed to save settings'], 500);
        }
    }

    public function saveSession(): void
    {
        $cookie = trim((string) ($_POST['cookie_data'] ?? ''));
        if ($cookie === '' || strlen($cookie) > 10240 || preg_match('/[\r\n]/', $cookie)) {
            $this->app->jsonResponse(['success' => false, 'error' => 'Enter a valid NairaTrader cookie string (maximum 10 KB).'], 400);
        }

        foreach (explode(';', $cookie) as $pair) {
            $parts = explode('=', trim($pair), 2);
            if (count($parts) !== 2 || !preg_match('/^[A-Za-z0-9_-]+$/', trim($parts[0])) || trim($parts[1]) === '') {
                $this->app->jsonResponse(['success' => false, 'error' => 'Cookie data must be copied as name=value pairs separated by semicolons.'], 400);
            }
        }

        $encryptedCookie = $this->app->encryption()->encrypt($cookie);
        $db = $this->app->db();
        $db->beginTransaction();
        try {
            $db->prepare('UPDATE monitor_sessions SET is_valid = 0 WHERE is_valid = 1')->execute();
            $db->prepare(
                'INSERT INTO monitor_sessions (session_name, encrypted_cookie_data, user_agent, is_valid, last_used_at) VALUES (?, ?, ?, 1, NULL)'
            )->execute(['NairaTrader admin session', $encryptedCookie, substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->getConnection()->inTransaction()) {
                $db->rollBack();
            }
            $this->app->logger()->error('Could not save NairaTrader session cookie', ['error' => $e->getMessage()]);
            $this->app->jsonResponse(['success' => false, 'error' => 'Could not save the NairaTrader session.'], 500);
        }

        $this->app->jsonResponse(['success' => true, 'message' => 'Encrypted NairaTrader session saved.']);
    }

    public function checkNow(): void
    {
        // Trigger monitoring run via cron endpoint
        $cronScript = __DIR__ . '/../../cron/monitor.php';
        if (!file_exists($cronScript)) {
            $this->app->jsonResponse(['success' => false, 'error' => 'Monitoring script is unavailable'], 503);
        }

        $output = [];
        $returnCode = 0;
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($cronScript) . ' 2>&1', $output, $returnCode);
        $this->app->logger()->info('Manual monitoring check completed', ['return_code' => $returnCode]);
        if ($returnCode !== 0) {
            $this->app->jsonResponse(['success' => false, 'error' => 'Monitoring check failed'], 503);
        }
        $this->app->jsonResponse(['success' => true, 'message' => 'Monitoring check completed']);
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