<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'About';
$showSidebar = false;
$mainContainerClass = 'container py-4 py-md-5';
include __DIR__ . '/includes/header.php';
?>

<section class="card-soft p-4 p-md-5 mb-4">
    <h1 class="mb-3">About SmartBlood Connect</h1>
    <p class="text-muted mb-4">
        SmartBlood Connect is a full-featured Blood Bank Management System built for rapid and reliable
        blood request fulfillment. It streamlines the complete flow from request registration to donor
        matching, appointment scheduling, inventory updates, and blood issuance.
    </p>
    <div class="row g-3">
        <div class="col-md-6">
            <div class="workflow-step h-100">
                <h6>Core Roles</h6>
                <p class="small text-muted mb-0">Patient, Donor, and Admin roles with secure session-based access and role protection.</p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="workflow-step h-100">
                <h6>Machine Learning Layer</h6>
                <p class="small text-muted mb-0">Logistic regression donor likelihood prediction plus proximity-based donor ranking logic.</p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="workflow-step h-100">
                <h6>Inventory Intelligence</h6>
                <p class="small text-muted mb-0">Compatible blood group checks, low stock visibility, and transaction logs for traceability.</p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="workflow-step h-100">
                <h6>Project Goal</h6>
                <p class="small text-muted mb-0">Reduce delays in emergency blood procurement and improve donor-to-patient conversion.</p>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

