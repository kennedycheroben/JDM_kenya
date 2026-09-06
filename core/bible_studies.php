<?php
declare(strict_types=1);

const BS_TIMEZONE = 'Africa/Nairobi';
const BS_UPLOAD_MAX_BYTES = 10485760;
const BS_NOTE_MAX_LENGTH = 10000;

function bs_required_tables(): array {
    return [
        'bible_studies',
        'bible_study_leader_assignments',
        'bible_study_sessions',
        'bible_study_registrations',
        'bible_study_materials',
        'bible_study_notes',
        'bible_study_attendance',
        'bible_study_audit_logs',
    ];
}
function bs_missing_tables(PDO $pdo): array {
    try {
        $existing = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        return array_values(array_diff(bs_required_tables(), array_map('strval', $existing)));
    } catch (Throwable $e) {
        error_log('[bible_studies] Schema check failed: '.$e->getMessage());
        return bs_required_tables();
    }
}
function bs_schema_ready(PDO $pdo): bool { return bs_missing_tables($pdo) === []; }

function bs_now(): DateTimeImmutable { return new DateTimeImmutable('now', new DateTimeZone(BS_TIMEZONE)); }
function bs_user_id(): int { return (int)($_SESSION['user_id'] ?? 0); }
function bs_role(): string { return (string)($_SESSION['user_role'] ?? ''); }
function bs_require_login(): void {
    if (bs_user_id() < 1 || !in_array(bs_role(), ['member','admin','super_admin'], true)) {
        header('Location: login.php'); exit;
    }
}
function bs_require_post(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); header('Allow: POST'); exit('Method not allowed.'); }
    if (!require_csrf()) { throw new RuntimeException('Your session expired. Reload the page and try again.'); }
}
function bs_positive_int(mixed $value, string $label = 'ID'): int {
    $v = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($v === false) throw new InvalidArgumentException("Invalid {$label}.");
    return (int)$v;
}
function bs_eligible_user(PDO $pdo, int $id): ?array {
    $s=$pdo->prepare("SELECT id,name,email,role,category,is_approved FROM users WHERE id=? LIMIT 1"); $s->execute([$id]); $u=$s->fetch();
    if (!$u || !in_array($u['role'], ['member','admin','super_admin'], true)) return null;
    if (in_array($u['category'], ['partner','missionary'], true) && empty($u['is_approved'])) return null;
    return $u;
}
function bs_require_eligible(PDO $pdo): array {
    bs_require_login(); $u=bs_eligible_user($pdo, bs_user_id());
    if (!$u) { http_response_code(403); exit('Your account is not eligible for Bible Study access.'); }
    return $u;
}
function bs_is_leader(PDO $pdo, int $studyId, int $userId, bool $currentOnly=true): bool {
    $sql='SELECT 1 FROM bible_study_leader_assignments WHERE bible_study_id=? AND leader_user_id=?'.($currentOnly?' AND is_current=1':'').' LIMIT 1';
    $s=$pdo->prepare($sql); $s->execute([$studyId,$userId]); return (bool)$s->fetchColumn();
}
function bs_can_manage(PDO $pdo, int $studyId): bool { return bs_role()==='super_admin' || bs_is_leader($pdo,$studyId,bs_user_id()); }
function bs_audit(PDO $pdo,string $action,string $type,?int $id,array $meta=[]): void {
    $s=$pdo->prepare('INSERT INTO bible_study_audit_logs(actor_user_id,action,entity_type,entity_id,metadata_json) VALUES(?,?,?,?,?)');
    $s->execute([bs_user_id() ?: null,$action,$type,$id,$meta ? json_encode($meta,JSON_THROW_ON_ERROR) : null]);
}
function bs_study(PDO $pdo,int $id): ?array {
    $s=$pdo->prepare("SELECT b.id,b.study_date,b.title,b.theme,b.primary_scripture,b.additional_scriptures,b.description,b.preparation_instructions,b.shared_summary,b.default_location,b.meeting_link,b.registration_opens_at,b.registration_deadline,b.status,b.cancellation_reason,b.published_at,b.completed_at,b.created_at,b.updated_at,u.name leader_name FROM bible_studies b LEFT JOIN bible_study_leader_assignments la ON la.bible_study_id=b.id AND la.is_current=1 LEFT JOIN users u ON u.id=la.leader_user_id WHERE b.id=? LIMIT 1");
    $s->execute([$id]); return $s->fetch() ?: null;
}
function bs_sessions(PDO $pdo,int $studyId): array {
    $s=$pdo->prepare("SELECT s.id,s.bible_study_id,s.session_name,s.start_time,s.end_time,s.location,s.meeting_link,s.capacity,s.is_active,s.display_order,COUNT(r.id) registered_count FROM bible_study_sessions s LEFT JOIN bible_study_registrations r ON r.session_id=s.id AND r.status='active' WHERE s.bible_study_id=? GROUP BY s.id ORDER BY s.display_order,s.start_time");
    $s->execute([$studyId]); return $s->fetchAll();
}
function bs_active_registration(PDO $pdo,int $studyId,int $memberId): ?array {
    $s=$pdo->prepare("SELECT r.id,r.session_id,r.registered_at,s.session_name,s.start_time,s.end_time FROM bible_study_registrations r JOIN bible_study_sessions s ON s.id=r.session_id WHERE r.bible_study_id=? AND r.member_user_id=? AND r.status='active' LIMIT 1");
    $s->execute([$studyId,$memberId]); return $s->fetch() ?: null;
}
function bs_registration_window(array $study): ?string {
    $now=bs_now(); $date=new DateTimeImmutable($study['study_date'].' 23:59:59',new DateTimeZone(BS_TIMEZONE));
    if ($study['status']!=='published') return 'This study is not open for registration.';
    if ($date < $now) return 'This study date has passed.';
    if ($now < new DateTimeImmutable($study['registration_opens_at'],new DateTimeZone(BS_TIMEZONE))) return 'Registration has not opened.';
    if ($now > new DateTimeImmutable($study['registration_deadline'],new DateTimeZone(BS_TIMEZONE))) return 'Registration has closed.';
    return null;
}
function bs_register(PDO $pdo,int $studyId,int $sessionId,int $memberId): void {
    if (!check_rate_limit('bs_register_'.$memberId,10,300)) throw new RuntimeException('Too many registration changes. Try again shortly.');
    $pdo->beginTransaction();
    try {
        $q=$pdo->prepare('SELECT id,study_date,status,registration_opens_at,registration_deadline FROM bible_studies WHERE id=? FOR UPDATE'); $q->execute([$studyId]); $study=$q->fetch();
        if (!$study) throw new RuntimeException('Study not found.');
        if ($reason=bs_registration_window($study)) throw new RuntimeException($reason);
        $q=$pdo->prepare('SELECT id,capacity,is_active FROM bible_study_sessions WHERE id=? AND bible_study_id=? FOR UPDATE'); $q->execute([$sessionId,$studyId]); $session=$q->fetch();
        if (!$session || !(int)$session['is_active']) throw new RuntimeException('That session is unavailable.');
        $q=$pdo->prepare("SELECT id,session_id FROM bible_study_registrations WHERE bible_study_id=? AND member_user_id=? AND status='active' FOR UPDATE"); $q->execute([$studyId,$memberId]); $old=$q->fetch();
        if ($old && (int)$old['session_id']===$sessionId) throw new RuntimeException('You already selected that session.');
        $q=$pdo->prepare("SELECT COUNT(*) FROM bible_study_registrations WHERE session_id=? AND status='active'"); $q->execute([$sessionId]);
        if ($session['capacity']!==null && (int)$q->fetchColumn()>=(int)$session['capacity']) throw new RuntimeException('That session is full.');
        if ($old) { $pdo->prepare("UPDATE bible_study_registrations SET status='moved',cancelled_at=NOW(),moved_by=? WHERE id=?")->execute([$memberId,$old['id']]); }
        $pdo->prepare("INSERT INTO bible_study_registrations(bible_study_id,session_id,member_user_id,status,moved_by) VALUES(?,?,?,'active',?)")->execute([$studyId,$sessionId,$memberId,$old ? $memberId : null]);
        $newId=(int)$pdo->lastInsertId(); bs_audit($pdo,$old?'registration_changed':'registration_created','registration',$newId,['from_session_id'=>$old?(int)$old['session_id']:null,'to_session_id'=>$sessionId]);
        $pdo->commit();
    } catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); throw $e; }
}
function bs_cancel_registration(PDO $pdo,int $studyId,int $memberId): void {
    if (!check_rate_limit('bs_register_'.$memberId,10,300)) throw new RuntimeException('Too many registration changes.');
    $pdo->beginTransaction(); try {
        $q=$pdo->prepare('SELECT id,study_date,status,registration_opens_at,registration_deadline FROM bible_studies WHERE id=? FOR UPDATE');$q->execute([$studyId]);$study=$q->fetch();
        if(!$study)throw new RuntimeException('Study not found.'); if($reason=bs_registration_window($study))throw new RuntimeException($reason);
        $q=$pdo->prepare("SELECT id FROM bible_study_registrations WHERE bible_study_id=? AND member_user_id=? AND status='active' FOR UPDATE");$q->execute([$studyId,$memberId]);$id=(int)$q->fetchColumn();
        if(!$id)throw new RuntimeException('No active registration found.');
        $pdo->prepare("UPDATE bible_study_registrations SET status='cancelled',cancelled_at=NOW() WHERE id=?")->execute([$id]);bs_audit($pdo,'registration_cancelled','registration',$id);$pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function bs_save_note(PDO $pdo,int $studyId,int $memberId,string $content): void {
    if(!check_rate_limit('bs_note_'.$memberId,20,300))throw new RuntimeException('Too many note updates.');
    if(mb_strlen($content)>BS_NOTE_MAX_LENGTH)throw new InvalidArgumentException('Note is too long.');
    $q=$pdo->prepare('SELECT id FROM bible_studies WHERE id=?');$q->execute([$studyId]);if(!$q->fetchColumn())throw new RuntimeException('Study not found.');
    if($content===''){$pdo->prepare('DELETE FROM bible_study_notes WHERE bible_study_id=? AND member_user_id=?')->execute([$studyId,$memberId]);return;}
    $pdo->prepare('INSERT INTO bible_study_notes(bible_study_id,member_user_id,note_content) VALUES(?,?,?) ON DUPLICATE KEY UPDATE note_content=VALUES(note_content),updated_at=NOW()')->execute([$studyId,$memberId,$content]);
}
function bs_material_storage_root(): string { return dirname(__DIR__).'/../jdm_private/bible_study_materials'; }
function bs_upload_material(PDO $pdo,int $studyId,array $file,string $title,bool $published): void {
    if(!check_rate_limit('bs_upload_'.bs_user_id(),10,3600))throw new RuntimeException('Upload limit reached.');
    if(!bs_can_manage($pdo,$studyId))throw new RuntimeException('Not authorized.');
    if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Upload failed.');
    $size=(int)($file['size']??0);if($size<1||$size>BS_UPLOAD_MAX_BYTES)throw new RuntimeException('File must be no larger than 10 MB.');
    $original=(string)($file['name']??''); if($original!==basename($original)||substr_count($original,'.')!==1)throw new RuntimeException('Unsafe filename.');
    $ext=strtolower(pathinfo($original,PATHINFO_EXTENSION));
    $allowed=['pdf'=>['application/pdf'],'docx'=>['application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/zip'],'pptx'=>['application/vnd.openxmlformats-officedocument.presentationml.presentation','application/zip'],'jpg'=>['image/jpeg'],'jpeg'=>['image/jpeg'],'png'=>['image/png'],'webp'=>['image/webp']];
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);if(!isset($allowed[$ext])||!in_array($mime,$allowed[$ext],true))throw new RuntimeException('Unsupported or disguised file.');
    $root=bs_material_storage_root();if(!is_dir($root)&&!mkdir($root,0750,true))throw new RuntimeException('Storage is unavailable.');
    $key=bin2hex(random_bytes(24)).'.'.$ext;$path=$root.'/'.$key;if(!move_uploaded_file($file['tmp_name'],$path))throw new RuntimeException('Could not store the file.');chmod($path,0640);
    try{$q=$pdo->prepare('INSERT INTO bible_study_materials(bible_study_id,material_title,original_filename,storage_key,mime_type,file_extension,file_size,uploaded_by,is_published) VALUES(?,?,?,?,?,?,?,?,?)');$q->execute([$studyId,$title,$original,$key,$mime,$ext,$size,bs_user_id(),$published?1:0]);bs_audit($pdo,'material_uploaded','material',(int)$pdo->lastInsertId());}catch(Throwable $e){@unlink($path);throw $e;}
}
