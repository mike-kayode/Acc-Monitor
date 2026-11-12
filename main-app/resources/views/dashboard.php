<?php $pageTitle = 'Overview'; ?>
<div class="row">
    <div class="col-md-3 mb-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h6 class="text-white-50">Available Accounts</h6>
                <h2 class="mb-0"><?php echo $latestSnapshot['available_accounts'] ?? '--'; ?></h2>
                <?php if ($previousSnapshot): ?>
                    <small>
                        <?php $diff = ($latestSnapshot['available_accounts'] ?? 0) - ($previousSnapshot['available_accounts'] ?? 0); ?>
                        <?php echo $diff >= 0 ? '+' : ''; echo $diff; ?> from last check
                    </small>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h6 class="text-white-50">Previous Count</h6>
                <h2 class="mb-0"><?php echo $previousSnapshot['available_accounts'] ?? '--'; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h6 class="text-white-50">Last Check</h6>
                <h2 class="mb-0"><?php echo date('H:i', strtotime($latestSnapshot['checked_at'] ?? '')); ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-warning text-dark">
            <div class="card-body">
                <h6 class="text-dark">Next Check</h6>
                <h2 class="mb-0"><?php echo date('H:i', strtotime('+' . ($settings['monitoring_interval_minutes'] ?? 5) . ' minutes')); ?></h2>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($latestSnapshot['plan_counts'])): ?>
<div class="card mt-4">
    <div class="card-header"><h5 class="mb-0">Accounts Remaining by Plan</h5></div>
    <div class="card-body">
        <ul class="list-unstyled mb-0">
            <?php foreach ($planCatalog as $planId => $plan): ?>
                <?php if (array_key_exists($planId, $latestSnapshot['plan_counts'])): ?>
                    <li class="mb-2"><?php echo Security::escape($plan['label']); ?> — <?php echo number_format((int) $latestSnapshot['plan_counts'][$planId]); ?> left</li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5>Service Connections</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span><i class="fab fa-whatsapp text-success"></i> WhatsApp</span>
                    <span class="badge bg-<?php echo ($whatsappStatus['connection_status'] === 'connected' || $whatsappStatus['connection_status'] === 'authenticated') ? 'success' : 'danger'; ?>">
                        <?php echo ucfirst($whatsappStatus['connection_status'] ?? 'disconnected'); ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span><i class="fab fa-telegram text-primary"></i> Telegram</span>
                    <span class="badge bg-<?php echo ($telegramStatus['is_enabled'] ? 'success' : 'secondary'); ?>">
                        <?php echo $telegramStatus['is_enabled'] ? 'Enabled' : 'Disabled'; ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span>NairaTrader Session</span>
                    <span class="badge bg-<?php echo $sessionValid ? 'success' : 'warning'; ?>">
                        <?php echo $sessionValid ? 'Authenticated' : 'Expired'; ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5>Quick Stats</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <span>Active Recipients</span>
                    <h4><?php echo $recipientCount; ?> / 5</h4>
                </div>
                <div class="mb-3">
                    <span>Monitoring Interval</span>
                    <h4><?php echo $settings['monitoring_interval_minutes'] ?? 5; ?> minutes</h4>
                </div>
                <div class="mb-0">
                    <span>Monitoring Status</span>
                    <h4 class="text-<?php echo $settings['monitoring_enabled'] === 'true' ? 'success' : 'danger'; ?>">
                        <?php echo $settings['monitoring_enabled'] === 'true' ? 'Enabled' : 'Disabled'; ?>
                    </h4>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($latestAlert): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="alert alert-<?php echo $latestAlert['severity'] === 'emergency' ? 'danger' : ($latestAlert['severity'] === 'critical' ? 'warning' : 'info'); ?>">
            <h6>Latest Alert</h6>
            <p class="mb-1"><?php echo nl2br(Security::escape($latestAlert['message'])); ?></p>
            <small><?php echo date('Y-m-d H:i:s', strtotime($latestAlert['created_at'])); ?></small>
        </div>
    </div>
</div>
<?php endif; ?>