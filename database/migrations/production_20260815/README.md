# Production Bible Study completion

The production database dump dated 2026-08-15 already contains
`bible_studies` and `bible_study_leader_assignments`.

Execute files `01` through `06` in numeric order in the SQL tab of phpMyAdmin.
Uploading these files to File Manager does not execute them.

Afterward, run:

```sql
SHOW TABLES LIKE 'bible_stud%';
```

Exactly eight table names must be returned.

