<?php
require_once dirname(__FILE__) . '/../../core/db_connect.php';
$userRole = $_SESSION['user_role'] ?? null;
$userName = $_SESSION['user_name'] ?? null;
$currentPage = basename($_SERVER['PHP_SELF']);
function navActive($page) {
    global $currentPage;
    return $currentPage === $page ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JDM Kenya | Jesus Disciple Movement</title>
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&family=Poppins:wght@400;500;600;700&family=Raleway:wght@500;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css?v=<?= filemtime(dirname(dirname(__DIR__)) . '/assets/css/style.css') ?>">
    <link rel="icon" type="image/png" href="<?= BASE_PATH ?>/images/jdm_logo.png">
</head>
<body>
<header id="header" class="header fixed-top">
    <div class="container-fluid container-xl position-relative d-flex align-items-center justify-content-between">
        <a href="<?= BASE_PATH ?>/index.php" class="logo d-flex align-items-center">
            <h1 class="sitename">JDM Kenya</h1>
        </a>
        <nav id="navmenu" class="navmenu">
            <ul>
                <li><a href="<?= BASE_PATH ?>/index.php" class="<?= navActive('index.php') ?>">Home</a></li>
                <li><a href="<?= BASE_PATH ?>/about.php" class="<?= navActive('about.php') ?>">About</a></li>
                <li><a href="<?= BASE_PATH ?>/activities.php" class="<?= navActive('activities.php') ?>">Activities</a></li>
                <li><a href="<?= BASE_PATH ?>/sports.php" class="<?= navActive('sports.php') ?>">Sports</a></li>
                <li><a href="<?= BASE_PATH ?>/resources.php" class="<?= navActive('resources.php') ?>">Resources</a></li>
                <li><a href="<?= BASE_PATH ?>/contact.php" class="<?= navActive('contact.php') ?>">Contact</a></li>
                <?php if ($userRole === 'admin' || $userRole === 'super_admin'): ?>
                    <li><a href="<?= BASE_PATH ?>/admin_dashboard.php" class="btn btn-warning btn-sm px-3">Dashboard</a></li>
                <?php elseif ($userRole === 'member'): ?>
                    <li><a href="<?= BASE_PATH ?>/member_dashboard.php" class="btn btn-warning btn-sm px-3">Member Portal</a></li>
                <?php else: ?>
                    <li><a href="<?= BASE_PATH ?>/login.php" class="btn btn-warning btn-sm px-3">Sign In</a></li>
                <?php endif; ?>
            </ul>
            <i class="mobile-nav-toggle d-xl-none bi bi-list"></i>
        </nav>
    </div>
</header>
