<?php
/**
 * NairaTrader Account Pool Monitor - Main Entry Point
 * Front controller for the admin dashboard
 */

declare(strict_types=1);

$basePath = dirname($_SERVER['SCRIPT_NAME'] ?? '');
$basePath = $basePath === '/' ? '' : rtrim($basePath, '/');
define('NAPM_BASE_PATH', $basePath);

if (!is_file(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.installed')) {
    header('Location: ' . app_install_url($basePath), true, 302);
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Core/App.php';
require_once __DIR__ . '/../app/Core/Config.php';
require_once __DIR__ . '/../app/Core/Env.php';
require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Core/Logger.php';
require_once __DIR__ . '/../app/Core/Encryption.php';
require_once __DIR__ . '/../app/Core/Security.php';

use NAPM\Core\App;
use NAPM\Core\Security;

$configDir = __DIR__ . '/../config';
$logDir = __DIR__ . '/../storage/logs';
$encryptionKey = $_ENV['APP_SECRET_KEY'] ?? 'default_change_me_in_production';

try {
    $app = App::init($configDir, $logDir, $encryptionKey);
} catch (Throwable $e) {
    error_log('NAPM bootstrap failed: ' . $e->getMessage());
    http_response_code(503);
    exit('The monitor needs setup. Visit the install page or contact the administrator.');
}

// Simple router
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = (string) $uri;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($basePath !== '' && str_starts_with($uri, $basePath)) {
    $uri = substr($uri, strlen($basePath));
}
$uri = '/' . trim($uri, '/');

// CSRF protection for state-changing requests
$hmacProtectedRoutes = ['/whatsapp/send', '/whatsapp/test', '/whatsapp/reconnect', '/whatsapp/webhook', '/telegram/webhook'];
$workerProtectedRoutes = ['/internal/monitor/run'];
if (
    in_array($method, ['POST', 'PUT', 'DELETE'], true)
    && !in_array($uri, $hmacProtectedRoutes, true)
    && !in_array($uri, $workerProtectedRoutes, true)
) {
    if (!Security::validateCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            App::instance()->jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
        }
        http_response_code(403);
        exit('Invalid CSRF token');
    }
}

// Route definitions
$routes = [
    'GET' => [
        '/' => 'DashboardController@index',
        '/dashboard' => 'DashboardController@index',
        '/login' => 'AuthController@login',
        '/logout' => 'AuthController@logout',
        '/whatsapp' => 'WhatsAppController@index',
        '/whatsapp/recipients' => 'WhatsAppController@recipients',
        '/telegram' => 'TelegramController@index',
        '/monitoring' => 'MonitoringController@index',
        '/history' => 'HistoryController@index',
        '/alerts' => 'AlertController@index',
        '/logs' => 'LogController@index',
        '/settings' => 'SettingsController@index',
        '/security' => 'SecurityController@index',
        '/auth-management' => 'AuthManagementController@index',
        '/internal/whatsapp/qr' => 'InternalApiController@getQR',
        '/internal/whatsapp/status' => 'InternalApiController@getStatus',
        '/internal/health' => 'InternalApiController@health',
    ],
    'POST' => [
        '/login' => 'AuthController@doLogin',
        '/whatsapp/connect' => 'WhatsAppController@connect',
        '/whatsapp/disconnect' => 'WhatsAppController@disconnect',
        '/whatsapp/refresh-qr' => 'WhatsAppController@refreshQR',
        '/whatsapp/recipients/add' => 'WhatsAppController@addRecipient',
        '/whatsapp/recipients/edit' => 'WhatsAppController@editRecipient',
        '/whatsapp/recipients/delete' => 'WhatsAppController@deleteRecipient',
        '/whatsapp/recipients/toggle' => 'WhatsAppController@toggleRecipient',
        '/whatsapp/recipients/test' => 'WhatsAppController@testRecipient',
        '/whatsapp/send' => 'InternalApiController@sendMessage',
        '/whatsapp/test' => 'InternalApiController@testMessage',
        '/whatsapp/reconnect' => 'InternalApiController@reconnect',
        '/whatsapp/webhook' => 'InternalApiController@webhook',
        '/telegram/save' => 'TelegramController@save',
        '/telegram/test' => 'TelegramController@test',
        '/telegram/configure-webhook' => 'TelegramController@configureWebhook',
        '/telegram/webhook' => 'TelegramWebhookController@receive',
        '/monitoring/save' => 'MonitoringController@save',
        '/monitoring/check-now' => 'MonitoringController@checkNow',
        '/monitoring/session' => 'MonitoringController@saveSession',
        '/auth-management/setup-session' => 'AuthManagementController@setupSession',
        '/auth-management/validate-session' => 'AuthManagementController@validateSession',
        '/auth-management/refresh-session' => 'AuthManagementController@refreshSession',
        '/auth-management/clear-session' => 'AuthManagementController@clearSession',
        '/internal/monitor/run' => 'CronController@run',
    ],
];

$handler = $routes[$method][$uri] ?? null;

if ($handler === null) {
    http_response_code(404);
    echo 'Not Found';
    exit;
}

[$controllerClass, $methodName] = explode('@', $handler);
$controllerClass = 'NAPM\\Controllers\\' . $controllerClass;

try {
    $controller = new $controllerClass($app);
    $controller->$methodName();
} catch (Throwable $e) {
    $app->logger()->error('Unhandled request failure', ['route' => $uri, 'error' => $e->getMessage()]);
    http_response_code(500);
    exit('The request could not be completed. Check the application logs.');
}

function app_install_url(string $basePath): string
{
    return rtrim($basePath, '/') . '/install.php';
}