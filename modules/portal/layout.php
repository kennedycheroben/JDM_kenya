<?php
// JDM Kenya - Dashboard Layout Template
// This layout provides consistent sidebar + navbar structure

require_once dirname(__FILE__) . '/../../core/db_connect.php';

// Get logged in user info
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : "Member";
$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : "member";
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$current_page = basename($_SERVER['PHP_SELF']);

// Get user's category and GBS leadership status
$user_category = 'member';
$is_gbs_leader = false;
$gbs_groups_count = 0;

if ($user_id > 0) {
    try {
        // Get user category
        $stmt = $pdo->prepare("SELECT category FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user_data) {
            $user_category = $user_data['category'];
        }
        
        // Check if user is GBS leader
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count FROM gbs_members 
            WHERE user_id = ? AND role = 'leader'
        ");
        $stmt->execute([$user_id]);
        $gbs_data = $stmt->fetch(PDO::FETCH_ASSOC);
        $is_gbs_leader = $gbs_data['count'] > 0;
        $gbs_groups_count = $gbs_data['count'];
    } catch (PDOException $e) {
        error_log("Error getting user details: " . $e->getMessage());
    }

    // Fetch all GBS groups the user belongs to (for sidebar quick access)
    $user_gbs_groups = [];
    try {
        $stmt = $pdo->prepare(
            "SELECT g.id, g.name, gm.role 
             FROM gbs_members gm 
             JOIN gbs_groups g ON gm.gbs_id = g.id 
             WHERE gm.user_id = ?
             ORDER BY g.name ASC"
        );
        $stmt->execute([$user_id]);
        $user_gbs_groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching user GBS groups: " . $e->getMessage());
    }
    $user_gbs_count = count($user_gbs_groups);
}

// Dual-role access: allow admin/super_admin to view as member
if (($user_role === 'admin' || $user_role === 'super_admin') && isset($_GET['view_as'])) {
    if ($_GET['view_as'] === 'member') {
        $_SESSION['view_as'] = 'member';
    } elseif ($_GET['view_as'] === 'admin') {
        unset($_SESSION['view_as']);
    }
    $redirect = strtok($_SERVER['REQUEST_URI'], '?');
    header('Location: ' . $redirect);
    exit;
}

// GBS Leader dashboard toggle — any logged-in GBS leader can switch views
if ($is_gbs_leader && isset($_GET['set_dashboard'])) {
    $req_dash = $_GET['set_dashboard'];
    if ($req_dash === 'gbs') {
        $_SESSION['active_dashboard'] = 'gbs';
        unset($_SESSION['view_as']);
        header('Location: gbs_dashboard.php');
        exit;
    } elseif ($req_dash === 'member') {
        $_SESSION['active_dashboard'] = 'member';
        header('Location: member_dashboard.php');
        exit;
    }
}
$active_dashboard = $_SESSION['active_dashboard'] ?? 'member';

$view_as = $_SESSION['view_as'] ?? null;
$is_impersonating_member = (($user_role === 'admin' || $user_role === 'super_admin') && $view_as === 'member');
$is_gbs_page = strpos($current_page, 'gbs') !== false;
$gbs_leader_switch_url = 'gbs_dashboard.php?set_dashboard=gbs';
$show_gbs_switch_prompt = $is_gbs_leader && !$is_gbs_page;

$is_admin = ($user_role === 'admin' || $user_role === 'super_admin') && !$is_impersonating_member;
$is_super_admin = ($user_role === 'super_admin') && !$is_impersonating_member;
$is_bible_study_leader = false;
$is_sports_admin = false;
$sports_application = null;
$sports_action_url = 'join_sports_ministry.php';
$sports_action_label = 'Join Sports Ministry';
if ($user_id > 0) {
    try {
        $stmtBsLeader = $pdo->prepare('SELECT 1 FROM bible_study_leader_assignments WHERE leader_user_id = ? AND is_current = 1 LIMIT 1');
        $stmtBsLeader->execute([$user_id]);
        $is_bible_study_leader = (bool)$stmtBsLeader->fetchColumn();
    } catch (Throwable $e) {
        // Migration may not yet be installed; keep existing navigation functional.
    }
    try {
        require_once dirname(__DIR__, 2) . '/core/sports_authorization.php';
        $is_sports_admin = sports_can_admin($pdo, $user_id);
        $sports_application = sports_application_for_user($pdo, $user_id);
        if ($sports_application) {
            $sports_action_url = 'sports_application_status.php';
            $sports_action_label = $sports_application['status'] === 'pending' ? 'Sports Application Pending' : 'Sports Application Status';
            if ($sports_application['status'] === 'approved') $sports_action_label = 'My Sports Ministry';
        }
    } catch (Throwable $e) {
        // The additive sports migration may not yet be installed.
    }
}

