<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('patient');

$user = current_user();
$patientId = (int) ($user['patient_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_with_csrf();
    $id = (int) ($_POST['notification_id'] ?? 0);
    if ($id > 0) {
        db_query("UPDATE patient_notifications SET is_read = 1 WHERE id = ? AND patient_id = ?", [$id, $patientId]);
    } else {
        db_query("UPDATE patient_notifications SET is_read = 1 WHERE patient_id = ?", [$patientId]);
    }
    set_flash('success', 'Notification status updated.');
    redirect('patient/notifications.php');
}

$notifications = db_fetch_all(
    "SELECT pn.*, br.blood_group, COALESCE(br.request_status, br.status) AS request_status
     FROM patient_notifications pn
     JOIN blood_requests br ON br.id = pn.request_id
     WHERE pn.patient_id = ?
     ORDER BY pn.created_at DESC",
    [$patientId]
);

$pageTitle = 'Patient Notifications';
$showSidebar = true;
$mainContainerClass = 'container-fluid py-4';
include __DIR__ . '/../includes/header.php';
?>

<section class="card-soft p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Notifications</h1>
        <form method="post">
            <?= csrf_field() ?>
            <button class="btn btn-sm btn-outline-secondary">Mark All Read</button>
        </form>
    </div>

    <div class="list-group">
        <?php foreach ($notifications as $notification): ?>
            <div class="list-group-item border rounded-3 mb-2">
                <div class="d-flex justify-content-between flex-wrap gap-2">
                    <div>
                        <div class="fw-semibold"><?= e($notification['message']) ?></div>
                        <div class="small text-muted">
                            Request BR-<?= e((int) $notification['request_id']) ?> |
                            Blood <?= e($notification['blood_group']) ?> |
                            Status <?= e($notification['request_status']) ?>
                        </div>
                        <div class="small text-muted"><?= e(date('Y-m-d H:i', strtotime((string) $notification['created_at']))) ?></div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <?php if ((int) $notification['is_read'] === 0): ?>
                            <span class="badge bg-warning-subtle text-warning-emphasis">Unread</span>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="notification_id" value="<?= e((int) $notification['id']) ?>">
                                <button class="btn btn-sm btn-outline-primary">Mark Read</button>
                            </form>
                        <?php else: ?>
                            <span class="badge bg-success-subtle text-success">Read</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$notifications): ?>
            <div class="text-muted">No notifications available.</div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
