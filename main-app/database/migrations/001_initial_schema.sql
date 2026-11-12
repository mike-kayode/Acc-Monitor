SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS admin_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'operator') NOT NULL DEFAULT 'operator',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at TIMESTAMP NULL DEFAULT NULL,
    failed_login_attempts INT UNSIGNED NOT NULL DEFAULT 0,
    locked_until TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS monitor_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    setting_type ENUM('boolean', 'integer', 'string', 'float') NOT NULL DEFAULT 'string',
    is_editable TINYINT(1) NOT NULL DEFAULT 1,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS monitor_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_name VARCHAR(100) NOT NULL,
    encrypted_cookie_data TEXT NOT NULL,
    user_agent VARCHAR(255) NULL,
    ip_address VARCHAR(45) NULL,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    is_valid TINYINT(1) NOT NULL DEFAULT 1,
    last_used_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_monitor_session_valid (is_valid, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS account_snapshots (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    available_accounts INT NOT NULL,
    previous_accounts INT NULL,
    plan_counts_json LONGTEXT NULL,
    source_url VARCHAR(500) NOT NULL,
    extraction_status ENUM('success', 'failed', 'partial') NOT NULL DEFAULT 'success',
    error_message TEXT NULL,
    checked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_snapshot_count (available_accounts),
    INDEX idx_snapshot_checked (checked_at),
    INDEX idx_snapshot_status (extraction_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alert_thresholds (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    threshold_name VARCHAR(50) NOT NULL UNIQUE,
    threshold_value INT NOT NULL,
    severity ENUM('warning', 'critical', 'emergency', 'exhaustion') NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alert_states (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    threshold_name VARCHAR(50) NOT NULL UNIQUE,
    severity ENUM('info', 'warning', 'critical', 'emergency') NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    first_triggered_at TIMESTAMP NULL DEFAULT NULL,
    last_triggered_at TIMESTAMP NULL DEFAULT NULL,
    last_notified_at TIMESTAMP NULL DEFAULT NULL,
    notification_count INT UNSIGNED NOT NULL DEFAULT 0,
    recovered_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_alert_state_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alert_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id VARCHAR(100) NOT NULL UNIQUE,
    alert_type ENUM('warning', 'critical', 'emergency', 'exhaustion', 'recovery', 'session_expired', 'monitoring_failure', 'whatsapp_disconnected', 'system_health') NOT NULL,
    severity ENUM('info', 'warning', 'critical', 'emergency') NOT NULL,
    available_accounts INT NULL,
    threshold_value INT NULL,
    previous_accounts INT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_alert_created (created_at),
    INDEX idx_alert_severity (severity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_name VARCHAR(100) NOT NULL,
    connection_status ENUM('disconnected', 'connecting', 'connected', 'authenticated', 'expired') NOT NULL DEFAULT 'disconnected',
    qr_code_data TEXT NULL,
    qr_code_expires_at TIMESTAMP NULL DEFAULT NULL,
    phone_number VARCHAR(20) NULL,
    profile_name VARCHAR(100) NULL,
    last_heartbeat_at TIMESTAMP NULL DEFAULT NULL,
    disconnected_at TIMESTAMP NULL DEFAULT NULL,
    reconnect_attempts INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_whatsapp_status (connection_status, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_recipients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipient_name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20) NOT NULL UNIQUE,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    last_message_at TIMESTAMP NULL DEFAULT NULL,
    last_delivery_status ENUM('pending', 'sent', 'delivered', 'failed', 'error') NOT NULL DEFAULT 'pending',
    last_error_message TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_recipient_enabled (is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS telegram_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bot_token VARCHAR(200) NULL,
    chat_id VARCHAR(50) NULL,
    additional_chat_ids TEXT NULL DEFAULT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 0,
    connection_status ENUM('unknown', 'connected', 'disconnected', 'error') NOT NULL DEFAULT 'unknown',
    last_tested_at TIMESTAMP NULL DEFAULT NULL,
    last_test_result ENUM('success', 'failed') NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_telegram_enabled (is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id VARCHAR(100) NOT NULL,
    alert_id INT UNSIGNED NULL,
    channel ENUM('whatsapp', 'telegram') NOT NULL,
    recipient VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    priority ENUM('normal', 'high', 'critical') NOT NULL DEFAULT 'normal',
    status ENUM('pending', 'processing', 'sent', 'delivered', 'failed', 'error') NOT NULL DEFAULT 'pending',
    retry_count INT UNSIGNED NOT NULL DEFAULT 0,
    provider_message_id VARCHAR(255) NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    delivered_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_queue_event (event_id),
    INDEX idx_queue_status (status, created_at),
    INDEX idx_queue_recipient (recipient),
    CONSTRAINT fk_notification_alert FOREIGN KEY (alert_id) REFERENCES alert_history(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    alert_id INT UNSIGNED NULL,
    channel ENUM('whatsapp', 'telegram') NOT NULL,
    recipient VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    delivery_status ENUM('pending', 'sent', 'delivered', 'failed', 'error') NOT NULL,
    provider_message_id VARCHAR(255) NULL,
    error_message TEXT NULL,
    attempt_count INT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    delivered_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_notification_history_alert (alert_id),
    INDEX idx_notification_history_created (created_at),
    CONSTRAINT fk_notification_history_alert FOREIGN KEY (alert_id) REFERENCES alert_history(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS monitor_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    execution_id VARCHAR(100) NOT NULL,
    log_level ENUM('debug', 'info', 'warning', 'error', 'critical') NOT NULL DEFAULT 'info',
    message TEXT NOT NULL,
    context JSON NULL,
    execution_time_ms INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_monitor_log_execution (execution_id),
    INDEX idx_monitor_log_level (log_level),
    INDEX idx_monitor_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_health (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    component VARCHAR(50) NOT NULL UNIQUE,
    status ENUM('healthy', 'degraded', 'unhealthy', 'unknown') NOT NULL DEFAULT 'unknown',
    message VARCHAR(255) NULL,
    last_check_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    response_time_ms INT UNSIGNED NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_system_health_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS execution_locks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lock_name VARCHAR(100) NOT NULL UNIQUE,
    locked_by VARCHAR(100) NULL,
    locked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_execution_lock_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO monitor_settings (setting_key, setting_value, setting_type, is_editable, description) VALUES
('monitoring_enabled', 'false', 'boolean', 1, 'Enable periodic monitoring'),
('monitoring_interval_minutes', '5', 'integer', 1, 'Monitoring interval'),
('warning_threshold', '200', 'integer', 1, 'Warning count'),
('critical_threshold', '100', 'integer', 1, 'Critical count'),
('emergency_threshold', '50', 'integer', 1, 'Emergency count'),
('exhaustion_threshold', '0', 'integer', 1, 'Exhaustion count'),
('max_whatsapp_recipients', '5', 'integer', 0, 'Maximum WhatsApp recipients'),
('whatsapp_enabled', 'false', 'boolean', 1, 'Enable WhatsApp notifications'),
('telegram_enabled', 'false', 'boolean', 1, 'Enable Telegram notifications'),
('reminder_interval_minutes', '60', 'integer', 1, 'Reminder interval'),
('connection_timeout_seconds', '15', 'integer', 1, 'Connection timeout'),
('request_timeout_seconds', '30', 'integer', 1, 'Request timeout'),
('extraction_retry_attempts', '2', 'integer', 1, 'Extraction retry count'),
('notification_retry_attempts', '3', 'integer', 1, 'Notification retry count'),
('duplicate_alert_prevention', 'true', 'boolean', 1, 'Prevent duplicate notifications'),
('recovery_notifications', 'true', 'boolean', 1, 'Send recovery notifications');

INSERT IGNORE INTO alert_thresholds (threshold_name, threshold_value, severity) VALUES
('warning', 200, 'warning'),
('critical', 100, 'critical'),
('emergency', 50, 'emergency'),
('exhaustion', 0, 'exhaustion');

INSERT IGNORE INTO whatsapp_sessions (session_name, connection_status) VALUES ('primary', 'disconnected');
INSERT IGNORE INTO telegram_settings (id, is_enabled, connection_status) VALUES (1, 0, 'unknown');