// Dynamic dual-role badge system
function getUserBadge($userId, $user_role, $user_category, $is_gbs_leader, $current_page) {
    $roles = [];
    $badge_classes = [];
    $badge_text = '';
    
    // Collect all roles
    if ($user_role === 'super_admin') {
        $roles[] = ['name' => 'JDM Leader', 'color' => '#2C5EAD', 'priority' => 5];
    } elseif ($user_role === 'admin') {
        $roles[] = ['name' => 'Admin', 'color' => '#1591DC', 'priority' => 4];
    }
    
    if ($is_gbs_leader) {
        $roles[] = ['name' => 'GBS Leader', 'color' => '#4BB8FA', 'priority' => 3];
    }
    
    // Member categories (only if no admin roles)
    if (empty($roles)) {
        switch ($user_category) {
            case 'student':
                $roles[] = ['name' => 'Student', 'color' => '#C4E2F5', 'priority' => 2];
                break;
            case 'associate':
                $roles[] = ['name' => 'Associate', 'color' => '#4BB8FA', 'priority' => 2];
                break;
            case 'partner':
                $roles[] = ['name' => 'Office Bearer', 'color' => '#1591DC', 'priority' => 2];
                break;
            case 'other':
                return ['html' => '', 'classes' => '', 'style' => '', 'text' => '', 'roles' => [], 'is_dual' => false];
            default:
                $roles[] = ['name' => 'Member', 'color' => '#1591DC', 'priority' => 0];
                break;
        }
    }
    
    // Sort by priority
    usort($roles, function($a, $b) {
        return $b['priority'] - $a['priority'];
    });
    
    // Generate badge HTML
    if (count($roles) === 1) {
        // Single role
        $role = $roles[0];
        $badge_classes = 'badge';
        $badge_style = "background-color: {$role['color']};";
        $badge_text = $role['name'];
    } elseif (count($roles) === 2) {
        // Dual role - create split-color badge
        $role1 = $roles[0];
        $role2 = $roles[1];
        $badge_classes = 'badge badge-dual';
        $badge_style = "background: linear-gradient(90deg, {$role1['color']} 50%, {$role2['color']} 50%);";
        
        // Shorten text for dual roles
        $short1 = $role1['name'] === 'JDM Leader' ? 'JDML' : 
                  ($role1['name'] === 'Admin' ? 'Adm' : 
                  ($role1['name'] === 'GBS Leader' ? 'GBSL' : substr($role1['name'], 0, 3)));
        $short2 = $role2['name'] === 'GBS Leader' ? 'GBSL' : substr($role2['name'], 0, 3);
        $badge_text = "$short1 / $short2";
        
        // Contextual styling
        if (strpos($current_page, 'gbs') !== false && $is_gbs_leader) {
            // In GBS pages, prioritize GBS Leader styling
            $badge_style = "background: linear-gradient(90deg, {$role2['color']} 60%, {$role1['color']} 40%);";
            $badge_classes .= ' badge-gbs-context';
        }
    } else {
        // Fallback
        $badge_classes = 'badge bg-primary';
        $badge_style = '';
        $badge_text = 'User';
    }
    
    return [
        'html' => '<span class="' . $badge_classes . '" style="' . $badge_style . '">' . $badge_text . '</span>',
        'classes' => $badge_classes,
        'style' => $badge_style,
        'text' => $badge_text,
        'roles' => $roles,
        'is_dual' => count($roles) > 1
    ];
}

// Legacy function for backward compatibility
function getDisplayRole($user_role, $user_category, $is_gbs_leader, $current_page) {
    $badge = getUserBadge(0, $user_role, $user_category, $is_gbs_leader, $current_page);
    return [
        'role' => $badge['text'],
        'badge' => $badge['classes'],
        'priority' => 0
    ];
}

$display_role = getUserBadge($user_id, $user_role, $user_category, $is_gbs_leader, $current_page);

