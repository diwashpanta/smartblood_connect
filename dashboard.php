<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
redirect(role_home_path((string) $user['role']));

