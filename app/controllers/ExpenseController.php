<?php

require_once __DIR__ . '/../models/Expense.php';
require_once __DIR__ . '/../models/Category.php';

// Shared "own expense" CRUD -- reachable by BOTH personas (a Household Admin
// logs and manages personal spending too, per FEATURES.md's feature table:
// "Log/edit/delete own expenses: Admin (own), Member (own)"). Every action
// here scopes to Auth::id() at the SQL layer (Expense::*Owned methods), so
// there is no role branching needed -- ownership IS the access control.
class ExpenseController
{
    private const METHODS = ['cash', 'card', 'upi', 'netbanking', 'other'];

    public function store(): void
    {
        Auth::requireLogin();
        Csrf::requireValid();

        [$data, $errors] = $this->validate($_POST);
        $returnTo = $this->returnRoute();

        if ($errors) {
            flash_set('error', implode(' ', $errors));
            redirect($returnTo);
        }

        Expense::create(Auth::id(), $data['category_id'], $data['amount'], $data['expense_date'], $data['payment_method'], $data['note']);

        if (Expense::isUnusual($data['category_id'], $data['amount'], $data['expense_date'])) {
            flash_set('warning', 'Expense logged -- note this amount is unusually high for this category compared to the last 3 months.');
        } else {
            flash_set('success', 'Expense logged.');
        }

        redirect($returnTo);
    }

    public function update(): void
    {
        Auth::requireLogin();
        Csrf::requireValid();

        $id = (int)($_POST['id'] ?? 0);
        $returnTo = $this->returnRoute();

        // Ownership check up front: a Member tampering with someone else's
        // expense id gets rejected here, before any UPDATE is attempted.
        $existing = Expense::findOwned($id, Auth::id());
        if (!$existing) {
            flash_set('error', 'Expense not found, or it does not belong to you.');
            redirect($returnTo);
        }

        [$data, $errors] = $this->validate($_POST, $existing);

        if ($errors) {
            flash_set('error', implode(' ', $errors));
            redirect($returnTo);
        }

        Expense::updateOwned(
            $id,
            Auth::id(),
            $data['category_id'],
            $data['amount'],
            $data['expense_date'],
            $data['payment_method'],
            $data['note']
        );

        flash_set('success', 'Expense updated.');
        redirect($returnTo);
    }

    public function delete(): void
    {
        Auth::requireLogin();
        Csrf::requireValid();

        $id = (int)($_POST['id'] ?? 0);
        $returnTo = $this->returnRoute();

        if (Expense::deleteOwned($id, Auth::id())) {
            flash_set('success', 'Expense deleted.');
        } else {
            flash_set('error', 'Expense not found, or it does not belong to you.');
        }

        redirect($returnTo);
    }

    // $existing (only passed from update()) is the expense's row BEFORE this
    // edit is applied. It lets an edit keep an expense's original category
    // even after that category has since been deactivated -- otherwise there
    // would be no way to save any edit (even just a note typo fix) to an old
    // expense without it silently reassigning to a different, active category.
    private function validate(array $post, ?array $existing = null): array
    {
        $v = (new Validator())
            ->required($post, 'category_id', 'Category')
            ->required($post, 'amount', 'Amount')
            ->numeric($post, 'amount', 'Amount')
            ->positive($post, 'amount', 'Amount')
            ->required($post, 'expense_date', 'Date')
            ->date($post, 'expense_date', 'Date')
            ->required($post, 'payment_method', 'Payment method')
            ->inArray($post, 'payment_method', self::METHODS, 'Payment method')
            ->maxLength($post, 'note', 255, 'Note');

        $errors = $v->errors();
        $categoryId = (int)($post['category_id'] ?? 0);

        if (!isset($errors['category_id'])) {
            $category = Category::find($categoryId);
            $keepsExistingCategory = $existing !== null && (int)$existing['category_id'] === $categoryId;
            if (!$category || (!$category['is_active'] && !$keepsExistingCategory)) {
                $errors['category_id'] = 'Choose a valid, active category.';
            }
        }

        if (!isset($errors['expense_date']) && !empty($post['expense_date']) && $post['expense_date'] > date('Y-m-d')) {
            $errors['expense_date'] = 'Date cannot be in the future.';
        }

        $note = request_str($post['note'] ?? '');
        $data = [
            'category_id'    => $categoryId,
            'amount'         => (float)($post['amount'] ?? 0),
            'expense_date'   => request_str($post['expense_date'] ?? ''),
            'payment_method' => request_str($post['payment_method'] ?? ''),
            'note'           => trim($note) ?: null,
        ];

        return [$data, array_values($errors)];
    }

    // Sends the user back to wherever they submitted from (Admin's dashboard
    // quick-add vs. Member's full "My Expenses" page) instead of hardcoding
    // one destination.
    private function returnRoute(): string
    {
        $to = $_POST['return_to'] ?? '';
        $allowed = ['admin/dashboard', 'member/dashboard', 'member/expenses'];
        return in_array($to, $allowed, true) ? $to : Auth::dashboardRoute(Auth::role());
    }
}
