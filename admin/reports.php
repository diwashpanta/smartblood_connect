<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$withChartJs = true;

$requestByUrgency = db_fetch_all("SELECT urgency, COUNT(*) AS c FROM blood_requests GROUP BY urgency");
$requestByStatus = db_fetch_all("SELECT COALESCE(request_status,status) AS status, COUNT(*) AS c FROM blood_requests GROUP BY COALESCE(request_status,status)");
$monthlyIssuance = db_fetch_all(
    "SELECT DATE_FORMAT(issued_at, '%Y-%m') AS m, SUM(units_issued) AS units
     FROM blood_issuance
     GROUP BY DATE_FORMAT(issued_at, '%Y-%m')
     ORDER BY m"
);
$topDonors = db_fetch_all(
    "SELECT u.full_name, COALESCE(d.blood_group, u.blood_group) AS blood_group, d.past_donations, d.response_rate
     FROM donors d
     JOIN users u ON u.id = d.user_id
     ORDER BY d.past_donations DESC, d.response_rate DESC
     LIMIT 10"
);

$pageTitle = 'Reports';
$showSidebar = true;
$mainContainerClass = 'container-fluid py-4';
include __DIR__ . '/../includes/header.php';
?>

<section class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card-soft p-4">
            <h4 class="mb-3">Request Urgency Distribution</h4>
            <div class="chart-wrap"><canvas id="urgencyChart"></canvas></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card-soft p-4">
            <h4 class="mb-3">Request Status Distribution</h4>
            <div class="chart-wrap"><canvas id="statusReportChart"></canvas></div>
        </div>
    </div>
</section>

<section class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="card-soft p-4">
            <h4 class="mb-3">Monthly Issuance Trend</h4>
            <div class="chart-wrap"><canvas id="issuanceChart"></canvas></div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card-soft p-4 h-100">
            <h4 class="mb-3">Request Workflow Diagram</h4>
            <div class="timeline">
                <div class="event"><div class="fw-semibold small">Patient creates blood request</div></div>
                <div class="event"><div class="fw-semibold small">Inventory compatibility check executes</div></div>
                <div class="event"><div class="fw-semibold small">Eligible donors ranked and notified</div></div>
                <div class="event"><div class="fw-semibold small">Donor accepts and appointment scheduled</div></div>
                <div class="event"><div class="fw-semibold small">Donation collected and request fulfilled</div></div>
            </div>
        </div>
    </div>
</section>

<section class="card-soft p-4">
    <h4 class="mb-3">Top Donor Contributions</h4>
    <div class="table-responsive">
        <table class="table table-modern align-middle">
            <thead>
            <tr><th>Donor</th><th>Blood Group</th><th>Past Donations</th><th>Response Rate</th></tr>
            </thead>
            <tbody>
            <?php foreach ($topDonors as $donor): ?>
                <tr>
                    <td><?= e($donor['full_name']) ?></td>
                    <td><?= e($donor['blood_group']) ?></td>
                    <td><?= e((int) $donor['past_donations']) ?></td>
                    <td><?= e(number_format((float) $donor['response_rate'], 2)) ?>%</td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$topDonors): ?>
                <tr><td colspan="4" class="text-center text-muted py-4">No donor report data yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    SmartBloodCharts.createDoughnutChart(
        'urgencyChart',
        <?= json_encode(array_map(static fn($r) => ucfirst($r['urgency']), $requestByUrgency)) ?>,
        <?= json_encode(array_map(static fn($r) => (int) $r['c'], $requestByUrgency)) ?>
    );
    SmartBloodCharts.createDoughnutChart(
        'statusReportChart',
        <?= json_encode(array_map(static fn($r) => $r['status'], $requestByStatus)) ?>,
        <?= json_encode(array_map(static fn($r) => (int) $r['c'], $requestByStatus)) ?>
    );
    SmartBloodCharts.createBarChart(
        'issuanceChart',
        <?= json_encode(array_map(static fn($r) => $r['m'], $monthlyIssuance)) ?>,
        <?= json_encode(array_map(static fn($r) => (int) $r['units'], $monthlyIssuance)) ?>,
        'Issued Units'
    );
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
