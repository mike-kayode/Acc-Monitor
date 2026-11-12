<?php
/**
 * Cron Controller - No exec() calls - Shared hosting compatible
 */

namespace NAPM\Controllers;

use NAPM\Core\App;
use NAPM\App\Services\MonitoringEngine;

class CronController
{
    private App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function run(): void
    {
        $isCLI = php_sapi_name() === 'cli';
        $isInternal = isset($_SERVER['HTTP_X_WORKER_SECRET']);

        if (!$isCLI && !$isInternal) {
            http_response_code(403);
            exit('Forbidden');
        }

        $executionId = uniqid('exec_', true);
        $startTime = microtime(true);

        $this->app->logger()->info('Monitoring execution started', ['execution_id' => $executionId]);

        try {
            $db = $this->app->db();
            $stmt = $db->prepare("SELECT setting_value FROM monitor_settings WHERE setting_key = 'monitoring_enabled'");
            $stmt->execute();
            $enabled = $stmt->fetchColumn();
            if ($enabled !== 'true') {
                $this->app->logger()->info('Monitoring is disabled', ['execution_id' => $executionId]);
                $this->releaseLock();
                if (!$isCLI) {
                    $this->app->jsonResponse(['success' => true, 'message' => 'Monitoring disabled']);
                }
                echo "Monitoring is disabled\n";
                return;
            }

            $engine = new MonitoringEngine($this->app);
            $results = $engine->run();

            if ($results['success']) {
                $this->updateSystemHealth('monitoring', 'healthy', 'Monitoring check completed successfully');
                $executionTime = (int) ((microtime(true) - $startTime) * 1000);
                $this->app->logger()->info('Monitoring execution completed', [
                    'execution_id' => $executionId,
                    'current_count' => $results['current_count'] ?? null,
                    'previous_count' => $results['previous_count'] ?? null,
                    'alerts_generated' => $results['alerts_generated'] ?? 0,
                    'execution_time_ms' => $executionTime,
                ]);

                if ($isCLI) {
                    echo "Monitoring completed. Count: " . ($results['current_count'] ?? 'N/A') . ", Alerts: " . ($results['alerts_generated'] ?? 0) . "\n";
                } else {
                    $this->app->jsonResponse([
                        'success' => true,
                        'current_count' => $results['current_count'],
                        'previous_count' => $results['previous_count'],
                        'alerts_generated' => $results['alerts_generated'],
                        'execution_time_ms' => $executionTime,
                    ]);
                }
            } else {
                $this->updateSystemHealth('monitoring', 'unhealthy', 'Monitoring check failed: ' . ($results['error'] ?? 'Unknown error'));
                $this->app->logger()->error('Monitoring execution failed', [
                    'execution_id' => $executionId,
                    'error' => $results['error'] ?? 'Unknown error',
                ]);

                if ($isCLI) {
                    echo "Error: " . ($results['error'] ?? 'Unknown error') . "\n";
                } else {
                    $this->app->jsonResponse(['success' => false, 'error' => $results['error'] ?? 'Unknown error'], 500);
                }
            }

            $this->releaseLock();

        } catch (\Throwable $e) {
            $this->app->logger()->critical('Monitoring execution failed', [
                'execution_id' => $executionId,
                'error' => $e->getMessage(),
            ]);
            $this->updateSystemHealth('monitoring', 'unhealthy', 'Monitoring check failed: ' . $e->getMessage());
            $this->releaseLock();

            if (php_sapi_name() === 'cli') {
                echo "Error: " . $e->getMessage() . "\n";
            } else {
                $this->app->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
            }
        }
    }

    private function acquireLock(string $executionId): bool
    {
        $db = $this->app->db();
        $expiresAt = date('Y-m-d H:i:s', time() + 300);

        try {
            $db->beginTransaction();
            $result = $db->prepare("INSERT INTO execution_locks (lock_name, locked_by, expires_at) VALUES (?, ?, ?)")
                ->execute(['monitor_cron', $executionId, $expiresAt]);
            $db->commit();
            return $result;
        } catch (\Throwable $e) {
            $db->rollBack();
            $stmt = $db->prepare("SELECT * FROM execution_locks WHERE lock_name = 'monitor_cron' AND expires_at < NOW()");
            $stmt->execute();
            $staleLock = $stmt->fetch();

            if ($staleLock) {
                $db->prepare("DELETE FROM execution_locks WHERE lock_name = 'monitor_cron'")->execute();
                return $this->acquireLock($executionId);
            }
            return false;
        }
    }

    private function releaseLock(): void
    {
        $db = $this->app->db();
        $db->prepare("DELETE FROM execution_locks WHERE lock_name = 'monitor_cron'")->execute();
    }

    private function updateSystemHealth(string $component, string $status, string $message): void
    {
        $db = $this->app->db();
        $db->prepare("INSERT INTO system_health (component, status, message, last_check_at, updated_at) VALUES (?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE status = VALUES(status), message = VALUES(message), last_check_at = NOW(), updated_at = NOW()")
            ->execute([$component, $status, $message]);
    }
}