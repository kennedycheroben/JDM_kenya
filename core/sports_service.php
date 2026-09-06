<?php

require_once __DIR__ . '/sports_authorization.php';

const SPORTS_POLICY_VERSION = '2026-08-16';

function sports_public_schema_missing(PDO $pdo): array
{
    $requirements = [
        'sports_applications' => ['id', 'user_id', 'status', 'publication_acknowledged_at', 'guardian_consent_at'],
        'sports_members' => ['id', 'user_id', 'application_id', 'membership_status', 'primary_position', 'assigned_jersey_number'],
        'sports_public_profiles' => ['sports_member_id', 'public_identifier', 'is_published', 'reviewed_at'],
        'sports_training_sessions' => ['title', 'training_date', 'start_time', 'status', 'is_public'],
        'sports_announcements' => ['title', 'content', 'status', 'is_public', 'published_at', 'expires_at'],
        'sports_role_assignments' => ['sports_member_id', 'role_code', 'status'],
        'sports_matches' => ['id', 'status', 'start_time', 'competition_type', 'venue', 'location_type', 'team_a_score', 'team_b_score', 'is_public'],
        'sports_stats' => ['user_id', 'appearances', 'goals_scored', 'assists'],
        'sports_commentary' => ['id', 'match_id', 'comment_text', 'created_at'],
    ];
    $missing = [];
    foreach ($requirements as $table => $columns) {
        if (!sports_table_exists($pdo, $table)) {
            $missing[] = $table;
            continue;
        }
        foreach ($columns as $column) {
            if (!sports_column_exists($pdo, $table, $column)) $missing[] = $table . '.' . $column;
        }
    }
    return $missing;
}

function sports_admin_schema_missing(PDO $pdo): array
{
    $missing = sports_public_schema_missing($pdo);
    $requirements = [
        'sports_applications' => ['application_source', 'date_of_birth', 'general_estate', 'education_level', 'primary_position', 'preferred_jersey_number', 'reviewed_by', 'reviewed_at', 'applicant_message', 'internal_review_note'],
        'sports_members' => ['joined_at', 'suspended_at'],
        'sports_admin_assignments' => ['user_id', 'appointed_by', 'appointed_at', 'ended_at', 'status', 'appointment_note'],
        'sports_role_assignments' => ['appointed_by', 'starts_at', 'ends_at', 'appointment_note'],
        'sports_public_profiles' => ['public_biography', 'public_achievements', 'profile_photo_path', 'reviewed_by', 'published_at'],
        'sports_training_sessions' => ['end_time', 'location', 'instructions', 'created_by'],
        'sports_training_attendance' => ['training_session_id', 'sports_member_id', 'attendance_status', 'remark', 'recorded_by', 'recorded_at'],
        'sports_announcements' => ['created_by', 'updated_at'],
        'sports_audit_logs' => ['actor_user_id', 'action', 'entity_type', 'entity_id', 'metadata_json', 'created_at'],
        'sports_stats' => ['starts', 'clean_sheets', 'yellow_cards', 'red_cards', 'mvp_awards'],
    ];
    foreach ($requirements as $table => $columns) {
        if (!sports_table_exists($pdo, $table)) {
            $missing[] = $table;
            continue;
        }
        foreach ($columns as $column) {
            if (!sports_column_exists($pdo, $table, $column)) $missing[] = $table . '.' . $column;
        }
    }
    return array_values(array_unique($missing));
}

function sports_education_levels(): array { return ['primary_school','high_school','college_university','graduate']; }
function sports_positions(): array { return ['goalkeeper','right_back','centre_back','left_back','defensive_midfielder','central_midfielder','attacking_midfielder','right_winger','left_winger','striker','not_sure']; }
function sports_role_codes(): array { return ['player','captain','assistant_captain','head_coach','assistant_coach','goalkeeping_coach','fitness_coach','team_manager','sports_administrator','welfare_discipleship_coordinator','medical_first_aid_officer','kit_equipment_manager','communications_media_officer']; }

