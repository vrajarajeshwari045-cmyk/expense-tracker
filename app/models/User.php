<?php

class User
{
    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::connect()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connect()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // Every household login except the admin themselves -- used for the
    // "Manage Members" screen and for household-wide roll-ups.
    public static function allMembers(): array
    {
        $stmt = Database::connect()->query(
            "SELECT * FROM users WHERE role = 'member' ORDER BY name"
        );
        return $stmt->fetchAll();
    }

    // All household logins (admin + members) -- used when the report needs
    // a per-person breakdown across the whole household.
    public static function allHousehold(): array
    {
        $stmt = Database::connect()->query('SELECT * FROM users ORDER BY role DESC, name');
        return $stmt->fetchAll();
    }

    public static function emailExists(string $email): bool
    {
        $stmt = Database::connect()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        return (bool)$stmt->fetch();
    }

    public static function create(string $name, string $email, string $password, string $role = 'member'): int
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare(
            'INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, "active")'
        );
        $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
        return (int)$pdo->lastInsertId();
    }

    public static function setStatus(int $id, string $status): void
    {
        $stmt = Database::connect()->prepare('UPDATE users SET status = ? WHERE id = ? AND role = "member"');
        $stmt->execute([$status, $id]);
    }
}
