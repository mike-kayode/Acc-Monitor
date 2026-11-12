/* NairaTrader Account Pool Monitor - Frontend JavaScript */

document.addEventListener('DOMContentLoaded', function() {
    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });

    // Format phone numbers
    const phoneInputs = document.querySelectorAll('input[name="phone_number"]');
    phoneInputs.forEach(function(input) {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value && !value.startsWith('234') && !value.startsWith('+234')) {
                value = '234' + value;
            }
            e.target.value = value ? '+' + value : '';
        });
    });
});

function showLoading(btn) {
    const original = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
    btn.disabled = true;
    return original;
}

function hideLoading(btn, original) {
    btn.innerHTML = original;
    btn.disabled = false;
}

function showMessage(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-' + type + ' alert-dismissible fade show';
    alertDiv.innerHTML = message + ' <button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    const container = document.querySelector('.container');
    if (container) {
        container.insertBefore(alertDiv, container.firstChild);
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alertDiv);
            bsAlert.close();
        }, 5000);
    }
}