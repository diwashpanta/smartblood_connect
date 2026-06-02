<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$admin = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_with_csrf();
    $action = $_POST['action'] ?? '';
    $donorId = (int) ($_POST['donor_id'] ?? 0);

    if ($donorId > 0) {
        if ($action === 'toggle_verify') {
            $row = db_fetch_one("SELECT is_verified FROM donors WHERE id = ?", [$donorId]);
            if ($row) {
                $next = (int) $row['is_verified'] === 1 ? 0 : 1;
                db_query("UPDATE donors SET is_verified = ?, updated_at = NOW() WHERE id = ?", [$next, $donorId]);
                audit_log((int) $admin['id'], 'donor_verify_toggle', 'donors', $donorId, ['verified' => $next]);
                set_flash('success', 'Donor verification status updated.');
            }
        }

        if ($action === 'set_availability') {
            $availability = $_POST['availability_status'] ?? 'available';
            if (in_array($availability, ['available', 'busy', 'inactive'], true)) {
                db_query("UPDATE donors SET availability_status = ?, updated_at = NOW() WHERE id = ?", [$availability, $donorId]);
                audit_log((int) $admin['id'], 'donor_availability_set', 'donors', $donorId, ['availability' => $availability]);
                set_flash('success', 'Donor availability updated.');
            }
        }
    }
    redirect('admin/donors.php');
}

$donors = db_fetch_all(
    "SELECT d.*, u.full_name, u.email, u.phone, u.city, u.blood_group
     FROM donors d
     JOIN users u ON u.id = d.user_id
     ORDER BY d.created_at DESC"
);

$pageTitle = 'Manage Donors';
$showSidebar = true;
$mainContainerClass = 'container-fluid py-4';
include __DIR__ . '/../includes/header.php';
?>

<section class="card-soft p-4">
    <h1 class="h3 mb-3">Donors</h1>
    <div class="table-responsive">
        <table class="table table-modern align-middle">
            <thead>
            <tr>
                <th>Donor</th><th>Blood</th><th>Age/Weight</th><th>Health</th><th>Availability</th><th>Verified</th><th>Response Rate</th><th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($donors as $donor): ?>
                <?php $eligible = donor_is_eligible([
                    'donor_age' => (int) $donor['age'],
                    'donor_weight' => (float) $donor['weight'],
                    'availability_status' => $donor['availability_status'],
                    'medical_condition_status' => $donor['medical_condition_status'],
                    'last_donation_date' => $donor['last_donation_date'],
                    'donor_verified' => (int) $donor['is_verified'],
                ]); ?>
                <tr>
                    <td>
                        <?= e($donor['full_name']) ?>
                        <div class="small text-muted"><?= e($donor['email']) ?> | <?= e($donor['phone']) ?></div>
                    </td>
                    <td><?= e($donor['blood_group']) ?></td>
                    <td><?= e((int) $donor['age']) ?> yrs / <?= e((float) $donor['weight']) ?> kg</td>
                    <td><?= e($donor['medical_condition_status']) ?><div class="small text-muted">Last donation: <?= e((string) $donor['last_donation_date']) ?></div></td>
                    <td><span class="badge badge-soft <?= e(badge_class_for_status($donor['availability_status'])) ?>"><?= e($donor['availability_status']) ?></span></td>
                    <td><span class="badge badge-soft <?= e((int) $donor['is_verified'] === 1 ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning-emphasis') ?>"><?= (int) $donor['is_verified'] === 1 ? 'Verified' : 'Pending' ?></span></td>
                    <td>
                        <?= e(number_format((float) $donor['response_rate'], 2)) ?>%
                        <div class="small <?= $eligible ? 'text-success' : 'text-warning' ?>"><?= $eligible ? 'Eligible' : 'Ineligible' ?></div>
                    </td>
                    <td>
                        <div class="d-flex flex-column gap-1">
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle_verify">
                                <input type="hidden" name="donor_id" value="<?= e((int) $donor['id']) ?>">
                                <button class="btn btn-sm btn-outline-primary"><?= (int) $donor['is_verified'] === 1 ? 'Unverify' : 'Verify' ?></button>
                            </form>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="set_availability">
                                <input type="hidden" name="donor_id" value="<?= e((int) $donor['id']) ?>">
                                <select name="availability_status" class="form-select form-select-sm" onchange="this.form.submit()">
                                    <?php foreach (['available','busy','inactive'] as $option): ?>
                                        <option value="<?= e($option) ?>" <?= $donor['availability_status'] === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$donors): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No donors found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

