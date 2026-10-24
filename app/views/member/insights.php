<?php require __DIR__ . '/../partials/header.php'; ?>
<?php require __DIR__ . '/../partials/navbar.php'; ?>
<main class="container py-4">
    <h1 class="page-title">My Spend Insights</h1>
    <p class="text-muted">Category breakdown for <?= e(month_label(date('Y-m'))) ?> and your 6-month spending trend.</p>

    <?php require __DIR__ . '/../partials/flash.php'; ?>

    <div class="row g-4 mb-4">
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header">Category Breakdown — This Month</div>
                <div class="card-body">
                    <?php if (empty($breakdown)): ?>
                        <p class="text-muted mb-0">No expenses logged this month yet.</p>
                    <?php else: ?>
                        <canvas id="breakdownChart" height="230"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header">Month-over-Month Trend (last 6 months)</div>
                <div class="card-body">
                    <canvas id="trendChart" height="230"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Unusual Spend Flags — This Month</div>
        <div class="card-body">
            <?php if (empty($unusual)): ?>
                <p class="text-muted mb-0">No unusual expenses flagged this month. A flag appears when a single expense exceeds this category's trailing 3-month mean plus twice its standard deviation (household-wide).</p>
            <?php else: ?>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($unusual as $exp): ?>
                        <li class="mb-1">
                            <span class="badge badge-unusual me-2">Unusual</span>
                            <?= e(format_inr((float)$exp['amount'])) ?> on <?= e($exp['category_name']) ?>
                            (<?= e(format_date($exp['expense_date'])) ?>)
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</main>
<script>
document.addEventListener('DOMContentLoaded', function () {
<?php if (!empty($breakdown)): ?>
new Chart(document.getElementById('breakdownChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($breakdown, 'name')) ?>,
        datasets: [{
            data: <?= json_encode(array_map('floatval', array_column($breakdown, 'total'))) ?>,
            backgroundColor: ['#0d9488', '#14b8a6', '#5eead4', '#f59e0b', '#334155', '#64748b', '#0f766e', '#94a3b8'],
            borderWidth: 0
        }]
    },
    options: { plugins: { legend: { position: 'bottom' } } }
});
<?php endif; ?>

new Chart(document.getElementById('trendChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map('month_label', array_keys($trend))) ?>,
        datasets: [{
            label: 'Total spend (₹)',
            data: <?= json_encode(array_values($trend)) ?>,
            backgroundColor: '#0d9488',
            borderRadius: 4
        }]
    },
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
});
});
</script>
<?php require __DIR__ . '/../partials/footer.php'; ?>
