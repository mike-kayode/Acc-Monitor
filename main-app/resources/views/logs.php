<?php $pageTitle = 'System Logs'; ?>
<div class="card">
    <div class="card-header"><h5>Monitoring Logs</h5></div>
    <div class="card-body">
        <?php if (empty($logs)): ?>
            <p class="text-muted text-center">No logs available.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-sm">
                    <thead>
                        <tr>
                            <th>Level</th>
                            <th>Message</th>
                            <th>Execution Time</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><span class="badge bg-<?php echo $log['log_level'] === 'error' ? 'danger' : ($log['log_level'] === 'warning' ? 'warning' : 'primary'); ?>">
                                <?php echo strtoupper($log['log_level']); ?>
                            </span></td>
                            <td><?php echo Security::escape(substr($log['message'], 0, 100)); ?></td>
                            <td><?php echo $log['execution_time_ms'] ? $log['execution_time_ms'] . ' ms' : '-'; ?></td>
                            <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>