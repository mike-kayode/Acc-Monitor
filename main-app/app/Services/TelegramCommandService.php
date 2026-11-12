<?php

namespace NAPM\App\Services;

use NAPM\Core\App;

class TelegramCommandService
{
    public function __construct(private App $app)
    {
    }

    public function webhookSecret(): string
    {
        $botToken = $this->app->db()->query(
            'SELECT bot_token FROM telegram_settings ORDER BY id ASC LIMIT 1'
        )->fetchColumn();
        if (!is_string($botToken) || $botToken === '') {
            throw new \RuntimeException('Telegram bot token is unavailable for webhook authentication.');
        }

        return hash_hmac('sha256', 'nairatrader-telegram-webhook-v1', $botToken);
    }

    public function setWebhook(string $botToken): bool
    {
        $baseUrl = rtrim((string) $this->app->config('app.url', ''), '/');
        $url = parse_url($baseUrl);
        if (
            !is_array($url)
            || strtolower((string) ($url['scheme'] ?? '')) !== 'https'
            || empty($url['host'])
            || preg_match('/[\r\n]/', $baseUrl)
        ) {
            throw new \RuntimeException('Set a valid HTTPS application URL before configuring the Telegram command.');
        }

        $webhookUrl = $baseUrl . app_url('telegram/webhook');
        $webhookInfo = $this->telegramRequest($botToken, 'getWebhookInfo', []);
        if (!is_array($webhookInfo) || ($webhookInfo['ok'] ?? false) !== true) {
            return false;
        }
        $existingUrl = (string) ($webhookInfo['result']['url'] ?? '');
        if ($existingUrl !== '' && $existingUrl !== $webhookUrl) {
            throw new \RuntimeException('This Telegram bot already has a different webhook configured. Clear or verify it before replacing it.');
        }

        $webhookSet = $this->callTelegram($botToken, 'setWebhook', [
            'url' => $webhookUrl,
            'secret_token' => $this->webhookSecret(),
            'allowed_updates' => ['message'],
        ]);
        if (!$webhookSet) {
            return false;
        }

        return $this->callTelegram($botToken, 'setMyCommands', [
            'commands' => [
                ['command' => 'checkaccbal', 'description' => 'Show remaining accounts for all plans'],
            ],
        ]);
    }

    public function handleUpdate(array $update): bool
    {
        $message = $update['message'] ?? null;
        if (!is_array($message) || !isset($message['text'], $message['chat']['id'])) {
            return true;
        }

        $text = trim((string) $message['text']);
        if (!preg_match('/^\/checkaccbal(?:@[A-Za-z0-9_]+)?(?:\s|$)/i', $text)) {
            return true;
        }

        $db = $this->app->db();
        $settings = $db->query(
            'SELECT bot_token, chat_id, additional_chat_ids, is_enabled FROM telegram_settings ORDER BY id ASC LIMIT 1'
        )->fetch();
        if (!$settings || (int) $settings['is_enabled'] !== 1 || empty($settings['bot_token'])) {
            return true;
        }

        $chatId = (string) $message['chat']['id'];
        try {
            $recipients = TelegramRecipients::parse(
                (string) ($settings['chat_id'] ?? ''),
                (string) ($settings['additional_chat_ids'] ?? '')
            );
        } catch (\InvalidArgumentException $e) {
            $this->app->logger()->error('Telegram balance command rejected due to invalid recipient configuration');
                return true;
        }

        $authorizedChatIds = array_filter(
            $recipients,
            static fn(string $recipient): bool => preg_match('/^-?[0-9]{1,20}$/', $recipient) === 1
        );
        if (!in_array($chatId, $authorizedChatIds, true)) {
            $this->app->logger()->warning('Telegram balance command ignored for an unconfigured chat');
            return true;
        }

        $result = (new ScraperService($this->app))->fetchPlanCounts();
        if (!$result['success']) {
            $reply = 'Unable to retrieve the latest NairaTrader balances. Please try again shortly.';
        } else {
            try {
                $reply = "Current NairaTrader account balances:\n\n"
                    . NairaTraderPlans::formatReport($result['plan_counts'] ?? []);
            } catch (\RuntimeException $e) {
                $this->app->logger()->error('Telegram balance command received incomplete plan counts');
                $reply = 'The API response did not include all configured account plans. No partial balance report was sent.';
            }
        }

        $sent = $this->sendMessage((string) $settings['bot_token'], $chatId, $reply);
        if (!$sent) {
            $this->app->logger()->error('Telegram balance command response failed');
        }
        return $sent;
    }

    private function sendMessage(string $botToken, string $chatId, string $text): bool
    {
        return $this->callTelegram($botToken, 'sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
        ]);
    }

    private function callTelegram(string $botToken, string $method, array $payload): bool
    {
        $data = $this->telegramRequest($botToken, $method, $payload);
        return is_array($data) && ($data['ok'] ?? false) === true;
    }

    private function telegramRequest(string $botToken, string $method, array $payload): ?array
    {
        $ch = curl_init('https://api.telegram.org/bot' . rawurlencode($botToken) . '/' . $method);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $curlError !== '') {
            $this->app->logger()->warning('Telegram API request failed', ['method' => $method, 'error' => $curlError]);
            return null;
        }

        $data = json_decode($response, true);
        return $httpCode === 200 && is_array($data) ? $data : null;
    }
}
