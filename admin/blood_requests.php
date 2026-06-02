<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$admin = current_user();
$adminId = (int) ($admin['admin_id'] ?? 0);
$statusFilter = trim((string) ($_GET['status'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_with_csrf();
    $action = $_POST['action'] ?? '';
    $requestId = (int) ($_POST['request_id'] ?? 0);

    $request = fetch_blood_request($requestId);
    if ($request) {
        $requestState = (string) ($request['request_status'] ?? $request['status'] ?? 'pending');

        if ($action === 'approve') {
            db_query(
                "UPDATE blood_requests
                 SET approved_by = ?,
                     approved_at = NOW(),
                     status = IF(COALESCE(request_status, status)='rejected','pending',COALESCE(request_status, status)),
                     request_status = IF(COALESCE(request_status, status)='rejected','pending',COALESCE(request_status, status)),
                     updated_at = NOW()
                 WHERE id = ?",
                [$adminId ?: null, $requestId]
            );
            $result = run_request_matching_pipeline($requestId, (int) $admin['id']);
            create_patient_notification((int) $request['patient_id'], $requestId, "Admin approved request. Issued: {$result['issued_units']}, notifications: {$result['notifications']}.");
            audit_log((int) $admin['id'], 'request_approved', 'blood_request', $requestId, $result);
            set_flash('success', 'Request approved and matching executed.');
        }

        if ($action === 'reject' && !in_array($requestState, ['fulfilled', 'cancelled'], true)) {
            db_query("UPDATE blood_requests SET status = 'rejected', request_status = 'rejected', approved_by = ?, approved_at = NOW(), updated_at = NOW() WHERE id = ?", [$adminId ?: null, $requestId]);
            create_patient_notification((int) $request['patient_id'], $requestId, 'Request has been rejected by admin.');
            audit_log((int) $admin['id'], 'request_rejected', 'blood_request', $requestId);
            set_flash('warning', 'Request rejected.');
        }

        if ($action === 'rematch' && !in_array($requestState, ['rejected', 'cancelled'], true)) {
            $result = run_request_matching_pipeline($requestId, (int) $admin['id']);
            set_flash('success', "Pipeline rerun. Issued {$result['issued_units']} unit(s), notified {$result['notifications']} donor(s).");
        }
    }
    redirect('admin/blood_requests.php' . ($statusFilter !== '' ? '?status=' . urlencode($statusFilter) : ''));
}

$sql = "SELECT br.*, COALESCE(br.request_status, br.status) AS request_status, COALESCE(br.city, br.hospital_city) AS city,
               u.full_name AS patient_name, u.phone AS patient_phone
        FROM blood_requests br
        JOIN patients p ON p.id = br.patient_id
        JOIN users u ON u.id = p.user_id
        WHERE 1=1";
$params = [];
if ($statusFilter !== '') {
    $sql .= " AND COALESCE(br.request_status, br.status) = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY br.created_at DESC";
$requests = db_fetch_all($sql, $params);

$pageTitle = 'Blood Requests';
$showSidebar = true;
$mainContainerClass = 'container-fluid py-4';
include __DIR__ . '/../includes/header.php';
?>

<section class="card-soft p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Blood Requests</h1>
        <form method="get" class="d-flex gap-2">
            <select name="status" class="form-select form-select-sm">
                <option value="">All Status</option>
                <?php foreach (['pending','matched','partially_fulfilled','fulfilled','rejected','cancelled'] as $status): ?>
                    <option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= e(ucwords(str_replace('_',' ', $status))) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-outline-secondary">Filter</button>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-modern align-middle">
            <thead>
            <tr>
                <th>Request</th><th>Patient</th><th>Blood</th><th>Units</th><th>Hospital</th><th>Urgency</th><th>Status</th><th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($requests as $request): ?>
                <tr>
                    <td><a href="<?= e(url('admin/request_details.php?id=' . (int) $request['id'])) ?>">BR-<?= e((int) $request['id']) ?></a><div class="small text-muted"><?= e(date('Y-m-d H:i', strtotime((string) $request['created_at']))) ?></div></td>
                    <td><?= e($request['patient_name']) ?><div class="small text-muted"><?= e($request['patient_phone']) ?></div></td>
                    <td><?= e($request['blood_group']) ?></td>
                    <td><?= e((int) $request['units_fulfilled']) ?>/<?= e((int) $request['units_needed']) ?></td>
                    <td><?= e($request['hospital_name']) ?>, <?= e($request['city']) ?></td>
                    <td><span class="badge bg-light text-dark"><?= e(ucfirst($request['urgency'])) ?></span></td>
                    <td><span class="badge badge-soft <?= e(badge_class_for_status($request['request_status'])) ?>"><?= e($request['request_status']) ?></span></td>
                    <td>
                        <div class="d-flex flex-column gap-1">
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="request_id" value="<?= e((int) $request['id']) ?>">
                                <input type="hidden" name="action" value="approve">
                                <button class="btn btn-sm btn-outline-success">Approve</button>
                            </form>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="request_id" value="<?= e((int) $request['id']) ?>">
                                <input type="hidden" name="action" value="rematch">
                                <button class="btn btn-sm btn-outline-primary">Run Match</button>
                            </form>
                            <?php if (!in_array($request['request_status'], ['fulfilled', 'cancelled'], true)): ?>
                                <form method="post" data-confirm="Reject this request?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="request_id" value="<?= e((int) $request['id']) ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button class="btn btn-sm btn-outline-danger">Reject</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
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
