<?php

namespace NAPM\Controllers;

use NAPM\App\Services\TelegramCommandService;
use NAPM\Core\App;

class TelegramWebhookController
{
    public function __construct(private App $app)
    {
    }

    public function receive(): void
    {
        $service = new TelegramCommandService($this->app);
        $providedSecret = (string) ($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');
        try {
            $expectedSecret = $service->webhookSecret();
        } catch (\RuntimeException $e) {
            $this->app->logger()->error('Telegram webhook secret is not configured');
            http_response_code(503);
            return;
        }
        if ($providedSecret === '' || !hash_equals($expectedSecret, $providedSecret)) {
            http_response_code(403);
            return;
        }

        $raw = file_get_contents('php://input');
        if ($raw === false || strlen($raw) > 65536) {
            http_response_code(400);
            return;
        }
        $update = json_decode($raw, true);
        if (!is_array($update)) {
            http_response_code(400);
            return;
        }

        $sent = $service->handleUpdate($update);
        http_response_code($sent ? 200 : 503);
        header('Content-Type: application/json');
        echo $sent ? '{"ok":true}' : '{"ok":false}';
    }
}
