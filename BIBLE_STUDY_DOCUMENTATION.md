# JDM Kenya General Bible Study Module

**Module:** General Monday Bible Study  
**Application:** JDM Kenya  
**Timezone:** Africa/Nairobi  
**Status:** Implemented locally; production deployment requires the included database migration

---

## 1. Overview

The General Bible Study module manages the Bible study normally held on Mondays. It is a standalone feature and is not part of the existing group-based GBS system.

The module allows:

- Super administrators to create, assign and publish studies.
- Appointed study leaders to prepare study content, upload materials and record attendance.
- Eligible authenticated members to select one available session, maintain private notes and view their history.

General Bible Study appointments do not alter `users.role`, `users.is_gbs_leader`, GBS membership or GBS permissions.

---

## 2. Default Sessions

Each study stores three session records:

| Session | Start | End |
|---|---:|---:|
| Morning Session | 08:00 | 10:00 |
| Midday Session | 11:00 | 13:00 |
| Evening Session | 18:30 | 20:00 |

The super administrator selects which sessions are available while creating the study. All three are selected by default. At least one session must remain selected.

Disabled sessions remain in the database for a consistent schedule definition but are not shown as registration choices to members.

---

## 3. Roles and Permissions

### Member

An authenticated eligible member can:

- View the next published study.
- See the study date, leader, scripture and preparation instructions.
- View active sessions and their times.
- Register for exactly one session.
- Change sessions before registration closes.
- Cancel registration before registration closes.
- Download published study materials.
- Create, update or delete their private note.
- View their registration and attendance history.

A member cannot view participant lists, another member's notes or register on behalf of another user.

### Appointed study leader

The current appointed leader can manage only an assigned study. The leader can:

- Edit the title, scripture references and preparation instructions.
- Add a meeting link and shared study summary.
- Upload study materials.
- Publish a material during upload.
- View registrations grouped by session.
- Record attendance as `not_recorded`, `present`, `absent` or `excused`.

The leader cannot appoint leaders, manage unrelated studies or access members' private notes.

### Super administrator

A user with `users.role = 'super_admin'` can:

- Create a Monday study.
- Select its active sessions.
- Appoint an eligible leader.
- Publish, complete, cancel or archive a study.
- Replace the leader while preserving appointment history.
- Open the leader management view for any study.
- View each study's active session names and total registrations.

Ordinary administrators do not automatically receive these management permissions.

---

## 4. User Workflows

### Creating a study

1. Sign in as a super administrator.
2. Open `/bible_study_admin.php` or select **Study Admin** in the sidebar.
3. Enter a Monday date and study title.
4. Optionally enter scripture and preparation instructions.
5. Select an eligible leader.
6. Select the registration opening and deadline dates.
7. Confirm the available Morning, Midday and/or Evening sessions.
8. Select **Create study**.

Registration opens at `00:00:00` on the opening date and closes at `23:59:59` on the deadline date in Africa/Nairobi time.

New studies start in `draft` status. Members cannot see or register for a draft study.

### Publishing a study

1. Find the study under **All studies**.
2. Select `published` from the status list.
3. Select **Update**.

Publishing records `published_at` and adds an audit entry.

### Replacing a leader

1. Find the relevant study.
2. Select the replacement leader.
3. Enter the required replacement reason.
4. Select **Replace leader**.

The previous assignment is ended rather than deleted. Its `ended_at`, `is_current` and `replacement_reason` values preserve the appointment history.

### Member registration

1. Open `/bible_study.php` or select **Bible Study** in the sidebar.
2. Review the published study.
3. Select an available session.
4. Use **Change to this session** to move before the deadline.
5. Use **Cancel registration** to cancel before the deadline.

Session changes happen in one database transaction. The old registration becomes `moved`, and a new active registration is created.

---

## 5. Routes and Files

### Public root wrappers

| Route | Module file | Purpose |
|---|---|---|
| `/bible_study.php` | `modules/bible_study/bible_study.php` | Member study page |
| `/bible_study_admin.php` | `modules/bible_study/admin.php` | Super-admin management |
| `/bible_study_leader.php` | `modules/bible_study/leader.php` | Assigned-leader workspace |
| `/bible_study_download.php` | `modules/bible_study/download.php` | Protected material download |

### Supporting files

| File | Purpose |
|---|---|
| `core/bible_studies.php` | Authorization, queries, registration rules, notes, uploads and audit helpers |
| `assets/css/bible_study.css` | Responsive module styles |
| `modules/portal/layout.php` | Bible Study sidebar navigation |
| `database/migrations/20260814_create_general_bible_studies.sql` | Additive UP migration |
| `database/migrations/20260814_drop_general_bible_studies.sql` | Destructive rollback migration |

---

## 6. Database Structure

The module uses eight InnoDB tables with `utf8mb4` encoding:

| Table | Purpose |
|---|---|
| `bible_studies` | Study date, content, registration window and lifecycle status |
| `bible_study_leader_assignments` | Current and historical leader appointments |
| `bible_study_sessions` | Session names, times, active state and optional capacity |
| `bible_study_registrations` | Active and historical member registrations |
| `bible_study_materials` | Secure file metadata; file binaries are not stored in MySQL |
| `bible_study_notes` | Member-owned private notes |
| `bible_study_attendance` | Attendance linked to registrations |
| `bible_study_audit_logs` | Sensitive-action history |

Important constraints include:

- One study per Monday date.
- One current leader assignment per study.
- One active registration per member and study.
- Session capacity may be `NULL`, meaning unlimited.
- Attendance has one record per registration.
- Private notes have one record per member and study.

All foreign keys referencing users use `INT UNSIGNED`, matching `users.id`.

