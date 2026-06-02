<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('donor');

header('Content-Type: application/json; charset=utf-8');

$user = current_user();
$donorId = (int) ($user['donor_id'] ?? 0);

$notifications = db_fetch_all(
    "SELECT
        dn.id,
        dn.request_id,
        dn.status,
        dn.distance_km,
        dn.matching_score,
        dn.predicted_probability,
        dn.created_at,
        br.hospital_name,
        br.hospital_address,
        COALESCE(br.city, br.hospital_city) AS city,
        COALESCE(br.latitude, br.hospital_latitude) AS latitude,
        COALESCE(br.longitude, br.hospital_longitude) AS longitude,
        br.blood_group,
        br.urgency,
        COALESCE(br.request_status, br.status) AS request_status
     FROM donor_notifications dn
     JOIN blood_requests br ON br.id = dn.request_id
     WHERE dn.donor_id = ?
     ORDER BY dn.created_at DESC",
    [$donorId]
);

echo json_encode([
    'ok' => true,
    'donor_location' => [
        'latitude' => isset($user['donor_latitude']) ? (float) $user['donor_latitude'] : (isset($user['latitude']) ? (float) $user['latitude'] : null),
        'longitude' => isset($user['donor_longitude']) ? (float) $user['donor_longitude'] : (isset($user['longitude']) ? (float) $user['longitude'] : null),
    ],
    'notifications' => $notifications,
]);

