<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/location_functions.php';
require_once __DIR__ . '/ml_bridge.php';
require_once __DIR__ . '/matching_algorithm.php';

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    return $path === '' ? APP_BASE_URL : APP_BASE_URL . '/' . $path;
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function js_config(): array
{
    return [
        'baseUrl' => APP_BASE_URL,
        'map' => sb_map_config(),
        'currentDate' => date('Y-m-d'),
    ];
}

function redirect(string $path): never
{
    if (!preg_match('/^https?:\/\//i', $path)) {
        $path = url($path);
    }
    header('Location: ' . $path);
    exit;
}

function db_query(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function db_fetch_one(string $sql, array $params = []): ?array
{
    $row = db_query($sql, $params)->fetch();
    return $row !== false ? $row : null;
}

function db_fetch_all(string $sql, array $params = []): array
{
    return db_query($sql, $params)->fetchAll();
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message,
    ];
}

function get_flash_messages(): array
{
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf_token(?string $token): bool
{
    if (!$token || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function require_post_with_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        set_flash('danger', 'Invalid request token. Please try again.');
        redirect($_SERVER['HTTP_REFERER'] ?? 'index.php');
    }
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    unset($_SESSION['current_user']);
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function current_user(bool $refresh = false): ?array
{
    if (!is_logged_in()) {
        return null;
    }

    if (!$refresh && isset($_SESSION['current_user'])) {
        return $_SESSION['current_user'];
    }

    $sql = "SELECT
                u.*,
                p.id AS patient_id,
                p.age AS patient_age,
                p.gender AS patient_gender,
                p.date_of_birth AS patient_dob,
                p.emergency_contact AS patient_emergency_contact,
                p.city AS patient_city,
                p.address AS patient_address,
                p.latitude AS patient_latitude,
                p.longitude AS patient_longitude,
                p.blood_group AS patient_blood_group,
                d.id AS donor_id,
                d.age AS donor_age,
                d.weight AS donor_weight,
                d.latitude AS donor_latitude,
                d.longitude AS donor_longitude,
                d.last_donation_date,
                d.medical_condition_status,
                d.medical_condition,
                COALESCE(d.availability_status, d.available_status) AS availability_status,
                d.available_status,
                d.is_verified AS donor_verified,
                d.is_eligible,
                d.past_donations,
                d.total_donations,
                d.response_rate,
                d.location_updated_at,
                COALESCE(d.city, u.city) AS donor_city,
                COALESCE(d.address, u.address) AS donor_address,
                COALESCE(d.blood_group, u.blood_group) AS donor_blood_group,
                a.id AS admin_id,
                a.designation AS admin_designation
            FROM users u
            LEFT JOIN patients p ON p.user_id = u.id
            LEFT JOIN donors d ON d.user_id = u.id
            LEFT JOIN admins a ON a.user_id = u.id
            WHERE u.id = ?";
    $user = db_fetch_one($sql, [(int) $_SESSION['user_id']]);

    if (!$user) {
        unset($_SESSION['user_id'], $_SESSION['current_user']);
        return null;
    }

    $_SESSION['current_user'] = $user;
    return $user;
}

function refresh_current_user(): ?array
{
    return current_user(true);
}

function has_role(string|array $roles): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }
    $roles = (array) $roles;
    return in_array($user['role'], $roles, true);
}

function role_home_path(string $role): string
{
    return match ($role) {
        'admin' => 'admin/dashboard.php',
        'donor' => 'donor/dashboard.php',
        default => 'patient/dashboard.php',
    };
}

function badge_class_for_status(string $status): string
{
    return match ($status) {
        'fulfilled', 'completed', 'available', 'accepted', 'approved', 'verified' => 'bg-success-subtle text-success',
        'matched', 'scheduled', 'partially_fulfilled', 'reserved' => 'bg-info-subtle text-info-emphasis',
        'pending' => 'bg-warning-subtle text-warning-emphasis',
        'rejected', 'cancelled', 'declined', 'expired', 'issued', 'inactive', 'no_show' => 'bg-danger-subtle text-danger',
        default => 'bg-secondary-subtle text-secondary',
    };
}

function active_link(string $needle): string
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    return str_contains($script, $needle) ? 'active' : '';
}

function blood_groups(): array
{
    return ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
}

