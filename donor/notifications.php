<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('donor');

$user = current_user();
$donorId = (int) ($user['donor_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_with_csrf();
    $notificationId = (int) ($_POST['notification_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $notification = db_fetch_one(
        "SELECT dn.*, br.patient_id, COALESCE(br.request_status, br.status) AS request_status
         FROM donor_notifications dn
         JOIN blood_requests br ON br.id = dn.request_id
         WHERE dn.id = ? AND dn.donor_id = ?",
        [$notificationId, $donorId]
    );

    if ($notification && in_array($action, ['accept', 'decline'], true)) {
        $newStatus = $action === 'accept' ? 'accepted' : 'declined';
        db_query(
            "UPDATE donor_notifications SET status = ?, responded_at = NOW() WHERE id = ?",
            [$newStatus, $notificationId]
        );

        create_patient_notification(
            (int) $notification['patient_id'],
            (int) $notification['request_id'],
            "Donor {$user['full_name']} {$newStatus} the donation notification."
        );
        audit_log((int) $user['id'], 'donor_notification_' . $newStatus, 'donor_notification', $notificationId);

        if ($newStatus === 'accept' && $notification['request_status'] === 'pending') {
            db_query("UPDATE blood_requests SET status = 'matched', request_status = 'matched', updated_at = NOW() WHERE id = ?", [(int) $notification['request_id']]);
        }
        if ($newStatus === 'decline') {
            create_donor_notifications_for_request((int) $notification['request_id'], (int) $user['id'], 2);
        }

        set_flash('success', 'Response submitted.');
    }
    redirect('donor/notifications.php');
}

$notifications = db_fetch_all(
    "SELECT dn.*, br.blood_group, br.units_needed, br.units_fulfilled, br.urgency,
            COALESCE(br.request_status, br.status) AS request_status,
            br.hospital_name, br.hospital_address, COALESCE(br.city, br.hospital_city) AS city,
            COALESCE(br.latitude, br.hospital_latitude) AS latitude,
            COALESCE(br.longitude, br.hospital_longitude) AS longitude,
            br.created_at AS request_created_at
     FROM donor_notifications dn
     JOIN blood_requests br ON br.id = dn.request_id
     WHERE dn.donor_id = ?
     ORDER BY dn.created_at DESC",
    [$donorId]
);

$pageTitle = 'Donor Notifications';
$showSidebar = true;
$withMaps = true;
$mainContainerClass = 'container-fluid py-4';
include __DIR__ . '/../includes/header.php';
?>

<section class="card-soft p-4">
    <h1 class="h3 mb-3">Donation Notifications</h1>
    <div class="row g-3">
        <?php foreach ($notifications as $notification): ?>
            <div class="col-lg-6">
                <div class="card-soft p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="fw-semibold">BR-<?= e((int) $notification['request_id']) ?> | <?= e($notification['hospital_name']) ?></div>
                            <div class="small text-muted"><?= e($notification['hospital_address'] ?: $notification['city']) ?></div>
                        </div>
                        <span class="badge badge-soft <?= e(badge_class_for_status($notification['status'])) ?>"><?= e($notification['status']) ?></span>
                    </div>
                    <div class="row g-2 small mb-2">
                        <div class="col-6"><strong>Blood:</strong> <?= e($notification['blood_group']) ?></div>
                        <div class="col-6"><strong>Urgency:</strong> <?= e(ucfirst($notification['urgency'])) ?></div>
                        <div class="col-6"><strong>Units:</strong> <?= e((int) $notification['units_fulfilled']) ?>/<?= e((int) $notification['units_needed']) ?></div>
                        <div class="col-6"><strong>Distance:</strong> <?= e(number_format((float) $notification['distance_km'], 2)) ?> km</div>
                        <div class="col-6"><strong>Prediction:</strong> <?= e(number_format((float) ($notification['predicted_probability'] ?? $notification['probability_score']) * 100, 1)) ?>%</div>
                        <div class="col-6"><strong>Matching:</strong> <?= e(number_format((float) ($notification['matching_score'] ?? 0), 2)) ?></div>
                    </div>
                    <div id="notifyMap<?= e((int) $notification['id']) ?>" class="map-canvas map-sm mb-2"
                         data-lat="<?= e((string) $notification['latitude']) ?>"
                         data-lng="<?= e((string) $notification['longitude']) ?>"
                         data-title="<?= e($notification['hospital_name']) ?>"></div>
                    <div class="d-flex justify-content-between align-items-center">
                        <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener"
                           href="https://www.openstreetmap.org/?mlat=<?= e((string) $notification['latitude']) ?>&mlon=<?= e((string) $notification['longitude']) ?>#map=15/<?= e((string) $notification['latitude']) ?>/<?= e((string) $notification['longitude']) ?>">Open Map</a>
                        <?php if ($notification['status'] === 'pending'): ?>
                            <div class="d-flex gap-1">
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="notification_id" value="<?= e((int) $notification['id']) ?>">
                                    <input type="hidden" name="action" value="accept">
                                    <button class="btn btn-sm btn-outline-success">Accept</button>
                                </form>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="notification_id" value="<?= e((int) $notification['id']) ?>">
                                    <input type="hidden" name="action" value="decline">
                                    <button class="btn btn-sm btn-outline-danger">Decline</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <a href="<?= e(url('donor/appointments.php')) ?>" class="btn btn-sm btn-outline-primary">Appointments</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$notifications): ?>
            <div class="col-12"><div class="text-center text-muted py-4">No notifications yet.</div></div>
        <?php endif; ?>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[id^=\"notifyMap\"]').forEach(function (el) {
        const lat = Number(el.dataset.lat);
        const lng = Number(el.dataset.lng);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
        const map = SmartBloodMaps.initMap(el.id, { lat: lat, lng: lng, zoom: 14 });
        if (!map) return;
        map.addMarker({ lat: lat, lng: lng, popup: `<strong>${el.dataset.title}</strong>` });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

