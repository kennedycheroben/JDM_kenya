<?php
require_once dirname(__FILE__) . '/../../core/db_connect.php';

if (isset($_SESSION['user_role'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';
$reset_success = isset($_GET['reset_success']) && $_GET['reset_success'] === '1';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // =====================================================================
            // OFFICE BEARER APPROVAL CHECK
            // =====================================================================
            // Partners (Office Bearers) must be approved by Super Admin before
            // they can access the full dashboard. Redirect to pending page if not approved.
            if ($user['category'] === 'partner' && (!isset($user['is_approved']) || !$user['is_approved'])) {
                $error = 'Your account is pending Super Admin approval. You will receive a notification once approved.';
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
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    }
}

// Show success message if password was reset
if ($reset_success) {
    $success = 'Password reset successfully! You can now sign in with your new password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JDM Kenya | Sign In</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/JDM_kenya/assets/css/style.css">
</head>
<body>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h3 class="mb-3">Member Login</h3>
                    <p class="text-muted">Sign in to access member resources and the JDM portal.</p>
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle"></i> <?= escape($success) ?>
                            <button class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-circle"></i> <?= escape($error) ?>
                            <button class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    <form method="post" novalidate>
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label">Email address</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                            <small class="text-muted d-block mt-1">
                                <a href="forgot_password.php" class="text-decoration-underline small">Forgot your password?</a>
                            </small>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Sign In</button>
                    </form>
                    <div class="mt-4 text-center">
                        <p class="mb-0">New to JDM? <a href="signup.php">Create an account</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<footer class="footer bg-white border-top mt-5">
    <div class="container text-center">
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
