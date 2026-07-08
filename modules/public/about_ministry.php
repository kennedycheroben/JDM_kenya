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

// Custom page content wrapper function to avoid duplication
if (!function_exists('getAboutSubMenu')) {
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
}

ob_start();
?>

<div class="about-ministry-container">
    <!-- Sub-navigation Pills -->
    <?= getAboutSubMenu() ?>

    <!-- Vision & History Section -->
    <div class="row mb-5 align-items-stretch">
        <div class="col-lg-7 mb-4 mb-lg-0">
            <div class="card vision-card p-4 h-100 d-flex flex-column justify-content-center">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-warning text-dark rounded-circle p-3 d-inline-flex me-3">
                        <i class="bi bi-eye-fill fs-3"></i>
                    </div>
                    <div>
                        <small class="text-warning text-uppercase fw-bold tracking-wider">Our Ultimate Goal</small>
                        <h3 class="fw-bold mb-0 text-white">The JDM Vision</h3>
                    </div>
                </div>
                <h4 class="display-6 fw-bold my-3 text-warning">"Let's make disciples of all nations!"</h4>
                <p class="lead text-white-50">Matthew 28:19 forms the core heartbeat of the Jesus Disciple Movement. We are actively engaged in multiplying disciples, building spiritual leaders, and expanding the Kingdom of God across campuses and communities globally.</p>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card p-4 h-100 shadow-sm border-0 bg-white">
                <h4 class="fw-bold mb-3 text-primary"><i class="bi bi-globe2 me-2 text-warning"></i>Global Heritage</h4>
                <p class="text-secondary">JDM started in South Korea in 1977 with a fiery vision to make disciples of all nations. For nearly five decades, JDM has expanded globally, establishing hubs across Asia, Europe, the Americas, and Africa—committed to raising dedicated leaders who live for world evangelization.</p>
                <div class="d-flex align-items-center mt-3 bg-light p-3 rounded">
                    <i class="bi bi-calendar3 text-success fs-3 me-3"></i>
                    <div>
                        <div class="fw-bold text-dark">Founded in 1977</div>
                        <small class="text-muted">Over 48 years of discipleship training</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Principles Section -->
    <div class="mb-5">
        <div class="text-center mb-4">
            <span class="badge bg-success-subtle text-success px-3 py-2 rounded-pill fw-bold text-uppercase mb-2">Our Foundation</span>
            <h2 class="fw-bold">Three Main Principles</h2>
            <p class="text-muted col-lg-8 mx-auto">Jesus Disciple Movement achieves the evangelism ministry of this age by focusing on these three core pillars of faith and practice.</p>
        </div>

        <div class="row g-4">
            <!-- Evangelism -->
            <div class="col-md-4">
                <div class="card principle-card p-4 h-100 bg-white">
                    <div class="text-danger mb-3">
                        <i class="bi bi-megaphone-fill fs-1"></i>
                    </div>
                    <h5 class="fw-bold mb-2">1. Evangelism</h5>
                    <p class="text-secondary mb-0">We lead the lost souls to the Lord by delivering them the new life in Jesus Christ and live as witnesses of the Gospel to the end of the earth.</p>
                </div>
            </div>
            <!-- Discipleship -->
            <div class="col-md-4">
                <div class="card principle-card p-4 h-100 bg-white">
                    <div class="text-primary mb-3">
                        <i class="bi bi-people-fill fs-1"></i>
                    </div>
                    <h5 class="fw-bold mb-2">2. Discipleship</h5>
                    <p class="text-secondary mb-0">Through strong discipleship training and following up, we help the people continue to fully live in the Holy Spirit and to grow up to deny themselves and devote themselves to the Lord Jesus.</p>
                </div>
            </div>
            <!-- World Mission -->
            <div class="col-md-4">
                <div class="card principle-card p-4 h-100 bg-white">
                    <div class="text-warning mb-3">
                        <i class="bi bi-globe fs-1"></i>
                    </div>
                    <h5 class="fw-bold mb-2">3. World Mission</h5>
                    <p class="text-secondary mb-0">We deliver the Gospel to everyone beyond the boundaries of region, tribe and culture so that they may be saved as people of God.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Characteristics Section -->
    <div class="mb-5">
        <div class="text-center mb-4">
            <span class="badge bg-primary-subtle text-primary px-3 py-2 rounded-pill fw-bold text-uppercase mb-2">Our Signature</span>
            <h2 class="fw-bold">Three Main Characteristics</h2>
            <p class="text-muted col-lg-8 mx-auto">JDM concentrates on expanding God's Kingdom throughout the world through Biblical Bible study, Christian community and discipleship.</p>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="card char-card p-4 h-100 bg-white border-top-0 border-end-0 border-bottom-0">
                    <h5 class="fw-bold text-primary mb-3"><i class="bi bi-book-half me-2"></i>Biblical Bible Study</h5>
                    <p class="text-secondary small mb-0">Biblical Bible study is a kind of JDM's basis because it plays an important role as an axis while the others play a role of two wheels. We can also say that the two storeys house built with Christian community and discipleship is based on the Biblical Bible study.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card char-card p-4 h-100 bg-white border-top-0 border-end-0 border-bottom-0 border-left-gold">
                    <h5 class="fw-bold text-warning mb-3"><i class="bi bi-houses-fill me-2"></i>Christian Community</h5>
                    <p class="text-secondary small mb-0">A Christian community means an expanded Christian family who live together overcoming all kinds of difficulties in faith. JDM is also a community for the ministries as well as a Christian community. Among these two, only Christian community can be one of JDM's characteristics because it is essential and fundamental.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card char-card p-4 h-100 bg-white border-top-0 border-end-0 border-bottom-0 border-left-navy">
                    <h5 class="fw-bold text-dark mb-3"><i class="bi bi-person-check-fill me-2"></i>Discipleship Focus</h5>
                    <p class="text-secondary small mb-0">JDM doesn't focus on increasing the numbers of its members or financial benefits like a commercial company, but JDM takes making disciples as its crucial hope and goal. We want to carry out discipleship training according to each nation's context so that the Biblical life style of Jesus may be applied to their own context.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Interactive Tabs for Ministries & Groups -->
    <div class="mb-5 bg-white p-4 rounded shadow-sm border border-light-subtle">
        <ul class="nav nav-tabs justify-content-center mb-4 gap-2 border-0" id="ministryTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link tab-btn active border shadow-sm" id="ministries-tab" data-bs-toggle="tab" data-bs-target="#ministries" type="button" role="tab" aria-controls="ministries" aria-selected="true">
                    <i class="bi bi-layers-half me-2"></i>Various Ministries (10)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link tab-btn border shadow-sm" id="groups-tab" data-bs-toggle="tab" data-bs-target="#groups" type="button" role="tab" aria-controls="groups" aria-selected="false">
                    <i class="bi bi-people-fill me-2"></i>Group Guidance (6)
                </button>
            </li>
        </ul>

        <div class="tab-content" id="ministryTabsContent">
            <!-- Ministries Tab Content -->
            <div class="tab-pane fade show active" id="ministries" role="tabpanel" aria-labelledby="ministries-tab">
                <img src="<?= BASE_PATH ?>/images/african-students.jpg" alt="African youth campus ministry" class="img-fluid rounded-3 mb-4" style="max-height: 300px; width: 100%; object-fit: cover; object-position: center 30%;">
                <div class="row g-3">
                    <!-- Teen Ministry -->
                    <div class="col-lg-6">
                        <div class="ministry-card p-3 h-100">
                            <div class="d-flex align-items-start">
                                <div class="badge bg-danger p-2 me-3 mt-1"><i class="bi bi-emoji-smile fs-5"></i></div>
                                <div>
                                    <h6 class="fw-bold mb-1">Teen Ministry</h6>
                                    <p class="text-secondary small mb-0">Bringing up teen disciples who pursue Christ-likeness and restore the self-image in God. We teach them to be the future leaders of God's ministry.</p>
                                    <a href="<?= BASE_PATH ?>/sports.php" class="btn btn-sm btn-success mt-2"><i class="bi bi-trophy"></i> Sports Ministry</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Campus Ministry -->
                    <div class="col-lg-6">
                        <div class="ministry-card p-3 h-100">
                            <div class="d-flex align-items-start">
                                <div class="badge bg-primary p-2 me-3 mt-1"><i class="bi bi-mortarboard fs-5"></i></div>
                                <div>
                                    <h6 class="fw-bold mb-1">Campus Ministry</h6>
                                    <p class="text-secondary small mb-0">Bringing up "Salt and Light" of the era who can create new campus culture by pursuing Christ-likeness. Campus ministry has been the key for world evangelization.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Job Ministry -->
                    <div class="col-lg-6">
                        <div class="ministry-card p-3 h-100">
                            <div class="d-flex align-items-start">
                                <div class="badge bg-success p-2 me-3 mt-1"><i class="bi bi-briefcase fs-5"></i></div>
                                <div>
                                    <h6 class="fw-bold mb-1">Job Ministry</h6>
                                    <p class="text-secondary small mb-0">Focuses on bringing up lay believers to have biblical views on their jobs and carry great influence on non-believers in working places.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- World Mission Ministry -->
                    <div class="col-lg-6">
                        <div class="ministry-card p-3 h-100">
                            <div class="d-flex align-items-start">
                                <div class="badge bg-warning text-dark p-2 me-3 mt-1"><i class="bi bi-send fs-5"></i></div>
                                <div>
                                    <h6 class="fw-bold mb-1">World Mission Ministry</h6>
                                    <p class="text-secondary small mb-0">Winning all people and nations for the Lord by making them disciples. We recruit, train, send, care, provide data, and educate missionaries.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Family Ministry -->
                    <div class="col-lg-6">
                        <div class="ministry-card p-3 h-100">
                            <div class="d-flex align-items-start">
                                <div class="badge bg-info p-2 me-3 mt-1"><i class="bi bi-heart-fill fs-5"></i></div>
                                <div>
                                    <h6 class="fw-bold mb-1">Family Ministry</h6>
                                    <p class="text-secondary small mb-0">Teaching families to make a holy family where Christ is the Lord. Helps restore true love and authority by teaching healthy family models.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Community Ministry -->
                    <div class="col-lg-6">
                        <div class="ministry-card p-3 h-100">
                            <div class="d-flex align-items-start">
                                <div class="badge bg-secondary p-2 me-3 mt-1"><i class="bi bi-house-heart fs-5"></i></div>
                                <div>
                                    <h6 class="fw-bold mb-1">Community Ministry</h6>
                                    <p class="text-secondary small mb-0">Straightening the body of Christ through the life following the Early Christian Community. We encourage community-centered training programs.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Inter Church Ministry -->
                    <div class="col-lg-6">
                        <div class="ministry-card p-3 h-100">
                            <div class="d-flex align-items-start">
                                <div class="badge bg-dark p-2 me-3 mt-1"><i class="bi bi-shuffle fs-5"></i></div>
                                <div>
                                    <h6 class="fw-bold mb-1">Inter Church Ministry</h6>
                                    <p class="text-secondary small mb-0">Working cooperatively with local churches to build the kingdom of God by exchanging news, support, and ideas across locations.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- WDTI -->
                    <div class="col-lg-6">
                        <div class="ministry-card p-3 h-100">
                            <div class="d-flex align-items-start">
                                <div class="badge bg-purple p-2 me-3 mt-1"><i class="bi bi-award-fill fs-5"></i></div>
                                <div>
                                    <h6 class="fw-bold mb-1">WDTI (World Disciple Training Institute)</h6>
                                    <p class="text-secondary small mb-0">Nurturing spiritual leaders for the 21st Century. Trainees live in community, attend lectures, evangelize, and engage in physical labor.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- TTS -->
                    <div class="col-lg-6">
                        <div class="ministry-card p-3 h-100">
                            <div class="d-flex align-items-start">
                                <div class="badge bg-success p-2 me-3 mt-1"><i class="bi bi-shield-shaded fs-5"></i></div>
                                <div>
                                    <h6 class="fw-bold mb-1">TTS (Timothy Training School)</h6>
                                    <p class="text-secondary small mb-0">A school open to everyone with a vision for making disciples. Concentrated training covers Bible study, Theology, Discipleship, and Leadership.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Publisher -->
                    <div class="col-lg-6">
                        <div class="ministry-card p-3 h-100">
                            <div class="d-flex align-items-start">
                                <div class="badge bg-dark p-2 me-3 mt-1"><i class="bi bi-journal-bookmark fs-5"></i></div>
                                <div>
                                    <h6 class="fw-bold mb-1">Shepherd and Disciple Publisher</h6>
                                    <p class="text-secondary small mb-0">Consolidating biblical values through literature. Publishes evangelical books, Bible study textbooks, and small books to build theology and faith.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Groups Tab Content -->
            <div class="tab-pane fade" id="groups" role="tabpanel" aria-labelledby="groups-tab">
                <div class="row g-3">
                    <!-- Teenage Group -->
                    <div class="col-md-6 col-lg-4">
                        <div class="card p-3 h-100 border border-light-subtle bg-light shadow-sm">
                            <h6 class="fw-bold text-danger"><i class="bi bi-hash me-2"></i>Teenage Group</h6>
                            <p class="small text-secondary mb-0">Meetings for Junior and Senior high students. Holds periodical chapels, dance/music contests, retreats, and spiritual growth events.</p>
                        </div>
                    </div>
                    <!-- Campus Group -->
                    <div class="col-md-6 col-lg-4">
                        <div class="card p-3 h-100 border border-light-subtle bg-light shadow-sm">
                            <h6 class="fw-bold text-primary"><i class="bi bi-mortarboard-fill me-2"></i>Campus Group</h6>
                            <p class="small text-secondary mb-0">Training university students to be soldiers of the Lord on campus through Quiet Time groups, small Bible studies, one-to-one mentorship, and conventions.</p>
                        </div>
                    </div>
                    <!-- Vacation Group -->
                    <div class="col-md-6 col-lg-4">
                        <div class="card p-3 h-100 border border-light-subtle bg-light shadow-sm">
                            <h6 class="fw-bold text-success"><i class="bi bi-sun-fill me-2"></i>Vacation Group</h6>
                            <p class="small text-secondary mb-0">Provides small groups and seminars based on jobs, ages, and locations to train interested lay believers to become active spiritual leaders.</p>
                        </div>
                    </div>
                    <!-- Family Group -->
                    <div class="col-md-6 col-lg-4">
                        <div class="card p-3 h-100 border border-light-subtle bg-light shadow-sm">
                            <h6 class="fw-bold text-info"><i class="bi bi-people-fill me-2"></i>Family Group</h6>
                            <p class="small text-secondary mb-0">Serving families to build healthy lives through small groups of fathers, mothers, husbands, wives, and targeted family seminars.</p>
                        </div>
                    </div>
                    <!-- Leader Group -->
                    <div class="col-md-6 col-lg-4">
                        <div class="card p-3 h-100 border border-light-subtle bg-light shadow-sm">
                            <h6 class="fw-bold text-warning text-gold mb-2"><i class="bi bi-person-fill-gear me-2"></i>Leader Group</h6>
                            <p class="small text-secondary mb-0">Concentrated training during set periods supporting future leaders to learn practical, biblical ways of working as spiritual shepherds.</p>
                        </div>
                    </div>
                    <!-- Mission Group -->
                    <div class="col-md-6 col-lg-4">
                        <div class="card p-3 h-100 border border-light-subtle bg-light shadow-sm">
                            <h6 class="fw-bold text-dark"><i class="bi bi-airplane-engines me-2"></i>Mission Group</h6>
                            <p class="small text-secondary mb-0">Encouraging those interested in missions to participate in mission seminars, camps, and the International Missionary Training Institute.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$page_title = "What Is JDM - JDM Kenya";

if ($isMemberPortal) {
    include dirname(__FILE__) . '/../portal/layout.php';
} else {
    // Public layout rendering
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($page_title) ?></title>
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

    <!-- Hero Banner -->
    <section class="page-hero about-hero">
        <div class="container h-100 d-flex align-items-center justify-content-center">
            <div class="text-center text-white">
                <h1 class="display-5 fw-bold mb-2">What Is JDM?</h1>
                <p class="lead">Our Vision, Pillars, Ministries & Guidance</p>
            </div>
        </div>
    </section>

    <main class="container my-5 about-main">
        <?= $content ?>
    </main>

    <?php include __DIR__ . '/footer.php'; ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_PATH ?>/assets/js/ui_animations.js"></script>
    </body>
    </html>
    <?php
}
?>
