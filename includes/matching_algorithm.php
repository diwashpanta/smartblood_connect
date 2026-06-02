<?php

declare(strict_types=1);

function sb_radius_for_urgency(string $urgency): float
{
    return match ($urgency) {
        'critical' => 25.0,
        'high' => 15.0,
        'medium' => 10.0,
        default => 5.0,
    };
}

function sb_blood_compatibility_score(string $donorGroup, string $requestGroup): float
{
    if ($donorGroup === $requestGroup) {
        return 1.0;
    }
    return can_donate_to($donorGroup, $requestGroup) ? 0.82 : 0.0;
}

function sb_distance_score(float $distanceKm, float $radiusKm): float
{
    if ($distanceKm <= 0) {
        return 1.0;
    }
    if ($distanceKm > $radiusKm) {
        return max(0.0, 1.0 - (($distanceKm - $radiusKm) / max(1.0, $radiusKm)));
    }
    return max(0.0, 1.0 - ($distanceKm / max(1.0, $radiusKm)));
}

function sb_eligibility_score(array $donor): float
{
    $score = 0.0;
    $age = (int) ($donor['age'] ?? 0);
    $weight = (float) ($donor['weight'] ?? 0);
    $days = days_since($donor['last_donation_date'] ?? null) ?? 999;

    if ($age >= 18 && $age <= 60) {
        $score += 0.34;
    }
    if ($weight >= 50) {
        $score += 0.24;
    }
    if (($donor['medical_condition_status'] ?? 'healthy') === 'healthy') {
        $score += 0.24;
    }
    if (($donor['availability_status'] ?? 'inactive') === 'available') {
        $score += 0.12;
    }
    if ($days >= 90) {
        $score += 0.06;
    }

    return min(1.0, round($score, 4));
}

function sb_matching_score(
    float $predictionProbability,
    float $distanceScore,
    float $responseRatePercent,
    float $eligibilityScore,
    int $pastDonations,
    float $bloodCompatibilityScore = 1.0
): float {
    $responseRate = max(0.0, min(1.0, $responseRatePercent / 100.0));
    $historyScore = max(0.0, min(1.0, $pastDonations / 20.0));

    $score = (
        (0.40 * $predictionProbability) +
        (0.30 * $distanceScore) +
        (0.15 * $responseRate) +
        (0.10 * $eligibilityScore) +
        (0.05 * $historyScore)
    );

    $score *= max(0.0, min(1.0, $bloodCompatibilityScore));
    return round($score * 100, 2);
}