function compatible_donor_groups(string $recipientGroup): array
{
    $map = [
        'A+' => ['A+', 'A-', 'O+', 'O-'],
        'A-' => ['A-', 'O-'],
        'B+' => ['B+', 'B-', 'O+', 'O-'],
        'B-' => ['B-', 'O-'],
        'AB+' => ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'],
        'AB-' => ['A-', 'B-', 'AB-', 'O-'],
        'O+' => ['O+', 'O-'],
        'O-' => ['O-'],
    ];

    return $map[$recipientGroup] ?? [];
}

function can_donate_to(string $donorGroup, string $recipientGroup): bool
{
    return in_array($donorGroup, compatible_donor_groups($recipientGroup), true);
}

function days_since(?string $date): ?int
{
    if (!$date) {
        return null;
    }
    $from = strtotime($date);
    if ($from === false) {
        return null;
    }
    $days = floor((time() - $from) / 86400);
    return max(0, (int) $days);
}

function haversine_km(?float $lat1, ?float $lon1, ?float $lat2, ?float $lon2): float
{
    if ($lat1 === null || $lon1 === null || $lat2 === null || $lon2 === null) {
        return 9999.0;
    }

    $earth = 6371.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2 +
        cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
        sin($dLon / 2) ** 2;
    return round($earth * (2 * asin(min(1, sqrt($a)))), 2);
}

function donor_is_eligible(array $donor): bool
{
    $age = (int) ($donor['donor_age'] ?? $donor['age'] ?? 0);
    $weight = (float) ($donor['donor_weight'] ?? $donor['weight'] ?? 0);
    $availability = $donor['availability_status'] ?? $donor['available_status'] ?? 'inactive';
    $medical = $donor['medical_condition_status'] ?? $donor['medical_condition'] ?? 'temporary_deferral';
    $days = days_since($donor['last_donation_date'] ?? null);
    $flagEligible = isset($donor['is_eligible']) ? (int) $donor['is_eligible'] : 1;

    if ($age < 18 || $age > 60) {
        return false;
    }
    if ($weight < 50) {
        return false;
    }
    if ($availability !== 'available') {
        return false;
    }
    if ($medical !== 'healthy') {
        return false;
    }
    if ($days !== null && $days < 90) {
        return false;
    }
    if ($flagEligible !== 1) {
        return false;
    }
    if (array_key_exists('donor_verified', $donor) && (int) $donor['donor_verified'] !== 1) {
        return false;
    }
    return true;
}

function heuristic_probability(array $donor, array $request): float
{
    $score = 0.45;
    $distance = haversine_km(
        isset($donor['latitude']) ? (float) $donor['latitude'] : (isset($donor['donor_latitude']) ? (float) $donor['donor_latitude'] : null),
        isset($donor['longitude']) ? (float) $donor['longitude'] : (isset($donor['donor_longitude']) ? (float) $donor['donor_longitude'] : null),
        isset($request['hospital_latitude']) ? (float) $request['hospital_latitude'] : null,
        isset($request['hospital_longitude']) ? (float) $request['hospital_longitude'] : null
    );

    $days = days_since($donor['last_donation_date'] ?? null);
    $past = (int) ($donor['past_donations'] ?? $donor['total_donations'] ?? 0);
    $responseRate = (float) ($donor['response_rate'] ?? 0.0);

    if (($donor['availability_status'] ?? $donor['available_status'] ?? '') === 'available') {
        $score += 0.12;
    }
    if (($donor['medical_condition_status'] ?? $donor['medical_condition'] ?? '') === 'healthy') {
        $score += 0.1;
    }
    if ($days === null || $days >= 90) {
        $score += 0.07;
    } else {
        $score -= 0.15;
    }
    if ($distance < 8) {
        $score += 0.1;
    } elseif ($distance < 20) {
        $score += 0.05;
    } elseif ($distance > 40) {
        $score -= 0.1;
    }
    $score += min(0.08, $past * 0.01);
    $score += min(0.1, $responseRate / 100.0);

    return max(0.05, min(0.95, round($score, 4)));
}

function shell_arg(string $arg): string
{
    return '"' . str_replace('"', '\"', $arg) . '"';
}

function urgency_encode(string $urgency): int
{
    return match ($urgency) {
        'critical' => 3,
        'high' => 2,
        'medium' => 1,
        default => 0,
    };
}

function blood_group_encode(string $bloodGroup): int
{
    $map = [
        'O-' => 0,
        'O+' => 1,
        'A-' => 2,
        'A+' => 3,
        'B-' => 4,
        'B+' => 5,
        'AB-' => 6,
        'AB+' => 7,
    ];
    return $map[$bloodGroup] ?? 0;
}

