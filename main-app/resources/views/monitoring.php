<?php $pageTitle = 'Monitoring Settings'; ?>
<?php
$settingsMap = array_column($settings, 'setting_value', 'setting_key');
$getSetting = static fn(string $key): string => htmlspecialchars((string) ($settingsMap[$key] ?? ''), ENT_QUOTES, 'UTF-8');
?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Monitoring Configuration</h5>
                <button class="btn btn-success" onclick="checkNow()"><i class="fas fa-sync"></i> Check Now</button>
            </div>
            <div class="card-body">
                <form id="monitoringForm">
                    <input type="hidden" name="csrf_token" value="<?php echo Security::escape($csrf_token); ?>">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Monitoring Enabled</label>
                            <select name="monitoring_enabled" class="form-select">
                                <option value="true" <?php echo ($settingsMap['monitoring_enabled'] ?? '') === 'true' ? 'selected' : ''; ?>>Yes</option>
                                <option value="false" <?php echo ($settingsMap['monitoring_enabled'] ?? '') === 'false' ? 'selected' : ''; ?>>No</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Interval (minutes)</label>
                            <input type="number" min="1" name="monitoring_interval_minutes" class="form-control" value="<?php echo $getSetting('monitoring_interval_minutes'); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Warning Threshold</label>
                            <input type="number" min="0" name="warning_threshold" class="form-control" value="<?php echo $getSetting('warning_threshold'); ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Critical Threshold</label>
                            <input type="number" min="0" name="critical_threshold" class="form-control" value="<?php echo $getSetting('critical_threshold'); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Emergency Threshold</label>
                            <input type="number" min="0" name="emergency_threshold" class="form-control" value="<?php echo $getSetting('emergency_threshold'); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Exhaustion Threshold</label>
                            <input type="number" min="0" name="exhaustion_threshold" class="form-control" value="<?php echo $getSetting('exhaustion_threshold'); ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">WhatsApp Enabled</label>
                            <select name="whatsapp_enabled" class="form-select">
                                <option value="true" <?php echo ($settingsMap['whatsapp_enabled'] ?? '') === 'true' ? 'selected' : ''; ?>>Yes</option>
                                <option value="false" <?php echo ($settingsMap['whatsapp_enabled'] ?? '') === 'false' ? 'selected' : ''; ?>>No</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Telegram Enabled</label>
                            <select name="telegram_enabled" class="form-select">
                                <option value="true" <?php echo ($settingsMap['telegram_enabled'] ?? '') === 'true' ? 'selected' : ''; ?>>Yes</option>
                                <option value="false" <?php echo ($settingsMap['telegram_enabled'] ?? '') === 'false' ? 'selected' : ''; ?>>No</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Reminder Interval (min)</label>
                            <input type="number" min="1" name="reminder_interval_minutes" class="form-control" value="<?php echo $getSetting('reminder_interval_minutes'); ?>">
                        </div>
                    </div>
                    <div class="d-grid">
                        <button type="button" class="btn btn-primary" onclick="saveMonitoring()">Save Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header"><h5>NairaTrader Read-only Session</h5></div>
    <div class="card-body">
        <p>Session status: <strong><?php echo !empty($monitorSession['is_valid']) ? 'Saved (encrypted)' : 'Not configured'; ?></strong></p>
        <p class="text-muted">Paste the cookie header for your authenticated NairaTrader MT5 pool page. It is encrypted before storage and used only to fetch the page.</p>
        <form id="sessionForm">
            <input type="hidden" name="csrf_token" value="<?php echo Security::escape($csrf_token); ?>">
            <label for="cookie_data" class="form-label">Cookie data</label>
            <textarea id="cookie_data" name="cookie_data" class="form-control" rows="3" maxlength="10240" required autocomplete="off"></textarea>
            <button type="button" class="btn btn-primary mt-3" onclick="saveNairaTraderSession()">Save encrypted session</button>
        </form>
        <div id="sessionResult" class="mt-3" aria-live="polite"></div>
    </div>
</div>

<script>
function saveMonitoring() {
    const formData = new FormData(document.getElementById('monitoringForm'));
    fetch('/monitoring/save', { method: 'POST', body: formData })
    .then(async r => {
        const data = await r.json();
        alert(data.success ? 'Settings saved!' : 'Error: ' + data.error);
    });
}

async function saveNairaTraderSession() {
    const form = document.getElementById('sessionForm');
    const result = document.getElementById('sessionResult');
    try {
        const response = await fetch('/monitoring/session', { method: 'POST', body: new FormData(form) });
        const data = await response.json();
        result.className = data.success ? 'alert alert-success mt-3' : 'alert alert-danger mt-3';
        result.textContent = data.message || data.error || 'Unable to save session.';
        if (data.success) form.reset();
    } catch (error) {
        result.className = 'alert alert-danger mt-3';
        result.textContent = 'Unable to save session: ' + error.message;
    }
}

function checkNow() {
    fetch('/monitoring/check-now', { method: 'POST' })
    .then(async r => {
        const data = await r.json();
        alert(data.success ? 'Monitoring check triggered!' : 'Error: ' + data.error);
    });
}
</script>