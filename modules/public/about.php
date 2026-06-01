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
    <title>JDM Kenya | About</title>
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&family=Poppins:wght@400;500;600;700&family=Raleway:wght@500;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/JDM_kenya/assets/css/style.css">
    <link rel="icon" type="image/png" href="/JDM_kenya/images/jdm_logo.png">
</head>
<body>
<header id="header" class="header fixed-top">
    <div class="container-fluid container-xl position-relative d-flex align-items-center justify-content-between">
        <a href="index.php" class="logo d-flex align-items-center">
            <img src="/JDM_kenya/images/jdm_logo.png" alt="JDM Kenya Logo" class="logo-img" style="height: 40px; width: auto;">
            <h1 class="sitename ms-2">JDM Kenya</h1>
        </a>
        <nav id="navmenu" class="navmenu">
            <ul>
                <li><a href="index.php" class="<?= navActive('index.php') ?>">Home</a></li>
                <li><a href="about.php" class="<?= navActive('about.php') ?>">About</a></li>
                <li><a href="activities.php" class="<?= navActive('activities.php') ?>">Activities</a></li>
                <li><a href="resources.php" class="<?= navActive('resources.php') ?>">Resources</a></li>
                <li><a href="contact.php" class="<?= navActive('contact.php') ?>">Contact</a></li>
                <?php if ($userRole === 'admin'): ?>
                    <li><a href="admin_dashboard.php" class="btn btn-warning btn-sm px-3">Dashboard</a></li>
                <?php elseif ($userRole === 'member'): ?>
                    <li><a href="resources.php" class="btn btn-outline-light btn-sm px-3">Member Portal</a></li>
                <?php else: ?>
                    <li><a href="login.php" class="btn btn-outline-light btn-sm px-3">Sign In</a></li>
                <?php endif; ?>
            </ul>
            <i class="mobile-nav-toggle d-xl-none bi bi-list"></i>
        </nav>
    </div>
</header>

<!-- Hero Banner with Background -->
<section class="page-hero" style="background: linear-gradient(135deg, rgba(16,42,84,0.85) 0%, rgba(18,35,65,0.75) 100%), url('/JDM_kenya/images/hero-bg.jpeg') center/cover no-repeat fixed; height: 40vh;">
    <div class="container h-100 d-flex align-items-center justify-content-center">
        <div class="text-center text-white">
            <h1 class="display-4 fw-bold mb-2" style="text-shadow: 2px 2px 4px rgba(0,0,0,0.3);">About JDM Kenya</h1>
            <p class="lead" style="text-shadow: 1px 1px 2px rgba(0,0,0,0.3);">Our Mission, Values & Community</p>
        </div>
    </div>
</section>

<main style="padding-top: 60px;">
    <section class="container">
        <div class="row align-items-center gy-4">
            <div class="col-lg-6">
                <h2 class="section-title mb-4">Our Story</h2>
                <p class="lead text-muted">Jesus Disciple Movement of Kenya is a growing discipleship community focused on faith formation, outreach, and sustainable spiritual growth.</p>
                <p>We bring partners together through mentoring, prayer, mission training, and practical service. Our movement is designed to help believers disciple one another and bring transformation to their communities.</p>
                <a href="activities.php" class="btn btn-warning btn-lg mt-3">Explore Activities</a>
            </div>
            <div class="col-lg-6">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card value-card p-4 h-100">
                            <h5 class="mb-3">Discipleship</h5>
                            <p class="mb-0 text-secondary">Teaching believers to follow Jesus with confidence, compassion, and consistency.</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card value-card p-4 h-100">
                            <h5 class="mb-3">Community</h5>
                            <p class="mb-0 text-secondary">Creating strong, supportive spiritual relationships rooted in love and service.</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card value-card p-4 h-100">
                            <h5 class="mb-3">Evangelism</h5>
                            <p class="mb-0 text-secondary">We do campus outreach and evangelism to share the gospel with our community and students.</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card value-card p-4 h-100">
                            <h5 class="mb-3">World Mission</h5>
                            <p class="mb-0 text-secondary">We are committed to sharing the gospel and making disciples globally.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>
<footer class="footer bg-white border-top">
    <div class="container text-center">
        <p class="mb-1">&copy; <?= date('Y') ?> Jesus Disciple Movement of Kenya</p>
        <p class="text-muted mb-0">Building a discipleship movement with faith, clarity, and service.</p>
    </div>
</footer>
<a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>
<div id="preloader"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script src="/JDM_kenya/assets/js/ui_animations.js"></script>
</body>
</html>
