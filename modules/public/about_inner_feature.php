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
<div class="about-inner-feature-container">
    <!-- Sub-navigation Pills -->
    <?= getAboutSubMenu() ?>

    <div class="row gy-5 align-items-center">
        <!-- Interactive Chart Visualizer -->
        <div class="col-lg-5 text-center">
            <div class="p-3 mb-4 bg-white rounded shadow-sm border border-light-subtle">
                <span class="badge bg-success-subtle text-success px-3 py-2 rounded-pill fw-bold text-uppercase mb-2">Inner Structure</span>
                <h3 class="fw-bold text-primary mb-2">The Fellowship Cross</h3>
                <p class="text-secondary small mb-0">Click any community node to highlight its details and scriptural references below.</p>
            </div>
            
            <div class="py-4 position-relative">
                <div class="cross-diagram-container">
                    <!-- Top node: Word -->
                    <div class="cross-node node-top btn-neon" onclick="highlightSection('word')">
                        <i class="bi bi-book-half text-primary fs-3 mb-2"></i>
                        <span class="fw-bold small text-dark">God's Word</span>
                    </div>
                    
                    <!-- Left node: Fellowship -->
                    <div class="cross-node node-left btn-neon" onclick="highlightSection('fellowship')">
                        <i class="bi bi-heart-fill text-orange fs-3 mb-2"></i>
                        <span class="fw-bold small text-dark">Fellowship</span>
                    </div>
                    
                    <!-- Center Hub -->
                    <div class="cross-node node-center btn-neon " onclick="highlightSection('center')">
                        <img src="<?= BASE_PATH ?>/images/jdm_logo.png" alt="JDM" class="rounded-circle bg-white p-1 mb-2 center-logo">
                        <span class="fw-bold small text-white">JDM Community</span>
                    </div>
                    
                    <!-- Right node: Evangelism -->
                    <div class="cross-node node-right btn-neon" onclick="highlightSection('evangelism')">
                        <i class="bi bi-megaphone-fill text-danger fs-3 mb-2"></i>
                        <span class="fw-bold small text-dark">Evangelism</span>
                    </div>
                    
                    <!-- Bottom node: Prayer -->
                    <div class="cross-node node-bottom btn-neon" onclick="highlightSection('prayer')">
                        <i class="bi bi-chat-heart text-success fs-3 mb-2"></i>
                        <span class="fw-bold small text-dark">Prayer</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Explanation Section -->
        <div class="col-lg-7">
            <div class="d-flex flex-column gap-4">
                <!-- Word Community -->
                <div id="detail-word" class="card detail-card p-4 border-start border-primary border-4">
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-book-half text-primary fs-3 me-3"></i>
                        <h4 class="fw-bold mb-0 text-dark">God's Word Community</h4>
                    </div>
                    <p class="text-secondary small">
                        All Scripture is the best gift given to people by God, the absolutely unique standard for faith and life, and the seed of faith for salvation. The Bible is the guide of our lives, which helps our faith to continually grow and makes us present ourselves perfect in Christ.
                    </p>
                    <p class="text-secondary small">
                        We members of JDM believe that all scripture is God-breathed and the complete guide which leads humans to get salvation and abundant life. Therefore, whenever we meet, we thoroughly read and deeply study the Bible. We JDM want to be reigned only by the Word of God.
                    </p>
                    <div class="scripture-highlight text-muted small">
                        <i class="bi bi-quote me-1"></i>"All Scripture is God-breathed and is useful for teaching, rebuking, correcting and training in righteousness..." <strong>— 2 Timothy 3:16</strong> (Also: 1 Peter 1:23, Romans 10:17, Colossians 1:28-29)
                    </div>
                </div>

                <!-- Prayer Community -->
                <div id="detail-prayer" class="card detail-card p-4 border-start border-success border-4">
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-chat-heart text-success fs-3 me-3"></i>
                        <h4 class="fw-bold mb-0 text-dark">Prayer Community</h4>
                    </div>
                    <p class="text-secondary small">
                        Prayer is conversation with God and the act through which Christians praise and worship the Lord, and thank, ask, and confess to God. Prayer is the breath of the spirit and the key through which we can receive the power of God.
                    </p>
                    <p class="text-secondary small mb-0">
                        We JDM pray that God may be glorified and praised as King of kings. We are always awake for prayer so that our brothers can grow in spirituality and the gospel may spread out to the world.
                    </p>
                </div>

                <!-- Fellowship Community -->
                <div id="detail-fellowship" class="card detail-card p-4 border-start border-warning border-4 border-left-orange">
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-heart-fill text-orange fs-3 me-3"></i>
                        <h4 class="fw-bold mb-0 text-dark">Fellowship Community</h4>
                    </div>
                    <p class="text-secondary small">
                        Jesus Christ has shown Himself as the example of love and sacrifice by humbling Himself and washing His disciples' feet. Love is the core and essence of Christianity, showing concern, serving, offering, and sacrifice.
                    </p>
                    <p class="text-secondary small">
                        The Bible says that we have fellowship and relationship with other brothers in Christ Jesus in order to build the body of Christ. This is the expression of our love. We JDM practice the love of Christ to serve people not only by just our thoughts but also by our physical bodies. Therefore JDM is a faith community where we share what we have with brothers and keep our unity in Christ.
                    </p>
                    <div class="scripture-highlight text-muted small">
                        <i class="bi bi-quote me-1"></i>"...Make my joy complete by being like-minded, having the same love, being one in spirit and of one mind." <strong>— Philippians 2:5</strong> (Also: John 13:1-17, 1 Corinthians 12:12-27, Ephesians 4:1-6)
                    </div>
                </div>

                <!-- Evangelism Community -->
                <div id="detail-evangelism" class="card detail-card p-4 border-start border-danger border-4">
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-megaphone-fill text-danger fs-3 me-3"></i>
                        <h4 class="fw-bold mb-0 text-dark">Evangelism Community</h4>
                    </div>
                    <p class="text-secondary small mb-0">
                        The Gospel is that Jesus Christ came to the world and plainly showed the way of salvation to sinners. This Gospel is not only for some special individual people but is for all people such as the poor, the rich, children, old people, men, and women. We JDM put all our efforts to deliver the Good News of Jesus Christ to the end of the world by spending and sacrificing our lives, time, all gifts, and all finances.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function highlightSection(id) {
        // Remove highlight from all cards
        document.querySelectorAll('.detail-card').forEach(card => {
            card.classList.remove('active-highlight');
        });
        // Add highlight to selected card
        const card = document.getElementById('detail-' + id);
        if (card) {
            card.classList.add('active-highlight');
            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
</script>

<?php
$content = ob_get_clean();
$page_title = "Inner Features - JDM Kenya";

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
                <h1 class="display-5 fw-bold mb-2">Inner Features</h1>
                <p class="lead">Our Core Communities of Word, Prayer, Fellowship & Evangelism</p>
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
