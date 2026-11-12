<?php
/**
 * Application Bootstrap
 * Initializes configuration, database, session, and error handling
 */

namespace NAPM\Core;

class App
{
    private static ?App $instance = null;
    private Database $db;
    private Logger $logger;
    private Encryption $encryption;
    private bool $initialized = false;

    private function __construct(string $configDir, string $logDir, string $encryptionKey)
    {
        // Load environment
        $envFile = $configDir . '/../.env';
        if (file_exists($envFile)) {
            Env::load($envFile);
        }

        require_once $configDir . '/helpers.php';

        // Load configuration
        Config::load($configDir . '/app.php', 'app');
        Config::load($configDir . '/database.php', 'database');
        Config::load($configDir . '/monitoring.php', 'monitoring');
        Config::load($configDir . '/security.php', 'security');

        // Set timezone
        date_default_timezone_set(Config::get('app.timezone', 'Africa/Lagos'));

        // Initialize database
        $this->db = new Database(Config::all('database'));

        // Initialize logger
        $this->logger = new Logger($logDir);

        // Initialize encryption
        $this->encryption = new Encryption(
            Config::get('security.encryption_key') ?: Config::get('app.secret_key', $encryptionKey)
        );

        // Start session
        $this->startSession();

        $this->initialized = true;
    }

    public static function init(string $configDir, string $logDir, string $encryptionKey): self
    {
        if (self::$instance === null) {
            self::$instance = new self($configDir, $logDir, $encryptionKey);
        }
        return self::$instance;
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException("App not initialized. Call App::init() first.");
        }
        return self::$instance;
    }

    public function db(): Database
    {
        return $this->db;
    }

    public function logger(): Logger
    {
        return $this->logger;
    }

    public function encryption(): Encryption
    {
        return $this->encryption;
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $cookieParams = session_get_cookie_params();
            session_set_cookie_params([
                'lifetime' => (int) Config::get('security.session_lifetime', 3600),
                'path' => '/',
                'domain' => '',
                'secure' => (bool) Config::get('security.session_secure', true),
                'httponly' => (bool) Config::get('security.session_httponly', true),
                'samesite' => Config::get('security.session_samesite', 'Strict'),
            ]);
            session_name('NAPM_SESS');
            session_start();
        }
    }

    public function redirect(string $url): void
    {
        if (str_starts_with($url, '/') && function_exists('app_url')) {
            $basePath = defined('NAPM_BASE_PATH') ? NAPM_BASE_PATH : '';
            if ($basePath !== '' && !str_starts_with($url, $basePath . '/') && $url !== $basePath) {
                $url = app_url($url);
            }
        }
        header("Location: {$url}", true, 302);
        exit;
    }

    public function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}