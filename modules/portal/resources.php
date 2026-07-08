<?php
require_once dirname(__FILE__) . '/../../core/db_connect.php';
require_once dirname(__FILE__) . '/../../core/downloads.php';
$userRole = $_SESSION['user_role'] ?? null;
$userName = $_SESSION['user_name'] ?? null;

$isLoggedIn = !empty($userRole);
$isMemberPortal = ($userRole === 'member' || $userRole === 'admin' || $userRole === 'super_admin');
$currentPage = basename($_SERVER['PHP_SELF']);
if (!function_exists('navActive')) {
    function navActive($page) {
        global $currentPage;
        return $currentPage === $page ? 'active' : '';
    }
}

$categoryParam = strtolower(trim($_GET['category'] ?? ''));
$categoryMap = [
    'study' => 'study_material',
    'study_material' => 'study_material',
    'pdf' => 'pdf_resource',
    'pdf_resource' => 'pdf_resource',
    'video' => 'video_content',
    'video_content' => 'video_content',
];
$category = $categoryMap[$categoryParam] ?? '';

if ($category !== '') {
    $stmt = $pdo->prepare('SELECT * FROM resources WHERE category = ? ORDER BY upload_date DESC');
    $stmt->execute([$category]);
    $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $resources = $pdo->query('SELECT * FROM resources ORDER BY upload_date DESC')->fetchAll(PDO::FETCH_ASSOC);
}
$galleryImages = $pdo->query('SELECT * FROM gallery_images ORDER BY uploaded_at DESC')->fetchAll(PDO::FETCH_ASSOC);
?>
<?php if ($isMemberPortal): ?>
<?php
    ob_start();
?>
<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h2><i class="bi bi-folder2-open"></i> Resource Center</h2>
            <p class="text-muted mb-0">Click a category to filter resources. Use the sidebar for portal navigation.</p>
        </div>
        <?php if ($category !== ''): ?>
            <a class="btn btn-outline-secondary" href="resources.php"><i class="bi bi-x-circle"></i> Clear Filter</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <a href="resources.php?category=study" class="text-decoration-none">
            <div class="card text-center p-4 h-100 <?= $category==='study_material'?'border border-primary':'' ?>">
                <i class="bi bi-book text-primary" style="font-size: 2.5rem;"></i>
                <h5 class="mt-3 mb-1">Study Materials</h5>
                <p class="text-muted small mb-0">Discipleship guides and study notes</p>
            </div>
        </a>
    </div>
    <div class="col-12 col-md-4">
        <a href="resources.php?category=pdf" class="text-decoration-none">
            <div class="card text-center p-4 h-100 <?= $category==='pdf_resource'?'border border-primary':'' ?>">
                <i class="bi bi-file-earmark-pdf text-danger" style="font-size: 2.5rem;"></i>
                <h5 class="mt-3 mb-1">PDF Resources</h5>
                <p class="text-muted small mb-0">Downloadable spiritual content</p>
            </div>
        </a>
    </div>
    <div class="col-12 col-md-4">
        <a href="resources.php?category=video" class="text-decoration-none">
            <div class="card text-center p-4 h-100 <?= $category==='video_content'?'border border-primary':'' ?>">
                <i class="bi bi-play-circle text-success" style="font-size: 2.5rem;"></i>
                <h5 class="mt-3 mb-1">Video Content</h5>
                <p class="text-muted small mb-0">MP4 sermons and teaching videos</p>
            </div>
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header bg-success text-white">
        <h5 class="mb-0"><i class="bi bi-list"></i> <?= $category ? ucfirst(str_replace('_',' ', $category)) : 'All Resources' ?> (<?= count($resources) ?>)</h5>
    </div>
    <div class="card-body">
        <?php if (!$resources): ?>
            <div class="alert alert-info mb-0">No resources found<?= $category ? ' in this category' : '' ?>.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Uploaded</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resources as $r): ?>
                            <?php
                                $icon = 'bi-file-earmark-pdf text-danger';
                                switch(($r['category'] ?? 'pdf_resource')) {
                                    case 'study_material':
                                        $icon = 'bi-book text-primary';
                                        break;
                                    case 'video_content':
                                        $icon = 'bi-play-circle text-success';
                                        break;
                                }
                            ?>
                            <tr>
                                <td><i class="bi <?= $icon ?>"></i> <?= escape($r['title'] ?? 'Resource') ?></td>
                                <td><span class="badge bg-light text-dark border"><?= escape($r['category'] ?? 'pdf_resource') ?></span></td>
                                <td><?= date('M d, Y', strtotime($r['upload_date'])) ?></td>
                                <td>
                                    <?php if (($r['file_type'] ?? '') === 'mp4' || ($r['category'] ?? '') === 'video_content'): ?>
                                        <div class="video-container mb-3" 
                                             data-video-id="<?= md5($r['id'] . $r['title']) ?>" 
                                             data-title="<?= escape($r['title']) ?>">
                                            <video controls preload="none" controlsList="nodownload" style="max-width: 250px; max-height: 140px; border-radius: 8px; background: #000;">
                                                <source src="<?= escape($r['file_path'] ?? '') ?>" type="video/mp4">
                                                Your browser does not support the video tag.
                                            </video>
                                        </div>
                                    <?php endif; ?>
                                    <a class="btn btn-sm btn-primary" href="<?= escape(download_url($r['file_path'] ?? '', $r['title'] ?? 'download')) ?>">
                                        <i class="bi bi-download"></i> Download
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
    $content = ob_get_clean();
    $page_title = "Resources - JDM Kenya";
    include(__DIR__ . '/layout.php');
