<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('donor');

$user = current_user();
$donorId = (int) ($user['donor_id'] ?? 0);

$history = db_fetch_all(
    "SELECT
        da.id AS appointment_id,
        da.scheduled_at,
        da.status,
        br.id AS request_id,
        br.hospital_name,
        br.hospital_city,
        br.blood_group,
        bi.id AS inventory_id,
        bi.expiry_date
     FROM donation_appointments da
     LEFT JOIN blood_requests br ON br.id = da.request_id
     LEFT JOIN blood_inventory bi ON bi.request_id = da.request_id AND bi.donor_id = da.donor_id
     WHERE da.donor_id = ? AND da.status = 'completed'
     ORDER BY da.scheduled_at DESC",
    [$donorId]
);

$pageTitle = 'Donation History';
$showSidebar = true;
$mainContainerClass = 'container-fluid py-4';
include __DIR__ . '/../includes/header.php';
?>

<section class="card-soft p-4">
    <h1 class="h3 mb-3">Donation History</h1>
    <div class="table-responsive">
        <table class="table table-modern align-middle">
            <thead>
            <tr>
                <th>Appointment</th>
                <th>Request</th>
                <th>Blood Group</th>
                <th>Hospital</th>
                <th>Donation Date</th>
                <th>Inventory Unit</th>
                <th>Expiry</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($history as $item): ?>
                <tr>
                    <td>AP-<?= e((int) $item['appointment_id']) ?></td>
                    <td>BR-<?= e((int) $item['request_id']) ?></td>
                    <td><?= e($item['blood_group']) ?></td>
                    <td><?= e($item['hospital_name']) ?>, <?= e($item['hospital_city']) ?></td>
                    <td><?= e(date('Y-m-d H:i', strtotime((string) $item['scheduled_at']))) ?></td>
                    <td><?= $item['inventory_id'] ? 'INV-' . e((int) $item['inventory_id']) : '-' ?></td>
                    <td><?= $item['expiry_date'] ? e((string) $item['expiry_date']) : '-' ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$history): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No completed donations yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