---

## 7. Registration Rules

The server verifies all of the following:

1. The requester is authenticated and eligible.
2. The study exists and is published.
3. The study date has not passed.
4. Registration has opened and the deadline has not passed.
5. The session belongs to the study and is active.
6. The member has no other active registration for the study.
7. Capacity is available when a limit exists.
8. The member ID comes from the authenticated session, never form input.

Capacity checks and session changes use a database transaction with row locking to prevent overbooking.

---

## 8. Materials and Upload Security

Accepted file types:

- PDF
- DOCX
- PPTX
- JPEG
- PNG
- WebP

The default maximum upload size is 10 MB.

Uploads are protected by:

- PHP upload-error validation.
- `finfo` MIME detection.
- Extension-to-MIME matching.
- Rejection of missing, multiple or unsafe extensions.
- Cryptographically random storage names.
- Storage outside the JDM Kenya public directory.
- Authorization through the download controller.
- Canonical path validation.
- `Content-Disposition: attachment` and `X-Content-Type-Options: nosniff`.

The default storage directory is:

```text
/opt/lampp/htdocs/../jdm_private/bible_study_materials
```

The web-server user must be able to create and write to this directory.

---

## 9. Security Controls

New module requests use:

- Session authentication.
- Database-backed role and eligibility checks.
- Server-side study ownership checks.
- POST-only state changes.
- CSRF tokens.
- PDO prepared statements.
- Strict positive-integer validation.
- HTML output escaping.
- Registration, note and upload rate limits.
- Private/no-store caching for sensitive pages.
- Generic database error messages with detailed server-side logging.
- Audit logging for sensitive operations.

The module never uses GBS membership or GBS leadership as authorization.

---

## 10. Local XAMPP Installation

1. Back up the `jdm_kenya` database.
2. Confirm MySQL and Apache are running in XAMPP.
3. Apply the migration:

```bash
/opt/lampp/bin/mysql -u YOUR_DATABASE_USER -p jdm_kenya \
  < database/migrations/20260814_create_general_bible_studies.sql
```

4. Confirm the eight tables exist:

```sql
SHOW TABLES LIKE 'bible_stud%';
```

5. Sign in as a super administrator.
6. Open `/JDM_kenya/bible_study_admin.php`.
7. Create and publish a test study.
8. Sign in as a member and test registration.

Do not use `setup.php` or a destructive setup script to install this migration.

---

## 11. Production/cPanel Deployment

1. Back up the application files and database.
2. Upload the new and modified files while preserving their paths.
3. Confirm production `users.id` is `INT UNSIGNED`.
4. Import `database/migrations/20260814_create_general_bible_studies.sql` through phpMyAdmin or the MySQL CLI.
5. Create a private material directory outside `public_html` and grant the PHP user read/write access.
6. Confirm `.htaccess` rules are enabled.
7. Verify member, leader and super-admin access separately.
8. Verify existing GBS pages after deployment.

Never upload local credential files or commit database passwords. Rotate any credentials that have previously appeared in an archive or source-control history.

---

## 12. Testing Checklist

### Access control

- [ ] Unauthenticated requests redirect to login.
- [ ] A member cannot open Study Admin.
- [ ] A member cannot open the leader workspace.
- [ ] A leader cannot open another leader's study.
- [ ] GBS leadership grants no General Bible Study management access.
- [ ] General Bible Study leadership grants no GBS access.

### Study management

- [ ] Only Monday dates are accepted.
- [ ] At least one session must be selected.
- [ ] Opening and deadline dates are required.
- [ ] The deadline must be after opening.
- [ ] Draft studies are hidden from members.
- [ ] Published studies appear to members.
- [ ] Cancellation requires a reason.
- [ ] Leader replacement preserves history.

### Registration

- [ ] A member can select one active session.
- [ ] A member can change sessions before the deadline.
- [ ] A member can cancel before the deadline.
- [ ] Inactive sessions are hidden and reject registration.
- [ ] Full sessions reject new registration.
- [ ] Registration before opening or after closing is rejected.
- [ ] Cancelled, completed and archived studies reject registration.

### Privacy and files

- [ ] Members cannot access another member's notes.
- [ ] Leaders cannot access private member notes.
- [ ] Unsupported and disguised uploads are rejected.
- [ ] Published material downloads require authentication.
- [ ] Unpublished material is restricted to authorized managers.

### Regression

- [ ] Login and logout still work.
- [ ] Member, admin and super-admin dashboards still work.
- [ ] Existing GBS pages still work.
- [ ] Members Management numbering remains sequential.

Run PHP syntax validation with:

```bash
php -l core/bible_studies.php
php -l modules/bible_study/bible_study.php
php -l modules/bible_study/admin.php
php -l modules/bible_study/leader.php
php -l modules/bible_study/download.php
```

---

## 13. Rollback

The DOWN migration removes every General Bible Study table and all data stored in the module:

```bash
/opt/lampp/bin/mysql -u YOUR_DATABASE_USER -p jdm_kenya \
  < database/migrations/20260814_drop_general_bible_studies.sql
```

This rollback is destructive. Export the eight Bible Study tables before running it. Application files can then be removed and the Bible Study navigation entries reverted.

---

## 14. Current Limitations

The current interface does not yet provide:

- Editing active sessions after a study has been created.
- Per-session capacity editing in the super-admin interface.
- Super-admin registration moves between sessions.
- Material deletion or publish/unpublish controls.
- An audit-log viewer.
- Bulk generation of upcoming Monday studies.
- Archive pagination.

These capabilities should be implemented using the existing authorization, audit and transaction helpers rather than direct database actions in templates.

