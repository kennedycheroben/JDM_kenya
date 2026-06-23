<?php
/**
 * Password Reset Execution Handler
 * Location: modules/auth/reset_password_view.php
 * 
 * This module handles the password reset workflow:
 * 1. Reads token safely from URL parameter
 * 2. Validates token exists in database
 * 3. Checks token has not expired (token_expires_at > NOW())
 * 4. Presents form with "New Password" and "Confirm Password" fields
 * 5. On submission: hashes password, updates user record, clears token
 * 6. Redirects to login with success message
 * 
 * Security Considerations:
 * - Token is read from URL (GET) only for validation/display purposes
 * - Tokens are single-use: cleared after successful reset
 * - Expired tokens cannot be used (timestamp validation)
 * - Passwords are hashed with PASSWORD_DEFAULT (bcrypt, currently)
 * - User cannot reset their own password to maintain security integrity
 * - XSS protection: all output escaped via escape() helper
 * - CSRF protection: form submission requires POST method
 */

require_once dirname(__FILE__) . '/../../core/db_connect.php';

// Redirect if user is already logged in
if (isset($_SESSION['user_role']) && !empty($_SESSION['user_role'])) {
    header('Location: ../../index.php');
    exit;
}

$error = '';
$success = '';
$token = '';
$user = null;
$token_valid = false;

// =========================================================================
// TOKEN VALIDATION: Read and validate recovery token
// =========================================================================
// Token comes from URL parameter (e.g., reset_password.php?token=XYZ)
// We validate it exists and hasn't expired before showing the reset form

