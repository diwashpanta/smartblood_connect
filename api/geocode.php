<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim((string) ($_GET['q'] ?? ''));
$lat = trim((string) ($_GET['lat'] ?? ''));
$lng = trim((string) ($_GET['lng'] ?? ''));

if ($q !== '') {
    $results = sb_nominatim_search($q);
    echo json_encode([
        'ok' => true,
        'mode' => 'search',
        'results' => $results,
    ]);
    exit;
}

$coords = sb_validate_lat_lng($lat, $lng);
if (!sb_has_valid_lat_lng($coords['lat'], $coords['lng'])) {
    echo json_encode(['ok' => false, 'message' => 'Query or coordinates are required.', 'results' => []]);
    exit;
}

$result = sb_nominatim_reverse((float) $coords['lat'], (float) $coords['lng']);
if (!$result) {
    echo json_encode(['ok' => false, 'mode' => 'reverse', 'message' => 'Address not found for selected location.']);
    exit;
}

echo json_encode([
    'ok' => true,
    'mode' => 'reverse',
    'result' => $result,
]);
