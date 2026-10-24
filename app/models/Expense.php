<?php

class Expense
{
    public static function find(int $id): ?array
    {
        $stmt = Database::connect()->prepare(
            'SELECT e.*, c.name AS category_name, u.name AS member_name
             FROM expenses e
             JOIN categories c ON c.id = e.category_id
             JOIN users u ON u.id = e.user_id
             WHERE e.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // Ownership-scoped lookup -- a Member can only ever fetch their OWN
    // expense this way. Used before every edit/delete so a Member can't
    // reach another member's row by tampering with the ?id= in the URL,
    // even though the SQL WHERE below is the real enforcement, not this
    // convenience wrapper (FEATURES.md §3 "role-scoped queries").
    public static function findOwned(int $id, int $userId): ?array
    {
        $stmt = Database::connect()->prepare(
            'SELECT e.*, c.name AS category_name
             FROM expenses e
             JOIN categories c ON c.id = e.category_id
             WHERE e.id = ? AND e.user_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(int $userId, int $categoryId, float $amount, string $date, string $method, ?string $note): int
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare(
            'INSERT INTO expenses (user_id, category_id, amount, expense_date, payment_method, note)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $categoryId, $amount, $date, $method, $note]);
        return (int)$pdo->lastInsertId();
    }

    // Scoped by id AND user_id in the WHERE clause itself -- a forged
    // request for someone else's expense id simply updates zero rows.
    public static function updateOwned(int $id, int $userId, int $categoryId, float $amount, string $date, string $method, ?string $note): bool
    {
        $stmt = Database::connect()->prepare(
            'UPDATE expenses SET category_id = ?, amount = ?, expense_date = ?, payment_method = ?, note = ?
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$categoryId, $amount, $date, $method, $note, $id, $userId]);
        return $stmt->rowCount() > 0;
    }

    public static function deleteOwned(int $id, int $userId): bool
    {
        $stmt = Database::connect()->prepare('DELETE FROM expenses WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        return $stmt->rowCount() > 0;
    }

    // Member's own expense list, newest first, optionally filtered.
    public static function listForUser(int $userId, array $filters = []): array
    {
        $sql = 'SELECT e.*, c.name AS category_name
                FROM expenses e JOIN categories c ON c.id = e.category_id
                WHERE e.user_id = ?';
        $params = [$userId];
        self::applyFilters($sql, $params, $filters);
        $sql .= ' ORDER BY e.expense_date DESC, e.id DESC';

        $stmt = Database::connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // Whole-household expense list (Admin only), newest first.
    public static function listHousehold(array $filters = []): array
    {
        $sql = 'SELECT e.*, c.name AS category_name, u.name AS member_name
                FROM expenses e
                JOIN categories c ON c.id = e.category_id
                JOIN users u ON u.id = e.user_id
                WHERE 1 = 1';
        $params = [];
        self::applyFilters($sql, $params, $filters);
        $sql .= ' ORDER BY e.expense_date DESC, e.id DESC';

        $stmt = Database::connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private static function applyFilters(string &$sql, array &$params, array $filters): void
    {
        if (!empty($filters['category_id'])) {
            $sql .= ' AND e.category_id = ?';
            $params[] = (int)$filters['category_id'];
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND e.expense_date >= ?';
            $params[] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND e.expense_date <= ?';
            $params[] = $filters['to'];
        }
        if (!empty($filters['user_id'])) {
            $sql .= ' AND e.user_id = ?';
            $params[] = (int)$filters['user_id'];
        }
    }

    // Sum of expenses per category for one calendar month ('YYYY-MM').
    // $userId = null means household-wide (Admin view); otherwise scoped
    // to one member -- this is the live query behind the budget-overrun
    // alert, computed fresh on every page load, never stored.
    public static function categoryTotalsForMonth(string $month, ?int $userId = null): array
    {
        $sql = "SELECT category_id, SUM(amount) AS total
                FROM expenses
                WHERE DATE_FORMAT(expense_date, '%Y-%m') = ?";
        $params = [$month];
        if ($userId !== null) {
            $sql .= ' AND user_id = ?';
            $params[] = $userId;
        }
        $sql .= ' GROUP BY category_id';

        $stmt = Database::connect()->prepare($sql);
        $stmt->execute($params);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(int)$row['category_id']] = (float)$row['total'];
        }
        return $out;
    }

    // Total spend per calendar month over the trailing $months months
    // (including the current one) -- feeds the month-over-month trend chart.
    public static function monthlyTotals(?int $userId, int $months = 6): array
    {
        $sql = "SELECT DATE_FORMAT(expense_date, '%Y-%m') AS ym, SUM(amount) AS total
                FROM expenses
                WHERE expense_date >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL ? MONTH)";
        $params = [$months - 1];
        if ($userId !== null) {
            $sql .= ' AND user_id = ?';
            $params[] = $userId;
        }
        $sql .= ' GROUP BY ym ORDER BY ym';

        $stmt = Database::connect()->prepare($sql);
        $stmt->execute($params);

        $totals = [];
        foreach ($stmt->fetchAll() as $row) {
            $totals[$row['ym']] = (float)$row['total'];
        }

        // Fill in any silent months with 0 so the chart's x-axis is continuous.
        $out = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $ym = date('Y-m', strtotime("first day of -$i month"));
            $out[$ym] = $totals[$ym] ?? 0.0;
        }
        return $out;
    }

    // Category breakdown for the current month (or a given month), used by
    // the spend-pattern doughnut chart.
    public static function categoryBreakdown(string $month, ?int $userId = null): array
    {
        $sql = "SELECT c.id, c.name, COALESCE(SUM(e.amount), 0) AS total
                FROM categories c
                LEFT JOIN expenses e ON e.category_id = c.id
                    AND DATE_FORMAT(e.expense_date, '%Y-%m') = ?"
                . ($userId !== null ? ' AND e.user_id = ?' : '') .
                " GROUP BY c.id, c.name
                HAVING total > 0
                ORDER BY total DESC";
        $params = [$month];
        if ($userId !== null) {
            $params[] = $userId;
        }
        $stmt = Database::connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // Trailing 3-FULL-month mean + population standard deviation for one
    // category, household-wide, ending the month BEFORE $referenceDate's
    // month (so a newly logged expense never skews its own baseline).
    // Returns null when there isn't enough history (< 3 data points) to
    // make the comparison meaningful -- FEATURES.md §3: never a stored flag,
    // always this live computation.
    public static function trailingStats(int $categoryId, string $referenceDate): ?array
    {
        $sql = "SELECT amount FROM expenses
                WHERE category_id = ?
                  AND expense_date >= DATE_SUB(DATE_FORMAT(?, '%Y-%m-01'), INTERVAL 3 MONTH)
                  AND expense_date < DATE_FORMAT(?, '%Y-%m-01')";
        $stmt = Database::connect()->prepare($sql);
        $stmt->execute([$categoryId, $referenceDate, $referenceDate]);
        $amounts = array_map('floatval', array_column($stmt->fetchAll(), 'amount'));

        $n = count($amounts);
        if ($n < 3) {
            return null;
        }

        $mean = array_sum($amounts) / $n;
        $variance = array_sum(array_map(fn($a) => ($a - $mean) ** 2, $amounts)) / $n;
        $stddev = sqrt($variance);

        return ['mean' => $mean, 'stddev' => $stddev, 'count' => $n, 'threshold' => $mean + 2 * $stddev];
    }

    public static function isUnusual(int $categoryId, float $amount, string $referenceDate): bool
    {
        $stats = self::trailingStats($categoryId, $referenceDate);
        return $stats !== null && $amount > $stats['threshold'];
    }
}
