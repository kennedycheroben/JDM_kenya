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

<div class="about-history-spirit-container">
    <!-- Sub-navigation Pills -->
    <?= getAboutSubMenu() ?>

    <div class="row">
        <!-- Spirit Section -->
        <div class="col-xl-6 mb-5 mb-xl-0">
            <div class="p-3 mb-4 bg-white rounded shadow-sm border border-light-subtle">
                <span class="badge bg-danger-subtle text-danger px-3 py-2 rounded-pill fw-bold text-uppercase mb-2">Our Inner Drive</span>
                <h3 class="fw-bold text-primary mb-3">JDM's Spirit</h3>
                <p class="text-muted">The core values and spiritual ethos that guide every JDM leader, member, and missionary in their daily walk and service.</p>
            </div>

            <div class="d-flex flex-column gap-4">
                <!-- 1. An ardent zeal towards God -->
                <div class="card spirit-card spirit-header-1 p-4">                    
                    <div class="card-spirit card-spirit-header r-p-4">
                        <!-- The container limits the width and hides overflow -->
                        <div class="custom-marquee-container">
                            <!-- The content track handles the translation/movement -->
                            <h5 class="fw-bold text-danger mb-2 custom-marquee-content">
                            <i class="bi bi-fire me-2"></i>1. An Ardent Zeal Towards God
                            </h5>
                        </div>
                    </div>
                    <p class="text-secondary small">
                        Paul, who was the excellent missionary in Christian history, always eagerly expected and hoped that Christ will be exalted. Zinzendorf, the leader of the Moravian Church, confessed:
                    </p>
                    <div class="quote-box small">
                        "I have one passion; it is He, and He alone."
                    </div>
                    <p class="text-secondary small mb-0">
                        The most precious person to Christians is Christ alone, and the person who is fully stated in the whole personality of disciples is also Christ. Like other members of JDM have had, you are to be filled with an ardent zeal towards God.
                    </p>
                    <div class="mt-2 scripture-ref"><i class="bi bi-book me-1"></i>Philippians 1:20</div>
                </div>

                <!-- 2. Readiness for suffering -->
                    <div class="card spirit-card spirit-header-2 p-4">
                        <div class="card-spirit card-spirit-header r-p-4">
                            <div class="custom-marquee-container">
                                <h5 class="fw-bold text-purple mb-2 custom-marquee-content">
                                <i class="bi bi-shield-exclamation me-2"></i>2. Readiness for Suffering
                                </h5>
                            </div>
                        </div>
                    <p class="text-secondary small mb-0">
                        If the modern church has lost something, it must surely be the Gospel of the cross and an emphasis on suffering. Can we imagine Christianity without the cross? Nevertheless, "cheap grace" is rampant. The consistent message of the Bible is that we should live with Jesus in suffering for the Gospel by the power of God, because God's grace has been granted to us on behalf of Christ not only to believe on Him but also to suffer for Him.
                    </p>
                    <div class="mt-3 scripture-ref">
                        <i class="bi bi-book me-1"></i>2 Timothy 1:8, Philippians 1:29, Colossians 1:24
                    </div>
                </div>

                <!-- 3. Oneness with other Christians -->
                <div class="card spirit-card spirit-header-3 p-4">
                    <div class="card-spirit card-spirit-header r-p-4">
                        <div class="custom-marquee-container">
                            <h5 class="fw-bold text-primary mb-2 custom-marquee-content">
                            <i class="bi bi-link-45deg me-2"></i>3. Oneness with Other Christians
                            </h5>
                        </div>
                    </div>
                    <p class="text-secondary small mb-0">
                        In the history of Christianity, much discussion has happened about Christian community. But community is not a historical product but a biblical product. Our Lord already has proclaimed that we all are one, and the Bible commands us to make every effort to keep the unity of the Holy Spirit. JDM is biblical and pioneering because we have regarded Christian community as one of our important characteristics since JDM was established. We work with other mission organizations and local churches.
                    </p>
                    <div class="mt-3 scripture-ref">
                        <i class="bi bi-book me-1"></i>1 Corinthians 12:12-13, Ephesians 4:3
                    </div>
                </div>

                <!-- 4. Serving heart -->
                <div class="card spirit-card spirit-header-4 p-4">
                    <div class="card-spirit card-spirit-header r-p-4">
                        <div class="custom-marquee-container">
                            <h5 class="fw-bold text-success mb-2 custom-marquee-content">
                            <i class="bi bi-heart-pulse-fill me-2"></i>4. Serving Heart
                            </h5>
                        </div>
                    </div>
                    <p class="text-secondary small mb-0">
                        There exist two types of leadership in history: the ruling monarch type and the serving shepherd type. Jesus declared that He had come to the world to serve and save us. Likewise, Christ's disciples should live the same as our Lord. Biblical leaders lead the people by serving and serve them by leading. We are to serve others as their servants without being famous or arrogant.
                    </p>
                    <div class="mt-3 scripture-ref">
                        <i class="bi bi-book me-1"></i>Mark 10:45
                    </div>
                </div>

                <!-- 5. Jesus Christ's mind and attitude -->
                    <div class="card spirit-card spirit-header-5 p-4">
                        <div class="card-spirit card-spirit-header r-p-4">
                            <div class="custom-marquee-container">
                                <h5 class="fw-bold text-orange mb-2 custom-marquee-content">
                                <i class="bi bi-emoji-neutral me-2"></i>5. Jesus Christ's Mind & Attitude
                                </h5>
                            </div>
                        </div>
                    <p class="text-secondary small mb-0">
                        JDM's spirit is Jesus' spirit. The Bible emphasizes that our attitude should be the same as that of Christ Jesus. It teaches that Jesus' mind is characterized with gentleness and humility. His true disciples do not just intellectually understand Jesus' mind and attitude, but practice them with their actions.
                    </p>
                    <div class="mt-3 scripture-ref">
                        <i class="bi bi-book me-1"></i>Philippians 2:5, Matthew 11:29
                    </div>
                </div>
            </div>
        </div>

        <!-- History Timeline Section -->
        <div class="col-xl-6">
            <div class="p-3 mb-4 bg-white rounded shadow-sm border border-light-subtle">
                <span class="badge bg-primary-subtle text-primary px-3 py-2 rounded-pill fw-bold text-uppercase mb-2">Our Journey</span>
                <h3 class="fw-bold text-primary mb-3">Historical Timeline</h3>
                <p class="text-muted">Key highlights and milestones of Jesus Disciple Movement since its inception in 1977.</p>
            </div>

            <ul class="timeline">
                <!-- 1977 -->
                <li class="timeline-item">
                    <div class="timeline-badge bg-primary">77</div>
                    <div class="timeline-panel">
                        <div class="fw-bold text-primary mb-1">December 3, 1977</div>
                        <p class="small text-secondary mb-0">
                            JDM starts in South Korea. Seven young pioneers—Yoon Tae Ho, Yoon Sil Gwon, Nam Gung Ok, Yoo Hyun Sin, Lee Byung Ae, Kim So Jung, Lee Hye Sook—begin fellowship with God through His Word and prayer.
                        </p>
                    </div>
                </li>

                <!-- 1978 Support -->
                <li class="timeline-item">
                    <div class="timeline-badge bg-success">78</div>
                    <div class="timeline-panel">
                        <div class="fw-bold text-success mb-1">February 1978</div>
                        <p class="small text-secondary mb-0">
                            Began supporting O-An Church, and later Chun-Sung, Sammach, and Key-Sung churches. Established a core value of cooperating and supporting local churches in need.
                        </p>
                    </div>
                </li>

                <!-- 1978 Naming -->
                <li class="timeline-item">
                    <div class="timeline-badge bg-warning text-dark">78</div>
                    <div class="timeline-panel">
                        <div class="fw-bold text-warning mb-1">March 10, 1978</div>
                        <p class="small text-secondary mb-0">
                            During JDM's first overnight retreat, the name <strong>"JESUS DISCIPLE MOVEMENT"</strong> is formally chosen.
                        </p>
                    </div>
                </li>

                <!-- 1978 Literature -->
                <li class="timeline-item">
                    <div class="timeline-badge bg-danger">78</div>
                    <div class="timeline-panel">
                        <div class="fw-bold text-danger mb-1">1978 - 1999 (Literature Ministry)</div>
                        <p class="small text-secondary mb-0">
                            Literature ministry launched: <em>The New Life</em> (Dec 1978), <em>The Fishers</em> (Apr 1981), and <em>The People of Jesus</em> (Apr 1990). The Shepherd & Disciple Publisher registered (Dec 1993) and <em>Golbang Malssum</em> QT guide launched (Apr 1999).
                        </p>
                    </div>
                </li>

                <!-- Districts -->
                <li class="timeline-item">
                    <div class="timeline-badge bg-info">12</div>
                    <div class="timeline-panel">
                        <div class="fw-bold text-info mb-1">National Expansion (12 Districts)</div>
                        <p class="small text-secondary mb-0">
                            JDM expands across Korea creating 12 Districts: Chun-Cheon(1977), Seoul(1979), Won-Ju(1985), Dae-Juen(1986), Kang-Leung(1987), Kyung-Nam(1991), Pu-San(1993), Dae-Gu(1995), Kwang-Ju(1998), Su-Won(1998), In-Chun(1999), and Chun-An(2001).
                        </p>
                    </div>
                </li>

                <!-- ministers -->
                    <li class="timeline-item">
                        <div class="timeline-badge bg-purple">03</div>
                    <div class="timeline-panel">
                        <div class="fw-bold text-purple mb-1">February 2003 Ministers</div>
                        <p class="small text-secondary mb-0">
                            Ministers grow to 86 staff in Korea headquarter/districts, and 15 full-time ministers in 9 other countries globally.
                        </p>
                    </div>
                </li>

                <!-- Boards -->
                <li class="timeline-item">
                    <div class="timeline-badge bg-dark">16</div>
                    <div class="timeline-panel">
                        <div class="fw-bold text-dark mb-1">Governance</div>
                        <p class="small text-secondary mb-0">
                            Strengthened JDM structure by establishing 16 boards across various branches and institutes by 2003.
                        </p>
                    </div>
                </li>

                <!-- Missions -->
                <li class="timeline-item">
                    <div class="timeline-badge bg-success">89</div>
                    <div class="timeline-panel">
                        <div class="fw-bold text-success mb-1">World Mission Starts</div>
                        <p class="small text-secondary mb-0">
                            Began supporting missionaries in 1987. Sent Kim Gum-Chan and Kim Mi-Ja to Ecuador in 1989. By February 2003, JDM fielded 30 long-term and 12 short-term missionaries in 14 countries.
                        </p>
                    </div>
                </li>

                <!-- Campus -->
                <li class="timeline-item">
                    <div class="timeline-badge bg-primary">84</div>
                    <div class="timeline-panel">
                        <div class="fw-bold text-primary mb-1">Campus Outreach Growth</div>
                        <p class="small text-secondary mb-0">
                            Campus ministry started at Kang-Won National University in 1984, expanding to over 60 universities in Korea and subsequently spreading across campuses worldwide.
                        </p>
                    </div>
                </li>

                <!-- Schools -->
                <li class="timeline-item">
                    <div class="timeline-badge bg-warning text-dark">91</div>
                    <div class="timeline-panel">
                        <div class="fw-bold text-warning mb-1">KDTI & Timothy Training School</div>
                        <p class="small text-secondary mb-0">
                            Inaugurated Korea Disciple Training Institute (KDTI) in 1991 for staff training. Raised the Timothy Training Course (TTC, est 1988) to school status in 1997, naming it Timothy Training School (TTS).
                        </p>
                    </div>
                </li>

                <!-- WDTI -->
                <li class="timeline-item">
                    <div class="timeline-badge bg-danger">98</div>
                    <div class="timeline-panel">
                        <div class="fw-bold text-danger mb-1">IMTI & World Disciple Training Institute</div>
                        <p class="small text-secondary mb-0">
                            Inaugurated International Missionary Training Institute (IMTI) in 1998, with the World Disciple Training Institute (WDTI) opening in 2004.
                        </p>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$page_title = "History & Spirit - JDM Kenya";

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
                <h1 class="display-5 fw-bold mb-2">History & Spirit</h1>
                <p class="lead">Our Legacy of Faith and Core Spiritual Ethos</p>
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
