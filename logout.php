<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$user = current_user();
if ($user) {
    audit_log((int) $user['id'], 'logout', 'users', (int) $user['id']);
}

logout_user();
session_start();
set_flash('success', 'You have been logged out.');
redirect('login.php');

