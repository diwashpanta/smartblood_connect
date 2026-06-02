<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$admin = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_with_csrf();
    $settings = $_POST['settings'] ?? [];
    if (is_array($settings)) {
        foreach ($settings as $key => $value) {
            $key = trim((string) $key);
            if ($key === '') {
                continue;
            }
            $existing = db_fetch_one("SELECT id FROM app_settings WHERE setting_key = ?", [$key]);
            if ($existing) {
                db_query("UPDATE app_settings SET setting_value = ? WHERE setting_key = ?", [trim((string) $value), $key]);
            } else {
                db_query("INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)", [$key, trim((string) $value)]);
            }
        }
        audit_log((int) $admin['id'], 'settings_updated', 'app_settings', null, ['count' => count($settings)]);
        set_flash('success', 'Settings saved.');
    }
    redirect('admin/settings.php');
}

$defaults = [
    'app_title' => APP_NAME,
    'low_stock_threshold' => '5',
    'default_appointment_duration_minutes' => '30',
    'support_email' => 'support@smartblood.test',
    'support_phone' => '+9779800000000',
];
$dbSettings = db_fetch_all("SELECT setting_key, setting_value FROM app_settings ORDER BY setting_key");
$values = $defaults;
foreach ($dbSettings as $row) {
    $values[$row['setting_key']] = (string) $row['setting_value'];
}

$pageTitle = 'Settings';
$showSidebar = true;
$mainContainerClass = 'container-fluid py-4';
include __DIR__ . '/../includes/header.php';
?>

<section class="row g-4">
    <div class="col-lg-7">
        <form method="post" class="card-soft p-4">
            <?= csrf_field() ?>
            <h1 class="h3 mb-3">Application Settings</h1>
            <?php foreach ($values as $key => $value): ?>
                <div class="mb-3">
                    <label class="form-label"><?= e(ucwords(str_replace('_', ' ', $key))) ?></label>
                    <input type="text" class="form-control" name="settings[<?= e($key) ?>]" value="<?= e($value) ?>">
                </div>
            <?php endforeach; ?>
            <button class="btn btn-danger">Save Settings</button>
        </form>
    </div>
    <div class="col-lg-5">
        <div class="card-soft p-4 h-100">
            <h4 class="mb-3">Environment Summary</h4>
            <ul class="small mb-0">
                <li><strong>Base URL:</strong> <?= e(APP_BASE_URL) ?></li>
                <li><strong>Database:</strong> <?= e((string) (getenv('DB_NAME') ?: 'smartblood_connect')) ?></li>
                <li><strong>DB Host:</strong> <?= e((string) (getenv('DB_HOST') ?: '127.0.0.1')) ?></li>
                <li><strong>Python Bin:</strong> <?= e(PYTHON_BIN) ?></li>
                <li><strong>Timezone:</strong> <?= e(date_default_timezone_get()) ?></li>
            </ul>
            <hr>
            <p class="small text-muted mb-0">
                You can change DB and runtime values through `.env` and then refresh Apache.
            </p>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

