<?php
require_once dirname(__FILE__) . '/../../core/db_connect.php';
$userRole = $_SESSION['user_role'] ?? null;
$userName = $_SESSION['user_name'] ?? null;
$currentPage = basename($_SERVER['PHP_SELF']);
if (!function_exists('navActive')) {
    function navActive($page) {
        global $currentPage;
        return $currentPage === $page ? 'active' : '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JDM Kenya | Terms of Use</title>
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&family=Poppins:wght@400;500;600;700&family=Raleway:wght@500;700&family=Inter:wght@300;400;500;600;700;800;900&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css?v=<?= filemtime(dirname(dirname(__DIR__)) . '/assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/about.css?v=<?= filemtime(dirname(dirname(__DIR__)) . '/assets/css/about.css') ?>">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/animated-scroll.css?v=<?= filemtime(dirname(dirname(__DIR__)) . '/assets/css/animated-scroll.css') ?>">
    <link rel="icon" type="image/png" href="<?= BASE_PATH ?>/images/jdm_logo.png">
</head>
<body>

<div id="cursor"></div>
<div id="cursor-blur"></div>

<header id="header" class="header fixed-top">
    <div class="container-fluid container-xl position-relative d-flex align-items-center justify-content-between">
        <a href="index.php" class="logo d-flex align-items-center">
            <img src="<?= BASE_PATH ?>/images/jdm_logo.png" alt="JDM Kenya Logo" class="logo-img">
            <h1 class="sitename ms-2">JDM Kenya</h1>
        </a>
        <nav id="navmenu" class="navmenu">
            <ul>
                <li><a href="index.php" class="<?= navActive('index.php') ?>">Home</a></li>
                <li><a href="about.php" class="<?= navActive('about.php') ?>">About</a></li>
                <li><a href="activities.php" class="<?= navActive('activities.php') ?>">Events</a></li>
                <li><a href="resources.php" class="<?= navActive('resources.php') ?>">Resources</a></li>
                <li><a href="contact.php" class="<?= navActive('contact.php') ?>">Contact</a></li>
                <?php if ($userRole === 'admin' || $userRole === 'super_admin'): ?>
                    <li><a href="admin_dashboard.php" class="btn btn-warning btn-sm px-3">Dashboard</a></li>
                <?php elseif ($userRole === 'member'): ?>
                    <li><a href="member_dashboard.php" class="btn btn-outline-light btn-sm px-3">Member Portal</a></li>
                <?php else: ?>
                    <li><a href="login.php" class="btn btn-outline-light btn-sm px-3">Sign In</a></li>
                <?php endif; ?>
            </ul>
            <button class="hamburger d-xl-none" type="button" aria-label="Toggle mobile menu">
                <span class="bar"></span>
            </button>
        </nav>
    </div>
</header>

<nav class="mobile-nav" aria-label="Mobile navigation">
    <a href="index.php" class="<?= navActive('index.php') ?>">Home</a>
    <a href="about.php" class="<?= navActive('about.php') ?>">About</a>
    <a href="activities.php" class="<?= navActive('activities.php') ?>">Events</a>
    <a href="resources.php" class="<?= navActive('resources.php') ?>">Resources</a>
    <a href="contact.php" class="<?= navActive('contact.php') ?>">Contact</a>
    <?php if ($userRole === 'admin' || $userRole === 'super_admin'): ?>
        <a href="admin_dashboard.php" class="btn btn-warning btn-sm px-3">Dashboard</a>
    <?php elseif ($userRole === 'member'): ?>
        <a href="member_dashboard.php" class="btn btn-outline-light btn-sm px-3">Member Portal</a>
    <?php else: ?>
        <a href="login.php" class="btn btn-outline-light btn-sm px-3">Sign In</a>
    <?php endif; ?>
</nav>

<div id="smooth-wrapper">
<div id="smooth-content">

<section class="page-hero about-hero tall">
    <div class="container h-100 d-flex align-items-center justify-content-center">
        <div class="text-center text-white">
            <h1 class="display-4 fw-bold">Terms of Use</h1>
            <p class="lead mt-3">Please review these terms before using JDM Kenya. They describe how the site works and what is expected from each member.</p>
            <div class="mt-4">
                <a href="contact.php" class="btn btn-warning btn-lg me-2 fw-bold">Contact Us</a>
                <a href="login.php" class="btn btn-outline-light btn-lg fw-bold">Sign In</a>
            </div>
        </div>
    </div>
</section>

<section class="as-section" style="background: var(--as-bg); color: #fff;">
  <div class="container-fluid">
    <div class="as-section-header text-center">
      <span class="as-section-tag">Terms</span>
      <h2 class="as-section-title">Use JDM Kenya with trust, care, and respect</h2>
      <p class="as-section-desc mx-auto">These terms explain membership requirements, account security, permitted behaviour, and how we protect the community.</p>
    </div>

    <div class="row g-4 mt-4">
      <div class="col-lg-6">
        <div class="card h-100 shadow-sm border-0 bg-white p-4">
          <h3 class="h5 text-dark mb-3">1. Membership and access</h3>
          <p class="text-secondary">JDM Kenya is intended for JDM members and approved participants. To access member-only pages, you must register with truthful information and maintain a valid login.</p>
          <p class="text-secondary mb-0">The site reserves the right to approve, suspend, or remove accounts if the terms are violated.</p>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card h-100 shadow-sm border-0 bg-white p-4">
          <h3 class="h5 text-dark mb-3">2. Account security</h3>
          <p class="text-secondary">You are responsible for your username and password. Keep them private and do not share them with others.</p>
          <p class="text-secondary mb-0">If your login details are lost or stolen, contact support immediately so we can protect your account.</p>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card h-100 shadow-sm border-0 bg-white p-4">
          <h3 class="h5 text-dark mb-3">3. Community behaviour</h3>
          <p class="text-secondary">Use the website for fellowship, prayer, ministry, and announcements. Do not post offensive, abusive, or illegal content.</p>
          <p class="text-secondary mb-0">Respect other members and use chat, comments, and profiles kindly and responsibly.</p>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card h-100 shadow-sm border-0 bg-white p-4">
          <h3 class="h5 text-dark mb-3">4. Content ownership</h3>
          <p class="text-secondary">JDM Kenya owns the website and platform. You retain ownership of personal content you post, while granting the site permission to display it within the community.</p>
          <p class="text-secondary mb-0">The site may use your contributions to improve service quality, support community activity, and manage membership.</p>
        </div>
      </div>
    </div>

    <div class="row g-4 mt-4">
      <div class="col-lg-6">
        <div class="card h-100 shadow-sm border-0 bg-white p-4">
          <h3 class="h5 text-dark mb-3">5. Privacy and data protection</h3>
          <p class="text-secondary">Your personal details are managed according to our Privacy Policy. We do not sell your personal information or share it with outside parties without permission.</p>
          <p class="text-secondary mb-0">Limited sharing may occur with authorized staff for site management and support.</p>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card h-100 shadow-sm border-0 bg-white p-4">
          <h3 class="h5 text-dark mb-3">6. Changes and updates</h3>
          <p class="text-secondary">JDM Kenya may update these terms as the community grows. The latest published version is binding.</p>
          <p class="text-secondary mb-0">If you continue using the site after a change, it means you accept the new terms.</p>
        </div>
      </div>
    </div>

    <div class="row mt-5">
      <div class="col-12">
        <div class="card border-0 bg-primary text-white p-4 shadow-sm">
          <h3 class="h5 mb-3">Contact and support</h3>
          <p class="mb-0">If you have questions about these terms or need help with your account, please <a href="contact.php" class="text-white text-decoration-underline">contact us</a>.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/footer.php'; ?>