function predict_donor_likelihood(array $donor, array $request): array
{
    $bloodGroup = (string) ($donor['blood_group'] ?? $donor['donor_blood_group'] ?? '');
    $urgency = (string) ($request['urgency'] ?? 'low');
    $features = [
        'age' => (int) ($donor['age'] ?? $donor['donor_age'] ?? 0),
        'weight' => (float) ($donor['weight'] ?? $donor['donor_weight'] ?? 0),
        'blood_group' => $bloodGroup,
        'days_since_last_donation' => days_since($donor['last_donation_date'] ?? null) ?? 120,
        'past_donations' => (int) ($donor['past_donations'] ?? $donor['total_donations'] ?? 0),
        'total_donations' => (int) ($donor['total_donations'] ?? $donor['past_donations'] ?? 0),
        'medical_condition_flag' => (($donor['medical_condition_status'] ?? $donor['medical_condition'] ?? 'healthy') === 'healthy') ? 0 : 1,
        'availability_flag' => (($donor['availability_status'] ?? $donor['available_status'] ?? 'available') === 'available') ? 1 : 0,
        'distance_km' => haversine_km(
            isset($donor['latitude']) ? (float) $donor['latitude'] : (isset($donor['donor_latitude']) ? (float) $donor['donor_latitude'] : null),
            isset($donor['longitude']) ? (float) $donor['longitude'] : (isset($donor['donor_longitude']) ? (float) $donor['donor_longitude'] : null),
            isset($request['latitude']) ? (float) $request['latitude'] : (isset($request['hospital_latitude']) ? (float) $request['hospital_latitude'] : null),
            isset($request['longitude']) ? (float) $request['longitude'] : (isset($request['hospital_longitude']) ? (float) $request['hospital_longitude'] : null)
        ),
        'response_rate' => (float) ($donor['response_rate'] ?? 0.0),
        'previous_response_rate' => (float) ($donor['response_rate'] ?? 0.0),
        'urgency_encoded' => urgency_encode($urgency),
        'blood_group_encoded' => blood_group_encode($bloodGroup),
    ];

    $fallbackProbability = heuristic_probability($donor, $request);
    $result = [
        'probability' => $fallbackProbability,
        'predicted_class' => $fallbackProbability >= 0.5 ? 'likely' : 'unlikely',
        'confidence_label' => $fallbackProbability >= 0.75 ? 'High' : ($fallbackProbability >= 0.5 ? 'Medium' : 'Low'),
        'model' => 'heuristic',
    ];

    $decoded = sb_ml_predict_donor($features);
    if (is_array($decoded) && isset($decoded['probability'])) {
        $probability = (float) $decoded['probability'];
        $probability = max(0.0, min(1.0, $probability));
        $result = [
            'probability' => round($probability, 4),
            'predicted_class' => $decoded['predicted_class'] ?? ($probability >= 0.5 ? 'likely' : 'unlikely'),
            'confidence_label' => $decoded['confidence_label'] ?? ($probability >= 0.75 ? 'High' : ($probability >= 0.5 ? 'Medium' : 'Low')),
            'model' => $decoded['model'] ?? 'logistic_regression',
        ];
    }

    return ['result' => $result, 'features' => $features];
}

function log_prediction(int $donorId, ?int $requestId, array $result, array $features): void
{
    db_query(
        "INSERT INTO ml_predictions (donor_id, request_id, model_name, probability_score, predicted_class, confidence_label, features_json)
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        [
            $donorId,
            $requestId,
            $result['model'] ?? 'heuristic',
            (float) ($result['probability'] ?? 0.0),
            (string) ($result['predicted_class'] ?? 'unlikely'),
            (string) ($result['confidence_label'] ?? 'Low'),
            json_encode($features),
        ]
    );
}

function audit_log(?int $userId, string $action, string $entityType, ?int $entityId = null, array $meta = []): void
{
    db_query(
        "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, meta_json) VALUES (?, ?, ?, ?, ?)",
        [$userId, $action, $entityType, $entityId, json_encode($meta)]
    );
}

function create_patient_notification(int $patientId, int $requestId, string $message): void
{
    db_query(
        "INSERT INTO patient_notifications (patient_id, request_id, message) VALUES (?, ?, ?)",
        [$patientId, $requestId, $message]
    );
}

