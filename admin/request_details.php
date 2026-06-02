<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$admin = current_user();
$requestId = (int) ($_GET['id'] ?? 0);
$request = fetch_blood_request($requestId);

if (!$request) {
    set_flash('danger', 'Blood request not found.');
    redirect('admin/blood_requests.php');
}
$requestStatus = (string) ($request['request_status'] ?? $request['status'] ?? 'pending');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_with_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'run_pipeline') {
        $result = run_request_matching_pipeline($requestId, (int) $admin['id']);
        set_flash('success', "Matching pipeline completed. Issued {$result['issued_units']} units, notified {$result['notifications']} donors.");
    }

    if ($action === 'notify_more') {
        $count = create_donor_notifications_for_request($requestId, (int) $admin['id'], 12);
        set_flash('success', "Created {$count} additional donor notification(s).");
    }

    if ($action === 'set_notification_status') {
        $notificationId = (int) ($_POST['notification_id'] ?? 0);
        $status = $_POST['status'] ?? 'pending';
        if (in_array($status, ['accepted', 'declined', 'pending', 'expired'], true)) {
            db_query(
                "UPDATE donor_notifications SET status = ?, responded_at = IF(? IN ('accepted','declined'), NOW(), responded_at) WHERE id = ? AND request_id = ?",
                [$status, $status, $notificationId, $requestId]
            );
            set_flash('success', 'Notification status updated.');
        }
    }

    if ($action === 'schedule') {
        $donorId = (int) ($_POST['donor_id'] ?? 0);
        $scheduledAt = trim((string) ($_POST['scheduled_at'] ?? ''));
        $location = trim((string) ($_POST['location'] ?? ''));

        if ($donorId > 0 && $scheduledAt !== '' && $location !== '') {
            $existing = db_fetch_one(
                "SELECT id FROM donation_appointments WHERE request_id = ? AND donor_id = ? AND status IN ('scheduled','completed')",
                [$requestId, $donorId]
            );
            if (!$existing) {
                db_query(
                    "INSERT INTO donation_appointments (request_id, donor_id, scheduled_at, location, status, notes, created_by)
                     VALUES (?, ?, ?, ?, 'scheduled', ?, ?)",
                    [$requestId, $donorId, $scheduledAt, $location, 'Scheduled by admin', (int) $admin['id']]
                );
                db_query(
                    "UPDATE donor_notifications SET status = 'accepted', responded_at = NOW() WHERE request_id = ? AND donor_id = ?",
                    [$requestId, $donorId]
                );
                create_patient_notification((int) $request['patient_id'], $requestId, "Admin scheduled donor appointment for {$scheduledAt}.");
                audit_log((int) $admin['id'], 'appointment_scheduled_admin', 'blood_request', $requestId, ['donor_id' => $donorId]);
                set_flash('success', 'Appointment scheduled.');
            } else {
                set_flash('warning', 'Appointment already exists for this donor and request.');
            }
        }
    }

    redirect('admin/request_details.php?id=' . $requestId);
}

$request = fetch_blood_request($requestId) ?: $request;
$requestStatus = (string) ($request['request_status'] ?? $request['status'] ?? 'pending');
$remaining = max(0, (int) $request['units_needed'] - (int) $request['units_fulfilled']);

$notifications = db_fetch_all(
    "SELECT dn.*, u.full_name, u.phone, u.city, u.blood_group
     FROM donor_notifications dn
     JOIN donors d ON d.id = dn.donor_id
     JOIN users u ON u.id = d.user_id
     WHERE dn.request_id = ?
     ORDER BY dn.probability_score DESC, dn.distance_km ASC",
    [$requestId]
);

$appointments = db_fetch_all(
    "SELECT da.*, u.full_name, u.phone
     FROM donation_appointments da
     JOIN donors d ON d.id = da.donor_id
     JOIN users u ON u.id = d.user_id
     WHERE da.request_id = ?
     ORDER BY da.scheduled_at DESC",
    [$requestId]
);

$issuances = db_fetch_all(
    "SELECT bi.*, inv.blood_group
     FROM blood_issuance bi
     JOIN blood_inventory inv ON inv.id = bi.inventory_id
     WHERE bi.request_id = ?
     ORDER BY bi.issued_at DESC",
    [$requestId]
);

$pageTitle = 'Request Detail';
$showSidebar = true;
$mainContainerClass = 'container-fluid py-4';
include __DIR__ . '/../includes/header.php';
?>