?>
<?php else: ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JDM Kenya | Resources</title>
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
<main style="padding-top: 80px;">
        <section class="container py-5">
            <div class="mb-5">
                <h1 class="section-heading mb-3">Resource Library</h1>
                <p class="lead text-muted">Explore discipleship materials, guides, and spiritual content to support your faith journey.</p>
            </div>

            <div class="row gy-4 mb-5">
                <div class="col-md-4">
                    <div class="card text-center p-4 h-100 border-0 bg-light">
                        <i class="bi bi-book text-primary" style="font-size: 2.5rem;"></i>
                        <h5 class="mt-3">Discipleship Guides</h5>
                        <p class="text-muted small">Comprehensive materials for spiritual growth</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center p-4 h-100 border-0 bg-light">
                        <i class="bi bi-people text-success" style="font-size: 2.5rem;"></i>
                        <h5 class="mt-3">Community Studies</h5>
                        <p class="text-muted small">Group discussion resources</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center p-4 h-100 border-0 bg-light">
                        <i class="bi bi-music text-info" style="font-size: 2.5rem;"></i>
                        <h5 class="mt-3">Spiritual Content</h5>
                        <p class="text-muted small">Music and worship resources</p>
                    </div>
                </div>
            </div>

            <div class="alert alert-primary alert-dismissible fade show" role="alert">
                <strong><i class="bi bi-star"></i> Unlock Member Benefits</strong>
                <p class="mb-2">Sign in as a member to access exclusive downloads and member-only content.</p>
                <a href="login.php" class="btn btn-primary btn-sm">Sign In Now</a>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>

            <div>
                <h3 class="mb-4">Public Preview</h3>
                <?php if ($resources && count($resources) > 0): ?>
                    <div class="row gy-3">
                        <?php 
                        $preview = array_slice($resources, 0, 3);
                        foreach ($preview as $resource): 
                        ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-body">
                                        <div class="d-flex align-items-start mb-3">
                                            <?php
                                                $icon = 'bi-file-earmark-pdf text-danger';
                                                switch(($resource['category'] ?? 'pdf_resource')) {
                                                    case 'study_material':
                                                        $icon = 'bi-book text-primary';
                                                        break;
                                                    case 'video_content':
                                                        $icon = 'bi-play-circle text-success';
                                                        break;
                                                }
                                            ?>
                                            <i class="bi <?= $icon ?>" style="font-size: 1.5rem;"></i>
                                            <div class="ms-3 flex-grow-1">
                                            <h6 class="card-title mb-1"><?= escape($resource['title'] ?? 'Resource') ?></h6>
                                                <small class="text-muted">Uploaded <?= date('M d, Y', strtotime($resource['upload_date'])) ?></small>
                                            </div>
                                        </div>
                                        <p class="card-text text-muted small mb-3">Sign in to download this resource</p>
                                    </div>
                                    <div class="card-footer bg-transparent">
                                        <a class="btn btn-sm btn-outline-primary w-100" href="login.php">
                                            Sign In to Download
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Resources are coming soon! Sign up to be notified when new content is available.
                    </div>
                <?php endif; ?>
            </div>

            <div class="mt-5 pt-5 border-top">
                <h3 class="mb-4">Ministry in Photos</h3>
                <p class="text-muted mb-4">Gallery of events, activities, and ministry moments from JDM Kenya</p>
                <?php if ($galleryImages && count($galleryImages) > 0): ?>
                    <div class="row g-3">
                        <?php 
                        $galleryPreview = array_slice($galleryImages, 0, 8);
                        foreach ($galleryPreview as $image): 
                        ?>
                            <div class="col-6 col-md-4 col-lg-3">
                                <div class="card shadow-sm overflow-hidden gallery-card" style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#imageModal" onclick="showImage('<?= escape($image['file_path']) ?>', '<?= escape($image['title'] ?? 'Gallery Image') ?>')">
                                    <img src="<?= escape($image['file_path']) ?>" class="card-img-top" alt="<?= escape($image['title'] ?? 'Gallery Image') ?>" style="height: 200px; object-fit: cover; transition: transform 0.3s;" loading="lazy">
                                    <div class="card-body p-2">
                                        <small class="text-muted"><i class="bi bi-calendar"></i> <?= date('M d, Y', strtotime($image['uploaded_at'])) ?></small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($galleryImages) > 8): ?>
                        <div class="text-center mt-4">
                            <p class="text-muted mb-3">See more photos by signing in as a member</p>
                            <a href="login.php" class="btn btn-primary btn-sm">Sign In</a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> No gallery images available yet. Sign up to stay updated!
                    </div>
                <?php endif; ?>
            </div>

            <div class="text-center mt-5 pt-4 border-top">
                <p class="text-muted mb-3">Ready to join our community?</p>
                <div class="btn-group" role="group">
                    <a href="login.php" class="btn btn-primary">Sign In</a>
                    <a href="signup.php" class="btn btn-outline-primary">Create Account</a>
                </div>
            </div>
        </section>
        </section>
