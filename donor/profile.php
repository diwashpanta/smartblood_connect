<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('donor');

$user = current_user();
$donorId = (int) ($user['donor_id'] ?? 0);
$donor = db_fetch_one("SELECT * FROM donors WHERE id = ?", [$donorId]);

if (!$donor) {
    set_flash('danger', 'Donor profile not found.');
    redirect('donor/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_with_csrf();

    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $city = trim((string) ($_POST['city'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));
    $bloodGroup = trim((string) ($_POST['blood_group'] ?? ''));
    $age = (int) ($_POST['age'] ?? 0);
    $dob = trim((string) ($_POST['date_of_birth'] ?? ''));
    $weight = (float) ($_POST['weight'] ?? 0);
    $lat = trim((string) ($_POST['latitude'] ?? ''));
    $lng = trim((string) ($_POST['longitude'] ?? ''));
    $lastDonation = trim((string) ($_POST['last_donation_date'] ?? ''));
    $medical = trim((string) ($_POST['medical_condition_status'] ?? 'healthy'));
    $availability = trim((string) ($_POST['availability_status'] ?? 'available'));

    $errors = [];
    if ($fullName === '') {
        $errors[] = 'Full name is required.';
    }
    if (!in_array($bloodGroup, blood_groups(), true)) {
        $errors[] = 'Invalid blood group.';
    }
    if ($age < 18 || $age > 65) {
        $errors[] = 'Donor age must be between 18 and 65.';
    }
    if ($weight < 45) {
        $errors[] = 'Weight must be at least 45 kg.';
    }
    if (!in_array($medical, ['healthy', 'temporary_deferral', 'chronic_issue'], true)) {
        $errors[] = 'Invalid medical status.';
    }
    if (!in_array($availability, ['available', 'busy', 'inactive'], true)) {
        $errors[] = 'Invalid availability status.';
    }

    $coords = sb_validate_lat_lng($lat, $lng);
    if (!sb_has_valid_lat_lng($coords['lat'], $coords['lng'])) {
        $errors[] = 'Valid latitude and longitude are required.';
    } elseif (sb_is_zero_coordinate_pair($coords['lat'], $coords['lng'])) {
        $errors[] = 'Invalid location selected. Please choose your actual donation location.';
    }

    if ($errors) {
        foreach ($errors as $error) {
            set_flash('danger', $error);
        }
        redirect('donor/profile.php');
    }

    $days = days_since($lastDonation ?: null);
    $isEligible = (
        $age >= 18 &&
        $age <= 60 &&
        $weight >= 50 &&
        $medical === 'healthy' &&
        ($days === null || $days >= 90) &&
        $availability === 'available'
    ) ? 1 : 0;

    db_query(
        "UPDATE users SET full_name = ?, name = ?, phone = ?, city = ?, address = ?, blood_group = ?, latitude = ?, longitude = ? WHERE id = ?",
        [$fullName, $fullName, $phone ?: null, $city ?: null, $address ?: null, $bloodGroup, $coords['lat'], $coords['lng'], (int) $user['id']]
    );
    db_query(
        "UPDATE donors
         SET blood_group = ?, age = ?, date_of_birth = ?, weight = ?, latitude = ?, longitude = ?, city = ?, address = ?, last_donation_date = ?,
             medical_condition_status = ?, medical_condition = ?, availability_status = ?, available_status = ?, is_eligible = ?, is_verified = 1, location_updated_at = NOW()
         WHERE id = ?",
        [
            $bloodGroup,
            $age,
            $dob ?: null,
            $weight,
            $coords['lat'],
            $coords['lng'],
            $city ?: null,
            $address ?: null,
            $lastDonation ?: null,
            $medical,
            $medical,
            $availability,
            $availability,
            $isEligible,
            $donorId,
        ]
    );

    db_query(
        "INSERT INTO donor_locations (donor_id, label, address, city, latitude, longitude, is_primary, created_at, recorded_at)
         VALUES (?, 'Profile Update', ?, ?, ?, ?, 1, NOW(), NOW())",
        [$donorId, $address ?: null, $city ?: null, $coords['lat'], $coords['lng']]
    );

    refresh_current_user();
    audit_log((int) $user['id'], 'donor_profile_updated', 'donors', $donorId);
    set_flash('success', 'Donor profile and location updated.');
    redirect('donor/profile.php');
}

$user = current_user(true);
$donor = db_fetch_one("SELECT * FROM donors WHERE id = ?", [$donorId]);

$pageTitle = 'Donor Profile';
$showSidebar = true;
$withMaps = true;
$mainContainerClass = 'container-fluid py-4';
include __DIR__ . '/../includes/header.php';
?>

<section class="card-soft p-4">
    <h1 class="h3 mb-4">Donor Profile and Location</h1>
    <form method="post">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Full Name</label><input name="full_name" class="form-control" value="<?= e($user['full_name']) ?>" required></div>
            <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" value="<?= e($user['email']) ?>" disabled></div>
            <div class="col-md-4"><label class="form-label">Phone</label><input name="phone" class="form-control" value="<?= e($user['phone']) ?>"></div>
            <div class="col-md-4"><label class="form-label">City</label><input name="city" class="form-control" value="<?= e($donor['city'] ?: $user['city']) ?>"></div>
            <div class="col-md-4"><label class="form-label">Blood Group</label>
                <select name="blood_group" class="form-select">
                    <?php foreach (blood_groups() as $group): ?>
                        <option value="<?= e($group) ?>" <?= (($donor['blood_group'] ?: $user['blood_group']) ?? '') === $group ? 'selected' : '' ?>><?= e($group) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3"><label class="form-label">Age</label><input type="number" name="age" class="form-control" value="<?= e((int) $donor['age']) ?>" required></div>
            <div class="col-md-3"><label class="form-label">DOB</label><input type="date" name="date_of_birth" class="form-control" value="<?= e((string) $donor['date_of_birth']) ?>"></div>
            <div class="col-md-3"><label class="form-label">Weight (kg)</label><input type="number" step="0.1" name="weight" class="form-control" value="<?= e((float) $donor['weight']) ?>" required></div>
            <div class="col-md-3"><label class="form-label">Last Donation</label><input type="date" name="last_donation_date" class="form-control" value="<?= e((string) $donor['last_donation_date']) ?>"></div>
            <div class="col-md-6"><label class="form-label">Address</label><input name="address" class="form-control" value="<?= e((string) ($donor['address'] ?: $user['address'])) ?>"></div>
            <div class="col-md-3"><label class="form-label">Medical Condition</label>
                <select name="medical_condition_status" class="form-select">
                    <option value="healthy" <?= $donor['medical_condition_status'] === 'healthy' ? 'selected' : '' ?>>Healthy</option>
                    <option value="temporary_deferral" <?= $donor['medical_condition_status'] === 'temporary_deferral' ? 'selected' : '' ?>>Temporary Deferral</option>
                    <option value="chronic_issue" <?= $donor['medical_condition_status'] === 'chronic_issue' ? 'selected' : '' ?>>Chronic Issue</option>
                </select>
            </div>
            <div class="col-md-3"><label class="form-label">Availability</label>
                <select name="availability_status" class="form-select">
                    <option value="available" <?= $donor['availability_status'] === 'available' ? 'selected' : '' ?>>Available</option>
                    <option value="busy" <?= $donor['availability_status'] === 'busy' ? 'selected' : '' ?>>Busy</option>
                    <option value="inactive" <?= $donor['availability_status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>

            <div class="col-12">
                <div class="map-card">
                    <div class="map-toolbar">
                        <input type="text" id="donorLocationSearch" class="form-control form-control-sm" placeholder="Search nearby landmark/address">
                        <button type="button" id="donorLocationSearchBtn" class="btn btn-sm btn-outline-secondary">Search</button>
                        <button type="button" id="donorUseCurrentLocation" class="btn btn-sm btn-outline-primary">Use My Current Location</button>
                    </div>
                    <div id="donorProfileMap" class="map-canvas"></div>
                    <div id="donorLocationPreview" class="map-hint">Update your primary donation location.</div>
                </div>
            </div>

            <div class="col-md-6"><label class="form-label">Latitude</label><input type="number" step="0.0000001" name="latitude" class="form-control" value="<?= e((string) ($donor['latitude'] ?? $user['latitude'])) ?>" required></div>
            <div class="col-md-6"><label class="form-label">Longitude</label><input type="number" step="0.0000001" name="longitude" class="form-control" value="<?= e((string) ($donor['longitude'] ?? $user['longitude'])) ?>" required></div>
        </div>
        <button class="btn btn-danger mt-4">Save Profile</button>
    </form>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    SmartBloodLocationPicker.initLocationPicker({
        mapId: 'donorProfileMap',
        latSelector: 'input[name=\"latitude\"]',
        lngSelector: 'input[name=\"longitude\"]',
        previewSelector: '#donorLocationPreview',
        searchInputSelector: '#donorLocationSearch',
        searchButtonSelector: '#donorLocationSearchBtn',
        gpsButtonSelector: '#donorUseCurrentLocation',
        addressSelector: 'input[name=\"address\"]',
        citySelector: 'input[name=\"city\"]'
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
