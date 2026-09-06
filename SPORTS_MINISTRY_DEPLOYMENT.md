# Sports Ministry implementation and deployment

## Architecture and workflow

Root wrappers load modular pages. `core/sports_service.php` owns validation, Nairobi age calculation, public-text screening and auditing. `core/sports_authorization.php` owns database-backed Sports Admin permission and the central restricted-applicant gate. The public hub and player profile use explicit public column lists. Administrative actions are POST-only, CSRF-protected, authorized on every request, rate-limited, prepared, audited and redirected after mutation.

Registration creates a normal `member` user with category `sports_ministry`, `is_approved=0`, plus one pending `sports_applications` row. Login establishes a restricted session and routes pending, rejected or withdrawn applicants to the status page. Approval locks the application, changes its sports status, enables existing JDM member access, creates/activates membership, defaults responsibility to Player, and provisions an unpublished random public profile. Suspension affects Sports Ministry only.

Authenticated approved JDM users use `join_sports_ministry.php`. This path inserts no user record and marks the application `existing_user`. The authenticated session supplies ownership; the form contains no editable user ID. Phone and DOB are validated and synchronized to their authoritative `users` fields in the same transaction as the application. Their existing user ID, role, category, general approval, portal permissions, GBS access and General Bible Study access remain unchanged while pending and after rejection or withdrawal. Only `new_user` applications are subject to the restricted portal gate.

Sports Admin is an appointment, not a role/category. A super admin appoints or ends it in the Sports Admin dashboard. Every sensitive request checks an active `sports_admin_assignments` row or current `users.role='super_admin'`; removal therefore takes effect on the next request.

## Schema and constraints

Migration: `database/migrations/20260816_sports_ministry.sql`, applied idempotently by `migrate_sports_schema.php`. It adds `sports_ministry` to the existing category enum and creates applications, members, admin assignments, role assignments, public profiles, training sessions/attendance, announcements and audit logs. The migrator also extends existing matches and statistics in place.

All user references are `INT UNSIGNED`, matching production-local `users.id`. `sports_applications.application_source` distinguishes `new_user` from `existing_user`, and `idx_sports_app_source_status` supports source-aware access checks. Tables use InnoDB/utf8mb4. Generated nullable keys enforce one active jersey, one active Sports Admin appointment per user, one active Captain, one active Assistant Captain, and no duplicate active responsibility per member. Historical actor foreign keys use `SET NULL`; core historical rows use `RESTRICT`.

No age column exists. Age is calculated with `DateTimeImmutable::diff()` in `Africa/Nairobi`. A February 29 birthday increments on March 1 in non-leap years.

## Authorization matrix

| Actor/state | Public sports/profile | Status page | Full portal | Sports admin | Appoint Sports Admin |
|---|---:|---:|---:|---:|---:|
| Visitor | Yes | No | No | No | No |
| Sports pending/rejected/withdrawn | Yes | Own only | No | No | No |
| Approved member | Yes | Redirects to portal | Yes | No | No |
| Existing JDM user with pending/rejected/withdrawn sports application | Yes | Own application | Yes | No | No |
| Suspended sports member | Yes, but hidden from roster | N/A | Yes | No | No |
| Active appointed Sports Admin | Yes | N/A | Existing access | Yes | No |
| Super admin | Yes | N/A | Yes | Yes | Yes |

Existing Office Bearer/missionary `users.is_approved` behavior remains separate. The existing legacy rejection behavior for those categories was not refactored.

## Public/private classification

Public queries may return reviewed name, calculated age, general estate, education level, approved institution/photo/biography/achievements, position, official jersey, appointments, membership month and statistics. They never select or return email, phone, exact DOB, guardian details, password/session data, internal reasons, audit records, database IDs, precise address, medical/identity data, or payment details. Public profile identifiers are random 128-bit hex strings.

Private application fields are limited to authorized Sports Admins/super admins. List views show only safeguarding-relevant summaries. Public biography and achievement text rejects likely phone, email, bank and mobile-money content before publication.

## Endpoints and actions

