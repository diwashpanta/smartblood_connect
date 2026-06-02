<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('donor');

$user = current_user();
$donorId = (int) ($user['donor_id'] ?? 0);
$userId = (int) ($user['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_with_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'schedule') {
        $notificationId = (int) ($_POST['notification_id'] ?? 0);
        $scheduledAt = trim((string) ($_POST['scheduled_at'] ?? ''));
        $location = trim((string) ($_POST['location'] ?? ''));

        $notification = db_fetch_one(
            "SELECT dn.*, br.patient_id
             FROM donor_notifications dn
             JOIN blood_requests br ON br.id = dn.request_id
             WHERE dn.id = ? AND dn.donor_id = ? AND dn.status = 'accepted'",
            [$notificationId, $donorId]
        );

        if ($notification && $scheduledAt !== '' && $location !== '') {
            $exists = db_fetch_one(
                "SELECT id FROM donation_appointments WHERE donor_id = ? AND request_id = ? AND status IN ('scheduled','completed')",
                [$donorId, (int) $notification['request_id']]
            );
            if (!$exists) {
                db_query(
                    "INSERT INTO donation_appointments (request_id, donor_id, scheduled_at, location, status, notes, created_by)
                     VALUES (?, ?, ?, ?, 'scheduled', ?, ?)",
                    [
                        (int) $notification['request_id'],
                        $donorId,
                        $scheduledAt,
                        $location,
                        'Scheduled by donor after acceptance',
                        $userId,
                    ]
                );
                create_patient_notification((int) $notification['patient_id'], (int) $notification['request_id'], "Donation appointment scheduled for {$scheduledAt}.");
                audit_log($userId, 'appointment_scheduled', 'donation_appointment', (int) db()->lastInsertId(), ['request_id' => (int) $notification['request_id']]);
                set_flash('success', 'Appointment scheduled successfully.');
            }
        }
    }

    if ($action === 'complete') {
        $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
        $appointment = db_fetch_one(
            "SELECT da.*, br.patient_id
             FROM donation_appointments da
             JOIN blood_requests br ON br.id = da.request_id
             WHERE da.id = ? AND da.donor_id = ? AND da.status = 'scheduled'",
            [$appointmentId, $donorId]
        );

        if ($appointment) {
            $pdo = db();
            try {
                $pdo->beginTransaction();
                db_query("UPDATE donation_appointments SET status='completed', updated_at = NOW() WHERE id = ?", [$appointmentId]);

                db_query(
                    "UPDATE donors
                     SET last_donation_date = CURDATE(),
                         past_donations = past_donations + 1,
                         response_rate = ROUND(
                            (SELECT IFNULL((SUM(CASE WHEN status='accepted' THEN 1 ELSE 0 END) / NULLIF(COUNT(*),0)) * 100, 0)
                             FROM donor_notifications
                             WHERE donor_id = ?), 2
                         )
                     WHERE id = ?",
                    [$donorId, $donorId]
                );

                db_query(
                    "INSERT INTO blood_inventory (blood_group, donor_id, request_id, quantity_units, expiry_date, status, collected_at)
                     VALUES (?, ?, ?, 1, DATE_ADD(CURDATE(), INTERVAL 35 DAY), 'available', CURDATE())",
                    [(string) $user['blood_group'], $donorId, (int) $appointment['request_id']]
                );
                $inventoryId = (int) $pdo->lastInsertId();

                db_query(
                    "INSERT INTO inventory_transactions (inventory_id, action, quantity_units, reference_type, reference_id, notes, performed_by)
                     VALUES (?, 'add', 1, 'donation_appointment', ?, 'Unit added after completed appointment', ?)",
                    [$inventoryId, $appointmentId, $userId]
                );

                $pdo->commit();
                $pipeline = run_request_matching_pipeline((int) $appointment['request_id'], $userId);
                create_patient_notification(
                    (int) $appointment['patient_id'],
                    (int) $appointment['request_id'],
                    "Donation completed and inventory updated. Issued units now: {$pipeline['issued_units']}."
                );
                audit_log($userId, 'appointment_completed', 'donation_appointment', $appointmentId, ['inventory_id' => $inventoryId]);
                set_flash('success', 'Appointment marked completed. Inventory and request status updated.');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                set_flash('danger', 'Failed to complete appointment.');
            }
        }
    }

    if ($action === 'cancel') {
        $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
        db_query("UPDATE donation_appointments SET status='cancelled', updated_at = NOW() WHERE id = ? AND donor_id = ? AND status = 'scheduled'", [$appointmentId, $donorId]);
        set_flash('warning', 'Appointment cancelled.');
    }

    redirect('donor/appointments.php');
}

