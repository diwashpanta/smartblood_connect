<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$admin = current_user();
$preselectRequestId = (int) ($_GET['request_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_with_csrf();

    $requestId = (int) ($_POST['request_id'] ?? 0);
    $inventoryId = (int) ($_POST['inventory_id'] ?? 0);
    $units = (int) ($_POST['units_issued'] ?? 1);
    $notes = trim((string) ($_POST['notes'] ?? ''));

    $request = fetch_blood_request($requestId);
    $inventory = db_fetch_one("SELECT * FROM blood_inventory WHERE id = ?", [$inventoryId]);

    if (!$request || !$inventory) {
        set_flash('danger', 'Invalid request or inventory selection.');
        redirect('admin/issue_blood.php');
    }

    $remaining = max(0, (int) $request['units_needed'] - (int) $request['units_fulfilled']);
    if ($remaining <= 0) {
        set_flash('warning', 'Request is already fulfilled.');
        redirect('admin/issue_blood.php?request_id=' . $requestId);
    }

    if ($inventory['status'] !== 'available' || (int) $inventory['quantity_units'] <= 0 || strtotime((string) $inventory['expiry_date']) < strtotime(date('Y-m-d'))) {
        set_flash('danger', 'Selected inventory unit is not available.');
        redirect('admin/issue_blood.php?request_id=' . $requestId);
    }

    if (!can_donate_to((string) $inventory['blood_group'], (string) $request['blood_group'])) {
        set_flash('danger', 'Selected inventory blood group is not compatible with request blood group.');
        redirect('admin/issue_blood.php?request_id=' . $requestId);
    }

    $issueUnits = min($units, (int) $inventory['quantity_units'], $remaining);
    if ($issueUnits <= 0) {
        set_flash('danger', 'Invalid issue quantity.');
        redirect('admin/issue_blood.php?request_id=' . $requestId);
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();
        $newQty = (int) $inventory['quantity_units'] - $issueUnits;
        $newStatus = $newQty <= 0 ? 'issued' : 'available';

        db_query("UPDATE blood_inventory SET quantity_units = ?, status = ? WHERE id = ?", [$newQty, $newStatus, $inventoryId]);
        db_query(
            "INSERT INTO blood_issuance (request_id, patient_id, inventory_id, units_issued, issued_by, notes)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$requestId, (int) $request['patient_id'], $inventoryId, $issueUnits, (int) $admin['id'], $notes ?: 'Manual issue by admin']
        );
        db_query(
            "INSERT INTO inventory_transactions (inventory_id, action, quantity_units, reference_type, reference_id, notes, performed_by)
             VALUES (?, 'issue', ?, 'blood_request', ?, ?, ?)",
            [$inventoryId, $issueUnits, $requestId, $notes ?: 'Manual issue by admin', (int) $admin['id']]
        );
        db_query(
            "UPDATE blood_requests
             SET units_fulfilled = LEAST(units_needed, units_fulfilled + ?), updated_at = NOW()
             WHERE id = ?",
            [$issueUnits, $requestId]
        );
        $pdo->commit();

        update_request_status_from_units($requestId);
        create_patient_notification((int) $request['patient_id'], $requestId, "Admin issued {$issueUnits} unit(s) from inventory.");
        audit_log((int) $admin['id'], 'manual_blood_issue', 'blood_request', $requestId, ['inventory_id' => $inventoryId, 'units' => $issueUnits]);
        set_flash('success', 'Blood issued successfully.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        set_flash('danger', 'Failed to issue blood.');
    }

    redirect('admin/issue_blood.php?request_id=' . $requestId);
}

$openRequests = db_fetch_all(
    "SELECT br.*, u.full_name AS patient_name
     FROM blood_requests br
     JOIN patients p ON p.id = br.patient_id
     JOIN users u ON u.id = p.user_id
     WHERE br.status IN ('pending','matched','partially_fulfilled')
     ORDER BY br.urgency DESC, br.created_at ASC"
);

$selectedRequest = null;
foreach ($openRequests as $request) {
    if ((int) $request['id'] === $preselectRequestId) {
        $selectedRequest = $request;
        break;
    }
}

$inventoryList = db_fetch_all(
    "SELECT * FROM blood_inventory
     WHERE status = 'available'
       AND quantity_units > 0
       AND expiry_date >= CURDATE()
     ORDER BY expiry_date ASC"
);

$compatibleInventory = [];
if ($selectedRequest) {
    foreach ($inventoryList as $item) {
        if (can_donate_to((string) $item['blood_group'], (string) $selectedRequest['blood_group'])) {
            $compatibleInventory[] = $item;
        }
    }
}

$pageTitle = 'Issue Blood';
$showSidebar = true;
$mainContainerClass = 'container-fluid py-4';
include __DIR__ . '/../includes/header.php';
?>

<section class="card-soft p-4 mb-4">
    <h1 class="h3 mb-3">Issue Blood to Patient Request</h1>
    <form method="get" class="row g-2 align-items-end mb-3">
        <div class="col-md-8">
            <label class="form-label">Select Request</label>
            <select name="request_id" class="form-select">
                <option value="0">Choose request</option>
                <?php foreach ($openRequests as $request): ?>
                    <option value="<?= e((int) $request['id']) ?>" <?= (int) $preselectRequestId === (int) $request['id'] ? 'selected' : '' ?>>
                        BR-<?= e((int) $request['id']) ?> | <?= e($request['patient_name']) ?> | <?= e($request['blood_group']) ?> | <?= e((int) $request['units_fulfilled']) ?>/<?= e((int) $request['units_needed']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4"><button class="btn btn-outline-secondary w-100">Load Compatible Inventory</button></div>
    </form>

    <?php if ($selectedRequest): ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="request_id" value="<?= e((int) $selectedRequest['id']) ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Compatible Inventory</label>
                    <select name="inventory_id" class="form-select" required>
                        <?php foreach ($compatibleInventory as $item): ?>
                            <option value="<?= e((int) $item['id']) ?>">
                                INV-<?= e((int) $item['id']) ?> | <?= e($item['blood_group']) ?> | Qty <?= e((int) $item['quantity_units']) ?> | Exp <?= e($item['expiry_date']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Units</label>
                    <input type="number" name="units_issued" min="1" value="1" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Notes</label>
                    <input type="text" name="notes" class="form-control" placeholder="Optional note">
                </div>
            </div>
            <button class="btn btn-danger mt-3" <?= $compatibleInventory ? '' : 'disabled' ?>>Issue Blood</button>
            <?php if (!$compatibleInventory): ?>
                <div class="small text-danger mt-2">No compatible available inventory for this request.</div>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