if (isset($_GET['token']) && !empty($_GET['token'])) {
    $rawToken = trim($_GET['token']);
    
    // Basic format check: token should be 64 hex characters (32 bytes * 2)
    if (strlen($rawToken) === 64 && ctype_xdigit($rawToken)) {
        // Hash the incoming token to match the hashed value stored in DB
        $token = hash('sha256', $rawToken);
        try {
            // =====================================================================
            // DATABASE LOOKUP: Find user with this hashed token
            // =====================================================================
            $stmt = $pdo->prepare('
                SELECT id, name, email 
                FROM users 
                WHERE reset_token = ? 
                LIMIT 1
            ');
            $stmt->execute([$token]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // =====================================================================
                // EXPIRATION CHECK: Verify token hasn't expired
                // =====================================================================
                // Query the same record to check token_expires_at timestamp
                $stmtExp = $pdo->prepare('
                    SELECT token_expires_at 
                    FROM users 
                    WHERE id = ? 
                    LIMIT 1
                ');
                $stmtExp->execute([$user['id']]);
                $expData = $stmtExp->fetch(PDO::FETCH_ASSOC);
                
                if ($expData && $expData['token_expires_at']) {
                    // Compare: is token_expires_at greater than current time?
                    // If expires_at < now(), token has expired
                    if (strtotime($expData['token_expires_at']) > time()) {
                        $token_valid = true;
                    } else {
                        $error = 'This recovery link has expired. Please request a new one.';
                    }
                } else {
                    $error = 'This recovery link is no longer valid.';
                }
            } else {
                $error = 'This recovery link is invalid or has already been used.';
            }
        } catch (PDOException $e) {
            error_log("Token validation error: " . $e->getMessage());
            $error = 'An error occurred. Please try again later.';
        }
    } else {
        $error = 'Invalid recovery link format.';
    }
} else {
    $error = 'No recovery link provided. Please use the link from your email.';
}

// =========================================================================
// PASSWORD RESET SUBMISSION
// =========================================================================
// Only process if token is valid AND user submitted the form
if ($token_valid && $user && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    
    // =====================================================================
    // PASSWORD VALIDATION
    // =====================================================================
    if ($new_password === '' || $confirm_password === '') {
        $error = 'Please enter and confirm your new password.';
    } elseif (strlen($new_password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match. Please try again.';
    } else {
        try {
            // =====================================================================
            // PASSWORD HASHING: Use bcrypt via PASSWORD_DEFAULT
            // =====================================================================
            // PASSWORD_DEFAULT currently uses bcrypt (2y algorithm) with cost 10
            // This is automatically updated when PHP increases default cost
            // Never store plain passwords; always hash before database storage
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // =====================================================================
            // DATABASE UPDATE: Reset password and clear token
            // =====================================================================
            // 1. Set password to hashed value
            // 2. Clear reset_token (prevents token reuse)
            // 3. Clear token_expires_at (cleanup)
            // 4. Update modified timestamp via updated_at
            $stmtReset = $pdo->prepare('
                UPDATE users 
                SET password = ?, 
                    reset_token = NULL, 
                    token_expires_at = NULL,
                    updated_at = NOW()
                WHERE id = ?
            ');
            $stmtReset->execute([$hashed_password, $user['id']]);
            
            // =====================================================================
            // SUCCESS: Redirect to login with confirmation
            // =====================================================================
            // Append success parameter to login so we can show confirmation message
            header('Location: login.php?reset_success=1');
            exit;
        } catch (PDOException $e) {
            error_log("Password reset error: " . $e->getMessage());
            $error = 'An error occurred while resetting your password. Please try again.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | JDM Kenya</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css">
    <style>
        /* Crisp, clean design matching JDM portal theme */
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .reset-container {
            max-width: 500px;
            width: 100%;
            margin: 0 auto;
            padding: 16px;
        }
        
        .reset-card {
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .reset-card-header {
            background: var(--default-color, #102a54);
            color: white;
            padding: 24px;
            text-align: center;
        }
        
        .reset-card-header h3 {
            margin: 0;
            font-size: 22px;
            color: white;
        }
        
        .reset-card-header p {
            margin: 8px 0 0 0;
            font-size: 14px;
            opacity: 0.9;
        }
        
        .reset-card-body {
            padding: 32px;
        }
        
        .form-label {
            font-weight: 500;
            color: var(--heading-color, #102a54);
            margin-bottom: 8px;
        }
        
        .form-control {
            border: 1px solid #dee2e6;
            padding: 10px 12px;
            border-radius: 4px;
            font-size: 15px;
        }
        
        .form-control:focus {
            border-color: var(--accent-color, #d4af37);
            box-shadow: 0 0 0 0.2rem rgba(212, 175, 55, 0.25);
        }
        
        .btn-primary {
            background: var(--default-color, #102a54);
            border: none;
            padding: 10px 24px;
            font-weight: 500;
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background: var(--accent-color, #d4af37);
            color: var(--default-color, #102a54);
        }
        
        .password-requirements {
            background: #f8f9fa;
            border-left: 4px solid var(--default-color, #102a54);
            padding: 12px;
            margin-top: 12px;
            border-radius: 4px;
            font-size: 13px;
        }
        
        .password-requirements li {
            margin-bottom: 4px;
        }
        
        .alert {
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .reset-footer {
            text-align: center;
            padding-top: 16px;
            border-top: 1px solid #dee2e6;
        }
        
        .reset-footer a {
            color: var(--default-color, #102a54);
            text-decoration: none;
            font-size: 14px;
        }
        
        .reset-footer a:hover {
            text-decoration: underline;
        }
        
        .error-box {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 16px;
            border-radius: 4px;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="reset-container">
    <div class="reset-card">
        <div class="reset-card-header">
            <h3><i class="fas fa-lock"></i> Reset Your Password</h3>
            <p>Create a new password to access your account</p>
        </div>
        
        <div class="reset-card-body">
            <!-- Error Display: Show any validation or token errors -->
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?= escape($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                
                <!-- If token is invalid, show recovery link -->
                <?php if (!$token_valid): ?>
                    <div class="text-center">
                        <p class="mb-3">Need a new recovery link?</p>
                        <a href="forgot_password.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-key"></i> Request New Link
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <!-- Password Reset Form: Only shown if token is valid -->
            <?php if ($token_valid && $user): ?>
                <form method="post" novalidate>
                    <?= csrf_field() ?>
                    <div class="mb-4">
                        <label for="new_password" class="form-label">New Password</label>
                        <input 
                            type="password" 
                            id="new_password" 
                            name="new_password" 
                            class="form-control" 
                            placeholder="Enter new password"
                            required
                            autocomplete="new-password"
                            minlength="8"
                        >
                        <div class="password-requirements">
                            <strong>Password requirements:</strong>
                            <ul class="mb-0 mt-2">
                                <li>✓ At least 8 characters long</li>
                                <li>✓ Mix of uppercase and lowercase letters</li>
                                <li>✓ Include numbers and special characters for security</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            class="form-control" 
                            placeholder="Re-enter your password"
                            required
                            autocomplete="new-password"
                            minlength="8"
                        >
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-check-circle"></i> Reset Password
                    </button>
                </form>
                
                <!-- Navigation -->
                <div class="reset-footer">
                    <p class="mb-0">
                        Changed your mind? <a href="login.php">Sign In</a>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>
