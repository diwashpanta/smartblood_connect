<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('patient');

$user = current_user();
$patientId = (int) ($user['patient_id'] ?? 0);
$requestId = (int) ($_GET['id'] ?? 0);

$request = null;
if ($requestId > 0) {
    $request = db_fetch_one(
        "SELECT br.* FROM blood_requests br WHERE br.id = ? AND br.patient_id = ?",
        [$requestId, $patientId]
    );
}
if (!$request) {
    set_flash('danger', 'Request not found.');
    redirect('patient/my_requests.php');
}

$requestStatus = (string) ($request['request_status'] ?? $request['status']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_with_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'cancel' && in_array($requestStatus, ['pending', 'matched'], true)) {
        db_query("UPDATE blood_requests SET status='cancelled', request_status='cancelled', updated_at = NOW() WHERE id = ? AND patient_id = ?", [$requestId, $patientId]);
        audit_log((int) $user['id'], 'request_cancelled', 'blood_request', $requestId);
        set_flash('warning', 'Request cancelled.');
        redirect('patient/request_details.php?id=' . $requestId);
    }

    if ($action === 'rematch' && !in_array($requestStatus, ['fulfilled', 'rejected', 'cancelled'], true)) {
        $result = run_request_matching_pipeline($requestId, (int) $user['id']);
        set_flash('success', "Matching refreshed. Issued units: {$result['issued_units']} | New notifications: {$result['notifications']}");
        redirect('patient/request_details.php?id=' . $requestId);
    }
}

$request = fetch_blood_request($requestId);
$requestStatus = (string) ($request['request_status'] ?? $request['status']);
$requestLat = isset($request['latitude']) ? (float) $request['latitude'] : (float) ($request['hospital_latitude'] ?? 0);
$requestLng = isset($request['longitude']) ? (float) $request['longitude'] : (float) ($request['hospital_longitude'] ?? 0);

$donorNotifications = db_fetch_all(
    "SELECT dn.*, u.full_name, u.phone, COALESCE(d.city,u.city) AS city, COALESCE(d.address,u.address) AS address,
            COALESCE(d.latitude,u.latitude) AS latitude, COALESCE(d.longitude,u.longitude) AS longitude,
            COALESCE(d.blood_group,u.blood_group) AS blood_group
     FROM donor_notifications dn
     JOIN donors d ON d.id = dn.donor_id
     JOIN users u ON u.id = d.user_id
     WHERE dn.request_id = ?
     ORDER BY dn.matching_score DESC, dn.distance_km ASC",
    [$requestId]
);

$issuances = db_fetch_all(
    "SELECT bi.*, inv.blood_group, inv.expiry_date
     FROM blood_issuance bi
     JOIN blood_inventory inv ON inv.id = bi.inventory_id
     WHERE bi.request_id = ?
     ORDER BY bi.issued_at DESC",
    [$requestId]
);

$timeline = db_fetch_all(
    "SELECT message, created_at FROM patient_notifications WHERE request_id = ? AND patient_id = ? ORDER BY created_at DESC",
    [$requestId, $patientId]
);

$remaining = max(0, (int) $request['units_needed'] - (int) $request['units_fulfilled']);

$pageTitle = 'Request Details';
$showSidebar = true;
$withMaps = true;
$extraScripts = [asset('js/patient-maps.js')];
$mainContainerClass = 'container-fluid py-4';
include __DIR__ . '/../includes/header.php';
?>

