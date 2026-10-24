<?php

require_once __DIR__ . '/../models/User.php';

class AuthController
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            redirect(Auth::dashboardRoute(Auth::role()));
        }
        $pageTitle = 'Login';
        require __DIR__ . '/../views/auth/login.php';
    }

    public function login(): void
    {
        Csrf::requireValid();

        // request_str() guards against an array-typed field (e.g. "email[]=x")
        // reaching trim(), which throws an uncaught TypeError on an array.
        $email = trim(request_str($_POST['email'] ?? ''));
        $password = request_str($_POST['password'] ?? '');

        $v = (new Validator())
            ->required($_POST, 'email', 'Email')
            ->email($_POST, 'email', 'Email')
            ->required($_POST, 'password', 'Password');

        if ($v->fails()) {
            flash_set('error', 'Please enter a valid email and password.');
            redirect('login');
        }

        if (!Auth::attempt($email, $password)) {
            flash_set('error', 'Invalid credentials, or this account is inactive. Contact your Household Admin.');
            redirect('login');
        }

        redirect(Auth::dashboardRoute(Auth::role()));
    }

    public function logout(): void
    {
        Csrf::requireValid();
        Auth::logout();
        redirect('login');
    }
}
