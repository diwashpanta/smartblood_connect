<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

header('Content-Type: application/json; charset=utf-8');

$requestId = (int) ($_GET['request_id'] ?? 0);

$requestSql = "SELECT
    br.id,
    br.hospital_name,
    br.hospital_address,
    COALESCE(br.city, br.hospital_city) AS city,
    COALESCE(br.latitude, br.hospital_latitude) AS latitude,
    COALESCE(br.longitude, br.hospital_longitude) AS longitude,
    br.blood_group,
    br.urgency,
    COALESCE(br.request_status, br.status) AS request_status,
    br.units_needed,
    br.units_fulfilled
 FROM blood_requests br
 WHERE COALESCE(br.request_status, br.status) IN ('pending','matched','partially_fulfilled')";
$params = [];
if ($requestId > 0) {
    $requestSql .= " AND br.id = ?";
    $params[] = $requestId;
}
$requestSql .= " ORDER BY br.created_at DESC LIMIT 100";

$requests = db_fetch_all($requestSql, $params);

$donorSql = "SELECT
    d.id AS donor_id,
    u.full_name,
    COALESCE(d.blood_group, u.blood_group) AS blood_group,
    COALESCE(d.city, u.city) AS city,
    COALESCE(d.latitude, u.latitude) AS latitude,
    COALESCE(d.longitude, u.longitude) AS longitude,
    COALESCE(d.availability_status, d.available_status) AS availability_status,
    d.is_verified,
    d.is_eligible
 FROM donors d
 JOIN users u ON u.id = d.user_id
 WHERE COALESCE(d.availability_status, d.available_status) = 'available'
   AND d.is_verified = 1";
$donorParams = [];
if ($requestId > 0) {
    $donorSql .= " AND d.id IN (SELECT donor_id FROM donor_notifications WHERE request_id = ?)";
    $donorParams[] = $requestId;
}
$donorSql .= " ORDER BY u.full_name";
$donors = db_fetch_all($donorSql, $donorParams);

echo json_encode([
    'ok' => true,
    'requests' => $requests,
    'donors' => $donors,
]);