// Default page title if not set
if (!isset($page_title)) {
    $page_title = "JDM Kenya Dashboard";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link rel="icon" type="image/png" href="<?= BASE_PATH ?>/images/jdm_logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style_dashboard.css">
    <?php if (!empty($page_styles) && is_array($page_styles)): foreach ($page_styles as $stylePath): ?>
        <link rel="stylesheet" href="<?= escape($stylePath) ?>">
    <?php endforeach; endif; ?>
    <?php
    $about_pages = ['about.php','about_ministry.php','about_history_spirit.php','about_inner_feature.php','about_outer_feature.php'];
    if (in_array($current_page, $about_pages)) {
        echo '<link rel="stylesheet" href="' . BASE_PATH . '/assets/css/about.css?v=' . filemtime(dirname(dirname(__DIR__)) . '/assets/css/about.css') . '">';
    }
    ?>
    <style>
        :root { --sidebar-width: 250px; }

        /*
         * FIX: animated-scroll.css sets #smooth-wrapper { position:fixed; overflow:hidden }
         * which is correct for public pages with Lenis smooth scroll, but breaks the
         * dashboard layout where the main content must scroll normally.
         * Override to make both divs transparent flow containers in the dashboard.
         */
        html, body, body.dashboard-wrapper, #smooth-wrapper, #smooth-content {
            position: static !important;
            height: auto !important;
            max-height: none !important;
            overflow: visible !important;
            overflow-y: auto !important;
            width: 100% !important;
        }

        body.dashboard-wrapper {
            min-height: 100vh !important;
            overflow-y: auto !important;
            -webkit-overflow-scrolling: touch !important;
        }

        #smooth-wrapper {
            position: static !important;
            height: auto !important;
            max-height: none !important;
            overflow: visible !important;
            overflow-y: auto !important;
            width: 100% !important;
            display: block !important;
        }
        #smooth-content {
            transform: none !important;
            will-change: auto !important;
            display: block !important;
        }

        .main-content-wrapper {
            overflow: visible !important;
            min-height: 100vh;
        }

        @media (min-width: 768px) {
            #sidebarMenu { width: var(--sidebar-width) !important; position: fixed; top: 0; left: 0; z-index: 1040; height: 100vh; overflow-y: auto; }
            .main-content-wrapper { width: calc(100% - var(--sidebar-width)); margin-left: var(--sidebar-width); }
            .site-footer { width: calc(100% - var(--sidebar-width)); margin-left: var(--sidebar-width); }
        }
        @media (max-width: 767.98px) {
            .main-content-wrapper { width: 100%; margin-left: 0; }
            .site-footer { width: 100%; margin-left: 0; }
        }
        #chatNotifyBadge {
            max-width: 170px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .announcement-toggle > summary {
            cursor: pointer;
            list-style: none;
        }
        .announcement-toggle > summary::-webkit-details-marker { display: none; }
        .announcement-toggle .announcement-more,
        .announcement-toggle .announcement-less { display: block; }
        .announcement-toggle .announcement-less { display: none; }
        .announcement-toggle[open] {
            display: flex;
            flex-direction: column;
        }
        .announcement-toggle[open] > summary {
            order: 2;
            margin-top: 0.5rem;
        }
        .announcement-toggle[open] > .announcement-full { order: 1; }
        .announcement-toggle[open] .announcement-preview { display: none; }
        .announcement-toggle[open] .announcement-more { display: none; }
        .announcement-toggle[open] .announcement-less { display: inline; }
        .announcement-preview { font-weight: 400; }
        .announcement-full {
            overflow-wrap: anywhere;
            white-space: normal;
            line-height: 1.7;
            font-weight: 400;
        }
        .announcement-item {
            margin-bottom: 0.75rem;
            border: 1px solid rgba(0, 0, 0, 0.125) !important;
            border-radius: 0.5rem !important;
        }

        /* ── Dashboard Switcher Button ── */
        .dashboard-switcher {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255,255,255,0.12);
            border: 1.5px solid rgba(255,255,255,0.3);
            border-radius: 10px;
            padding: 0.55rem 0.9rem;
            color: #fff;
            font-weight: 600;
            font-size: 0.82rem;
            letter-spacing: 0.3px;
            text-decoration: none;
            transition: background 0.25s ease, border-color 0.25s ease, transform 0.2s ease, box-shadow 0.25s ease !important;
            margin: 0.25rem 0;
            position: relative;
            overflow: hidden;
        }
        .dashboard-switcher::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.05) 0%, transparent 60%);
            border-radius: inherit;
            pointer-events: none;
        }
        .dashboard-switcher:hover {
            background: rgba(255,255,255,0.22);
            border-color: rgba(255,255,255,0.55);
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 16px rgba(0,0,0,0.25) !important;
            color: #fff;
        }
        .dashboard-switcher:active {
            transform: translateY(0) !important;
        }
        .dashboard-switcher.switcher-gbs {
            border-color: #4BB8FA;
            background: rgba(75,184,250,0.15);
        }
        .dashboard-switcher.switcher-gbs:hover {
            background: rgba(75,184,250,0.28);
            border-color: #4BB8FA;
            box-shadow: 0 6px 16px rgba(75,184,250,0.3) !important;
        }
        .dashboard-switcher.switcher-member {
            border-color: rgba(255,255,255,0.4);
        }
        /* Sidebar divider */
        .sidebar-divider {
            border-top: 1px solid rgba(255,255,255,0.15);
            margin: 0.5rem 0.75rem;
        }
        /* Page fade-in on load */
        .main-content-wrapper {
            animation: pageFadeIn 0.4s ease-out;
        }
        @keyframes pageFadeIn {
            from { opacity: 0; transform: translateX(12px); }
            to   { opacity: 1; transform: translateX(0); }
        }
        
        /* Remove global * transition that kills performance */
        
        /* Smooth scrolling */
        html {
            scroll-behavior: smooth;
        }
        
        /* Neon button system – overrides from style_dashboard.css handled by CSS variables */
        .btn {
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1) !important;
        }
        .btn:hover {
            transform: translateY(-2px) !important;
        }
        .btn:active {
            transform: translateY(0) !important;
        }
        
        /* Card hover effects */
        .card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }
        
        /* Form input transitions */
        .form-control, .form-select {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .form-control:focus, .form-select:focus {
            transform: scale(1.02);
            box-shadow: 0 0 0 0.2rem rgba(21, 145, 220, 0.25);
        }
        
        /* Modal transitions */
        .modal {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .modal.show {
            animation: modalFadeIn 0.3s ease-out;
        }
        
        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.7);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        /* Sidebar transitions */
        .sidebar {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        /* Badge animations */
        .badge {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .badge:hover {
            transform: scale(1.1);
        }
        
        /* Alert transitions */
        .alert {
            animation: slideInDown 0.3s ease-out;
        }
        
        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Dual-role badge styles */
        .badge-dual {
            position: relative;
            color: white;
            font-weight: 600;
            font-size: 0.75em;
            padding: 0.35em 0.65em;
            border-radius: 0.375rem;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: linear-gradient(90deg, #2C5EAD 50%, #4BB8FA 50%);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .badge-dual:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 4px 8px rgba(0,0,0,0.3);
            border-color: rgba(255,255,255,0.4);
        }
        
        .badge-dual::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, rgba(44,94,173,0.1) 50%, rgba(75,184,250,0.1) 50%);
            border-radius: inherit;
            pointer-events: none;
        }
        
        /* Contextual GBS badge styling */
        .badge-gbs-context {
            background: linear-gradient(90deg, #4BB8FA 60%, #2C5EAD 40%) !important;
            animation: badgePulse 2s ease-in-out infinite;
        }
        
        @keyframes badgePulse {
            0%, 100% { 
                transform: scale(1); 
                box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            }
            50% { 
                transform: scale(1.05); 
                box-shadow: 0 4px 8px rgba(75,184,250,0.4);
            }
        }
        
        /* Enhanced single badge styles */
        .badge {
            font-weight: 600;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
        }
        
        .badge::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.1) 50%, transparent 70%);
            transform: rotate(45deg);
            transition: all 0.6s;
            pointer-events: none;
        }
        
        .badge:hover::after {
            animation: badgeShine 0.6s ease-in-out;
        }
        
        @keyframes badgeShine {
            0% { transform: rotate(45deg) translateY(-100%); }
            50% { transform: rotate(45deg) translateY(100%); }
            100% { transform: rotate(45deg) translateY(100%); }
        }
        
        /* Responsive badge adjustments */
        @media (max-width: 768px) {
            .badge-dual {
                font-size: 0.65em;
                padding: 0.25em 0.5em;
            }
        }
        
        @media (max-width: 576px) {
            .badge-dual {
                font-size: 0.6em;
                padding: 0.2em 0.4em;
            }
        }
    </style>
    <?php if (!empty($page_scripts) && is_array($page_scripts)): ?>
        <?php foreach ($page_scripts as $scriptPath): ?>
            <script src="<?php echo htmlspecialchars($scriptPath); ?>" defer></script>
        <?php endforeach; ?>
    <?php endif; ?>

    <script>window.BASE_PATH = '<?= BASE_PATH ?>';</script>
    <script src="<?= BASE_PATH ?>/assets/js/global_search.js" defer></script>
