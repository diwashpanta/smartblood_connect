<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
ensure_guest();

$errors = [];
$form = [
    'full_name' => '',
    'email' => '',
    'phone' => '',
    'city' => '',
    'address' => '',
    'blood_group' => '',
    'role' => 'patient',
    'age' => '',
    'gender' => '',
    'date_of_birth' => '',
    'weight' => '',
    'latitude' => '',
    'longitude' => '',
    'last_donation_date' => '',
    'emergency_contact' => '',
    'medical_condition_status' => 'healthy',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_with_csrf();
    foreach ($form as $key => $value) {
        $form[$key] = trim((string) ($_POST[$key] ?? ''));
    }

    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role = in_array($form['role'], ['patient', 'donor'], true) ? $form['role'] : 'patient';

    if ($form['full_name'] === '') {
        $errors[] = 'Full name is required.';
    }
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }
    if (!in_array($form['blood_group'], blood_groups(), true)) {
        $errors[] = 'Please select a valid blood group.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'Password confirmation does not match.';
    }

    $age = (int) $form['age'];
    if ($age <= 0) {
        $errors[] = 'Age is required.';
    }

    $coords = sb_validate_lat_lng($form['latitude'], $form['longitude']);
    if (!sb_has_valid_lat_lng($coords['lat'], $coords['lng'])) {
        $errors[] = 'Please select your location from map or current location.';
    } elseif (sb_is_zero_coordinate_pair($coords['lat'], $coords['lng'])) {
        $errors[] = 'Invalid location selected. Please choose your real location on map.';
    }

    if ($role === 'donor') {
        if ((float) $form['weight'] < 45) {
            $errors[] = 'Donor weight should be at least 45 kg.';
        }
        if (!in_array($form['medical_condition_status'], ['healthy', 'temporary_deferral', 'chronic_issue'], true)) {
            $errors[] = 'Invalid donor medical status.';
        }
    }

    if (!$errors) {
        $existing = db_fetch_one("SELECT id FROM users WHERE email = ?", [strtolower($form['email'])]);
        if ($existing) {
            $errors[] = 'Email already exists. Please login.';
        }
    }

    if (!$errors) {
        $pdo = db();
        try {
            $pdo->beginTransaction();

            db_query(
                "INSERT INTO users (full_name, name, email, password_hash, role, phone, city, address, blood_group, latitude, longitude, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')",
                [
                    $form['full_name'],
                    $form['full_name'],
                    strtolower($form['email']),
                    password_hash($password, PASSWORD_DEFAULT),
                    $role,
                    $form['phone'] ?: null,
                    $form['city'] ?: null,
                    $form['address'] ?: null,
                    $form['blood_group'],
                    $coords['lat'],
                    $coords['lng'],
                ]
            );
            $userId = (int) $pdo->lastInsertId();

            if ($role === 'patient') {
                db_query(
                    "INSERT INTO patients (user_id, blood_group, age, gender, date_of_birth, emergency_contact, city, address, latitude, longitude, hospital_preference, notes)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $userId,
                        $form['blood_group'],
                        $age,
                        $form['gender'] ?: 'other',
                        $form['date_of_birth'] ?: null,
                        $form['emergency_contact'] ?: null,
                        $form['city'] ?: null,
                        $form['address'] ?: null,
                        $coords['lat'],
                        $coords['lng'],
                        null,
                        null,
                    ]
                );
            } else {
                $weight = (float) $form['weight'];
                $days = days_since($form['last_donation_date'] ?: null);
                $isEligible = (
                    $age >= 18 &&
                    $age <= 60 &&
                    $weight >= 50 &&
                    ($form['medical_condition_status'] === 'healthy') &&
                    ($days === null || $days >= 90)
                ) ? 1 : 0;

                db_query(
                    "INSERT INTO donors
                     (user_id, blood_group, age, date_of_birth, weight, medical_condition_status, medical_condition, availability_status, available_status, city, address, latitude, longitude, location_updated_at, last_donation_date, is_verified, is_eligible, past_donations, total_donations, response_rate)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'available', 'available', ?, ?, ?, ?, NOW(), ?, 1, ?, 0, 0, 0)",
                    [
                        $userId,
                        $form['blood_group'],
                        $age,
                        $form['date_of_birth'] ?: null,
                        $weight,
                        $form['medical_condition_status'],
                        $form['medical_condition_status'],
                        $form['city'] ?: null,
                        $form['address'] ?: null,
                        $coords['lat'],
                        $coords['lng'],
                        $form['last_donation_date'] ?: null,
                        $isEligible,
                    ]
                );

                $donorId = (int) $pdo->lastInsertId();
                db_query(
                    "INSERT INTO donor_locations (donor_id, label, address, city, latitude, longitude, is_primary, created_at, recorded_at)
                     VALUES (?, 'Primary', ?, ?, ?, ?, 1, NOW(), NOW())",
                    [
                        $donorId,
                        $form['address'] ?: null,
                        $form['city'] ?: null,
                        $coords['lat'],
                        $coords['lng'],
                    ]
                );
            }

            audit_log($userId, 'register', 'users', $userId, ['role' => $role]);
            $pdo->commit();

            set_flash('success', $role === 'donor'
                ? 'Registration successful. You can login and start receiving compatible blood requests.'
                : 'Registration successful. Please login.');
            redirect('login.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}

if ($errors) {
    foreach ($errors as $error) {
        set_flash('danger', $error);
    }
}

$pageTitle = 'Register';
$showSidebar = false;
$withMaps = true;
$extraScripts = [asset('js/patient-maps.js')];
$mainContainerClass = 'container py-4 py-md-5';
include __DIR__ . '/includes/header.php';
?>

<div class="auth-wrap" style="max-width: 860px;">
    <form method="post" class="card-soft p-4 p-md-5">
        <?= csrf_field() ?>
        <h1 class="h2 mb-2">Create Account</h1>
        <p class="text-muted mb-4">Choose role, fill profile, and pin your location on map.</p>

        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Full Name</label><input type="text" name="full_name" class="form-control" value="<?= e($form['full_name']) ?>" required></div>
            <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= e($form['email']) ?>" required></div>
            <div class="col-md-4"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="<?= e($form['phone']) ?>"></div>
            <div class="col-md-4"><label class="form-label">City</label><input type="text" name="city" class="form-control" value="<?= e($form['city']) ?>" required></div>
            <div class="col-md-4"><label class="form-label">Role</label>
                <select name="role" class="form-select" required>
                    <option value="patient" <?= $form['role'] === 'patient' ? 'selected' : '' ?>>Patient</option>
                    <option value="donor" <?= $form['role'] === 'donor' ? 'selected' : '' ?>>Donor</option>
                </select>
            </div>
            <div class="col-md-6"><label class="form-label">Blood Group</label>
                <select name="blood_group" class="form-select" required>
                    <option value="">Select blood group</option>
                    <?php foreach (blood_groups() as $group): ?>
                        <option value="<?= e($group) ?>" <?= $form['blood_group'] === $group ? 'selected' : '' ?>><?= e($group) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3"><label class="form-label">Age</label><input type="number" name="age" class="form-control" value="<?= e($form['age']) ?>" required></div>
            <div class="col-md-3"><label class="form-label">Gender</label>
                <select name="gender" class="form-select">
                    <option value="male" <?= $form['gender'] === 'male' ? 'selected' : '' ?>>Male</option>
                    <option value="female" <?= $form['gender'] === 'female' ? 'selected' : '' ?>>Female</option>
                    <option value="other" <?= $form['gender'] === 'other' ? 'selected' : '' ?>>Other</option>
                </select>
            </div>
            <div class="col-md-4"><label class="form-label">Date of Birth</label><input type="date" name="date_of_birth" class="form-control" value="<?= e($form['date_of_birth']) ?>"></div>
            <div class="col-md-4"><label class="form-label">Emergency Contact</label><input type="text" name="emergency_contact" class="form-control" value="<?= e($form['emergency_contact']) ?>"></div>
            <div class="col-md-4 donor-only"><label class="form-label">Weight (kg, donor)</label><input type="number" step="0.1" name="weight" class="form-control" value="<?= e($form['weight']) ?>"></div>
            <div class="col-md-6 donor-only"><label class="form-label">Last Donation Date (donor)</label><input type="date" name="last_donation_date" class="form-control" value="<?= e($form['last_donation_date']) ?>"></div>
            <div class="col-md-6 donor-only"><label class="form-label">Medical Condition (donor)</label>
                <select name="medical_condition_status" class="form-select">
                    <option value="healthy" <?= $form['medical_condition_status'] === 'healthy' ? 'selected' : '' ?>>Healthy</option>
                    <option value="temporary_deferral" <?= $form['medical_condition_status'] === 'temporary_deferral' ? 'selected' : '' ?>>Temporary Deferral</option>
                    <option value="chronic_issue" <?= $form['medical_condition_status'] === 'chronic_issue' ? 'selected' : '' ?>>Chronic Issue</option>
                </select>
            </div>
            <div class="col-12"><label class="form-label">Address</label><input type="text" name="address" class="form-control" value="<?= e($form['address']) ?>" required></div>

            <div class="col-12">
                <div class="map-card">
                    <div class="map-toolbar">
                        <input type="text" id="registerLocationSearch" class="form-control form-control-sm" placeholder="Search area / hospital / street">
                        <button type="button" id="registerLocationSearchBtn" class="btn btn-sm btn-outline-secondary">Search</button>
                        <button type="button" id="registerUseCurrentLocation" class="btn btn-sm btn-outline-primary">Use My Current Location</button>
                    </div>
                    <div id="registerLocationMap" class="map-canvas"></div>
                    <div id="registerLocationPreview" class="map-hint">Click map or use current location.</div>
                </div>
            </div>
            <div class="col-md-6"><label class="form-label">Latitude</label><input type="number" step="0.0000001" name="latitude" class="form-control" value="<?= e($form['latitude']) ?>" required></div>
            <div class="col-md-6"><label class="form-label">Longitude</label><input type="number" step="0.0000001" name="longitude" class="form-control" value="<?= e($form['longitude']) ?>" required></div>

            <div class="col-md-6"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Confirm Password</label><input type="password" name="confirm_password" class="form-control" required></div>
        </div>

        <button type="submit" class="btn btn-danger mt-4 w-100">Register</button>
        <p class="small text-muted mt-3 mb-0">Already registered? <a href="<?= e(url('login.php')) ?>">Login now</a></p>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    SmartBloodLocationPicker.initLocationPicker({
        mapId: 'registerLocationMap',
        latSelector: 'input[name=\"latitude\"]',
        lngSelector: 'input[name=\"longitude\"]',
        previewSelector: '#registerLocationPreview',
        searchInputSelector: '#registerLocationSearch',
        searchButtonSelector: '#registerLocationSearchBtn',
        gpsButtonSelector: '#registerUseCurrentLocation',
        addressSelector: 'input[name=\"address\"]',
        citySelector: 'input[name=\"city\"]'
    });

    const roleSelect = document.querySelector('select[name=\"role\"]');
    const donorOnlyFields = Array.from(document.querySelectorAll('.donor-only'));

    function toggleRoleFields() {
        const isDonor = roleSelect && roleSelect.value === 'donor';
        donorOnlyFields.forEach(function (group) {
            group.classList.toggle('d-none', !isDonor);
            group.querySelectorAll('input, select, textarea').forEach(function (input) {
                if (input.name === 'weight' || input.name === 'medical_condition_status') {
                    input.required = isDonor;
                } else {
                    input.required = false;
                }
            });
        });
    }

    if (roleSelect) {
        roleSelect.addEventListener('change', toggleRoleFields);
    }
    toggleRoleFields();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
