<?php

declare(strict_types=1);

// Let PHP's built-in dev server (`php -S`) serve real static files directly
// instead of routing every asset request through the front controller.
if (PHP_SAPI === 'cli-server') {
    $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $assetPath = __DIR__ . $requestPath;
    if (!str_ends_with($requestPath, '.php') && is_file($assetPath)) {
        return false;
    }
}

date_default_timezone_set('Asia/Kolkata');
ini_set('display_errors', '1');
error_reporting(E_ALL);

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
]);

require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Csrf.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../core/Router.php';

require_once __DIR__ . '/../app/models/User.php';
require_once __DIR__ . '/../app/models/Category.php';
require_once __DIR__ . '/../app/models/Budget.php';
require_once __DIR__ . '/../app/models/Expense.php';

require_once __DIR__ . '/../app/controllers/AuthController.php';
require_once __DIR__ . '/../app/controllers/AdminController.php';
require_once __DIR__ . '/../app/controllers/MemberController.php';
require_once __DIR__ . '/../app/controllers/ExpenseController.php';

$router = new Router();

// ---- Auth ----
$router->get('login', fn() => (new AuthController())->showLogin());
$router->post('login', fn() => (new AuthController())->login());
$router->post('logout', fn() => (new AuthController())->logout());

// ---- Super Admin ----
$router->get('admin/dashboard', fn() => (new AdminController())->dashboard());
$router->get('admin/members', fn() => (new AdminController())->members());
$router->post('admin/members/store', fn() => (new AdminController())->memberStore());
$router->post('admin/members/status', fn() => (new AdminController())->memberStatus());
$router->get('admin/categories', fn() => (new AdminController())->categories());
$router->post('admin/categories/store', fn() => (new AdminController())->categoryStore());
$router->post('admin/categories/rename', fn() => (new AdminController())->categoryRename());
$router->post('admin/categories/toggle', fn() => (new AdminController())->categoryToggle());
$router->post('admin/budgets/store', fn() => (new AdminController())->budgetStore());
$router->get('admin/reports', fn() => (new AdminController())->reports());
$router->get('admin/reports/export', fn() => (new AdminController())->reportExport());

// ---- Member ----
$router->get('member/dashboard', fn() => (new MemberController())->dashboard());
$router->get('member/expenses', fn() => (new MemberController())->expenses());
$router->get('member/insights', fn() => (new MemberController())->insights());
$router->get('member/report', fn() => (new MemberController())->report());
$router->get('member/report/export', fn() => (new MemberController())->reportExport());

// ---- Shared "own expense" CRUD (both personas -- ownership enforced in the model layer) ----
$router->post('expenses/store', fn() => (new ExpenseController())->store());
$router->post('expenses/update', fn() => (new ExpenseController())->update());
$router->post('expenses/delete', fn() => (new ExpenseController())->delete());

// request_str() guards against an array-typed "route" query param (e.g.
// "?route[]=x") reaching Router::dispatch()'s `string $route` parameter --
// with strict_types=1 in this file, that would throw an uncaught TypeError
// on any GET request, before Router's own try/catch ever runs.
$route = request_str($_GET['route'] ?? '');

if ($route === '') {
    if (Auth::check()) {
        redirect(Auth::dashboardRoute(Auth::role()));
    }
    redirect('login');
}

$router->dispatch($_SERVER['REQUEST_METHOD'], $route);
