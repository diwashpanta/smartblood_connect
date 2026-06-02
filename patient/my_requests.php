<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('patient');

$user = current_user();
$patientId = (int) ($user['patient_id'] ?? 0);
$statusFilter = trim((string) ($_GET['status'] ?? ''));

$sql = "SELECT br.*, COALESCE(br.request_status, br.status) AS request_status, COALESCE(br.city, br.hospital_city) AS city
        FROM blood_requests br
        WHERE br.patient_id = ?";
$params = [$patientId];
if ($statusFilter !== '') {
    $sql .= " AND COALESCE(br.request_status, br.status) = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY br.created_at DESC";

$requests = db_fetch_all($sql, $params);

$pageTitle = 'My Requests';
$showSidebar = true;
$mainContainerClass = 'container-fluid py-4';
include __DIR__ . '/../includes/header.php';
?>

<section class="card-soft p-4">
    <div class="d-flex justify-content-between flex-wrap gap-2 align-items-center mb-3">
        <h1 class="h3 mb-0">My Blood Requests</h1>
        <div class="d-flex gap-2">
            <form class="d-flex gap-2" method="get">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    <?php foreach (['pending','matched','partially_fulfilled','fulfilled','rejected','cancelled'] as $s): ?>
                        <option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e(ucwords(str_replace('_',' ', $s))) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-outline-secondary">Filter</button>
            </form>
            <a href="<?= e(url('patient/create_request.php')) ?>" class="btn btn-sm btn-danger">Create Request</a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-modern align-middle">
            <thead>
            <tr>
                <th>ID</th>
                <th>Blood</th>
                <th>Units</th>
                <th>Hospital</th>
                <th>Urgency</th>
                <th>Status</th>
                <th>Created</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($requests as $request): ?>
                <tr>
                    <td>BR-<?= e((int) $request['id']) ?></td>
                    <td><?= e($request['blood_group']) ?></td>
                    <td><?= e((int) $request['units_fulfilled']) ?>/<?= e((int) $request['units_needed']) ?></td>
                    <td><?= e($request['hospital_name']) ?>, <?= e($request['city']) ?></td>
                    <td><span class="badge bg-light text-dark"><?= e(ucfirst($request['urgency'])) ?></span></td>
                    <td><span class="badge badge-soft <?= e(badge_class_for_status($request['request_status'])) ?>"><?= e($request['request_status']) ?></span></td>
                    <td><?= e(date('Y-m-d H:i', strtotime((string) $request['created_at']))) ?></td>
                    <td><a class="btn btn-sm btn-outline-primary" href="<?= e(url('patient/request_details.php?id=' . (int) $request['id'])) ?>">Details</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$requests): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No requests found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
