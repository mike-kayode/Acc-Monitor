<?php

namespace NAPM\App\Services;

use NAPM\Core\App;

class ThresholdEngine
{
    public function __construct(private App $app)
    {
    }

    public function evaluate(int $currentCount, ?int $previousCount): array
    {
        $db = $this->app->db();
        $settings = [];
        $stmt = $db->query(
            "SELECT setting_key, setting_value FROM monitor_settings WHERE setting_key IN ('warning_threshold', 'critical_threshold', 'emergency_threshold', 'exhaustion_threshold', 'reminder_interval_minutes')"
        );
        foreach ($stmt->fetchAll() as $row) {
            $settings[$row['setting_key']] = (int) $row['setting_value'];
        }

        $thresholds = [
            'warning' => $settings['warning_threshold'] ?? 200,
            'critical' => $settings['critical_threshold'] ?? 100,
            'emergency' => $settings['emergency_threshold'] ?? 50,
            'exhaustion' => $settings['exhaustion_threshold'] ?? 0,
        ];
        $activeAlert = null;
        foreach (['exhaustion', 'emergency', 'critical', 'warning'] as $name) {
            if ($currentCount <= $thresholds[$name]) {
                $activeAlert = $name;
                break;
            }
        }

        $alerts = [];
        $activeStates = $db->query('SELECT threshold_name, severity FROM alert_states WHERE is_active = 1')->fetchAll();
        foreach ($activeStates as $state) {
            $name = $state['threshold_name'];
            if (!isset($thresholds[$name]) || $currentCount <= $thresholds[$name]) {
                continue;
            }

            $message = "MT5 account availability recovered above the {$name} threshold. Remaining accounts: {$currentCount}.";
            $alerts[] = $this->recordAlert('recovery', 'info', $currentCount, $thresholds[$name], $previousCount, $message);
            $db->prepare('UPDATE alert_states SET is_active = 0, recovered_at = NOW(), updated_at = NOW() WHERE threshold_name = ?')
                ->execute([$name]);
        }

        if ($activeAlert === null) {
            return $alerts;
        }

        $stmt = $db->prepare('SELECT * FROM alert_states WHERE threshold_name = ? LIMIT 1');
        $stmt->execute([$activeAlert]);
        $state = $stmt->fetch();
        $reminderMinutes = max(1, $settings['reminder_interval_minutes'] ?? 60);
        $isReminder = $state && $state['is_active']
            && $state['last_notified_at']
            && (time() - strtotime($state['last_notified_at'])) >= $reminderMinutes * 60;

        if ($state && $state['is_active'] && !$isReminder) {
            return $alerts;
        }

        $severity = match ($activeAlert) {
            'warning' => 'warning',
            'critical' => 'critical',
            default => 'emergency',
        };
        $message = sprintf(
            "NairaTrader MT5 pool alert (%s): %d accounts remain; threshold is %d.",
            strtoupper($activeAlert),
            $currentCount,
            $thresholds[$activeAlert]
        );
        $alerts[] = $this->recordAlert($activeAlert, $severity, $currentCount, $thresholds[$activeAlert], $previousCount, $message);

        $db->prepare(
            'INSERT INTO alert_states (threshold_name, severity, is_active, first_triggered_at, last_triggered_at, last_notified_at, notification_count, updated_at) VALUES (?, ?, 1, NOW(), NOW(), NOW(), 1, NOW()) ON DUPLICATE KEY UPDATE severity = VALUES(severity), is_active = 1, last_triggered_at = NOW(), last_notified_at = NOW(), notification_count = notification_count + 1, recovered_at = NULL, updated_at = NOW()'
        )->execute([$activeAlert, $severity]);

        return $alerts;
    }

    public function evaluatePlans(array $currentCounts, ?array $previousCounts): array
    {
        $db = $this->app->db();
        $alerts = [];
        foreach (NairaTraderPlans::catalog() as $planId => $plan) {
            if (!array_key_exists($planId, $currentCounts) || !is_numeric($currentCounts[$planId])) {
                continue;
            }

            $thresholdName = 'plan_' . $planId;
            $currentCount = (int) $currentCounts[$planId];
            $threshold = (int) $plan['minimum'];
            $stmt = $db->prepare('SELECT * FROM alert_states WHERE threshold_name = ? LIMIT 1');
            $stmt->execute([$thresholdName]);
            $state = $stmt->fetch();
            $isBelowMinimum = $currentCount <= $threshold;
            $previousCount = isset($previousCounts[$planId]) ? (int) $previousCounts[$planId] : null;

            if ($state && (int) $state['is_active'] === 1 && !$isBelowMinimum) {
                $message = $plan['label'] . ' recovered — ' . number_format($currentCount)
                    . ' left (minimum ' . number_format($threshold) . ').';
                $alerts[] = $this->recordAlert('recovery', 'info', $currentCount, $threshold, $previousCount, $message);
                $db->prepare('UPDATE alert_states SET is_active = 0, recovered_at = NOW(), updated_at = NOW() WHERE threshold_name = ?')
                    ->execute([$thresholdName]);
                continue;
            }

            if (!$isBelowMinimum || ($state && (int) $state['is_active'] === 1)) {
                continue;
            }

            $message = 'Low account balance: ' . $plan['label'] . ' — ' . number_format($currentCount)
                . ' left (minimum ' . number_format($threshold) . ').';
            $alerts[] = $this->recordAlert('emergency', 'emergency', $currentCount, $threshold, $previousCount, $message);
            $db->prepare(
                'INSERT INTO alert_states (threshold_name, severity, is_active, first_triggered_at, last_triggered_at, last_notified_at, notification_count, updated_at) VALUES (?, ?, 1, NOW(), NOW(), NOW(), 1, NOW()) ON DUPLICATE KEY UPDATE severity = VALUES(severity), is_active = 1, last_triggered_at = NOW(), last_notified_at = NOW(), notification_count = notification_count + 1, recovered_at = NULL, updated_at = NOW()'
            )->execute([$thresholdName, 'emergency']);
        }

        return $alerts;
    }

    private function recordAlert(
        string $type,
        string $severity,
        int $count,
        int $threshold,
        ?int $previousCount,
        string $message
    ): array {
        $eventId = \NAPM\Core\Security::generateEventId();
        $db = $this->app->db();
        $db->prepare(
            'INSERT INTO alert_history (event_id, alert_type, severity, available_accounts, threshold_value, previous_accounts, message) VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$eventId, $type, $severity, $count, $threshold, $previousCount, $message]);

        return [
            'event_id' => $eventId,
            'alert_type' => $type,
            'severity' => $severity,
            'available_accounts' => $count,
            'threshold_value' => $threshold,
            'message' => $message,
            'alert_id' => (int) $db->lastInsertId(),
        ];
    }
}