function fetch_blood_request(int $requestId): ?array
{
    return db_fetch_one(
        "SELECT
            br.*,
            COALESCE(br.request_status, br.status) AS request_status,
            COALESCE(br.city, br.hospital_city) AS city,
            COALESCE(br.latitude, br.hospital_latitude) AS latitude,
            COALESCE(br.longitude, br.hospital_longitude) AS longitude,
            p.user_id AS patient_user_id,
            u.full_name AS patient_name,
            u.phone AS patient_phone,
            u.city AS patient_city,
            u.address AS patient_address
         FROM blood_requests br
         JOIN patients p ON p.id = br.patient_id
         JOIN users u ON u.id = p.user_id
         WHERE br.id = ?",
        [$requestId]
    );
}

function update_request_status_from_units(int $requestId): ?array
{
    $request = fetch_blood_request($requestId);
    if (!$request) {
        return null;
    }

    $needed = (int) $request['units_needed'];
    $fulfilled = (int) $request['units_fulfilled'];
    $status = $request['request_status'] ?? $request['status'];

    if ($fulfilled <= 0 && !in_array($status, ['rejected', 'cancelled'], true)) {
        $status = 'pending';
    } elseif ($fulfilled >= $needed) {
        $status = 'fulfilled';
    } elseif ($fulfilled > 0) {
        $status = 'partially_fulfilled';
    }

    db_query("UPDATE blood_requests SET status = ?, request_status = ?, updated_at = NOW() WHERE id = ?", [$status, $status, $requestId]);
    return fetch_blood_request($requestId);
}

function apply_inventory_to_request(int $requestId, ?int $actorUserId = null): int
{
    $request = fetch_blood_request($requestId);
    $requestStatus = (string) ($request['request_status'] ?? $request['status'] ?? 'pending');
    if (!$request || in_array($requestStatus, ['rejected', 'cancelled'], true)) {
        return 0;
    }

    $remaining = (int) $request['units_needed'] - (int) $request['units_fulfilled'];
    if ($remaining <= 0) {
        return 0;
    }

    $compatible = compatible_donor_groups((string) $request['blood_group']);
    if (!$compatible) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($compatible), '?'));
    $params = $compatible;

    $inventory = db_fetch_all(
        "SELECT * FROM blood_inventory
         WHERE status = 'available'
           AND quantity_units > 0
           AND expiry_date >= CURDATE()
           AND blood_group IN ($placeholders)
         ORDER BY expiry_date ASC, id ASC",
        $params
    );

    if (!$inventory) {
        return 0;
    }

    $pdo = db();
    $issuedTotal = 0;

    try {
        $pdo->beginTransaction();

        foreach ($inventory as $item) {
            if ($remaining <= 0) {
                break;
            }

            $availableUnits = (int) $item['quantity_units'];
            if ($availableUnits <= 0) {
                continue;
            }

            $issueUnits = min($availableUnits, $remaining);
            $newQty = $availableUnits - $issueUnits;
            $newStatus = $newQty <= 0 ? 'issued' : 'available';

            db_query(
                "UPDATE blood_inventory SET quantity_units = ?, status = ? WHERE id = ?",
                [$newQty, $newStatus, (int) $item['id']]
            );

            db_query(
                "INSERT INTO blood_issuance (request_id, patient_id, inventory_id, units_issued, issued_by, notes)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [
                    $requestId,
                    (int) $request['patient_id'],
                    (int) $item['id'],
                    $issueUnits,
                    $actorUserId,
                    'Auto-issued via request matching pipeline',
                ]
            );

            db_query(
                "INSERT INTO inventory_transactions (inventory_id, action, quantity_units, reference_type, reference_id, notes, performed_by)
                 VALUES (?, 'issue', ?, 'blood_request', ?, ?, ?)",
                [
                    (int) $item['id'],
                    $issueUnits,
                    $requestId,
                    'Issued from inventory to patient request',
                    $actorUserId,
                ]
            );

            $issuedTotal += $issueUnits;
            $remaining -= $issueUnits;
        }

        if ($issuedTotal > 0) {
            db_query(
                "UPDATE blood_requests
                 SET units_fulfilled = LEAST(units_needed, units_fulfilled + ?),
                     updated_at = NOW()
                 WHERE id = ?",
                [$issuedTotal, $requestId]
            );
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    if ($issuedTotal > 0) {
        $updated = update_request_status_from_units($requestId);
        if ($updated) {
            create_patient_notification(
                (int) $updated['patient_id'],
                (int) $updated['id'],
                "Inventory issued {$issuedTotal} unit(s). Current status: {$updated['status']}."
            );
        }
        audit_log($actorUserId, 'inventory_auto_issue', 'blood_request', $requestId, ['units_issued' => $issuedTotal]);
    }

    return $issuedTotal;
}

