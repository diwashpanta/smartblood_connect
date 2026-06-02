<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('donor');

$user = current_user();
$donorId = (int) ($user['donor_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_with_csrf();
    $requestId = (int) ($_POST['request_id'] ?? 0);
    if ($requestId > 0) {
        $request = fetch_blood_request($requestId);
        $donorBloodGroup = (string) ($user['donor_blood_group'] ?? $user['blood_group'] ?? '');
        if ($request && can_donate_to($donorBloodGroup, (string) $request['blood_group'])) {
            $existing = db_fetch_one("SELECT id FROM donor_notifications WHERE request_id = ? AND donor_id = ?", [$requestId, $donorId]);
            if ($existing) {
                db_query("UPDATE donor_notifications SET status = 'accepted', responded_at = NOW() WHERE id = ?", [(int) $existing['id']]);
            } else {
                $prediction = predict_donor_likelihood([
                    'donor_age' => (int) ($user['donor_age'] ?? 0),
                    'donor_weight' => (float) ($user['donor_weight'] ?? 0),
                    'blood_group' => $donorBloodGroup,
                    'last_donation_date' => $user['last_donation_date'] ?? null,
                    'medical_condition_status' => (string) ($user['medical_condition_status'] ?? 'healthy'),
                    'availability_status' => (string) ($user['availability_status'] ?? 'available'),
                    'donor_latitude' => isset($user['donor_latitude']) ? (float) $user['donor_latitude'] : null,
                    'donor_longitude' => isset($user['donor_longitude']) ? (float) $user['donor_longitude'] : null,
                    'past_donations' => (int) ($user['past_donations'] ?? 0),
                    'total_donations' => (int) ($user['total_donations'] ?? 0),
                    'response_rate' => (float) ($user['response_rate'] ?? 0),
                ], $request);

                $probability = (float) ($prediction['result']['probability'] ?? 0.0);
                $distance = haversine_km(
                    isset($user['donor_latitude']) ? (float) $user['donor_latitude'] : null,
                    isset($user['donor_longitude']) ? (float) $user['donor_longitude'] : null,
                    isset($request['latitude']) ? (float) $request['latitude'] : (isset($request['hospital_latitude']) ? (float) $request['hospital_latitude'] : null),
                    isset($request['longitude']) ? (float) $request['longitude'] : (isset($request['hospital_longitude']) ? (float) $request['hospital_longitude'] : null)
                );

                db_query(
                    "INSERT INTO donor_notifications
                     (request_id, blood_request_id, donor_id, probability_score, predicted_probability, matching_score, distance_km, status, message, responded_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'accepted', ?, NOW())",
                    [
                        $requestId,
                        $requestId,
                        $donorId,
                        $probability,
                        $probability,
                        round($probability * 100, 2),
                        $distance,
                        "Donor volunteered via nearby requests panel for {$request['blood_group']} at {$request['hospital_name']}.",
                    ]
                );
                log_prediction($donorId, $requestId, $prediction['result'], $prediction['features']);
            }

            db_query("UPDATE blood_requests SET status = 'matched', request_status = 'matched', updated_at = NOW() WHERE id = ? AND COALESCE(request_status,status) = 'pending'", [$requestId]);
            create_patient_notification((int) $request['patient_id'], $requestId, "Donor {$user['full_name']} expressed willingness to donate.");
            audit_log((int) $user['id'], 'donor_interest', 'blood_request', $requestId, ['donor_id' => $donorId]);
            set_flash('success', 'Thanks for volunteering. Your response has been recorded.');
        }
    }
    redirect('donor/nearby_requests.php');
}

$requests = db_fetch_all(
    "SELECT br.*, COALESCE(br.request_status, br.status) AS request_status, COALESCE(br.city, br.hospital_city) AS city, u.full_name AS patient_name
     FROM blood_requests br
     JOIN patients p ON p.id = br.patient_id
     JOIN users u ON u.id = p.user_id
     WHERE COALESCE(br.request_status, br.status) IN ('pending','matched','partially_fulfilled')
     ORDER BY br.created_at DESC"
);

$list = [];
foreach ($requests as $request) {
    $donorBloodGroup = (string) ($user['donor_blood_group'] ?? $user['blood_group'] ?? '');
    if (!can_donate_to($donorBloodGroup, (string) $request['blood_group'])) {
        continue;
    }
    $distance = haversine_km(
        isset($user['donor_latitude']) ? (float) $user['donor_latitude'] : null,
        isset($user['donor_longitude']) ? (float) $user['donor_longitude'] : null,
        isset($request['latitude']) ? (float) $request['latitude'] : (isset($request['hospital_latitude']) ? (float) $request['hospital_latitude'] : null),
        isset($request['longitude']) ? (float) $request['longitude'] : (isset($request['hospital_longitude']) ? (float) $request['hospital_longitude'] : null)
    );
    $existing = db_fetch_one(
        "SELECT status FROM donor_notifications WHERE request_id = ? AND donor_id = ?",
        [(int) $request['id'], $donorId]
    );
    $request['distance_km'] = $distance;
    $request['my_notification_status'] = $existing['status'] ?? null;
    $list[] = $request;
}
usort($list, static fn ($a, $b) => $a['distance_km'] <=> $b['distance_km']);

$pageTitle = 'Nearby Requests';
$showSidebar = true;
$mainContainerClass = 'container-fluid py-4';
include __DIR__ . '/../includes/header.php';
?>

<section class="card-soft p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Nearby Compatible Requests</h1>
        <span class="text-muted small">Sorted by proximity to your current location</span>
    </div>

    <div class="table-responsive">
        <table class="table table-modern align-middle">
            <thead>
            <tr>
                <th>Request</th>
                <th>Patient</th>
                <th>Blood</th>
                <th>Units</th>
                <th>Hospital</th>
                <th>Distance</th>
                <th>Urgency</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($list as $request): ?>
                <tr>
                    <td>BR-<?= e((int) $request['id']) ?></td>
                    <td><?= e($request['patient_name']) ?></td>
                    <td><?= e($request['blood_group']) ?></td>
                    <td><?= e((int) $request['units_fulfilled']) ?>/<?= e((int) $request['units_needed']) ?></td>
                    <td><?= e($request['hospital_name']) ?>, <?= e($request['city']) ?></td>
                    <td><?= e(number_format((float) $request['distance_km'], 2)) ?> km</td>
                    <td><span class="badge bg-light text-dark"><?= e(ucfirst($request['urgency'])) ?></span></td>
                    <td>
                        <?php if ($request['my_notification_status']): ?>
                            <span class="badge badge-soft <?= e(badge_class_for_status($request['my_notification_status'])) ?>"><?= e($request['my_notification_status']) ?></span>
                        <?php else: ?>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="request_id" value="<?= e((int) $request['id']) ?>">
                                <button class="btn btn-sm btn-outline-primary">I Can Donate</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$list): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No compatible requests near your location right now.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
