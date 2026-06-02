<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$admin = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_with_csrf();
    $bloodGroup = trim((string) ($_POST['blood_group'] ?? ''));
    $donorId = trim((string) ($_POST['donor_id'] ?? ''));
    $quantity = (int) ($_POST['quantity_units'] ?? 1);
    $expiryDate = trim((string) ($_POST['expiry_date'] ?? ''));
    $collectedAt = trim((string) ($_POST['collected_at'] ?? date('Y-m-d')));
    $requestId = trim((string) ($_POST['request_id'] ?? ''));

    $errors = [];
    if (!in_array($bloodGroup, blood_groups(), true)) {
        $errors[] = 'Invalid blood group.';
    }
    if ($quantity < 1) {
        $errors[] = 'Quantity must be at least 1.';
    }
    if ($expiryDate === '') {
        $errors[] = 'Expiry date is required.';
    }

    if ($errors) {
        foreach ($errors as $error) {
            set_flash('danger', $error);
        }
        redirect('admin/add_inventory.php');
    }

    $status = strtotime($expiryDate) < strtotime(date('Y-m-d')) ? 'expired' : 'available';
    db_query(
        "INSERT INTO blood_inventory (blood_group, donor_id, request_id, quantity_units, expiry_date, status, collected_at, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $bloodGroup,
            $donorId !== '' ? (int) $donorId : null,
            $requestId !== '' ? (int) $requestId : null,
            $quantity,
            $expiryDate,
            $status,
            $collectedAt !== '' ? $collectedAt : null,
            (int) ($admin['admin_id'] ?? null),
        ]
    );
    $inventoryId = (int) db()->lastInsertId();

    db_query(
        "INSERT INTO inventory_transactions (inventory_id, action, quantity_units, reference_type, reference_id, notes, performed_by)
         VALUES (?, 'add', ?, 'manual', NULL, ?, ?)",
        [$inventoryId, $quantity, 'Manual stock add by admin', (int) $admin['id']]
    );

    audit_log((int) $admin['id'], 'inventory_added', 'blood_inventory', $inventoryId, ['quantity' => $quantity]);
    set_flash('success', 'Inventory added successfully.');
    redirect('admin/inventory.php');
}

$donors = db_fetch_all(
    "SELECT d.id, u.full_name, u.blood_group, d.is_verified
     FROM donors d
     JOIN users u ON u.id = d.user_id
     ORDER BY u.full_name"
);

$openRequests = db_fetch_all(
    "SELECT id, blood_group, units_needed, units_fulfilled, hospital_name
     FROM blood_requests
     WHERE status IN ('pending','matched','partially_fulfilled')
     ORDER BY created_at DESC
     LIMIT 20"
);

$pageTitle = 'Add Inventory';
$showSidebar = true;
$mainContainerClass = 'container-fluid py-4';
include __DIR__ . '/../includes/header.php';
?>

<section class="card-soft p-4">
    <h1 class="h3 mb-3">Add Blood Inventory</h1>
    <form method="post">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Blood Group</label>
                <select name="blood_group" class="form-select" required>
                    <?php foreach (blood_groups() as $group): ?>
                        <option value="<?= e($group) ?>"><?= e($group) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Quantity (Units)</label>
                <input type="number" name="quantity_units" class="form-control" min="1" value="1" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Expiry Date</label>
                <input type="date" name="expiry_date" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Donor (optional)</label>
                <select name="donor_id" class="form-select">
                    <option value="">Manual stock / Unknown donor</option>
                    <?php foreach ($donors as $donor): ?>
                        <option value="<?= e((int) $donor['id']) ?>"><?= e($donor['full_name']) ?> (<?= e($donor['blood_group']) ?>) <?= (int) $donor['is_verified'] === 1 ? '' : '[Unverified]' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Related Request (optional)</label>
                <select name="request_id" class="form-select">
                    <option value="">No linked request</option>
                    <?php foreach ($openRequests as $request): ?>
                        <option value="<?= e((int) $request['id']) ?>">BR-<?= e((int) $request['id']) ?> | <?= e($request['blood_group']) ?> | <?= e($request['hospital_name']) ?> (<?= e((int) $request['units_fulfilled']) ?>/<?= e((int) $request['units_needed']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Collected At</label>
                <input type="date" name="collected_at" class="form-control" value="<?= e(date('Y-m-d')) ?>">
            </div>
        </div>
        <button class="btn btn-danger mt-4">Save Inventory</button>
    </form>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

