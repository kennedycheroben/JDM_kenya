<?php
require_once dirname(__DIR__, 2) . '/core/db_connect.php';
require_once dirname(__DIR__, 2) . '/core/sports_service.php';
header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: ' . BASE_PATH . '/login.php');
    exit;
}

$userStmt = $pdo->prepare('SELECT id,name,email,whatsapp_phone,role,category,is_approved,date_of_birth,pfp_path FROM users WHERE id=? LIMIT 1');
$userStmt->execute([$userId]);
$account = $userStmt->fetch(PDO::FETCH_ASSOC);
if (!$account) { header('Location: ' . BASE_PATH . '/logout.php'); exit; }

$generalApprovalRequired = in_array($account['category'], ['partner','missionary'], true);
if ($generalApprovalRequired && empty($account['is_approved'])) {
    http_response_code(403);
    exit('Your JDM account must be approved before you can apply to Sports Ministry.');
}

$existingApplication = sports_application_for_user($pdo, $userId);
$editing = $existingApplication && $existingApplication['application_source']==='existing_user' && $existingApplication['status']==='pending';
$applicationDetails = [];
if ($editing) {
    $details=$pdo->prepare('SELECT date_of_birth,general_estate,education_level,primary_position,preferred_jersey_number,guardian_name,guardian_phone,guardian_consent_at FROM sports_applications WHERE id=? AND user_id=? LIMIT 1');
    $details->execute([(int)$existingApplication['id'],$userId]);$applicationDetails=$details->fetch(PDO::FETCH_ASSOC)?:[];
} elseif ($existingApplication) {
    header('Location: ' . BASE_PATH . '/sports_application_status.php');
    exit;
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!require_csrf()) {
        $error = 'Your session expired. Please reload and try again.';
    } elseif (!check_rate_limit('join_sports_ministry', 5, 900)) {
        $error = 'Too many application attempts. Please wait and try again.';
    } else {
        $validation = sports_validate_application($_POST, new DateTimeImmutable('today', new DateTimeZone('Africa/Nairobi')));
        if ($validation['errors']) {
            $error = $validation['errors'][0];
        } else {
            try {
                $pdo->beginTransaction();
                $lock = $pdo->prepare('SELECT id,role,category,is_approved FROM users WHERE id=? FOR UPDATE');
                $lock->execute([$userId]);
                $lockedAccount = $lock->fetch(PDO::FETCH_ASSOC);
                if (!$lockedAccount) throw new RuntimeException('Account unavailable.');
                if (in_array($lockedAccount['category'], ['partner','missionary'], true) && empty($lockedAccount['is_approved'])) {
                    throw new RuntimeException('Your JDM account is not yet approved.');
                }
                $phone = sports_normalize_phone((string)$_POST['whatsapp_phone']);
                $now = (new DateTimeImmutable('now', new DateTimeZone('Africa/Nairobi')))->format('Y-m-d H:i:s');
                $minor = $validation['age'] < 18;
                $pdo->prepare('UPDATE users SET whatsapp_phone=?,date_of_birth=? WHERE id=?')->execute([$phone,$validation['dob'],$userId]);
                if ($editing) {
                    $editSourceSql=sports_column_exists($pdo,'sports_applications','application_source')?'application_source':"'existing_user' AS application_source";
                    $current=$pdo->prepare("SELECT id,status,$editSourceSql FROM sports_applications WHERE id=? AND user_id=? FOR UPDATE");$current->execute([(int)$existingApplication['id'],$userId]);$lockedApplication=$current->fetch(PDO::FETCH_ASSOC);
                    if(!$lockedApplication||$lockedApplication['status']!=='pending'||$lockedApplication['application_source']!=='existing_user')throw new RuntimeException('This application can no longer be edited.');
                    $update=$pdo->prepare('UPDATE sports_applications SET date_of_birth=?,general_estate=?,education_level=?,primary_position=?,preferred_jersey_number=?,guardian_name=?,guardian_phone=?,guardian_consent_at=?,guardian_policy_version=?,rules_accepted_at=?,publication_acknowledged_at=?,publication_policy_version=?,updated_at=NOW() WHERE id=? AND user_id=?');
                    $update->execute([$validation['dob'],trim($_POST['general_estate']),$_POST['education_level'],$_POST['primary_position'],$validation['jersey'],$minor?trim($_POST['guardian_name']):null,$minor?sports_normalize_phone($_POST['guardian_phone']):null,$minor?$now:null,$minor?SPORTS_POLICY_VERSION:null,$now,$now,SPORTS_POLICY_VERSION,(int)$existingApplication['id'],$userId]);
                    $applicationId=(int)$existingApplication['id'];sports_audit($pdo,$userId,'existing_user_application_updated','sports_application',$applicationId,['source'=>'existing_user']);
                } else {
                $duplicate = $pdo->prepare('SELECT id FROM sports_applications WHERE user_id=? LIMIT 1 FOR UPDATE');$duplicate->execute([$userId]);if($duplicate->fetchColumn())throw new RuntimeException('A Sports Ministry relationship already exists for this account.');
                $hasApplicationSource=sports_column_exists($pdo,'sports_applications','application_source');
                $insert = $pdo->prepare($hasApplicationSource ? 'INSERT INTO sports_applications
                    (user_id,application_source,date_of_birth,general_estate,education_level,primary_position,preferred_jersey_number,
                     guardian_name,guardian_phone,guardian_consent_at,guardian_policy_version,rules_accepted_at,
                     publication_acknowledged_at,publication_policy_version,status,submitted_at)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)' : 'INSERT INTO sports_applications
                    (user_id,date_of_birth,general_estate,education_level,primary_position,preferred_jersey_number,
                     guardian_name,guardian_phone,guardian_consent_at,guardian_policy_version,rules_accepted_at,
                     publication_acknowledged_at,publication_policy_version,status,submitted_at)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                $applicationValues=[
                    $userId,'existing_user',$validation['dob'],trim($_POST['general_estate']),$_POST['education_level'],
                    $_POST['primary_position'],$validation['jersey'],$minor?trim($_POST['guardian_name']):null,
                    $minor?sports_normalize_phone($_POST['guardian_phone']):null,$minor?$now:null,
                    $minor?SPORTS_POLICY_VERSION:null,$now,$now,SPORTS_POLICY_VERSION,'pending',$now
                ];if(!$hasApplicationSource)array_splice($applicationValues,1,1);$insert->execute($applicationValues);
                $applicationId = (int)$pdo->lastInsertId();
                sports_audit($pdo,$userId,'existing_user_application_submitted','sports_application',$applicationId,['source'=>'existing_user']);
                $reviewers=$pdo->query("SELECT id FROM users WHERE role='super_admin' UNION SELECT user_id FROM sports_admin_assignments WHERE status='active' AND ended_at IS NULL")->fetchAll(PDO::FETCH_COLUMN);
                foreach(array_unique(array_map('intval',$reviewers)) as $reviewerId) sports_notify($pdo,$userId,$reviewerId,'An existing JDM member submitted a Sports Ministry application for authorized review.');
                }
                $pdo->commit();
                header('Location: '.BASE_PATH.'/sports_application_status.php?'.($editing?'updated=1':'submitted=1'));
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('Existing-member sports application failed: '.$e->getMessage());
                $error = $e instanceof RuntimeException ? $e->getMessage() : 'Your application could not be submitted. Please try again.';
            }
        }
    }
}

