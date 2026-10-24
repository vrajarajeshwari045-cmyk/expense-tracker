# How to Run — Expense Tracker (spend-pattern insights)

Assumes prerequisites in `PREREQUISITES.md` are already done. No Composer step — this project has no dependencies.

## One-time setup

```
mysql -u root -P 3306 < database/schema.sql
```
(or import `database/schema.sql` via phpMyAdmin)

To wipe the seeded data and start over, drop the database first, then re-run the import above (re-running it directly without dropping first fails with "table already exists"):
```
mysql -u root -P 3306 -e "DROP DATABASE IF EXISTS expense_tracker;"
```

## Start MySQL

Open **XAMPP Control Panel** and make sure **MySQL** is running.

## Run the app

From this project's folder:
```
php -S localhost:8013 -t public public/index.php
```
(`-t public` is required — without it, assets under `public/assets` 404.)

## Open it

Visit http://localhost:8013/index.php?route=login and sign in with any of the demo credentials in `README.md` under "Default Login Credentials".

## Stop the app

`Ctrl+C` in the terminal running the `php -S` command.
