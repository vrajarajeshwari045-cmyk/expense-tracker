<?php

// $value is untyped (not `?string`) on purpose: a client can submit an
// array-typed field (e.g. "email[]=x"), and a $_POST/$_GET value flows into
// e() in plenty of places with no scalar guard upstream. A `?string` type
// hint would let that array reach here and throw an uncaught TypeError: PHP
// never coerces array-to-string across a typed parameter boundary, no matter
// the strict_types setting. Anything that isn't a string/int/float/bool is
// rendered as empty rather than crashing the request.
function e($value): string
{
    if (!is_string($value)) {
        $value = is_scalar($value) ? (string)$value : '';
    }
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// Coerces a request field to a plain string, defaulting non-string values
// (arrays included) to $default instead of letting them reach trim()/strlen()
// and the like, which throw a TypeError on an array argument.
function request_str($value, string $default = ''): string
{
    return is_string($value) ? $value : $default;
}

// Neutralizes CSV/Excel formula injection (CWE-1236): a cell whose text
// starts with =, +, -, @, tab, or CR is evaluated as a formula by Excel/
// LibreOffice/Sheets instead of shown as plain text. Prefixing it with a
// straight quote forces text interpretation while leaving the visible value
// unchanged. Apply to every user-supplied cell written via fputcsv().
function csv_safe(?string $value): string
{
    $value = $value ?? '';
    if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value)) {
        return "'" . $value;
    }
    return $value;
}

function redirect(string $route): void
{
    header('Location: /index.php?route=' . $route);
    exit;
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][$type] = $message;
}

function flash_get(string $type): ?string
{
    if (empty($_SESSION['flash'][$type])) {
        return null;
    }
    $message = $_SESSION['flash'][$type];
    unset($_SESSION['flash'][$type]);
    return $message;
}

function old(array $data, string $key, string $default = ''): string
{
    return e($data[$key] ?? $default);
}

function format_date(?string $date): string
{
    if (empty($date)) {
        return '-';
    }
    $ts = strtotime($date);
    return $ts === false ? '-' : date('d M Y', $ts);
}

function format_datetime(?string $datetime): string
{
    if (empty($datetime)) {
        return '-';
    }
    $ts = strtotime($datetime);
    return $ts === false ? '-' : date('d M Y, h:i A', $ts);
}

// ₹ (INR) formatting -- comma grouping in the Indian numbering style
// (last 3 digits, then groups of 2): 12,34,567.89
function format_inr(float $amount): string
{
    $negative = $amount < 0;
    $amount = abs($amount);
    $parts = explode('.', number_format($amount, 2, '.', ''));
    $whole = $parts[0];
    $decimal = $parts[1];

    if (strlen($whole) > 3) {
        $lastThree = substr($whole, -3);
        $rest = substr($whole, 0, -3);
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
        $whole = $rest . ',' . $lastThree;
    }

    return ($negative ? '-' : '') . '₹' . $whole . '.' . $decimal;
}

function month_label(string $ym): string
{
    $ts = DateTime::createFromFormat('Y-m-d', $ym . '-01');
    return $ts ? $ts->format('M Y') : $ym;
}
