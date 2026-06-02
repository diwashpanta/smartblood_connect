<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$admin = current_user();
$search = trim((string) ($_GET['q'] ?? ''));
$roleFilter = trim((string) ($_GET['role'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_with_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_status') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $row = db_fetch_one("SELECT status FROM users WHERE id = ? AND id <> ?", [$userId, (int) $admin['id']]);
        if ($row) {
            $next = $row['status'] === 'active' ? 'inactive' : 'active';
            db_query("UPDATE users SET status = ? WHERE id = ?", [$next, $userId]);
            audit_log((int) $admin['id'], 'user_status_changed', 'users', $userId, ['status' => $next]);
            set_flash('success', "User status changed to {$next}.");
        }
        redirect('admin/users.php');
    }

    if ($action === 'create_user') {
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = $_POST['password'] ?? '';
        $role = trim((string) ($_POST['role'] ?? 'patient'));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $city = trim((string) ($_POST['city'] ?? ''));
        $bloodGroup = trim((string) ($_POST['blood_group'] ?? 'O+'));
        $age = (int) ($_POST['age'] ?? 25);
        $weight = (float) ($_POST['weight'] ?? 55);

        $errors = [];
        if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid full name and email are required.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if (!in_array($role, ['patient', 'donor', 'admin'], true)) {
            $errors[] = 'Invalid role selected.';
        }
        if (!in_array($bloodGroup, blood_groups(), true)) {
            $errors[] = 'Invalid blood group.';
        }

        if (!$errors && db_fetch_one("SELECT id FROM users WHERE email = ?", [$email])) {
            $errors[] = 'Email already exists.';
        }

        if ($errors) {
            foreach ($errors as $error) {
                set_flash('danger', $error);
            }
            redirect('admin/users.php');
        }

        $pdo = db();
        try {
            $pdo->beginTransaction();
            db_query(
                "INSERT INTO users (full_name, email, password_hash, role, phone, city, blood_group, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'active')",
                [$fullName, $email, password_hash($password, PASSWORD_DEFAULT), $role, $phone ?: null, $city ?: null, $bloodGroup]
            );
            $newUserId = (int) $pdo->lastInsertId();

            if ($role === 'patient') {
                db_query(
                    "INSERT INTO patients (user_id, age, gender) VALUES (?, ?, 'other')",
                    [$newUserId, max(1, $age)]
                );
            } elseif ($role === 'donor') {
                db_query(
                    "INSERT INTO donors (user_id, age, weight, medical_condition_status, availability_status, is_verified)
                     VALUES (?, ?, ?, 'healthy', 'available', 1)",
                    [$newUserId, max(18, $age), max(45, $weight)]
                );
            } else {
                db_query("INSERT INTO admins (user_id, designation) VALUES (?, 'Admin User')", [$newUserId]);
            }

            $pdo->commit();
            audit_log((int) $admin['id'], 'user_created', 'users', $newUserId, ['role' => $role]);
            set_flash('success', 'User created successfully.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash('danger', 'Failed to create user.');
        }
        redirect('admin/users.php');
    }
}

$sql = "SELECT id, full_name, email, role, phone, city, blood_group, status, created_at FROM users WHERE 1=1";
$params = [];
if ($roleFilter !== '') {
    $sql .= " AND role = ?";
    $params[] = $roleFilter;
}
if ($search !== '') {
    $sql .= " AND (full_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $needle = '%' . $search . '%';
    $params[] = $needle;
    $params[] = $needle;
    $params[] = $needle;
}
$sql .= " ORDER BY created_at DESC";
$users = db_fetch_all($sql, $params);

$pageTitle = 'Manage Users';
$showSidebar = true;
$mainContainerClass = 'container-fluid py-4';
include __DIR__ . '/../includes/header.php';
?>

<section class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card-soft p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="h3 mb-0">Users</h1>
                <form method="get" class="d-flex gap-2">
                    <input type="text" name="q" class="form-control form-control-sm" placeholder="Search user..." value="<?= e($search) ?>">
                    <select name="role" class="form-select form-select-sm">
                        <option value="">All Roles</option>
                        <?php foreach (['admin','patient','donor'] as $role): ?>
                            <option value="<?= e($role) ?>" <?= $roleFilter === $role ? 'selected' : '' ?>><?= e(ucfirst($role)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-sm btn-outline-secondary">Filter</button>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-modern align-middle">
                    <thead>
                    <tr><th>Name</th><th>Email</th><th>Role</th><th>Phone</th><th>Blood</th><th>Status</th><th>Created</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $row): ?>
                        <tr>
                            <td><?= e($row['full_name']) ?><div class="small text-muted"><?= e($row['city']) ?></div></td>
                            <td><?= e($row['email']) ?></td>
                            <td><span class="badge bg-light text-dark"><?= e(ucfirst($row['role'])) ?></span></td>
                            <td><?= e($row['phone']) ?></td>
                            <td><?= e((string) $row['blood_group']) ?></td>
                            <td><span class="badge badge-soft <?= e(badge_class_for_status($row['status'])) ?>"><?= e($row['status']) ?></span></td>
                            <td><?= e(date('Y-m-d', strtotime((string) $row['created_at']))) ?></td>
                            <td>
                                <?php if ((int) $row['id'] !== (int) $admin['id']): ?>
                                    <form method="post" data-confirm="Change this user's active status?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="user_id" value="<?= e((int) $row['id']) ?>">
                                        <button class="btn btn-sm btn-outline-primary">Toggle</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$users): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No users found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card-soft p-4">
            <h4 class="mb-3">Create User</h4>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_user">
                <div class="mb-2"><label class="form-label">Full Name</label><input name="full_name" class="form-control" required></div>
                <div class="mb-2"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
                <div class="mb-2"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
                <div class="mb-2"><label class="form-label">Role</label>
                    <select name="role" class="form-select">
                        <option value="patient">Patient</option>
                        <option value="donor">Donor</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="mb-2"><label class="form-label">Phone</label><input name="phone" class="form-control"></div>
                <div class="mb-2"><label class="form-label">City</label><input name="city" class="form-control"></div>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label">Blood</label>
                        <select name="blood_group" class="form-select">
                            <?php foreach (blood_groups() as $group): ?>
                                <option value="<?= e($group) ?>"><?= e($group) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Age</label>
                        <input type="number" name="age" class="form-control" value="25">
                    </div>
                </div>
                <div class="mt-2"><label class="form-label">Weight (for donor)</label><input type="number" step="0.1" name="weight" class="form-control" value="55"></div>
                <button class="btn btn-danger mt-3 w-100">Create</button>
            </form>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

