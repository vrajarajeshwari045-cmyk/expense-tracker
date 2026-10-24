<?php

require_once __DIR__ . '/../models/Expense.php';
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../models/Budget.php';

class MemberController
{
    public function dashboard(): void
    {
        Auth::requireRole('member');

        $userId = Auth::id();
        $month = date('Y-m');
        $categories = Category::active();

        // The budget cap is a shared household constraint, so the overrun
        // check compares the HOUSEHOLD's actual spend against it here too
        // (same computation as the Admin dashboard) -- a Member should know
        // when the household is about to bust a category's budget even if
        // they didn't personally spend it, per FEATURES.md §3.
        $householdSpend = Expense::categoryTotalsForMonth($month, null);
        $budgets = Budget::forMonth($month);

        $budgetRows = [];
        foreach ($categories as $cat) {
            $spent = $householdSpend[$cat['id']] ?? 0.0;
            $cap = $budgets[$cat['id']] ?? null;
            $budgetRows[] = [
                'category' => $cat,
                'spent'    => $spent,
                'cap'      => $cap,
                'over'     => $cap !== null && $spent > $cap,
            ];
        }

        $mySpendByCategory = Expense::categoryTotalsForMonth($month, $userId);
        $myTotal = array_sum($mySpendByCategory);

        // Unusual-spend flags scoped to just this Member's own expenses.
        $myRecent = Expense::listForUser($userId, ['from' => $month . '-01']);
        $unusual = array_values(array_filter($myRecent, fn($e) =>
            Expense::isUnusual((int)$e['category_id'], (float)$e['amount'], $e['expense_date'])
        ));

        $myExpenses = array_slice($myRecent, 0, 8);

        $pageTitle = 'My Dashboard';
        require __DIR__ . '/../views/member/dashboard.php';
    }

    public function expenses(): void
    {
        Auth::requireRole('member');

        $categories = Category::active();
        $filters = [];
        if (!empty($_GET['category_id'])) {
            $filters['category_id'] = (int)$_GET['category_id'];
        }
        if (!empty($_GET['from'])) {
            $filters['from'] = $_GET['from'];
        }
        if (!empty($_GET['to'])) {
            $filters['to'] = $_GET['to'];
        }

        $myExpenses = Expense::listForUser(Auth::id(), $filters);

        // Flag unusual amounts on this page too, so it's visible wherever
        // the Member reviews their own history, not just the dashboard.
        $unusualIds = [];
        foreach ($myExpenses as $exp) {
            if (Expense::isUnusual((int)$exp['category_id'], (float)$exp['amount'], $exp['expense_date'])) {
                $unusualIds[$exp['id']] = true;
            }
        }

        $pageTitle = 'My Expenses';
        require __DIR__ . '/../views/member/expenses.php';
    }

    public function insights(): void
    {
        Auth::requireRole('member');

        $userId = Auth::id();
        $month = date('Y-m');

        $breakdown = Expense::categoryBreakdown($month, $userId);
        $trend = Expense::monthlyTotals($userId, 6);

        $myRecent = Expense::listForUser($userId, ['from' => $month . '-01']);
        $unusual = array_values(array_filter($myRecent, fn($e) =>
            Expense::isUnusual((int)$e['category_id'], (float)$e['amount'], $e['expense_date'])
        ));

        $pageTitle = 'My Spend Insights';
        require __DIR__ . '/../views/member/insights.php';
    }

    public function report(): void
    {
        Auth::requireRole('member');

        $from = $_GET['from'] ?? date('Y-m-01');
        $to = $_GET['to'] ?? date('Y-m-d');
        $categoryId = (int)($_GET['category_id'] ?? 0);

        $filters = ['from' => $from, 'to' => $to];
        if ($categoryId > 0) {
            $filters['category_id'] = $categoryId;
        }

        $expenses = Expense::listForUser(Auth::id(), $filters);
        $categories = Category::all();
        $total = array_sum(array_map(fn($e) => (float)$e['amount'], $expenses));

        $byCategory = [];
        foreach ($expenses as $exp) {
            $byCategory[$exp['category_name']] = ($byCategory[$exp['category_name']] ?? 0) + (float)$exp['amount'];
        }
        arsort($byCategory);

        $pageTitle = 'My Report';
        require __DIR__ . '/../views/member/report.php';
    }

    public function reportExport(): void
    {
        Auth::requireRole('member');

        $from = $_GET['from'] ?? date('Y-m-01');
        $to = $_GET['to'] ?? date('Y-m-d');
        $categoryId = (int)($_GET['category_id'] ?? 0);

        $filters = ['from' => $from, 'to' => $to];
        if ($categoryId > 0) {
            $filters['category_id'] = $categoryId;
        }
        $expenses = Expense::listForUser(Auth::id(), $filters);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="my-expense-report-' . $from . '-to-' . $to . '.csv"');

        $out = fopen('php://output', 'w');
        // Escape char passed explicitly (PHP 8.1+ deprecates the implicit
        // default) so the header row can never end up with a stray warning
        // spliced into the CSV output stream.
        fputcsv($out, ['Date', 'Category', 'Amount (INR)', 'Payment Method', 'Note'], ',', '"', '\\');
        foreach ($expenses as $exp) {
            // csv_safe() neutralizes CSV/Excel formula injection (CWE-1236) --
            // note is free text this member controls, and category name is
            // also defensively escaped.
            fputcsv($out, [
                $exp['expense_date'],
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
