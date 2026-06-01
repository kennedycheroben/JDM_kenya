<?php

require_once dirname(__FILE__) . '/../../core/db_connect.php';

if (isset($_SESSION['user_role']) && !empty($_SESSION['user_role'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $whatsappPhone = trim($_POST['whatsapp_phone'] ?? '');
    $category = strtolower(trim($_POST['category'] ?? ''));
        $campusName = trim($_POST['campus_name'] ?? '');
        $graduationYear = trim($_POST['graduation_year'] ?? '');
        $currentProfession = trim($_POST['current_profession'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm = trim($_POST['confirm_password'] ?? '');
    
    // Birthday fields
    $birthYear = (int)($_POST['birth_year'] ?? 0);
    $birthMonth = (int)($_POST['birth_month'] ?? 0);
    $birthDay = (int)($_POST['birth_day'] ?? 0);

    $allowedCategories = ['student', 'associate', 'partner'];
    $allowedCampuses = ['Main Campus', 'Upper Kabete', 'Lower Kabete', 'Chiromo', 'Kikuyu', 'Parklands'];

    if ($name === '' || $email === '' || $whatsappPhone === '' || $category === '' || $password === '' || $confirm === '') {
        $error = 'All fields are required.';
    } elseif ($birthYear === 0 || $birthMonth === 0 || $birthDay === 0) {
        $error = 'Please select your complete date of birth.';
    } elseif (!checkdate($birthMonth, $birthDay, $birthYear)) {
        $error = 'Please enter a valid date of birth.';
    } elseif ($birthYear < 1920 || $birthYear > (int)date('Y') - 13) {
        $error = 'Please enter a valid birth year.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid email address.';
    } elseif (($category === 'associate' || $category === 'partner') && ($graduationYear === '' || $currentProfession === '')) {
        $error = 'Please provide graduation year and current profession for this category.';
    } elseif (!in_array($category, $allowedCategories, true)) {
        $error = 'Please select a valid member category.';
    } elseif ($category === 'student' && !in_array($campusName, $allowedCampuses, true)) {
        $error = 'Please select a valid campus for students.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'That email is already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            try {
                $pdo->beginTransaction();
                $dateOfBirth = sprintf('%04d-%02d-%02d', $birthYear, $birthMonth, $birthDay);
                $insert = $pdo->prepare('INSERT INTO users (name, email, whatsapp_phone, password, role, category, date_of_birth) VALUES (?, ?, ?, ?, ?, ?, ?);');
                $insert->execute([$name, $email, $whatsappPhone, $hash, 'member', $category, $dateOfBirth]);
                $userId = (int)$pdo->lastInsertId();

                if ($category === 'student') {
                    $stmt2 = $pdo->prepare('INSERT INTO students (user_id, campus_name) VALUES (?, ?)');
                    $stmt2->execute([$userId, $campusName]);
                } elseif ($category === 'partner') {
                    $stmt2 = $pdo->prepare('INSERT INTO marketplace_partners (user_id, graduation_year, current_profession) VALUES (?, ?, ?)');
                    $stmt2->execute([$userId, $graduationYear !== '' ? $graduationYear : null, $currentProfession !== '' ? $currentProfession : null]);
                } elseif ($category === 'associate') {
                        $stmt2 = $pdo->prepare('INSERT INTO associates (user_id, graduation_year, current_profession) VALUES (?, ?, ?)');
                        $stmt2->execute([$userId, $graduationYear !== '' ? $graduationYear : null, $currentProfession !== '' ? $currentProfession : null]);
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
                // fall through to show error
            } else {
            header('Location: login.php');
            exit;
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
    <link rel="stylesheet" href="/JDM_kenya/assets/css/style.css">
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
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email address</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">WhatsApp Phone Number</label>
                            <input type="tel" name="whatsapp_phone" class="form-control" placeholder="0712345678" required>
                            <div class="form-text">This number is for WhatsApp connectivity.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Member Category</label>
                            <select name="category" id="category" class="form-select" required>
                                <option value="" selected disabled>Select category</option>
                                <option value="student">Student</option>
                                <option value="associate">Associate</option>
                                <option value="partner">Partner</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date of Birth *</label>
                            <div class="row g-2">
                                <div class="col-4">
                                    <select name="birth_year" id="birth_year" class="form-select" required>
                                        <option value="" selected>Year</option>
                                        <?php for ($y = (int)date('Y') - 13; $y >= 1920; $y--): ?>
                                            <option value="<?= $y ?>"><?= $y ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-4">
                                    <select name="birth_month" id="birth_month" class="form-select" required>
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
                                    <select name="birth_day" id="birth_day" class="form-select" required>
                                        <option value="" selected>Day</option>
                                        <?php for ($d = 1; $d <= 31; $d++): ?>
                                            <option value="<?= $d ?>"><?= $d ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-text">Required for birthday notifications and age verification</div>
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
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const category = document.getElementById('category');
        const campusWrapper = document.getElementById('campusWrapper');
        const campus = document.getElementById('campus_name');
        const gradYearFields = document.getElementById('gradYearFields');
        const gradYearInput = document.getElementById('graduation_year');
        const professionFields = document.getElementById('professionFields');
        const professionInput = document.getElementById('current_profession');

        function updateFields() {
            if (!category) return;
            
            const selectedCategory = category.value;
            const isStudent = selectedCategory === 'student';
            const isAssociateOrPartner = selectedCategory === 'associate' || selectedCategory === 'partner';

            // Handle Campus field
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

            // Handle Graduation Year field
            if (gradYearFields && gradYearInput) {
                if (isAssociateOrPartner) {
                    gradYearFields.classList.remove('d-none');
                    gradYearInput.setAttribute('required', 'required');
                } else {
                    gradYearFields.classList.add('d-none');
                    gradYearInput.removeAttribute('required');
                    gradYearInput.value = '';
                }
            }

            // Handle Profession field
            if (professionFields && professionInput) {
                if (isAssociateOrPartner) {
                    professionFields.classList.remove('d-none');
                    professionInput.setAttribute('required', 'required');
                } else {
                    professionFields.classList.add('d-none');
                    professionInput.removeAttribute('required');
                    professionInput.value = '';
                }
            }
        }

        if (category) {
            category.addEventListener('change', updateFields);
            // Initialize on page load
            updateFields();
        }
    });
</script>
