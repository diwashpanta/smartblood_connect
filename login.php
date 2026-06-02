<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
ensure_guest();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_with_csrf();

    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        set_flash('danger', 'Please enter a valid email and password.');
        redirect('login.php');
    }

    $user = db_fetch_one("SELECT * FROM users WHERE email = ? LIMIT 1", [$email]);
    if (!$user || !password_verify($password, $user['password_hash'])) {
        set_flash('danger', 'Invalid login credentials.');
        redirect('login.php');
    }

    if (($user['status'] ?? 'active') !== 'active') {
        set_flash('warning', 'Your account is currently inactive. Contact admin.');
        redirect('login.php');
    }

    db_query("UPDATE users SET last_login_at = NOW() WHERE id = ?", [(int) $user['id']]);
    login_user($user);
    audit_log((int) $user['id'], 'login', 'users', (int) $user['id'], ['ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    redirect(role_home_path((string) $user['role']));
}

$pageTitle = 'Login';
$showSidebar = false;
$mainContainerClass = 'container py-4 py-md-5';
include __DIR__ . '/includes/header.php';
?>

<div class="auth-wrap">
    <form method="post" class="card-soft p-4 p-md-5">
        <?= csrf_field() ?>
        <h1 class="h2 mb-3">Welcome Back</h1>
        <p class="text-muted mb-4">Sign in to access your dashboard.</p>
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-danger w-100">Login</button>
        <p class="small text-muted mt-3 mb-0">New here? <a href="<?= e(url('register.php')) ?>">Create an account</a></p>
    </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

