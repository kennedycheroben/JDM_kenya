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
    <title>Jesus Disciple Movement Kenya | Home</title>
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&family=Poppins:wght@400;500;600;700&family=Raleway:wght@500;700&family=Inter:wght@300;400;500;600;700;800;900&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css?v=<?= filemtime(dirname(dirname(__DIR__)) . '/assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/animated-scroll.css?v=<?= filemtime(dirname(dirname(__DIR__)) . '/assets/css/animated-scroll.css') ?>">
    <link rel="icon" type="image/png" href="<?= BASE_PATH ?>/images/jdm_logo.png">
</head>
<body>

<div id="cursor"></div>
<div id="cursor-blur"></div>

<header id="header" class="header fixed-top hero-header">
    <div class="container-fluid container-xl position-relative d-flex align-items-center justify-content-between">
        <a href="index.php" class="logo d-flex align-items-center">
            <img src="<?= BASE_PATH ?>/images/jdm_logo.png" alt="JDM Kenya Logo" class="logo-img" style="height: 40px; width: auto;">
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

<main>
    <section class="hero-landing position-relative d-flex align-items-center justify-content-start overflow-hidden" style="background: linear-gradient(135deg, rgba(139, 179, 243, 0.85) 0%, rgba(18,35,65,0.75) 100%), url('<?= BASE_PATH ?>/images/african-welcome.jpg') center/cover no-repeat scroll; height: 100vh; width: 100%;">
        <div class="container-fluid h-100">
            <div class="row h-100 align-items-center">
                <div class="col-lg-5 ps-lg-5 text-white z-1">
                    <h1 class="hero-title display-3 fw-bold mb-4 animated-title">
                        Jesus Disciple<br>Movement of Kenya
                    </h1>
                    
                    <p class="hero-subtitle lead mb-5 animated-subtitle">
                        Empowering believers through <span class="typing-text">Multiplication</span>
                    </p>

                    <div class="social-links d-flex gap-3 mt-5">
                        <a href="https://www.facebook.com/share/1DuVRA4Qph/" target="_blank" class="social-icon btn-neon" title="Facebook">
                            <i class="bi bi-facebook"></i>
                        </a>
                        <a href="#" target="_blank" class="social-icon btn-neon" title="Instagram">
                            <i class="bi bi-instagram"></i>
                        </a>
                        <a href="https://vm.tiktok.com/ZS9jGEtHaDFBC-dncuF/" target="_blank" class="social-icon btn-neon" title="TikTok">
                            <i class="bi bi-tiktok"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="as-section as-welcome" style="background: var(--as-bg); color: #fff;">
      <div class="container-fluid">
        <div class="as-section-header text-center">
          <span class="as-section-tag">Welcome</span>
          <h2 class="as-section-title">Building a <span>Discipleship Movement</span></h2>
          <p class="as-section-desc mx-auto">Empowering believers across Kenya through faith, community, and mission.</p>
        </div>
        <div class="as-grid-4">
          <div class="as-service-card">
            <div class="as-service-icon"><i class="ri-community-line"></i></div>
            <h3>Community</h3>
            <p>Strong fellowship rooted in love, service, and shared faith in Christ.</p>
          </div>
          <div class="as-service-card">
            <div class="as-service-icon"><i class="ri-book-open-line"></i></div>
            <h3>Discipleship</h3>
            <p>Teaching believers to follow Jesus with confidence and consistency.</p>
          </div>
          <div class="as-service-card">
            <div class="as-service-icon"><i class="ri-megaphone-line"></i></div>
            <h3>Evangelism</h3>
            <p>Campus outreach and evangelism to share the gospel with our community.</p>
          </div>
          <div class="as-service-card">
            <div class="as-service-icon"><i class="ri-earth-line"></i></div>
            <h3>World Mission</h3>
            <p>Committed to making disciples globally through missions and service.</p>
          </div>
        </div>
      </div>
    </section>

    <section class="as-section as-quote-section" style="background: linear-gradient(135deg, rgba(15,19,48,0.93) 0%, rgba(26,31,74,0.88) 100%), url('<?= BASE_PATH ?>/images/african-students.jpg') center / cover fixed no-repeat; color: #fff;">
      <div class="container-fluid text-center">
        <div class="row justify-content-center">
          <div class="col-lg-8">
            <i class="ri-double-quotes-l" style="display: block; font-size: 2.5rem; color: var(--as-secondary); opacity: 0.6; margin-bottom: 1rem;"></i>
            <blockquote style="margin: 0;">
              <p style="font-size: clamp(1.2rem, 2.5vw, 1.75rem); font-weight: 400; line-height: 1.6; font-family: 'Playfair Display', serif; font-style: italic; color: rgba(255,255,255,0.92); margin-bottom: 1.5rem;">
                Go therefore and make disciples of all nations, baptizing them in the name of the Father and of the Son and of the Holy Spirit, teaching them to observe all that I have commanded you.
              </p>
              <footer style="font-size: 0.95rem; color: var(--as-secondary); font-weight: 600; letter-spacing: 1px;">
                — Matthew 28:19-20
              </footer>
            </blockquote>
          </div>
        </div>
      </div>
    </section>

    <section class="as-section as-about-section" style="background: var(--as-surface); color: #fff;">
      <div class="as-about-content">
        <div class="as-section-header">
          <span class="as-section-tag">Our Mission</span>
          <h2 class="as-section-title">Cultivating Faith & <span>Leadership</span></h2>
        </div>
        <p class="as-about-text">
          Jesus Disciple Movement of Kenya is a growing discipleship community focused on Campus ministry,
          Evangelism, and Discipleship. We bring believers together through mentoring, prayer, training,
          and practical service to transform campuses and communities across Kenya.
        </p>
        <div class="as-stats">
          <div class="as-stat">
            <span class="as-stat-number" data-target="45">0</span><span class="as-stat-plus">+</span>
            <span class="as-stat-label">Years</span>
          </div>
          <div class="as-stat">
            <span class="as-stat-number" data-target="200">0</span><span class="as-stat-plus">+</span>
            <span class="as-stat-label">Members</span>
          </div>
          <div class="as-stat">
            <span class="as-stat-number" data-target="8">0</span><span class="as-stat-plus">+</span>
            <span class="as-stat-label">Campuses</span>
          </div>
        </div>
      </div>
      <div class="as-about-visual">
        <div class="as-about-image-wrapper">
          <img src="<?= BASE_PATH ?>/images/kenyan-community.jpg" alt="JDM Community in Kenya" loading="lazy" class="as-parallax-img" />
          <div class="as-about-play-btn"><i class="ri-play-fill"></i></div>
        </div>
      </div>
    </section>

    <section class="as-section" style="background: var(--as-bg); color: #fff;">
      <div class="container-fluid">
        <div class="as-section-header text-center">
          <span class="as-section-tag">Our Work</span>
          <h2 class="as-section-title">Featured <span>Ministries</span></h2>
          <p class="as-section-desc mx-auto">Discover the various ministries and programs that make up JDM Kenya.</p>
        </div>
        <div class="as-grid-2">
          <div class="as-card as-card-flip-wrap">
            <div class="as-card-image">
              <img src="<?= BASE_PATH ?>/images/african-choir.jpg" alt="Youth Ministry" loading="lazy" />
            </div>
            <div class="as-card-body">
              <span class="as-card-tag">Youth</span>
              <h3>Campus & Youth Ministry</h3>
              <p>Empowering young believers through fellowship, training, and outreach on campuses across Kenya.</p>
            </div>
          </div>
          <div class="as-card as-card-flip-wrap">
            <div class="as-card-image">
              <img src="<?= BASE_PATH ?>/images/african-celebration.jpg" alt="Sports Ministry" loading="lazy" />
            </div>
            <div class="as-card-body">
              <span class="as-card-tag">Sports</span>
              <h3>Sports Ministry</h3>
              <p>Using sports as a platform to build character, teamwork, and share the gospel with young people.</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="as-contact-section" style="background: linear-gradient(135deg, var(--as-primary), var(--as-secondary)); color: #fff;">
      <div class="as-contact-inner">
        <h2 class="as-section-title">Join the <span>Movement</span></h2>
        <p>Ready to be part of something greater? Connect with us and start your discipleship journey.</p>
        <a href="<?= BASE_PATH ?>/contact.php" class="as-btn-primary">Get In Touch</a>
      </div>
    </section>

</main><!-- /main -->

<?php include __DIR__ . '/footer.php'; ?>

<div id="preloader"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_PATH ?>/assets/js/ui_animations.js"></script>
<script>
// Typing Effect
const phrases = ['Multiplication', 'Community', 'Mission'];
let phraseIndex = 0;
let charIndex = 0;
let isDeleting = false;

function typeEffect() {
    const typingElement = document.querySelector('.typing-text');
    if (!typingElement) return;

    const currentPhrase = phrases[phraseIndex];
    
    if (isDeleting) {
        charIndex--;
    } else {
        charIndex++;
    }
    
    typingElement.textContent = currentPhrase.substring(0, charIndex);
    
    if (!isDeleting && charIndex === currentPhrase.length) {
        setTimeout(() => { isDeleting = true; }, 2000);
    } else if (isDeleting && charIndex === 0) {
        isDeleting = false;
        phraseIndex = (phraseIndex + 1) % phrases.length;
    }
    
    setTimeout(typeEffect, isDeleting ? 50 : 100);
}

document.addEventListener('DOMContentLoaded', typeEffect);

// Smooth page transitions
document.addEventListener('DOMContentLoaded', function() {
    const preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.style.display = 'none';
    }
});
</script>
</body>
</html>
