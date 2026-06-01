<?php
require_once dirname(__FILE__) . '/../../core/db_connect.php';
$userRole = $_SESSION['user_role'] ?? null;
$userName = $_SESSION['user_name'] ?? null;
$currentPage = basename($_SERVER['PHP_SELF']);
function navActive($page) {
    global $currentPage;
    return $currentPage === $page ? 'active' : '';
}
$activities = $pdo->query('SELECT * FROM activities ORDER BY date DESC')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JDM Kenya | Activities</title>
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
                    <li><a href="admin_.php" class="btn btn-warning btn-sm px-3"></a></li>
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

<main style="padding-top: 80px;">
    <section class="container py-5">
        <div class="mb-5">
            <h1 class="section-heading mb-3">Activities & Events</h1>
            <p class="lead text-muted">Discover the latest JDM Kenya seminars, conferences, and outreach programs designed to strengthen faith and build community.</p>
        </div>

        <div class="row gy-4">
            <?php if ($activities && count($activities) > 0): ?>
                <?php foreach ($activities as $activity): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="card activity-card h-100 shadow-sm hover-card">
                            <?php if ($activity['image']): ?>
                                <img src="<?= htmlspecialchars($activity['image']) ?>" class="card-img-top" alt="<?= htmlspecialchars($activity['title']) ?>" style="height: 200px; object-fit: cover;">
                            <?php else: ?>
                                <div class="card-img-top bg-secondary d-flex align-items-center justify-content-center" style="height: 200px;">
                                    <i class="bi bi-calendar-event text-white" style="font-size: 3rem;"></i>
                                </div>
                            <?php endif; ?>
                            <div class="card-body">
                                <span class="badge bg-primary mb-2"><?= date('M d, Y', strtotime($activity['date'])) ?></span>
                                <h5 class="card-title"><?= htmlspecialchars($activity['title']) ?></h5>
                                <p class="card-text text-secondary small"><?= htmlspecialchars(substr($activity['content'], 0, 100)) ?>...</p>
                            </div>
                            <div class="card-footer bg-transparent border-0">
                                <small class="text-muted"><i class="bi bi-geo-alt"></i> Nairobi, Kenya</small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <i class="bi bi-info-circle"></i> No activities are currently scheduled. Check back soon for upcoming events and programs!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    
                    <div class="row mt-5 text-center">
                        <div class="col-md-4">
                            <div class="card p-4 h-100">
                                <i class="bi bi-book text-primary" style="font-size: 2.5rem;"></i>
                                <h5 class="mt-3">Bible Study Groups</h5>
                                <p class="text-muted small">Regular in-depth scripture study sessions for all levels.</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card p-4 h-100">
                                <i class="bi bi-people text-primary" style="font-size: 2.5rem;"></i>
                                <h5 class="mt-3">Community Services</h5>
                                <p class="text-muted small">Service initiatives, like evangelism, children's home visits and mission work in local communities.</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card p-4 h-100">
                                <i class="bi bi-chat-heart text-primary" style="font-size: 2.5rem;"></i>
                                <h5 class="mt-3">Prayer Meetings</h5>
                                <p class="text-muted small">Weekly gatherings for intercession and spiritual growth.</p>
                            </div>
                        </div>                        
                    </div><br>

                    <div>
                        <div class="col-md-4">
                            <div class="card p-4 h-100">
                                <!-- Try this if bi-hands still doesn't show up -->
                                <i class="bi bi-brightness-high text-primary" style="font-size: 2.5rem;"></i>
                                <h5 class="mt-3">Quite Time</h5>
                                <p class="text-muted small">Daily quiet time for reflection and spiritual growth through prayer,reading bible and meditation.</p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="text-center mt-5 pt-4 border-top">
            <p class="text-muted">Want to stay updated on new activities?</p>
            <a href="contact.php" class="btn btn-primary">Contact Us</a>
        </div>
    </section>
</main>

<footer class="footer bg-white border-top mt-5">
    <div class="container text-center py-4">
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
