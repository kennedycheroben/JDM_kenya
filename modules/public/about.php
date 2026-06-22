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

$isMemberPortal = !empty($userRole);

function getAboutSubMenu() {
    return '
    <div class="row mb-4">
        <div class="col-12">
            <ul class="nav nav-pills justify-content-center justify-content-lg-start gap-2 p-2 bg-white rounded shadow-sm border border-light-subtle">
                <li class="nav-item">
                    <a class="btn-neon about-nav-link ' . navActive('about.php') . ' px-3 py-2 fw-semibold rounded" href="about.php">
                        <i class="bi bi-info-circle me-1"></i> Story & Overview
                    </a>
                </li>
                <li class="nav-item">
                    <a class="btn-neon about-nav-link ' . navActive('about_ministry.php') . ' px-3 py-2 fw-semibold rounded" href="about_ministry.php">
                        <i class="bi bi-compass me-1"></i> What Is JDM
                    </a>
                </li>
                <li class="nav-item">
                    <a class="btn-neon about-nav-link ' . navActive('about_history_spirit.php') . ' px-3 py-2 fw-semibold rounded" href="about_history_spirit.php">
                        <i class="bi bi-clock-history me-1"></i> History & Spirit
                    </a>
                </li>
                <li class="nav-item">
                    <a class="btn-neon about-nav-link ' . navActive('about_inner_feature.php') . ' px-3 py-2 fw-semibold rounded" href="about_inner_feature.php">
                        <i class="bi bi-door-open me-1"></i> Inner Features
                    </a>
                </li>
                <li class="nav-item">
                    <a class="btn-neon about-nav-link ' . navActive('about_outer_feature.php') . ' px-3 py-2 fw-semibold rounded" href="about_outer_feature.php">
                        <i class="bi bi-globe me-1"></i> Outer Features
                    </a>
                </li>
            </ul>
        </div>
    </div>';
}

function getSubpagesGrid() {
    return '
    <div class="row g-4 mt-2">
        <!-- What is JDM Card -->
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 shadow-sm border-0 bg-white p-3 hover-card">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-warning-subtle text-warning rounded p-2 me-3">
                        <i class="bi bi-compass fs-4"></i>
                    </div>
                    <h5 class="fw-bold mb-0 text-dark">What Is JDM</h5>
                </div>
                <p class="text-secondary small flex-grow-1">
                    Explore JDM\'s core vision, foundational principles, characteristics, various ministries, and group guidances.
                </p>
                <a href="about_ministry.php" class="btn btn-neon w-100 fw-bold mt-2">Explore Pillars</a>
            </div>
        </div>

        <!-- History & Spirit Card -->
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 shadow-sm border-0 bg-white p-3 hover-card">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-success-subtle text-success rounded p-2 me-3">
                        <i class="bi bi-clock-history fs-4"></i>
                    </div>
                    <h5 class="fw-bold mb-0 text-dark">History & Spirit</h5>
                </div>
                <p class="text-secondary small flex-grow-1">
                    Follow the JDM historical timeline since 1977 and learn about the five core spirits driving our mission.
                </p>
                <a href="about_history_spirit.php" class="btn btn-neon w-100 fw-bold mt-2">Read Our Story</a>
            </div>
        </div>

        <!-- Inner Features Card -->
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 shadow-sm border-0 bg-white p-3 hover-card">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-primary-subtle text-primary rounded p-2 me-3">
                        <i class="bi bi-door-open fs-4"></i>
                    </div>
                    <h5 class="fw-bold mb-0 text-dark">Inner Features</h5>
                </div>
                <p class="text-secondary small flex-grow-1">
                    Discover our spiritual heartbeat centered on Word, Prayer, Fellowship, and Evangelism communities.
                </p>
                <a href="about_inner_feature.php" class="btn btn-neon w-100 fw-bold mt-2">Discover Communities</a>
            </div>
        </div>

        <!-- Outer Features Card -->
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 shadow-sm border-0 bg-white p-3 hover-card">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-info-subtle text-info rounded p-2 me-3">
                        <i class="bi bi-globe fs-4"></i>
                    </div>
                    <h5 class="fw-bold mb-0 text-dark">Outer Features</h5>
                </div>
                <p class="text-secondary small flex-grow-1">
                    See how our mission translates to active movements: Youth, Lay, Discipleship, and World Missions.
                </p>
                <a href="about_outer_feature.php" class="btn btn-neon w-100 fw-bold mt-2">See Movements</a>
            </div>
        </div>
    </div>';
}
?>

