<?php
require_once dirname(__FILE__) . '/../../core/db_connect.php';
require_once dirname(__FILE__) . '/../../core/downloads.php';

if (empty($_SESSION['user_role'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$userName = $_SESSION['user_name'] ?? 'Member';
$userRole = $_SESSION['user_role'] ?? 'member';

if ($userRole === 'admin' || $userRole === 'super_admin') {
    $members = $pdo->query("SELECT id, name, pfp_path FROM users WHERE role IN ('member','admin') ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $members = $pdo->query("SELECT id, name, pfp_path FROM users WHERE role IN ('member','admin') AND category NOT IN ('partner', 'missionary') ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
}
$resources = $pdo->query('SELECT id, title, file_path, upload_date FROM resources ORDER BY upload_date DESC')->fetchAll(PDO::FETCH_ASSOC);
$announcements = $pdo->query('SELECT id, title, content, date_posted FROM announcements ORDER BY date_posted DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
$publicPrayers = $pdo->query('SELECT pr.id, pr.message, pr.created_at, pr.privacy_level, pr.is_private, u.name FROM prayer_requests pr INNER JOIN users u ON u.id = pr.user_id WHERE pr.privacy_level IN ("public", "anonymous") OR pr.is_private = 0 ORDER BY pr.created_at DESC LIMIT 30')->fetchAll(PDO::FETCH_ASSOC);

$pfpPath = null;
if ($userId > 0) {
    $stmt = $pdo->prepare('SELECT pfp_path FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $pfpPath = $stmt->fetchColumn() ?: null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JDM Kenya | Members Portal</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/animated-scroll.css">
    <style>
        .avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            background: #f1f3f5;
            border: 1px solid rgba(0,0,0,.08);
        }
        .avatar-lg {
            width: 96px;
            height: 96px;
            border-radius: 50%;
            object-fit: cover;
            background: #f1f3f5;
            border: 1px solid rgba(0,0,0,.08);
        }
        .member-card { cursor: pointer; }
        .member-card:active { transform: scale(.99); }
        .resource-row a { word-break: break-word; }
        .modal-img {
            width: 100%;
            height: auto;
            display: block;
            border-radius: 16px;
        }
    </style>
</head>
<body class="bg-light">

<div id="cursor"></div>
<div id="cursor-blur"></div>

<div id="smooth-wrapper">
<div id="smooth-content">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="members_portal.php">
            <i class="bi bi-people-fill"></i>
            <span>JDM Members</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link active" href="members_portal.php">Portal</a></li>
                <li class="nav-item"><a class="nav-link" href="resources.php">Resource Center</a></li>
                <li class="nav-item"><a class="nav-link" href="news_room.php">Newsroom</a></li>
                <li class="nav-item"><a class="nav-link" href="prayer_wall.php">Prayer Wall</a></li>
                <li class="nav-item"><a class="nav-link" href="gallery.php">Gallery</a></li>
                <li class="nav-item"><a class="nav-link" href="profile.php">Profile</a></li>
                <?php if ($userRole === 'admin' || $userRole === 'super_admin'): ?>
                    <li class="nav-item"><a class="nav-link" href="admin_dashboard.php">Admin</a></li>
                <?php endif; ?>
            </ul>
            <div class="d-flex align-items-center gap-2">
                <?php if ($pfpPath): ?>
                    <img class="avatar" src="<?= escape($pfpPath) ?>" alt="Profile picture">
                <?php else: ?>
                    <i class="bi bi-person-circle text-white" style="font-size: 1.75rem;"></i>
                <?php endif; ?>
                <div class="text-white small d-none d-lg-block">
                    <?= escape($userName) ?>
                </div>
                <a class="btn btn-outline-light btn-sm" href="logout.php">Sign out</a>
            </div>
        </div>
    </div>
</nav>

<main class="container py-4">
    <div class="row g-3 align-items-stretch">
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <?php if ($pfpPath): ?>
                            <img class="avatar-lg" src="<?= escape($pfpPath) ?>" alt="Profile picture">
                        <?php else: ?>
                            <div class="avatar-lg d-flex align-items-center justify-content-center">
                                <i class="bi bi-person text-secondary" style="font-size: 2.5rem;"></i>
                            </div>
                        <?php endif; ?>
                        <div class="flex-grow-1">
                            <h5 class="mb-1">Welcome, <?= escape($userName) ?></h5>
                            <div class="text-muted small">Members Portal</div>
                            <div class="mt-3 d-grid gap-2">
                                <a class="btn btn-primary" href="profile.php"><i class="bi bi-person-badge"></i> Manage Profile</a>
                                <a class="btn btn-outline-secondary" href="resources.php"><i class="bi bi-folder2-open"></i> Resource Center</a>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="d-flex gap-2 flex-wrap">
                        <span class="badge text-bg-dark"><i class="bi bi-shield-lock"></i> Privacy-first directory</span>
                        <span class="badge text-bg-success"><i class="bi bi-phone"></i> WhatsApp enabled</span>
                        
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-8">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <ul class="nav nav-pills card-header-pills flex-wrap" id="portalTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="dir-tab" data-bs-toggle="tab" data-bs-target="#dir" type="button" role="tab">Directory</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="res-tab" data-bs-toggle="tab" data-bs-target="#res" type="button" role="tab">Resources</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="pr-tab" data-bs-toggle="tab" data-bs-target="#pr" type="button" role="tab">Prayer Wall</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="bi-tab" data-bs-toggle="tab" data-bs-target="#bi" type="button" role="tab">Newsroom</button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="portalTabsContent">
                        <div class="tab-pane fade show active" id="dir" role="tabpanel" aria-labelledby="dir-tab" tabindex="0">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <h6 class="mb-0">Members Directory</h6>
                                    <div class="text-muted small">Only names and profile pictures are visible.</div>
                                </div>
                                <span class="badge text-bg-light border"><?= count($members) ?> members</span>
                            </div>
                            <div class="row g-2">
                                <?php foreach ($members as $m): ?>
                                    <div class="col-12 col-md-6">
                                        <div class="card member-card" data-img="<?= escape($m['pfp_path'] ?? '') ?>" data-name="<?= escape($m['name']) ?>" data-bs-toggle="modal" data-bs-target="#imgModal">
                                            <div class="card-body d-flex align-items-center gap-3">
                                                <?php if (!empty($m['pfp_path'])): ?>
                                                    <img class="avatar" src="<?= escape($m['pfp_path']) ?>" alt="Profile picture">
                                                <?php else: ?>
                                                    <div class="avatar d-flex align-items-center justify-content-center">
                                                        <i class="bi bi-person text-secondary"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="flex-grow-1">
                                                    <div class="fw-semibold"><?= escape($m['name']) ?></div>
                                                    <div class="text-muted small">Tap photo to expand</div>
                                                </div>
                                                <i class="bi bi-arrows-fullscreen text-muted"></i>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="res" role="tabpanel" aria-labelledby="res-tab" tabindex="0">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <h6 class="mb-0">Resource Center</h6>
                                    <div class="text-muted small">View and download PDF/document materials.</div>
                                </div>
                                <a href="resources.php" class="btn btn-sm btn-outline-secondary">Open full page</a>
                            </div>
                            <?php if (!$resources): ?>
                                <div class="alert alert-info mb-0">No resources uploaded yet.</div>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach (array_slice($resources, 0, 8) as $r): ?>
                                        <div class="list-group-item resource-row d-flex justify-content-between align-items-center gap-3">
                                            <div class="min-w-0">
                                                <div class="fw-semibold text-truncate"><?= escape($r['title']) ?></div>
                                                <div class="text-muted small">Uploaded <?= date('M d, Y', strtotime($r['upload_date'])) ?></div>
                                            </div>
                                            <a class="btn btn-sm btn-primary flex-shrink-0" href="<?= escape(download_url($r['file_path'], $r['title'])) ?>">
                                                <i class="bi bi-download"></i>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="tab-pane fade" id="pr" role="tabpanel" aria-labelledby="pr-tab" tabindex="0">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <h6 class="mb-0">Prayer Wall</h6>
                                    <div class="text-muted small">Public requests are visible to members; private is admin-only.</div>
                                </div>
                                <a href="prayer_wall.php" class="btn btn-sm btn-outline-secondary">Submit / View</a>
                            </div>
                            <?php if (!$publicPrayers): ?>
                                <div class="alert alert-info mb-0">No public prayer requests yet.</div>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach (array_slice($publicPrayers, 0, 5) as $p): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="fw-semibold">
                                                    <?php 
                                                        $privacyLevel = $p['privacy_level'] ?? ($p['is_private'] ? 'private' : 'public');
                                                        $displayName = ($privacyLevel === 'anonymous') ? 'Anonymous' : escape($p['name']);
                                                    ?>
                                                    <?= $displayName ?>
                                                    <?php if ($privacyLevel === 'anonymous'): ?>
                                                        <span class="badge text-bg-info ms-2"><i class="bi bi-incognito"></i> Anonymous</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-muted small"><?= date('M d, Y', strtotime($p['created_at'])) ?></div>
                                            </div>
                                            <div class="text-muted mt-1"><?= nl2br(escape($p['message'])) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="tab-pane fade" id="bi" role="tabpanel" aria-labelledby="bi-tab" tabindex="0">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <h6 class="mb-0">Newsroom</h6>
                                    <div class="text-muted small">Announcements visible to all logged-in users.</div>
                                </div>
                                <a href="news_room.php" class="btn btn-sm btn-outline-secondary">Open</a>
                            </div>
                            <?php if (!$announcements): ?>
                                <div class="alert alert-info mb-0">No announcements posted yet.</div>
                            <?php else: ?>
                                <div class="accordion" id="annAccordion">
                                    <?php foreach (array_slice($announcements, 0, 5) as $idx => $a): ?>
                                        <div class="accordion-item">
                                            <h2 class="accordion-header">
                                                <button class="accordion-button <?= $idx === 0 ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#ann<?= (int)$a['id'] ?>">
                                                    <?= escape($a['title']) ?>
                                                    <span class="ms-2 text-muted small">(<?= date('M d', strtotime($a['date_posted'])) ?>)</span>
                                                </button>
                                            </h2>
                                            <div id="ann<?= (int)$a['id'] ?>" class="accordion-collapse collapse <?= $idx === 0 ? 'show' : '' ?>" data-bs-parent="#annAccordion">
                                                <div class="accordion-body">
                                                    <?= nl2br(escape($a['content'])) ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<div class="modal fade" id="imgModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="imgModalTitle">Profile Picture</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <img id="imgModalImg" class="modal-img" src="" alt="Full size profile picture">
                <div id="imgModalEmpty" class="text-center text-muted py-5 d-none">
                    No profile picture uploaded.
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script>
    (function () {
        const modal = document.getElementById('imgModal');
        const titleEl = document.getElementById('imgModalTitle');
        const imgEl = document.getElementById('imgModalImg');
        const emptyEl = document.getElementById('imgModalEmpty');

        modal.addEventListener('show.bs.modal', function (event) {
            const card = event.relatedTarget;
            const img = card?.getAttribute('data-img') || '';
            const name = card?.getAttribute('data-name') || 'Profile Picture';

            titleEl.textContent = name;
            if (img) {
                imgEl.src = img;
                imgEl.classList.remove('d-none');
                emptyEl.classList.add('d-none');
            } else {
                imgEl.src = '';
                imgEl.classList.add('d-none');
                emptyEl.classList.remove('d-none');
            }
        });
    })();
</script>

</div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
<script src="<?= BASE_PATH ?>/assets/js/lenis.min.js"></script>
<script src="<?= BASE_PATH ?>/assets/js/animated-scroll.js?v=<?= filemtime(dirname(dirname(__DIR__)) . '/assets/js/animated-scroll.js') ?>"></script>

</body>
</html>
