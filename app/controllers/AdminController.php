<?php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../models/Budget.php';
require_once __DIR__ . '/../models/Expense.php';

class AdminController
{
    public function dashboard(): void
    {
        Auth::requireRole('super_admin');

        $month = date('Y-m');
        $categories = Category::active();
        $spendByCategory = Expense::categoryTotalsForMonth($month, null); // household-wide
        $budgets = Budget::forMonth($month);

        $budgetRows = [];
        foreach ($categories as $cat) {
            $spent = $spendByCategory[$cat['id']] ?? 0.0;
            $cap = $budgets[$cat['id']] ?? null;
            $budgetRows[] = [
                'category' => $cat,
                'spent'    => $spent,
                'cap'      => $cap,
                'over'     => $cap !== null && $spent > $cap,
            ];
        }

        // Household-wide unusual-spend flags for the current month.
        $unusual = [];
        foreach (Expense::listHousehold(['from' => $month . '-01', 'to' => date('Y-m-t', strtotime($month . '-01'))]) as $exp) {
            if (Expense::isUnusual((int)$exp['category_id'], (float)$exp['amount'], $exp['expense_date'])) {
                $unusual[] = $exp;
            }
        }

        $householdTotal = array_sum($spendByCategory);
        $memberCount = count(User::allMembers());

        // Admin's own recent expenses, for the shared quick-add/manage widget.
        $myExpenses = Expense::listForUser(Auth::id());
        $myExpenses = array_slice($myExpenses, 0, 8);

        $pageTitle = 'Admin Dashboard';
        require __DIR__ . '/../views/admin/dashboard.php';
    }

    // ---- Members ----

    public function members(): void
    {
        Auth::requireRole('super_admin');
        $pageTitle = 'Manage Members';
        $members = User::allMembers();
        require __DIR__ . '/../views/admin/members.php';
    }

    public function memberStore(): void
    {
        Auth::requireRole('super_admin');
        Csrf::requireValid();

        // request_str() guards against an array-typed field reaching trim(),
        // which throws an uncaught TypeError on an array argument.
        $name = trim(request_str($_POST['name'] ?? ''));
        $email = trim(request_str($_POST['email'] ?? ''));
        $password = request_str($_POST['password'] ?? '');

        $v = (new Validator())
            ->required($_POST, 'name', 'Name')
            ->maxLength($_POST, 'name', 100, 'Name')
            ->required($_POST, 'email', 'Email')
            ->email($_POST, 'email', 'Email')
            ->required($_POST, 'password', 'Password')
            ->minLength($_POST, 'password', 6, 'Password');

        if (!$v->fails() && User::emailExists($email)) {
            $errors = $v->errors();
            $errors['email'] = 'A member with this email already exists.';
        } else {
            $errors = $v->errors();
        }

        if (!empty($errors)) {
            flash_set('error', implode(' ', $errors));
            redirect('admin/members');
        }

        User::create($name, $email, $password, 'member');
        flash_set('success', "Member \"$name\" added.");
        redirect('admin/members');
    }

    public function memberStatus(): void
    {
        Auth::requireRole('super_admin');
        Csrf::requireValid();

        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';

        if (!in_array($status, ['active', 'inactive'], true)) {
            flash_set('error', 'Invalid status.');
            redirect('admin/members');
        }

        User::setStatus($id, $status);
        flash_set('success', 'Member status updated.');
        redirect('admin/members');
    }

    // ---- Categories & Budgets ----

    public function categories(): void
    {
        Auth::requireRole('super_admin');

        $month = $_GET['month'] ?? date('Y-m');
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }

        $categories = Category::all();
        $budgets = Budget::forMonth($month);
        $spend = Expense::categoryTotalsForMonth($month, null);