</head>
<body class="dashboard-wrapper">

<div id="cursor"></div>
<div id="cursor-blur"></div>

        <!-- Sidebar -->
        <div class="sidebar offcanvas-md offcanvas-start vh-100 d-flex flex-column align-items-center p-0" tabindex="-1" id="sidebarMenu" style="width:250px; background-color: #2C5EAD !important; min-height:100vh;">
            <div class="offcanvas-header w-100 d-md-none border-bottom pb-2 mt-2" style="border-color: rgba(255,255,255,0.1) !important;">
                <h5 class="offcanvas-title text-white ms-2">Menu</h5>
                <button type="button" class="btn-close btn-close-white me-2" data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenu" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body flex-column w-100 p-0">
                <div class="w-100 d-flex flex-column align-items-center py-4" style="background: #2C5EAD !important;">
                    <a href="<?php echo $is_admin ? BASE_PATH . '/admin_dashboard.php' : BASE_PATH . '/member_dashboard.php'; ?>" class="d-flex flex-column align-items-center text-decoration-none">
                        <img src="<?= BASE_PATH ?>/images/jdm_logo.png" alt="JDM Kenya Logo" style="width:110px; height:110px; object-fit:contain; background:white; border-radius:50%; box-shadow:0 2px 8px rgba(0,0,0,0.08); margin-bottom:10px;">
                        <h4 class="fw-bold text-white mb-2" style="letter-spacing:1px;">JDM Kenya</h4>
                    </a>
                </div>
                <ul class="nav flex-column w-100 px-3">
                <li class="nav-item mb-2">
                    <a class="nav-link text-white" href="<?php echo $is_admin ? 'admin_dashboard.php' : 'member_dashboard.php'; ?>">
                        <i class="bi bi-house-door"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link text-white d-flex align-items-center justify-content-between" href="bible_study.php">
                        <span><i class="bi bi-book-half"></i> Bible Study</span>
                        <span id="nav-badge-bible-study" class="badge bg-danger rounded-pill d-none" style="font-size: 0.65em;"></span>
                    </a>
                </li>
                <?php if ($is_bible_study_leader): ?>
                <li class="nav-item mb-2">
                    <a class="nav-link text-white" href="bible_study_leader.php">
                        <i class="bi bi-journal-check"></i> Study Leader
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($is_super_admin): ?>
                <li class="nav-item mb-2">
                    <a class="nav-link text-white" href="bible_study_admin.php">
                        <i class="bi bi-calendar2-week"></i> Study Admin
                    </a>
                </li>
                <?php endif; ?>

                <?php if($is_admin): ?>
                    <li class="nav-item mb-2">
                        <a class="nav-link text-white" href="view_members.php">
                            <i class="bi bi-people"></i> Members
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link text-white" href="profile.php">
                            <i class="bi bi-person-circle"></i> My Profile
                        </a>
                    </li>
                    <?php if($is_super_admin): ?>
                    <li class="nav-item mb-2">
                        <a class="nav-link text-white" href="super_admin_dashboard.php">
                            <i class="bi bi-shield-lock"></i> JDM Leader
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link text-white" href="applications.php">
                            <i class="bi bi-file-earmark-text"></i> Applications
                            <?php
                            $appCount = 0;
                            try {
                                $stmtApp = $pdo->query("SELECT COUNT(*) as c FROM users WHERE is_approved = 0 AND category IN ('partner','missionary')");
                                $appRow = $stmtApp->fetch(PDO::FETCH_ASSOC);
                                $appCount = (int)$appRow['c'];
                            } catch (Throwable $e) {}
                            if ($appCount > 0): ?>
                                <span class="badge bg-danger ms-1"><?= $appCount ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item mb-2">
                        <a class="nav-link text-white d-flex align-items-center justify-content-between" href="admin_dashboard.php?tab=activities">
                            <span><i class="bi bi-calendar-event"></i> Activities</span>
                            <span id="nav-badge-activities" class="badge bg-danger rounded-pill d-none" style="font-size: 0.65em;"></span>
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link text-white d-flex align-items-center justify-content-between" href="admin_dashboard.php?tab=resources">
                            <span><i class="bi bi-file-earmark-pdf"></i> Resources</span>
                            <span id="nav-badge-resources" class="badge bg-danger rounded-pill d-none" style="font-size: 0.65em;"></span>
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link text-white d-flex align-items-center justify-content-between" href="admin_dashboard.php?tab=announcements">
                            <span><i class="bi bi-megaphone"></i> Newsroom</span>
                            <span id="nav-badge-announcements" class="badge bg-danger rounded-pill d-none" style="font-size: 0.65em;"></span>
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link text-white" href="admin_dashboard.php?tab=gallery">
                            <i class="bi bi-images"></i> Gallery
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link text-white" href="admin_dashboard.php?tab=messages">
                            <i class="bi bi-envelope"></i> Contact Messages
                        </a>
                    </li>
                    <?php if ($is_sports_admin): ?><li class="nav-item mb-2">
                        <a class="nav-link text-white" href="sports_admin.php">
                            <i class="bi bi-trophy-fill text-warning"></i> Sports Admin
                        </a>
                    </li><?php endif; ?>
                    <li class="nav-item mb-2"><a class="nav-link text-white" href="<?= escape($sports_action_url) ?>"><i class="bi bi-person-plus"></i> <?= escape($sports_action_label) ?></a></li>

                <?php else: ?>
                    <li class="nav-item mb-2">
                        <a class="nav-link text-white" href="profile.php">
                            <i class="bi bi-person-circle"></i> My Profile
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link text-white" href="directory.php">
                            <i class="bi bi-people"></i> JDM Members
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link text-white" href="chat.php">
                            <i class="bi bi-chat-dots"></i> Chat                                        
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link text-white" href="prayer_wall.php">
                            <i class="bi bi-heart"></i> Prayer Wall
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link text-white d-flex align-items-center justify-content-between" href="news_room.php">
                            <span><i class="bi bi-megaphone"></i> Newsroom</span>
                            <span id="nav-badge-announcements" class="badge bg-danger rounded-pill d-none" style="font-size: 0.65em;"></span>
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link text-white d-flex align-items-center justify-content-between" href="activities.php">
                            <span><i class="bi bi-calendar-event"></i> Activities</span>
                            <span id="nav-badge-activities" class="badge bg-danger rounded-pill d-none" style="font-size: 0.65em;"></span>
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link text-white d-flex align-items-center justify-content-between" href="resources.php">
                            <span><i class="bi bi-file-earmark-pdf"></i> Resources</span>
                            <span id="nav-badge-resources" class="badge bg-danger rounded-pill d-none" style="font-size: 0.65em;"></span>
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link text-white" href="gallery.php">
                            <i class="bi bi-images"></i> Gallery
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link text-white" href="sports.php">
                            <i class="bi bi-trophy"></i> Sports Ministry
                        </a>
                    </li>
                    <li class="nav-item mb-2"><a class="nav-link text-white" href="<?= escape($sports_action_url) ?>"><i class="bi bi-person-plus"></i> <?= escape($sports_action_label) ?></a></li>
                    <?php if ($is_sports_admin): ?>
                    <li class="nav-item mb-2"><a class="nav-link text-white" href="sports_admin.php"><i class="bi bi-clipboard2-check"></i> Sports Admin Dashboard</a></li>
                    <?php endif; ?>
                <li class="nav-item mb-2">
                    <a class="nav-link text-white" href="about.php">
                        <i class="bi bi-info-circle"></i> About
                    </a>
                </li>
                <?php endif; ?>

                <!-- User GBS groups quick access -->
                <?php if (!empty($user_gbs_groups)): ?>
                    <li class="nav-item mb-2 px-1">
                        <small class="text-white-50 px-3 fw-bold text-uppercase" style="font-size: 0.65rem;">My Groups</small>
                        <ul class="list-unstyled ms-3 mb-3">
                            <?php foreach ($user_gbs_groups as $g): ?>
                                <li class="mb-1">
                                    <a class="nav-link text-white d-flex align-items-center p-0" href="gbs_group.php?id=<?= (int)$g['id'] ?>">
                                        <i class="bi bi-people-fill me-2"></i>
                                        <span class="text-white"><?= htmlspecialchars($g['name']) ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php endif; ?>

                <!-- Curated quick links + site map -->
                <li class="nav-item mb-2 px-1">
                    <small class="text-white-50 px-3 fw-bold text-uppercase" style="font-size: 0.65rem;">Quick Links</small>
                    <ul class="list-unstyled ms-3 mb-3">
                        <?php $quick_pages = [
                            'index.php' => 'Home',
                            'about.php' => 'About',
                            'activities.php' => 'Activities',
                            'sports.php' => 'Sports',
                            'prayer_wall.php' => 'Prayer Wall',
                            'resources.php' => 'Resources',
                            'gallery.php' => 'Gallery',
                            'news_room.php' => 'Newsroom',
                            'directory.php' => 'Members',
                            'contact.php' => 'Contact',
                            'gbs_dashboard.php' => 'Groups'
                        ]; ?>
                        <?php foreach ($quick_pages as $p => $label): ?>
                            <li class="mb-1"><a class="nav-link text-white p-0" href="<?= $p ?>"><?= $label ?></a></li>
                        <?php endforeach; ?>
                        
                    </ul>
                </li>

                <li class="nav-item mt-3 mb-1">
                    <div class="sidebar-divider"></div>
                    <small class="text-white-50 px-3 fw-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.5px;">Dashboard Switcher</small>
                </li>

                <?php if ($user_role === 'admin' || $user_role === 'super_admin'): ?>
                    <?php if ($is_impersonating_member): ?>
                        <li class="nav-item mb-2 px-1">
                            <a class="dashboard-switcher switcher-member" style="border-color: #2C5EAD; background: rgba(44,94,173,0.15);" href="?view_as=admin">
                                <i class="bi bi-shield-lock"></i>
                                <span>Switch to Admin View</span>
                                <i class="bi bi-arrow-return-left ms-auto"></i>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item mb-2 px-1">
                            <a class="dashboard-switcher switcher-member" href="?view_as=member">
                                <i class="bi bi-person"></i>
                                <span>Switch to Member View</span>
                                <i class="bi bi-toggle-on ms-auto"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($is_gbs_leader): ?>
                    <li class="nav-item mb-2 px-1">
                        <?php if ($is_gbs_page || $active_dashboard === 'gbs'): ?>
                            <a class="dashboard-switcher switcher-member" href="?set_dashboard=member">
                                <i class="bi bi-person"></i>
                                <span>Switch to Member View</span>
                                <i class="bi bi-arrow-left-circle ms-auto"></i>
                            </a>
                        <?php else: ?>
                            <a class="dashboard-switcher switcher-gbs" href="gbs_dashboard.php?set_dashboard=gbs">
                                <i class="bi bi-people-fill"></i>
                                <span>GBS Leader Dashboard</span>
                                <i class="bi bi-arrow-right-circle ms-auto"></i>
                            </a>
                        <?php endif; ?>
                    </li>
                <?php endif; ?>
                <hr class="text-white">
                <li class="nav-item mb-2">
                    <a class="nav-link text-white" href="logout.php">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </li>
            </ul>
            </div>
        </div>

