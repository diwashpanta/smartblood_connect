<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$admin = current_user();
$statusFilter = trim((string) ($_GET['status'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_with_csrf();
    $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
    $status = $_POST['status'] ?? 'scheduled';

    if ($appointmentId > 0 && in_array($status, ['scheduled', 'completed', 'cancelled', 'no_show'], true)) {
        $appointment = db_fetch_one(
            "SELECT da.*, br.patient_id, u.blood_group
             FROM donation_appointments da
             JOIN blood_requests br ON br.id = da.request_id
             JOIN donors d ON d.id = da.donor_id
             JOIN users u ON u.id = d.user_id
             WHERE da.id = ?",
            [$appointmentId]
        );

        if ($appointment) {
            $previous = $appointment['status'];
            db_query("UPDATE donation_appointments SET status = ?, updated_at = NOW() WHERE id = ?", [$status, $appointmentId]);

            if ($status === 'completed' && $previous !== 'completed') {
                $alreadyLogged = db_fetch_one(
                    "SELECT id FROM inventory_transactions WHERE reference_type = 'donation_appointment' AND reference_id = ? AND action = 'add' LIMIT 1",
                    [$appointmentId]
                );
                if (!$alreadyLogged) {
                    db_query(
                        "INSERT INTO blood_inventory (blood_group, donor_id, request_id, quantity_units, expiry_date, status, collected_at)
                         VALUES (?, ?, ?, 1, DATE_ADD(CURDATE(), INTERVAL 35 DAY), 'available', CURDATE())",
                        [$appointment['blood_group'], (int) $appointment['donor_id'], (int) $appointment['request_id']]
                    );
                    $inventoryId = (int) db()->lastInsertId();

                    db_query(
                        "INSERT INTO inventory_transactions (inventory_id, action, quantity_units, reference_type, reference_id, notes, performed_by)
                         VALUES (?, 'add', 1, 'donation_appointment', ?, 'Inventory added after admin completion', ?)",
                        [$inventoryId, $appointmentId, (int) $admin['id']]
                    );
                }

                db_query(
                    "UPDATE donors SET last_donation_date = CURDATE(), past_donations = past_donations + 1 WHERE id = ?",
                    [(int) $appointment['donor_id']]
                );

                $pipeline = run_request_matching_pipeline((int) $appointment['request_id'], (int) $admin['id']);
                create_patient_notification(
                    (int) $appointment['patient_id'],
                    (int) $appointment['request_id'],
                    "Appointment marked completed by admin. Issued units updated: {$pipeline['issued_units']}."
                );
            }

            audit_log((int) $admin['id'], 'appointment_status_updated', 'donation_appointment', $appointmentId, ['status' => $status]);
            set_flash('success', 'Appointment status updated.');
        }
    }

    redirect('admin/appointments.php' . ($statusFilter !== '' ? '?status=' . urlencode($statusFilter) : ''));
}

$sql = "SELECT da.*, br.blood_group, br.hospital_name, br.hospital_city, br.urgency,
               u.full_name AS donor_name, u.phone AS donor_phone
        FROM donation_appointments da
        JOIN blood_requests br ON br.id = da.request_id
        JOIN donors d ON d.id = da.donor_id
        JOIN users u ON u.id = d.user_id
        WHERE 1=1";
$params = [];
if ($statusFilter !== '') {
    $sql .= " AND da.status = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY da.scheduled_at DESC";
$appointments = db_fetch_all($sql, $params);

$pageTitle = 'Manage Appointments';
$showSidebar = true;
$mainContainerClass = 'container-fluid py-4';
include __DIR__ . '/../includes/header.php';
?>

<section class="card-soft p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Donation Appointments</h1>
        <form method="get">
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All Status</option>
                <?php foreach (['scheduled','completed','cancelled','no_show'] as $status): ?>
                    <option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-modern align-middle">
            <thead>
            <tr>
                <th>Appointment</th><th>Request</th><th>Donor</th><th>Hospital</th><th>Schedule</th><th>Status</th><th>Update</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($appointments as $appointment): ?>
                <tr>
                    <td>AP-<?= e((int) $appointment['id']) ?></td>
                    <td>BR-<?= e((int) $appointment['request_id']) ?> (<?= e($appointment['blood_group']) ?>)</td>
                    <td><?= e($appointment['donor_name']) ?><div class="small text-muted"><?= e($appointment['donor_phone']) ?></div></td>
                    <td><?= e($appointment['hospital_name']) ?>, <?= e($appointment['hospital_city']) ?></td>
                    <td><?= e(date('Y-m-d H:i', strtotime((string) $appointment['scheduled_at']))) ?></td>
                    <td><span class="badge badge-soft <?= e(badge_class_for_status($appointment['status'])) ?>"><?= e($appointment['status']) ?></span></td>
                    <td>
                        <form method="post" class="d-flex gap-1">
                            <?= csrf_field() ?>
                            <input type="hidden" name="appointment_id" value="<?= e((int) $appointment['id']) ?>">
                            <select name="status" class="form-select form-select-sm" style="min-width:130px;">
                                <?php foreach (['scheduled','completed','cancelled','no_show'] as $status): ?>
                                    <option value="<?= e($status) ?>" <?= $appointment['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-sm btn-outline-primary">Save</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$appointments): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No appointments found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