<section class="card-soft p-4 mb-4">
    <div class="d-flex justify-content-between flex-wrap gap-3 align-items-center">
        <div>
            <h1 class="h3 mb-1">Request BR-<?= e((int) $request['id']) ?></h1>
            <div class="text-muted small">
                <?= e($request['hospital_name']) ?>, <?= e($request['city'] ?? $request['hospital_city']) ?> |
                Created <?= e(date('Y-m-d H:i', strtotime((string) $request['created_at']))) ?>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge badge-soft <?= e(badge_class_for_status($requestStatus)) ?>"><?= e($requestStatus) ?></span>
            <?php if (in_array($requestStatus, ['pending', 'matched'], true)): ?>
                <form method="post" data-confirm="Cancel this blood request?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="cancel">
                    <button class="btn btn-outline-danger btn-sm">Cancel</button>
                </form>
            <?php endif; ?>
            <?php if (!in_array($requestStatus, ['fulfilled', 'rejected', 'cancelled'], true)): ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="rematch">
                    <button class="btn btn-outline-primary btn-sm">Refresh Matching</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card-soft p-4 h-100">
            <h4 class="mb-3">Hospital and Matched Donor Map</h4>
            <div id="patientRequestMap" class="map-canvas map-lg"></div>
            <div class="map-hint">
                Hospital location with matched donor markers and approximate distances.
                <?php if (sb_has_valid_lat_lng($requestLat, $requestLng)): ?>
                    <a href="<?= e(SmartBloodMapsOpenLink($requestLat, $requestLng)) ?>" target="_blank" rel="noopener">Open in map</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card-soft p-4 h-100">
            <h4 class="mb-3">Request Timeline</h4>
            <div class="timeline">
                <?php foreach ($timeline as $item): ?>
                    <div class="event">
                        <div class="small fw-semibold"><?= e($item['message']) ?></div>
                        <div class="small text-muted"><?= e(date('Y-m-d H:i', strtotime((string) $item['created_at']))) ?></div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$timeline): ?>
                    <p class="text-muted small mb-0">No timeline records yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<section class="row g-4">
    <div class="col-lg-7">
        <div class="card-soft p-4 h-100">
            <h4 class="mb-3">Matched Donors and Notifications</h4>
            <div class="table-responsive">
                <table class="table table-modern table-sm align-middle">
                    <thead>
                    <tr><th>Donor</th><th>Blood</th><th>Distance</th><th>Prediction</th><th>Matching</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($donorNotifications as $dn): ?>
                        <tr>
                            <td><?= e($dn['full_name']) ?><div class="text-muted small"><?= e($dn['phone']) ?></div></td>
                            <td><?= e($dn['blood_group']) ?></td>
                            <td><?= e(number_format((float) $dn['distance_km'], 2)) ?> km</td>
                            <td><?= e(number_format((float) ($dn['predicted_probability'] ?? $dn['probability_score']) * 100, 1)) ?>%</td>
                            <td><?= e(number_format((float) ($dn['matching_score'] ?? 0), 2)) ?></td>
                            <td><span class="badge badge-soft <?= e(badge_class_for_status($dn['status'])) ?>"><?= e($dn['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$donorNotifications): ?>
                        <tr><td colspan="6" class="text-muted text-center py-3">No donors matched yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card-soft p-4 h-100">
            <h4 class="mb-3">Fulfillment Progress</h4>
            <div class="mb-2"><strong>Blood:</strong> <?= e($request['blood_group']) ?></div>
            <div class="mb-2"><strong>Needed:</strong> <?= e((int) $request['units_needed']) ?> units</div>
            <div class="mb-2"><strong>Fulfilled:</strong> <?= e((int) $request['units_fulfilled']) ?> units</div>
            <div class="mb-3"><strong>Remaining:</strong> <?= e($remaining) ?> units</div>
            <div class="progress mb-3" role="progressbar" aria-valuemin="0" aria-valuemax="100">
                <?php $progress = (int) min(100, round(((int) $request['units_fulfilled'] / max(1, (int) $request['units_needed'])) * 100)); ?>
                <div class="progress-bar bg-danger" style="width: <?= e($progress) ?>%"><?= e($progress) ?>%</div>
            </div>
            <h6>Issuance Records</h6>
            <ul class="small mb-0">
                <?php foreach ($issuances as $issuance): ?>
                    <li>INV-<?= e((int) $issuance['inventory_id']) ?> (<?= e($issuance['blood_group']) ?>): <?= e((int) $issuance['units_issued']) ?> unit(s)</li>
                <?php endforeach; ?>
                <?php if (!$issuances): ?>
                    <li class="text-muted">No inventory issued yet.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    PatientMaps.loadRequestMap('patientRequestMap', <?= json_encode((int) $requestId) ?>);
});
</script>

<?php
function SmartBloodMapsOpenLink(float $lat, float $lng): string
{
    return 'https://www.openstreetmap.org/?mlat=' . rawurlencode((string) $lat) . '&mlon=' . rawurlencode((string) $lng) . '#map=15/' . rawurlencode((string) $lat) . '/' . rawurlencode((string) $lng);
}
include __DIR__ . '/../includes/footer.php';
?>

