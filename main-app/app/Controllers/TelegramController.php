<?php
/**
 * Telegram Controller
 * Telegram bot configuration and testing - supports multiple recipients
 */

namespace NAPM\Controllers;

use NAPM\App\Services\TelegramRecipients;
use NAPM\App\Services\TelegramCommandService;
use NAPM\Core\App;
use NAPM\Core\Security;

class TelegramController
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
        $stmt = $db->prepare(
            'SELECT id, chat_id, additional_chat_ids, is_enabled, connection_status, last_tested_at, last_test_result, (bot_token IS NOT NULL AND bot_token <> \'\') AS token_configured FROM telegram_settings ORDER BY id ASC LIMIT 1'
        );
        $stmt->execute();
        $settings = $stmt->fetch();

        $data = [
            'settings' => $settings,
            'csrf_token' => Security::generateCsrfToken(),
        ];

        $this->render('telegram', $data);
    }

    public function save(): void
    {
        $db = $this->app->db();
        $stmt = $db->query('SELECT id, bot_token FROM telegram_settings ORDER BY id ASC LIMIT 1');
        $existing = $stmt->fetch();

        foreach (['bot_token', 'chat_id', 'additional_chat_ids'] as $field) {
            if (isset($_POST[$field]) && !is_string($_POST[$field])) {
                $this->app->jsonResponse(['success' => false, 'error' => 'Invalid Telegram settings payload.'], 400);
            }
        }

        $botToken = trim($_POST['bot_token'] ?? '');
        if ($botToken === '' && $existing) {
            $botToken = (string) $existing['bot_token'];
        }
        $chatId = trim($_POST['chat_id'] ?? '');
        $additionalInput = $_POST['additional_chat_ids'] ?? '';
        $enabled = isset($_POST['is_enabled']);

        if ($botToken !== '' && !preg_match('/^[0-9]{5,}:[A-Za-z0-9_-]{20,}$/', $botToken)) {
            $this->app->jsonResponse(['success' => false, 'error' => 'Enter a valid Telegram bot token.'], 400);
        }

        try {
            $recipients = TelegramRecipients::parse($chatId, $additionalInput);
        } catch (\InvalidArgumentException $e) {
            $this->app->jsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }

        if ($enabled && ($botToken === '' || $chatId === '' || count($recipients) < 1)) {
            $this->app->jsonResponse(['success' => false, 'error' => 'A bot token and primary chat ID are required to enable Telegram.'], 400);
        }

        $additionalIds = array_values(array_filter($recipients, static fn(string $id): bool => $id !== $chatId));
        $additionalIds = implode(',', $additionalIds);

        $db->beginTransaction();
        try {
            if ($existing) {
                $db->prepare(
                    'UPDATE telegram_settings SET bot_token = ?, chat_id = ?, additional_chat_ids = ?, is_enabled = ?, connection_status = ?, updated_at = NOW() WHERE id = ?'
                )->execute([
                    $botToken !== '' ? $botToken : null,
                    $chatId !== '' ? $chatId : null,
                    $additionalIds !== '' ? $additionalIds : null,
                    $enabled ? 1 : 0,
                    $enabled ? 'unknown' : 'disconnected',
                    $existing['id'],
                ]);
            } else {
                $db->prepare(
                    'INSERT INTO telegram_settings (bot_token, chat_id, additional_chat_ids, is_enabled, connection_status) VALUES (?, ?, ?, ?, ?)'
                )->execute([
                    $botToken !== '' ? $botToken : null,
                    $chatId !== '' ? $chatId : null,
                    $additionalIds !== '' ? $additionalIds : null,
                    $enabled ? 1 : 0,
                    $enabled ? 'unknown' : 'disconnected',
                ]);
            }
            $db->prepare("UPDATE monitor_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = 'telegram_enabled'")
                ->execute([$enabled ? 'true' : 'false']);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->getConnection()->inTransaction()) {
                $db->rollBack();
            }
            $this->app->logger()->error('Telegram settings update failed', ['error' => $e->getMessage()]);
            $this->app->jsonResponse(['success' => false, 'error' => 'Telegram settings could not be saved.'], 500);
        }

        $this->app->logger()->info('Telegram settings updated', ['recipient_count' => count($recipients), 'enabled' => $enabled]);
        $this->app->jsonResponse(['success' => true, 'message' => 'Telegram settings saved']);
    }

    public function test(): void
    {
        $db = $this->app->db();
        $stmt = $db->prepare('SELECT * FROM telegram_settings ORDER BY id ASC LIMIT 1');
        $stmt->execute();
        $settings = $stmt->fetch();

        if (!$settings || !$settings['bot_token']) {
            $this->app->jsonResponse(['success' => false, 'error' => 'Telegram bot token not configured'], 400);
        }

        try {
            $recipients = TelegramRecipients::parse(
                (string) ($settings['chat_id'] ?? ''),
                (string) ($settings['additional_chat_ids'] ?? '')
            );
        } catch (\InvalidArgumentException $e) {
            $this->app->jsonResponse(['success' => false, 'error' => 'Telegram recipient configuration is invalid.'], 400);
        }
        if ($recipients === []) {
            $this->app->jsonResponse(['success' => false, 'error' => 'Configure at least one Telegram chat ID.'], 400);
        }

        $message = "Test notification from NairaTrader Account Pool Monitor.\n\nTelegram integration is working correctly.";
        $results = [];

        foreach ($recipients as $recipient) {
            $results[] = $this->sendTelegramMessage($settings['bot_token'], $recipient, $message);
        }

        $allSuccess = !in_array(false, $results, true);
        $db->prepare("UPDATE telegram_settings SET last_tested_at = NOW(), last_test_result = ?, connection_status = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$allSuccess ? 'success' : 'failed', $allSuccess ? 'connected' : 'error', $settings['id']]);

        if ($allSuccess) {
            $this->app->logger()->info('Telegram test message sent to ' . count($recipients) . ' recipients');
            $this->app->jsonResponse(['success' => true, 'message' => 'Test message sent to ' . count($recipients) . ' recipient(s)']);
        }
        $this->app->jsonResponse([
            'success' => false,
            'error' => 'Telegram test failed for one or more recipients.',
            'failed_count' => count(array_filter($results, static fn(bool $success): bool => !$success)),
        ], 502);
    }

    public function configureWebhook(): void
    {
        $settings = $this->app->db()->query(
            'SELECT bot_token, is_enabled FROM telegram_settings ORDER BY id ASC LIMIT 1'
        )->fetch();
        if (!$settings || empty($settings['bot_token']) || (int) $settings['is_enabled'] !== 1) {
            $this->app->jsonResponse(['success' => false, 'error' => 'Enable Telegram and save a bot token first.'], 400);
        }

        try {
            $configured = (new TelegramCommandService($this->app))->setWebhook((string) $settings['bot_token']);
        } catch (\RuntimeException $e) {
            $this->app->jsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
        if (!$configured) {
            $this->app->jsonResponse(['success' => false, 'error' => 'Telegram rejected the webhook configuration. Check the application URL and bot settings.'], 502);
        }

        $this->app->logger()->info('Telegram balance-command webhook configured');
        $this->app->jsonResponse(['success' => true, 'message' => 'Telegram command enabled. Send /checkaccbal to the bot.']);
    }

    private function sendTelegramMessage(string $botToken, string $chatId, string $message): bool
    {
        $ch = curl_init('https://api.telegram.org/bot' . rawurlencode($botToken) . '/sendMessage');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode(['chat_id' => $chatId, 'text' => $message], JSON_THROW_ON_ERROR),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $curlError !== '') {
            $this->app->logger()->warning('Telegram test request failed', ['recipient' => $chatId, 'error' => $curlError]);
            return false;
        }
        $result = json_decode($response, true);
        return $httpCode === 200 && is_array($result) && ($result['ok'] ?? false) === true;
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