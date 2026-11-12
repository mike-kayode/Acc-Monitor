<?php $pageTitle = 'Alert History'; ?>
<div class="card">
    <div class="card-header"><h5>Alert History</h5></div>
    <div class="card-body">
        <?php if (empty($alerts)): ?>
            <p class="text-muted text-center">No alerts recorded.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Severity</th>
                            <th>Accounts</th>
                            <th>Threshold</th>
                            <th>Message</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alerts as $alert): ?>
                        <tr>
                            <td><?php echo ucfirst($alert['alert_type']); ?></td>
                            <td><span class="badge bg-<?php echo $alert['severity'] === 'emergency' ? 'danger' : ($alert['severity'] === 'critical' ? 'warning' : 'info'); ?>">
                                <?php echo ucfirst($alert['severity']); ?>
                            </span></td>
                            <td><?php echo $alert['available_accounts'] ?? '-'; ?></td>
                            <td><?php echo $alert['threshold_value'] ?? '-'; ?></td>
                            <td><?php echo Security::escape(substr($alert['message'], 0, 80)); ?>...</td>
                            <td><?php echo date('Y-m-d H:i:s', strtotime($alert['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>