<?php require __DIR__ . '/../partials/header.php'; ?>
<?php require __DIR__ . '/../partials/navbar.php'; ?>
<main class="container py-4">
    <h1 class="page-title">Categories &amp; Budgets</h1>
    <p class="text-muted">Set a monthly budget cap per category. A category with existing expenses can only be deactivated, never deleted, so past reports stay accurate.</p>

    <?php require __DIR__ . '/../partials/flash.php'; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">Add Category</div>
                <div class="card-body">
                    <form method="POST" action="/index.php?route=admin/categories/store">
                        <?= Csrf::field() ?>
                        <div class="mb-3">
                            <label class="form-label">Category name</label>
                            <input type="text" name="name" class="form-control" maxlength="80" required>
                        </div>
                        <button type="submit" class="btn btn-teal w-100">Add Category</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Budget month</div>
                <div class="card-body">
                    <form method="GET" action="/index.php" class="d-flex gap-2">
                        <input type="hidden" name="route" value="admin/categories">
                        <input type="month" name="month" class="form-control" value="<?= e($month) ?>">
                        <button type="submit" class="btn btn-outline-teal">View</button>
                    </form>
                    <p class="text-muted small mt-2 mb-0">Budget caps are set per calendar month, so you can plan ahead. A past month's cap is locked once its calendar month has ended and can no longer be edited.</p>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">Categories — <?= e(month_label($month)) ?></div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                        <tr><th>Category</th><th>Status</th><th>Spend this month</th><th>Budget cap (₹)</th><th></th></tr>
                        </thead>
                        <tbody>
                        <?php $monthLocked = $month < date('Y-m'); ?>
                        <?php foreach ($categories as $cat):
                            $spent = $spend[$cat['id']] ?? 0.0;
                            $cap = $budgets[$cat['id']] ?? null;
                        ?>
                            <tr>
                                <td>
                                    <form method="POST" action="/index.php?route=admin/categories/rename" class="d-flex gap-1 align-items-center category-rename-form">
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
                                        <input type="text" name="name" value="<?= e($cat['name']) ?>" class="form-control form-control-sm rename-input" readonly maxlength="80">
                                        <button type="button" class="btn btn-sm btn-link p-0 rename-edit-btn">rename</button>
                                        <button type="submit" class="btn btn-sm btn-link p-0 d-none rename-save-btn">save</button>
                                    </form>
                                </td>
                                <td>
                                    <span class="badge <?= $cat['is_active'] ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                        <?= $cat['is_active'] ? 'active' : 'inactive' ?>
                                    </span>
                                </td>
                                <td><?= e(format_inr((float)$spent)) ?></td>
                                <td>
                                    <?php if ($monthLocked): ?>
                                        <span class="text-muted small">
                                            <?= $cap !== null ? e(format_inr($cap)) : 'not set' ?>
                                            &mdash; <span class="badge text-bg-secondary">locked</span>
                                        </span>
                                    <?php else: ?>
                                        <form method="POST" action="/index.php?route=admin/budgets/store" class="d-flex gap-1">
                                            <?= Csrf::field() ?>
                                            <input type="hidden" name="category_id" value="<?= (int)$cat['id'] ?>">
                                            <input type="hidden" name="month" value="<?= e($month) ?>">
                                            <input type="number" step="0.01" min="0.01" name="cap_amount" class="form-control form-control-sm"
                                                   value="<?= $cap !== null ? e((string)$cap) : '' ?>" placeholder="not set" style="max-width: 8.5rem">
                                            <button type="submit" class="btn btn-sm btn-outline-teal">Save</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <form method="POST" action="/index.php?route=admin/categories/toggle" class="d-inline">
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                                            <?= $cat['is_active'] ? 'Deactivate' : 'Reactivate' ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