        $pageTitle = 'Categories & Budgets';
        require __DIR__ . '/../views/admin/categories.php';
    }

    public function categoryStore(): void
    {
        Auth::requireRole('super_admin');
        Csrf::requireValid();

        $name = trim(request_str($_POST['name'] ?? ''));
        $v = (new Validator())->required($_POST, 'name', 'Category name')->maxLength($_POST, 'name', 80, 'Category name');

        if (!$v->fails() && Category::nameExists($name)) {
            flash_set('error', 'A category with this name already exists.');
            redirect('admin/categories');
        }
        if ($v->fails()) {
            flash_set('error', implode(' ', $v->errors()));
            redirect('admin/categories');
        }

        Category::create($name);
        flash_set('success', "Category \"$name\" added.");
        redirect('admin/categories');
    }

    public function categoryRename(): void
    {
        Auth::requireRole('super_admin');
        Csrf::requireValid();

        $id = (int)($_POST['id'] ?? 0);
        $name = trim(request_str($_POST['name'] ?? ''));

        $v = (new Validator())->required($_POST, 'name', 'Category name')->maxLength($_POST, 'name', 80, 'Category name');
        if (!$v->fails() && Category::nameExists($name, $id)) {
            flash_set('error', 'A category with this name already exists.');
            redirect('admin/categories');
        }
        if ($v->fails() || !Category::find($id)) {
            flash_set('error', 'Could not rename category.');
            redirect('admin/categories');
        }

        Category::rename($id, $name);
        flash_set('success', 'Category renamed.');
        redirect('admin/categories');
    }

    public function categoryToggle(): void
    {
        Auth::requireRole('super_admin');
        Csrf::requireValid();

        $id = (int)($_POST['id'] ?? 0);
        $category = Category::find($id);

        if (!$category) {
            flash_set('error', 'Category not found.');
            redirect('admin/categories');
        }

        // Deactivate-not-delete: a category with historical expenses stays
        // forever (past reports must keep reading it correctly) but can be
        // hidden from the "add expense" form going forward.
        Category::setActive($id, !$category['is_active']);
        $verb = $category['is_active'] ? 'deactivated' : 'reactivated';
        flash_set('success', "Category \"{$category['name']}\" $verb.");
        redirect('admin/categories');
    }

    public function budgetStore(): void
    {
        Auth::requireRole('super_admin');
        Csrf::requireValid();

        $categoryId = (int)($_POST['category_id'] ?? 0);
        $month = $_POST['month'] ?? date('Y-m');
        $amount = $_POST['cap_amount'] ?? '';

        $v = (new Validator())
            ->required($_POST, 'cap_amount', 'Budget cap')
            ->numeric($_POST, 'cap_amount', 'Budget cap')
            ->positive($_POST, 'cap_amount', 'Budget cap');

        if ($v->fails() || !preg_match('/^\d{4}-\d{2}$/', $month) || !Category::find($categoryId)) {
            flash_set('error', 'Could not save budget cap. Enter a valid amount.');
            redirect('admin/categories?month=' . urlencode($month));
        }

        // Budgets have no snapshot/audit trail -- budget-vs-actual is always a
        // live join against this row. Without this lock an admin could rewrite
        // what a closed month's cap "was" after that month's expenses are
        // already recorded, silently changing the household's historical
        // over/under-budget picture with no record it was ever different.
        if ($month < date('Y-m')) {
            flash_set('error', 'Budget caps for past months are locked and can no longer be edited.');
            redirect('admin/categories?month=' . urlencode($month));
        }

        Budget::upsert($categoryId, $month, (float)$amount);
        flash_set('success', 'Budget cap saved for ' . month_label($month) . '.');
        redirect('admin/categories?month=' . urlencode($month));
    }

    // ---- Household Reports ----

    public function reports(): void
    {
        Auth::requireRole('super_admin');

        $from = $_GET['from'] ?? date('Y-m-01');
        $to = $_GET['to'] ?? date('Y-m-d');
        $categoryId = (int)($_GET['category_id'] ?? 0);

        $filters = ['from' => $from, 'to' => $to];
        if ($categoryId > 0) {
            $filters['category_id'] = $categoryId;
        }

        $expenses = Expense::listHousehold($filters);
        $categories = Category::all();

        $total = array_sum(array_map(fn($e) => (float)$e['amount'], $expenses));

        // Keyed by user_id, not member_name -- member_name is free-text with
        // no uniqueness constraint (only email is UNIQUE), so two members who
        // share a display name would otherwise have their spend silently
        // merged into one row. The name is carried alongside purely for
        // display.
        $byMember = [];
        foreach ($expenses as $exp) {
            $uid = (int)$exp['user_id'];
            if (!isset($byMember[$uid])) {
                $byMember[$uid] = ['name' => $exp['member_name'], 'total' => 0.0];
            }
            $byMember[$uid]['total'] += (float)$exp['amount'];
        }
        uasort($byMember, fn($a, $b) => $b['total'] <=> $a['total']);

        $pageTitle = 'Household Reports';
        require __DIR__ . '/../views/admin/reports.php';
    }

    public function reportExport(): void
    {
        Auth::requireRole('super_admin');

        $from = $_GET['from'] ?? date('Y-m-01');
        $to = $_GET['to'] ?? date('Y-m-d');
        $categoryId = (int)($_GET['category_id'] ?? 0);

        $filters = ['from' => $from, 'to' => $to];
        if ($categoryId > 0) {
            $filters['category_id'] = $categoryId;
        }
        $expenses = Expense::listHousehold($filters);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="household-expense-report-' . $from . '-to-' . $to . '.csv"');

        $out = fopen('php://output', 'w');
        // Escape char passed explicitly (PHP 8.1+ deprecates the implicit
        // default) so the header row can never end up with a stray warning
        // spliced into the CSV output stream.
        fputcsv($out, ['Date', 'Member', 'Category', 'Amount (INR)', 'Payment Method', 'Note'], ',', '"', '\\');
        foreach ($expenses as $exp) {
            // csv_safe() neutralizes CSV/Excel formula injection (CWE-1236) --
            // note is free text a Member controls, and member/category names
            // are also user-supplied, so all three are defensively escaped.
            fputcsv($out, [
                $exp['expense_date'],
                csv_safe($exp['member_name']),
                csv_safe($exp['category_name']),
                number_format((float)$exp['amount'], 2, '.', ''),
                $exp['payment_method'],
                csv_safe($exp['note'] ?? ''),
            ], ',', '"', '\\');
        }
        fclose($out);
        exit;
    }
}
