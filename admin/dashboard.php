<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$stats = [
    'active_requests' => 0,
    'emergency_requests' => 0,
    'available_donors' => 0,
    'notifications_sent' => 0,
    'accepted_donors' => 0,
    'pending_appointments' => 0,
    'available_units' => 0,
    'low_stock_groups' => 0,
];

$stockRows = [];
$monthlyRequests = [];
$donationTrend = [];
$predictionSummary = [];
$requestStatus = [];
$urgencyRows = [];
$acceptDecline = ['accepted' => 0, 'declined' => 0];
$avgDistance = [];
$matchSuccess = ['matched' => 0, 'fulfilled' => 0];

try {
    $stats['active_requests'] = (int) (db_fetch_one("SELECT COUNT(*) AS c FROM blood_requests WHERE COALESCE(request_status,status) IN ('pending','matched','partially_fulfilled')")['c'] ?? 0);
    $stats['emergency_requests'] = (int) (db_fetch_one("SELECT COUNT(*) AS c FROM blood_requests WHERE COALESCE(request_status,status) IN ('pending','matched','partially_fulfilled') AND urgency IN ('high','critical')")['c'] ?? 0);
    $stats['available_donors'] = (int) (db_fetch_one("SELECT COUNT(*) AS c FROM donors WHERE COALESCE(availability_status,available_status)='available' AND is_verified=1 AND is_eligible=1")['c'] ?? 0);
    $stats['notifications_sent'] = (int) (db_fetch_one("SELECT COUNT(*) AS c FROM donor_notifications")['c'] ?? 0);
    $stats['accepted_donors'] = (int) (db_fetch_one("SELECT COUNT(*) AS c FROM donor_notifications WHERE status='accepted'")['c'] ?? 0);
    $stats['pending_appointments'] = (int) (db_fetch_one("SELECT COUNT(*) AS c FROM donation_appointments WHERE status='scheduled'")['c'] ?? 0);
    $stats['available_units'] = (int) (db_fetch_one("SELECT COALESCE(SUM(quantity_units),0) AS c FROM blood_inventory WHERE status='available' AND expiry_date >= CURDATE()")['c'] ?? 0);
    $stats['low_stock_groups'] = (int) (db_fetch_one(
        "SELECT COUNT(*) AS c
         FROM (
            SELECT blood_group, SUM(quantity_units) AS total_units
            FROM blood_inventory
            WHERE status='available' AND expiry_date >= CURDATE()
            GROUP BY blood_group
            HAVING total_units <= 5
         ) t"
    )['c'] ?? 0);

    $stockRows = db_fetch_all(
        "SELECT blood_group, COALESCE(SUM(quantity_units),0) AS total_units
         FROM blood_inventory
         WHERE status='available' AND expiry_date >= CURDATE()
         GROUP BY blood_group
         ORDER BY FIELD(blood_group,'A+','A-','B+','B-','AB+','AB-','O+','O-')"
    );
    $monthlyRequests = db_fetch_all(
        "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_label, COUNT(*) AS total
         FROM blood_requests
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
         GROUP BY DATE_FORMAT(created_at, '%Y-%m')
         ORDER BY month_label"
    );
    $donationTrend = db_fetch_all(
        "SELECT DATE_FORMAT(updated_at, '%Y-%m') AS month_label, COUNT(*) AS total
         FROM donation_appointments
         WHERE status = 'completed'
         GROUP BY DATE_FORMAT(updated_at, '%Y-%m')
         ORDER BY month_label"
    );
    $predictionSummary = db_fetch_all(
        "SELECT
            CASE
                WHEN probability_score < 0.4 THEN 'Low'
                WHEN probability_score < 0.7 THEN 'Medium'
                ELSE 'High'
            END AS band,
            COUNT(*) AS c
         FROM ml_predictions
         GROUP BY band"
    );
    $requestStatus = db_fetch_all("SELECT COALESCE(request_status,status) AS status, COUNT(*) AS c FROM blood_requests GROUP BY COALESCE(request_status,status)");
    $urgencyRows = db_fetch_all("SELECT urgency, COUNT(*) AS c FROM blood_requests GROUP BY urgency");

    $acc = db_fetch_all("SELECT status, COUNT(*) AS c FROM donor_notifications WHERE status IN ('accepted','declined') GROUP BY status");
    foreach ($acc as $row) {
        $acceptDecline[$row['status']] = (int) $row['c'];
    }

    $avgDistance = db_fetch_all(
        "SELECT urgency, ROUND(AVG(distance_km),2) AS avg_distance
         FROM donor_notifications dn
         JOIN blood_requests br ON br.id = dn.request_id
         GROUP BY urgency
         ORDER BY FIELD(urgency,'critical','high','medium','low')"
    );

    $matchSuccess['matched'] = (int) (db_fetch_one("SELECT COUNT(*) AS c FROM blood_requests WHERE COALESCE(request_status,status) IN ('matched','partially_fulfilled','fulfilled')")['c'] ?? 0);
    $matchSuccess['fulfilled'] = (int) (db_fetch_one("SELECT COUNT(*) AS c FROM blood_requests WHERE COALESCE(request_status,status)='fulfilled'")['c'] ?? 0);
} catch (Throwable $e) {
    set_flash('danger', 'Dashboard query failed. Check database setup.');
}

$pageTitle = 'Admin Dashboard';
$showSidebar = true;
$withChartJs = true;
$withMaps = true;
$extraScripts = [asset('js/admin-maps.js')];
$mainContainerClass = 'container-fluid py-4';
include __DIR__ . '/../includes/header.php';
?>

