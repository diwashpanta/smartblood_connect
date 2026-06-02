<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('donor');

$user = current_user();
$donorId = (int) ($user['donor_id'] ?? 0);

$stats = [
    'notifications' => 0,
    'accepted' => 0,
    'appointments' => 0,
    'completed' => 0,
];
$responseStatus = ['pending' => 0, 'accepted' => 0, 'declined' => 0, 'expired' => 0];
$nearbyRequests = [];
$predictionSummary = null;

try {
    $stats['notifications'] = (int) (db_fetch_one("SELECT COUNT(*) AS c FROM donor_notifications WHERE donor_id = ?", [$donorId])['c'] ?? 0);
    $stats['accepted'] = (int) (db_fetch_one("SELECT COUNT(*) AS c FROM donor_notifications WHERE donor_id = ? AND status = 'accepted'", [$donorId])['c'] ?? 0);
    $stats['appointments'] = (int) (db_fetch_one("SELECT COUNT(*) AS c FROM donation_appointments WHERE donor_id = ? AND status = 'scheduled'", [$donorId])['c'] ?? 0);
    $stats['completed'] = (int) (db_fetch_one("SELECT COUNT(*) AS c FROM donation_appointments WHERE donor_id = ? AND status = 'completed'", [$donorId])['c'] ?? 0);

    $rows = db_fetch_all("SELECT status, COUNT(*) AS c FROM donor_notifications WHERE donor_id = ? GROUP BY status", [$donorId]);
    foreach ($rows as $row) {
        $responseStatus[$row['status']] = (int) $row['c'];
    }

    $requests = db_fetch_all(
        "SELECT br.*, COALESCE(br.request_status, br.status) AS request_status, COALESCE(br.city, br.hospital_city) AS city,
                COALESCE(br.latitude, br.hospital_latitude) AS latitude, COALESCE(br.longitude, br.hospital_longitude) AS longitude,
                u.full_name AS patient_name
         FROM blood_requests br
         JOIN patients p ON p.id = br.patient_id
         JOIN users u ON u.id = p.user_id
         WHERE COALESCE(br.request_status, br.status) IN ('pending','matched','partially_fulfilled')
         ORDER BY br.created_at DESC
         LIMIT 25"
    );

    foreach ($requests as $request) {
        if (!can_donate_to((string) ($user['donor_blood_group'] ?? $user['blood_group']), (string) $request['blood_group'])) {
            continue;
        }
        $distance = haversine_km(
            isset($user['donor_latitude']) ? (float) $user['donor_latitude'] : (isset($user['latitude']) ? (float) $user['latitude'] : null),
            isset($user['donor_longitude']) ? (float) $user['donor_longitude'] : (isset($user['longitude']) ? (float) $user['longitude'] : null),
            isset($request['latitude']) ? (float) $request['latitude'] : null,
            isset($request['longitude']) ? (float) $request['longitude'] : null
        );
        $request['distance_km'] = $distance;
        $nearbyRequests[] = $request;
    }
    usort($nearbyRequests, static fn ($a, $b) => $a['distance_km'] <=> $b['distance_km']);
    $nearbyRequests = array_slice($nearbyRequests, 0, 8);

    if ($nearbyRequests) {
        $prediction = predict_donor_likelihood([
            'age' => (int) ($user['donor_age'] ?? 0),
            'weight' => (float) ($user['donor_weight'] ?? 0),
            'blood_group' => (string) ($user['donor_blood_group'] ?? $user['blood_group'] ?? ''),
            'last_donation_date' => $user['last_donation_date'] ?? null,
            'medical_condition_status' => (string) ($user['medical_condition_status'] ?? 'healthy'),
            'availability_status' => (string) ($user['availability_status'] ?? 'available'),
            'latitude' => isset($user['donor_latitude']) ? (float) $user['donor_latitude'] : null,
            'longitude' => isset($user['donor_longitude']) ? (float) $user['donor_longitude'] : null,
            'past_donations' => (int) ($user['past_donations'] ?? 0),
            'total_donations' => (int) ($user['total_donations'] ?? 0),
            'response_rate' => (float) ($user['response_rate'] ?? 0),
        ], $nearbyRequests[0]);
        $predictionSummary = $prediction['result'];
    }
} catch (Throwable $e) {
    set_flash('danger', 'Could not load donor dashboard data.');
}