<?php if ($isMemberPortal): ?>
<?php
    ob_start();
?>
<div class="row mb-4">
    <div class="col-12">
        <h2><i class="bi bi-info-circle-fill text-primary"></i> About JDM Kenya</h2>
        <p class="text-muted mb-0">Learn more about our story, mission, and core values.</p>
    </div>
</div>

<!-- Sub-navigation Pills -->
<?= getAboutSubMenu() ?>

<div class="row align-items-center gy-4">
    <div class="col-lg-6">
        <h4 class="fw-bold mb-3 text-primary">Our Story</h4>
        <p class="lead text-muted">Jesus Disciple Movement of Kenya is a growing discipleship community focused on Campus ministry, Evangelism, and Discipleship.</p>
        <p class="text-secondary">We bring believers together through mentoring, prayer, training (e.g. TTS), and practical service. Our movement is designed to help believers disciple one another and bring transformation to their campuses and communities.</p>
        <div class="d-flex gap-2 mt-3">
            <a href="activities.php" class="btn btn-warning fw-bold">Explore Activities</a>
            <a href="contact.php" class="btn btn-outline-primary fw-bold">Contact Us</a>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="card value-card p-3 h-100 shadow-sm bg-white">
                    <h6 class="fw-bold text-primary mb-2"><i class="bi bi-people-fill me-1"></i>Discipleship</h6>
                    <p class="small mb-0 text-secondary">Teaching believers to follow Jesus with confidence, compassion, and consistency.</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card value-card p-3 h-100 shadow-sm bg-white">
                    <h6 class="fw-bold text-success mb-2"><i class="bi bi-houses-fill me-1"></i>Community</h6>
                    <p class="small mb-0 text-secondary">Creating strong, supportive spiritual relationships rooted in love and service.</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card value-card p-3 h-100 shadow-sm bg-white">
                    <h6 class="fw-bold text-info mb-2"><i class="bi bi-megaphone-fill me-1"></i>Evangelism</h6>
                    <p class="small mb-0 text-secondary">We do campus outreach and evangelism to share the gospel with our community and students.</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card value-card p-3 h-100 shadow-sm bg-white">
                    <h6 class="fw-bold text-warning mb-2"><i class="bi bi-globe me-1"></i>World Mission</h6>
                    <p class="small mb-0 text-secondary">We are committed to sharing the gospel and making disciples globally.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<hr class="my-5 opacity-75">

<div class="row mb-3">
    <div class="col-12 text-center text-lg-start">
        <h4 class="fw-bold text-primary mb-2"><i class="bi bi-layers-half me-2"></i>Explore JDM Pillars & Spirit</h4>
        <p class="text-muted small">Select a category below to view detailed teachings, histories, and features.</p>
    </div>
</div>

<?= getSubpagesGrid() ?>

<?php
    $content = ob_get_clean();
    $page_title = "About Us - JDM Kenya";
    include dirname(__FILE__) . '/../portal/layout.php';
?>
<?php else: ?>
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
    <link rel="stylesheet" href="/JDM_kenya/assets/css/style.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/JDM_kenya/assets/css/style.css') ?>">
    <link rel="stylesheet" href="/JDM_kenya/assets/css/about.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/JDM_kenya/assets/css/about.css') ?>">
    <link rel="icon" type="image/png" href="/JDM_kenya/images/jdm_logo.png">
</head>
<body>
<header id="header" class="header fixed-top">
    <div class="container-fluid container-xl position-relative d-flex align-items-center justify-content-between">
        <a href="index.php" class="logo d-flex align-items-center">
            <img src="/JDM_kenya/images/jdm_logo.png" alt="JDM Kenya Logo" class="logo-img">
            <h1 class="sitename ms-2">JDM Kenya</h1>
        </a>
        <nav id="navmenu" class="navmenu">
            <ul>
                <li><a href="index.php" class="<?= navActive('index.php') ?>">Home</a></li>
                <li><a href="about.php" class="<?= navActive('about.php') ?>">About</a></li>
                <li><a href="activities.php" class="<?= navActive('activities.php') ?>">Activities</a></li>
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
            <i class="mobile-nav-toggle d-xl-none bi bi-list"></i>
        </nav>
    </div>
