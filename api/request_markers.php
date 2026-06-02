<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$requestId = (int) ($_GET['request_id'] ?? 0);
$user = current_user();

if ($requestId <= 0) {
    $requests = db_fetch_all(
        "SELECT id, hospital_name, blood_group, urgency, COALESCE(request_status, status) AS request_status,
                COALESCE(latitude, hospital_latitude) AS latitude, COALESCE(longitude, hospital_longitude) AS longitude
         FROM blood_requests
         WHERE COALESCE(request_status, status) IN ('pending','matched','partially_fulfilled')
         ORDER BY created_at DESC LIMIT 50"
    );
    echo json_encode(['ok' => true, 'requests' => $requests]);
    exit;
}

$request = fetch_blood_request($requestId);
if (!$request) {
    echo json_encode(['ok' => false, 'message' => 'Request not found']);
    exit;
}

if (($user['role'] ?? '') === 'patient' && (int) ($user['patient_id'] ?? 0) !== (int) $request['patient_id']) {
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

$donors = db_fetch_all(
    "SELECT
        dn.donor_id,
        dn.status,
        dn.distance_km,
        dn.matching_score,
        dn.predicted_probability,
        u.full_name,
        COALESCE(d.blood_group, u.blood_group) AS blood_group,
        COALESCE(d.latitude, u.latitude) AS latitude,
        COALESCE(d.longitude, u.longitude) AS longitude
     FROM donor_notifications dn
     JOIN donors d ON d.id = dn.donor_id
     JOIN users u ON u.id = d.user_id
     WHERE dn.request_id = ?",
    [$requestId]
);

echo json_encode([
    'ok' => true,
    'request' => [
        'id' => (int) $request['id'],
        'hospital_name' => (string) $request['hospital_name'],
        'blood_group' => (string) $request['blood_group'],
        'urgency' => (string) $request['urgency'],
        'status' => (string) ($request['request_status'] ?? $request['status']),
        'latitude' => (float) ($request['latitude'] ?? $request['hospital_latitude'] ?? 0),
        'longitude' => (float) ($request['longitude'] ?? $request['hospital_longitude'] ?? 0),
    ],
    'donors' => $donors,
]);

