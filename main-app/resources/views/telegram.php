<?php $pageTitle = 'Telegram Settings'; ?>
<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h5>Telegram Configuration</h5></div>
            <div class="card-body">
                <form id="telegramForm">
                    <input type="hidden" name="csrf_token" value="<?php echo Security::escape($csrf_token); ?>">
                    <div class="mb-3">
                        <label class="form-label">Bot Token</label>
                        <input type="password" name="bot_token" class="form-control" value="" placeholder="<?php echo !empty($settings['token_configured']) ? 'Saved token — leave blank to keep it' : 'Enter bot token from @BotFather'; ?>" autocomplete="new-password">
                        <div class="form-text">The saved token is never shown. Leave blank to keep it unchanged.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Chat ID</label>
                        <input type="text" name="chat_id" class="form-control" value="<?php echo Security::escape($settings['chat_id'] ?? ''); ?>" placeholder="-1001234567890">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Additional recipients (up to 3)</label>
                        <textarea name="additional_chat_ids" class="form-control" rows="3" placeholder="One numeric chat ID or @channel username per line"><?php echo Security::escape($settings['additional_chat_ids'] ?? ''); ?></textarea>
                        <div class="form-text">Use numeric chat IDs for individual users. Each recipient must start the bot first. Primary plus additional recipients: maximum 4.</div>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="is_enabled" class="form-check-input" <?php echo $settings['is_enabled'] ? 'checked' : ''; ?>>
                        <label class="form-check-label">Enable Telegram Notifications</label>
                    </div>
                    <div class="d-grid">
                        <button type="button" class="btn btn-primary" onclick="saveTelegram()">Save Settings</button>
                    </div>
                </form>
                <div class="mt-3">
                    <button class="btn btn-outline-secondary" onclick="testTelegram()">
                        <i class="fas fa-paper-plane"></i> Test Connection
                    </button>
                    <button class="btn btn-outline-primary" onclick="configureTelegramCommands()">
                        <i class="fas fa-terminal"></i> Enable /checkaccbal
                    </button>
                </div>
                <?php if ($settings): ?>
                <div class="mt-3 p-3 bg-light">
                    <small><strong>Last Test:</strong> <?php echo $settings['last_tested_at'] ?? 'Never'; ?> - <?php echo ucfirst($settings['last_test_result'] ?? 'unknown'); ?></small>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h5>Information</h5></div>
            <div class="card-body">
                <p>Telegram sends alerts to the primary chat and up to three additional recipients. Each recipient must start the bot before messages can be delivered.</p>
                <p>Telegram is the secondary notification channel. It receives:</p>
                <ul>
                    <li>Threshold warnings, critical and emergency alerts</li>
                    <li>NairaTrader session expiry notifications</li>
                    <li>WhatsApp disconnection alerts</li>
                    <li>Monitoring failure notifications</li>
                    <li>System health failures</li>
                    <li>Recovery notifications</li>
                </ul>
                <p>After enabling the command, send <code>/checkaccbal</code> to the bot to receive the latest remaining count for each configured account plan. Only configured numeric chat IDs can use the command.</p>
                <div class="alert alert-info">
                    <strong>Setup:</strong> Create a bot via @BotFather on Telegram, get the bot token, and start a chat with your bot to get the Chat ID.
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function saveTelegram() {
    const formData = new FormData(document.getElementById('telegramForm'));
    fetch('/telegram/save', { method: 'POST', body: formData })
    .then(async r => {
        const data = await r.json();
        alert(data.success ? 'Settings saved!' : 'Error: ' + data.error);
        if (data.success) location.reload();
    });
}

function testTelegram() {
    fetch('/telegram/test', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: '' })
    .then(async r => {
        const data = await r.json();
        alert(data.success ? 'Test message sent!' : 'Error: ' + data.error);
        if (data.success) location.reload();
    });
}

function configureTelegramCommands() {
    const formData = new FormData(document.getElementById('telegramForm'));
    fetch('/telegram/configure-webhook', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ csrf_token: formData.get('csrf_token') })
    })
    .then(async r => {
        const data = await r.json();
        alert(data.success ? data.message : 'Error: ' + data.error);
    });
}
</script>