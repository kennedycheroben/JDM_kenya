<?php
/**
 * JDM Kenya - Unified Member & Administrator Profile
 *
 * Integrated into the shared portal layout with role awareness,
 * category-specific data rendering, profile picture upload, and
 * strict protection against unauthorized role/approval manipulation.
 */

require_once dirname(__FILE__) . '/../../core/db_connect.php';

if (empty($_SESSION['user_role'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: logout.php');
    exit;
}

$error = '';
$success = '';

// Handle Profile Updates
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    
    // 1. Profile Picture Upload
    if (isset($_POST['upload_pfp'])) {
        if (!empty($_FILES['profile_picture']['name'])) {
            $file = $_FILES['profile_picture'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error = 'Upload failed. Please check file and try again.';
            } elseif (!in_array($ext, $allowed, true)) {
                $error = 'Please upload a valid image file (JPG, PNG, or WEBP).';
            } elseif (($file['size'] ?? 0) > 4 * 1024 * 1024) {
                $error = 'Image file too large. Maximum size allowed is 4MB.';
            } else {
                $destinationDir = UPLOAD_DIR . 'profiles/';
                if (!is_dir($destinationDir)) {
                    @mkdir($destinationDir, 0775, true);
                }

                $targetName = 'pfp_' . $userId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                $destination = $destinationDir . $targetName;

                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $publicPath = 'uploads/profiles/' . $targetName;
                    $stmt = $pdo->prepare('UPDATE users SET pfp_path = ? WHERE id = ?');
                    $stmt->execute([$publicPath, $userId]);
                    $success = 'Profile picture updated successfully.';
                } else {
                    $error = 'Unable to save the uploaded image.';
                }
            }
        } else {
            $error = 'Please select a profile picture to upload.';
        }
    }

    // 2. Main Profile Details Update
    if (isset($_POST['update_profile'])) {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $whatsappPhone = trim($_POST['whatsapp_phone'] ?? '');
        
        if ($name === '' || $email === '' || $whatsappPhone === '') {
            $error = 'Name, Email address, and WhatsApp Phone are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please provide a valid email address.';
        } else {
            // Ensure email uniqueness (excluding current user)
            $stmtCheck = $pdo->prepare('SELECT COUNT(*) FROM users WHERE TRIM(LOWER(email)) = TRIM(LOWER(?)) AND id != ?');
            $stmtCheck->execute([$email, $userId]);
            if ((int)$stmtCheck->fetchColumn() > 0) {
                $error = 'This email address is already associated with another account.';
            } else {
                $pdo->beginTransaction();
                try {
                    // Fetch existing user record to verify current category
                    $stmtCurrent = $pdo->prepare('SELECT category FROM users WHERE id = ?');
                    $stmtCurrent->execute([$userId]);
                    $currentCategory = $stmtCurrent->fetchColumn() ?: 'other';

                    // Update standard personal & contact fields ONLY (never role, is_approved, or permissions)
                    $stmtUpdate = $pdo->prepare('UPDATE users SET name = ?, email = ?, whatsapp_phone = ? WHERE id = ?');
                    $stmtUpdate->execute([$name, $email, $whatsappPhone, $userId]);

                    // Category-specific updates
                    if ($currentCategory === 'partner') {
                        $gradYear = trim($_POST['graduation_year'] ?? '') ?: null;
                        $campusRole = trim($_POST['campus_role'] ?? '') ?: null;
                        $empStatus = trim($_POST['employment_status'] ?? '') ?: null;
                        $compName = trim($_POST['company_name'] ?? '') ?: null;
                        $indProf = trim($_POST['industry_profession'] ?? '') ?: null;
                        $partFocus = trim($_POST['partnership_focus'] ?? '') ?: null;
                        $contribPhone = trim($_POST['contribution_phone'] ?? '') ?: null;

                        $stmtP = $pdo->prepare('
                            UPDATE users SET 
                                graduation_year = ?, 
                                campus_role = ?, 
                                employment_status = ?, 
                                company_name = ?, 
                                industry_profession = ?, 
                                partnership_focus = ?, 
                                contribution_phone = ? 
                            WHERE id = ?
                        ');
                        $stmtP->execute([$gradYear, $campusRole, $empStatus, $compName, $indProf, $partFocus, $contribPhone, $userId]);
                    } elseif ($currentCategory === 'missionary') {
                        $missionaryType = trim($_POST['missionary_type'] ?? '') ?: null;
                        $country = trim($_POST['country'] ?? '') ?: null;
                        try {
                            $stmtM = $pdo->prepare('UPDATE users SET missionary_type = ?, country = ? WHERE id = ?');
                            $stmtM->execute([$missionaryType, $country, $userId]);
                        } catch (Throwable $e) {
                            error_log('Missionary fields update note: ' . $e->getMessage());
                        }
                    } elseif ($currentCategory === 'student') {
                        $campusName = trim($_POST['campus_name'] ?? '');
                        $gradYear = (int)($_POST['graduation_year'] ?? 0);
                        if ($campusName !== '') {
                            $stmtStudent = $pdo->prepare('
                                INSERT INTO students (user_id, campus_name, graduation_year)
                                VALUES (?, ?, ?)
                                ON DUPLICATE KEY UPDATE campus_name = VALUES(campus_name), graduation_year = VALUES(graduation_year)
                            ');
                            $stmtStudent->execute([$userId, $campusName, $gradYear ?: null]);
                        }
                    } elseif ($currentCategory === 'associate') {
                        $gradYear = (int)($_POST['graduation_year'] ?? 0);
                        $currentProf = trim($_POST['current_profession'] ?? '');
                        $stmtAssoc = $pdo->prepare('
                            INSERT INTO associates (user_id, graduation_year, current_profession)
                            VALUES (?, ?, ?)
                            ON DUPLICATE KEY UPDATE graduation_year = VALUES(graduation_year), current_profession = VALUES(current_profession)
                        ');
                        $stmtAssoc->execute([$userId, $gradYear ?: null, $currentProf ?: null]);
                    }

                    $pdo->commit();
                    $success = 'Profile details updated successfully.';
                    $_SESSION['user_name'] = $name;
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('Profile update error: ' . $e->getMessage());
                    $error = 'An error occurred while updating your profile. Please try again.';
                }
            }
        }
    }
}

// Fetch current user details
$stmt = $pdo->prepare('
    SELECT 
        id, name, email, whatsapp_phone, role, category, pfp_path,
        is_approved, created_at, date_of_birth,
        graduation_year, campus_role, employment_status,
        company_name, industry_profession, partnership_focus,
        contribution_phone
    FROM users 
    WHERE id = ? 
    LIMIT 1
');
$stmt->execute([$userId]);
$me = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$me) {
    header('Location: logout.php');
    exit;
}

// Check for optional missionary columns
$missionaryType = null;
$country = null;
try {
    $stmtM = $pdo->prepare('SELECT missionary_type, country FROM users WHERE id = ? LIMIT 1');
    $stmtM->execute([$userId]);
    $mRow = $stmtM->fetch(PDO::FETCH_ASSOC);
    if ($mRow) {
        $missionaryType = $mRow['missionary_type'] ?? null;
        $country = $mRow['country'] ?? null;
    }
} catch (Throwable $e) {}

// Fetch student info
$student = null;
if ($me['category'] === 'student') {
    try {
        $stmtS = $pdo->prepare('SELECT campus_name, graduation_year FROM students WHERE user_id = ? LIMIT 1');
        $stmtS->execute([$userId]);
        $student = $stmtS->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
}

// Fetch associate info
$associate = null;
if ($me['category'] === 'associate') {
    try {
        $stmtA = $pdo->prepare('SELECT graduation_year, current_profession FROM associates WHERE user_id = ? LIMIT 1');
        $stmtA->execute([$userId]);
        $associate = $stmtA->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
}

// Determine dashboard return route
$dashboardUrl = in_array($me['role'], ['admin', 'super_admin'], true) ? 'admin_dashboard.php' : 'member_dashboard.php';

$page_title = 'My Profile - JDM Kenya';
ob_start();
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h2><i class="bi bi-person-circle text-primary"></i> My Profile</h2>
                <p class="text-muted mb-0">View and manage your personal details, contact information, and membership account.</p>
            </div>
            <a href="<?= $dashboardUrl ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
</div>

<?php if ($success !== ''): ?>
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
        <i class="bi bi-check-circle-fill fs-5"></i>
        <div><?= escape($success) ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
        <div><?= escape($error) ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Left Column: Profile Picture & Membership Information -->
    <div class="col-lg-4 col-md-5">
        <!-- Profile Picture Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="mb-0"><i class="bi bi-camera"></i> Profile Picture</h5>
            </div>
            <div class="card-body text-center p-4">
                <div class="mb-3 d-flex justify-content-center">
                    <?php if (!empty($me['pfp_path']) && file_exists(dirname(__DIR__, 2) . '/' . $me['pfp_path'])): ?>
                        <img src="<?= escape($me['pfp_path']) ?>" alt="<?= escape($me['name']) ?>" class="rounded-circle shadow-sm" style="width: 140px; height: 140px; object-fit: cover; border: 4px solid #f8f9fa;">
                    <?php else: ?>
                        <div class="rounded-circle bg-light d-flex align-items-center justify-content-center shadow-sm" style="width: 140px; height: 140px; border: 4px solid #f8f9fa;">
                            <i class="bi bi-person text-secondary" style="font-size: 4.5rem;"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <h5 class="fw-bold mb-1"><?= escape($me['name']) ?></h5>
                <p class="text-muted small mb-3"><?= escape($me['email']) ?></p>

                <form method="post" enctype="multipart/form-data" class="text-start border-top pt-3">
                    <?= csrf_field() ?>
                    <label class="form-label small fw-semibold">Upload New Photo</label>
                    <input type="file" name="profile_picture" class="form-control form-control-sm mb-2" accept=".jpg,.jpeg,.png,.webp" required>
                    <small class="text-muted d-block mb-3" style="font-size: 0.8rem;">Supported formats: JPG, PNG, WEBP (Max 4MB).</small>
                    <button type="submit" name="upload_pfp" class="btn btn-sm btn-primary w-100">
                        <i class="bi bi-upload"></i> Update Photo
                    </button>
                </form>
            </div>
        </div>

        <!-- Membership Information (Strictly Read-Only) -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-light py-3 border-bottom">
                <h5 class="mb-0 text-dark"><i class="bi bi-shield-check text-primary"></i> Membership Details</h5>
            </div>
            <div class="card-body p-3">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                        <span class="text-muted small">Account Role</span>
                        <span>
                            <?php if ($me['role'] === 'super_admin'): ?>
                                <span class="badge bg-danger">JDM Leader</span>
                            <?php elseif ($me['role'] === 'admin'): ?>
                                <span class="badge bg-primary">Administrator</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Member</span>
                            <?php endif; ?>
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                        <span class="text-muted small">Membership Category</span>
                        <span class="badge bg-info text-dark"><?= escape(ucfirst(str_replace('_', ' ', $me['category']))) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                        <span class="text-muted small">Approval Status</span>
                        <span>
                            <?php if (!empty($me['is_approved'])): ?>
                                <span class="badge bg-success"><i class="bi bi-check-circle"></i> Approved</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark"><i class="bi bi-clock"></i> Pending Review</span>
                            <?php endif; ?>
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                        <span class="text-muted small">Member Since</span>
                        <span class="small fw-semibold"><?= !empty($me['created_at']) ? date('M d, Y', strtotime($me['created_at'])) : 'N/A' ?></span>
                    </li>
                </ul>
                <div class="alert alert-light border mt-3 mb-0 py-2 px-3 small text-muted">
                    <i class="bi bi-info-circle me-1"></i> Membership categories, verification, and access roles are managed by JDM Kenya leadership.
                </div>
            </div>
        </div>

        <!-- Account Security Information -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light py-3 border-bottom">
                <h5 class="mb-0 text-dark"><i class="bi bi-key text-primary"></i> Account Security</h5>
            </div>
            <div class="card-body p-3">
                <p class="small text-muted mb-3">Keep your account secure by using a strong password and updating it regularly.</p>
                <div class="d-grid gap-2">
                    <a href="reset_password.php" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-shield-lock"></i> Change Password
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Personal, Contact & Category-Specific Form -->
    <div class="col-lg-8 col-md-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light py-3 border-bottom">
                <h5 class="mb-0 text-dark"><i class="bi bi-pencil-square text-primary"></i> Edit Profile Information</h5>
            </div>
            <div class="card-body p-4">
                <form method="post">
                    <?= csrf_field() ?>

                    <!-- Section 1: Personal Information -->
                    <h6 class="fw-bold text-primary mb-3 border-bottom pb-2">
                        <i class="bi bi-person"></i> 1. Personal Information
                    </h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="<?= escape($me['name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" value="<?= escape($me['email']) ?>" required>
                            <small class="text-muted" style="font-size: 0.8rem;">Used for login and official notifications.</small>
                        </div>
                    </div>

                    <!-- Section 2: Contact Information -->
                    <h6 class="fw-bold text-primary mb-3 border-bottom pb-2">
                        <i class="bi bi-telephone"></i> 2. Contact Information
                    </h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">WhatsApp Phone <span class="text-danger">*</span></label>
                            <input type="tel" name="whatsapp_phone" class="form-control" placeholder="e.g. 254712345678" value="<?= escape($me['whatsapp_phone']) ?>" required>
                            <small class="text-muted" style="font-size: 0.8rem;">Used for GBS and ministry communication.</small>
                        </div>
                        <?php if ($me['category'] === 'partner'): ?>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Contribution Phone</label>
                                <input type="tel" name="contribution_phone" class="form-control" placeholder="e.g. 254712345678" value="<?= escape($me['contribution_phone'] ?? '') ?>">
                                <small class="text-muted" style="font-size: 0.8rem;">Dedicated phone for partnership contributions.</small>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Section 3: Category-Specific Information -->
                    <?php if ($me['category'] === 'student'): ?>
                        <h6 class="fw-bold text-primary mb-3 border-bottom pb-2">
                            <i class="bi bi-mortarboard"></i> 3. Student Information
                        </h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Campus Name</label>
                                <select name="campus_name" class="form-select">
                                    <option value="" <?= empty($student['campus_name']) ? 'selected' : '' ?>>Select Campus</option>
                                    <?php
                                    $campuses = ['Main Campus', 'Upper Kabete', 'Lower Kabete', 'Chiromo', 'Kikuyu', 'Parklands'];
                                    foreach ($campuses as $campus): ?>
                                        <option value="<?= escape($campus) ?>" <?= ($student['campus_name'] ?? '') === $campus ? 'selected' : '' ?>>
                                            <?= escape($campus) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Expected Graduation Year</label>
                                <input type="number" name="graduation_year" class="form-control" min="2020" max="<?= date('Y') + 10 ?>" placeholder="e.g. <?= date('Y') + 2 ?>" value="<?= escape($student['graduation_year'] ?? '') ?>">
                            </div>
                        </div>
                    <?php elseif ($me['category'] === 'associate'): ?>
                        <h6 class="fw-bold text-primary mb-3 border-bottom pb-2">
                            <i class="bi bi-briefcase"></i> 3. Associate Information
                        </h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Graduation Year</label>
                                <input type="number" name="graduation_year" class="form-control" min="1950" max="<?= date('Y') ?>" placeholder="e.g. 2020" value="<?= escape($associate['graduation_year'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Current Profession / Field</label>
                                <input type="text" name="current_profession" class="form-control" maxlength="120" placeholder="e.g. Software Engineer, Educator" value="<?= escape($associate['current_profession'] ?? '') ?>">
                            </div>
                        </div>
                    <?php elseif ($me['category'] === 'partner'): ?>
                        <h6 class="fw-bold text-primary mb-3 border-bottom pb-2">
                            <i class="bi bi-building"></i> 3. Partner & Professional Information
                        </h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Company / Organization</label>
                                <input type="text" name="company_name" class="form-control" value="<?= escape($me['company_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Industry / Profession</label>
                                <input type="text" name="industry_profession" class="form-control" value="<?= escape($me['industry_profession'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Employment Status</label>
                                <select name="employment_status" class="form-select">
                                    <option value="" <?= empty($me['employment_status']) ? 'selected' : '' ?>>Select Status</option>
                                    <?php
                                    $empStatuses = ['Employed', 'Business Owner', 'Self-Employed', 'Freelancer', 'Other'];
                                    foreach ($empStatuses as $status): ?>
                                        <option value="<?= escape($status) ?>" <?= ($me['employment_status'] ?? '') === $status ? 'selected' : '' ?>>
                                            <?= escape($status) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Partnership Focus</label>
                                <input type="text" name="partnership_focus" class="form-control" placeholder="e.g. Missions, Youth, Bible Study" value="<?= escape($me['partnership_focus'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Campus Leadership Role (if applicable)</label>
                                <input type="text" name="campus_role" class="form-control" value="<?= escape($me['campus_role'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Graduation Year</label>
                                <input type="number" name="graduation_year" class="form-control" min="1950" max="<?= date('Y') ?>" value="<?= escape($me['graduation_year'] ?? '') ?>">
                            </div>
                        </div>
                    <?php elseif ($me['category'] === 'missionary'): ?>
                        <h6 class="fw-bold text-primary mb-3 border-bottom pb-2">
                            <i class="bi bi-globe-americas"></i> 3. Missionary Details
                        </h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Missionary Type</label>
                                <input type="text" name="missionary_type" class="form-control" placeholder="e.g. Full-time, Field, Tentmaker" value="<?= escape($missionaryType ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Country / Region of Mission</label>
                                <input type="text" name="country" class="form-control" placeholder="e.g. Kenya, Uganda, South Sudan" value="<?= escape($country ?? '') ?>">
                            </div>
                        </div>
                    <?php elseif (in_array($me['role'], ['admin', 'super_admin'], true)): ?>
                        <h6 class="fw-bold text-primary mb-3 border-bottom pb-2">
                            <i class="bi bi-shield-lock"></i> 3. Administrative Responsibility
                        </h6>
                        <div class="alert alert-light border p-3 mb-4">
                            <p class="mb-1 fw-semibold text-dark"><i class="bi bi-award-fill text-warning me-1"></i> Authorized Administrative Account</p>
                            <p class="small text-muted mb-0">You have management access across the JDM Kenya portal. Contact Super Administrators for role assignment adjustments.</p>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex flex-wrap gap-2 pt-3 border-top">
                        <button class="btn btn-primary px-4" type="submit" name="update_profile">
                            <i class="bi bi-check2-circle"></i> Save Profile Changes
                        </button>
                        <a class="btn btn-outline-secondary" href="<?= $dashboardUrl ?>">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/portal/layout.php';
