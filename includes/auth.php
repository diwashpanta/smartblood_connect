<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('warning', 'Please log in to continue.');
        redirect('login.php');
    }
}

function require_role(string|array $roles): void
{
    require_login();
    if (!has_role($roles)) {
        set_flash('danger', 'You are not authorized to access that page.');
        $user = current_user();
        redirect(role_home_path($user['role'] ?? 'patient'));
    }
}

function ensure_guest(): void
{
    if (is_logged_in()) {
        $user = current_user();
        redirect(role_home_path($user['role'] ?? 'patient'));
    }
}

