<?php
/**
 * Scraper Service - API version
 * Extracts MT5 account count from NairaTrader API
 * Read-only - never modifies data on the target site
 */

namespace NAPM\App\Services;

use NAPM\Core\App;
use NAPM\Core\Database;
use NAPM\Core\Logger;

class ScraperService
{
    private App $app;
    private Logger $logger;
    private string $apiUrl;
    private int $connectionTimeout;
    private int $requestTimeout;

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->logger = $app->logger();
        $this->apiUrl = (string) $app->config('monitoring.nairatrader_api_url', 'https://nairatrader.ng/api/v1/admin/mirror/mt5-pool-available-counts?include_inactive=1');
        $this->connectionTimeout = (int) $app->config('monitoring.connection_timeout_seconds', 15);
        $this->requestTimeout = (int) $app->config('monitoring.request_timeout_seconds', 30);
    }

    public function validateSession(): bool
    {
        $configToken = $this->app->config('monitoring.api_token', '');
        if (!empty($configToken)) {
            $result = $this->callApi($configToken);
            return $result['success'];
        }

        $db = $this->app->db();
        $stmt = $db->prepare('SELECT id, encrypted_cookie_data, expires_at FROM monitor_sessions WHERE is_valid = 1 ORDER BY updated_at DESC LIMIT 1');
        $stmt->execute();
        $session = $stmt->fetch();
        if (!$session) {
            $this->logger->warning('No valid NairaTrader session found');
            return false;
        }
        if ($session['expires_at'] && new \DateTimeImmutable($session['expires_at']) < new \DateTimeImmutable()) {
            $db->prepare('UPDATE monitor_sessions SET is_valid = 0 WHERE id = ?')->execute([$session['id']]);
            $this->logger->warning('NairaTrader session expired');
            return false;
        }
        try {
            $token = $this->app->encryption()->decrypt($session['encrypted_cookie_data']);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to decrypt session token', ['error' => $e->getMessage()]);
            $db->prepare('UPDATE monitor_sessions SET is_valid = 0 WHERE id = ?')->execute([$session['id']]);
            return false;
        }
        $result = $this->callApi($token);
        if (!$result['success']) {
            $db->prepare('UPDATE monitor_sessions SET is_valid = 0 WHERE id = ?')->execute([$session['id']]);
            $this->logger->warning('Session validation failed: ' . $result['error']);
            return false;
        }
        $db->prepare('UPDATE monitor_sessions SET last_used_at = NOW() WHERE id = ?')->execute([$session['id']]);
        return true;
    }

    public function extractAccountCount(): array
    {
        $db = $this->app->db();
        $previousSnapshot = $this->getLastSnapshot($db);
        $previousCount = $previousSnapshot['count'];

        $configToken = $this->app->config('monitoring.api_token', '');
        if (!empty($configToken)) {
            $result = $this->callApi($configToken);
            if ($result['success']) {
                $count = $result['count'];
                $this->logger->info('Account count extracted from config token', [
                    'count' => $count,
                    'previous_count' => $previousCount,
                    'source' => 'config_token',
                ]);
                return [
                    'status' => 'success',
                    'count' => $count,
                    'previous_count' => $previousCount,
                    'plan_counts' => $result['plan_counts'],
                    'previous_plan_counts' => $previousSnapshot['plan_counts'],
                    'source_url' => $this->apiUrl,
                ];
            }
            return ['status' => 'failed', 'error' => 'Config token API request failed: ' . $result['error'], 'count' => null, 'previous_count' => $previousCount];
        }

        $stmt = $db->prepare('SELECT id, encrypted_cookie_data FROM monitor_sessions WHERE is_valid = 1 ORDER BY updated_at DESC LIMIT 1');
        $stmt->execute();
        $session = $stmt->fetch();
        if (!$session) {
            return ['status' => 'failed', 'error' => 'No valid NairaTrader session available and no config token', 'count' => null, 'previous_count' => $previousCount];
        }

        $attempts = max(1, (int) $this->app->config('monitoring.extraction_retry_attempts', 2));
        try {
            $token = $this->app->encryption()->decrypt($session['encrypted_cookie_data']);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to decrypt session token', ['error' => $e->getMessage()]);
            return ['status' => 'failed', 'error' => 'Failed to decrypt session token', 'count' => null, 'previous_count' => $previousCount];
        }

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $result = $this->callApi($token);
            if (!$result['success']) {
                if ($attempt < $attempts) {
                    sleep(2);
                    continue;
                }
                return ['status' => 'failed', 'error' => 'API request failed: ' . $result['error'], 'count' => null, 'previous_count' => $previousCount];
            }
            $count = $result['count'];
            if ($count < 0) {
                return ['status' => 'failed', 'error' => 'Invalid negative account count', 'count' => null, 'previous_count' => $previousCount];
            }
            $this->logger->info('Account count extracted', [
                'count' => $count,
                'previous_count' => $previousCount,
                'attempt' => $attempt,
            ]);
            return [
                'status' => 'success',
                'count' => $count,
                'previous_count' => $previousCount,
                'plan_counts' => $result['plan_counts'],
                'previous_plan_counts' => $previousSnapshot['plan_counts'],
                'source_url' => $this->apiUrl,
            ];
        }

        return ['status' => 'failed', 'error' => 'Extraction failed after all retries', 'count' => null, 'previous_count' => $previousCount];
    }

    public function saveSnapshot(int $count, ?int $previousCount = null, array $planCounts = []): int
    {
        $db = $this->app->db();
        $planCountsJson = $planCounts === [] ? null : json_encode($planCounts, JSON_THROW_ON_ERROR);
        $db->prepare("INSERT INTO account_snapshots (available_accounts, previous_accounts, plan_counts_json, source_url, extraction_status, checked_at) VALUES (?, ?, ?, ?, 'success', NOW())")
            ->execute([$count, $previousCount, $planCountsJson, $this->apiUrl]);
        return (int) $db->lastInsertId();
    }

    public function fetchPlanCounts(): array
    {
        $configToken = $this->app->config('monitoring.api_token', '');
        if (!empty($configToken)) {
            return $this->callApi($configToken);
        }

        $db = $this->app->db();
        $stmt = $db->prepare('SELECT encrypted_cookie_data, expires_at FROM monitor_sessions WHERE is_valid = 1 ORDER BY updated_at DESC LIMIT 1');
        $stmt->execute();
        $session = $stmt->fetch();
        if (!$session) {
            return ['success' => false, 'error' => 'No valid NairaTrader API token is configured'];
        }
        if ($session['expires_at'] && new \DateTimeImmutable($session['expires_at']) < new \DateTimeImmutable()) {
            return ['success' => false, 'error' => 'The NairaTrader API token has expired'];
        }

        try {
            $token = $this->app->encryption()->decrypt($session['encrypted_cookie_data']);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to decrypt session token', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Failed to decrypt the saved NairaTrader API token'];
        }

        return $this->callApi($token);
    }

    private function callApi(string $token): array
    {
        try {
            $url = parse_url($this->apiUrl);
            if (
                !is_array($url)
                || strtolower((string) ($url['scheme'] ?? '')) !== 'https'
                || strtolower((string) ($url['host'] ?? '')) !== 'nairatrader.ng'
            ) {
                return ['success' => false, 'error' => 'Invalid NairaTrader API endpoint configuration'];
            }

            $ch = curl_init($this->apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_CONNECTTIMEOUT => $this->connectionTimeout,
                CURLOPT_TIMEOUT => $this->requestTimeout,
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

            if ($response === false || $httpCode !== 200) {
                return [
                    'success' => false,
                    'error' => 'HTTP ' . $httpCode . ($curlError ? ' - ' . $curlError : ''),
                ];
            }

            $data = json_decode($response, true);
            if ($data === null) {
                return [
                    'success' => false,
                    'error' => 'Invalid JSON response',
                ];
            }

            $count = null;
            $planCounts = [];
            if (isset($data['available_accounts']) && is_numeric($data['available_accounts'])) {
                $count = (int) $data['available_accounts'];
            } elseif (isset($data['count']) && is_numeric($data['count'])) {
                $count = (int) $data['count'];
            } elseif (isset($data['left']) && is_numeric($data['left'])) {
                $count = (int) $data['left'];
            } elseif (isset($data['by_plan_id']) && is_array($data['by_plan_id'])) {
                foreach (NairaTraderPlans::catalog() as $planId => $_plan) {
                    if (!array_key_exists((string) $planId, $data['by_plan_id'])
                        || !is_numeric($data['by_plan_id'][(string) $planId])) {
                        return [
                            'success' => false,
                            'error' => 'NairaTrader API response is missing a required plan count',
                        ];
                    }
                    $planCounts[$planId] = (int) $data['by_plan_id'][(string) $planId];
                }
                $count = array_sum($planCounts);
            } elseif (isset($data['data']) && is_array($data['data'])) {
                foreach ($data['data'] as $item) {
                    if (is_array($item) && isset($item['available_accounts']) && is_numeric($item['available_accounts'])) {
                        $count = (int) $item['available_accounts'];
                        break;
                    }
                    if (is_array($item) && isset($item['left']) && is_numeric($item['left'])) {
                        $count = (int) $item['left'];
                        break;
                    }
                    if (is_array($item) && isset($item['count']) && is_numeric($item['count'])) {
                        $count = (int) $item['count'];
                        break;
                    }
                }
            }
            if ($count === null) {
                return [
                    'success' => false,
                    'error' => 'Could not find account count in API response',
                ];
            }

            return [
                'success' => true,
                'count' => $count,
                'plan_counts' => $planCounts,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function getLastSnapshot(Database $db): array
    {
        $stmt = $db->query("SELECT available_accounts, plan_counts_json FROM account_snapshots WHERE extraction_status = 'success' ORDER BY checked_at DESC LIMIT 1");
        $snapshot = $stmt->fetch();
        if (!$snapshot) {
            return ['count' => null, 'plan_counts' => null];
        }

        $planCounts = $snapshot['plan_counts_json'] !== null
            ? json_decode($snapshot['plan_counts_json'], true)
            : null;

        return [
            'count' => (int) $snapshot['available_accounts'],
            'plan_counts' => is_array($planCounts) ? $planCounts : null,
        ];
    }

    public function saveFailedSnapshot(string $errorMessage): int
    {
        $db = $this->app->db();
        $db->prepare("INSERT INTO account_snapshots (available_accounts, previous_accounts, source_url, extraction_status, error_message, checked_at) VALUES (0, NULL, ?, 'failed', ?, NOW())")->execute([$this->apiUrl, $errorMessage]);
        return (int) $db->lastInsertId();
    }
}