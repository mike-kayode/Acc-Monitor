<?php $pageTitle = 'Security Settings'; ?>
<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h5>Admin Profile</h5></div>
            <div class="card-body">
                <?php if ($admin): ?>
                    <p><strong>Username:</strong> <?php echo Security::escape($admin['username']); ?></p>
                    <p><strong>Email:</strong> <?php echo Security::escape($admin['email']); ?></p>
                    <p><strong>Full Name:</strong> <?php echo Security::escape($admin['full_name']); ?></p>
                    <p><strong>Role:</strong> <?php echo $admin['role']; ?></p>
                    <p><strong>Last Login:</strong> <?php echo $admin['last_login_at'] ?? 'Never'; ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h5>Security Information</h5></div>
            <div class="card-body">
                <ul>
                    <li>Passwords are hashed with Argon2id</li>
                    <li>CSRF protection enabled</li>
                    <li>Secure session cookies</li>
                    <li>Session regeneration on login</li>
                    <li>Rate-limited login attempts</li>
                    <li>Account lockout after 5 failed attempts</li>
                </ul>
            </div>
        </div>
    </div>
</div>