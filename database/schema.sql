-- Kutumb -- Household Expense Tracker
-- Import via phpMyAdmin or: mysql -u root -P 3306 < schema.sql
-- Creates database, schema, and demo seed data.
-- Expense/budget dates are expressed relative to CURDATE() (DATE_SUB(...))
-- so the current-month budget-overrun and unusual-spend demo state always
-- looks fresh, however long after import the demo actually happens.

CREATE DATABASE IF NOT EXISTS expense_tracker
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE expense_tracker;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS expenses;
DROP TABLE IF EXISTS budgets;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- Schema
-- ============================================================

CREATE TABLE users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    password      VARCHAR(255) NOT NULL,
    role          ENUM('super_admin','member') NOT NULL DEFAULT 'member',
    status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE categories (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(80) NOT NULL UNIQUE,
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- One budget cap per category per calendar month ('YYYY-MM'). Deliberately
-- NOT a per-member cap -- it's a shared household limit everyone spends
-- against, per FEATURES.md §3.
CREATE TABLE budgets (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    category_id   INT NOT NULL,
    month         CHAR(7) NOT NULL,
    cap_amount    DECIMAL(10,2) NOT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_budget_cat_month (category_id, month),
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE expenses (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    category_id     INT NOT NULL,
    amount          DECIMAL(10,2) NOT NULL,
    expense_date    DATE NOT NULL,
    payment_method  ENUM('cash','card','upi','netbanking','other') NOT NULL DEFAULT 'upi',
    note            VARCHAR(255) DEFAULT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id),
    INDEX idx_expenses_user (user_id),
    INDEX idx_expenses_category_date (category_id, expense_date)
) ENGINE=InnoDB;

-- ============================================================
-- Seed data -- "Sharma Household"
-- ============================================================

-- Passwords: Admin@123 (admin), Member@123 (all members) -- bcrypt hashes
-- via PHP's password_hash(), verified against password_verify().
INSERT INTO users (id, name, email, password, role, status) VALUES
(1, 'Priya Sharma',  'admin@kutumb.test',   '$2y$12$DldXDloYkt/y.SgpP1zX5.820XLbeMD0CduHjS1Zs9xDHoVb6ZWmi', 'super_admin', 'active'),
(2, 'Rahul Sharma',  'member1@kutumb.test', '$2y$12$pptRygDIbVdpjquK82Wqf.bBzkMW74O4AbjYaxugqVyyjK8dcC39y', 'member', 'active'),
(3, 'Ananya Sharma', 'member2@kutumb.test', '$2y$12$pptRygDIbVdpjquK82Wqf.bBzkMW74O4AbjYaxugqVyyjK8dcC39y', 'member', 'active'),
(4, 'Vikram Sharma', 'member3@kutumb.test', '$2y$12$pptRygDIbVdpjquK82Wqf.bBzkMW74O4AbjYaxugqVyyjK8dcC39y', 'member', 'active'),
(5, 'Neha Sharma',   'member4@kutumb.test', '$2y$12$pptRygDIbVdpjquK82Wqf.bBzkMW74O4AbjYaxugqVyyjK8dcC39y', 'member', 'active'),
(6, 'Karan Sharma',  'member5@kutumb.test', '$2y$12$pptRygDIbVdpjquK82Wqf.bBzkMW74O4AbjYaxugqVyyjK8dcC39y', 'member', 'inactive');

-- Category 9 (Subscriptions) is deactivated below on purpose -- it already
-- has historical expenses, so it's deactivated rather than deleted
-- (deactivate-not-delete, FEATURES.md §3): still shown correctly in past
-- reports, just hidden from the "add expense" category dropdown going
-- forward.
INSERT INTO categories (id, name, is_active) VALUES
(1, 'Groceries', 1),
(2, 'Utilities', 1),
(3, 'Rent', 1),
(4, 'Dining Out', 1),
(5, 'Transport', 1),
(6, 'Entertainment', 1),
(7, 'Healthcare', 1),
(8, 'Shopping', 1),
(9, 'Subscriptions', 0);

-- Current-month budget caps. Entertainment is deliberately left WITHOUT a
-- cap (demoes the "no budget set" state); Groceries and Dining Out are
-- deliberately set low enough that this month's actual household spend
-- exceeds them (demoes the budget-overrun alert, computed live -- see
-- Expense::categoryTotalsForMonth()).
INSERT INTO budgets (category_id, month, cap_amount) VALUES
(1, DATE_FORMAT(CURDATE(), '%Y-%m'), 15000.00),  -- Groceries: actual ~17,400 -> OVER
(2, DATE_FORMAT(CURDATE(), '%Y-%m'), 6000.00),   -- Utilities: actual ~5,300 -> under
(3, DATE_FORMAT(CURDATE(), '%Y-%m'), 18500.00),  -- Rent: actual 18,000 -> under
(4, DATE_FORMAT(CURDATE(), '%Y-%m'), 3000.00),   -- Dining Out: actual ~4,500 -> OVER (driven by the unusual expense below)
(5, DATE_FORMAT(CURDATE(), '%Y-%m'), 3800.00),   -- Transport: actual ~3,430 -> under
(7, DATE_FORMAT(CURDATE(), '%Y-%m'), 3500.00),   -- Healthcare: actual ~3,100 -> under
(8, DATE_FORMAT(CURDATE(), '%Y-%m'), 6500.00);   -- Shopping: actual ~5,750 -> under

-- Expenses across ~4 months (this month + trailing 3) and all 6 users
-- (5 active + 1 intentionally-inactive), 15-25 rows per active member.
--
-- The Dining Out (category 4) rows are the unusual-spend demo: 6 trailing
-- rows in months -3..-1 give a mean of ~948 and a population stddev of
-- ~111 (threshold = mean + 2*stddev =~ 1,170); the current-month row of
-- 4,500 blows well past that threshold, so Expense::isUnusual() flags it
-- live -- nothing here is a stored flag.
INSERT INTO expenses (user_id, category_id, amount, expense_date, payment_method, note) VALUES
(2, 4, 850.00, DATE_SUB(CURDATE(), INTERVAL 111 DAY), 'upi', 'Dinner with friends'),
(3, 4, 920.00, DATE_SUB(CURDATE(), INTERVAL 98 DAY), 'card', 'Weekend brunch'),
(4, 4, 1050.00, DATE_SUB(CURDATE(), INTERVAL 82 DAY), 'upi', 'Family dinner out'),
(2, 4, 780.00, DATE_SUB(CURDATE(), INTERVAL 64 DAY), 'cash', 'Lunch with colleagues'),
(5, 4, 990.00, DATE_SUB(CURDATE(), INTERVAL 47 DAY), 'upi', 'Pizza night'),
(3, 4, 1100.00, DATE_SUB(CURDATE(), INTERVAL 31 DAY), 'card', 'Anniversary dinner'),
(2, 4, 4500.00, DATE_SUB(CURDATE(), INTERVAL 11 DAY), 'card', 'Big birthday dinner for the whole family at a fancy restaurant'),
(1, 1, 3200.00, DATE_SUB(CURDATE(), INTERVAL 23 DAY), 'upi', 'Monthly supermarket run'),
(1, 1, 2100.00, DATE_SUB(CURDATE(), INTERVAL 9 DAY), 'upi', 'Top-up groceries'),
(2, 1, 2800.00, DATE_SUB(CURDATE(), INTERVAL 20 DAY), 'card', 'Groceries + household supplies'),
(3, 1, 3400.00, DATE_SUB(CURDATE(), INTERVAL 14 DAY), 'upi', 'Big grocery haul'),
(4, 1, 2600.00, DATE_SUB(CURDATE(), INTERVAL 6 DAY), 'cash', 'Vegetables and groceries'),
(5, 1, 3300.00, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'upi', 'Grocery + fruits'),
(1, 1, 6200.00, DATE_SUB(CURDATE(), INTERVAL 53 DAY), 'upi', 'Monthly groceries'),
(2, 1, 5100.00, DATE_SUB(CURDATE(), INTERVAL 44 DAY), 'card', 'Groceries'),
(3, 1, 5800.00, DATE_SUB(CURDATE(), INTERVAL 36 DAY), 'upi', 'Groceries'),
(1, 1, 6100.00, DATE_SUB(CURDATE(), INTERVAL 82 DAY), 'upi', 'Monthly groceries'),
(4, 1, 4900.00, DATE_SUB(CURDATE(), INTERVAL 71 DAY), 'cash', 'Groceries'),
(5, 1, 5300.00, DATE_SUB(CURDATE(), INTERVAL 62 DAY), 'upi', 'Groceries'),
(1, 1, 5900.00, DATE_SUB(CURDATE(), INTERVAL 112 DAY), 'upi', 'Monthly groceries'),
(2, 1, 5200.00, DATE_SUB(CURDATE(), INTERVAL 100 DAY), 'card', 'Groceries'),
(1, 2, 2400.00, DATE_SUB(CURDATE(), INTERVAL 18 DAY), 'netbanking', 'Electricity bill'),
(1, 2, 900.00, DATE_SUB(CURDATE(), INTERVAL 17 DAY), 'netbanking', 'Water bill'),
(1, 2, 1200.00, DATE_SUB(CURDATE(), INTERVAL 15 DAY), 'upi', 'Internet + broadband'),
(1, 2, 2300.00, DATE_SUB(CURDATE(), INTERVAL 50 DAY), 'netbanking', 'Electricity bill'),
(1, 2, 1150.00, DATE_SUB(CURDATE(), INTERVAL 47 DAY), 'upi', 'Internet + broadband'),
(1, 2, 2500.00, DATE_SUB(CURDATE(), INTERVAL 79 DAY), 'netbanking', 'Electricity bill'),
(1, 2, 1100.00, DATE_SUB(CURDATE(), INTERVAL 76 DAY), 'upi', 'Internet + broadband'),
(1, 2, 2350.00, DATE_SUB(CURDATE(), INTERVAL 111 DAY), 'netbanking', 'Electricity bill'),
(1, 3, 18000.00, DATE_SUB(CURDATE(), INTERVAL 24 DAY), 'netbanking', 'Monthly house rent'),
(1, 3, 18000.00, DATE_SUB(CURDATE(), INTERVAL 55 DAY), 'netbanking', 'Monthly house rent'),
(1, 3, 18000.00, DATE_SUB(CURDATE(), INTERVAL 85 DAY), 'netbanking', 'Monthly house rent'),
(1, 3, 18000.00, DATE_SUB(CURDATE(), INTERVAL 116 DAY), 'netbanking', 'Monthly house rent'),
(2, 5, 450.00, DATE_SUB(CURDATE(), INTERVAL 22 DAY), 'upi', 'Cab to office'),
(3, 5, 620.00, DATE_SUB(CURDATE(), INTERVAL 16 DAY), 'upi', 'Fuel top-up'),
(4, 5, 380.00, DATE_SUB(CURDATE(), INTERVAL 11 DAY), 'cash', 'Auto fare'),
(5, 5, 700.00, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'card', 'Fuel top-up'),
(2, 5, 500.00, DATE_SUB(CURDATE(), INTERVAL 48 DAY), 'upi', 'Cab rides'),
(3, 5, 640.00, DATE_SUB(CURDATE(), INTERVAL 40 DAY), 'upi', 'Fuel top-up'),
(4, 5, 410.00, DATE_SUB(CURDATE(), INTERVAL 74 DAY), 'cash', 'Auto fare'),
(5, 5, 680.00, DATE_SUB(CURDATE(), INTERVAL 99 DAY), 'card', 'Fuel top-up'),
(3, 6, 600.00, DATE_SUB(CURDATE(), INTERVAL 19 DAY), 'upi', 'Movie night'),
(4, 6, 1200.00, DATE_SUB(CURDATE(), INTERVAL 12 DAY), 'card', 'Concert tickets'),
(2, 6, 450.00, DATE_SUB(CURDATE(), INTERVAL 4 DAY), 'upi', 'Streaming subscription top-up'),
(3, 6, 550.00, DATE_SUB(CURDATE(), INTERVAL 45 DAY), 'upi', 'Movie night'),
(5, 6, 800.00, DATE_SUB(CURDATE(), INTERVAL 77 DAY), 'card', 'Bowling with friends'),
(5, 7, 950.00, DATE_SUB(CURDATE(), INTERVAL 20 DAY), 'upi', 'Pharmacy'),
(1, 7, 1800.00, DATE_SUB(CURDATE(), INTERVAL 8 DAY), 'card', 'Doctor consultation'),
(4, 7, 620.00, DATE_SUB(CURDATE(), INTERVAL 42 DAY), 'upi', 'Pharmacy'),
(2, 7, 1400.00, DATE_SUB(CURDATE(), INTERVAL 64 DAY), 'netbanking', 'Dental checkup'),
(3, 8, 2200.00, DATE_SUB(CURDATE(), INTERVAL 17 DAY), 'card', 'Clothes shopping'),
(5, 8, 1500.00, DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'upi', 'Home decor'),
(4, 8, 3100.00, DATE_SUB(CURDATE(), INTERVAL 46 DAY), 'card', 'Festive shopping'),
(2, 8, 900.00, DATE_SUB(CURDATE(), INTERVAL 80 DAY), 'upi', 'Shoes'),
(3, 8, 1700.00, DATE_SUB(CURDATE(), INTERVAL 103 DAY), 'card', 'Clothes shopping'),
(1, 9, 500.00, DATE_SUB(CURDATE(), INTERVAL 54 DAY), 'card', 'Streaming bundle (now cancelled)'),
(2, 9, 300.00, DATE_SUB(CURDATE(), INTERVAL 84 DAY), 'card', 'Music subscription (now cancelled)'),
(1, 9, 500.00, DATE_SUB(CURDATE(), INTERVAL 84 DAY), 'card', 'Streaming bundle (now cancelled)'),
(1, 9, 500.00, DATE_SUB(CURDATE(), INTERVAL 115 DAY), 'card', 'Streaming bundle (now cancelled)'),
(6, 1, 2400.00, DATE_SUB(CURDATE(), INTERVAL 47 DAY), 'upi', 'Groceries'),
(6, 5, 500.00, DATE_SUB(CURDATE(), INTERVAL 41 DAY), 'cash', 'Auto fare'),
(6, 4, 700.00, DATE_SUB(CURDATE(), INTERVAL 75 DAY), 'upi', 'Dinner out'),
(6, 8, 1200.00, DATE_SUB(CURDATE(), INTERVAL 67 DAY), 'card', 'Shopping'),
(6, 2, 900.00, DATE_SUB(CURDATE(), INTERVAL 109 DAY), 'netbanking', 'Utilities share'),
(2, 7, 350.00, CURDATE(), 'upi', 'Pharmacy'),
(2, 6, 300.00, DATE_SUB(CURDATE(), INTERVAL 32 DAY), 'upi', 'Movie'),
(2, 2, 400.00, CURDATE(), 'upi', 'Mobile recharge'),
(3, 5, 300.00, CURDATE(), 'upi', 'Cab'),
(3, 7, 500.00, DATE_SUB(CURDATE(), INTERVAL 51 DAY), 'card', 'Pharmacy'),
(3, 2, 400.00, DATE_SUB(CURDATE(), INTERVAL 33 DAY), 'upi', 'Mobile recharge'),
(4, 2, 400.00, CURDATE(), 'upi', 'Mobile recharge'),
(4, 6, 350.00, DATE_SUB(CURDATE(), INTERVAL 69 DAY), 'upi', 'Streaming subscription'),
(4, 5, 420.00, DATE_SUB(CURDATE(), INTERVAL 96 DAY), 'cash', 'Auto fare'),
(5, 2, 400.00, DATE_SUB(CURDATE(), INTERVAL 29 DAY), 'upi', 'Mobile recharge'),
(5, 8, 650.00, DATE_SUB(CURDATE(), INTERVAL 73 DAY), 'upi', 'Accessories'),
(5, 4, 550.00, DATE_SUB(CURDATE(), INTERVAL 58 DAY), 'card', 'Cafe with friends'),
(1, 8, 2200.00, DATE_SUB(CURDATE(), INTERVAL 35 DAY), 'card', 'Home essentials'),
(1, 6, 700.00, DATE_SUB(CURDATE(), INTERVAL 29 DAY), 'upi', 'Family outing'),
(1, 5, 600.00, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'upi', 'Fuel'),
(3, 8, 950.00, CURDATE(), 'upi', 'Accessories'),
(3, 5, 480.00, DATE_SUB(CURDATE(), INTERVAL 53 DAY), 'cash', 'Cab rides'),
(3, 7, 300.00, DATE_SUB(CURDATE(), INTERVAL 108 DAY), 'upi', 'Pharmacy'),
(4, 1, 2900.00, DATE_SUB(CURDATE(), INTERVAL 48 DAY), 'upi', 'Groceries'),
(4, 8, 1100.00, DATE_SUB(CURDATE(), INTERVAL 4 DAY), 'card', 'Shoes'),
(4, 7, 400.00, DATE_SUB(CURDATE(), INTERVAL 98 DAY), 'upi', 'Pharmacy'),
(4, 4, 620.00, DATE_SUB(CURDATE(), INTERVAL 91 DAY), 'card', 'Cafe visit'),
(5, 1, 2700.00, DATE_SUB(CURDATE(), INTERVAL 42 DAY), 'upi', 'Groceries'),
(5, 5, 380.00, DATE_SUB(CURDATE(), INTERVAL 13 DAY), 'cash', 'Auto fare'),
(5, 6, 500.00, DATE_SUB(CURDATE(), INTERVAL 109 DAY), 'upi', 'Movie night'),
(5, 7, 700.00, DATE_SUB(CURDATE(), INTERVAL 95 DAY), 'card', 'Doctor visit');