<div id="smooth-wrapper">
<div id="smooth-content">

        <div class="main-content-wrapper" style="min-width: 0;">
            <!-- Top Navbar -->
            <nav class="navbar bg-primary py-2 py-md-1">
                <div class="container-fluid d-flex justify-content-between align-items-start">
                    <!-- Left Side: Title and Badges -->
                    <div class="d-flex flex-column align-items-start text-white gap-2 me-auto" style="padding-right: 10px;">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-cross d-none d-sm-inline-block me-2" style="font-size: 1.5rem;"></i>
                            <span>
                                Dashboard - Welcome <strong><?= htmlspecialchars($user_name) ?></strong> 
                            </span>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <?= $display_role['html'] ?>
                            <?php if($is_impersonating_member): ?>
                                <span class="badge bg-warning text-dark flex-shrink-0">Member View</span>
                            <?php endif; ?>
                            <?php if (($user_role === 'admin' || $user_role === 'super_admin') && $is_gbs_leader): ?>
                                <div class="dropdown d-inline-block">
                                    <button class="btn btn-sm btn-outline-light dropdown-toggle py-0 py-sm-1" type="button" data-bs-toggle="dropdown">
                                        <i class="bi bi-toggle-on"></i> Switch View
                                    </button>
                                    <ul class="dropdown-menu shadow">
                                        <?php if (!$is_impersonating_member): ?>
                                            <li><a class="dropdown-item" href="?view_as=member">
                                                <i class="bi bi-person"></i> View as Member
                                            </a></li>
                                        <?php endif; ?>
                                        <?php if (strpos($current_page, 'gbs') === false): ?>
                                            <li><a class="dropdown-item" href="<?= $gbs_leader_switch_url ?>">
                                                <i class="bi bi-people-fill"></i> GBS Leader View
                                            </a></li>
                                        <?php endif; ?>
                                        <?php if ($is_impersonating_member): ?>
                                            <li><a class="dropdown-item" href="?view_as=admin">
                                                <i class="bi bi-shield"></i> Admin View
                                            </a></li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Right Side: Hamburger and Search -->
                    <div class="d-flex flex-column align-items-end gap-2 ms-auto" style="min-width: 150px; max-width: 300px; flex: 1;">
                        <button class="btn btn-outline-light d-md-none border-2 px-3 py-1 shadow-sm mt-1 mt-sm-0" style="font-size: 1.4rem;" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu">
                            <i class="bi bi-list"></i>
                        </button>
                        <div id="searchBarPlaceholder" class="w-100 d-flex justify-content-end"></div>
                    </div>
                </div>
            </nav>

            <!-- Main Content Area -->
            <div class="container-fluid mt-4 p-4">
                <?php if ($show_gbs_switch_prompt): ?>
                    <div class="alert alert-warning border-warning shadow-sm d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3" role="alert">
                        <div>
                            <div class="fw-bold">
                                <i class="bi bi-star-fill"></i> You have been appointed as a GBS Leader.
                            </div>
                            <div class="small mb-0">
                                Use the button below or the sidebar shortcut to enter your GBS Leader dashboard and manage your group, members, resources and announcements.
                            </div>
                        </div>
                        <a href="<?= $gbs_leader_switch_url ?>" class="btn btn-warning btn-lg text-dark fw-bold flex-shrink-0">
                            <i class="bi bi-people-fill"></i> Go to GBS Leader Dashboard
                        </a>
                    </div>
                <?php endif; ?>
                <?php
                    if(isset($content)) {
                        echo $content;     // Display captured content
                    } elseif(!empty($page)) {
                        include($page);     // Load page dynamically
                    }
                ?>
            </div>
        </div>
    <?php $as_no_wrapper = true; include dirname(__DIR__) . '/public/footer.php'; ?>

