<?php

require_once dirname(__FILE__) . '/../../core/db_connect.php';
require_once dirname(__FILE__) . '/../../core/sports_service.php';

if (isset($_SESSION['user_role']) && !empty($_SESSION['user_role'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!require_csrf()) { $error = $_SESSION['csrf_error'] ?? 'Session expired.'; unset($_SESSION['csrf_error']); }
    elseif (!check_rate_limit('signup', 3, 900)) { $error = 'Too many registration attempts. Please try again later.'; }
    else {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $category = strtolower(trim($_POST['category'] ?? ''));
    $whatsappPhone = trim($_POST['whatsapp_phone'] ?? '');
    $campusName = trim($_POST['campus_name'] ?? '');
    $graduationYear = trim($_POST['graduation_year'] ?? '');
    $currentProfession = trim($_POST['current_profession'] ?? '');
    $missionaryType = trim($_POST['missionary_type'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $birthYear = (int)($_POST['birth_year'] ?? 0);
    $birthMonth = (int)($_POST['birth_month'] ?? 0);
    $birthDay = (int)($_POST['birth_day'] ?? 0);

    $allowedCategories = ['student', 'associate', 'partner', 'missionary', 'sports_ministry', 'other'];
    $allowedCampuses = ['Main Campus', 'Upper Kabete', 'Lower Kabete', 'Chiromo', 'Kikuyu', 'Parklands'];

    $sportsValidation = null;
    if ($category === 'sports_ministry') {
        $sportsValidation = sports_validate_application($_POST, new DateTimeImmutable('today', new DateTimeZone('Africa/Nairobi')));
    }

    if ($name === '' || $email === '' || $category === '' || $password === '' || $confirm === '') {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid email address.';
    } elseif (!in_array($category, $allowedCategories, true)) {
        $error = 'Please select a valid member category.';
    } elseif ($category === 'student' && !in_array($campusName, $allowedCampuses, true)) {
        $error = 'Please select a valid campus for students.';
    } elseif ($category === 'associate' && ($graduationYear === '' || $currentProfession === '')) {
        $error = 'Please provide graduation year and current profession for this category.';
    } elseif ($category === 'missionary' && $country === '') {
        $error = 'Please select your country of origin.';
    } elseif ($category !== 'missionary' && $whatsappPhone === '') {
        $error = 'WhatsApp phone number is required.';
    } elseif ($category === 'sports_ministry' && !empty($sportsValidation['errors'])) {
        $error = $sportsValidation['errors'][0];
    } elseif ($birthYear !== 0 && (!checkdate($birthMonth, $birthDay, $birthYear) || $birthYear < 1920 || $birthYear > (int)date('Y') - 13)) {
        $error = 'Please enter a valid date of birth.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE TRIM(LOWER(email)) = TRIM(LOWER(?)) LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'That email is already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            try {
                $pdo->beginTransaction();
                $dateOfBirth = $category === 'sports_ministry'
                    ? $sportsValidation['dob']
                    : (($birthYear !== 0 && $birthMonth !== 0 && $birthDay !== 0)
                    ? sprintf('%04d-%02d-%02d', $birthYear, $birthMonth, $birthDay)
                    : null);

                $gradYearParam = null;
                $campusRoleParam = null;
                $employmentStatusParam = null;
                $companyNameParam = null;
                $industryProfessionParam = null;
                $partnershipFocusParam = null;
                $contributionPhoneParam = null;

                if ($category === 'partner') {
                    $gradYearParam = !empty($_POST['partner_graduation_year']) ? (int)$_POST['partner_graduation_year'] : null;
                    $campusRoleParam = !empty($_POST['partner_campus_role']) ? trim($_POST['partner_campus_role']) : null;

                    $empStatusRaw = !empty($_POST['partner_employment_status']) ? trim($_POST['partner_employment_status']) : null;
                    if ($empStatusRaw === 'Other' && !empty($_POST['partner_employment_status_other'])) {
                        $employmentStatusParam = trim($_POST['partner_employment_status_other']);
                    } else {
                        $employmentStatusParam = $empStatusRaw ?: null;
                    }

                    $companyNameParam = !empty($_POST['partner_company_name']) ? trim($_POST['partner_company_name']) : null;

                    $indProfRaw = !empty($_POST['partner_industry_profession']) ? trim($_POST['partner_industry_profession']) : null;
                    if ($indProfRaw === 'Other' && !empty($_POST['partner_industry_profession_other'])) {
                        $industryProfessionParam = trim($_POST['partner_industry_profession_other']);
                    } else {
                        $industryProfessionParam = $indProfRaw ?: null;
                    }

                    $partnershipFocusParam = !empty($_POST['partner_partnership_focus']) ? trim($_POST['partner_partnership_focus']) : null;
                    $contributionPhoneParam = !empty($_POST['partner_contribution_phone']) ? trim($_POST['partner_contribution_phone']) : null;
                } elseif ($category === 'associate') {
                    $gradYearParam = $graduationYear !== '' ? (int)$graduationYear : null;
                    $industryProfessionParam = $currentProfession !== '' ? trim($currentProfession) : null;
                }

                $isApproved = in_array($category, ['partner', 'missionary', 'sports_ministry'], true) ? 0 : 1;
                if ($category === 'sports_ministry') {
                    $whatsappPhone = sports_normalize_phone($whatsappPhone) ?? $whatsappPhone;
                }

                $insert = $pdo->prepare('
                    INSERT INTO users (
                        name, email, whatsapp_phone, password, role, category, date_of_birth,
                        graduation_year, campus_role, employment_status, company_name,
                        industry_profession, partnership_focus, contribution_phone,
                        is_approved, missionary_type, country
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);
                ');
                $insert->execute([
                    $name, $email, $whatsappPhone, $hash, 'member', $category, $dateOfBirth,
                    $gradYearParam, $campusRoleParam, $employmentStatusParam, $companyNameParam,
                    $industryProfessionParam, $partnershipFocusParam, $contributionPhoneParam,
                    $isApproved, $missionaryType ?: null, $country ?: null
                ]);
                $userId = (int)$pdo->lastInsertId();

                if ($category === 'student') {
                    $stmt2 = $pdo->prepare('INSERT INTO students (user_id, campus_name) VALUES (?, ?)');
                    $stmt2->execute([$userId, $campusName]);
                } elseif ($category === 'partner') {
                    $stmt2 = $pdo->prepare('INSERT INTO marketplace_partners (user_id, business_name, current_profession, graduation_year) VALUES (?, ?, ?, ?)');
                    $stmt2->execute([$userId, $companyNameParam, $industryProfessionParam, $gradYearParam]);
                } elseif ($category === 'associate') {
                    $stmt2 = $pdo->prepare('INSERT INTO associates (user_id, graduation_year, current_profession) VALUES (?, ?, ?)');
                    $stmt2->execute([$userId, $gradYearParam, $industryProfessionParam]);
                } elseif ($category === 'sports_ministry') {
                    $isMinor = $sportsValidation['age'] < 18;
                    $now = (new DateTimeImmutable('now', new DateTimeZone('Africa/Nairobi')))->format('Y-m-d H:i:s');
                    $hasApplicationSource=sports_column_exists($pdo,'sports_applications','application_source');
                    $stmt2 = $pdo->prepare($hasApplicationSource ? 'INSERT INTO sports_applications
                        (user_id,application_source,date_of_birth,general_estate,education_level,primary_position,preferred_jersey_number,
                         guardian_name,guardian_phone,guardian_consent_at,guardian_policy_version,rules_accepted_at,
                         publication_acknowledged_at,publication_policy_version,status,submitted_at)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)' : 'INSERT INTO sports_applications
                        (user_id,date_of_birth,general_estate,education_level,primary_position,preferred_jersey_number,
                         guardian_name,guardian_phone,guardian_consent_at,guardian_policy_version,rules_accepted_at,
                         publication_acknowledged_at,publication_policy_version,status,submitted_at)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                    $applicationValues=[
                        $userId, 'new_user', $sportsValidation['dob'], trim($_POST['general_estate']), $_POST['education_level'],
                        $_POST['primary_position'], $sportsValidation['jersey'],
                        $isMinor ? trim($_POST['guardian_name']) : null,
                        $isMinor ? sports_normalize_phone($_POST['guardian_phone']) : null,
                        $isMinor ? $now : null, $isMinor ? SPORTS_POLICY_VERSION : null,
                        $now, $now, SPORTS_POLICY_VERSION, 'pending', $now
                    ];if(!$hasApplicationSource)array_splice($applicationValues,1,1);$stmt2->execute($applicationValues);
                    $reviewers = $pdo->query("SELECT id FROM users WHERE role='super_admin' UNION SELECT user_id FROM sports_admin_assignments WHERE status='active' AND ended_at IS NULL")->fetchAll(PDO::FETCH_COLUMN);
                    foreach (array_unique(array_map('intval', $reviewers)) as $reviewerId) {
                        sports_notify($pdo, $userId, $reviewerId, 'A new Sports Ministry application is awaiting authorized review.');
                    }
                }

                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("DB Insert Error: " . $e->getMessage());
                $error = 'Registration failed: ' . $e->getMessage();
            }
            if ($error !== '') {
            } else {
            $redirectParam = in_array($category, ['partner', 'missionary', 'sports_ministry'], true) ? '?registration=pending' : '';
            header('Location: login.php' . $redirectParam);
            exit;
            }
        }
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JDM Kenya | Sign Up</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css">
</head>
<body>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h3 class="mb-3">Create Your Account</h3>
                    <p class="text-muted">Join JDM Kenya to access discipleship PDFs and portal resources.</p>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= escape($error) ?></div>
                    <?php elseif ($success): ?>
                        <div class="alert alert-success"><?= escape($success) ?></div>
                    <?php endif; ?>
                    <form method="post" novalidate>
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email address</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Member Category</label>
                            <select name="category" id="category" class="form-select" required>
                                <option value="" selected disabled>Select category</option>
                                <option value="student">Student</option>
                                <option value="associate">Associate</option>
                                <option value="partner">Office Bearer</option>
                                <option value="missionary">Missionary</option>
                                <option value="sports_ministry">Sports Ministry</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <fieldset id="sportsFields" class="card border-success mb-3 d-none" aria-describedby="sportsPrivacyNote">
                            <div class="card-body">
                                <legend class="h5">Sports Ministry application</legend>
                                <p id="sportsPrivacyNote" class="small text-muted">Your name, calculated age, general estate, education level, playing position, approved photo and verified sports information may be public after approval and review. Your exact birth date, phone and guardian details always remain private.</p>
                                <div class="mb-3"><label for="sports_date_of_birth" class="form-label">Date of birth</label><input type="date" class="form-control sports-required" id="sports_date_of_birth" name="sports_date_of_birth" max="<?= date('Y-m-d') ?>"></div>
                                <div class="mb-3"><label for="general_estate" class="form-label">General estate or neighbourhood</label><input class="form-control sports-required" id="general_estate" name="general_estate" maxlength="120" placeholder="e.g. Umoja"></div>
                                <div class="mb-3"><label for="education_level" class="form-label">Education level</label><select class="form-select sports-required" id="education_level" name="education_level"><option value="">Select level</option><option value="primary_school">Primary school</option><option value="high_school">High school</option><option value="college_university">College/University</option><option value="graduate">Graduate</option></select></div>
                                <div class="mb-3"><label for="primary_position" class="form-label">Primary playing position</label><select class="form-select sports-required" id="primary_position" name="primary_position"><option value="">Select position</option><option value="goalkeeper">Goalkeeper</option><option value="right_back">Right Back</option><option value="centre_back">Centre Back</option><option value="left_back">Left Back</option><option value="defensive_midfielder">Defensive Midfielder</option><option value="central_midfielder">Central Midfielder</option><option value="attacking_midfielder">Attacking Midfielder</option><option value="right_winger">Right Winger</option><option value="left_winger">Left Winger</option><option value="striker">Striker</option><option value="not_sure">Not Sure Yet</option></select></div>
                                <div class="mb-3"><label for="preferred_jersey_number" class="form-label">Preferred jersey number</label><input type="number" min="1" max="99" class="form-control sports-required" id="preferred_jersey_number" name="preferred_jersey_number"><div class="form-text">A preference only; an official number is assigned by Sports Ministry administration.</div></div>
                                <div id="guardianFields" class="border rounded p-3 mb-3 d-none"><h6>Guardian authorization (under 18)</h6><div class="mb-3"><label for="guardian_name" class="form-label">Guardian name</label><input class="form-control guardian-required" id="guardian_name" name="guardian_name" maxlength="120"></div><div class="mb-3"><label for="guardian_phone" class="form-label">Guardian phone</label><input type="tel" class="form-control guardian-required" id="guardian_phone" name="guardian_phone" maxlength="30"></div><div class="form-check"><input class="form-check-input guardian-required" type="checkbox" id="guardian_consent" name="guardian_consent" value="1"><label class="form-check-label" for="guardian_consent">My guardian authorizes this application and the described safe public profile.</label></div></div>
                                <div class="form-check mb-2"><input class="form-check-input sports-required" type="checkbox" id="sports_rules_accepted" name="sports_rules_accepted" value="1"><label class="form-check-label" for="sports_rules_accepted">I have read and accept the <a href="<?= BASE_PATH ?>/sports_rules.php" target="_blank" rel="noopener">Sports Ministry rules<span class="visually-hidden"> (opens in a new tab)</span></a>.</label></div>
                                <div class="form-check"><input class="form-check-input sports-required" type="checkbox" id="publication_acknowledged" name="publication_acknowledged" value="1"><label class="form-check-label" for="publication_acknowledged">I acknowledge that the approved safe profile information described above will become publicly visible.</label></div>
                            </div>
                        </fieldset>
                        <div class="mb-3">
                            <label class="form-label">WhatsApp Phone Number</label>
                            <input type="number" name="whatsapp_phone" id="whatsapp_phone" class="form-control" placeholder="0712345678" required>
                            <div class="form-text">This number is for WhatsApp connectivity.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date of Birth <small class="text-muted">(recommended)</small></label>
                            <div class="row g-2">
                                <div class="col-4">
                                    <select name="birth_year" id="birth_year" class="form-select">
                                        <option value="" selected>Year</option>
                                        <?php for ($y = (int)date('Y') - 13; $y >= 1920; $y--): ?>
                                            <option value="<?= $y ?>"><?= $y ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-4">
                                    <select name="birth_month" id="birth_month" class="form-select">
                                        <option value="" selected>Month</option>
                                        <option value="1">January</option>
                                        <option value="2">February</option>
                                        <option value="3">March</option>
                                        <option value="4">April</option>
                                        <option value="5">May</option>
                                        <option value="6">June</option>
                                        <option value="7">July</option>
                                        <option value="8">August</option>
                                        <option value="9">September</option>
                                        <option value="10">October</option>
                                        <option value="11">November</option>
                                        <option value="12">December</option>
                                    </select>
                                </div>
                                <div class="col-4">
                                    <select name="birth_day" id="birth_day" class="form-select">
                                        <option value="" selected>Day</option>
                                        <?php for ($d = 1; $d <= 31; $d++): ?>
                                            <option value="<?= $d ?>"><?= $d ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-text">Optional but recommended for birthday notifications.</div>
                        </div>
                        <div class="mb-3 d-none" id="campusWrapper">
                            <label class="form-label">Campus</label>
                            <select name="campus_name" id="campus_name" class="form-select">
                                <option value="" selected disabled>Select campus</option>
                                <option value="Main Campus">Main Campus</option>
                                <option value="Upper Kabete">Upper Kabete</option>
                                <option value="Lower Kabete">Lower Kabete</option>
                                <option value="Chiromo">Chiromo</option>
                                <option value="Kikuyu">Kikuyu</option>
                                <option value="Parklands">Parklands</option>
                            </select>
                        </div>
                            <div class="mb-3 d-none" id="gradYearFields">
                                <label class="form-label">Graduation Year</label>
                                <input type="number" name="graduation_year" id="graduation_year" class="form-control" min="1950" max="<?= date('Y') ?>">
                            </div>
                            <div class="mb-3 d-none" id="professionFields">
                                <label class="form-label">Current Profession</label>
                                <input type="text" name="current_profession" id="current_profession" class="form-control" maxlength="120">
                            </div>

                            <div id="missionary-fields" style="display: none;">
                                <div class="card p-3 mb-3 border-info" style="background-color: #f0f8ff;">
                                    <h6 class="text-info fw-bold mb-3"><i class="fa fa-globe"></i> Missionary Details</h6>
                                    <div class="mb-3">
                                        <label class="form-label">Missionary Type</label>
                                        <select name="missionary_type" id="missionary_type" class="form-select">
                                            <option value="" selected disabled>Select missionary type</option>
                                            <option value="short_term">Short Term Missionary</option>
                                            <option value="long_term">Long Term Missionary</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Country of Origin</label>
                                        <select name="country" id="country" class="form-select">
                                            <option value="" selected disabled>Select your country</option>
                                            <option value="Afghanistan">Afghanistan</option>
                                            <option value="Albania">Albania</option>
                                            <option value="Algeria">Algeria</option>
                                            <option value="Andorra">Andorra</option>
                                            <option value="Angola">Angola</option>
                                            <option value="Antigua and Barbuda">Antigua and Barbuda</option>
                                            <option value="Argentina">Argentina</option>
                                            <option value="Armenia">Armenia</option>
                                            <option value="Australia">Australia</option>
                                            <option value="Austria">Austria</option>
                                            <option value="Azerbaijan">Azerbaijan</option>
                                            <option value="Bahamas">Bahamas</option>
                                            <option value="Bahrain">Bahrain</option>
                                            <option value="Bangladesh">Bangladesh</option>
                                            <option value="Barbados">Barbados</option>
                                            <option value="Belarus">Belarus</option>
                                            <option value="Belgium">Belgium</option>
                                            <option value="Belize">Belize</option>
                                            <option value="Benin">Benin</option>
                                            <option value="Bhutan">Bhutan</option>
                                            <option value="Bolivia">Bolivia</option>
                                            <option value="Bosnia and Herzegovina">Bosnia and Herzegovina</option>
                                            <option value="Botswana">Botswana</option>
                                            <option value="Brazil">Brazil</option>
                                            <option value="Brunei">Brunei</option>
                                            <option value="Bulgaria">Bulgaria</option>
                                            <option value="Burkina Faso">Burkina Faso</option>
                                            <option value="Burundi">Burundi</option>
                                            <option value="Cabo Verde">Cabo Verde</option>
                                            <option value="Cambodia">Cambodia</option>
                                            <option value="Cameroon">Cameroon</option>
                                            <option value="Canada">Canada</option>
                                            <option value="Central African Republic">Central African Republic</option>
                                            <option value="Chad">Chad</option>
                                            <option value="Chile">Chile</option>
                                            <option value="China">China</option>
                                            <option value="Colombia">Colombia</option>
                                            <option value="Comoros">Comoros</option>
                                            <option value="Congo">Congo</option>
                                            <option value="Costa Rica">Costa Rica</option>
                                            <option value="Croatia">Croatia</option>
                                            <option value="Cuba">Cuba</option>
                                            <option value="Cyprus">Cyprus</option>
                                            <option value="Czech Republic">Czech Republic</option>
                                            <option value="Denmark">Denmark</option>
                                            <option value="Djibouti">Djibouti</option>
                                            <option value="Dominica">Dominica</option>
                                            <option value="Dominican Republic">Dominican Republic</option>
                                            <option value="Ecuador">Ecuador</option>
                                            <option value="Egypt">Egypt</option>
                                            <option value="El Salvador">El Salvador</option>
                                            <option value="Equatorial Guinea">Equatorial Guinea</option>
                                            <option value="Eritrea">Eritrea</option>
                                            <option value="Estonia">Estonia</option>
                                            <option value="Eswatini">Eswatini</option>
                                            <option value="Ethiopia">Ethiopia</option>
                                            <option value="Fiji">Fiji</option>
                                            <option value="Finland">Finland</option>
                                            <option value="France">France</option>
                                            <option value="Gabon">Gabon</option>
                                            <option value="Gambia">Gambia</option>
                                            <option value="Georgia">Georgia</option>
                                            <option value="Germany">Germany</option>
                                            <option value="Ghana">Ghana</option>
                                            <option value="Greece">Greece</option>
                                            <option value="Grenada">Grenada</option>
                                            <option value="Guatemala">Guatemala</option>
                                            <option value="Guinea">Guinea</option>
                                            <option value="Guinea-Bissau">Guinea-Bissau</option>
                                            <option value="Guyana">Guyana</option>
                                            <option value="Haiti">Haiti</option>
                                            <option value="Honduras">Honduras</option>
                                            <option value="Hungary">Hungary</option>
                                            <option value="Iceland">Iceland</option>
                                            <option value="India">India</option>
                                            <option value="Indonesia">Indonesia</option>
                                            <option value="Iran">Iran</option>
                                            <option value="Iraq">Iraq</option>
                                            <option value="Ireland">Ireland</option>
                                            <option value="Israel">Israel</option>
                                            <option value="Italy">Italy</option>
                                            <option value="Jamaica">Jamaica</option>
                                            <option value="Japan">Japan</option>
                                            <option value="Jordan">Jordan</option>
                                            <option value="Kazakhstan">Kazakhstan</option>
                                            <option value="Kenya">Kenya</option>
                                            <option value="Kiribati">Kiribati</option>
                                            <option value="Kuwait">Kuwait</option>
                                            <option value="Kyrgyzstan">Kyrgyzstan</option>
                                            <option value="Laos">Laos</option>
                                            <option value="Latvia">Latvia</option>
                                            <option value="Lebanon">Lebanon</option>
                                            <option value="Lesotho">Lesotho</option>
                                            <option value="Liberia">Liberia</option>
                                            <option value="Libya">Libya</option>
                                            <option value="Liechtenstein">Liechtenstein</option>
                                            <option value="Lithuania">Lithuania</option>
                                            <option value="Luxembourg">Luxembourg</option>
                                            <option value="Madagascar">Madagascar</option>
                                            <option value="Malawi">Malawi</option>
                                            <option value="Malaysia">Malaysia</option>
                                            <option value="Maldives">Maldives</option>
                                            <option value="Mali">Mali</option>
                                            <option value="Malta">Malta</option>
                                            <option value="Marshall Islands">Marshall Islands</option>
                                            <option value="Mauritania">Mauritania</option>
                                            <option value="Mauritius">Mauritius</option>
                                            <option value="Mexico">Mexico</option>
                                            <option value="Micronesia">Micronesia</option>
                                            <option value="Moldova">Moldova</option>
                                            <option value="Monaco">Monaco</option>
                                            <option value="Mongolia">Mongolia</option>
                                            <option value="Montenegro">Montenegro</option>
                                            <option value="Morocco">Morocco</option>
                                            <option value="Mozambique">Mozambique</option>
                                            <option value="Myanmar">Myanmar</option>
                                            <option value="Namibia">Namibia</option>
                                            <option value="Nauru">Nauru</option>
                                            <option value="Nepal">Nepal</option>
                                            <option value="Netherlands">Netherlands</option>
                                            <option value="New Zealand">New Zealand</option>
                                            <option value="Nicaragua">Nicaragua</option>
                                            <option value="Niger">Niger</option>
                                            <option value="Nigeria">Nigeria</option>
                                            <option value="North Korea">North Korea</option>
                                            <option value="North Macedonia">North Macedonia</option>
                                            <option value="Norway">Norway</option>
                                            <option value="Oman">Oman</option>
                                            <option value="Pakistan">Pakistan</option>
                                            <option value="Palau">Palau</option>
                                            <option value="Palestine">Palestine</option>
                                            <option value="Panama">Panama</option>
                                            <option value="Papua New Guinea">Papua New Guinea</option>
                                            <option value="Paraguay">Paraguay</option>
                                            <option value="Peru">Peru</option>
                                            <option value="Philippines">Philippines</option>
                                            <option value="Poland">Poland</option>
                                            <option value="Portugal">Portugal</option>
                                            <option value="Qatar">Qatar</option>
                                            <option value="Romania">Romania</option>
                                            <option value="Russia">Russia</option>
                                            <option value="Rwanda">Rwanda</option>
                                            <option value="Saint Kitts and Nevis">Saint Kitts and Nevis</option>
                                            <option value="Saint Lucia">Saint Lucia</option>
                                            <option value="Saint Vincent and the Grenadines">Saint Vincent and the Grenadines</option>
                                            <option value="Samoa">Samoa</option>
                                            <option value="San Marino">San Marino</option>
                                            <option value="Sao Tome and Principe">Sao Tome and Principe</option>
                                            <option value="Saudi Arabia">Saudi Arabia</option>
                                            <option value="Senegal">Senegal</option>
                                            <option value="Serbia">Serbia</option>
                                            <option value="Seychelles">Seychelles</option>
                                            <option value="Sierra Leone">Sierra Leone</option>
                                            <option value="Singapore">Singapore</option>
                                            <option value="Slovakia">Slovakia</option>
                                            <option value="Slovenia">Slovenia</option>
                                            <option value="Solomon Islands">Solomon Islands</option>
                                            <option value="Somalia">Somalia</option>
                                            <option value="South Africa">South Africa</option>
                                            <option value="South Korea">South Korea</option>
                                            <option value="South Sudan">South Sudan</option>
                                            <option value="Spain">Spain</option>
                                            <option value="Sri Lanka">Sri Lanka</option>
                                            <option value="Sudan">Sudan</option>
                                            <option value="Suriname">Suriname</option>
                                            <option value="Sweden">Sweden</option>
                                            <option value="Switzerland">Switzerland</option>
                                            <option value="Syria">Syria</option>
                                            <option value="Taiwan">Taiwan</option>
                                            <option value="Tajikistan">Tajikistan</option>
                                            <option value="Tanzania">Tanzania</option>
                                            <option value="Thailand">Thailand</option>
                                            <option value="Timor-Leste">Timor-Leste</option>
                                            <option value="Togo">Togo</option>
                                            <option value="Tonga">Tonga</option>
                                            <option value="Trinidad and Tobago">Trinidad and Tobago</option>
                                            <option value="Tunisia">Tunisia</option>
                                            <option value="Turkey">Turkey</option>
                                            <option value="Turkmenistan">Turkmenistan</option>
                                            <option value="Tuvalu">Tuvalu</option>
                                            <option value="Uganda">Uganda</option>
                                            <option value="Ukraine">Ukraine</option>
                                            <option value="United Arab Emirates">United Arab Emirates</option>
                                            <option value="United Kingdom">United Kingdom</option>
                                            <option value="United States">United States</option>
                                            <option value="Uruguay">Uruguay</option>
                                            <option value="Uzbekistan">Uzbekistan</option>
                                            <option value="Vanuatu">Vanuatu</option>
                                            <option value="Vatican City">Vatican City</option>
                                            <option value="Venezuela">Venezuela</option>
                                            <option value="Vietnam">Vietnam</option>
                                            <option value="Yemen">Yemen</option>
                                            <option value="Zambia">Zambia</option>
                                            <option value="Zimbabwe">Zimbabwe</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Specialized Fields for Office Bearers (Partners) -->
                            <div id="partner-fields" style="display: none;">
                                <div class="card p-3 mb-3 border-warning" style="background-color: #fffdf5;">
                                    <h6 class="text-warning fw-bold mb-3"><i class="fa fa-briefcase"></i> Office Bearer Details</h6>

                                    <div class="mb-3">
                                        <label class="form-label">Graduation Year (UON Alumni Verification)</label>
                                        <select name="partner_graduation_year" id="partner_graduation_year" class="form-select">
                                            <option value="" selected disabled>Select graduation year</option>
                                            <?php for ($y = 2026; $y >= 2010; $y--): ?>
                                                <option value="<?= $y ?>"><?= $y ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Campus Responsibility/Role Held</label>
                                        <select name="partner_campus_role" id="partner_campus_role" class="form-select">
                                            <option value="" selected disabled>Select former role</option>
                                            <option value="JDM Chairperson">JDM Chairperson</option>
                                            <option value="GBS leader">GBS leader</option>
                                            <option value="JDM leader">JDM leader</option>
                                            <option value="JDM Member">JDM Member</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Employment Status</label>
                                        <select name="partner_employment_status" id="partner_employment_status" class="form-select">
                                            <option value="" selected disabled>Select employment status</option>
                                            <option value="Employed">Employed</option>
                                            <option value="Business Owner">Business Owner</option>
                                            <option value="Self-Employed">Self-Employed</option>
                                            <option value="Freelancer">Freelancer</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    <div class="mb-3 d-none" id="employmentStatusOtherWrapper">
                                        <label class="form-label">Please specify employment status</label>
                                        <input type="text" name="partner_employment_status_other" id="partner_employment_status_other" class="form-control" placeholder="Specify status">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Organization / Business Name</label>
                                        <input type="text" name="partner_company_name" id="partner_company_name" class="form-control" placeholder="Workplace or name of enterprise">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Industry / Profession</label>
                                        <select name="partner_industry_profession" id="partner_industry_profession" class="form-select">
                                            <option value="" selected disabled>Select industry/profession</option>
                                            <option value="IT">IT</option>
                                            <option value="Finance">Finance</option>
                                            <option value="Engineering">Engineering</option>
                                            <option value="Healthcare">Healthcare</option>
                                            <option value="Business Services">Business Services</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    <div class="mb-3 d-none" id="industryOtherWrapper">
                                        <label class="form-label">Please specify industry/profession</label>
                                        <input type="text" name="partner_industry_profession_other" id="partner_industry_profession_other" class="form-control" placeholder="Specify industry/profession">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Partnership Focus Area</label>
                                        <select name="partner_partnership_focus" id="partner_partnership_focus" class="form-select">
                                            <option value="" selected disabled>Select primary focus</option>
                                            <option value="Missions & Evangelism Support">Missions & Evangelism Support</option>
                                            <option value="Student Welfare & Mentorship">Student Welfare & Mentorship</option>
                                            <option value="GBS/Discipleship Material Development">GBS/Discipleship Material Development</option>
                                            <option value="General Ministry Operations">General Ministry Operations</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Sign Up</button>
                    </form>
                    <div class="mt-4 text-center">
                        <p class="mb-0">Already a member? <a href="login.php">Sign In</a></p>
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

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_PATH ?>/assets/js/sports_registration.js" defer></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const category = document.getElementById('category');
        const campusWrapper = document.getElementById('campusWrapper');
        const campus = document.getElementById('campus_name');
        const whatsappPhone = document.getElementById('whatsapp_phone');

        const gradYearFields = document.getElementById('gradYearFields');
        const gradYearInput = document.getElementById('graduation_year');
        const professionFields = document.getElementById('professionFields');
        const professionInput = document.getElementById('current_profession');

        const partnerFields = document.getElementById('partner-fields');
        const partnerGradYear = document.getElementById('partner_graduation_year');
        const partnerCampusRole = document.getElementById('partner_campus_role');
        const partnerEmpStatus = document.getElementById('partner_employment_status');
        const partnerCompany = document.getElementById('partner_company_name');
        const partnerIndustry = document.getElementById('partner_industry_profession');
        const partnerFocus = document.getElementById('partner_partnership_focus');

        const missionaryFields = document.getElementById('missionary-fields');
        const missionaryType = document.getElementById('missionary_type');
        const country = document.getElementById('country');

        const empStatusOtherWrapper = document.getElementById('employmentStatusOtherWrapper');
        const empStatusOtherInput = document.getElementById('partner_employment_status_other');
        const industryOtherWrapper = document.getElementById('industryOtherWrapper');
        const industryOtherInput = document.getElementById('partner_industry_profession_other');

        if (partnerEmpStatus) {
            partnerEmpStatus.addEventListener('change', function() {
                if (this.value === 'Other') {
                    empStatusOtherWrapper.classList.remove('d-none');
                    empStatusOtherInput.setAttribute('required', 'required');
                } else {
                    empStatusOtherWrapper.classList.add('d-none');
                    empStatusOtherInput.removeAttribute('required');
                    empStatusOtherInput.value = '';
                }
            });
        }

        if (partnerIndustry) {
            partnerIndustry.addEventListener('change', function() {
                if (this.value === 'Other') {
                    industryOtherWrapper.classList.remove('d-none');
                    industryOtherInput.setAttribute('required', 'required');
                } else {
                    industryOtherWrapper.classList.add('d-none');
                    industryOtherInput.removeAttribute('required');
                    industryOtherInput.value = '';
                }
            });
        }

        function updateFields() {
            if (!category) return;

            const selectedCategory = category.value;
            const isStudent = selectedCategory === 'student';
            const isAssociate = selectedCategory === 'associate';
            const isPartner = selectedCategory === 'partner';
            const isMissionary = selectedCategory === 'missionary';

            if (campusWrapper && campus) {
                if (isStudent) {
                    campusWrapper.classList.remove('d-none');
                    campus.setAttribute('required', 'required');
                } else {
                    campusWrapper.classList.add('d-none');
                    campus.removeAttribute('required');
                    campus.value = '';
                }
            }

            if (gradYearFields && gradYearInput) {
                if (isAssociate) {
                    gradYearFields.classList.remove('d-none');
                    gradYearInput.setAttribute('required', 'required');
                } else {
                    gradYearFields.classList.add('d-none');
                    gradYearInput.removeAttribute('required');
                    gradYearInput.value = '';
                }
            }

            if (professionFields && professionInput) {
                if (isAssociate) {
                    professionFields.classList.remove('d-none');
                    professionInput.setAttribute('required', 'required');
                } else {
                    professionFields.classList.add('d-none');
                    professionInput.removeAttribute('required');
                    professionInput.value = '';
                }
            }

            if (missionaryFields) {
                if (isMissionary) {
                    missionaryFields.style.display = 'block';
                    missionaryType?.setAttribute('required', 'required');
                    country?.setAttribute('required', 'required');
                    if (whatsappPhone) {
                        whatsappPhone.placeholder = 'Optional';
                        whatsappPhone.removeAttribute('required');
                    }
                } else {
                    missionaryFields.style.display = 'none';
                    missionaryType?.removeAttribute('required');
                    country?.removeAttribute('required');
                    if (missionaryType) missionaryType.value = '';
                    if (country) country.value = '';
                    if (whatsappPhone) {
                        whatsappPhone.placeholder = '0712345678';
                        whatsappPhone.setAttribute('required', 'required');
                    }
                }
            }

            if (partnerFields) {
                if (isPartner) {
                    partnerFields.style.display = 'block';
                    partnerGradYear?.setAttribute('required', 'required');
                    partnerCampusRole?.setAttribute('required', 'required');
                    partnerEmpStatus?.setAttribute('required', 'required');
                    partnerCompany?.setAttribute('required', 'required');
                    partnerIndustry?.setAttribute('required', 'required');
                    partnerFocus?.setAttribute('required', 'required');
                } else {
                    partnerFields.style.display = 'none';
                    partnerGradYear?.removeAttribute('required');
                    partnerCampusRole?.removeAttribute('required');
                    partnerEmpStatus?.removeAttribute('required');
                    partnerCompany?.removeAttribute('required');
                    partnerIndustry?.removeAttribute('required');
                    partnerFocus?.removeAttribute('required');

                    if (partnerGradYear) partnerGradYear.value = '';
                    if (partnerCampusRole) partnerCampusRole.value = '';
                    if (partnerEmpStatus) partnerEmpStatus.value = '';
                    if (partnerCompany) partnerCompany.value = '';
                    if (partnerIndustry) partnerIndustry.value = '';
                    if (partnerFocus) partnerFocus.value = '';

                    empStatusOtherWrapper.classList.add('d-none');
                    empStatusOtherInput.value = '';
                    industryOtherWrapper.classList.add('d-none');
                    industryOtherInput.value = '';
                }
            }
        }

        if (category) {
            category.addEventListener('change', updateFields);
            updateFields();
        }
    });
</script>
