# Prerequisites & Setup — Expense Tracker (spend-pattern insights) (Windows)

Everything below is Windows-specific. This project is plain PHP (custom MVC) with **no Composer dependencies** — no email/PDF/Excel library is used.

## 1. Install XAMPP

1. Download XAMPP with **PHP 8.1 or newer** from https://www.apachefriends.org (default install path `C:\xampp`).
2. Start **Apache** and **MySQL** from the XAMPP Control Panel.
3. Add `C:\xampp\php` and `C:\xampp\mysql\bin` to your system `Path`, then confirm `php -v` / `mysql --version` in a new terminal.

## 2. No Composer needed

This project has no `composer.json` — skip Composer entirely.

## 3. No extra PHP extensions needed

Only `pdo_mysql` is required, which is enabled by default in a standard XAMPP install.

## 4. Configure MySQL/MariaDB port

`config/database.php` is committed with `port => 3306` — XAMPP's own default MariaDB port, so no change should be needed on a standard XAMPP install. Only edit `config/database.php` if your local MySQL runs on a different port.

## 5. Database setup

```
mysql -u root -P 3306 < database/schema.sql
```
(adjust `-P` to your port, or import `database/schema.sql` via phpMyAdmin). Creates the `expense_tracker` database, tables, and demo seed data.

## 6. Verify everything

```
php -v
mysql -u root -h 127.0.0.1 -P 3306 -e "SELECT 1;"
```
Ready — see `HOW-TO-RUN.md` to start the app.