<section class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="kpi-card"><div class="kpi-label">Active Requests</div><div class="kpi-value"><?= e($stats['active_requests']) ?></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="kpi-card"><div class="kpi-label">Emergency Requests</div><div class="kpi-value"><?= e($stats['emergency_requests']) ?></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="kpi-card"><div class="kpi-label">Available Donors</div><div class="kpi-value"><?= e($stats['available_donors']) ?></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="kpi-card"><div class="kpi-label">Notifications Sent</div><div class="kpi-value"><?= e($stats['notifications_sent']) ?></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="kpi-card"><div class="kpi-label">Accepted Donors</div><div class="kpi-value"><?= e($stats['accepted_donors']) ?></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="kpi-card"><div class="kpi-label">Pending Appointments</div><div class="kpi-value"><?= e($stats['pending_appointments']) ?></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="kpi-card"><div class="kpi-label">Available Units</div><div class="kpi-value"><?= e($stats['available_units']) ?></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="kpi-card"><div class="kpi-label">Low Stock Groups</div><div class="kpi-value"><?= e($stats['low_stock_groups']) ?></div></div></div>
</section>

<section class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card-soft p-4">
            <h4 class="mb-3">Live Blood Request Map</h4>
            <div id="adminRequestMap" class="map-canvas map-lg"></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card-soft p-4">
            <h4 class="mb-3">Donor Availability Map</h4>
            <div id="adminDonorMap" class="map-canvas map-lg"></div>
        </div>
    </div>
</section>

<section class="row g-4 mb-4">
    <div class="col-lg-6"><div class="card-soft p-4"><h4 class="mb-3">Blood Stock by Group</h4><div class="chart-wrap"><canvas id="stockChart"></canvas></div></div></div>
    <div class="col-lg-6"><div class="card-soft p-4"><h4 class="mb-3">Request Urgency Chart</h4><div class="chart-wrap"><canvas id="urgencyChart"></canvas></div></div></div>
    <div class="col-lg-6"><div class="card-soft p-4"><h4 class="mb-3">Request Status Chart</h4><div class="chart-wrap"><canvas id="statusChart"></canvas></div></div></div>
    <div class="col-lg-6"><div class="card-soft p-4"><h4 class="mb-3">Donor Accept vs Decline</h4><div class="chart-wrap"><canvas id="acceptDeclineChart"></canvas></div></div></div>
</section>

<section class="row g-4 mb-4">
    <div class="col-lg-6"><div class="card-soft p-4"><h4 class="mb-3">Average Donor Distance by Urgency</h4><div class="chart-wrap"><canvas id="distanceChart"></canvas></div></div></div>
    <div class="col-lg-6"><div class="card-soft p-4"><h4 class="mb-3">Monthly Donations</h4><div class="chart-wrap"><canvas id="donationChart"></canvas></div></div></div>
    <div class="col-lg-6"><div class="card-soft p-4"><h4 class="mb-3">ML Likelihood Distribution</h4><div class="chart-wrap"><canvas id="mlBandChart"></canvas></div></div></div>
    <div class="col-lg-6"><div class="card-soft p-4"><h4 class="mb-3">Nearby Match Success</h4><div class="chart-wrap"><canvas id="matchSuccessChart"></canvas></div></div></div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    AdminMaps.loadAdminMaps('adminRequestMap', 'adminDonorMap');
    SmartBloodCharts.createBarChart('stockChart', <?= json_encode(array_map(static fn($r) => $r['blood_group'], $stockRows)) ?>, <?= json_encode(array_map(static fn($r) => (int) $r['total_units'], $stockRows)) ?>, 'Units');
    SmartBloodCharts.createDoughnutChart('urgencyChart', <?= json_encode(array_map(static fn($r) => ucfirst($r['urgency']), $urgencyRows)) ?>, <?= json_encode(array_map(static fn($r) => (int) $r['c'], $urgencyRows)) ?>);
    SmartBloodCharts.createDoughnutChart('statusChart', <?= json_encode(array_map(static fn($r) => $r['status'], $requestStatus)) ?>, <?= json_encode(array_map(static fn($r) => (int) $r['c'], $requestStatus)) ?>);
    SmartBloodCharts.createDoughnutChart('acceptDeclineChart', <?= json_encode(array_keys($acceptDecline)) ?>, <?= json_encode(array_values($acceptDecline)) ?>);
    SmartBloodCharts.createBarChart('distanceChart', <?= json_encode(array_map(static fn($r) => ucfirst($r['urgency']), $avgDistance)) ?>, <?= json_encode(array_map(static fn($r) => (float) $r['avg_distance'], $avgDistance)) ?>, 'Avg km');
    SmartBloodCharts.createBarChart('donationChart', <?= json_encode(array_map(static fn($r) => $r['month_label'], $donationTrend)) ?>, <?= json_encode(array_map(static fn($r) => (int) $r['total'], $donationTrend)) ?>, 'Donations');
    SmartBloodCharts.createDoughnutChart('mlBandChart', <?= json_encode(array_map(static fn($r) => $r['band'], $predictionSummary)) ?>, <?= json_encode(array_map(static fn($r) => (int) $r['c'], $predictionSummary)) ?>);
    SmartBloodCharts.createBarChart('matchSuccessChart', ['Matched', 'Fulfilled'], [<?= (int) $matchSuccess['matched'] ?>, <?= (int) $matchSuccess['fulfilled'] ?>], 'Requests');
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
