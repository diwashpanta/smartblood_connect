<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$stats = [
    'donors' => 0,
    'patients' => 0,
    'pending_requests' => 0,
    'available_units' => 0,
];

try {
    $stats['donors'] = (int) (db_fetch_one("SELECT COUNT(*) AS c FROM donors")['c'] ?? 0);
    $stats['patients'] = (int) (db_fetch_one("SELECT COUNT(*) AS c FROM patients")['c'] ?? 0);
    $stats['pending_requests'] = (int) (db_fetch_one("SELECT COUNT(*) AS c FROM blood_requests WHERE status IN ('pending','matched','partially_fulfilled')")['c'] ?? 0);
    $stats['available_units'] = (int) (db_fetch_one("SELECT COALESCE(SUM(quantity_units),0) AS c FROM blood_inventory WHERE status = 'available' AND expiry_date >= CURDATE()")['c'] ?? 0);
} catch (Throwable $e) {
    set_flash('warning', 'Database is not initialized yet. Import SQL files from database folder.');
}

$pageTitle = 'Landing';
$showSidebar = false;
$mainContainerClass = 'container py-4 py-md-5';
include __DIR__ . '/includes/header.php';
?>

<section class="hero p-4 p-md-5 mb-4">
    <div class="row align-items-center g-4">
        <div class="col-lg-7">
            <h1 class="display-5 fw-bold mb-3">SmartBlood Connect</h1>
            <p class="lead text-muted mb-4">
                Modern Blood Bank Management System for patient requests, donor matching, inventory tracking,
                and machine-learning assisted donation likelihood prediction.
            </p>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= e(url('register.php')) ?>" class="btn btn-danger btn-lg">Create Account</a>
                <a href="<?= e(url('login.php')) ?>" class="btn btn-outline-dark btn-lg">Login</a>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="workflow-grid">
                <div class="workflow-step">
                    <h6>1. Request</h6>
                    <p class="small text-muted mb-0">Patients submit urgent or routine blood requests.</p>
                </div>
                <div class="workflow-step">
                    <h6>2. Match</h6>
                    <p class="small text-muted mb-0">System checks inventory then finds nearest eligible donors.</p>
                </div>
                <div class="workflow-step">
                    <h6>3. Notify</h6>
                    <p class="small text-muted mb-0">Top matched donors receive acceptance/decline notifications.</p>
                </div>
                <div class="workflow-step">
                    <h6>4. Fulfill</h6>
                    <p class="small text-muted mb-0">Appointments and blood issuance complete the request lifecycle.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="kpi-card">
            <div class="kpi-label">Verified Donors</div>
            <div class="kpi-value"><?= e($stats['donors']) ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="kpi-card">
            <div class="kpi-label">Registered Patients</div>
            <div class="kpi-value"><?= e($stats['patients']) ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="kpi-card">
            <div class="kpi-label">Active Requests</div>
            <div class="kpi-value"><?= e($stats['pending_requests']) ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="kpi-card">
            <div class="kpi-label">Available Units</div>
            <div class="kpi-value"><?= e($stats['available_units']) ?></div>
        </div>
    </div>
</section>

<section class="row g-4">
    <div class="col-lg-8">
        <div class="card-soft p-4 h-100">
            <h3 class="mb-3">Why SmartBlood Connect</h3>
            <ul class="mb-0">
                <li>Role-based dashboards for Patient, Donor, and Admin.</li>
                <li>Blood compatibility-aware request matching and inventory issuance.</li>
                <li>Proximity and ML-assisted donor ranking for faster response.</li>
                <li>Audit trails, notification workflow, and status lifecycle tracking.</li>
            </ul>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card-soft p-4 h-100">
            <h4 class="mb-3">Quick Access</h4>
            <div class="d-grid gap-2">
                <a href="<?= e(url('about.php')) ?>" class="btn btn-outline-secondary">About Project</a>
                <a href="<?= e(url('contact.php')) ?>" class="btn btn-outline-secondary">Contact</a>
                <a href="<?= e(url('admin/dashboard.php')) ?>" class="btn btn-outline-secondary">Admin Console</a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

