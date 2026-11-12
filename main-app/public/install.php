<?php
declare(strict_types=1);

$appRoot = dirname(__DIR__);
$installedMarker = $appRoot . DIRECTORY_SEPARATOR . '.installed';
$installerTokenFile = $appRoot . DIRECTORY_SEPARATOR . '.install-token-hash';
$error = '';
$success = false;

if (is_file($installedMarker)) {
    http_response_code(404);
    exit('Not Found');
}

if (!is_file($installerTokenFile)) {
    http_response_code(404);
    exit('Not Found');
}

if (($_SERVER['HTTPS'] ?? '') !== 'on' && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') !== 'https') {
    http_response_code(400);
    exit('Run setup over HTTPS.');
}

$scriptDirectory = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$cookiePath = $scriptDirectory === '' ? '/' : $scriptDirectory . '/';
session_set_cookie_params([
    'lifetime' => 1800,
    'path' => $cookiePath,
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();
$_SESSION['install_csrf'] ??= bin2hex(random_bytes(32));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['install_csrf'], (string) ($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        exit('Invalid setup request. Reload the page and retry.');
    }
    $storedTokenHash = trim((string) file_get_contents($installerTokenFile));
    $providedTokenHash = hash('sha256', (string) ($_POST['install_token'] ?? ''));
    if ($storedTokenHash === '' || !hash_equals($storedTokenHash, $providedTokenHash)) {
        http_response_code(403);
        exit('Invalid installation token.');
    }

    $host = trim((string) ($_POST['db_host'] ?? ''));
    $port = filter_var($_POST['db_port'] ?? null, FILTER_VALIDATE_INT);
    $database = trim((string) ($_POST['db_name'] ?? ''));
    $username = trim((string) ($_POST['db_user'] ?? ''));
    $dbPassword = (string) ($_POST['db_password'] ?? '');
    $adminUsername = trim((string) ($_POST['admin_username'] ?? ''));
    $adminEmail = trim((string) ($_POST['admin_email'] ?? ''));
    $adminName = trim((string) ($_POST['admin_name'] ?? ''));
    $adminPassword = (string) ($_POST['admin_password'] ?? '');

    if ($host === '' || $database === '' || $username === '' || !$port || $port < 1 || $port > 65535) {
        $error = 'Enter valid database connection details.';
    } elseif (!preg_match('/^[A-Za-z0-9_]+$/', $database) || !preg_match('/^[A-Za-z0-9_]+$/', $adminUsername)) {
        $error = 'Database name and admin username may contain only letters, numbers, and underscores.';
    } elseif (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL) || $adminName === '' || strlen($adminPassword) < 12) {
        $error = 'Enter a valid admin name/email and a password of at least 12 characters.';
    } elseif (!preg_match('/^[A-Za-z0-9._:-]+$/', $host)) {
        $error = 'The database host contains unsupported characters.';
    } else {
        $envFile = $appRoot . DIRECTORY_SEPARATOR . '.env';
        $envValues = [
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_TIMEZONE' => 'Africa/Lagos',
            'APP_URL' => '',
            'APP_SECRET_KEY' => bin2hex(random_bytes(32)),
            'SESSION_ENCRYPTION_KEY' => bin2hex(random_bytes(32)),
            'WORKER_SECRET' => bin2hex(random_bytes(32)),
            'HMAC_SECRET' => bin2hex(random_bytes(32)),
            'SESSION_SECURE' => 'true',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $host,
            'DB_PORT' => (string) $port,
            'DB_DATABASE' => $database,
            'DB_USERNAME' => $username,
            'DB_PASSWORD_BASE64' => base64_encode($dbPassword),
        ];
        $envContents = '';
        foreach ($envValues as $key => $value) {
            $envContents .= $key . '="' . addcslashes($value, "\\\"\n\r") . '"' . PHP_EOL;
        }

        try {
            if (file_put_contents($envFile, $envContents, LOCK_EX) === false) {
                throw new RuntimeException('Could not write the private environment file.');
            }
            if (!chmod($envFile, 0600)) {
                throw new RuntimeException('Could not secure the private environment file.');
            }

            $pdo = new PDO(
                sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database),
                $username,
                $dbPassword,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
            );
            $schema = file_get_contents($appRoot . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '001_initial_schema.sql');
            if ($schema === false) {
                throw new RuntimeException('The initial database schema is missing.');
            }
            foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
                $pdo->exec($statement);
            }

            $insert = $pdo->prepare(
                "INSERT INTO admin_users (username, email, password_hash, full_name, role, is_active) VALUES (?, ?, ?, ?, 'admin', 1)"
            );
            $insert->execute([$adminUsername, $adminEmail, password_hash($adminPassword, PASSWORD_DEFAULT), $adminName]);
            if (file_put_contents($installedMarker, gmdate('c'), LOCK_EX) === false) {
                throw new RuntimeException('Could not lock the installer after setup.');
            }
            if (!chmod($installedMarker, 0600)) {
                throw new RuntimeException('Could not secure the installation marker.');
            }
            if (!unlink($installerTokenFile)) {
                error_log('NAPM setup completed but could not remove the one-time installer token hash.');
            }
            $success = true;
        } catch (Throwable $exception) {
            $error = 'Setup failed. Check the database details and permissions in your hosting panel, then try again.';
            error_log('NAPM setup failure: ' . $exception->getMessage());
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>NairaTrader Monitor Setup</title>
    <style>
        body{font:16px system-ui,sans-serif;background:#f3f5f8;margin:0;padding:32px;color:#172033}
        main{max-width:620px;margin:0 auto;background:#fff;padding:28px;border-radius:12px;box-shadow:0 8px 32px #17203318}
        label{display:block;font-weight:600;margin:14px 0 6px}
        input{box-sizing:border-box;width:100%;padding:10px;border:1px solid #ccd3de;border-radius:6px}
        button{margin-top:20px;padding:12px 18px;border:0;border-radius:6px;background:#175cd3;color:white;font-weight:700}
        .grid{display:grid;grid-template-columns:2fr 1fr;gap:12px}
        .notice{padding:12px;border-radius:6px;background:#fff1f0;color:#9b1c1c}
        @media(max-width:520px){body{padding:12px}.grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
<main>
    <h1>NairaTrader Monitor Setup</h1>
    <?php if ($success): ?>
        <p>Setup completed. The database schema and administrator account are ready.</p>
        <p><a href="index.php">Continue to sign in</a></p>
    <?php else: ?>
        <p>Enter the one-time installation token, MySQL details from your hosting control panel, and create the first administrator.</p>
        <?php if ($error !== ''): ?><p class="notice"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
        <form method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['install_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
            <label for="install_token">One-time installation token</label><input id="install_token" name="install_token" type="password" required autocomplete="off">
            <div class="grid">
                <div><label for="db_host">Database host</label><input id="db_host" name="db_host" value="localhost" required></div>
                <div><label for="db_port">Port</label><input id="db_port" name="db_port" type="number" value="3306" required></div>
            </div>
            <label for="db_name">Database name</label><input id="db_name" name="db_name" required>
            <label for="db_user">Database username</label><input id="db_user" name="db_user" required>
            <label for="db_password">Database password</label><input id="db_password" name="db_password" type="password" required>
            <hr>
            <label for="admin_name">Administrator name</label><input id="admin_name" name="admin_name" required>
            <label for="admin_email">Administrator email</label><input id="admin_email" name="admin_email" type="email" required>
            <label for="admin_username">Administrator username</label><input id="admin_username" name="admin_username" required>
            <label for="admin_password">Administrator password (12+ characters)</label><input id="admin_password" name="admin_password" type="password" minlength="12" required>
            <button type="submit">Install monitor</button>
        </form>
    <?php endif; ?>
</main>
</body>
</html>