$acceptedNotifications = db_fetch_all(
    "SELECT dn.*, br.hospital_name, br.hospital_city
     FROM donor_notifications dn
     JOIN blood_requests br ON br.id = dn.request_id
     LEFT JOIN donation_appointments da ON da.request_id = dn.request_id AND da.donor_id = dn.donor_id AND da.status IN ('scheduled','completed')
     WHERE dn.donor_id = ? AND dn.status = 'accepted' AND da.id IS NULL
     ORDER BY dn.created_at DESC",
    [$donorId]
);

$appointments = db_fetch_all(
    "SELECT da.*, br.blood_group, br.units_needed, br.units_fulfilled, br.hospital_name, br.hospital_city, br.urgency
     FROM donation_appointments da
     JOIN blood_requests br ON br.id = da.request_id
     WHERE da.donor_id = ?
     ORDER BY da.scheduled_at DESC",
    [$donorId]
);

$pageTitle = 'Donor Appointments';
$showSidebar = true;
$mainContainerClass = 'container-fluid py-4';
include __DIR__ . '/../includes/header.php';
?>

<section class="row g-4 mb-4">
    <div class="col-lg-5">
        <div class="card-soft p-4 h-100">
            <h4 class="mb-3">Schedule Donation Appointment</h4>
            <?php if ($acceptedNotifications): ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="schedule">
                    <div class="mb-3">
                        <label class="form-label">Accepted Notification</label>
                        <select name="notification_id" class="form-select" required>
                            <?php foreach ($acceptedNotifications as $notification): ?>
                                <option value="<?= e((int) $notification['id']) ?>">
                                    BR-<?= e((int) $notification['request_id']) ?> | <?= e($notification['hospital_name']) ?> | <?= e(number_format((float) $notification['distance_km'], 2)) ?> km
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Schedule Date & Time</label>
                        <input type="datetime-local" name="scheduled_at" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Location</label>
                        <input type="text" name="location" class="form-control" placeholder="Donation desk / hospital wing" required>
                    </div>
                    <button class="btn btn-danger">Schedule</button>
                </form>
            <?php else: ?>
                <p class="text-muted mb-0">No accepted notifications waiting for scheduling.</p>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card-soft p-4 h-100">
            <h4 class="mb-3">Appointment Workflow</h4>
            <div class="workflow-grid">
                <div class="workflow-step"><h6>1. Accept Alert</h6><p class="small text-muted mb-0">Respond to a donor notification as accepted.</p></div>
                <div class="workflow-step"><h6>2. Schedule Slot</h6><p class="small text-muted mb-0">Choose date/time and hospital collection location.</p></div>
                <div class="workflow-step"><h6>3. Complete Donation</h6><p class="small text-muted mb-0">Mark completion after successful blood collection.</p></div>
                <div class="workflow-step"><h6>4. Auto Update</h6><p class="small text-muted mb-0">Inventory and patient request statuses update automatically.</p></div>
            </div>
        </div>
    </div>
</section>

<section class="card-soft p-4">
    <h4 class="mb-3">My Appointments</h4>
    <div class="table-responsive">
        <table class="table table-modern align-middle">
            <thead>
            <tr>
                <th>Request</th><th>Blood</th><th>Hospital</th><th>Schedule</th><th>Status</th><th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($appointments as $appointment): ?>
                <tr>
                    <td>BR-<?= e((int) $appointment['request_id']) ?></td>
                    <td><?= e($appointment['blood_group']) ?> (<?= e((int) $appointment['units_fulfilled']) ?>/<?= e((int) $appointment['units_needed']) ?>)</td>
                    <td><?= e($appointment['hospital_name']) ?>, <?= e($appointment['hospital_city']) ?></td>
                    <td><?= e(date('Y-m-d H:i', strtotime((string) $appointment['scheduled_at']))) ?></td>
                    <td><span class="badge badge-soft <?= e(badge_class_for_status($appointment['status'])) ?>"><?= e($appointment['status']) ?></span></td>
                    <td>
                        <?php if ($appointment['status'] === 'scheduled'): ?>
                            <div class="d-flex gap-1">
                                <form method="post" data-confirm="Mark appointment completed and add blood inventory?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="complete">
                                    <input type="hidden" name="appointment_id" value="<?= e((int) $appointment['id']) ?>">
                                    <button class="btn btn-sm btn-outline-success">Complete</button>
                                </form>
                                <form method="post" data-confirm="Cancel this appointment?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="appointment_id" value="<?= e((int) $appointment['id']) ?>">
                                    <button class="btn btn-sm btn-outline-danger">Cancel</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <span class="text-muted small">No action</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$appointments): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No appointments scheduled yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

