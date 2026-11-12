<?php $pageTitle = 'Authentication Management'; ?>
<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5>NairaTrader API Authentication</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <span class="badge bg-<?php echo $isValid ? 'success' : 'danger'; ?>">
                        <?php echo $isValid ? 'API Token Active' : 'API Token Not Configured'; ?>
                    </span>
                </div>

                <?php if (!$isValid): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>No active NairaTrader API token.</strong> Add the bearer token used by the NairaTrader admin dashboard to enable read-only monitoring.
                </div>

                <form id="sessionForm">
                    <input type="hidden" name="csrf_token" value="<?php echo Security::escape($csrf_token); ?>">
                    <div class="mb-3">
                        <label class="form-label">NairaTrader API Bearer Token</label>
                        <textarea name="bearer_token" class="form-control" rows="4" placeholder="Paste the bearer token from an authenticated NairaTrader API request" maxlength="4096" autocomplete="off" required></textarea>
                        <div class="form-text">The token is encrypted before storage and sent only to the verified HTTPS NairaTrader API endpoint. Never share it with anyone.</div>
                    </div>
                    <div class="d-grid">
                        <button type="button" class="btn btn-primary" onclick="saveSession()">
                            <i class="fas fa-save"></i> Validate and Save Token
                        </button>
                    </div>
                </form>
<?php else: ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <strong>NairaTrader API token is active.</strong> Monitoring can now request the available MT5 account count.
                </div>
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-primary" onclick="validateSession()">
                        <i class="fas fa-sync"></i> Validate Session
                    </button>
                    <button class="btn btn-outline-warning" onclick="refreshSession()">
                        <i class="fas fa-clock"></i> Extend Expiry (24h)
                    </button>
                    <button class="btn btn-outline-danger" onclick="clearSession()">
                        <i class="fas fa-trash"></i> Clear Session
                    </button>
                </div>
                <?php endif; ?>

                <hr>
                <h6>API Endpoint</h6>
                <ol>
                    <li>Monitoring queries the read-only available MT5 account counts endpoint.</li>
                    <li>Use an API bearer token issued by NairaTrader for your authorized admin account.</li>
                    <li>Do not paste your password or cookies in this field.</li>
                </ol>
                <div class="alert alert-info">
                    <strong>Read-only system:</strong> Never creates, deletes, or modifies MT5 accounts.
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h5>Session Info</h5></div>
            <div class="card-body">
                <?php if ($session): ?>
                    <p><strong>Last Updated:</strong> <?php echo date('Y-m-d H:i:s', strtotime($session['updated_at'])); ?></p>
                    <p><strong>Expires At:</strong> <?php echo $session['expires_at'] ? date('Y-m-d H:i:s', strtotime($session['expires_at'])) : 'Not specified'; ?></p>
                    <p><strong>IP:</strong> <?php echo Security::escape($session['ip_address'] ?? 'Unknown'); ?></p>
                <?php else: ?>
                    <p class="text-muted">No session configured</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><h5>Read-only API URL</h5></div>
            <div class="card-body">
                <p class="text-muted small"><?php echo Security::escape($apiUrl); ?></p>
                <a href="<?php echo Security::escape($apiUrl); ?>" class="btn btn-outline-primary btn-sm w-100" target="_blank" rel="noopener noreferrer">Open API endpoint</a>
            </div>
        </div>
    </div>
</div>

<script>
async function saveSession() {
    const formData = new FormData(document.getElementById('sessionForm'));
    const btn = document.querySelector('button[onclick="saveSession()"]');
    const original = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    btn.disabled = true;
    try {
        const response = await fetch('/auth-management/setup-session', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(formData)
        });
        const data = await response.json();
        if (data.success) { alert('Session saved!'); location.reload(); }
        else { alert('Error: ' + data.error); btn.innerHTML = original; btn.disabled = false; }
    } catch (err) { alert('Error: ' + err.message); btn.innerHTML = original; btn.disabled = false; }
}

async function validateSession() {
    const btn = document.querySelector('button[onclick="validateSession()"]');
    const original = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Validating...';
    btn.disabled = true;
    try {
        const response = await fetch('/auth-management/validate-session', { method: 'POST' });
        const data = await response.json();
        if (data.valid) { alert('✓ ' + data.message); location.reload(); }
        else { alert('✗ ' + data.message); btn.innerHTML = original; btn.disabled = false; }
    } catch (err) { alert('Error: ' + err.message); btn.innerHTML = original; btn.disabled = false; }
}

async function refreshSession() {
    if (!confirm('Extend session expiry by 24 hours?')) return;
    try {
        const response = await fetch('/auth-management/refresh-session', { method: 'POST' });
        const data = await response.json();
        if (data.success) { alert('Extended!'); location.reload(); }
        else { alert('Error: ' + data.error); }
    } catch (err) { alert('Error: ' + err.message); }
}

async function clearSession() {
    if (!confirm('Clear session? You will need to re-authenticate.')) return;
    try {
        const response = await fetch('/auth-management/clear-session', { method: 'POST' });
        const data = await response.json();
        if (data.success) { alert('Cleared!'); location.reload(); }
        else { alert('Error: ' + data.error); }
    } catch (err) { alert('Error: ' + err.message); }
}
</script>