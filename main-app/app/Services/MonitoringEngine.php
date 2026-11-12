<?php
/**
 * Monitoring Engine
 * Orchestrates the monitoring workflow
 */

namespace NAPM\App\Services;

use NAPM\Core\App;

class MonitoringEngine
{
    private App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function run(): array
    {
        $results = [
            'success' => false,
            'steps' => [],
        ];

        $db = $this->app->db();

        // Step 1: Check monitoring enabled
        $stmt = $db->prepare("SELECT setting_value FROM monitor_settings WHERE setting_key = 'monitoring_enabled'");
        $stmt->execute();
        $enabled = $stmt->fetchColumn();
        $results['steps'][] = ['step' => 'check_enabled', 'enabled' => $enabled === 'true'];

        if ($enabled !== 'true') {
            $results['message'] = 'Monitoring is disabled';
            return $results;
        }

        // Step 2: Validate session
        $scraper = new ScraperService($this->app);
        $sessionValid = $scraper->validateSession();
        $results['steps'][] = ['step' => 'validate_session', 'valid' => $sessionValid];

        if (!$sessionValid) {
            $results['error'] = 'Session expired';
            return $results;
        }

        // Step 3: Extract account count
        $extractionResult = $scraper->extractAccountCount();
        $results['steps'][] = ['step' => 'extract_count', 'status' => $extractionResult['status']];

        if ($extractionResult['status'] !== 'success') {
            $scraper->saveFailedSnapshot($extractionResult['error'] ?? 'Unknown error');
            $results['error'] = $extractionResult['error'] ?? 'Extraction failed';
            return $results;
        }

        $currentCount = (int) $extractionResult['count'];
        $previousCount = $extractionResult['previous_count'] ?? null;

        // Step 4: Save snapshot
        $planCounts = $extractionResult['plan_counts'] ?? [];
        $snapshotId = $scraper->saveSnapshot($currentCount, $previousCount, $planCounts);
        $results['steps'][] = ['step' => 'save_snapshot', 'snapshot_id' => $snapshotId];

        // Step 5: Evaluate thresholds
        $thresholdEngine = new ThresholdEngine($this->app);
        $alerts = $thresholdEngine->evaluate($currentCount, $previousCount);
        if ($planCounts !== []) {
            $alerts = array_merge(
                $alerts,
                $thresholdEngine->evaluatePlans(
                    $planCounts,
                    $extractionResult['previous_plan_counts'] ?? null
                )
            );
        }
        $results['steps'][] = ['step' => 'evaluate_thresholds', 'alerts_count' => count($alerts)];

        // Step 6: Dispatch notifications
        if (!empty($alerts)) {
            $dispatcher = new NotificationDispatcher($this->app);
            $dispatcher->dispatch($alerts);
            $results['steps'][] = ['step' => 'dispatch_notifications', 'alerts_count' => count($alerts)];
        }

        $results['success'] = true;
        $results['current_count'] = $currentCount;
        $results['previous_count'] = $previousCount;
        $results['plan_counts'] = $planCounts;
        $results['alerts_generated'] = count($alerts);

        return $results;
    }
}