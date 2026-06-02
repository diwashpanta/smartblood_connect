<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$requestId = (int) ($_GET['request_id'] ?? 0);
if ($requestId <= 0) {
    echo json_encode(['ok' => false, 'message' => 'request_id is required']);
    exit;
}

$request = fetch_blood_request($requestId);
if (!$request) {
    echo json_encode(['ok' => false, 'message' => 'Request not found']);
    exit;
}

$radius = (float) ($_GET['radius_km'] ?? 0);
$candidates = sb_find_matching_donors($request, 30, 50.0);
if ($radius > 0) {
    $candidates = array_values(array_filter($candidates, static fn ($d) => (float) $d['distance_km'] <= $radius));
}

$rows = array_map(
    static function (array $d): array {
        return [
            'donor_id' => (int) $d['donor_id'],
            'full_name' => (string) $d['full_name'],
            'blood_group' => (string) $d['blood_group'],
            'city' => (string) ($d['city'] ?? ''),
            'address' => (string) ($d['address'] ?? ''),
            'latitude' => (float) $d['latitude'],
            'longitude' => (float) $d['longitude'],
            'distance_km' => (float) $d['distance_km'],
            'matching_score' => (float) ($d['matching_score'] ?? 0.0),
            'prediction_probability' => (float) ($d['prediction']['result']['probability'] ?? 0.0),
        ];
    },
    $candidates
);

echo json_encode([
    'ok' => true,
    'request_id' => $requestId,
    'donors' => $rows,
]);

