<?php $pageTitle = 'WhatsApp Connection'; ?>
<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5>WhatsApp Connection Status</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <span class="badge bg-<?php echo ($session && ($session['connection_status'] === 'connected' || $session['connection_status'] === 'authenticated')) ? 'success' : 'danger'; ?>">
                        <?php echo ucfirst($session['connection_status'] ?? 'disconnected'); ?>
                    </span>
                </div>
                <?php if ($session && ($session['connection_status'] === 'connected' || $session['connection_status'] === 'authenticated')): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> WhatsApp is connected and ready to send notifications.
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> WhatsApp is not connected. Click "Connect WhatsApp" to scan the QR code.
                    </div>
                <?php endif; ?>
                <div class="d-grid gap-2">
                    <?php if (!$session || $session['connection_status'] !== 'connected'): ?>
                        <button class="btn btn-primary" onclick="connectWhatsApp()">
                            <i class="fas fa-qrcode"></i> Connect WhatsApp
                        </button>
                    <?php else: ?>
                        <button class="btn btn-danger" onclick="disconnectWhatsApp()">
                            <i class="fas fa-phone-slash"></i> Disconnect WhatsApp
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5>Configuration</h5>
            </div>
            <div class="card-body">

<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Recipients (<?php echo count($recipients); ?> / <?php echo $maxRecipients; ?>)</h5>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addRecipientModal">
                    <i class="fas fa-plus"></i> Add Recipient
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($recipients)): ?>
                    <p class="text-muted text-center">No recipients configured.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th>Last Delivery</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recipients as $idx => $recipient): ?>
                                <tr>
                                    <td><?php echo $idx + 1; ?></td>
                                    <td><?php echo Security::escape($recipient['recipient_name']); ?></td>
                                    <td><?php echo Security::escape($recipient['phone_number']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $recipient['is_enabled'] ? 'success' : 'secondary'; ?>">
                                            <?php echo $recipient['is_enabled'] ? 'Enabled' : 'Disabled'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $recipient['last_delivery_status'] ?? 'pending'; ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="testRecipient('<?php echo Security::escape($recipient['phone_number']); ?>')">
                                            <i class="fas fa-phone"></i> Test
                                        </button>
                                        <?php if ($recipient['is_enabled']): ?>
                                            <button class="btn btn-sm btn-outline-warning" onclick="toggleRecipient(<?php echo $recipient['id']; ?>, 0)">Disable</button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-success" onclick="toggleRecipient(<?php echo $recipient['id']; ?>, 1)">Enable</button>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteRecipient(<?php echo $recipient['id']; ?>)"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addRecipientModal" tabindex="-1">

<script>
async function connectWhatsApp() {
    const btn = document.querySelector('button[onclick="connectWhatsApp()"]');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Connecting...';
    btn.disabled = true;
    try {
        const response = await fetch('/whatsapp/connect', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({})
        });
        const data = await response.json();
        if (data.success) { location.reload(); }
        else { alert('Error: ' + data.error); btn.disabled = false; btn.innerHTML = '<i class="fas fa-qrcode"></i> Connect WhatsApp'; }
    } catch (err) {
        alert('Error: ' + err.message);
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-qrcode"></i> Connect WhatsApp';
    }
}

function disconnectWhatsApp() {
    if (confirm('Disconnect WhatsApp?')) {
        fetch('/whatsapp/disconnect', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({}) })
        .then(() => location.reload());
    }
}

function testRecipient(phone) {
    if (confirm('Send test to ' + phone + '?')) {
        fetch('/whatsapp/recipients/test', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'phone_number=' + encodeURIComponent(phone)
        }).then(async r => {
            const data = await r.json();
            alert(data.success ? 'Test sent!' : 'Error: ' + data.error);
        });
    }
}

function toggleRecipient(id, enabled) {
    fetch('/whatsapp/recipients/toggle', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + id + '&is_enabled=' + enabled
    }).then(async r => {
        const data = await r.json();
        if (data.success) location.reload();
        else alert('Error: ' + data.error);
    });
}

function deleteRecipient(id) {
    if (confirm('Delete recipient?')) {
        fetch('/whatsapp/recipients/delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + id
        }).then(async r => {
            const data = await r.json();
            if (data.success) location.reload();
            else alert('Error: ' + data.error);
        });
    }
}

function submitAddRecipient() {
    const formData = new FormData(document.getElementById('addRecipientForm'));
    fetch('/whatsapp/recipients/add', { method: 'POST', body: formData })
    .then(async r => {
        const data = await r.json();
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('addRecipientModal')).hide();
            location.reload();
        } else {
            alert('Error: ' + data.error);
        }
    });
}
</script>
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add WhatsApp Recipient</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addRecipientForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo Security::escape($csrf_token); ?>">
                    <div class="mb-3">
                        <label class="form-label">Recipient Name</label>
                        <input type="text" name="recipient_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone_number" class="form-control" placeholder="+234XXXXXXXXXX" required>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="is_enabled" class="form-check-input" checked>
                        <label class="form-check-label">Enabled</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="submitAddRecipient()">Add</button>
                </div>
            </form>
        </div>
    </div>
</div>
                <p><strong>Max Recipients:</strong> <?php echo $maxRecipients; ?></p>
                <p><strong>Primary Channel:</strong> WhatsApp</p>
                <p><strong>Secondary Channel:</strong> Telegram</p>
            </div>
        </div>
    </div>
</div>