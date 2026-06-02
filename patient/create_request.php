<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('patient');

$user = current_user();
$patientId = (int) ($user['patient_id'] ?? 0);

$form = [
    'blood_group' => $user['blood_group'] ?? $user['patient_blood_group'] ?? '',
    'units_needed' => '1',
    'hospital_name' => '',
    'hospital_address' => '',
    'city' => $user['patient_city'] ?? $user['city'] ?? '',
    'latitude' => '',
    'longitude' => '',
    'urgency' => 'high',
    'notes' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_with_csrf();
    foreach ($form as $key => $value) {
        $form[$key] = trim((string) ($_POST[$key] ?? ''));
    }

    $errors = [];
    if (!in_array($form['blood_group'], blood_groups(), true)) {
        $errors[] = 'Select a valid blood group.';
    }
    $unitsNeeded = (int) $form['units_needed'];
    if ($unitsNeeded < 1 || $unitsNeeded > 20) {
        $errors[] = 'Units needed should be between 1 and 20.';
    }
    if ($form['hospital_name'] === '' || $form['city'] === '') {
        $errors[] = 'Hospital name and city are required.';
    }
    if (!in_array($form['urgency'], ['low', 'medium', 'high', 'critical'], true)) {
        $errors[] = 'Invalid urgency level selected.';
    }

    $coords = sb_validate_lat_lng($form['latitude'], $form['longitude']);
    if (!sb_has_valid_lat_lng($coords['lat'], $coords['lng'])) {
        $errors[] = 'Please select hospital location on map.';
    } elseif (sb_is_zero_coordinate_pair($coords['lat'], $coords['lng'])) {
        $errors[] = 'Invalid hospital location selected. Please choose actual hospital location on map.';
    }

    if ($errors) {
        foreach ($errors as $err) {
            set_flash('danger', $err);
        }
        redirect('patient/create_request.php');
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();
        db_query(
            "INSERT INTO blood_requests
             (patient_id, blood_group, units_needed, units_fulfilled, hospital_name, hospital_address, hospital_city, city, hospital_latitude, hospital_longitude, latitude, longitude, urgency, notes, status, request_status)
             VALUES (?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending')",
            [
                $patientId,
                $form['blood_group'],
                $unitsNeeded,
                $form['hospital_name'],
                $form['hospital_address'] ?: null,
                $form['city'],
                $form['city'],
                $coords['lat'],
                $coords['lng'],
                $coords['lat'],
                $coords['lng'],
                $form['urgency'],
                $form['notes'] ?: null,
            ]
        );
        $requestId = (int) $pdo->lastInsertId();
        audit_log((int) $user['id'], 'request_created', 'blood_request', $requestId, [
            'blood_group' => $form['blood_group'],
            'units_needed' => $unitsNeeded,
            'latitude' => $coords['lat'],
            'longitude' => $coords['lng'],
        ]);
        $pdo->commit();

        $pipeline = run_request_matching_pipeline($requestId, (int) $user['id']);
        set_flash('success', "Request created. Issued units: {$pipeline['issued_units']}, donor notifications: {$pipeline['notifications']}.");
        redirect('patient/request_details.php?id=' . $requestId);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        set_flash('danger', 'Unable to create request. Please try again.');
    }
}

$pageTitle = 'Create Blood Request';
$showSidebar = true;
$withMaps = true;
$mainContainerClass = 'container-fluid py-4';
include __DIR__ . '/../includes/header.php';
?>

<section class="card-soft p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Create Blood Request</h1>
        <a href="<?= e(url('patient/my_requests.php')) ?>" class="btn btn-outline-secondary btn-sm">Back to Requests</a>
    </div>

    <form method="post">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Blood Group</label>
                <select name="blood_group" class="form-select" required>
                    <?php foreach (blood_groups() as $group): ?>
                        <option value="<?= e($group) ?>" <?= $form['blood_group'] === $group ? 'selected' : '' ?>><?= e($group) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Units Needed</label>
                <input type="number" name="units_needed" class="form-control" min="1" max="20" value="<?= e($form['units_needed']) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Urgency</label>
                <select name="urgency" class="form-select">
                    <?php foreach (['low','medium','high','critical'] as $urgency): ?>
                        <option value="<?= e($urgency) ?>" <?= $form['urgency'] === $urgency ? 'selected' : '' ?>><?= e(ucfirst($urgency)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6"><label class="form-label">Hospital Name</label><input type="text" name="hospital_name" class="form-control" value="<?= e($form['hospital_name']) ?>" required></div>
            <div class="col-md-6"><label class="form-label">Hospital Address</label><input type="text" name="hospital_address" class="form-control" value="<?= e($form['hospital_address']) ?>"></div>
            <div class="col-md-6"><label class="form-label">City</label><input type="text" name="city" class="form-control" value="<?= e($form['city']) ?>" required></div>
            <div class="col-12">
                <div class="map-card">
                    <div class="map-toolbar">
                        <input type="text" id="requestLocationSearch" class="form-control form-control-sm" placeholder="Search hospital or address">
                        <button type="button" id="requestLocationSearchBtn" class="btn btn-sm btn-outline-secondary">Search</button>
                        <button type="button" id="requestUseCurrentLocation" class="btn btn-sm btn-outline-primary">Use Current Location</button>
                    </div>
                    <div id="requestLocationMap" class="map-canvas map-lg"></div>
                    <div id="requestLocationPreview" class="map-hint">Set exact hospital/request location.</div>
                </div>
            </div>
            <div class="col-md-6"><label class="form-label">Latitude</label><input type="number" step="0.0000001" name="latitude" class="form-control" value="<?= e($form['latitude']) ?>" required></div>
            <div class="col-md-6"><label class="form-label">Longitude</label><input type="number" step="0.0000001" name="longitude" class="form-control" value="<?= e($form['longitude']) ?>" required></div>
            <div class="col-12">
                <label class="form-label">Notes</label>
                <textarea name="notes" rows="4" class="form-control"><?= e($form['notes']) ?></textarea>
            </div>
        </div>
        <button class="btn btn-danger mt-4" type="submit">Submit Request</button>
    </form>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    SmartBloodLocationPicker.initLocationPicker({
        mapId: 'requestLocationMap',
        latSelector: 'input[name=\"latitude\"]',
        lngSelector: 'input[name=\"longitude\"]',
        previewSelector: '#requestLocationPreview',
        searchInputSelector: '#requestLocationSearch',
        searchButtonSelector: '#requestLocationSearchBtn',
        gpsButtonSelector: '#requestUseCurrentLocation',
        addressSelector: 'input[name=\"hospital_address\"]',
        citySelector: 'input[name=\"city\"]'
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
