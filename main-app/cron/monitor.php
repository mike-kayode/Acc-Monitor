#!/usr/bin/php
<?php
/**
 * NairaTrader Account Pool Monitor - Cron Script
 * Executes the monitoring engine on schedule
 * Run this script every five minutes via Hostinger Cron Jobs.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Core/App.php';
require_once __DIR__ . '/../app/Core/Config.php';
require_once __DIR__ . '/../app/Core/Env.php';
require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Core/Logger.php';
require_once __DIR__ . '/../app/Core/Encryption.php';
require_once __DIR__ . '/../app/Core/Security.php';

use NAPM\Core\App;
use NAPM\Controllers\CronController;

$configDir = __DIR__ . '/../config';
$logDir = __DIR__ . '/../storage/logs';
$encryptionKey = $_ENV['APP_SECRET_KEY'] ?? 'default_change_me_in_production';

$app = App::init($configDir, $logDir, $encryptionKey);

$cron = new CronController($app);
$cron->run();