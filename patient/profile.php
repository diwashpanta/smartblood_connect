<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('patient');

$user = current_user();
$patientId = (int) ($user['patient_id'] ?? 0);

$patient = db_fetch_one("SELECT * FROM patients WHERE id = ?", [$patientId]);
if (!$patient) {
    set_flash('danger', 'Patient profile not found.');
    redirect('patient/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_with_csrf();

    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $city = trim((string) ($_POST['city'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));
    $bloodGroup = trim((string) ($_POST['blood_group'] ?? ''));
    $age = (int) ($_POST['age'] ?? 0);
    $gender = trim((string) ($_POST['gender'] ?? 'other'));
    $dob = trim((string) ($_POST['date_of_birth'] ?? ''));
    $hospitalPreference = trim((string) ($_POST['hospital_preference'] ?? ''));
    $notes = trim((string) ($_POST['notes'] ?? ''));

    $errors = [];
    if ($fullName === '') {
        $errors[] = 'Full name is required.';
    }
    if (!in_array($bloodGroup, blood_groups(), true)) {
        $errors[] = 'Invalid blood group selected.';
    }
    if ($age < 1 || $age > 120) {
        $errors[] = 'Enter a valid age.';
    }
    if (!in_array($gender, ['male', 'female', 'other'], true)) {
        $errors[] = 'Invalid gender option.';
    }

    if ($errors) {
        foreach ($errors as $error) {
            set_flash('danger', $error);
        }
        redirect('patient/profile.php');
    }

    db_query(
        "UPDATE users SET full_name = ?, phone = ?, city = ?, address = ?, blood_group = ? WHERE id = ?",
        [$fullName, $phone ?: null, $city ?: null, $address ?: null, $bloodGroup, (int) $user['id']]
    );
    db_query(
        "UPDATE patients SET age = ?, gender = ?, date_of_birth = ?, hospital_preference = ?, notes = ? WHERE id = ?",
        [$age, $gender, $dob ?: null, $hospitalPreference ?: null, $notes ?: null, $patientId]
    );

    refresh_current_user();
    audit_log((int) $user['id'], 'patient_profile_updated', 'patients', $patientId);
    set_flash('success', 'Profile updated successfully.');
    redirect('patient/profile.php');
}

$user = current_user(true);
$patient = db_fetch_one("SELECT * FROM patients WHERE id = ?", [$patientId]);

$pageTitle = 'Patient Profile';
$showSidebar = true;
$mainContainerClass = 'container-fluid py-4';
include __DIR__ . '/../includes/header.php';
?>

<section class="card-soft p-4">
    <h1 class="h3 mb-4">Patient Profile</h1>
    <form method="post">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Full Name</label>
                <input type="text" name="full_name" class="form-control" value="<?= e($user['full_name']) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" value="<?= e($user['email']) ?>" disabled>
            </div>
            <div class="col-md-4">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control" value="<?= e($user['phone']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">City</label>
                <input type="text" name="city" class="form-control" value="<?= e($user['city']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Blood Group</label>
                <select name="blood_group" class="form-select">
                    <?php foreach (blood_groups() as $group): ?>
                        <option value="<?= e($group) ?>" <?= ($user['blood_group'] ?? '') === $group ? 'selected' : '' ?>><?= e($group) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Age</label>
                <input type="number" name="age" class="form-control" value="<?= e((int) $patient['age']) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Gender</label>
                <select name="gender" class="form-select">
                    <?php foreach (['male','female','other'] as $g): ?>
                        <option value="<?= e($g) ?>" <?= $patient['gender'] === $g ? 'selected' : '' ?>><?= e(ucfirst($g)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Date of Birth</label>
                <input type="date" name="date_of_birth" class="form-control" value="<?= e((string) ($patient['date_of_birth'] ?? '')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Hospital Preference</label>
                <input type="text" name="hospital_preference" class="form-control" value="<?= e((string) ($patient['hospital_preference'] ?? '')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Address</label>
                <input type="text" name="address" class="form-control" value="<?= e((string) ($user['address'] ?? '')) ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Medical Notes</label>
                <textarea name="notes" rows="4" class="form-control"><?= e((string) ($patient['notes'] ?? '')) ?></textarea>
            </div>
        </div>
        <button class="btn btn-danger mt-4">Save Profile</button>
    </form>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

