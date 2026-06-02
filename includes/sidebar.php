<?php

declare(strict_types=1);

$user = current_user();
$role = $user['role'] ?? 'patient';
$donorPending = ($role === 'donor' && isset($user['donor_id'])) ? get_donor_pending_notifications_count((int) $user['donor_id']) : 0;
$patientUpdates = ($role === 'patient' && isset($user['patient_id'])) ? get_patient_unread_updates_count((int) $user['patient_id']) : 0;
$adminEmergency = ($role === 'admin') ? get_admin_active_emergency_count() : 0;

$menu = [
    'patient' => [
        ['label' => 'Dashboard', 'icon' => 'speedometer2', 'path' => 'patient/dashboard.php'],
        ['label' => 'Create Request', 'icon' => 'plus-circle', 'path' => 'patient/create_request.php'],
        ['label' => 'My Requests', 'icon' => 'clipboard2-pulse', 'path' => 'patient/my_requests.php'],
        ['label' => 'Notifications', 'icon' => 'bell', 'path' => 'patient/notifications.php', 'badge' => $patientUpdates],
        ['label' => 'Profile', 'icon' => 'person-circle', 'path' => 'patient/profile.php'],
    ],
    'donor' => [
        ['label' => 'Dashboard', 'icon' => 'speedometer2', 'path' => 'donor/dashboard.php'],
        ['label' => 'Profile', 'icon' => 'person-badge', 'path' => 'donor/profile.php'],
        ['label' => 'Nearby Requests', 'icon' => 'geo-alt', 'path' => 'donor/nearby_requests.php'],
        ['label' => 'Notifications', 'icon' => 'megaphone', 'path' => 'donor/notifications.php', 'badge' => $donorPending],
        ['label' => 'Appointments', 'icon' => 'calendar-check', 'path' => 'donor/appointments.php'],
        ['label' => 'Donation History', 'icon' => 'clock-history', 'path' => 'donor/donation_history.php'],
        ['label' => 'Prediction', 'icon' => 'bar-chart-line', 'path' => 'donor/prediction.php'],
    ],
    'admin' => [
        ['label' => 'Dashboard', 'icon' => 'speedometer2', 'path' => 'admin/dashboard.php'],
        ['label' => 'Users', 'icon' => 'people', 'path' => 'admin/users.php'],
        ['label' => 'Donors', 'icon' => 'person-hearts', 'path' => 'admin/donors.php'],
        ['label' => 'Patients', 'icon' => 'person-vcard', 'path' => 'admin/patients.php'],
        ['label' => 'Blood Requests', 'icon' => 'clipboard2-heart', 'path' => 'admin/blood_requests.php', 'badge' => $adminEmergency],
        ['label' => 'Inventory', 'icon' => 'boxes', 'path' => 'admin/inventory.php'],
        ['label' => 'Add Inventory', 'icon' => 'plus-square', 'path' => 'admin/add_inventory.php'],
        ['label' => 'Issue Blood', 'icon' => 'send-check', 'path' => 'admin/issue_blood.php'],
        ['label' => 'Appointments', 'icon' => 'calendar2-week', 'path' => 'admin/appointments.php'],
        ['label' => 'Reports', 'icon' => 'graph-up-arrow', 'path' => 'admin/reports.php'],
        ['label' => 'ML Reports', 'icon' => 'cpu', 'path' => 'admin/ml_reports.php'],
        ['label' => 'Settings', 'icon' => 'gear', 'path' => 'admin/settings.php'],
    ],
];

$currentScript = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
?>
<aside class="sidebar border-end bg-white">
    <div class="px-3 py-4">
        <div class="small text-uppercase text-muted fw-semibold mb-2">Navigation</div>
        <div class="list-group list-group-flush">
            <?php foreach ($menu[$role] ?? [] as $item): ?>
                <?php $isActive = str_contains($currentScript, '/' . $item['path']); ?>
                <a class="list-group-item list-group-item-action border-0 rounded-3 mb-1 <?= $isActive ? 'active' : '' ?>" href="<?= e(url($item['path'])) ?>">
                    <span class="d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-<?= e($item['icon']) ?> me-2"></i><?= e($item['label']) ?></span>
                        <?php if (!empty($item['badge'])): ?>
                            <span class="badge rounded-pill bg-danger-subtle text-danger-emphasis"><?= e((int) $item['badge']) ?></span>
                        <?php endif; ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="px-3 py-3 border-top text-muted small">
        <div><strong>Blood Group:</strong> <?= e($user['blood_group'] ?? 'N/A') ?></div>
        <div><strong>City:</strong> <?= e($user['city'] ?? 'N/A') ?></div>
    </div>
</aside>
