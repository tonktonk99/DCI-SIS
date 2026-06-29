# DCI-SIS — Student Information System

**Stack:** PHP 8.3 · MySQL 8.0 · Apache / Nginx  
**Scale:** ~10,000 accounts · 500–1,000 peak concurrent users  
**Roles:** Admin · Registrar · Professor · Student · Alumni

---

## Local Development (MAMP)

```bash
# 1. Clone and enter project
cd /Applications/MAMP/htdocs/dci-sis

# 2. Configure database connection
cp config/database.example.php config/database.php
# Edit config/database.php — set DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS
# or export environment variables:
export APP_ENV=local DB_PORT=8889 DB_USER=root DB_PASS=root

# 3. Check migration status
php scripts/migrate.php status

# 4. Apply pending migrations
php scripts/migrate.php migrate --apply

# 5. Seed test accounts (local only)
SEED_CONFIRM=YES SEED_DEFAULT_PASSWORD='DevPass123!' \
  php scripts/seed_staging.php --apply

# 6. Access app
open http://localhost/dci-sis/login.php
# Test accounts: admin_test, registrar_test, prof_test, student_test, alumni_test
```

---

## Key Documentation

| Document | Purpose |
|----------|---------|
| [`docs/staging-deployment-checklist.md`](docs/staging-deployment-checklist.md) | **Start here** — step-by-step staging deployment |
| [`docs/migrations.md`](docs/migrations.md) | Database migration runner guide |
| [`docs/staging-seed-data.md`](docs/staging-seed-data.md) | Staging seed data guide and QA scenarios |
| [`docs/backup-restore-plan.md`](docs/backup-restore-plan.md) | Backup schedule, retention, restore drill |
| [`docs/production-checklist.md`](docs/production-checklist.md) | Production infrastructure requirements |
| [`docs/production-smoke-checklist.md`](docs/production-smoke-checklist.md) | Production pre/post-deploy checklist |
| [`docs/test-plan.md`](docs/test-plan.md) | Full test plan (148 test cases, all 5 roles) |
| [`docs/load-test-plan.md`](docs/load-test-plan.md) | k6 load test plan and staging scenarios (Phase 1O) |
| [`docs/final-production-readiness-review.md`](docs/final-production-readiness-review.md) | Final production readiness audit: scorecard, blockers, Go/No-Go (Phase 1P) |
| [`docs/staging-execution-plan.md`](docs/staging-execution-plan.md) | Blocker triage + ordered commit plan + staging deploy steps (Phase 2A) |
| [`docs/pilot-wave-1-plan.md`](docs/pilot-wave-1-plan.md) | Pilot Wave 1 execution plan: scope, entry criteria, monitoring, rollback (Phase 2F) |

---

## Scripts

| Script | Usage |
|--------|-------|
| `scripts/migrate.php` | Migration runner: `status` · `migrate --dry-run` · `migrate --apply` · `checksum` |
| `scripts/seed_staging.php` | Seed test accounts: `--dry-run` · `--apply` (requires `SEED_CONFIRM=YES`) |
| `scripts/backup_database.sh` | Create timestamped DB backup (requires `DB_NAME`, `DB_USER`, `DB_PASS`) |
| `scripts/restore_database.sh` | Restore DB from backup (requires `RESTORE_CONFIRM=YES`) |
| `scripts/smoke_check.sh` | Automated public smoke check (requires `BASE_URL`) |

---

## Environment Variables

Copy `.env.example` for reference. Never commit `.env` or `config/database.php`.

| Variable | Default | Description |
|----------|---------|-------------|
| `APP_ENV` | `local` | `local` · `staging` · `production` |
| `APP_TIMEZONE` | `Asia/Bangkok` | IANA timezone |
| `DB_HOST` | `127.0.0.1` | Database host |
| `DB_PORT` | `3306` | Database port (MAMP: `8889`) |
| `DB_NAME` | `dci_sis` | Database name |
| `DB_USER` | `root` | Database user |
| `DB_PASS` | *(empty)* | Database password |

---

## PHP Requirements

- PHP **8.3+**
- Extensions: `pdo_mysql`, `mbstring`, `openssl`, `session`
- MySQL / MariaDB **8.0+** with `utf8mb4` charset
