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
<div class="about-outer-feature-container">
    <!-- Sub-navigation Pills -->
    <?= getAboutSubMenu() ?>

    <div class="row gy-5 align-items-center">
        <!-- Interactive Chart Visualizer -->
        <div class="col-lg-5 text-center">
            <div class="p-3 mb-4 bg-white rounded shadow-sm border border-light-subtle">
                <span class="badge bg-primary-subtle text-primary px-3 py-2 rounded-pill fw-bold text-uppercase mb-2">Outer Expression</span>
                <h3 class="fw-bold text-primary mb-2">The Mission Cross</h3>
                <p class="text-secondary small mb-0">Click any movement node to highlight its details and scriptural references below.</p>
            </div>
            
            <div class="py-4 position-relative">
                <div class="cross-diagram-container">
                    <!-- Top node: Youth -->
                    <div class="cross-node node-top btn-neon" onclick="highlightSection('youth')">
                        <i class="bi bi-rocket-takeoff text-purple fs-3 mb-2"></i>
                        <span class="fw-bold small text-dark">Youth Mov.</span>
                    </div>
                    
                    <!-- Left node: Discipleship -->
                    <div class="cross-node node-left btn-neon" onclick="highlightSection('discipleship')">
                        <i class="bi bi-people-fill text-info fs-3 mb-2"></i>
                        <span class="fw-bold small text-dark">Discipleship</span>
                    </div>
                    
                    <!-- Center Hub -->
                    <div class="cross-node node-center btn-neon" onclick="highlightSection('center')">
                        <img src="/JDM_kenya/images/jdm_logo.png" alt="JDM" class="rounded-circle bg-white p-1 mb-2 center-logo">
                        <span class="fw-bold small text-white">JDM Community</span>
                    </div>
                    
                    <!-- Right node: World Mission -->
                    <div class="cross-node node-right btn-neon" onclick="highlightSection('mission')">
                        <i class="bi bi-globe-americas text-orange fs-3 mb-2"></i>
                        <span class="fw-bold small text-dark">World Mission</span>
                    </div>
                    
                    <!-- Bottom node: Lay -->
                    <div class="cross-node node-bottom btn-neon" onclick="highlightSection('lay')">
                        <i class="bi bi-person-fill-gear text-success fs-3 mb-2"></i>
                        <span class="fw-bold small text-dark">Lay Movement</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Explanation Section -->
        <div class="col-lg-7">
            <div class="d-flex flex-column gap-4">
                <!-- Youth Movement -->
                <div id="detail-youth" class="card detail-card p-4 border-start border-purple border-4 border-left-purple">
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-rocket-takeoff text-purple fs-3 me-3"></i>
                        <h4 class="fw-bold mb-0 text-dark">A Youth Movement</h4>
                    </div>
                    <p class="text-secondary small">
                        JDM was begun by young people. Generally speaking, young people do not give up their possibilities of growth and improvement, even under difficult situations. They have an adventurous spirit and a progressive spirit for ministries.
                    </p>
                    <p class="mb-2 text-muted">
                        A youth is not a matter of age, but a matter of spirit. Therefore, JDM is a whole life-devotion movement which started with the youth, trains young people, and is led by those who carry the spirit of a youth.
                    </p>
                    <p class="fst-italic text-secondary small mb-3 border-start border-3 ps-2">
                        <i class="bi-quote me-1"></i>"Remember your Creator in the days of your youth, before the days of trouble come..." <strong>— Ecclesiastes 12:1</strong> (Also: 2 Timothy 2:22)
                    </p>
                    <a href="sports.php" class="btn btn-sm btn-outline-success mt-2">
                        <i class="bi bi-trophy"></i> Visit Sports Ministry
                    </a>
                </div>

                <!-- Lay Movement -->
                <div id="detail-lay" class="card detail-card p-4 border-start border-success border-4 border-left-green">
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-person-fill-gear text-success fs-3 me-3"></i>
                        <h4 class="fw-bold mb-0 text-dark">Lay Movement</h4>
                    </div>
                    <p class="text-secondary small">
                        In the Bible, laity means God's chosen people. It means that, without any exception, all Christians including the clergy are the people of God, the laity. The laity are also called as the holy ministers of God to worship in their holistic lives and to witness God to the contemporary secular world.
                    </p>
                    <p class="text-secondary small mb-0">
                        We JDM help unlock the ministry of the laity who have much potential power, like a storehouse of bombs. Our lay movement is a spiritual awakening movement, a restoration movement to the Early Church, a new reformation movement, and a life movement.
                    </p>
                    <div class="scripture-highlight text-muted small mt-2">
                        <i class="bi-quote me-1"></i>"But you are a chosen people, a royal priesthood, a holy nation, God's special possession..." <strong>— 1 Peter 2:9</strong> (Also: Acts 8:1)
                    </div>
                </div>

                <!-- Discipleship Movement -->
                <div id="detail-discipleship" class="card detail-card p-4 border-start border-info border-4">
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-people-fill text-info fs-3 me-3"></i>
                        <h4 class="fw-bold mb-0 text-dark">Discipleship Movement</h4>
                    </div>
                    <p class="text-secondary small">
                        The discipleship movement is the ultimate goal of the Great Commission that Jesus has given to us. We, as "little Jesuses," are to be controlled by Jesus our Lord in every area of our life, such as lifestyle, stewardship, goals for life, and whole personality.
                    </p>
                    <p class="text-secondary small mb-0">
                        Our real lifestyle is having a passion for evangelism to make disciples of all nations, devotion for discipleship to live a Spirit-filled life, and devotion for vision to make our lives worthy. The ultimate conclusion of JDM ministry is the discipleship movement.
                    </p>
                    <div class="scripture-highlight text-muted small mt-2">
                        <i class="bi-quote me-1"></i>"Therefore go and make disciples of all nations, baptizing them in the name of the Father and of the Son and of the Holy Spirit..." <strong>— Matthew 28:19</strong> (Also: 2 Timothy 2:2)
                    </div>
                </div>

                <!-- World Mission Movement -->
                <div id="detail-mission" class="card detail-card p-4 border-start border-warning border-4 border-left-orange">
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-globe-americas text-orange fs-3 me-3"></i>
                        <h4 class="fw-bold mb-0 text-dark">World Mission Movement</h4>
                    </div>
                    <p class="text-secondary small">
                        The life given by Jesus should be productive and spread out to the whole world. We look forward to the day when Jesus comes again, just as the Apostle John did. That time will come only when the gospel of the kingdom is preached in the whole world as a testimony to all nations.
                    </p>
                    <p class="text-secondary small mb-0">
                        The internal goal of the world mission movement is spreading JDM's ministry in the world. The way and notion of our missions is based on the spirit of JDM.
                    </p>
                    <div class="scripture-highlight text-muted small mt-2">
                        <i class="bi-quote me-1"></i>"He said to them, 'Go into all the world and preach the gospel to all creation.'" <strong>— Mark 16:15</strong> (Also: Acts 1:8, Matthew 24:14, Revelation 22:20)
                    </div>
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
$page_title = "Outer Features - JDM Kenya";

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

    <!-- Hero Banner -->
    <section class="page-hero about-hero">
        <div class="container h-100 d-flex align-items-center justify-content-center">
            <div class="text-center text-white">
                <h1 class="display-5 fw-bold mb-2">Outer Features</h1>
                <p class="lead">Our Movements: Youth, Lay, Discipleship & World Mission</p>
            </div>
        </div>
    </section>

    <main class="container my-5 about-main">
        <?= $content ?>
    </main>

    <footer class="footer bg-white border-top mt-auto">
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

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
    <script src="/JDM_kenya/assets/js/ui_animations.js"></script>
    </body>
    </html>
    <?php
}
?>
