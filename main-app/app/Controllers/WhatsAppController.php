<?php

namespace NAPM\Controllers;

use NAPM\Core\App;
use NAPM\Core\Security;

class WhatsAppController
{
    public function __construct(private App $app)
    {
        if (empty($_SESSION['admin_id'])) {
            $this->app->redirect('/login');
        }
    }

    public function index(): void
    {
        $db = $this->app->db();
        $session = $db->query('SELECT * FROM whatsapp_sessions ORDER BY updated_at DESC LIMIT 1')->fetch();
        $recipients = $db->query('SELECT * FROM whatsapp_recipients ORDER BY id ASC')->fetchAll();
        $data = ['session' => $session, 'recipients' => $recipients, 'maxRecipients' => 5, 'csrf_token' => Security::generateCsrfToken()];
        extract($data);
        $viewFile = __DIR__ . '/../../resources/views/whatsapp.php';
        require __DIR__ . '/../../resources/views/layout.php';
    }

    public function recipients(): void
    {
        $recipients = $this->app->db()->query('SELECT * FROM whatsapp_recipients ORDER BY id ASC')->fetchAll();
        $this->app->jsonResponse(['success' => true, 'recipients' => $recipients, 'max' => 5]);
    }

    public function connect(): void
    {
        $workerUrl = rtrim((string) \NAPM\Core\Env::get('WHATSAPP_WORKER_URL', ''), '/');
        if ($workerUrl === '') {
            $this->app->jsonResponse(['success' => false, 'error' => 'WhatsApp worker is not configured'], 503);
        }
        $this->workerRequest('POST', '/api/qr');
    }

    public function disconnect(): void
    {
        $this->app->db()->prepare(
            "UPDATE whatsapp_sessions SET connection_status = 'disconnected', disconnected_at = NOW() WHERE connection_status <> 'disconnected'"
        )->execute();
        $this->app->jsonResponse(['success' => true]);
    }

    public function refreshQR(): void
    {
        $this->connect();
    }

    public function addRecipient(): void
    {
        $name = trim((string) ($_POST['recipient_name'] ?? ''));
        $phone = trim((string) ($_POST['phone_number'] ?? ''));
        if ($name === '' || !Security::validatePhoneNumber($phone)) {
            $this->app->jsonResponse(['success' => false, 'error' => 'Enter a name and a valid international phone number'], 400);
        }

        $db = $this->app->db();
        if ((int) $db->query('SELECT COUNT(*) FROM whatsapp_recipients')->fetchColumn() >= 5) {
            $this->app->jsonResponse(['success' => false, 'error' => 'Maximum five WhatsApp recipients allowed'], 400);
        }
        $db->prepare('INSERT INTO whatsapp_recipients (recipient_name, phone_number, is_enabled) VALUES (?, ?, ?)')
            ->execute([Security::sanitizeInput($name), $phone, isset($_POST['is_enabled']) ? 1 : 0]);
        $this->app->jsonResponse(['success' => true]);
    }

    public function editRecipient(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['recipient_name'] ?? ''));
        $phone = trim((string) ($_POST['phone_number'] ?? ''));
        if ($id < 1 || $name === '' || !Security::validatePhoneNumber($phone)) {
            $this->app->jsonResponse(['success' => false, 'error' => 'Invalid recipient details'], 400);
        }
        $this->app->db()->prepare('UPDATE whatsapp_recipients SET recipient_name = ?, phone_number = ?, is_enabled = ?, updated_at = NOW() WHERE id = ?')
            ->execute([Security::sanitizeInput($name), $phone, isset($_POST['is_enabled']) ? 1 : 0, $id]);
        $this->app->jsonResponse(['success' => true]);
    }

    public function deleteRecipient(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id < 1) {
            $this->app->jsonResponse(['success' => false, 'error' => 'Invalid recipient ID'], 400);
        }
        $this->app->db()->prepare('DELETE FROM whatsapp_recipients WHERE id = ?')->execute([$id]);
        $this->app->jsonResponse(['success' => true]);
    }

    public function toggleRecipient(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id < 1) {
            $this->app->jsonResponse(['success' => false, 'error' => 'Invalid recipient ID'], 400);
        }
        $this->app->db()->prepare('UPDATE whatsapp_recipients SET is_enabled = ?, updated_at = NOW() WHERE id = ?')
            ->execute([isset($_POST['is_enabled']) ? 1 : 0, $id]);
        $this->app->jsonResponse(['success' => true]);
    }

    public function testRecipient(): void
    {
        $phone = trim((string) ($_POST['phone_number'] ?? ''));
        if (!Security::validatePhoneNumber($phone)) {
            $this->app->jsonResponse(['success' => false, 'error' => 'Invalid phone number'], 400);
        }
        $this->workerRequest('POST', '/api/send', [
            'event_id' => Security::generateEventId('test'),
            'recipients' => [$phone],
            'message' => 'NAPM WhatsApp test message',
        ]);
    }

    private function workerRequest(string $method, string $path, array $payload = []): void
    {
        $workerUrl = rtrim((string) \NAPM\Core\Env::get('WHATSAPP_WORKER_URL', ''), '/');
        $secret = (string) \NAPM\Core\Env::get('WORKER_SECRET', '');
        if ($workerUrl === '' || $secret === '') {
            $this->app->jsonResponse(['success' => false, 'error' => 'WhatsApp worker is not configured'], 503);
        }
        $ch = curl_init($workerUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Worker-Secret: ' . $secret],
            CURLOPT_POSTFIELDS => $payload ? json_encode($payload, JSON_THROW_ON_ERROR) : null,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
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
