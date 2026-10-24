<?php require __DIR__ . '/../partials/header.php'; ?>
<?php require __DIR__ . '/../partials/navbar.php'; ?>
<main class="container py-4">
    <h1 class="page-title">My Report</h1>
    <p class="text-muted">Your own expenses for any date range, exportable as CSV.</p>

    <?php require __DIR__ . '/../partials/flash.php'; ?>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="/index.php" class="row g-2 align-items-end">
                <input type="hidden" name="route" value="member/report">
                <div class="col-6 col-md-3">
                    <label class="form-label">From</label>
                    <input type="date" name="from" class="form-control" value="<?= e($from) ?>">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label">To</label>
                    <input type="date" name="to" class="form-control" value="<?= e($to) ?>">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-select">
                        <option value="0">All categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>" <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-teal flex-fill">Filter</button>
                    <a class="btn btn-outline-teal flex-fill"
                       href="/index.php?route=member/report/export&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&category_id=<?= $categoryId ?>">
                        Export CSV
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="stat-tile">
                <div class="stat-label">Total spend in range</div>
                <div class="stat-value"><?= e(format_inr($total)) ?></div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">Spend by Category</div>
                <div class="card-body">
                    <?php if (empty($byCategory)): ?>
                        <p class="text-muted mb-0">No expenses in this range.</p>
                    <?php endif; ?>
                    <?php foreach ($byCategory as $name => $amt): $pct = $total > 0 ? round($amt / $total * 100) : 0; ?>
                        <div class="d-flex justify-content-between small mb-1">
                            <span><?= e($name) ?></span><span><?= e(format_inr($amt)) ?> (<?= $pct ?>%)</span>
                        </div>
                        <div class="progress budget-progress mb-2"><div class="progress-bar bg-teal" style="width: <?= $pct ?>%"></div></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Expenses (<?= count($expenses) ?>)</div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Date</th><th>Category</th><th>Amount</th><th>Payment</th><th>Note</th></tr></thead>
                <tbody>
                <?php foreach ($expenses as $exp): ?>
                    <tr>
                        <td><?= e(format_date($exp['expense_date'])) ?></td>
                        <td><?= e($exp['category_name']) ?></td>
                        <td class="fw-semibold"><?= e(format_inr((float)$exp['amount'])) ?></td>
                        <td class="text-capitalize"><?= e($exp['payment_method']) ?></td>
                        <td class="text-muted small"><?= e($exp['note'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($expenses)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">No expenses in this range.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
