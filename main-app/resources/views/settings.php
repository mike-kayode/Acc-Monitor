<?php $pageTitle = 'Settings'; ?>
<div class="card">
    <div class="card-header"><h5>Application Settings</h5></div>
    <div class="card-body">
        <table class="table table-bordered">
            <thead>
                <tr><th>Key</th><th>Value</th><th>Type</th><th>Editable</th></tr>
            </thead>
            <tbody>
                <?php foreach ($settings as $setting): ?>
                <tr>
                    <td><?php echo Security::escape($setting['setting_key']); ?></td>
                    <td><?php echo Security::escape($setting['setting_value']); ?></td>
                    <td><?php echo $setting['setting_type']; ?></td>
                    <td><?php echo $setting['is_editable'] ? 'Yes' : 'No'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>