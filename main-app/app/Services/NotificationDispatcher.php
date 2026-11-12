<?php

namespace NAPM\App\Services;

use NAPM\Core\App;
use NAPM\Core\Database;
use NAPM\App\Services\TelegramRecipients;

class NotificationDispatcher
{
    public function __construct(private App $app)
    {
    }

    public function dispatch(array $alerts): void
    {
        $db = $this->app->db();
        $enabled = $db->query(
            "SELECT setting_key, setting_value FROM monitor_settings WHERE setting_key IN ('whatsapp_enabled', 'telegram_enabled')"
        )->fetchAll();
        $settings = array_column($enabled, 'setting_value', 'setting_key');
        $whatsAppEnabled = ($settings['whatsapp_enabled'] ?? 'true') === 'true';
        $telegramEnabled = ($settings['telegram_enabled'] ?? 'false') === 'true';

        foreach ($alerts as $alert) {
            $priority = match ($alert['severity'] ?? '') {
                'emergency' => 'critical',
                'critical' => 'high',
                default => 'normal',
            };
            if ($whatsAppEnabled) {
                $recipients = $db->query(
                    'SELECT phone_number FROM whatsapp_recipients WHERE is_enabled = 1 ORDER BY id ASC LIMIT 5'
                )->fetchAll(\PDO::FETCH_COLUMN);
                foreach ($recipients as $phone) {
                    $this->queue($db, $alert, 'whatsapp', (string) $phone, $priority);
                }
            }

            if ($telegramEnabled) {
                $stmt = $db->query(
                    'SELECT bot_token, chat_id, additional_chat_ids FROM telegram_settings WHERE is_enabled = 1 ORDER BY id DESC LIMIT 1'
                );
                $telegram = $stmt->fetch();
                if ($telegram && $telegram['bot_token']) {
                    try {
                        $recipients = TelegramRecipients::parse(
                            (string) ($telegram['chat_id'] ?? ''),
                            (string) ($telegram['additional_chat_ids'] ?? '')
                        );
                    } catch (\InvalidArgumentException $e) {
                        $this->app->logger()->error('Telegram notifications skipped due to invalid recipient configuration', ['error' => $e->getMessage()]);
                        continue;
                    }
                    foreach ($recipients as $recipient) {
                        $this->sendTelegram($db, $alert, $telegram['bot_token'], $recipient);
                    }
                }
            }
        }
    }

    private function queue(Database $db, array $alert, string $channel, string $recipient, string $priority): void
    {
        $stmt = $db->prepare(
            "SELECT id FROM notification_queue WHERE event_id = ? AND channel = ? AND recipient = ? AND status IN ('pending', 'processing', 'sent', 'delivered') LIMIT 1"
        );
        $stmt->execute([$alert['event_id'], $channel, $recipient]);
        if ($stmt->fetchColumn()) {
            return;
        }

        $db->prepare(
            "INSERT INTO notification_queue (event_id, alert_id, channel, recipient, message, priority, status, expires_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', DATE_ADD(NOW(), INTERVAL 1 DAY))"
        )->execute([
            $alert['event_id'],
            $alert['alert_id'] ?? null,
            $channel,
            $recipient,
            $alert['message'],
            $priority,
        ]);
    }

    private function sendTelegram(Database $db, array $alert, string $token, string $chatId): void
    {
        $ch = curl_init('https://api.telegram.org/bot' . rawurlencode($token) . '/sendMessage');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode(
                ['chat_id' => $chatId, 'text' => $alert['message']],
                JSON_THROW_ON_ERROR
            ),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
        ]);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $responseData = $response === false ? null : json_decode($response, true);
        $success = $status >= 200 && $status < 300 && is_array($responseData) && ($responseData['ok'] ?? false) === true;
        $db->prepare(
            'INSERT INTO notification_history (alert_id, channel, recipient, message, delivery_status, error_message, attempt_count, created_at, delivered_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), IF(? = 1, NOW(), NULL))'
        )->execute([
            $alert['alert_id'] ?? null,
            'telegram',
            $chatId,
            $alert['message'],
            $success ? 'sent' : 'failed',
            $success ? null : ($error ?: (is_array($responseData) ? 'Telegram API rejected the message' : 'Telegram returned HTTP ' . $status)),
            $success ? 1 : 0,
        ]);
    }
}
