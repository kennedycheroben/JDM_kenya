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
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&family=Poppins:wght@400;500;600;700&family=Raleway:wght@500;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/JDM_kenya/assets/css/style.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/JDM_kenya/assets/css/style.css') ?>">
    <link rel="icon" type="image/png" href="/JDM_kenya/images/jdm_logo.png">
</head>
<body>
<header id="header" class="header fixed-top hero-header">
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

<main>
    <section class="hero-landing position-relative d-flex align-items-center justify-content-start overflow-hidden" style="background: linear-gradient(135deg, rgba(139, 179, 243, 0.85) 0%, rgba(18,35,65,0.75) 100%), url('/JDM_kenya/images/hero-bg.jpeg') center/cover no-repeat fixed; height: 100vh; width: 100%;">
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

        <!-- Scroll Indicator -->
        <div class="scroll-indicator position-absolute bottom-0 start-50 translate-middle-x mb-4 text-white" style="opacity: 0.6;">
            <p class="small mb-2">Scroll to explore</p>
            <i class="bi bi-chevron-down animate-bounce" style="font-size: 1.5rem;"></i>
        </div>
    </section>
</main>

<footer class="footer bg-dark text-white border-top">
    <div class="container text-center py-4">
        <p class="mb-1">&copy; <?= date('Y') ?> Jesus Disciple Movement of Kenya</p>
        <p class="text-muted mb-0">Go and make Discuples of all nations</p>
    </div>
</footer>

<div id="preloader"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script src="/JDM_kenya/assets/js/ui_animations.js"></script>
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