function fetch_eligible_donors_for_request(array $request, int $limit = 15): array
{
    return sb_find_matching_donors($request, $limit, 50.0);
}

function create_donor_notifications_for_request(int $requestId, ?int $actorUserId = null, int $limit = 8): int
{
    $request = fetch_blood_request($requestId);
    $requestStatus = (string) ($request['request_status'] ?? $request['status'] ?? 'pending');
    if (!$request || in_array($requestStatus, ['fulfilled', 'rejected', 'cancelled'], true)) {
        return 0;
    }

    $remaining = (int) $request['units_needed'] - (int) $request['units_fulfilled'];
    if ($remaining <= 0) {
        return 0;
    }

    $candidates = fetch_eligible_donors_for_request($request, $limit);
    if (!$candidates) {
        create_patient_notification((int) $request['patient_id'], $requestId, 'No eligible nearby donors were found yet. Admin will continue monitoring.');
        return 0;
    }

    $inserted = 0;
    foreach ($candidates as $donor) {
        $existing = db_fetch_one(
            "SELECT id FROM donor_notifications WHERE request_id = ? AND donor_id = ?",
            [$requestId, (int) $donor['donor_id']]
        );
        if ($existing) {
            continue;
        }

        db_query(
            "INSERT INTO donor_notifications
             (request_id, blood_request_id, donor_id, probability_score, predicted_probability, matching_score, distance_km, status, message)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)",
            [
                $requestId,
                $requestId,
                (int) $donor['donor_id'],
                (float) $donor['prediction']['result']['probability'],
                (float) $donor['prediction']['result']['probability'],
                (float) ($donor['matching_score'] ?? 0.0),
                (float) $donor['distance_km'],
                "Urgent need for {$request['blood_group']} blood at {$request['hospital_name']}.",
            ]
        );
        log_prediction((int) $donor['donor_id'], $requestId, $donor['prediction']['result'], $donor['prediction']['features']);
        $inserted++;
    }

    if ($inserted > 0) {
        if ($requestStatus === 'pending') {
            db_query("UPDATE blood_requests SET status = 'matched', request_status = 'matched', updated_at = NOW() WHERE id = ?", [$requestId]);
        }
        create_patient_notification((int) $request['patient_id'], $requestId, "Matched {$inserted} eligible donor(s). Waiting for donor responses.");
        audit_log($actorUserId, 'donor_notifications_created', 'blood_request', $requestId, ['notifications' => $inserted]);
    }

    return $inserted;
}

function run_request_matching_pipeline(int $requestId, ?int $actorUserId = null): array
{
    $issued = apply_inventory_to_request($requestId, $actorUserId);
    $request = fetch_blood_request($requestId);
    if (!$request) {
        return ['issued_units' => $issued, 'notifications' => 0, 'remaining_units' => 0];
    }

    $remaining = max(0, (int) $request['units_needed'] - (int) $request['units_fulfilled']);
    $notifications = 0;

    $requestStatus = (string) ($request['request_status'] ?? $request['status'] ?? 'pending');
    if ($remaining > 0 && !in_array($requestStatus, ['rejected', 'cancelled'], true)) {
        $notifications = create_donor_notifications_for_request($requestId, $actorUserId);
        $request = fetch_blood_request($requestId) ?: $request;
        $remaining = max(0, (int) $request['units_needed'] - (int) $request['units_fulfilled']);
    }

    return [
        'issued_units' => $issued,
        'notifications' => $notifications,
        'remaining_units' => $remaining,
    ];
}

function get_donor_pending_notifications_count(int $donorId): int
{
    return (int) (db_fetch_one(
        "SELECT COUNT(*) AS c FROM donor_notifications WHERE donor_id = ? AND status = 'pending'",
        [$donorId]
    )['c'] ?? 0);
}

function get_admin_active_emergency_count(): int
{
    return (int) (db_fetch_one(
        "SELECT COUNT(*) AS c
         FROM blood_requests
         WHERE COALESCE(request_status, status) IN ('pending','matched','partially_fulfilled')
           AND urgency IN ('high','critical')"
    )['c'] ?? 0);
}

function get_patient_unread_updates_count(int $patientId): int
{
    return (int) (db_fetch_one(
        "SELECT COUNT(*) AS c FROM patient_notifications WHERE patient_id = ? AND is_read = 0",
        [$patientId]
    )['c'] ?? 0);
}
