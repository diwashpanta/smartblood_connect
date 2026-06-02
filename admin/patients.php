<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$search = trim((string) ($_GET['q'] ?? ''));

$sql = "SELECT p.*, u.full_name, u.email, u.phone, u.city, u.blood_group, u.status
        FROM patients p
        JOIN users u ON u.id = p.user_id
        WHERE 1=1";
$params = [];
if ($search !== '') {
    $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $needle = '%' . $search . '%';
    $params[] = $needle;
    $params[] = $needle;
    $params[] = $needle;
}
$sql .= " ORDER BY p.created_at DESC";
$patients = db_fetch_all($sql, $params);

$pageTitle = 'Manage Patients';
$showSidebar = true;
$mainContainerClass = 'container-fluid py-4';
include __DIR__ . '/../includes/header.php';
?>

<section class="card-soft p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Patients</h1>
        <form method="get" class="d-flex gap-2">
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Search patient..." value="<?= e($search) ?>">
            <button class="btn btn-sm btn-outline-secondary">Search</button>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-modern align-middle">
            <thead>
            <tr>
                <th>Patient</th><th>Blood Group</th><th>Age/Gender</th><th>Hospital Preference</th><th>Status</th><th>Created</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($patients as $patient): ?>
                <tr>
                    <td>
                        <?= e($patient['full_name']) ?>
                        <div class="small text-muted"><?= e($patient['email']) ?> | <?= e($patient['phone']) ?></div>
                    </td>
                    <td><?= e((string) $patient['blood_group']) ?></td>
                    <td><?= e((int) $patient['age']) ?> / <?= e($patient['gender']) ?></td>
                    <td><?= e((string) ($patient['hospital_preference'] ?: 'N/A')) ?></td>
                    <td><span class="badge badge-soft <?= e(badge_class_for_status($patient['status'])) ?>"><?= e($patient['status']) ?></span></td>
                    <td><?= e(date('Y-m-d', strtotime((string) $patient['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$patients): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No patients found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