</header>

<!-- Hero Banner with Background -->
<section class="page-hero about-hero tall">
    <div class="container h-100 d-flex align-items-center justify-content-center">
        <div class="text-center text-white">
            <h1 class="display-4 fw-bold mb-2">About JDM Kenya</h1>
            <p class="lead">Our Mission, Values & Community</p>
        </div>
    </div>
</section>

<main class="about-main tall">
    <section class="container">
        <!-- Sub-navigation Pills -->
        <?= getAboutSubMenu() ?>

        <div class="row align-items-center gy-4 mt-2">
            <div class="col-lg-6">
                <h2 class="section-title mb-4">Our Story</h2>
                <p class="lead text-muted">Jesus Disciple Movement of Kenya is a growing discipleship community focused on Campus ministry, Evangelism, and Discipleship.</p>
                <p>We bring believers together through mentoring, prayer, training (e.g. TTS), and practical service. Our movement is designed to help believers disciple one another and bring transformation to their communities.</p>
                <a href="activities.php" class="btn btn-warning btn-lg mt-3">Explore Activities</a>
            </div>
            <div class="col-lg-6">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card value-card p-4 h-100 bg-white">
                            <h5 class="mb-3 text-primary"><i class="bi bi-people-fill me-2"></i>Discipleship</h5>
                            <p class="mb-0 text-secondary">Teaching believers to follow Jesus with confidence, compassion, and consistency.</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card value-card p-4 h-100 bg-white">
                            <h5 class="mb-3 text-success"><i class="bi bi-houses-fill me-2"></i>Community</h5>
                            <p class="mb-0 text-secondary">Creating strong, supportive spiritual relationships rooted in love and service.</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card value-card p-4 h-100 bg-white">
                            <h5 class="mb-3 text-info"><i class="bi bi-megaphone-fill me-2"></i>Evangelism</h5>
                            <p class="mb-0 text-secondary">We do campus outreach and evangelism to share the gospel with our community and students.</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card value-card p-4 h-100 bg-white">
                            <h5 class="mb-3 text-warning"><i class="bi bi-globe me-2"></i>World Mission</h5>
                            <p class="mb-0 text-secondary">We are committed to sharing the gospel and making disciples globally.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <hr class="my-5 opacity-75">

        <div class="row mb-3">
            <div class="col-12 text-center text-lg-start">
                <h3 class="fw-bold text-primary mb-2"><i class="bi bi-layers-half me-2"></i>Explore JDM Pillars & Spirit</h3>
                <p class="text-muted">Select a category below to explore teachings, timelines, and details about JDM.</p>
            </div>
        </div>

        <?= getSubpagesGrid() ?>
    </section>
</main>
<footer class="footer bg-white border-top">
    <div class="container text-center">
        <p class="mb-1">&copy; <?= date('Y') ?> Jesus Disciple Movement of Kenya</p>
        <p class="text-muted mb-0">Building a discipleship movement with faith, clarity, and service.</p>
        <div class="d-flex justify-content-center gap-3 mt-3">
            <a href="https://www.facebook.com/share/1DuVRA4Qph/" target="_blank" class="text-secondary hover-text-primary" title="Facebook"><i class="bi bi-facebook fs-5"></i></a>
            <a href="#" target="_blank" class="text-secondary hover-text-danger" title="Instagram"><i class="bi bi-instagram fs-5"></i></a>
            <a href="https://vm.tiktok.com/ZS9jGEtHaDFBC-dncuF/" target="_blank" class="text-secondary hover-text-dark" title="TikTok"><i class="bi bi-tiktok fs-5"></i></a>
        </div>
    </div>
</footer>
<a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>
<div id="preloader"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script src="/JDM_kenya/assets/js/ui_animations.js"></script>
</body>
</html>
<?php endif; ?>
