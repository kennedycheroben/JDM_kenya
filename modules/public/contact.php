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
    <title>JDM Kenya | Contact</title>
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&family=Poppins:wght@400;500;600;700&family=Raleway:wght@500;700&family=Inter:wght@300;400;500;600;700;800;900&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/animated-scroll.css">
    <link rel="icon" type="image/png" href="<?= BASE_PATH ?>/images/jdm_logo.png">
</head>
<body>

<div id="cursor"></div>
<div id="cursor-blur"></div>

<header id="header" class="header fixed-top">
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

<!-- Hero Banner with Background -->
<section class="page-hero" style="background: linear-gradient(135deg, rgba(16,42,84,0.85) 0%, rgba(18,35,65,0.75) 100%), url('<?= BASE_PATH ?>/images/african-welcome.jpg') center/cover no-repeat scroll; height: 40vh;">
    <div class="container h-100 d-flex align-items-center justify-content-center">
        <div class="text-center text-white">
            <h1 class="display-4 fw-bold mb-2" style="text-shadow: 2px 2px 4px rgba(0,0,0,0.3);">Contact Us</h1>
            <p class="lead" style="text-shadow: 1px 1px 2px rgba(0,0,0,0.3);">We'd love to hear from you</p>
        </div>
    </div>
</section>

<main style="padding-top: 60px;">
    <section class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="text-center mb-5">
                    <h2 class="section-title mb-3">Get In Touch</h2>
                    <p class="text-muted">Reach out to Jesus Disciple Movement of Kenya for prayer, support, or to learn more about our community.</p>
                </div>
                <div class="row gy-4">
                    <div class="col-md-6">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body text-center">
                                <i class="bi bi-geo-alt-fill fs-1 text-primary mb-3"></i>
                                <h5 class="card-title">Visit Us</h5>
                                <p class="card-text">Lower Kabete, Nairobi Kenya<br>Weekly Services: Sundays at 9 AM</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body text-center">
                                <i class="bi bi-envelope-fill fs-1 text-primary mb-3"></i>
                                <h5 class="card-title">Email Us</h5>
                                <p class="card-text">jesusdisciplemovementk@gmail.com<br>support@jdmkenya.com</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body text-center">
                                <i class="bi bi-telephone-fill fs-1 text-primary mb-3"></i>
                                <h5 class="card-title">Call Us</h5>
                                <p class="card-text">0731 243 053<br>Mon-Fri, 9 AM - 5 PM</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body text-center">
                                <i class="bi bi-chat-heart-fill fs-1 text-primary mb-3"></i>
                                <h5 class="card-title">Prayer Requests</h5>
                                <p class="card-text">Submit your prayer request and we will pray together.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card shadow-sm mt-5">
                    <div class="card-body p-4">
                        <h3 class="mb-4">Send Us a Message</h3>
                        <?php if (isset($_GET['success'])): ?>
                            <div class="alert alert-success">Thank you for your message! We'll get back to you soon.</div>
                        <?php elseif (isset($_GET['error'])): ?>
                            <?php if ($_GET['error'] === 'rate'): ?>
                                <div class="alert alert-warning">Too many messages. Please try again later.</div>
                            <?php elseif ($_GET['error'] === 'csrf'): ?>
                                <div class="alert alert-danger">Session expired. Please reload and try again.</div>
                            <?php else: ?>
                                <div class="alert alert-danger">Please fill in all fields correctly.</div>
                            <?php endif; ?>
                        <?php endif; ?>
                       <form method="post" action="<?= BASE_PATH ?>/contact_process.php">
                            <?= csrf_field() ?>
                            <div class="row gy-3">
                                <div class="col-md-6">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" name="name" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" name="email" class="form-control" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Subject</label>
                                    <input type="text" name="subject" class="form-control" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Message</label>
                                    <textarea name="message" class="form-control" rows="5" required></textarea>
                                </div>
                            </div>
                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-primary">Send Message</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>
<?php include __DIR__ . '/footer.php'; ?>
<a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>
<div id="preloader"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_PATH ?>/assets/js/ui_animations.js"></script>
</body>
</html>