<?php
require_once dirname(__FILE__) . '/../../core/db_connect.php';
require_once dirname(__FILE__) . '/../../vendor/autoload.php';

if (isset($_SESSION['user_role'])) {
    $pendingAuthorizationUrl = \Jdm\OAuth\Support\PendingAuthorization::consumeUrl(BASE_PATH);
    if ($pendingAuthorizationUrl !== null) {
        header('Location: ' . $pendingAuthorizationUrl);
        exit;
    }

    header('Location: index.php');
    exit;
}

$error = '';
$success = '';
$reset_success = isset($_GET['reset_success']) && $_GET['reset_success'] === '1';
$registration_pending = isset($_GET['registration']) && $_GET['registration'] === 'pending';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!require_csrf()) { $error = $_SESSION['csrf_error'] ?? 'Session expired. Please reload.'; unset($_SESSION['csrf_error']); }
    elseif (!check_rate_limit('login', 5, 300)) { $error = 'Too many login attempts. Please try again in 5 minutes.'; }
    else {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } else {
        $stmt = $pdo->prepare('SELECT id,name,email,password,role,category,is_approved,is_gbs_leader,account_status FROM users WHERE TRIM(LOWER(email)) = TRIM(LOWER(?)) LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            if ($user['account_status'] !== 'active') {
                $error = 'This account is not available. Please contact JDM Kenya support.';
            } else {
            // =====================================================================
            // APPROVAL CHECK
            // =====================================================================
            // Partners (Office Bearers) and Missionaries must be approved by Super Admin before
            // they can access the full dashboard. Redirect to pending page if not approved.
            if (in_array($user['category'], ['partner', 'missionary']) && empty($user['is_approved'])) {
                $label = $user['category'] === 'partner' ? 'Office Bearer' : 'Missionary';
                $error = "Your {$label} registration is pending JDM leadership approval. You will receive a notification once approved.";
            } else {
                // =====================================================================
                // LOGIN SUCCESSFUL: Set session variables
                // =====================================================================
                // Prevent session fixation attacks by regenerating the session ID
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['is_gbs_leader'] = $user['is_gbs_leader'] ?? 0;

                $pendingAuthorizationUrl = \Jdm\OAuth\Support\PendingAuthorization::consumeUrl(BASE_PATH);
                if ($pendingAuthorizationUrl !== null) {
                    header('Location: ' . $pendingAuthorizationUrl);
                    exit;
                }

                if ($user['category'] === 'sports_ministry') {
                    header('Location: sports_application_status.php');
                    exit;
                }

                // Route to appropriate dashboard based on role
                if ($user['role'] === 'super_admin') {
                    header('Location: super_admin_dashboard.php');
                    exit;
                }
                if ($user['role'] === 'admin') {
                    header('Location: admin_dashboard.php');
                    exit;
                }

                header('Location: member_dashboard.php');
                exit;
            }
            }
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    }
    }
}

// Show success message if password was reset
if ($reset_success) {
    $success = 'Password reset successfully! You can now sign in with your new password.';
}

// Show confirmation message after registration for partner/missionary
if ($registration_pending) {
    $success = 'Your application has been submitted successfully! You will receive a notification once JDM leadership reviews and approves your registration.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JDM Kenya | Sign In</title>
    <link href="<?= BASE_PATH ?>/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css">
</head>
<body class="auth-page">
<div class="auth-page-inner">
    <div class="recovery-container">
        <div class="card-flip-wrap">
            <div class="card-flip-inner" id="loginCardInner">
                <div class="card-flip-front">
                    <div class="recovery-card">
                        <div class="recovery-card-header">
                            <h3>Member Login</h3>
                            <p>Sign in to access member resources and the JDM Dashboard.</p>
                        </div>
                        <div class="recovery-card-body">
                            <?php if ($success): ?>
                                <div class="alert alert-success alert-dismissible fade show">
                                    <span aria-hidden="true">✓</span> <?= escape($success) ?>
                                    <button class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            <?php if ($error): ?>
                                <div class="alert alert-danger alert-dismissible fade show">
                                    <span aria-hidden="true">!</span> <?= escape($error) ?>
                                    <button class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            <form method="post" novalidate>
                                <?= csrf_field() ?>
                                <div class="auth-floating-group">
                                    <input id="login_email" type="email" name="email" class="form-control auth-floating-input" placeholder=" " value="" autocomplete="username" required>
                                    <label class="auth-floating-label" for="login_email">Email address</label>
                                </div>
                                <div class="auth-floating-group">
                                    <input id="login_password" type="password" name="password" class="form-control auth-floating-input" placeholder=" " autocomplete="current-password" required>
                                    <label class="auth-floating-label" for="login_password">Password</label>
                                    <small class="text-muted d-block mt-2">
                                        <a href="forgot_password.php" class="text-decoration-underline small">Forgot your password?</a>
                                    </small>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Sign In</button>
                            </form>
                        </div>
                        <div class="recovery-footer">
                            <p class="mb-0">New to JDM? <a href="signup.php">Create an account</a></p>
                        </div>
                    </div>
                </div>
                <div class="card-flip-back">
                    <div class="back-logo" aria-hidden="true">✚</div>
                    <h3>JDM Kenya</h3>
                    <p>Building a discipleship movement with faith, clarity, and service.</p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $skipAnimationScripts = true; include dirname(__DIR__) . '/public/footer.php'; ?>
<a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center" aria-label="Back to top">↑</a>
<div id="preloader"></div>

<script src="<?= BASE_PATH ?>/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_PATH ?>/assets/js/ui_animations.js"></script>
</body>
</html>
