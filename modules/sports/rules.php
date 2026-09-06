<?php
require_once dirname(__DIR__, 2) . '/core/db_connect.php';
$rulesUserId=(int)($_SESSION['user_id']??0);$rulesApplication=$rulesUserId?sports_application_for_user($pdo,$rulesUserId):null;
$nextUrl = !$rulesUserId ? BASE_PATH.'/signup.php' : ($rulesApplication ? BASE_PATH.'/sports_application_status.php' : BASE_PATH.'/join_sports_ministry.php');
$nextLabel = !$rulesUserId ? 'Continue to registration' : ($rulesApplication ? 'Return to application status' : 'Join Sports Ministry');
$sections = [
    'Christian character and respect' => [
        'Treat teammates, opponents, coaches, officials, volunteers and members of the public with dignity.',
        'Bullying, harassment, discrimination, threats, fighting, abusive language and deliberate humiliation are prohibited.',
        'Show honesty, humility, teamwork, self-control and good sportsmanship on and off the pitch.',
        'Respect match officials and use the approved team process to raise concerns.',
    ],
    'Attendance and communication' => [
        'Arrive at the communicated time and notify an authorized team official when unable to attend.',
        'Follow safe preparation, venue and equipment instructions issued by authorized Sports Ministry officials.',
        'Do not publish private team arrangements, another participant’s contact information or a minor’s travel details.',
        'Repeated unexplained absence may affect selection but must be reviewed fairly.',
    ],
    'Safety and safeguarding' => [
        'Follow reasonable safety directions and report unsafe equipment, injuries or dangerous conduct promptly.',
        'Participants must not be pressured to play while injured or unwell.',
        'Alcohol, illegal drugs, weapons and participation while intoxicated are prohibited at ministry activities.',
        'Adults must maintain appropriate boundaries with minors. Private or secretive adult-minor arrangements are prohibited.',
        'Guardian authorization is mandatory for applicants under 18. Guardian information remains private.',
        'Safeguarding concerns must be reported promptly to JDM Kenya leadership or an authorized safeguarding contact.',
    ],
    'Selection, positions and jerseys' => [
        'A preferred position or jersey number is a request and is not guaranteed.',
        'Official positions, responsibilities and jersey numbers are assigned by authorized Sports Ministry administration.',
        'Selection may consider attendance, conduct, development, fitness, team balance and safety.',
        'Players must care for borrowed kits and equipment and return them when requested.',
    ],
    'Privacy, photographs and public profiles' => [
        'After approval and review, public information may include name, age, general neighbourhood, education level, position, official jersey, approved photograph, achievements and statistics.',
        'Phone numbers, email addresses, exact birth dates, guardian details, precise addresses, identity documents, medical details, internal notes and personal payment details are never public.',
        'A profile is not published until approval, active membership, policy acknowledgement and administrative review are complete.',
        'Suspended, withdrawn, rejected and inactive participants do not appear in the active public roster.',
        'A material privacy-policy change may require renewed acknowledgement.',
    ],
    'Discipline, suspension and review' => [
        'Concerns must be reviewed fairly, confidentially and proportionately by authorized leadership.',
        'Serious safety or safeguarding risks may result in immediate temporary removal from Sports Ministry activities.',
        'Sports Ministry rejection or suspension does not automatically suspend the participant’s entire JDM account.',
        'Application, appointment and team history is preserved after rejection, withdrawal, suspension or replacement.',
        'A participant may request an explanation or review through official JDM Kenya leadership channels.',
    ],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="Participation, safeguarding and conduct rules for JDM Kenya Sports Ministry.">
    <title>Sports Ministry Rules | JDM Kenya</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/sports.css">
</head>
<body class="bg-light">
<a class="visually-hidden-focusable" href="#rules">Skip to rules</a>
<nav class="navbar navbar-dark bg-dark"><div class="container"><a class="navbar-brand" href="<?= BASE_PATH ?>/sports.php">Sports Ministry</a><a class="btn btn-warning btn-sm" href="<?= escape($nextUrl) ?>"><?= escape($nextLabel) ?></a></div></nav>
<header class="bg-success text-white py-5"><div class="container" style="max-width:960px"><p class="text-uppercase fw-semibold mb-2">JDM Kenya</p><h1 class="display-5 fw-bold">Sports Ministry rules</h1><p class="lead mb-0">Standards for safe, respectful and Christ-centred football participation.</p></div></header>
<main id="rules" class="container py-5" style="max-width:960px">
    <div class="alert alert-info"><strong>Policy version: 16 August 2026.</strong> Applicants must read and accept these rules. A parent or guardian must authorize participation by anyone under 18.</div>
    <?php $number = 1; foreach ($sections as $heading => $rules): ?>
        <section class="card shadow-sm mb-4"><div class="card-body p-4"><h2 class="h4"><?= $number++ ?>. <?= escape($heading) ?></h2><ul class="mb-0"><?php foreach ($rules as $rule): ?><li><?= escape($rule) ?></li><?php endforeach ?></ul></div></section>
    <?php endforeach ?>
    <section class="card border-success mb-4"><div class="card-body p-4"><h2 class="h4">Acknowledgement</h2><p>By accepting these rules, an applicant confirms understanding of the conduct, safeguarding, privacy and public-profile requirements. Acceptance does not guarantee application approval or team selection.</p><p class="mb-0">For a minor, the guardian confirms authorization and understanding of the safe public-profile policy.</p></div></section>
    <div class="d-flex flex-wrap gap-2"><a class="btn btn-success" href="<?= escape($nextUrl) ?>"><?= escape($nextLabel) ?></a><a class="btn btn-outline-secondary" href="<?= BASE_PATH ?>/sports.php">Return to Sports Ministry</a></div>
</main>
<footer class="bg-dark text-white py-4 mt-5"><div class="container" style="max-width:960px">&copy; <?= date('Y') ?> JDM Kenya · Sports Ministry</div></footer>
</body>
</html>