$old = static function(string $key, string $fallback='') use ($applicationDetails): string { return escape($_POST[$key] ?? ($applicationDetails[$key] ?? $fallback)); };
$page_title='Join Sports Ministry - JDM Kenya';
ob_start();
?>
<div class="row justify-content-center"><div class="col-xl-9"><div class="card shadow-sm"><div class="card-body p-3 p-md-5">
<h1 class="h2"><?=$editing?'Update Sports Ministry application':'Join Sports Ministry'?></h1><p class="text-muted">Use your existing JDM account. Your current role, category and normal portal access will not change while this application is reviewed.</p>
<?php if($error):?><div class="alert alert-danger" role="alert"><?=escape($error)?></div><?php endif?>
<div class="d-flex align-items-center gap-3 border rounded p-3 mb-4"><?php if($account['pfp_path']):?><img src="<?=escape($account['pfp_path'])?>" alt="Your current profile photograph" class="rounded-circle" width="64" height="64" style="object-fit:cover"><?php endif?><div><strong><?=escape($account['name'])?></strong><div class="text-muted"><?=escape($account['email'])?></div></div></div>
<form method="post" novalidate><?=csrf_field()?>
<div class="row g-3"><div class="col-md-6"><label for="sports_date_of_birth" class="form-label">Date of birth</label><input type="date" id="sports_date_of_birth" name="sports_date_of_birth" class="form-control" max="<?=date('Y-m-d')?>" value="<?=$old('sports_date_of_birth',(string)($applicationDetails['date_of_birth']??$account['date_of_birth']))?>" required></div>
<div class="col-md-6"><label for="whatsapp_phone" class="form-label">Phone/WhatsApp number</label><input type="tel" id="whatsapp_phone" name="whatsapp_phone" class="form-control" maxlength="30" value="<?=$old('whatsapp_phone',(string)$account['whatsapp_phone'])?>" required><div class="form-text">Saving this form updates your authoritative JDM phone number.</div></div>
<div class="col-md-6"><label for="general_estate" class="form-label">General estate or neighbourhood</label><input id="general_estate" name="general_estate" class="form-control" maxlength="120" value="<?=$old('general_estate')?>" placeholder="e.g. Umoja" required></div>
<div class="col-md-6"><label for="education_level" class="form-label">Education level</label><select id="education_level" name="education_level" class="form-select" required><option value="">Select level</option><?php foreach(['primary_school'=>'Primary school','high_school'=>'High school','college_university'=>'College/University','graduate'=>'Graduate'] as $value=>$label):?><option value="<?=$value?>" <?=($_POST['education_level']??($applicationDetails['education_level']??''))===$value?'selected':''?>><?=$label?></option><?php endforeach?></select></div>
<div class="col-md-6"><label for="primary_position" class="form-label">Primary playing position</label><select id="primary_position" name="primary_position" class="form-select" required><option value="">Select position</option><?php foreach(sports_positions() as $value):?><option value="<?=escape($value)?>" <?=($_POST['primary_position']??($applicationDetails['primary_position']??''))===$value?'selected':''?>><?=escape(ucwords(str_replace('_',' ',$value==='not_sure'?'not sure yet':$value)))?></option><?php endforeach?></select></div>
<div class="col-md-6"><label for="preferred_jersey_number" class="form-label">Preferred jersey number</label><input type="number" min="1" max="99" id="preferred_jersey_number" name="preferred_jersey_number" class="form-control" value="<?=$old('preferred_jersey_number')?>" required><div class="form-text">This is a preference, not an official assignment.</div></div></div>
<div id="guardianFields" class="border rounded p-3 my-4 d-none"><h2 class="h5">Guardian authorization</h2><div class="row g-3"><div class="col-md-6"><label for="guardian_name" class="form-label">Guardian name</label><input id="guardian_name" name="guardian_name" class="form-control guardian-required" maxlength="120" value="<?=$old('guardian_name')?>"></div><div class="col-md-6"><label for="guardian_phone" class="form-label">Guardian phone</label><input type="tel" id="guardian_phone" name="guardian_phone" class="form-control guardian-required" maxlength="30" value="<?=$old('guardian_phone')?>"></div><div class="col-12"><label class="form-check"><input type="checkbox" name="guardian_consent" value="1" class="form-check-input guardian-required" <?=!empty($_POST['guardian_consent'])?'checked':''?>> My guardian authorizes this application and the safe public-profile policy.</label></div></div></div>
<div class="form-check mb-2"><input type="checkbox" id="sports_rules_accepted" name="sports_rules_accepted" value="1" class="form-check-input" <?=!empty($_POST['sports_rules_accepted'])?'checked':''?> required><label for="sports_rules_accepted" class="form-check-label">I have read and accept the <a href="<?=BASE_PATH?>/sports_rules.php" target="_blank" rel="noopener">Sports Ministry rules<span class="visually-hidden"> (opens in a new tab)</span></a>.</label></div>
<div class="form-check mb-4"><input type="checkbox" id="publication_acknowledged" name="publication_acknowledged" value="1" class="form-check-input" <?=!empty($_POST['publication_acknowledged'])?'checked':''?> required><label for="publication_acknowledged" class="form-check-label">I acknowledge that approved safe profile information may become public after Sports Admin review. My phone, exact birth date and guardian information remain private.</label></div>
<button class="btn btn-success btn-lg" type="submit"><?=$editing?'Save application':'Submit application'?></button> <a class="btn btn-outline-secondary btn-lg" href="<?=BASE_PATH?>/sports.php">Cancel</a>
</form></div></div></div></div><script src="<?=BASE_PATH?>/assets/js/sports_join.js" defer></script>
<?php $content=ob_get_clean();include dirname(__DIR__).'/portal/layout.php';
