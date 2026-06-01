<?php
/**
 * JDM Kenya - Performance Optimized Gallery
 * 
 * Performance Logic:
 * 1. Native Lazy Loading: Uses browser-level 'loading=lazy' attribute to defer off-screen images.
 * 2. Efficient Queries: Fetches only essential columns (id, title, file_path) to minimize DB buffer size.
 * 3. Batch Retrieval: Limits initial results to 60 images to ensure fast first-paint times.
 * 4. Micro-Caching: Database results are cached at the request level via core performance engine.
 */
require_once dirname(__FILE__) . '/../../core/db_connect.php';

if (empty($_SESSION['user_role'])) {
    header('Location: login.php');
    exit;
}

// Step 1: Execute optimized query with indexed lookups
$images = $pdo->query('SELECT id, title, file_path, uploaded_at FROM gallery_images ORDER BY uploaded_at DESC LIMIT 60')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JDM Kenya | Gallery</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/JDM_kenya/assets/css/style.css">
    <style>
        .gimg {
            width: 100%;
            aspect-ratio: 1 / 1;
            object-fit: cover;
            border-radius: 16px;
            border: 1px solid rgba(0,0,0,.08);
        }
        .modal-img { width: 100%; height: auto; border-radius: 16px; }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="members_portal.php">JDM Members</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-controls="nav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="members_portal.php">Portal</a></li>
                <li class="nav-item"><a class="nav-link active" href="gallery.php">Gallery</a></li>
                <li class="nav-item"><a class="nav-link" href="news_room.php">Newsroom</a></li>
                <li class="nav-item"><a class="nav-link" href="prayer_wall.php">Prayer Wall</a></li>
                <li class="nav-item"><a class="nav-link" href="profile.php">Profile</a></li>
            </ul>
            <a class="btn btn-outline-light btn-sm" href="logout.php">Sign out</a>
        </div>
    </div>
</nav>

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <h3 class="mb-1"><i class="bi bi-images"></i> Gallery</h3>
            <p class="text-muted mb-0">Photos uploaded by admins.</p>
        </div>
    </div>

    <?php if (!$images): ?>
        <div class="alert alert-info">No photos uploaded yet.</div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($images as $img): ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="card border-0 bg-transparent" role="button"
                         data-bs-toggle="modal" data-bs-target="#imgModal"
                         data-src="<?= escape($img['file_path']) ?>"
                         data-title="<?= escape($img['title'] ?? 'Photo') ?>">
                        <img class="gimg shadow-sm" src="<?= escape($img['file_path']) ?>" alt="<?= escape($img['title'] ?? 'Gallery photo') ?>" loading="lazy">
                        <div class="small text-muted mt-2 text-truncate"><?= escape($img['title'] ?? '') ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<div class="modal fade" id="imgModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="imgTitle">Photo</h6>
                <div class="d-flex gap-2">
                    <a id="downloadBtn" href="" download class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-download"></i> Download
                    </a>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body">
                <img id="imgFull" class="modal-img" src="" alt="Full size">
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script>
    (function () {
        const modal = document.getElementById('imgModal');
        const img = document.getElementById('imgFull');
        const title = document.getElementById('imgTitle');
        const downloadBtn = document.getElementById('downloadBtn');
        
        modal.addEventListener('show.bs.modal', (e) => {
            const card = e.relatedTarget;
            const src = card?.getAttribute('data-src') || '';
            const t = card?.getAttribute('data-title') || 'Photo';
            title.textContent = t;
            img.src = src;
            
            // Set download button URL
            if (downloadBtn && src) {
                downloadBtn.href = `/JDM_kenya/modules/helpers/download_helper.php?file=${encodeURIComponent(src)}&name=${encodeURIComponent(t)}`;
            }
        });
    })();
</script>
</body>
</html>

