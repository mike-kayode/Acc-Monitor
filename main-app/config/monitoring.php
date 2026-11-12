<?php
// Monitoring configuration
return [
    'enabled' => (bool) env('MONITOR_ENABLED', true),
    'interval_minutes' => (int) env('MONITOR_INTERVAL', 5),
    'warning_threshold' => (int) env('WARNING_THRESHOLD', 200),
    'critical_threshold' => (int) env('CRITICAL_THRESHOLD', 100),
    'emergency_threshold' => (int) env('EMERGENCY_THRESHOLD', 50),
    'exhaustion_threshold' => (int) env('EXHAUSTION_THRESHOLD', 0),
    'max_whatsapp_recipients' => (int) env('MAX_WHATSAPP_RECIPIENTS', 5),
    'whatsapp_enabled' => (bool) env('WHATSAPP_ENABLED', true),
    'telegram_enabled' => (bool) env('TELEGRAM_ENABLED', true),
    'reminder_interval_minutes' => (int) env('REMINDER_INTERVAL_MINUTES', 60),
    'connection_timeout_seconds' => (int) env('CONNECTION_TIMEOUT_SECONDS', 15),
    'request_timeout_seconds' => (int) env('REQUEST_TIMEOUT_SECONDS', 30),
    'extraction_retry_attempts' => (int) env('EXTRACTION_RETRY_ATTEMPTS', 2),
    'notification_retry_attempts' => (int) env('NOTIFICATION_RETRY_ATTEMPTS', 3),
    'duplicate_alert_prevention' => (bool) env('DUPLICATE_ALERT_PREVENTION', true),
    'recovery_notifications' => (bool) env('RECOVERY_NOTIFICATIONS', true),
    'nairatrader_url' => env('NAIRATRADER_URL', 'https://nairatrader.ng/admin/data-mirror?tab=mt5-pool-create'),
    'nairatrader_api_url' => env('NAIRATRADER_API_URL', 'https://nairatrader.ng/api/v1/admin/mirror/mt5-pool-available-counts?include_inactive=1'),
    'api_token' => env('NAIRATRADER_API_TOKEN', ''),
];