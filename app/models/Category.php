<?php

class Category
{
    public static function all(): array
    {
        $stmt = Database::connect()->query('SELECT * FROM categories ORDER BY is_active DESC, name');
        return $stmt->fetchAll();
    }

    public static function active(): array
    {
        $stmt = Database::connect()->query('SELECT * FROM categories WHERE is_active = 1 ORDER BY name');
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connect()->prepare('SELECT * FROM categories WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function nameExists(string $name, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $stmt = Database::connect()->prepare('SELECT id FROM categories WHERE name = ? AND id != ? LIMIT 1');
            $stmt->execute([$name, $excludeId]);
        } else {
            $stmt = Database::connect()->prepare('SELECT id FROM categories WHERE name = ? LIMIT 1');
            $stmt->execute([$name]);
        }
        return (bool)$stmt->fetch();
    }

    public static function create(string $name): int
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare('INSERT INTO categories (name, is_active) VALUES (?, 1)');
        $stmt->execute([$name]);
        return (int)$pdo->lastInsertId();
    }

    public static function rename(int $id, string $name): void
    {
        $stmt = Database::connect()->prepare('UPDATE categories SET name = ? WHERE id = ?');
        $stmt->execute([$name, $id]);
    }

    public static function setActive(int $id, bool $active): void
    {
        $stmt = Database::connect()->prepare('UPDATE categories SET is_active = ? WHERE id = ?');
        $stmt->execute([$active ? 1 : 0, $id]);
    }

    // A category with any historical expenses can only be deactivated, never
    // deleted -- deleting it would corrupt past reports (FEATURES.md §3).
    public static function hasExpenses(int $id): bool
    {
        $stmt = Database::connect()->prepare('SELECT id FROM expenses WHERE category_id = ? LIMIT 1');
        $stmt->execute([$id]);
        return (bool)$stmt->fetch();
    }
}
