<?php require __DIR__ . '/../partials/header.php'; ?>
<?php require __DIR__ . '/../partials/navbar.php'; ?>
<main class="container py-4">
    <h1 class="page-title">Manage Members</h1>
    <p class="text-muted">Create and deactivate Member accounts for the household. Only the Household Admin can add logins — there is no self-registration.</p>

    <?php require __DIR__ . '/../partials/flash.php'; ?>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">Add Member</div>
                <div class="card-body">
                    <form method="POST" action="/index.php?route=admin/members/store">
                        <?= Csrf::field() ?>
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" maxlength="100" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Temporary Password</label>
                            <input type="text" name="password" class="form-control" minlength="6" required>
                        </div>
                        <button type="submit" class="btn btn-teal w-100">Create Member Account</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">Members (<?= count($members) ?>)</div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                        <tr><th>Name</th><th>Email</th><th>Status</th><th></th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($members as $m): ?>
                            <tr>
                                <td><?= e($m['name']) ?></td>
                                <td><?= e($m['email']) ?></td>
                                <td>
                                    <span class="badge <?= $m['status'] === 'active' ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                        <?= e($m['status']) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <form method="POST" action="/index.php?route=admin/members/status" class="d-inline"
                                          onsubmit="return confirm('<?= $m['status'] === 'active' ? 'Deactivate' : 'Reactivate' ?> this member?');">
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                                        <input type="hidden" name="status" value="<?= $m['status'] === 'active' ? 'inactive' : 'active' ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                                            <?= $m['status'] === 'active' ? 'Deactivate' : 'Reactivate' ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($members)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">No members yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
