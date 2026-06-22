<?php
/**
 * Password Recovery Request Handler
 * Location: modules/auth/forgot_password_view.php
 * 
 * This module handles the password recovery initiation workflow:
 * 1. Presents a clean Bootstrap form requesting user's registered email
 * 2. Validates email exists in the database
 * 3. Generates cryptographically secure recovery token
 * 4. Saves hashed token and 15-minute expiration to database
 * 5. Sends a real password recovery email with the reset link
 * 
 * Security Notes:
 * - Tokens are 256-bit random values (bin2hex = 64-character strings)
 * - Tokens are stored as-is (clear) in database for validation
 * - Expiration window is 15 minutes from generation
 * - Old tokens are automatically cleared when new ones are generated
 * - XSS protection: all output is escaped via escape() helper function
 */

require_once dirname(__FILE__) . '/../../core/db_connect.php';
require_once dirname(__FILE__) . '/../../core/mailer.php';

// Redirect if user is already logged in
if (isset($_SESSION['user_role']) && !empty($_SESSION['user_role'])) {
    header('Location: ../../index.php');
    exit;
}

$error = '';
$success = '';

// =========================================================================
// FORM SUBMISSION HANDLER
// =========================================================================
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    // Retrieve and sanitize email from form submission
    $email = trim($_POST['email'] ?? '');
    
    // =====================================================================
    // INPUT VALIDATION
    // =====================================================================
    if ($email === '') {
        $error = 'Please enter your registered email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // =====================================================================
        // DATABASE LOOKUP: Verify email exists
        // =====================================================================
        try {
            $stmt = $pdo->prepare('SELECT id, name FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                // Security best practice: Don't reveal whether email exists in system
                // Use generic success message to prevent user enumeration attacks
                $success = 'If that email is registered, you will receive recovery instructions.';
            } else {
                // =====================================================================
                // TOKEN GENERATION: Create cryptographically secure random token
                // =====================================================================
                // random_bytes(32) generates 32 bytes (256 bits) of cryptographic randomness
                // bin2hex() converts binary to hexadecimal string (64 characters)
                // This is much stronger than simple uniqid() or md5(microtime())
                // Generate a 256-bit random token (64-char hex)
                $token = bin2hex(random_bytes(32));
                
                // Hash the token before storing in DB so a database breach
                // does not expose active reset tokens.
                $tokenHash = hash('sha256', $token);
                
                // =====================================================================
                // EXPIRATION CALCULATION: Token valid for 15 minutes
                // =====================================================================
                // When user validates token, we check: token_expires_at > NOW()
                // If time has passed, token is considered expired/invalid
                $expirationTime = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                
                // =====================================================================
                // DATABASE UPDATE: Save hashed token and expiration to user record
                // =====================================================================
                $stmtUpdate = $pdo->prepare('
                    UPDATE users 
                    SET reset_token = ?, token_expires_at = ?
                    WHERE id = ?
                ');
                $stmtUpdate->execute([$tokenHash, $expirationTime, $user['id']]);
                
                $recoveryLink = site_base_url() . '/reset_password.php?token=' . urlencode($token);
                $safeName = escape($user['name'] ?? 'there');
                $safeLink = escape($recoveryLink);
                $subject = 'Reset your JDM Kenya password';
                $htmlBody = "
                    <p>Hello {$safeName},</p>
                    <p>We received a request to reset your JDM Kenya password.</p>
                    <p><a href=\"{$safeLink}\">Reset your password</a></p>
                    <p>This link expires in 15 minutes. If you did not request this, you can ignore this email.</p>
                ";
                $plainBody = "Hello " . ($user['name'] ?? 'there') . ",\n\n"
                    . "We received a request to reset your JDM Kenya password.\n\n"
                    . "Reset your password here:\n{$recoveryLink}\n\n"
                    . "This link expires in 15 minutes. If you did not request this, you can ignore this email.";

                try {
                    $mailSent = send_app_email($email, $subject, $htmlBody, $plainBody);
                } catch (Throwable $mailError) {
                    error_log("Password recovery mail error: " . $mailError->getMessage());
                    $mailSent = false;
                }

                if ($mailSent) {
                    $success = 'If that email is registered, you will receive recovery instructions.';
                } else {
                    $stmtClear = $pdo->prepare('
                        UPDATE users 
                        SET reset_token = NULL, token_expires_at = NULL
                        WHERE id = ?
                    ');
                    $stmtClear->execute([$user['id']]);
                    $error = 'We could not send the recovery email. Please contact JDM Kenya support.';
                }
            }
        } catch (PDOException $e) {
            error_log("Password recovery error: " . $e->getMessage());
            $error = 'An error occurred. Please try again later.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recover Password | JDM Kenya</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/JDM_kenya/assets/css/style.css">
</head>
<body class="auth-page">
<div class="recovery-container">
    <div class="recovery-card">
        <div class="recovery-card-header">
            <h3><i class="fas fa-key"></i> Recover Password</h3>
            <p>Enter your email to receive recovery instructions</p>
        </div>
        
        <div class="recovery-card-body">
            <!-- Error Alert: Display validation or system errors -->
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?= escape($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Success Alert: Email verification successful -->
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?= escape($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Email Recovery Form -->
            <form method="post" novalidate>
                <?= csrf_field() ?>
                <div class="mb-4">
                    <label for="email" class="form-label">Registered Email Address</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="form-control" 
                        placeholder="your@email.com"
                        required
                        autocomplete="email"
                    >
                    <small class="text-muted d-block mt-2">
                        Enter the email address associated with your JDM Kenya account
                    </small>
                </div>
                
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-paper-plane"></i> Send Recovery Link
                </button>
            </form>
            
            <!-- Navigation Links -->
            <div class="recovery-footer">
                <p class="mb-0">
                    Remember your password? <a href="login.php">Sign In</a>
                </p>
                <p class="mb-0">
                    Don't have an account? <a href="signup.php">Create One</a>
                </p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>
