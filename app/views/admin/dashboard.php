<?php require __DIR__ . '/../partials/header.php'; ?>
<?php require __DIR__ . '/../partials/navbar.php'; ?>
<main class="container py-4">
    <h1 class="page-title">Household Dashboard</h1>
    <p class="text-muted">Overview for <?= e(month_label(date('Y-m'))) ?></p>

    <?php require __DIR__ . '/../partials/flash.php'; ?>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-tile">
                <div class="stat-label">Household spend this month</div>
                <div class="stat-value"><?= e(format_inr($householdTotal)) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-tile">
                <div class="stat-label">Active members</div>
                <div class="stat-value"><?= (int)$memberCount ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-tile stat-tile-warn">
                <div class="stat-label">Categories over budget</div>
                <div class="stat-value"><?= count(array_filter($budgetRows, fn($r) => $r['over'])) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-tile stat-tile-warn">
                <div class="stat-label">Unusual expenses flagged</div>
                <div class="stat-value"><?= count($unusual) ?></div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header">Budget vs. Actual — <?= e(month_label(date('Y-m'))) ?> (household-wide)</div>
                <div class="card-body">
                    <?php if (empty($budgetRows)): ?>
                        <p class="text-muted mb-0">No active categories yet.</p>
                    <?php endif; ?>
                    <?php foreach ($budgetRows as $row):
                        $cat = $row['category'];
                        $pct = $row['cap'] ? min(100, round($row['spent'] / $row['cap'] * 100)) : 0;
                    ?>
                        <div class="budget-row mb-3">
                            <div class="d-flex justify-content-between">
                                <span class="fw-semibold"><?= e($cat['name']) ?></span>
                                <span class="<?= $row['over'] ? 'text-danger fw-semibold' : 'text-muted' ?>">
                                    <?= e(format_inr($row['spent'])) ?>
                                    <?= $row['cap'] !== null ? ' / ' . e(format_inr($row['cap'])) : ' (no budget set)' ?>
                                </span>
                            </div>
                            <?php if ($row['cap'] !== null): ?>
                                <div class="progress budget-progress">
                                    <div class="progress-bar <?= $row['over'] ? 'bg-danger' : 'bg-teal' ?>" style="width: <?= $pct ?>%"></div>
                                </div>
                                <?php if ($row['over']): ?>
                                    <div class="text-danger small mt-1">Over budget by <?= e(format_inr($row['spent'] - $row['cap'])) ?></div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header">Category Breakdown — This Month</div>
                <div class="card-body">
                    <canvas id="categoryChart" height="230"></canvas>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($unusual)): ?>
    <div class="card mb-4 border-warning">
        <div class="card-header bg-warning-subtle">Unusual Spend Flags — This Month (household-wide)</div>
        <div class="card-body">
            <ul class="list-unstyled mb-0">
                <?php foreach ($unusual as $exp): ?>
                    <li class="mb-1">
                        <span class="badge badge-unusual me-2">Unusual</span>
                        <?= e($exp['member_name']) ?> spent <strong><?= e(format_inr((float)$exp['amount'])) ?></strong>
                        on <?= e($exp['category_name']) ?> (<?= e(format_date($exp['expense_date'])) ?>) —
                        well above that category's recent trailing average.
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <h2 class="section-title">My Own Expenses</h2>
    <?php $expenses = $myExpenses; $returnTo = 'admin/dashboard'; $compact = true; require __DIR__ . '/../partials/expense_manager.php'; ?>
</main>
<script>
document.addEventListener('DOMContentLoaded', function () {
new Chart(document.getElementById('categoryChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_map(fn($r) => $r['category']['name'], array_filter($budgetRows, fn($r) => $r['spent'] > 0))) ?>,
        datasets: [{
            data: <?= json_encode(array_map(fn($r) => $r['spent'], array_filter($budgetRows, fn($r) => $r['spent'] > 0))) ?>,
            backgroundColor: ['#0d9488', '#14b8a6', '#5eead4', '#f59e0b', '#334155', '#64748b', '#0f766e', '#94a3b8'],
            borderWidth: 0
        }]
    },
    options: { plugins: { legend: { position: 'bottom' } } }
});
});
</script>
<?php require __DIR__ . '/../partials/footer.php'; ?>
