<?php require __DIR__ . '/../partials/header.php'; ?>
<?php require __DIR__ . '/../partials/navbar.php'; ?>
<main class="container py-4">
    <h1 class="page-title">My Expenses</h1>
    <p class="text-muted">All expenses you've logged. Filter by category or date range below.</p>

    <?php require __DIR__ . '/../partials/flash.php'; ?>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="/index.php" class="row g-2 align-items-end">
                <input type="hidden" name="route" value="member/expenses">
                <div class="col-6 col-md-3">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-select">
                        <option value="0">All</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>" <?= (($_GET['category_id'] ?? '') == $cat['id']) ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label">From</label>
                    <input type="date" name="from" class="form-control" value="<?= e($_GET['from'] ?? '') ?>">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label">To</label>
                    <input type="date" name="to" class="form-control" value="<?= e($_GET['to'] ?? '') ?>">
                </div>
                <div class="col-6 col-md-3">
                    <button type="submit" class="btn btn-teal w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <?php $expenses = $myExpenses; $returnTo = 'member/expenses'; $compact = false; require __DIR__ . '/../partials/expense_manager.php'; ?>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