- `sports.php`: public hub and rate-limited public commentary feed.
- `sports_player.php?profile=<identifier>`: reviewed public player profile.
- `sports_application_status.php`: own status and CSRF-protected withdrawal.
- `join_sports_ministry.php`: authenticated existing-member submission and pending-field update using the session owner.
- `sports_admin.php`: applications, appointments, players, profiles, roles, training, fixtures/results/commentary, announcements and statistics.
- `migrate_sports_schema.php`: CLI, or super-admin POST with CSRF.

## Validation

Education and position values use explicit allowlists. DOB must be a real non-future date and no more than 110 years old. Phone numbers normalize to E.164 (Kenyan local `07…` becomes `+2547…`). Preferred/official jersey numbers are 1–99. Under-18 applications require guardian name, normalized phone and authorization timestamp/version. Public/rules acknowledgements are mandatory and versioned. No minimum participation age was invented; JDM Kenya must establish that safeguarding policy.

## Local XAMPP deployment

1. Back up the local database and confirm configuration remains outside version control.
2. From the project root run `/opt/lampp/bin/php migrate_sports_schema.php`.
3. Run `/opt/lampp/bin/php tests/sports_ministry_test.php` and lint changed PHP files.
4. Test visitor, pending, approved, suspended, appointed admin and super-admin sessions in separate private browser windows.
5. Verify mobile widths 320, 375, 576, 768 and 1024 px and keyboard operation.

## cPanel production migration

1. Put the site in a planned maintenance window and take verified database/files backups.
2. Compare production `SHOW CREATE TABLE users`, `sports_matches`, `sports_stats`, and `sports_commentary` with the preflight assumptions. Stop if `users.id` is not unsigned integer or names/types conflict.
3. Upload code without credentials or local users. Run the migration using the cPanel PHP CLI from the application root, once.
4. Confirm tables/columns/indexes and review PHP/Apache logs. Smoke-test public pages before enabling appointments or publishing profiles.
5. Appoint Sports Admins through the super-admin UI. Do not manually elevate `users.role`.

No deployment, push or production import is performed by this implementation.

## Rollback

Prefer rolling the application code forward/fixing defects because new tables preserve business history. Before any rollback, export all new sports tables. Revert the application code first. Only if no production sports application exists, manually drop new foreign-key-dependent tables in this order: `sports_training_attendance`, `sports_role_assignments`, `sports_public_profiles`, `sports_audit_logs`, `sports_announcements`, `sports_training_sessions`, `sports_admin_assignments`, `sports_members`, `sports_applications`; then remove only the newly added legacy columns and restore the old category enum. If any `sports_ministry` user exists, do not restore the old enum until those rows are safely mapped under an approved business decision. Never reset auto-increments and never execute rollback automatically.

## Security, performance and known limitations

Prepared statements, output escaping, CSRF, POST/Redirect/Get, session regeneration, ownership checks, DB authorization, transactions/locks and database uniqueness address injection, XSS, CSRF, IDOR, privilege staleness and races. Public lists are bounded; dashboard lists are capped and should gain cursor pagination when the ministry exceeds 100 applications/players. Existing session/file rate limiting is single-server and should move to shared storage if the site scales horizontally.

Image upload was not added; existing approved profile paths can be reviewed, avoiding a new upload attack surface. Reapplication after rejection or withdrawal remains disabled because the current unique user constraint permits one historical application per account; enabling it requires a separate history-preserving current-application migration. Gallery management remains future work. A production schema export was not available in the repository, so production preflight is mandatory. Browser automation and email delivery are not included.

## Manual acceptance checklist

- Confirm sports fields work with JavaScript disabled and minors cannot submit without guardian consent.
- Confirm pending/rejected users cannot open dashboard, chat, directory, prayer, GBS, or Bible Study private pages.
- Approve concurrently in two sessions and confirm one membership/profile only.
- Remove an appointed Sports Admin and confirm their next request is denied.
- Attempt duplicate active jerseys and Captain/Assistant Captain assignments.
- Inspect public HTML/network payloads for prohibited private fields.
- Publish/unpublish a profile; suspend/reinstate the player.
- Create a fixture/result/commentary/stat update and confirm legacy records remain.
- Exercise Office Bearer, ordinary signup, GBS, General Bible Study and existing dashboards.
- Rotate database/SMTP credentials after deployment if they were shared during operational work; never commit them.