function sports_age(string $dateOfBirth, ?DateTimeImmutable $asOf = null): int
{
    $tz = new DateTimeZone('Africa/Nairobi');
    $dob = DateTimeImmutable::createFromFormat('!Y-m-d', $dateOfBirth, $tz);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$dob || ($errors !== false && ($errors['warning_count'] || $errors['error_count'])) || $dob->format('Y-m-d') !== $dateOfBirth) {
        throw new InvalidArgumentException('Invalid date of birth.');
    }
    $today = ($asOf ?: new DateTimeImmutable('today', $tz))->setTimezone($tz);
    if ($dob > $today) throw new InvalidArgumentException('Date of birth cannot be in the future.');
    return $dob->diff($today)->y;
}

function sports_normalize_phone(string $phone): ?string
{
    $value = preg_replace('/[\s().-]+/', '', trim($phone));
    if (str_starts_with($value, '0')) $value = '+254' . substr($value, 1);
    elseif (str_starts_with($value, '254')) $value = '+' . $value;
    return preg_match('/^\+[1-9][0-9]{8,14}$/', $value) ? $value : null;
}

function sports_validate_application(array $input, DateTimeImmutable $today): array
{
    $errors = [];
    $dob = trim((string)($input['sports_date_of_birth'] ?? ''));
    try { $age = sports_age($dob, $today); if ($age > 110) $errors[] = 'Please enter a realistic date of birth.'; }
    catch (Throwable $e) { $age = null; $errors[] = 'Please enter a valid date of birth.'; }
    if (!sports_normalize_phone((string)($input['whatsapp_phone'] ?? ''))) $errors[] = 'Please enter a valid phone/WhatsApp number.';
    if (trim((string)($input['general_estate'] ?? '')) === '' || mb_strlen(trim((string)$input['general_estate'])) > 120) $errors[] = 'Please enter a general estate or neighbourhood.';
    if (!in_array($input['education_level'] ?? '', sports_education_levels(), true)) $errors[] = 'Please select a valid education level.';
    if (!in_array($input['primary_position'] ?? '', sports_positions(), true)) $errors[] = 'Please select a valid playing position.';
    $jersey = filter_var($input['preferred_jersey_number'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>99]]);
    if ($jersey === false) $errors[] = 'Preferred jersey number must be from 1 to 99.';
    if (empty($input['sports_rules_accepted'])) $errors[] = 'You must accept the Sports Ministry rules.';
    if (empty($input['publication_acknowledged'])) $errors[] = 'You must acknowledge the public-profile policy.';
    if ($age !== null && $age < 18) {
        if (trim((string)($input['guardian_name'] ?? '')) === '') $errors[] = 'Guardian name is required for applicants under 18.';
        if (!sports_normalize_phone((string)($input['guardian_phone'] ?? ''))) $errors[] = 'A valid guardian phone is required for applicants under 18.';
        if (empty($input['guardian_consent'])) $errors[] = 'Guardian authorization is required for applicants under 18.';
    }
    return ['errors'=>$errors, 'age'=>$age, 'dob'=>$dob, 'jersey'=>$jersey];
}

function sports_audit(PDO $pdo, ?int $actor, string $action, string $entityType, ?int $entityId, array $metadata = []): void
{
    $stmt = $pdo->prepare('INSERT INTO sports_audit_logs (actor_user_id,action,entity_type,entity_id,metadata_json) VALUES (?,?,?,?,?)');
    $stmt->execute([$actor,$action,$entityType,$entityId,$metadata ? json_encode($metadata, JSON_THROW_ON_ERROR) : null]);
}

function sports_notify(PDO $pdo, int $senderId, int $receiverId, string $message): void
{
    if ($senderId <= 0 || $receiverId <= 0 || $senderId === $receiverId) return;
    $stmt = $pdo->prepare("INSERT INTO messages(sender_id,receiver_id,message_text,status) VALUES (?, ?, ?, 'sent')");
    $stmt->execute([$senderId, $receiverId, $message]);
}

function sports_public_text_is_safe(string $value): bool
{
    $patterns = ['/\b(?:\+?254|0)7\d{8}\b/', '/\b[\w.%+-]+@[\w.-]+\.[A-Za-z]{2,}\b/', '/\b(?:account|acct|mpesa|m-pesa|paybill|till|bank)\b.{0,30}\d{4,}/i'];
    foreach ($patterns as $pattern) if (preg_match($pattern, $value)) return false;
    return true;
}
