<?php

class Budget
{
    // Returns [category_id => cap_amount] for one month ('YYYY-MM').
    public static function forMonth(string $month): array
    {
        $stmt = Database::connect()->prepare('SELECT category_id, cap_amount FROM budgets WHERE month = ?');
        $stmt->execute([$month]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(int)$row['category_id']] = (float)$row['cap_amount'];
        }
        return $out;
    }

    public static function get(int $categoryId, string $month): ?float
    {
        $stmt = Database::connect()->prepare('SELECT cap_amount FROM budgets WHERE category_id = ? AND month = ? LIMIT 1');
        $stmt->execute([$categoryId, $month]);
        $row = $stmt->fetch();
        return $row ? (float)$row['cap_amount'] : null;
    }

    // Insert-or-update the cap for one category+month.
    public static function upsert(int $categoryId, string $month, float $amount): void
    {
        $stmt = Database::connect()->prepare(
            'INSERT INTO budgets (category_id, month, cap_amount) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE cap_amount = VALUES(cap_amount)'
        );
        $stmt->execute([$categoryId, $month, $amount]);
    }
}
