<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('patient');

$user = current_user();
$patientId = (int) ($user['patient_id'] ?? 0);

$stats = [
    'total' => 0,
    'pending' => 0,
    'fulfilled' => 0,
    'notifications' => 0,
];
$statusLabels = ['pending', 'matched', 'partially_fulfilled', 'fulfilled', 'rejected', 'cancelled'];
$statusCounts = array_fill_keys($statusLabels, 0);
$recentRequests = [];
$recentNotifications = [];
$availabilitySummary = [];

try {
    $stats['total'] = (int) (db_fetch_one("SELECT COUNT(*) AS c FROM blood_requests WHERE patient_id = ?", [$patientId])['c'] ?? 0);
    $stats['pending'] = (int) (db_fetch_one("SELECT COUNT(*) AS c FROM blood_requests WHERE patient_id = ? AND COALESCE(request_status,status) IN ('pending','matched','partially_fulfilled')", [$patientId])['c'] ?? 0);
    $stats['fulfilled'] = (int) (db_fetch_one("SELECT COUNT(*) AS c FROM blood_requests WHERE patient_id = ? AND COALESCE(request_status,status) = 'fulfilled'", [$patientId])['c'] ?? 0);
    $stats['notifications'] = (int) (db_fetch_one("SELECT COUNT(*) AS c FROM patient_notifications WHERE patient_id = ? AND is_read = 0", [$patientId])['c'] ?? 0);

    $statusRows = db_fetch_all(
        "SELECT COALESCE(request_status,status) AS status, COUNT(*) AS c FROM blood_requests WHERE patient_id = ? GROUP BY COALESCE(request_status,status)",
        [$patientId]
    );
    foreach ($statusRows as $row) {
        $statusCounts[$row['status']] = (int) $row['c'];
    }

    $recentRequests = db_fetch_all(
        "SELECT br.*, COALESCE(br.request_status, br.status) AS request_status
         FROM blood_requests br
         WHERE br.patient_id = ?
         ORDER BY br.created_at DESC
         LIMIT 6",
        [$patientId]
    );

    $recentNotifications = db_fetch_all(
        "SELECT * FROM patient_notifications WHERE patient_id = ? ORDER BY created_at DESC LIMIT 6",
        [$patientId]
    );

    $availabilitySummary = db_fetch_all(
        "SELECT blood_group, COALESCE(SUM(quantity_units),0) AS units
         FROM blood_inventory
         WHERE status = 'available' AND expiry_date >= CURDATE()
         GROUP BY blood_group
         ORDER BY FIELD(blood_group,'A+','A-','B+','B-','AB+','AB-','O+','O-')"
    );
} catch (Throwable $e) {
    set_flash('danger', 'Unable to load dashboard metrics. Please verify database setup.');
}

$pageTitle = 'Patient Dashboard';
$showSidebar = true;
$withChartJs = true;
$mainContainerClass = 'container-fluid py-4';
include __DIR__ . '/../includes/header.php';
?>

<section class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-label">My Requests</div>
            <div class="kpi-value"><?= e($stats['total']) ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-label">Active Requests</div>
            <div class="kpi-value"><?= e($stats['pending']) ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-label">Fulfilled Requests</div>
            <div class="kpi-value"><?= e($stats['fulfilled']) ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-label">Unread Alerts</div>
            <div class="kpi-value"><?= e($stats['notifications']) ?></div>
        </div>
    </div>
</section>

<section class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card-soft p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="mb-0">Request Status Overview</h3>
                <a href="<?= e(url('patient/create_request.php')) ?>" class="btn btn-danger btn-sm">New Request</a>
            </div>
            <div class="chart-wrap">
                <canvas id="patientRequestStatusChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card-soft p-4 h-100">
            <h4 class="mb-3">Inventory Snapshot</h4>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                    <tr><th>Group</th><th class="text-end">Units</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($availabilitySummary as $row): ?>
                        <tr>
                            <td><?= e($row['blood_group']) ?></td>
                            <td class="text-end fw-semibold"><?= e((int) $row['units']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$availabilitySummary): ?>
                        <tr><td colspan="2" class="text-muted small">No inventory data.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<section class="row g-4">
    <div class="col-lg-7">
        <div class="card-soft p-4 h-100">
            <h4 class="mb-3">Recent Requests</h4>
            <div class="table-responsive">
                <table class="table table-modern table-sm align-middle">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Blood</th>
                        <th>Units</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentRequests as $request): ?>
                        <tr>
                            <td><a href="<?= e(url('patient/request_details.php?id=' . (int) $request['id'])) ?>">BR-<?= e((int) $request['id']) ?></a></td>
                            <td><?= e($request['blood_group']) ?></td>
                            <td><?= e((int) $request['units_fulfilled']) ?>/<?= e((int) $request['units_needed']) ?></td>
                            <td><span class="badge badge-soft <?= e(badge_class_for_status($request['request_status'])) ?>"><?= e($request['request_status']) ?></span></td>
                            <td><?= e(date('Y-m-d', strtotime((string) $request['created_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recentRequests): ?>
                        <tr><td colspan="5" class="text-muted">No requests yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card-soft p-4 h-100">
            <h4 class="mb-3">Notification Timeline</h4>
            <div class="timeline">
                <?php foreach ($recentNotifications as $notice): ?>
                    <div class="event">
                        <div class="small fw-semibold"><?= e($notice['message']) ?></div>
                        <div class="text-muted small"><?= e(date('Y-m-d H:i', strtotime((string) $notice['created_at']))) ?></div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$recentNotifications): ?>
                    <p class="text-muted small mb-0">No notifications yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    SmartBloodCharts.createDoughnutChart(
        'patientRequestStatusChart',
        <?= json_encode(array_map(static fn ($key) => ucwords(str_replace('_', ' ', $key)), array_keys($statusCounts))) ?>,
        <?= json_encode(array_values($statusCounts)) ?>
    );
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
