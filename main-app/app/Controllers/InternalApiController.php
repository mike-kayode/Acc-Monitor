<?php

namespace NAPM\Controllers;

use NAPM\Core\App;
use NAPM\Core\Security;

class InternalApiController
{
    private array $input = [];

    public function __construct(private App $app)
    {
        $this->authorizeRequest();
    }

    public function getQR(): void
    {
        $this->proxyWorker('GET', '/api/qr');
    }

    public function getStatus(): void
    {
        $this->proxyWorker('GET', '/api/status');
    }

    public function health(): void
    {
        try {
            $this->app->db()->query('SELECT 1');
            $this->app->jsonResponse(['success' => true, 'database' => 'healthy', 'timestamp' => date(DATE_ATOM)]);
        } catch (\Throwable $e) {
            $this->app->jsonResponse(['success' => false, 'database' => 'unhealthy'], 503);
        }
    }

    public function sendMessage(): void
    {
        $eventId = (string) ($this->input['event_id'] ?? Security::generateEventId());
        $message = trim((string) ($this->input['message'] ?? ''));
        $recipients = $this->input['recipients'] ?? [];
        if ($message === '' || !is_array($recipients) || count($recipients) > 5) {
            $this->app->jsonResponse(['success' => false, 'error' => 'Invalid notification payload'], 400);
        }

        $db = $this->app->db();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare('SELECT id FROM alert_history WHERE event_id = ? LIMIT 1');
            $stmt->execute([$eventId]);
            $alertId = $stmt->fetchColumn();
            if ($alertId === false) {
                $db->prepare(
                    "INSERT INTO alert_history (event_id, alert_type, severity, message) VALUES (?, 'system_health', 'info', ?)"
                )->execute([$eventId, $message]);
                $alertId = (int) $db->lastInsertId();
            }

            foreach ($recipients as $recipient) {
                if (!is_string($recipient) || !Security::validatePhoneNumber($recipient)) {
                    throw new \InvalidArgumentException('Invalid recipient number');
                }
                $stmt = $db->prepare(
                    "SELECT id FROM notification_queue WHERE event_id = ? AND channel = 'whatsapp' AND recipient = ? LIMIT 1"
                );
                $stmt->execute([$eventId, $recipient]);
                if ($stmt->fetchColumn() === false) {
                    $db->prepare(
                        "INSERT INTO notification_queue (event_id, alert_id, channel, recipient, message, priority, status, expires_at) VALUES (?, ?, 'whatsapp', ?, ?, 'normal', 'pending', DATE_ADD(NOW(), INTERVAL 1 DAY))"
                    )->execute([$eventId, $alertId, $recipient, $message]);
                }
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            $this->app->logger()->error('Could not queue internal notification', ['error' => $e->getMessage()]);
            $this->app->jsonResponse(['success' => false, 'error' => 'Could not queue notification'], 500);
        }

        $this->app->jsonResponse(['success' => true, 'event_id' => $eventId, 'queued' => count($recipients)]);
    }

    public function testMessage(): void
    {
        $this->input['recipients'] = [$this->input['phone'] ?? ''];
        $this->input['message'] = $this->input['message'] ?? 'NAPM WhatsApp test message';
        $this->input['event_id'] = Security::generateEventId('test');
        $this->sendMessage();
    }

    public function reconnect(): void
    {
        $this->app->db()->prepare(
            "UPDATE whatsapp_sessions SET connection_status = 'connecting', reconnect_attempts = reconnect_attempts + 1 WHERE connection_status <> 'connected'"
        )->execute();
        $this->app->jsonResponse(['success' => true, 'message' => 'Reconnect state updated']);
    }

    public function webhook(): void
    {
        $eventId = (string) ($this->input['event_id'] ?? '');
        $recipient = (string) ($this->input['recipient'] ?? '');
        $status = (string) ($this->input['status'] ?? '');
        if ($eventId === '' || $recipient === '' || !in_array($status, ['sent', 'delivered', 'failed', 'error'], true)) {
            $this->app->jsonResponse(['success' => false, 'error' => 'Invalid webhook payload'], 400);
        }

        $this->app->db()->prepare(
            'UPDATE notification_queue SET status = ?, provider_message_id = ?, error_message = ?, delivered_at = IF(? = \"delivered\", NOW(), delivered_at) WHERE event_id = ? AND recipient = ?'
        )->execute([
            $status,
            $this->input['message_id'] ?? null,
            $this->input['error'] ?? null,
            $status,
            $eventId,
            $recipient,
        ]);
        $this->app->jsonResponse(['success' => true]);
    }

    private function authorizeRequest(): void
    {
        $raw = file_get_contents('php://input') ?: '';
        $this->input = $raw === '' ? [] : (json_decode($raw, true) ?: []);
        $workerSecret = (string) ($_SERVER['HTTP_X_WORKER_SECRET'] ?? '');
        $expectedSecret = (string) \NAPM\Core\Env::get('WORKER_SECRET', '');
        $hmacSecret = (string) \NAPM\Core\Env::get('HMAC_SECRET', '');
        $timestamp = (int) ($_SERVER['HTTP_X_TIMESTAMP'] ?? 0);
        $nonce = (string) ($_SERVER['HTTP_X_NONCE'] ?? '');
        $signature = (string) ($_SERVER['HTTP_X_HMAC_SIGNATURE'] ?? '');

        if ($expectedSecret === '' || $hmacSecret === '' || $workerSecret === '' || !hash_equals($expectedSecret, $workerSecret)) {
            $this->app->jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        }
        if ($timestamp === 0 || abs(time() - $timestamp) > 300 || $nonce === '') {
            $this->app->jsonResponse(['success' => false, 'error' => 'Invalid request timestamp or nonce'], 401);
        }

        $signed = $raw === '' ? $timestamp . '.' . $nonce : $raw . '.' . $timestamp . '.' . $nonce;
        if (!Security::verifyHmac($signed, $signature, $hmacSecret)) {
            $this->app->jsonResponse(['success' => false, 'error' => 'Invalid request signature'], 401);
        }
    }

    private function proxyWorker(string $method, string $path): void
    {
        $workerUrl = rtrim((string) \NAPM\Core\Env::get('WHATSAPP_WORKER_URL', ''), '/');
        if ($workerUrl === '') {
            $this->app->jsonResponse(['success' => false, 'error' => 'WhatsApp worker is not configured'], 503);
        }

        $ch = curl_init($workerUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['X-Worker-Secret: ' . \NAPM\Core\Env::get('WORKER_SECRET', '')],
        ]);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($response === false || $status >= 400) {
            $this->app->jsonResponse(['success' => false, 'error' => 'WhatsApp worker request failed'], 502);
        }
        header('Content-Type: application/json');
        echo $response;
    }
}