function sb_find_matching_donors(array $request, int $limit = 12, float $maxRadius = 50.0): array
{
    $requestGroup = (string) ($request['blood_group'] ?? '');
    $compatibles = compatible_donor_groups($requestGroup);
    if (!$compatibles) {
        return [];
    }

    $radius = sb_radius_for_urgency((string) ($request['urgency'] ?? 'low'));
    $lat = isset($request['latitude']) ? (float) $request['latitude'] : (isset($request['hospital_latitude']) ? (float) $request['hospital_latitude'] : null);
    $lng = isset($request['longitude']) ? (float) $request['longitude'] : (isset($request['hospital_longitude']) ? (float) $request['hospital_longitude'] : null);

    if ($lat === null || $lng === null) {
        $lat = MAP_DEFAULT_LAT;
        $lng = MAP_DEFAULT_LNG;
    }

    $placeholders = implode(',', array_fill(0, count($compatibles), '?'));
    $candidates = db_fetch_all(
        "SELECT
            d.id AS donor_id,
            d.user_id,
            u.full_name,
            u.phone,
            u.email,
            COALESCE(d.city, u.city) AS city,
            COALESCE(d.address, u.address) AS address,
            COALESCE(d.blood_group, u.blood_group) AS blood_group,
            d.age,
            d.weight,
            COALESCE(d.latitude, u.latitude) AS latitude,
            COALESCE(d.longitude, u.longitude) AS longitude,
            d.last_donation_date,
            COALESCE(d.medical_condition_status, d.medical_condition) AS medical_condition_status,
            COALESCE(d.availability_status, d.available_status) AS availability_status,
            d.is_verified,
            d.is_eligible,
            d.past_donations,
            d.total_donations,
            d.response_rate
         FROM donors d
         JOIN users u ON u.id = d.user_id
         WHERE u.status = 'active'
           AND d.is_verified = 1
           AND COALESCE(d.blood_group, u.blood_group) IN ($placeholders)",
        $compatibles
    );

    $scored = [];
    $mlPayloadDonors = [];
    foreach ($candidates as $row) {
        $candidate = [
            'donor_id' => (int) $row['donor_id'],
            'user_id' => (int) $row['user_id'],
            'full_name' => (string) $row['full_name'],
            'phone' => (string) ($row['phone'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'city' => (string) ($row['city'] ?? ''),
            'address' => (string) ($row['address'] ?? ''),
            'blood_group' => (string) $row['blood_group'],
            'age' => (int) $row['age'],
            'weight' => (float) $row['weight'],
            'latitude' => $row['latitude'] !== null ? (float) $row['latitude'] : null,
            'longitude' => $row['longitude'] !== null ? (float) $row['longitude'] : null,
            'last_donation_date' => $row['last_donation_date'],
            'medical_condition_status' => (string) $row['medical_condition_status'],
            'availability_status' => (string) $row['availability_status'],
            'donor_verified' => (int) $row['is_verified'],
            'is_eligible' => (int) ($row['is_eligible'] ?? 1),
            'past_donations' => (int) ($row['past_donations'] ?? 0),
            'total_donations' => (int) ($row['total_donations'] ?? $row['past_donations'] ?? 0),
            'response_rate' => (float) ($row['response_rate'] ?? 0.0),
        ];

        if (!sb_has_valid_lat_lng($candidate['latitude'], $candidate['longitude'])) {
            continue;
        }
        if ($candidate['is_eligible'] !== 1 || !donor_is_eligible($candidate)) {
            continue;
        }

        $distance = haversine_km($candidate['latitude'], $candidate['longitude'], $lat, $lng);
        $candidate['distance_km'] = $distance;
        $scored[] = $candidate;
        $mlPayloadDonors[] = [
            'donor_id' => $candidate['donor_id'],
            'blood_group' => $candidate['blood_group'],
            'age' => $candidate['age'],
            'weight' => $candidate['weight'],
            'medical_condition_status' => $candidate['medical_condition_status'],
            'availability_status' => $candidate['availability_status'],
            'is_verified' => $candidate['donor_verified'],
            'past_donations' => $candidate['past_donations'],
            'total_donations' => $candidate['total_donations'],
            'response_rate' => $candidate['response_rate'],
            'days_since_last_donation' => days_since($candidate['last_donation_date']) ?? 120,
            'latitude' => $candidate['latitude'],
            'longitude' => $candidate['longitude'],
        ];
    }

    if (!$scored) {
        return [];
    }

    $mlRanked = sb_ml_rank_donors([
        'blood_group' => $requestGroup,
        'urgency' => (string) ($request['urgency'] ?? 'low'),
        'hospital_latitude' => $lat,
        'hospital_longitude' => $lng,
    ], $mlPayloadDonors);
    $mlScoreByDonor = [];
    if ($mlRanked) {
        foreach ($mlRanked as $row) {
            if (isset($row['donor_id'], $row['matching_score'])) {
                $mlScoreByDonor[(int) $row['donor_id']] = (float) $row['matching_score'];
            }
        }
    }

    $filtered = [];
    $activeRadius = $radius;
    while ($activeRadius <= $maxRadius && count($filtered) < max($limit, 5)) {
        $filtered = [];
        foreach ($scored as $candidate) {
            if ((float) $candidate['distance_km'] <= $activeRadius) {
                $filtered[] = $candidate;
            }
        }
        if (count($filtered) >= max(5, min(8, $limit)) || $activeRadius >= $maxRadius) {
            break;
        }
        $activeRadius += 5.0;
    }

    if (!$filtered) {
        $filtered = $scored;
        $activeRadius = $maxRadius;
    }

    foreach ($filtered as &$candidate) {
        $predictionBundle = predict_donor_likelihood($candidate, [
            'blood_group' => $requestGroup,
            'urgency' => (string) ($request['urgency'] ?? 'low'),
            'hospital_latitude' => $lat,
            'hospital_longitude' => $lng,
        ]);
        $prediction = (float) ($predictionBundle['result']['probability'] ?? 0.5);
        $distanceScore = sb_distance_score((float) $candidate['distance_km'], $activeRadius);
        $eligibilityScore = sb_eligibility_score($candidate);
        $bloodScore = sb_blood_compatibility_score((string) $candidate['blood_group'], $requestGroup);
        $matchingScore = sb_matching_score(
            $prediction,
            $distanceScore,
            (float) $candidate['response_rate'],
            $eligibilityScore,
            (int) $candidate['past_donations'],
            $bloodScore
        );

        if (isset($mlScoreByDonor[$candidate['donor_id']])) {
            $matchingScore = round(($matchingScore * 0.8) + ((float) $mlScoreByDonor[$candidate['donor_id']] * 20.0), 2);
        }

        $candidate['prediction'] = $predictionBundle;
        $candidate['distance_score'] = round($distanceScore * 100, 2);
        $candidate['matching_score'] = $matchingScore;
    }
    unset($candidate);

    usort(
        $filtered,
        static function (array $a, array $b): int {
            if ((float) $a['matching_score'] === (float) $b['matching_score']) {
                return (float) $a['distance_km'] <=> (float) $b['distance_km'];
            }
            return (float) $b['matching_score'] <=> (float) $a['matching_score'];
        }
    );

    return array_slice($filtered, 0, $limit);
}
