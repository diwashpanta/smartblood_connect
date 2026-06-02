<?php

declare(strict_types=1);

function sb_validate_coordinate(?string $value, string $axis): ?float
{
    if ($value === null || trim($value) === '') {
        return null;
    }
    if (!is_numeric($value)) {
        return null;
    }
    $number = (float) $value;
    if ($axis === 'lat' && ($number < -90 || $number > 90)) {
        return null;
    }
    if ($axis === 'lng' && ($number < -180 || $number > 180)) {
        return null;
    }
    return round($number, 7);
}

function sb_validate_lat_lng(?string $lat, ?string $lng): array
{
    return [
        'lat' => sb_validate_coordinate($lat, 'lat'),
        'lng' => sb_validate_coordinate($lng, 'lng'),
    ];
}

function sb_has_valid_lat_lng(?float $lat, ?float $lng): bool
{
    return $lat !== null && $lng !== null && $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;
}

function sb_is_zero_coordinate_pair(?float $lat, ?float $lng): bool
{
    if (!sb_has_valid_lat_lng($lat, $lng)) {
        return false;
    }
    return abs((float) $lat) < 0.0001 && abs((float) $lng) < 0.0001;
}

function sb_default_map_center(): array
{
    return [
        'lat' => MAP_DEFAULT_LAT,
        'lng' => MAP_DEFAULT_LNG,
        'zoom' => MAP_DEFAULT_ZOOM,
    ];
}

function sb_map_provider(): string
{
    $provider = MAP_PROVIDER;
    if ($provider === 'google' && MAP_GOOGLE_API_KEY === '') {
        return 'osm';
    }
    return in_array($provider, ['osm', 'google'], true) ? $provider : 'osm';
}

function sb_map_config(): array
{
    return [
        'provider' => sb_map_provider(),
        'osmTileUrl' => MAP_OSM_TILE_URL,
        'googleApiKey' => MAP_GOOGLE_API_KEY,
        'defaultLat' => MAP_DEFAULT_LAT,
        'defaultLng' => MAP_DEFAULT_LNG,
        'defaultZoom' => MAP_DEFAULT_ZOOM,
        'baseUrl' => APP_BASE_URL,
    ];
}

function sb_location_label(?string $address, ?string $city, ?float $lat, ?float $lng): string
{
    $parts = array_filter([trim((string) $address), trim((string) $city)]);
    if ($parts) {
        return implode(', ', $parts);
    }
    if (sb_has_valid_lat_lng($lat, $lng)) {
        return number_format((float) $lat, 5) . ', ' . number_format((float) $lng, 5);
    }
    return 'Location not set';
}

function sb_nominatim_search(string $query): array
{
    $query = trim($query);
    if ($query === '') {
        return [];
    }

    $url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&addressdetails=1&limit=6&q=' . rawurlencode($query);
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 8,
            'header' => "User-Agent: SmartBloodConnect/1.0\r\nAccept-Language: en\r\n",
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    if (!is_string($response) || trim($response) === '') {
        return [];
    }
    $data = json_decode($response, true);
    if (!is_array($data)) {
        return [];
    }

    $results = [];
    foreach ($data as $row) {
        $lat = isset($row['lat']) ? (float) $row['lat'] : null;
        $lng = isset($row['lon']) ? (float) $row['lon'] : null;
        if (!sb_has_valid_lat_lng($lat, $lng)) {
            continue;
        }
        $displayName = (string) ($row['display_name'] ?? '');
        $addressParts = is_array($row['address'] ?? null) ? $row['address'] : [];
        $city = (string) (
            $addressParts['city']
            ?? $addressParts['town']
            ?? $addressParts['village']
            ?? $addressParts['municipality']
            ?? $addressParts['county']
            ?? $addressParts['state_district']
            ?? $addressParts['state']
            ?? ''
        );
        $streetAddress = trim((string) (
            ($addressParts['house_number'] ?? '') .
            (((string) ($addressParts['house_number'] ?? '')) !== '' && ((string) ($addressParts['road'] ?? '')) !== '' ? ' ' : '') .
            ($addressParts['road'] ?? '')
        ));
        if ($streetAddress === '') {
            $streetAddress = trim((string) ($addressParts['neighbourhood'] ?? $addressParts['suburb'] ?? $displayName));
        }

        $results[] = [
            'display_name' => $displayName,
            'lat' => round((float) $lat, 7),
            'lng' => round((float) $lng, 7),
            'city' => $city,
            'address' => $streetAddress,
        ];
    }
    return $results;
}

function sb_nominatim_reverse(float $lat, float $lng): ?array
{
    if (!sb_has_valid_lat_lng($lat, $lng)) {
        return null;
    }

    $url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&addressdetails=1&lat=' . rawurlencode((string) $lat) . '&lon=' . rawurlencode((string) $lng);
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 8,
            'header' => "User-Agent: SmartBloodConnect/1.0\r\nAccept-Language: en\r\n",
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    if (!is_string($response) || trim($response) === '') {
        return null;
    }

    $row = json_decode($response, true);
    if (!is_array($row)) {
        return null;
    }

    $foundLat = isset($row['lat']) ? (float) $row['lat'] : $lat;
    $foundLng = isset($row['lon']) ? (float) $row['lon'] : $lng;
    if (!sb_has_valid_lat_lng($foundLat, $foundLng)) {
        return null;
    }

    $displayName = (string) ($row['display_name'] ?? '');
    $addressParts = is_array($row['address'] ?? null) ? $row['address'] : [];
    $city = (string) (
        $addressParts['city']
        ?? $addressParts['town']
        ?? $addressParts['village']
        ?? $addressParts['municipality']
        ?? $addressParts['county']
        ?? $addressParts['state_district']
        ?? $addressParts['state']
        ?? ''
    );
    $streetAddress = trim((string) (
        ($addressParts['house_number'] ?? '') .
        (((string) ($addressParts['house_number'] ?? '')) !== '' && ((string) ($addressParts['road'] ?? '')) !== '' ? ' ' : '') .
        ($addressParts['road'] ?? '')
    ));
    if ($streetAddress === '') {
        $streetAddress = trim((string) ($addressParts['neighbourhood'] ?? $addressParts['suburb'] ?? $displayName));
    }

    return [
        'display_name' => $displayName,
        'lat' => round($foundLat, 7),
        'lng' => round($foundLng, 7),
        'city' => $city,
        'address' => $streetAddress,
    ];
}
