<?php
/**
 * Shared "own expense" quick-add + list widget, reused by both personas:
 *   - Admin dashboard (their own recent expenses)
 *   - Member "My Expenses" page (their full, filterable history)
 *
 * Expects:
 *   array  $categories   active categories for the dropdown (the "Add" form and
 *                        each row's edit form add a synthetic extra <option> for
 *                        that row's own category if it's since been deactivated)
 *   array  $expenses     rows to render (already scoped to the owner)
 *   string $returnTo     route to bounce back to after add/edit/delete
 *   bool   $compact      true = dashboard widget (fewer columns), false = full page
 */
$compact = $compact ?? false;
?>
<div class="card expense-card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Log an Expense</span>
    </div>
    <div class="card-body">
        <form method="POST" action="/index.php?route=expenses/store" class="row g-2 align-items-end expense-add-form">
            <?= Csrf::field() ?>
            <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
            <div class="col-6 col-md-3">
                <label class="form-label">Category</label>
                <select name="category_id" class="form-select" required>
                    <option value="">Choose...</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Amount (₹)</label>
                <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Date</label>
                <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Payment</label>
                <select name="payment_method" class="form-select" required>
                    <option value="upi">UPI</option>
                    <option value="card">Card</option>
                    <option value="cash">Cash</option>
                    <option value="netbanking">Net Banking</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label">Note</label>
                <input type="text" name="note" maxlength="255" class="form-control" placeholder="optional">
            </div>
            <div class="col-12 col-md-1">
                <button type="submit" class="btn btn-teal w-100">Add</button>
            </div>
        </form>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-hover align-middle expense-table">
        <thead>
        <tr>
            <th>Date</th>
            <th>Category</th>
            <th>Amount</th>
            <th>Payment</th>
            <?php if (!$compact): ?><th>Note</th><?php endif; ?>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($expenses)): ?>
            <tr><td colspan="6" class="text-muted text-center py-4">No expenses logged yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($expenses as $exp):
            $flagged = isset($unusualIds) ? !empty($unusualIds[$exp['id']]) : Expense::isUnusual((int)$exp['category_id'], (float)$exp['amount'], $exp['expense_date']);
        ?>
            <tr>
                <td><?= e(format_date($exp['expense_date'])) ?></td>
                <td>
                    <?= e($exp['category_name']) ?>
                    <?php if ($flagged): ?>
                        <span class="badge badge-unusual" title="Unusually high vs. this category's trailing 3-month average">Unusual</span>
                    <?php endif; ?>
                </td>
                <td class="fw-semibold"><?= e(format_inr((float)$exp['amount'])) ?></td>
                <td><span class="text-capitalize"><?= e($exp['payment_method']) ?></span></td>
                <?php if (!$compact): ?><td class="text-muted small"><?= e($exp['note'] ?? '') ?></td><?php endif; ?>
                <td class="text-end">
                    <button class="btn btn-sm btn-outline-secondary" type="button"
                            data-bs-toggle="collapse" data-bs-target="#edit-<?= (int)$exp['id'] ?>">Edit</button>
                    <form method="POST" action="/index.php?route=expenses/delete" class="d-inline"
                          onsubmit="return confirm('Delete this expense?');">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="id" value="<?= (int)$exp['id'] ?>">
                        <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                </td>
            </tr>
            <tr class="collapse" id="edit-<?= (int)$exp['id'] ?>">
                <td colspan="<?= $compact ? 5 : 6 ?>" class="bg-light">
                    <form method="POST" action="/index.php?route=expenses/update" class="row g-2 align-items-end py-2">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="id" value="<?= (int)$exp['id'] ?>">
                        <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
                        <div class="col-6 col-md-3">
                            <?php
                                // $categories is active-only. If this expense's category was
                                // deactivated since it was logged, it won't be in that list --
                                // append it here (using the category_name already carried on
                                // the row) so the edit form still has a matching <option> and
                                // doesn't silently fall back to whatever's first in the list.
                                $currentCatId = (int)$exp['category_id'];
                                $hasCurrentCat = false;
                                foreach ($categories as $cat) {
                                    if ((int)$cat['id'] === $currentCatId) {
                                        $hasCurrentCat = true;
                                        break;
                                    }
                                }
                            ?>
                            <select name="category_id" class="form-select form-select-sm" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= (int)$cat['id'] ?>" <?= (int)$cat['id'] === $currentCatId ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                                <?php endforeach; ?>
                                <?php if (!$hasCurrentCat): ?>
                                    <option value="<?= $currentCatId ?>" selected><?= e($exp['category_name']) ?> (inactive)</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-6 col-md-2">
                            <input type="number" step="0.01" min="0.01" name="amount" class="form-control form-control-sm" value="<?= e((string)$exp['amount']) ?>" required>
                        </div>
                        <div class="col-6 col-md-2">
                            <input type="date" name="expense_date" class="form-control form-control-sm" value="<?= e($exp['expense_date']) ?>" max="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-6 col-md-2">
                            <select name="payment_method" class="form-select form-select-sm" required>
                                <?php foreach (['upi' => 'UPI', 'card' => 'Card', 'cash' => 'Cash', 'netbanking' => 'Net Banking', 'other' => 'Other'] as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= $exp['payment_method'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-8 col-md-2">
                            <input type="text" name="note" maxlength="255" class="form-control form-control-sm" value="<?= e($exp['note'] ?? '') ?>">
                        </div>
                        <div class="col-4 col-md-1">
                            <button type="submit" class="btn btn-sm btn-teal w-100">Save</button>
                        </div>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