</main>

<?php include dirname(__DIR__) . '/public/footer.php'; ?>

<a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>
<div id="preloader"></div>

<!-- Image Lightbox Modal -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-dark border-0">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white" id="imageModalLabel">Gallery Image</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-0">
                <img id="modalImage" src="" alt="Full size image" class="img-fluid w-100" style="max-height: 70vh; object-fit: contain;">
            </div>
            <div class="modal-footer border-secondary">
                <small class="text-secondary" id="imageCaption"></small>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script>
    // Pass user ID to video player
    window.currentUserId = <?= (int)($_SESSION['user_id'] ?? 0) ?>;
</script>
<script src="<?= BASE_PATH ?>/assets/js/video_player.js"></script>
<script src="<?= BASE_PATH ?>/assets/js/ui_animations.js"></script>
<script>
function showImage(imagePath, imageTitle) {
    document.getElementById('modalImage').src = imagePath;
    document.getElementById('imageCaption').textContent = imageTitle;
}

// Add hover effect to gallery cards
document.addEventListener('DOMContentLoaded', function() {
    const galleryCards = document.querySelectorAll('.gallery-card');
    galleryCards.forEach(card => {
        card.addEventListener('mouseover', function() {
            const img = this.querySelector('img');
            img.style.transform = 'scale(1.08)';
        });
        card.addEventListener('mouseout', function() {
            const img = this.querySelector('img');
            img.style.transform = 'scale(1)';
        });
    });
});
</script>
</body>
</html>
<?php endif; ?>
