<?php $pageTitle = 'Account History'; ?>
<div class="card">
    <div class="card-header"><h5>Account Snapshots</h5></div>
    <div class="card-body">
        <?php if (empty($snapshots)): ?>
            <p class="text-muted text-center">No snapshots available.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Available Accounts</th>
                            <th>Previous</th>
                            <th>Change</th>
                            <th>Status</th>
                            <th>Checked At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($snapshots as $snapshot): ?>
                        <tr>
                            <td><strong><?php echo $snapshot['available_accounts']; ?></strong></td>
                            <td><?php echo $snapshot['previous_accounts'] ?? '-'; ?></td>
                            <td>
                                <?php if ($snapshot['previous_accounts'] !== null): ?>
                                    <?php $diff = $snapshot['available_accounts'] - $snapshot['previous_accounts']; ?>
                                    <?php echo $diff >= 0 ? '+' : ''; echo $diff; ?>
                                <?php else: ?>-<?php endif; ?>
                            </td>
                            <td><span class="badge bg-<?php echo $snapshot['extraction_status'] === 'success' ? 'success' : 'danger'; ?>">
                                <?php echo ucfirst($snapshot['extraction_status']); ?>
                            </span></td>
                            <td><?php echo date('Y-m-d H:i:s', strtotime($snapshot['checked_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <div class="mt-3">
            <strong>Stats:</strong> Min: <?php echo $stats['min']; ?> | Max: <?php echo $stats['max']; ?> | Latest: <?php echo $stats['latest']; ?> | Total: <?php echo $stats['total']; ?>
        </div>
    </div>
</div>