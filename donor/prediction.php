<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('donor');

$user = current_user();
$donorId = (int) ($user['donor_id'] ?? 0);
$userId = (int) ($user['id'] ?? 0);

$openRequests = db_fetch_all(
    "SELECT br.*, u.full_name AS patient_name
     FROM blood_requests br
     JOIN patients p ON p.id = br.patient_id
     JOIN users u ON u.id = p.user_id
     WHERE br.status IN ('pending','matched','partially_fulfilled')
     ORDER BY br.created_at DESC
     LIMIT 25"
);

$eligibleRequests = [];
foreach ($openRequests as $request) {
    if (can_donate_to((string) $user['blood_group'], (string) $request['blood_group'])) {
        $eligibleRequests[] = $request;
    }
}

$selectedRequestId = (int) ($_GET['request_id'] ?? ($eligibleRequests[0]['id'] ?? 0));
$selectedRequest = null;
$prediction = null;

foreach ($eligibleRequests as $request) {
    if ((int) $request['id'] === $selectedRequestId) {
        $selectedRequest = $request;
        break;
    }
}

if ($selectedRequest) {
    $predictionData = predict_donor_likelihood([
        'donor_age' => (int) ($user['donor_age'] ?? 0),
        'donor_weight' => (float) ($user['donor_weight'] ?? 0),
        'blood_group' => (string) ($user['blood_group'] ?? ''),
        'last_donation_date' => $user['last_donation_date'] ?? null,
        'medical_condition_status' => (string) ($user['medical_condition_status'] ?? 'healthy'),
        'availability_status' => (string) ($user['availability_status'] ?? 'available'),
        'donor_latitude' => isset($user['donor_latitude']) ? (float) $user['donor_latitude'] : null,
        'donor_longitude' => isset($user['donor_longitude']) ? (float) $user['donor_longitude'] : null,
        'past_donations' => (int) ($user['past_donations'] ?? 0),
        'response_rate' => (float) ($user['response_rate'] ?? 0),
    ], $selectedRequest);
    $prediction = $predictionData['result'];
    log_prediction($donorId, (int) $selectedRequest['id'], $predictionData['result'], $predictionData['features']);
    audit_log($userId, 'prediction_viewed', 'blood_request', (int) $selectedRequest['id'], ['probability' => $prediction['probability']]);
}

$logs = db_fetch_all(
    "SELECT mp.*, br.hospital_name, br.blood_group
     FROM ml_predictions mp
     LEFT JOIN blood_requests br ON br.id = mp.request_id
     WHERE mp.donor_id = ?
     ORDER BY mp.created_at DESC
     LIMIT 12",
    [$donorId]
);

$pageTitle = 'Donor Prediction';
$showSidebar = true;
$mainContainerClass = 'container-fluid py-4';
include __DIR__ . '/../includes/header.php';
?>

<section class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="card-soft p-4 h-100">
            <h4 class="mb-3">Run Prediction</h4>
            <form method="get">
                <label class="form-label">Select Request</label>
                <select name="request_id" class="form-select mb-3">
                    <?php foreach ($eligibleRequests as $request): ?>
                        <option value="<?= e((int) $request['id']) ?>" <?= $selectedRequestId === (int) $request['id'] ? 'selected' : '' ?>>
                            BR-<?= e((int) $request['id']) ?> | <?= e($request['blood_group']) ?> | <?= e($request['hospital_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-danger w-100">Refresh Prediction</button>
            </form>
            <?php if (!$eligibleRequests): ?>
                <p class="small text-muted mt-3 mb-0">No compatible open requests available for prediction.</p>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card-soft p-4 h-100">
            <h4 class="mb-3">Prediction Result</h4>
            <?php if ($prediction && $selectedRequest): ?>
                <?php
                $distance = haversine_km(
                    isset($user['donor_latitude']) ? (float) $user['donor_latitude'] : null,
                    isset($user['donor_longitude']) ? (float) $user['donor_longitude'] : null,
                    isset($selectedRequest['hospital_latitude']) ? (float) $selectedRequest['hospital_latitude'] : null,
                    isset($selectedRequest['hospital_longitude']) ? (float) $selectedRequest['hospital_longitude'] : null
                );
                ?>
                <div class="row g-3">
                    <div class="col-md-4"><div class="workflow-step"><h6>Probability</h6><p class="mb-0 fw-bold"><?= e(number_format((float) $prediction['probability'] * 100, 2)) ?>%</p></div></div>
                    <div class="col-md-4"><div class="workflow-step"><h6>Class</h6><p class="mb-0 fw-bold"><?= e(ucfirst($prediction['predicted_class'])) ?></p></div></div>
                    <div class="col-md-4"><div class="workflow-step"><h6>Confidence</h6><p class="mb-0 fw-bold"><?= e($prediction['confidence_label']) ?></p></div></div>
                </div>
                <hr>
                <p class="mb-1"><strong>Request:</strong> BR-<?= e((int) $selectedRequest['id']) ?> (<?= e($selectedRequest['blood_group']) ?>)</p>
                <p class="mb-1"><strong>Hospital:</strong> <?= e($selectedRequest['hospital_name']) ?>, <?= e($selectedRequest['hospital_city']) ?></p>
                <p class="mb-0"><strong>Distance:</strong> <?= e(number_format($distance, 2)) ?> km</p>
            <?php else: ?>
                <p class="text-muted mb-0">Select a request to evaluate your donation likelihood score.</p>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="card-soft p-4">
    <h4 class="mb-3">Recent Prediction Logs</h4>
    <div class="table-responsive">
        <table class="table table-modern align-middle">
            <thead>
            <tr><th>Time</th><th>Request</th><th>Model</th><th>Score</th><th>Class</th><th>Confidence</th></tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= e(date('Y-m-d H:i', strtotime((string) $log['created_at']))) ?></td>
                    <td><?= $log['request_id'] ? 'BR-' . e((int) $log['request_id']) . ' / ' . e($log['blood_group']) : 'N/A' ?></td>
                    <td><?= e($log['model_name']) ?></td>
                    <td><?= e(number_format((float) $log['probability_score'] * 100, 1)) ?>%</td>
                    <td><?= e($log['predicted_class']) ?></td>
                    <td><?= e($log['confidence_label']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$logs): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No prediction logs available.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

