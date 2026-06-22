<?php
require_once dirname(__FILE__) . '/../../core/db_connect.php';
require_once dirname(__FILE__) . '/../../core/downloads.php';

// Check member access
if (empty($_SESSION['user_role']) || ($_SESSION['user_role'] !== 'member' && $_SESSION['user_role'] !== 'admin')) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];

// Get member details
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

// =====================================================================
// OFFICE BEARER APPROVAL CHECK
// =====================================================================
// If this is a partner (office bearer) and not yet approved,
// show pending approval message instead of full dashboard
if ($member && $member['category'] === 'partner' && (!isset($member['is_approved']) || !$member['is_approved'])) {
    // Show pending approval page
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Pending Approval | JDM Kenya</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <link rel="stylesheet" href="/JDM_kenya/assets/css/style.css">
        <style>
            body {
                background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            
            .pending-card {
                background: white;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
                max-width: 600px;
                overflow: hidden;
            }
            
            .pending-header {
                background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
                color: white;
                padding: 40px 30px;
                text-align: center;
            }
            
            .pending-header h2 {
                margin: 0 0 10px 0;
                font-size: 28px;
                font-weight: 600;
            }
            
            .pending-header p {
                margin: 0;
                opacity: 0.95;
            }
            
            .pending-body {
                padding: 40px 30px;
            }
            
            .pending-icon {
                text-align: center;
                margin-bottom: 30px;
            }
            
            .pending-icon i {
                font-size: 64px;
                color: #ffc107;
            }
            
            .status-box {
                background: #f8f9fa;
                border-left: 4px solid #ffc107;
                padding: 20px;
                border-radius: 4px;
                margin-bottom: 20px;
            }
            
            .status-box h5 {
                color: #102a54;
                margin-bottom: 10px;
            }
            
            .status-box p {
                margin: 8px 0;
                color: #555;
                line-height: 1.6;
            }
            
            .pending-body a {
                color: #102a54;
                text-decoration: none;
            }
            
            .pending-body a:hover {
                text-decoration: underline;
            }
            
            .footer-link {
                text-align: center;
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #dee2e6;
            }
            
            .logout-btn {
                display: inline-block;
                margin-top: 15px;
            }
        </style>
    </head>
    <body>
        <div class="pending-card">
            <div class="pending-header">
                <h2>⏳ Pending Approval</h2>
                <p>Your office bearer registration is under review</p>
            </div>
            
            <div class="pending-body">
                <div class="pending-icon">
                    <i class="fas fa-hourglass-end"></i>
                </div>
                
                <div class="status-box">
                    <h5><i class="fas fa-check-circle" style="color: #ffc107;"></i> Thank You for Registering!</h5>
                    <p>
                        Hello <?= escape($member['name']) ?>,
                    </p>
                    <p>
                        We have received your registration as an Office Bearer in JDM Kenya. 
                        Your application is currently being reviewed by our JDM leadership team.
                    </p>
                </div>
                
                <div class="status-box">
                    <h5><i class="fas fa-clock" style="color: #ffc107;"></i> What Happens Next?</h5>
                    <p>
                        Our Super Admin will review your application and verify your details. 
                        You will receive a notification via email and in-app message once a decision has been made.
                    </p>
                    <p class="mb-0">
                        <strong>This usually takes 1-2 business days.</strong>
                    </p>
                </div>
                
                <div class="status-box">
                    <h5><i class="fas fa-envelope" style="color: #ffc107;"></i> Account Information</h5>
                    <p><strong>Email:</strong> <?= escape($member['email']) ?></p>
                    <p class="mb-0"><strong>WhatsApp:</strong> <?= escape($member['whatsapp_phone']) ?></p>
                </div>
                
                <div class="status-box">
                    <h5><i class="fas fa-question-circle" style="color: #ffc107;"></i> Need Help?</h5>
                    <p>
                        If you have any questions or need to update your information, 
                        please contact <a href="mailto:admin@jdmkenya.com">JDM leadership</a>.
                    </p>
                </div>
                
                <div class="footer-link">
                    <p class="text-muted">
                        You can log out now and check back later, or keep this page open.
                    </p>
                    <a href="logout.php" class="btn btn-sm btn-outline-primary logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Log Out
                    </a>
                </div>
            </div>
        </div>
        
        <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    echo ob_get_clean();
    exit;
}


// Get resources
$resources = $pdo->query('SELECT * FROM resources ORDER BY upload_date DESC')->fetchAll(PDO::FETCH_ASSOC);

// Get activities
$activities = $pdo->query('SELECT * FROM activities WHERE date >= CURDATE() ORDER BY date ASC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);

// Get gallery images
$galleryImages = $pdo->query('SELECT * FROM gallery_images ORDER BY uploaded_at DESC LIMIT 8')->fetchAll(PDO::FETCH_ASSOC);

// Get unread messages count
$stmt = $pdo->prepare("SELECT COUNT(*) AS unread_count FROM messages WHERE receiver_id = ? AND status = 'sent'");
$stmt->execute([$user_id]);
$unreadResult = $stmt->fetch(PDO::FETCH_ASSOC);
$unreadCount = (int)($unreadResult['unread_count'] ?? 0);

// Start output buffering
ob_start();
?>

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-md-12">
        <h2><img src="/JDM_kenya/images/jdm_logo.png" alt="JDM Logo" class="logo-img" style="height: 40px; width: auto;"> Member Dashboard</h2>
        <p class="text-muted">Access your resources and stay updated with JDM Kenya</p>
    </div>
</div>

<!-- Member Info Cards -->
<div class="row mb-4">
    <div class="col-md-6 mb-3">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-person-circle"></i> Your Profile</h5>
            </div>
            <div class="card-body">
                <p><strong>Name:</strong> <?= escape($member['name']) ?></p>
                <p><strong>Email:</strong> <?= escape($member['email']) ?></p>
                <p><strong>WhatsApp:</strong> <?= escape(maskPhone($member['whatsapp_phone'] ?? '')) ?></p>
                <p><strong>Category:</strong> 
                    <?php 
                    $badge = match($member['category']) {
                        'student' => '<span class="badge bg-info"><i class="bi bi-book"></i> Student</span>',
                        'associate' => '<span class="badge bg-success"><i class="bi bi-briefcase"></i> Associate</span>',
                        'partner' => '<span class="badge bg-warning text-dark"><i class="bi bi-briefcase-fill"></i> Office Bearer</span>',
                        'other' => '<span class="badge bg-secondary">Member</span>',
                        default => '<span class="badge bg-secondary">Member</span>'
                    };
                    echo $badge;
                    ?>
                </p>
                <p><strong>Member Since:</strong> <?= date('M d, Y', strtotime($member['created_at'])) ?></p>
                <a href="contact.php" class="btn btn-sm btn-primary mt-2">
                    <i class="bi bi-pencil"></i> Update Information
                </a>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-3">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Membership Status</h5>
            </div>
            <div class="card-body">
                <p><strong>Account Status:</strong> <span class="badge bg-success">Active</span></p>
                <p><strong>Role:</strong> <span class="badge bg-primary">Member</span></p>
                <p><strong>Access Level:</strong> Full Member Portal Access</p>
                <hr>
                <p class="text-muted small mb-0">You have access to all member resources, activities, and community features. Stay connected with us!</p>
            </div>
        </div>
    </div>
</div>

<!-- Quick Stats -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card stat-card">
            <i class="bi bi-file-pdf text-success"></i>
            <h5>Resources</h5>
            <h3 class="text-success"><?= count($resources) ?></h3>
            <p>Available for download</p>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card stat-card">
            <i class="bi bi-calendar-event text-info"></i>
            <h5>Upcoming Events</h5>
            <h3 class="text-info"><?= count($activities) ?></h3>
            <p>Activities lined up</p>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card stat-card">
            <i class="bi bi-images text-danger"></i>
            <h5>Gallery</h5>
            <h3 class="text-danger"><?= count($galleryImages) ?></h3>
            <p>Ministry photos</p>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <a href="chat.php" class="text-decoration-none" id="messagesCard">
            <div class="card stat-card h-100" style="cursor: pointer;">
                <i class="bi bi-chat-dots text-warning"></i>
                <h5>Messages</h5>
                <h3 class="text-warning" id="unreadCount"><?= $unreadCount ?></h3>
                <p>Unread messages</p>
            </div>
        </a>
    </div>
</div>

<!-- Upcoming Activities -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-calendar-event"></i> Upcoming Activities</h5>
            </div>
            <div class="card-body">
                <?php if (count($activities) > 0): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($activities as $activity): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1"><?= escape($activity['title']) ?></h6>
                                        <p class="mb-1 text-muted"><?= escape(mb_strimwidth($activity['content'], 0, 100, '...')) ?></p>
                                        <small class="text-muted"><i class="bi bi-calendar"></i> <?= date('l, M d, Y', strtotime($activity['date'])) ?></small>
                                    </div>
                                    <a href="activities.php" class="btn btn-sm btn-outline-primary">View</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-center mt-3">
                        <a href="activities.php" class="btn btn-primary">
                            <i class="bi bi-arrow-right"></i> View All Activities
                        </a>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No upcoming activities at the moment. Check back soon!</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Available Resources -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-file-pdf"></i> Available Resources (<?= count($resources) ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (count($resources) > 0): ?>
                    <div class="row g-3">
                        <?php 
                        $preview_resources = array_slice($resources, 0, 6);
                        foreach ($preview_resources as $resource): 
                        ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-body text-center">
                                        <i class="bi bi-file-pdf" style="font-size: 3rem; color: #dc3545;"></i>
                                        <h6 class="card-title mt-3"><?= escape($resource['title']) ?></h6>
                                        <small class="text-muted d-block mb-3"><?= date('M d, Y', strtotime($resource['upload_date'])) ?></small>
                                    </div>
                                    <div class="card-footer bg-transparent">
                                        <a href="<?= escape(download_url($resource['file_path'], $resource['title'])) ?>" class="btn btn-primary btn-sm w-100">
                                            <i class="bi bi-download"></i> Download
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($resources) > 6): ?>
                        <div class="text-center mt-3">
                            <a href="resources.php" class="btn btn-success">
                                <i class="bi bi-arrow-right"></i> View All Resources
                            </a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-info">No resources available yet. Check back soon!</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Ministry Gallery -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="bi bi-images"></i> Ministry in Photos</h5>
            </div>
            <div class="card-body">
                <?php if (count($galleryImages) > 0): ?>
                    <div class="row g-3">
                        <?php foreach ($galleryImages as $image): ?>
                            <div class="col-6 col-md-4 col-lg-3">
                                <div class="card shadow-sm overflow-hidden gallery-card" style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#imageModal" onclick="showImage('<?= escape($image['file_path']) ?>', '<?= escape($image['title'] ?? 'Gallery Image') ?>')">
                                    <img src="<?= escape($image['file_path']) ?>" class="card-img-top" alt="Gallery" style="height: 150px; object-fit: cover; transition: transform 0.3s;">
                                    <div class="card-body p-2">
                                        <small class="text-muted"><i class="bi bi-calendar"></i> <?= date('M d, Y', strtotime($image['uploaded_at'])) ?></small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No gallery images yet. Check back soon for ministry updates!</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Image Lightbox Modal -->
<div class="modal fade" id="imageModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-dark border-0">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white">Gallery Image</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-0">
                <img id="modalImage" src="" alt="Full size image" class="img-fluid w-100" style="max-height: 70vh; object-fit: contain;">
            </div>
        </div>
    </div>
</div>

<script>
function showImage(imagePath, imageTitle) {
    document.getElementById('modalImage').src = imagePath;
}

// Gallery card hover effect
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

    // Listen for real-time unread message updates from layout.php
    window.addEventListener('notificationsUpdated', function(event) {
        const unreadCountElement = document.getElementById('unreadCount');
        if (unreadCountElement) {
            const count = event.detail.totalCount || 0;
            unreadCountElement.textContent = count;
            
            // Add visual indicator if there are unread messages
            const messagesCard = document.getElementById('messagesCard');
            if (messagesCard) {
                if (count > 0) {
                    messagesCard.style.opacity = '1';
                } else {
                    messagesCard.style.opacity = '0.7';
                }
            }
        }
    });
});
</script>

<?php
// Get buffered content
$content = ob_get_clean();
$page_title = "Member Dashboard - JDM Kenya";
include(__DIR__ . "/layout.php");
?>
