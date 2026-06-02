<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$statusFilter = trim((string) ($_GET['status'] ?? ''));
$bloodFilter = trim((string) ($_GET['blood_group'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_with_csrf();
    $action = $_POST['action'] ?? '';
    $inventoryId = (int) ($_POST['inventory_id'] ?? 0);
    if ($action === 'expire' && $inventoryId > 0) {
        db_query("UPDATE blood_inventory SET status='expired' WHERE id = ?", [$inventoryId]);
        db_query(
            "INSERT INTO inventory_transactions (inventory_id, action, quantity_units, reference_type, notes, performed_by)
             SELECT id, 'expire', quantity_units, 'manual', 'Marked expired by admin', ? FROM blood_inventory WHERE id = ?",
            [(int) current_user()['id'], $inventoryId]
        );
        set_flash('warning', 'Inventory unit marked as expired.');
    }
    redirect('admin/inventory.php');
}

$sql = "SELECT bi.*, u.full_name AS donor_name
        FROM blood_inventory bi
        LEFT JOIN donors d ON d.id = bi.donor_id
        LEFT JOIN users u ON u.id = d.user_id
        WHERE 1=1";
$params = [];
if ($statusFilter !== '') {
    $sql .= " AND bi.status = ?";
    $params[] = $statusFilter;
}
if ($bloodFilter !== '') {
    $sql .= " AND bi.blood_group = ?";
    $params[] = $bloodFilter;
}
$sql .= " ORDER BY bi.expiry_date ASC, bi.created_at DESC";
$inventory = db_fetch_all($sql, $params);

$summary = db_fetch_all(
    "SELECT blood_group, SUM(quantity_units) AS units
     FROM blood_inventory
     WHERE status = 'available' AND expiry_date >= CURDATE()
     GROUP BY blood_group
     ORDER BY FIELD(blood_group,'A+','A-','B+','B-','AB+','AB-','O+','O-')"
);

$lowStockThreshold = (int) (db_fetch_one("SELECT setting_value FROM app_settings WHERE setting_key = 'low_stock_threshold'")['setting_value'] ?? 5);

$pageTitle = 'Inventory';
$showSidebar = true;
$mainContainerClass = 'container-fluid py-4';
include __DIR__ . '/../includes/header.php';
?>

<section class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="card-soft p-4 h-100">
            <h4 class="mb-3">Stock Summary</h4>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Blood Group</th><th class="text-end">Units</th><th>State</th></tr></thead>
                    <tbody>
                    <?php foreach ($summary as $row): ?>
                        <?php $isLow = (int) $row['units'] <= $lowStockThreshold; ?>
                        <tr>
                            <td><?= e($row['blood_group']) ?></td>
                            <td class="text-end fw-semibold"><?= e((int) $row['units']) ?></td>
                            <td><?= $isLow ? '<span class="badge bg-danger-subtle text-danger">Low</span>' : '<span class="badge bg-success-subtle text-success">Healthy</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card-soft p-4 h-100">
            <h4 class="mb-3">Inventory Status Overview</h4>
            <div class="workflow-grid">
                <div class="workflow-step"><h6>Available</h6><p class="small text-muted mb-0">Units ready for compatible patient requests.</p></div>
                <div class="workflow-step"><h6>Reserved</h6><p class="small text-muted mb-0">Units temporarily held for matched cases.</p></div>
                <div class="workflow-step"><h6>Issued</h6><p class="small text-muted mb-0">Units dispensed and recorded in issuance logs.</p></div>
                <div class="workflow-step"><h6>Expired</h6><p class="small text-muted mb-0">Units automatically excluded from active stock.</p></div>
            </div>
        </div>
    </div>
</section>

<section class="card-soft p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Blood Inventory</h1>
        <div class="d-flex gap-2">
            <form method="get" class="d-flex gap-2">
                <select name="blood_group" class="form-select form-select-sm">
                    <option value="">All Groups</option>
                    <?php foreach (blood_groups() as $group): ?>
                        <option value="<?= e($group) ?>" <?= $bloodFilter === $group ? 'selected' : '' ?>><?= e($group) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <?php foreach (['available','reserved','issued','expired'] as $status): ?>
                        <option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-outline-secondary">Filter</button>
            </form>
            <a class="btn btn-sm btn-danger" href="<?= e(url('admin/add_inventory.php')) ?>">Add Inventory</a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-modern align-middle">
            <thead>
            <tr>
                <th>ID</th><th>Blood</th><th>Donor</th><th>Units</th><th>Collected</th><th>Expiry</th><th>Status</th><th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($inventory as $item): ?>
                <?php $isExpiredByDate = strtotime((string) $item['expiry_date']) < strtotime(date('Y-m-d')); ?>
                <tr class="<?= $isExpiredByDate ? 'table-danger' : '' ?>">
                    <td>INV-<?= e((int) $item['id']) ?></td>
                    <td><?= e($item['blood_group']) ?></td>
                    <td><?= e((string) ($item['donor_name'] ?? 'Manual Stock')) ?></td>
                    <td><?= e((int) $item['quantity_units']) ?></td>
                    <td><?= e((string) ($item['collected_at'] ?: '-')) ?></td>
                    <td><?= e($item['expiry_date']) ?></td>
                    <td><span class="badge badge-soft <?= e(badge_class_for_status($item['status'])) ?>"><?= e($item['status']) ?></span></td>
                    <td>
                        <?php if ($item['status'] !== 'expired'): ?>
                            <form method="post" data-confirm="Mark this inventory as expired?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="expire">
                                <input type="hidden" name="inventory_id" value="<?= e((int) $item['id']) ?>">
                                <button class="btn btn-sm btn-outline-danger">Expire</button>
                            </form>
                        <?php else: ?>
                            <span class="text-muted small">No action</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$inventory): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No inventory records found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

