<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_with_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $message === '') {
        set_flash('danger', 'Please provide valid name, email, and message.');
    } else {
        try {
            db_query(
                "INSERT INTO contact_messages (name, email, message) VALUES (?, ?, ?)",
                [$name, $email, $message]
            );
            set_flash('success', 'Your message has been received. We will contact you soon.');
        } catch (Throwable $e) {
            set_flash('warning', 'Message could not be saved right now. Please try again.');
        }
    }
    redirect('contact.php');
}

$pageTitle = 'Contact';
$showSidebar = false;
$mainContainerClass = 'container py-4 py-md-5';
include __DIR__ . '/includes/header.php';
?>

<section class="row g-4">
    <div class="col-lg-5">
        <div class="card-soft p-4 h-100">
            <h2 class="mb-3">Contact Team</h2>
            <p class="text-muted">Reach out for deployment, training, or customization support.</p>
            <ul class="small text-muted mb-0">
                <li>Email: support@smartblood.test</li>
                <li>Phone: +977-9800000000</li>
                <li>Address: Kathmandu, Nepal</li>
            </ul>
        </div>
    </div>
    <div class="col-lg-7">
        <form method="post" class="card-soft p-4">
            <?= csrf_field() ?>
            <h3 class="mb-3">Send Message</h3>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Message</label>
                    <textarea name="message" rows="5" class="form-control" required></textarea>
                </div>
            </div>
            <button class="btn btn-danger mt-3" type="submit">Submit Message</button>
        </form>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