$pageTitle = 'Donor Dashboard';
$showSidebar = true;
$withChartJs = true;
$withMaps = true;
$extraScripts = [asset('js/donor-maps.js')];
$mainContainerClass = 'container-fluid py-4';
include __DIR__ . '/../includes/header.php';
?>

<section class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="kpi-card"><div class="kpi-label">Notifications</div><div class="kpi-value"><?= e($stats['notifications']) ?></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="kpi-card"><div class="kpi-label">Accepted Alerts</div><div class="kpi-value"><?= e($stats['accepted']) ?></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="kpi-card"><div class="kpi-label">Scheduled Appointments</div><div class="kpi-value"><?= e($stats['appointments']) ?></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="kpi-card"><div class="kpi-label">Completed Donations</div><div class="kpi-value"><?= e($stats['completed']) ?></div></div></div>
</section>

<section class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card-soft p-4">
            <h3 class="mb-3">Live Nearby Request Map</h3>
            <div id="donorDashboardMap" class="map-canvas map-lg"></div>
            <div class="map-hint">Map shows your saved donor location and nearby active blood requests.</div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card-soft p-4 h-100">
            <h4 class="mb-3">Eligibility and Prediction</h4>
            <ul class="small mb-3">
                <li>Age: <?= e((int) ($user['donor_age'] ?? 0)) ?> years</li>
                <li>Weight: <?= e((float) ($user['donor_weight'] ?? 0)) ?> kg</li>
                <li>Last donation: <?= e((string) ($user['last_donation_date'] ?: 'Not provided')) ?></li>
                <li>Medical status: <?= e((string) ($user['medical_condition_status'] ?? 'N/A')) ?></li>
                <li>Availability: <?= e((string) ($user['availability_status'] ?? 'N/A')) ?></li>
            </ul>
            <div class="alert <?= donor_is_eligible($user) ? 'alert-success' : 'alert-warning' ?> py-2">
                <?= donor_is_eligible($user) ? 'You are currently eligible to donate.' : 'Eligibility conditions are not fully met.' ?>
            </div>
            <?php if ($predictionSummary): ?>
                <div class="mt-3">
                    <div class="small text-muted">ML Donation Likelihood</div>
                    <div class="h4 mb-0"><?= e(number_format((float) $predictionSummary['probability'] * 100, 1)) ?>%</div>
                    <div class="small"><?= e(ucfirst($predictionSummary['predicted_class'])) ?> (<?= e($predictionSummary['confidence_label']) ?> confidence)</div>
                </div>
            <?php endif; ?>
            <hr>
            <div class="chart-wrap" style="min-height:180px"><canvas id="donorResponseChart"></canvas></div>
        </div>
    </div>
</section>

<section class="card-soft p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Nearby Compatible Requests</h4>
        <a href="<?= e(url('donor/notifications.php')) ?>" class="btn btn-sm btn-outline-secondary">Respond to Notifications</a>
    </div>
    <div class="table-responsive">
        <table class="table table-modern align-middle">
            <thead>
            <tr><th>Request</th><th>Patient</th><th>Blood</th><th>Units</th><th>Hospital</th><th>Distance</th><th>Urgency</th><th>Action</th></tr>
            </thead>
            <tbody>
            <?php foreach ($nearbyRequests as $req): ?>
                <tr>
                    <td>BR-<?= e((int) $req['id']) ?></td>
                    <td><?= e($req['patient_name']) ?></td>
                    <td><?= e($req['blood_group']) ?></td>
                    <td><?= e((int) $req['units_fulfilled']) ?>/<?= e((int) $req['units_needed']) ?></td>
                    <td><?= e($req['hospital_name']) ?>, <?= e($req['city']) ?></td>
                    <td><?= e(number_format((float) $req['distance_km'], 2)) ?> km</td>
                    <td><span class="badge bg-light text-dark"><?= e(ucfirst($req['urgency'])) ?></span></td>
                    <td>
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('donor/notifications.php')) ?>">Accept / Decline</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$nearbyRequests): ?>
                <tr><td colspan="8" class="text-muted text-center">No nearby compatible requests found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    DonorMaps.loadDonorDashboardMap('donorDashboardMap');
    SmartBloodCharts.createBarChart(
        'donorResponseChart',
        <?= json_encode(array_map('ucfirst', array_keys($responseStatus))) ?>,
        <?= json_encode(array_values($responseStatus)) ?>,
        'Notification Responses'
    );
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

