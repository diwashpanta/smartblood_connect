<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$pageTitle = $pageTitle ?? APP_NAME;
$bodyClass = $bodyClass ?? '';
$currentUser = current_user();
$showSidebar = $showSidebar ?? ($currentUser !== null);
$mainContainerClass = $mainContainerClass ?? 'container-fluid py-4';
$flashMessages = get_flash_messages();
$withMaps = !empty($withMaps);
$mapConfig = js_config()['map'];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(asset('css/style.css')) ?>" rel="stylesheet">
    <?php if ($withMaps): ?>
        <link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet">
        <link href="<?= e(asset('css/maps.css')) ?>" rel="stylesheet">
    <?php endif; ?>
</head>
<body class="<?= e($bodyClass) ?>">
<script>
window.SMARTBLOOD_CONFIG = <?= json_encode(js_config(), JSON_UNESCAPED_SLASHES) ?>;
</script>
<div class="app-backdrop"></div>
<header class="topbar py-3 border-bottom bg-white sticky-top">
    <div class="container-fluid d-flex align-items-center justify-content-between gap-3">
        <a href="<?= e(url('index.php')) ?>" class="brand d-flex align-items-center text-decoration-none">
            <span class="brand-mark me-2"><i class="bi bi-droplet-half"></i></span>
            <span>
                <strong>SmartBlood</strong>
                <small class="d-block">Connect Platform</small>
            </span>
        </a>
        <div class="d-flex align-items-center gap-2">
            <?php if ($currentUser): ?>
                <span class="text-muted small d-none d-md-inline">Signed in as <?= e($currentUser['full_name']) ?> (<?= e(ucfirst($currentUser['role'])) ?>)</span>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url(role_home_path($currentUser['role']))) ?>">Dashboard</a>
                <a class="btn btn-sm btn-outline-danger" href="<?= e(url('logout.php')) ?>"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
            <?php else: ?>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('about.php')) ?>">About</a>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('contact.php')) ?>">Contact</a>
                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('login.php')) ?>">Login</a>
                <a class="btn btn-sm btn-danger" href="<?= e(url('register.php')) ?>">Register</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<div class="<?= $showSidebar ? 'app-shell' : '' ?>">
    <?php if ($showSidebar): ?>
        <?php include __DIR__ . '/sidebar.php'; ?>
    <?php endif; ?>
    <main class="<?= $showSidebar ? 'app-main' : 'public-main' ?>">
        <div class="<?= e($mainContainerClass) ?>">
            <?php foreach ($flashMessages as $flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show">
                    <?= e($flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endforeach; ?>
