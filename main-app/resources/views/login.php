<?php $pageTitle = 'Login'; ?>
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-4">
        <div class="card shadow">
            <div class="card-body p-5">
                <h2 class="text-center mb-4">
                    <i class="fas fa-shield-alt text-primary"></i> NairaTrader Monitor
                </h2>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo Security::escape($error); ?></div>
                <?php endif; ?>
                <form method="POST" action="<?php echo Security::escape(app_url('login')); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo Security::escape($csrf_token); ?>">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Sign In</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>