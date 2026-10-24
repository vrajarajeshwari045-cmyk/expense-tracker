<?php require __DIR__ . '/../partials/header.php'; ?>
<?php require __DIR__ . '/../partials/navbar.php'; ?>
<main class="container py-4">
    <h1 class="page-title">My Dashboard</h1>
    <p class="text-muted">Overview for <?= e(month_label(date('Y-m'))) ?></p>

    <?php require __DIR__ . '/../partials/flash.php'; ?>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4">
            <div class="stat-tile">
                <div class="stat-label">My spend this month</div>
                <div class="stat-value"><?= e(format_inr($myTotal)) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="stat-tile stat-tile-warn">
                <div class="stat-label">Household categories over budget</div>
                <div class="stat-value"><?= count(array_filter($budgetRows, fn($r) => $r['over'])) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="stat-tile stat-tile-warn">
                <div class="stat-label">My unusual expenses this month</div>
                <div class="stat-value"><?= count($unusual) ?></div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">Household Budget Status — <?= e(month_label(date('Y-m'))) ?></div>
        <div class="card-body">
            <p class="text-muted small">Budget caps are set household-wide by your Household Admin — this shows the whole household's spend against each cap, since it's a shared limit everyone contributes to.</p>
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
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (!empty($unusual)): ?>
    <div class="card mb-4 border-warning">
        <div class="card-header bg-warning-subtle">My Unusual Spend Flags — This Month</div>
        <div class="card-body">
            <ul class="list-unstyled mb-0">
                <?php foreach ($unusual as $exp): ?>
                    <li class="mb-1">
                        <span class="badge badge-unusual me-2">Unusual</span>
                        <?= e(format_inr((float)$exp['amount'])) ?> on <?= e($exp['category_name']) ?>
                        (<?= e(format_date($exp['expense_date'])) ?>) — above your recent trailing average for this category.
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <h2 class="section-title">Quick Add / Recent Expenses</h2>
    <?php $expenses = $myExpenses; $returnTo = 'member/dashboard'; $compact = true; require __DIR__ . '/../partials/expense_manager.php'; ?>
    <p><a href="/index.php?route=member/expenses" class="link-teal">View / manage all my expenses &rarr;</a></p>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
