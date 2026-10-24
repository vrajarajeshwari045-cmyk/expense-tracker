<?php require __DIR__ . '/../partials/header.php'; ?>
<div class="login-shell">
    <div class="login-card">
        <div class="login-brand">
            <span class="brand-mark">₹</span>
            <h1>Kutumb</h1>
            <p class="text-muted">Household Expense Tracker</p>
        </div>

        <?php if ($msg = flash_get('error')): ?>
            <div class="alert alert-danger"><?= e($msg) ?></div>
        <?php endif; ?>
        <?php if ($msg = flash_get('success')): ?>
            <div class="alert alert-success"><?= e($msg) ?></div>
        <?php endif; ?>

        <form method="POST" action="/index.php?route=login">
            <?= Csrf::field() ?>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-teal w-100">Log In</button>
        </form>

        <div class="demo-creds">
            <p class="fw-semibold mb-1">Demo credentials</p>
            <table class="table table-sm mb-0">
                <tbody>
                <tr><td>Household Admin</td><td><code>admin@kutumb.test</code></td><td><code>Admin@123</code></td></tr>
                <tr><td>Member</td><td><code>member1@kutumb.test</code></td><td><code>Member@123</code></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