<section class="card-soft p-4 mb-4">
    <div class="d-flex justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">Request BR-<?= e((int) $request['id']) ?></h1>
            <div class="small text-muted"><?= e($request['patient_name']) ?> | <?= e($request['hospital_name']) ?>, <?= e($request['city']) ?></div>
        </div>
        <div class="d-flex gap-2">
            <span class="badge badge-soft <?= e(badge_class_for_status($requestStatus)) ?>"><?= e($requestStatus) ?></span>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/issue_blood.php?request_id=' . (int) $request['id'])) ?>">Issue Blood</a>
        </div>
    </div>
    <div class="row g-3 mt-2">
        <div class="col-md-3"><strong>Blood:</strong> <?= e($request['blood_group']) ?></div>
        <div class="col-md-3"><strong>Needed:</strong> <?= e((int) $request['units_needed']) ?></div>
        <div class="col-md-3"><strong>Fulfilled:</strong> <?= e((int) $request['units_fulfilled']) ?></div>
        <div class="col-md-3"><strong>Remaining:</strong> <?= e($remaining) ?></div>
    </div>
    <div class="d-flex gap-2 mt-3">
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="run_pipeline">
            <button class="btn btn-sm btn-outline-primary">Run Matching Pipeline</button>
        </form>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="notify_more">
            <button class="btn btn-sm btn-outline-secondary">Notify More Donors</button>
        </form>
    </div>
</section>

<section class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card-soft p-4">
            <h4 class="mb-3">Matched Donor Notifications</h4>
            <div class="table-responsive">
                <table class="table table-modern align-middle">
                    <thead>
                    <tr><th>Donor</th><th>Blood</th><th>Distance</th><th>ML Score</th><th>Status</th><th>Update</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($notifications as $notification): ?>
                        <tr>
                            <td><?= e($notification['full_name']) ?><div class="small text-muted"><?= e($notification['phone']) ?> | <?= e($notification['city']) ?></div></td>
                            <td><?= e($notification['blood_group']) ?></td>
                            <td><?= e(number_format((float) $notification['distance_km'], 2)) ?> km</td>
                            <td><?= e(number_format((float) $notification['probability_score'] * 100, 1)) ?>%</td>
                            <td><span class="badge badge-soft <?= e(badge_class_for_status($notification['status'])) ?>"><?= e($notification['status']) ?></span></td>
                            <td>
                                <form method="post" class="d-flex gap-1">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="set_notification_status">
                                    <input type="hidden" name="notification_id" value="<?= e((int) $notification['id']) ?>">
                                    <select name="status" class="form-select form-select-sm" style="min-width:120px;">
                                        <?php foreach (['pending','accepted','declined','expired'] as $status): ?>
                                            <option value="<?= e($status) ?>" <?= $notification['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-sm btn-outline-secondary">Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$notifications): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No donor notifications yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card-soft p-4">
            <h4 class="mb-3">Schedule Appointment</h4>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="schedule">
                <div class="mb-2">
                    <label class="form-label">Donor</label>
                    <select name="donor_id" class="form-select" required>
                        <?php foreach ($notifications as $notification): ?>
                            <option value="<?= e((int) $notification['donor_id']) ?>">
                                <?= e($notification['full_name']) ?> (<?= e($notification['status']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label">Date & Time</label>
                    <input type="datetime-local" name="scheduled_at" class="form-control" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Location</label>
                    <input type="text" name="location" class="form-control" required>
                </div>
                <button class="btn btn-danger w-100">Schedule</button>
            </form>
        </div>
    </div>
</section>

<section class="row g-4">
    <div class="col-lg-6">
        <div class="card-soft p-4 h-100">
            <h4 class="mb-3">Appointments</h4>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Donor</th><th>Schedule</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($appointments as $appointment): ?>
                        <tr>
                            <td><?= e($appointment['full_name']) ?><div class="small text-muted"><?= e($appointment['phone']) ?></div></td>
                            <td><?= e(date('Y-m-d H:i', strtotime((string) $appointment['scheduled_at']))) ?></td>
                            <td><span class="badge badge-soft <?= e(badge_class_for_status($appointment['status'])) ?>"><?= e($appointment['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$appointments): ?>
                        <tr><td colspan="3" class="text-muted text-center">No appointments scheduled.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card-soft p-4 h-100">
            <h4 class="mb-3">Issuance Records</h4>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Inventory</th><th>Blood</th><th>Units</th><th>Time</th></tr></thead>
                    <tbody>
                    <?php foreach ($issuances as $issue): ?>
                        <tr>
                            <td>INV-<?= e((int) $issue['inventory_id']) ?></td>
                            <td><?= e($issue['blood_group']) ?></td>
                            <td><?= e((int) $issue['units_issued']) ?></td>
                            <td><?= e(date('Y-m-d H:i', strtotime((string) $issue['issued_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$issuances): ?>
                        <tr><td colspan="4" class="text-muted text-center">No issuance entries yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
