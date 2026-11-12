<?php
/**
 * Authentication Management Controller
 * Handles NairaTrader manual login and session management
 */

namespace NAPM\Controllers;

use NAPM\Core\App;
use NAPM\Core\Security;

class AuthManagementController
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

        // Get current NairaTrader session
        $stmt = $db->prepare("SELECT * FROM monitor_sessions ORDER BY updated_at DESC LIMIT 1");
        $stmt->execute();
        $session = $stmt->fetch();

        // Check if session is valid
        $isValid = false;
        if ($session && (int) $session['is_valid'] === 1) {
            if ($session['expires_at'] && new \DateTime($session['expires_at']) < new \DateTime('now')) {
                $isValid = false;
            } else {
                $isValid = true;
            }
        }

        $data = [
            'session' => $session,
            'isValid' => $isValid,
            'csrf_token' => Security::generateCsrfToken(),
            'apiUrl' => $this->app->config('monitoring.nairatrader_api_url', 'https://nairatrader.ng/api/v1/admin/mirror/mt5-pool-available-counts?include_inactive=1'),
        ];

        $this->render('auth-management', $data);
    }

    public function setupSession(): void
    {
        // This handles the form submission where admin pastes their Bearer token
        $db = $this->app->db();
        if (isset($_POST['bearer_token']) && !is_string($_POST['bearer_token'])) {
            $this->app->jsonResponse(['success' => false, 'error' => 'Invalid API token.'], 400);
        }
        $bearerToken = trim((string) ($_POST['bearer_token'] ?? ''));
        if (isset($_POST['user_agent']) && !is_string($_POST['user_agent'])) {
            $this->app->jsonResponse(['success' => false, 'error' => 'Invalid user agent.'], 400);
        }
        $userAgent = trim((string) ($_POST['user_agent'] ?? ''));
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';

        if ($bearerToken === '') {
            $this->app->jsonResponse(['success' => false, 'error' => 'Bearer token is required'], 400);
        }

        // Remove "Bearer " prefix if present
        $token = preg_replace('/^Bearer\s+/i', '', $bearerToken) ?? '';
        if ($token === '' || strlen($token) > 4096 || preg_match('/[\x00-\x20\x7f]/', $token)) {
            $this->app->jsonResponse(['success' => false, 'error' => 'Invalid token format'], 400);
        }

        if ($userAgent === '') {
            $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
        }
        if (strlen($userAgent) > 255 || preg_match('/[\r\n]/', $userAgent)) {
            $this->app->jsonResponse(['success' => false, 'error' => 'User agent must be 255 characters or fewer.'], 400);
        }

        try {
            // Validate the token by making a test request to the API
            $apiUrl = $this->app->config('monitoring.nairatrader_api_url', 'https://nairatrader.ng/api/v1/admin/mirror/mt5-pool-available-counts?include_inactive=1');
            $testResult = $this->testApiToken($apiUrl, $token);

            if (!$testResult['success']) {
                $this->app->jsonResponse(['success' => false, 'error' => 'The API rejected the token or could not be reached. Please check the token and try again.'], 400);
            }

            $encryptedToken = $this->app->encryption()->encrypt($token);
            $expiresAt = date('Y-m-d H:i:s', time() + 86400);

            $db->beginTransaction();
            try {
                $db->prepare('UPDATE monitor_sessions SET is_valid = 0 WHERE is_valid = 1')->execute();
                $db->prepare("INSERT INTO monitor_sessions (session_name, encrypted_cookie_data, user_agent, ip_address, expires_at, is_valid, last_used_at, created_at) 
                              VALUES ('nairatrader_api', ?, ?, ?, ?, 1, NOW(), NOW())")
                    ->execute([$encryptedToken, $userAgent, $ipAddress, $expiresAt]);
                $db->commit();

                $this->app->logger()->info('NairaTrader API token saved', ['ip' => $ipAddress]);

                $this->app->jsonResponse(['success' => true, 'message' => 'API token saved successfully. Token expires in 24 hours.']);
            } catch (\Throwable $e) {
                if ($db->getConnection()->inTransaction()) {
                    $db->rollBack();
                }
                throw $e;
            }
        } catch (\Throwable $e) {
            $this->app->logger()->error('Failed to save API token', ['error' => $e->getMessage()]);
            $this->app->jsonResponse(['success' => false, 'error' => 'Failed to save token. Check the application logs.'], 500);
        }
    }

    private function testApiToken(string $apiUrl, string $token): array
    {
        try {
            $url = parse_url($apiUrl);
            if (
                !is_array($url)
                || strtolower((string) ($url['scheme'] ?? '')) !== 'https'
                || strtolower((string) ($url['host'] ?? '')) !== 'nairatrader.ng'
            ) {
                return ['success' => false, 'error' => 'Invalid API endpoint configuration'];
            }

            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0 Safari/537.36',
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $token,
                    'Accept: application/json',
                    'X-Tenant-Slug: nairatrader',
                ],
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HEADER => false,
            ]);
            $response = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($httpCode === 200 && $response !== false) {
                $data = json_decode($response, true);
                if ($data !== null && (isset($data['data']) || isset($data['available_accounts']) || isset($data['count']) || isset($data['left']))) {
                    return ['success' => true, 'error' => null];
                }
            }

            return ['success' => false, 'error' => 'API returned HTTP ' . $httpCode . ($curlError ? ' - ' . $curlError : '')];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function validateSession(): void
    {
        $scraper = new \NAPM\App\Services\ScraperService($this->app);

        $isValid = $scraper->validateSession();

        $this->app->jsonResponse([
            'success' => true,
            'valid' => $isValid,
            'message' => $isValid ? 'Session is valid and working' : 'Session is invalid or expired. Please re-authenticate.'
        ]);
    }

    public function refreshSession(): void
    {
        $db = $this->app->db();
        $stmt = $db->prepare("UPDATE monitor_sessions SET expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE session_name IN ('nairatrader_admin', 'nairatrader_api') AND is_valid = 1");
        $stmt->execute();
        if ($stmt->rowCount() === 0) {
            $this->app->jsonResponse(['success' => false, 'error' => 'No active session is available to extend.'], 400);
        }

        $isValid = (new \NAPM\App\Services\ScraperService($this->app))->validateSession();
        if (!$isValid) {
            $this->app->jsonResponse(['success' => false, 'error' => 'The NairaTrader session could not be validated. Please sign in again.'], 400);
        }

        $this->app->logger()->info('NairaTrader session expiry extended');
        $this->app->jsonResponse(['success' => true, 'message' => 'Session expiry extended by 24 hours']);
    }

    public function clearSession(): void
    {
        $db = $this->app->db();
        $db->prepare("UPDATE monitor_sessions SET is_valid = 0, encrypted_cookie_data = '' WHERE session_name IN ('nairatrader_admin', 'nairatrader_api')")->execute();

        $this->app->logger()->warning('NairaTrader API session cleared by admin');
        $this->app->jsonResponse(['success' => true, 'message' => 'NairaTrader API token cleared. Add a new token to resume monitoring.']);
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