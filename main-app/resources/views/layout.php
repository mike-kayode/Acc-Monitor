<?php
if (isset($viewFile) && is_file($viewFile)) {
    ob_start();
    require $viewFile;
    $content = ob_get_clean();
}
$csrfToken = $csrf_token ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'NairaTrader Monitor'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo Security::escape(app_url('assets/css/style.css')); ?>" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        window.NAPM_BASE_PATH = <?php echo json_encode(defined('NAPM_BASE_PATH') ? NAPM_BASE_PATH : ''); ?>;
        window.NAPM_CSRF_TOKEN = <?php echo json_encode($csrfToken); ?>;
        window.fetch = ((nativeFetch) => (input, init = {}) => {
            const options = { ...init };
            const method = (options.method || 'GET').toUpperCase();
            if (method === 'POST' && window.NAPM_CSRF_TOKEN) {
                const headers = new Headers(options.headers || {});
                headers.set('X-CSRF-TOKEN', window.NAPM_CSRF_TOKEN);
                options.headers = headers;
            }
            if (typeof input === 'string' && input.startsWith('/') && window.NAPM_BASE_PATH) {
                input = window.NAPM_BASE_PATH + input;
            }
            return nativeFetch(input, options);
        })(window.fetch);
    </script>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="<?php echo Security::escape(app_url('dashboard')); ?>">
                <i class="fas fa-shield-alt"></i> NairaTrader Monitor
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="<?php echo Security::escape(app_url('dashboard')); ?>">Overview</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo Security::escape(app_url('whatsapp')); ?>">WhatsApp</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo Security::escape(app_url('telegram')); ?>">Telegram</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo Security::escape(app_url('monitoring')); ?>">Monitoring</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo Security::escape(app_url('history')); ?>">History</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo Security::escape(app_url('alerts')); ?>">Alerts</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo Security::escape(app_url('logs')); ?>">Logs</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo Security::escape(app_url('auth-management')); ?>">NairaTrader Auth</a></li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="<?php echo Security::escape(app_url('settings')); ?>">Settings</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo Security::escape(app_url('security')); ?>">Security</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo Security::escape(app_url('logout')); ?>">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (isset($flash_message)): ?>
            <div class="alert alert-info alert-dismissible fade show">
                <?php echo $flash_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($flash_error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $flash_error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php echo $content; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo Security::escape(app_url('assets/js/app.js')); ?>"></script>
</body>
</html>