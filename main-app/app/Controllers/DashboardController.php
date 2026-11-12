<?php
/**
 * Dashboard Controller
 * Main overview page with system status
 */

namespace NAPM\Controllers;

use NAPM\Core\App;
use NAPM\Core\Database;

class DashboardController
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

        // Get latest account snapshot
        $stmt = $db->prepare("SELECT * FROM account_snapshots WHERE extraction_status = 'success' ORDER BY checked_at DESC LIMIT 1");
        $stmt->execute();
        $latestSnapshot = $stmt->fetch();
        if ($latestSnapshot) {
            $planCounts = json_decode((string) ($latestSnapshot['plan_counts_json'] ?? ''), true);
            $latestSnapshot['plan_counts'] = is_array($planCounts) ? $planCounts : [];
        }

        // Get previous snapshot for comparison
        $stmt = $db->prepare("SELECT * FROM account_snapshots WHERE extraction_status = 'success' ORDER BY checked_at DESC LIMIT 1 OFFSET 1");
        $stmt->execute();
        $previousSnapshot = $stmt->fetch();

        // Get monitoring settings
        $settings = $this->getSettings($db);

        // Get WhatsApp session status
        $stmt = $db->prepare("SELECT * FROM whatsapp_sessions ORDER BY updated_at DESC LIMIT 1");
        $stmt->execute();
        $whatsappStatus = $stmt->fetch();

        // Get NairaTrader API session status
        $stmt = $db->query("SELECT is_valid FROM monitor_sessions ORDER BY updated_at DESC LIMIT 1");
        $monitorSession = $stmt->fetch();
        $sessionValid = (bool) ($monitorSession['is_valid'] ?? false);

        // Get Telegram settings
        $stmt = $db->prepare("SELECT * FROM telegram_settings LIMIT 1");
        $stmt->execute();
        $telegramStatus = $stmt->fetch();

        // Get recipient count
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM whatsapp_recipients WHERE is_enabled = 1");
        $stmt->execute();
        $recipientCount = $stmt->fetchColumn() ?? 0;

        // Get system health
        $stmt = $db->prepare("SELECT * FROM system_health ORDER by last_check_at DESC");
        $stmt->execute();
        $systemHealth = $stmt->fetchAll();

        // Get latest alert
        $stmt = $db->prepare("SELECT * FROM alert_history ORDER BY created_at DESC LIMIT 1");
        $stmt->execute();
        $latestAlert = $stmt->fetch();

        $data = [
            'latestSnapshot' => $latestSnapshot,
            'planCatalog' => \NAPM\App\Services\NairaTraderPlans::catalog(),
            'previousSnapshot' => $previousSnapshot,
            'settings' => $settings,
            'whatsappStatus' => $whatsappStatus,
            'sessionValid' => $sessionValid,
            'telegramStatus' => $telegramStatus,
            'recipientCount' => $recipientCount,
            'systemHealth' => $systemHealth,
            'latestAlert' => $latestAlert,
            'csrf_token' => \NAPM\Core\Security::generateCsrfToken(),
            'adminName' => $_SESSION['admin_full_name'] ?? 'Administrator',
        ];

        $this->render('dashboard', $data);
    }

    private function getSettings(Database $db): array
    {
        $stmt = $db->prepare("SELECT setting_key, setting_value FROM monitor_settings");
        $stmt->execute();
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
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