</div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_PATH ?>/assets/js/ui_animations.js"></script>
    <script>
        (function () {
            const userId = <?= (int)($_SESSION['user_id'] ?? 0) ?>;
            if (!userId) return;
            const badge = document.getElementById('chatNotifyBadge');

            // Store global notification state for chat.php to access
            window.unreadNotifications = {
                totalCount: 0,
                perSenderBreakdown: {}
            };

            async function pollNewMessages() {
                try {
                    const res = await fetch('check_new_messages.php', { credentials: 'same-origin' });
                    const data = await res.json();
                    if (!data.ok) {
                        console.error('check_new_messages failed:', data);
                        if(badge) badge.classList.add('d-none');
                        return;
                    }
                    const c = parseInt(data.unread_count || 0, 10) || 0;
                    
                    // Store global state for other pages (especially chat.php)
                    window.unreadNotifications.totalCount = c;
                    window.unreadNotifications.perSenderBreakdown = data.per_sender_breakdown || {};
                    
                    // Dispatch custom event for other pages to listen to
                    window.dispatchEvent(new CustomEvent('notificationsUpdated', { detail: {
                        totalCount: c,
                        perSenderBreakdown: data.per_sender_breakdown || {},
                        recentSenderName: data.recent_sender_name,
                        newResources: data.new_resources || 0,
                        newAnnouncements: data.new_announcements || 0,
                        newActivities: data.new_activities || 0,
                        newBibleStudies: data.new_bible_studies || 0
                    }}));
                    
                    if (badge) {
                        if (c > 0) {
                            badge.classList.remove('d-none');
                            const sender = (data.recent_sender_name || '').trim();
                            const countText = c > 99 ? '99+' : String(c);
                            badge.textContent = sender ? `${countText} (${sender})` : countText;
                            badge.title = sender ? `${c} New from ${sender}` : `${c} New messages`;
                        } else {
                            badge.classList.add('d-none');
                        }
                    }

                    // Update sidebar badges
                    const updateBadge = (id, count) => {
                        const el = document.getElementById(id);
                        if (el) {
                            if (count > 0) {
                                el.textContent = count > 99 ? '99+' : count;
                                el.classList.remove('d-none');
                            } else {
                                el.classList.add('d-none');
                            }
                        }
                    };

                    updateBadge('nav-badge-resources', data.new_resources || 0);
                    updateBadge('nav-badge-announcements', data.new_announcements || 0);
                    updateBadge('nav-badge-activities', data.new_activities || 0);
                    updateBadge('nav-badge-bible-study', data.new_bible_studies || 0);
                } catch (e) {
                    console.error('check_new_messages error:', e);
                }
            }

            pollNewMessages();
            setInterval(pollNewMessages, 5000);
        })();
    </script>
    <script>
        // Global modal cleanup: ensure no leftover backdrop or body classes remain
        document.addEventListener('DOMContentLoaded', function() {
            document.addEventListener('hidden.bs.modal', function (e) {
                // small delay to allow Bootstrap to finish its cleanup, then remove any stray backdrops
                setTimeout(function() {
                    document.querySelectorAll('.modal-backdrop').forEach(function(el){ el.remove(); });
                    document.body.classList.remove('modal-open');
                    document.documentElement.classList.remove('modal-open');
                    document.body.style.overflow = '';
                    document.documentElement.style.overflow = '';
                    document.body.style.paddingRight = '';
                    document.documentElement.style.paddingRight = '';
                }, 50);
            });

            // When showing a modal, ensure we don't accumulate multiple backdrops
            document.addEventListener('show.bs.modal', function (e) {
                setTimeout(function() {
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    if (backdrops.length > 1) {
                        backdrops.forEach(function(b, i){ if (i > 0) b.remove(); });
                    }
                }, 60);
            });
        });
    </script>
</body>
</html>
