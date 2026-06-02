<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$admin = current_user();
$commandOutput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_with_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'train_model') {
        $script = BASE_PATH . DIRECTORY_SEPARATOR . 'ml' . DIRECTORY_SEPARATOR . 'train_model.py';
        if (is_file($script)) {
            $cmd = shell_arg(PYTHON_BIN) . ' ' . shell_arg($script) . ' 2>&1';
            $commandOutput = (string) @shell_exec($cmd);
            set_flash('info', 'Train command executed. Check output below.');
        } else {
            set_flash('warning', 'Training script not found.');
        }
    }

    if ($action === 'run_batch') {
        $requests = db_fetch_all("SELECT id FROM blood_requests WHERE COALESCE(request_status,status) IN ('pending','matched','partially_fulfilled') ORDER BY created_at DESC LIMIT 12");
        $donors = db_fetch_all(
            "SELECT d.id AS donor_id, u.blood_group, d.age, d.weight, d.last_donation_date, COALESCE(d.medical_condition_status,d.medical_condition) AS medical_condition_status, COALESCE(d.availability_status,d.available_status) AS availability_status, d.latitude, d.longitude, d.past_donations, d.response_rate
             FROM donors d JOIN users u ON u.id = d.user_id WHERE d.is_verified = 1 LIMIT 20"
        );
        $runs = 0;
        foreach ($requests as $requestRow) {
            $request = fetch_blood_request((int) $requestRow['id']);
            if (!$request) {
                continue;
            }
            foreach ($donors as $donor) {
                if (!can_donate_to((string) $donor['blood_group'], (string) $request['blood_group'])) {
                    continue;
                }
                $prediction = predict_donor_likelihood($donor, $request);
                log_prediction((int) $donor['donor_id'], (int) $request['id'], $prediction['result'], $prediction['features']);
                $runs++;
            }
        }
        audit_log((int) $admin['id'], 'ml_batch_predictions', 'ml_predictions', null, ['runs' => $runs]);
        set_flash('success', "Batch prediction run completed: {$runs} inference log(s) inserted.");
    }
}

$stats = db_fetch_one(
    "SELECT
        COUNT(*) AS total_predictions,
        ROUND(AVG(probability_score) * 100, 2) AS avg_probability,
        SUM(CASE WHEN predicted_class = 'likely' THEN 1 ELSE 0 END) AS likely_count,
        SUM(CASE WHEN predicted_class = 'unlikely' THEN 1 ELSE 0 END) AS unlikely_count
     FROM ml_predictions"
);

$modelBreakdown = db_fetch_all(
    "SELECT model_name, COUNT(*) AS c, ROUND(AVG(probability_score) * 100, 2) AS avg_score
     FROM ml_predictions
     GROUP BY model_name
     ORDER BY c DESC"
);

$recentPredictions = db_fetch_all(
    "SELECT mp.*, u.full_name AS donor_name, br.hospital_name, br.blood_group
     FROM ml_predictions mp
     JOIN donors d ON d.id = mp.donor_id
     JOIN users u ON u.id = d.user_id
     LEFT JOIN blood_requests br ON br.id = mp.request_id
     ORDER BY mp.created_at DESC
     LIMIT 30"
);

$pageTitle = 'ML Reports';
$showSidebar = true;
$mainContainerClass = 'container-fluid py-4';
include __DIR__ . '/../includes/header.php';
?>

<section class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="kpi-card"><div class="kpi-label">Total Predictions</div><div class="kpi-value"><?= e((int) ($stats['total_predictions'] ?? 0)) ?></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="kpi-card"><div class="kpi-label">Average Probability</div><div class="kpi-value"><?= e(number_format((float) ($stats['avg_probability'] ?? 0), 2)) ?>%</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="kpi-card"><div class="kpi-label">Likely</div><div class="kpi-value"><?= e((int) ($stats['likely_count'] ?? 0)) ?></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="kpi-card"><div class="kpi-label">Unlikely</div><div class="kpi-value"><?= e((int) ($stats['unlikely_count'] ?? 0)) ?></div></div></div>
</section>

<section class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card-soft p-4 h-100">
            <h4 class="mb-3">Model Control</h4>
            <form method="post" class="mb-2">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="train_model">
                <button class="btn btn-outline-primary">Run `ml/train_model.py`</button>
            </form>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="run_batch">
                <button class="btn btn-danger">Run Batch Predictions from DB</button>
            </form>
            <p class="small text-muted mt-3 mb-1">Use `SMARTBLOOD_PYTHON` in `.env` for the Python executable path.</p>
            <p class="small text-muted mb-0">Current: <code><?= e(PYTHON_BIN) ?></code></p>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card-soft p-4 h-100">
            <h4 class="mb-3">Model Breakdown</h4>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Model</th><th>Runs</th><th>Avg Score</th></tr></thead>
                    <tbody>
                    <?php foreach ($modelBreakdown as $row): ?>
                        <tr><td><?= e($row['model_name']) ?></td><td><?= e((int) $row['c']) ?></td><td><?= e((float) $row['avg_score']) ?>%</td></tr>
                    <?php endforeach; ?>
                    <?php if (!$modelBreakdown): ?>
                        <tr><td colspan="3" class="text-muted text-center">No prediction logs yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<?php if ($commandOutput !== ''): ?>
    <section class="card-soft p-4 mb-4">
        <h4 class="mb-3">Training Output</h4>
        <pre class="small bg-light p-3 rounded mb-0"><?= e($commandOutput) ?></pre>
    </section>
<?php endif; ?>

<section class="card-soft p-4">
    <h4 class="mb-3">Recent Prediction Logs</h4>
    <div class="table-responsive">
        <table class="table table-modern align-middle">
            <thead><tr><th>Time</th><th>Donor</th><th>Request</th><th>Model</th><th>Score</th><th>Class</th><th>Confidence</th></tr></thead>
            <tbody>
            <?php foreach ($recentPredictions as $row): ?>
                <tr>
                    <td><?= e(date('Y-m-d H:i', strtotime((string) $row['created_at']))) ?></td>
                    <td><?= e($row['donor_name']) ?></td>
                    <td><?= $row['request_id'] ? 'BR-' . e((int) $row['request_id']) . ' / ' . e((string) $row['blood_group']) : 'N/A' ?></td>
                    <td><?= e($row['model_name']) ?></td>
                    <td><?= e(number_format((float) $row['probability_score'] * 100, 1)) ?>%</td>
                    <td><?= e($row['predicted_class']) ?></td>
                    <td><?= e($row['confidence_label']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$recentPredictions): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No ML prediction records available.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
