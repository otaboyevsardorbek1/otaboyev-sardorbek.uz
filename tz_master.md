# Master plan — portfolio system

> **Canonical architecture and agent rules:** [AGENTS.md](AGENTS.md).
> **Current state, backlog and acceptance:** [tz.md](tz.md).
> This file is the long-term product/architecture plan, API contract and hosting reference.

Last audit: **2026-10-22**.

## 1. Product goal

A personal portfolio and brand site for Sardorbek Otaboyev:

- 3 locales: `uz`, `en`, `ru`.
- Home page, projects listing and 3 projects with a detail page per locale (9 detail URLs).
- GitHub activity, downloadable CV, contact form and SEO metadata.
- Admin panel for profile/project content without editing HTML.
- Static frontend, PHP + MySQL backend, no Node runtime in production.

## 2. Current system

The repository has one Next.js frontend layer, one PHP content/admin backend and historical Sitejet/legacy files.

### Frontend

- **Next.js App Router at the repo root** (`app/`, `components/`, `content/`).
- Static export via `output: 'export'`, `trailingSlash: true`, `images.unoptimized: true`.
- Node is used at build time only.
- 3 home pages: `/uz/`, `/en/`, `/ru/`.
- 3 listing pages: `/{locale}/projects/`.
- 9 known detail pages: `/{locale}/projects/{slug}/`.
- 3 generic fallback pages: `/{locale}/projects/view/` for admin-created slugs.
- Static admin UI: `/admin/`, `/admin/verify/`, `/admin/dashboard/`.

### Detail-page behaviour

1. Next generates fallback HTML from `content/locales.js` at build time.
2. `components/ProjectDetail.jsx` fetches `/api.php/projects/{slug}?lang=...` with `cache: 'no-store'`.
3. On success it replaces the fallback and updates `document.title`, meta description and canonical URL.
4. On API 503 it keeps the known fallback; on API 404 it hides the fallback and shows “not found”.
5. Admin-created slugs use the Apache fallback to `/projects/view/?slug=...`.

### Backend

- `api.php` — request routing, native contact form, token check, Sitejet proxy and content/admin includes.
- `backend/config.php` — DB, admin and mail settings from env or gitignored `config.local.php`.
- `backend/contact.php` — contact validation, IP hash, rate limit, inbox persistence and native `id=contact` handler.
- `backend/content.php` — public JSON content API.
- `backend/admin.php` — Telegram + email OTP auth, sessions, CSRF, rate limits, audit log, profile/project CRUD, inbox, sitemap, admin-user management and `super`/`admin` role guards (`403 forbidden`, `422 last_super_admin`).
- `backend/bot.php` — private Telegram email bridge: webhook secret plus `admin_users.is_active` authorization (empty table falls back to `admin.telegram_id`), IMAP inbox/read, contact inbox commands, cPanel `mail()` send with `/reply`; `update_id` idempotency, per-admin command rate limits and audit rows; only `/api.php/bot/webhook`.
- `backend/mailer.php` — `mail`, SMTP, Resend, Brevo and local `log` drivers.
- `database/schema.sql`, `database/seed.sql`, `database/migrate-project-sections.sql`, `database/migrate-contact-messages.sql`, `database/migrate-bot-guards.sql`, `database/migrate-admin-role.sql`.

### Legacy / Sitejet files

- `{uz,en,ru}/projects/<slug>/index.html` remain in the repo as historical copies but are **not copied to `out/`** and are not served.
- `js/portfolio.js` is copied to `out/js` but is not used by the active React pages.
- `css/custom.*.css`, `webcard/`, `bundles/`, `g/fonts/`, `images/` and extra `en/*` pages are Sitejet artifacts; they are not part of the active design.

## 3. Data model

MySQL 5.7+ / MariaDB 10.2+, `utf8mb4`.

| Table | Purpose |
|---|---|
| `profiles` | single profile row (`id=1`) with name, email, GitHub, Telegram, avatar |
| `profile_translations` | `role`, `bio`, `quick_text`, `location` per locale |
| `projects` | slug, category, repo/demo URLs, sort order, active flag |
| `project_translations` | tag, title, summary, description, tech, results, stack notes per locale |
| `contact_messages` | contact inbox: form id, locale, sender, subject, message, JSON payload, hashed IP, read flag |
| `admin_users` | bootstrap/active admins |
| `admin_login_challenges` | hashed OTP challenge, attempts, expiry |
| `admin_sessions` | hashed session tokens, expiry, last seen |
| `admin_auth_events` | login/auth event log |
| `admin_audit_log` | content mutation audit log |

`tech`, `results` and `stack_notes` are JSON text (`TEXT`) decoded by PHP. The seed contains 1 profile, 3 projects and 9 translations.

Fresh install:

```bash
mysql -u USER -p DB_NAME < database/schema.sql
mysql -u USER -p DB_NAME < database/seed.sql
```

Existing database (before the project-sections and contact-inbox migrations):

```bash
mysql -u USER -p DB_NAME < database/migrate-project-sections.sql
mysql -u USER -p DB_NAME < database/migrate-contact-messages.sql
mysql -u USER -p DB_NAME < database/migrate-bot-guards.sql
mysql -u USER -p DB_NAME < database/migrate-admin-role.sql
```

`migrate-project-sections.sql` adds `results` and `stack_notes`; like the others it is idempotent (`information_schema` gates each `ALTER`), so all four migrations are safe to re-run. `migrate-contact-messages.sql` also creates `idx_contact_messages_ip (ip_hash, created_at)` for the per-IP rate-limit query; `migrate-bot-guards.sql` adds the bot idempotency/rate-limit tables; `migrate-admin-role.sql` adds `admin_users.role` (`super`/`admin`), its index and a one-time safety promotion of the earliest active admin when no active super admin exists. Re-import `database/seed.sql` **only** when resetting to the default content — it overwrites existing admin edits.

## 4. API contract

Base path: `/api.php`. JSON is UTF-8. `lang` accepts `uz|en|ru`; invalid values fall back to `uz`.

Envelope:

```json
{ "status": "ok", "data": {} }
{ "status": "error", "message": "Human readable", "code": "project_not_found" }
```

### Public

| Method | Path | Purpose |
|---|---|---|
| GET | `/api.php/health` (or `/status`) | liveness check |
| GET | `/api.php/profile?lang=uz` | name, role, location, email, GitHub, Telegram, avatar, short bio |
| GET | `/api.php/projects?lang=uz` | active project cards: slug, title, summary, tech, category, links |
| GET | `/api.php/projects/{slug}?lang=uz` | full detail: description, results, stack notes |
| POST | `/api.php/form_container/submit` | `id=contact`: native validation + `contact_messages` + 5/10 min IP limit; other IDs: Sitejet contract (`id`, `form_<id>[ed-f-N]`) |
| POST | `/api.php/bot/webhook` | private Telegram bot bridge: `X-Telegram-Bot-Api-Secret-Token`, numeric admin ID, `message` updates only |
| GET | `/api.php/bot/health` | read-only monitoring flags (`configured`, `webhook_secret`, `imap`, `database`); no tokens, secrets, passwords or email |
| GET | `/api.php/websites` | token-protected site list (`X-Api-Token` or `Authorization: Bearer`) |

Other paths are proxied to `https://api.sitehub.io`; CDN image URLs are rewritten to `/api.php`/`https://inter-cdn.com` per the existing proxy rules.

Errors: `404 project_not_found`, `503 db_unavailable`, `405 method_not_allowed`, `not_found`. Public GETs use `Cache-Control: public, max-age=60`; errors and form/admin responses use `no-store`.

### Admin

| Method | Path | Purpose |
|---|---|---|
| GET | `/api.php/admin/config` | public bot username/auth URL (no secrets) |
| GET | `/api.php/admin/auth/telegram` | Telegram callback; validates HMAC + `auth_date`, sends OTP |
| GET | `/api.php/admin/auth/status` | masked email, TTL, attempts remaining |
| POST | `/api.php/admin/auth/verify` | verify 6-digit OTP, create session |
| POST | `/api.php/admin/auth/resend` | resend OTP with rate limit |
| GET | `/api.php/admin/session` | session + CSRF; `?optional=1` returns 200 with `{authenticated:false}` |
| POST | `/api.php/admin/logout` | revoke session |
| GET/PUT | `/api.php/admin/profile` | profile + 3 translations |
| GET/POST | `/api.php/admin/projects` | list all projects / create |
| GET/PUT/DELETE | `/api.php/admin/projects/{id}` | read / update / delete |
| GET | `/api.php/admin/messages[?count=1\|unread=1]` | inbox counts / list |
| GET/PUT/DELETE | `/api.php/admin/messages/{id}` | read full message / mark read / delete |
| POST | `/api.php/admin/sitemap` | regenerate `sitemap.xml` from active projects |
| GET/POST | `/api.php/admin/admins` | list / create extra admins |
| GET/PUT/DELETE | `/api.php/admin/admins/{id}` | read / update / delete; self and last-admin guards |

`X-CSRF-Token` is required on mutations. Admin responses must not use wildcard CORS. The slug `view` is reserved for the fallback route. Sitemap generation runs automatically after project mutations and fails soft there; the manual endpoint returns `500 sitemap_write_failed` if the web root is not writable.

## 5. Admin security

- Telegram Login Widget callback: HMAC-SHA256 with the bot token, `auth_date` window, allowed `ADMIN_TELEGRAM_ID` or active `admin_users` row. `admin_users.role` separates `super` (admin management) from `admin` (content + inbox); `/api.php/admin/admins*` requires `super` and the last active super admin cannot be removed, deactivated or demoted.
- OTP: 6 digits, HMAC-hashed, one-time, 10 minutes, 5 attempts.
- Cookies: `admin_session` HttpOnly, Secure on HTTPS, `SameSite=Strict`; `admin_challenge` HttpOnly, `SameSite=Lax`.
- CSRF: HMAC token bound to the session; required on POST/PUT/DELETE. `Origin`/`Referer` must match `site_url` by scheme, host and port; `Origin: null` is rejected; if both headers are absent (CLI), `X-Requested-With: XMLHttpRequest` is required.
- Rate limits: challenge and start attempts per time window; auth events recorded.
- Audit: profile/project, inbox read/delete, sitemap and admin-user mutations written to `admin_audit_log`.
- Auth is disabled unless all required settings are valid: HTTPS production URL, mail driver, Telegram bot token/username, numeric admin ID, valid admin email and a 64-hex app secret.
- Bot bridge: secret token (`hash_equals`) plus `admin_users.is_active = 1` authorization (bootstrap `admin.telegram_id` only while the table is empty or missing); GET/other updates rejected; `update_id` idempotency (`bot_updates_seen`), per-admin limits (`bot_rate_limit`), `admin_audit_log` entries for bot actions; no OTP, bot token or mailbox password is sent to Telegram.
- Never expose bot token, app secret, OTP, webhook secret, IMAP password or real admin email in public JSON or logs.
- Production DB user has only `SELECT, INSERT, UPDATE, DELETE`; DDL is granted temporarily for migrations.

## 6. Deployment model

### 6.1. Runtime

- **Build artifact:** `out/` published to the web root.
- **Runtime:** PHP + MySQL only; no Passenger/Node runtime.
- **Primary server:** Apache with `.htaccess` (`mod_rewrite`, `mod_headers`).
- **Nginx:** `.htaccess` is ignored; equivalent `try_files`/rewrite is required for the generic detail fallback, plus `error_page 404 /404.html` for real 404s.
- **Build:** `npm ci && npm run build` locally or on the server; copy `out/` to the web root. The build never copies `config.local.php`; `run_local.sh`/`run_server.sh` copy it into `out/` with mode `600` for preview only, and production places it in the web root manually.

### 6.2. Database migrations — order

Migrations are applied to the production DB **before** the code that depends on them is deployed. Order is fixed:

| # | File | Idempotent | Purpose |
|---|---|---|---|
| 1 | `database/schema.sql` | ✅ `CREATE TABLE IF NOT EXISTS` | Fresh install (new database) |
| 2 | `database/seed.sql` | ✅ `INSERT ... ON DUPLICATE KEY UPDATE` | Demo content (1 profile, 3 projects, 9 translations) — **production only once, before admin edits** |
| 3 | `database/migrate-project-sections.sql` | ✅ (information_schema checks) | `project_translations.results` + `stack_notes` |
| 4 | `database/migrate-contact-messages.sql` | ✅ | `contact_messages` table + `idx_contact_messages_ip` |
| 5 | `database/migrate-bot-guards.sql` | ✅ | `bot_updates_seen`, `bot_rate_limit` |
| 6 | `database/migrate-admin-role.sql` | ✅ (information_schema checks) | `admin_users.role` + `idx_admin_users_role` + first active admin promoted to `super` when no active super exists |

Fresh install (new database):

```bash
mysql -u USER -p DB_NAME < database/schema.sql
mysql -u USER -p DB_NAME < database/seed.sql
mysql -u USER -p DB_NAME < database/migrate-bot-guards.sql
```

Existing DB (created before the current schema):

```bash
mysql -u USER -p DB_NAME < database/migrate-project-sections.sql
mysql -u USER -p DB_NAME < database/migrate-contact-messages.sql
mysql -u USER -p DB_NAME < database/migrate-bot-guards.sql
mysql -u USER -p DB_NAME < database/migrate-admin-role.sql
```

**Verification (before and after deploy):**

```sql
-- RBAC column
SHOW COLUMNS FROM admin_users LIKE 'role';
-- expected: role | varchar(20) | NO | | admin |

-- At least one active super
SELECT id, telegram_id, role, is_active FROM admin_users;
-- expected: at least one row with role='super', is_active=1

-- Bot guard tables
SHOW TABLES LIKE 'bot_updates_seen';
SHOW TABLES LIKE 'bot_rate_limit';
```

`migrate-admin-role.sql` and `migrate-project-sections.sql` are idempotent: `information_schema` gates each `ALTER` (and the safety-net `UPDATE` runs only while no active super exists). CI drops the columns/indexes on a legacy-shaped database, imports both migrations twice and asserts both runs succeed.

### 6.3. Production deploy — order

Current production: source `/home/host9590/apps/otaboyev-sardorbek.uz`, web root `/home/host9590/public_html`, Node 22 via `/opt/alt/alt-nodejs22/enable`; deploy with `tar` because `rsync` is absent, preserving `config.local.php` and `.well-known/`.

**Steps:**

1. **Migration (production DB):**

   ```bash
   cd /home/host9590/apps/otaboyev-sardorbek.uz
   git pull --ff-only origin main

   mysql -u USER -p DB_NAME < database/migrate-project-sections.sql   # idempotent
   mysql -u USER -p DB_NAME < database/migrate-contact-messages.sql   # idempotent
   mysql -u USER -p DB_NAME < database/migrate-bot-guards.sql      # idempotent
   mysql -u USER -p DB_NAME < database/migrate-admin-role.sql      # idempotent

   mysql -u USER -p DB_NAME -e "SHOW COLUMNS FROM admin_users LIKE 'role';"
   mysql -u USER -p DB_NAME -e "SELECT id, telegram_id, role, is_active FROM admin_users;"
   ```

   At least one active `super` is required. If the migration could not promote anyone (for example `admin_users` was empty), the first-login safety net creates the configured `config.telegram_id` as `super`.

2. **Build (server or local):**

   ```bash
   source /opt/alt/alt-nodejs22/enable
   npm ci
   npm run build
   ```

   Confirm `out/` does **not** contain `config.local.php`, `config.local.js` or `github_keys.txt` (CI and `copy-static.mjs` also assert this).

3. **Tar deploy** (existing `config.local.php` and `.well-known/` preserved):

   ```bash
   tar -C out --exclude='config.local.php' -cf - . | tar -C /home/host9590/public_html -xf -
   ```

4. **`config.local.php`** is placed in the web root manually with `chmod 600`. It must never exist inside `out/`.

5. **`setMyCommands`** (bot command list):

   ```bash
   cd /home/host9590/apps/otaboyev-sardorbek.uz
   BOT_TOKEN="$(php -r "require 'config.local.php'; echo (require 'config.local.php')['admin']['telegram_bot_token'];")" \
     ./scripts/set-bot-commands.sh
   ```

   Or via `curl` manually (see the "setMyCommands" section in `README.md`). `getWebhookInfo` must report `pending_update_count=0`.

6. **Post-deploy smoke (RBAC + bot):**

   ```bash
   curl -sS https://otaboyev-sardorbek.uz/api.php/health              # {"status":"ok"}
   curl -sS https://otaboyev-sardorbek.uz/api.php/admin/config        # enabled=true
   # Super admin: /admin/ → Telegram → OTP → dashboard; session admin.role = 'super'
   # Add a new admin (role=admin) and log in for real:
   curl -sS -H "Cookie: admin_session=..." https://otaboyev-sardorbek.uz/api.php/admin/admins  # 403 forbidden
   # Try deleting/demoting the last active super → 422 last_super_admin
   # is_active=0 admin sends /help in the bot → silently ignored
   # Contact form → Telegram message to every active admin
   ```

7. **Audit check:**

   ```sql
   SELECT action, entity, details, created_at
     FROM admin_audit_log
    ORDER BY id DESC
    LIMIT 20;
   -- admin_created / admin_updated / admin_deleted + role
   -- entity='bot' rows for bot_message_read / bot_message_reply / bot_email_sent
   ```

### 6.4. Environment groups

| Group | Keys |
|---|---|
| DB | `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, optional `DB_SOCKET` |
| Admin | `SITE_URL`/`APP_URL`, `APP_ENV`, `APP_SECRET`/`ADMIN_APP_SECRET`, `TELEGRAM_BOT_TOKEN`, `TELEGRAM_BOT_USERNAME`, `ADMIN_TELEGRAM_ID`, `ADMIN_EMAIL`, `ADMIN_PHONE`, TTL/attempt settings |
| Bot/IMAP | `bot.webhook_secret`, `bot.imap_host` (`panel2.eskiz.uz`), `bot.imap_port` (`993`), `bot.imap_user`, `bot.imap_pass`, `bot.imap_ssl` |
| Mail | `ADMIN_MAIL_DRIVER`/`MAIL_DRIVER`, `ADMIN_MAIL_FROM`, `ADMIN_MAIL_FROM_NAME`, log file, Resend/Brevo keys, SMTP host/port/secure/user/pass |
| Tokens | `API_TOKEN`, `SITEHUB_API_TOKEN`, `OTABOYEV_API_TOKEN` |

DB config: gitignored `config.local.php` or `DB_*` environment variables. Production DB `host9590_portfolio` is least-privilege (`SELECT, INSERT, UPDATE, DELETE`; DDL granted temporarily for migrations).

Production requires HTTPS, a real DB, Telegram bot/ID, email credentials, bot webhook secret and `APP_SECRET`.

### 6.5. CI

CI lives in `.github/workflows/ci.yml` and runs on every push/PR to `main`:

1. `npm ci`
2. `npm run check` (PHP/JS/Bash lint, including `tests/bot-guard-test.php` and `scripts/set-bot-commands.sh`)
3. Static RBAC checks (`admin_require_super`, `last_super_admin`, `bot_find_admin`, `bot_notify_admins`, migration files, `setMyCommands`)
4. MySQL 8.0 service: import `schema.sql` + `seed.sql` + `migrate-bot-guards.sql` (twice) + `migrate-admin-role.sql` twice on a legacy-shaped DB
5. `tests/bot-guard-test.php` (real DB, no Telegram traffic)
6. `npm run build` + secret-free `out/` artifact check

Full workflow in `.github/workflows/ci.yml`. Server-only PHP changes must be verified manually with the same steps.

## 7. QA

### 7.1. Local preview

```bash
npm run check
npm run build
DB_SOCKET=... DB_NAME=... DB_USER=... DB_PASS=... ./run_local.sh
# or: DB_SOCKET=... DB_NAME=... DB_USER=... DB_PASS=... php -S 127.0.0.1:8000 -t out
```

`run_local.sh` copies `config.local.php` into `out/` with mode `600`. `.htaccess` is not honored by the PHP built-in server — rewrite/redirect behaviour is verified on Apache separately.

### 7.2. Static export — artifact

- `out/` contains `.htaccess`, `api.php`, `404.html`, `backend/`, admin pages.
- `out/config.local.php`, `out/config.local.js`, `out/github_keys.txt` are **absent**.
- `out/uz/`, `out/en/`, `out/ru/` — 3 homes; `out/{uz,en,ru}/projects/` — 3 listings; 9 known details; 3 generic `view` routes.
- CI asserts the same.

### 7.3. Frontend and SEO

- `/uz/`, `/en/`, `/ru/` load without console/network/hydration errors.
- One known detail per locale, its language switcher and API refresh.
- One admin-created slug through Apache → `/projects/view/?slug=...` fallback.
- Canonical + hreflang on 3 homes + 3 listings + 9 details.
- CV links resolve (`/files/sardorbek-otaboyev-cv-{uz,en,ru}.{pdf,txt}`).

### 7.4. Content API

- `GET /api.php/profile?lang=uz|en|ru` — valid envelope, `role`, `bio`, `quick_text`, `location`.
- `GET /api.php/projects?lang=...` — array, only `is_active=1`.
- `GET /api.php/projects/gitinsight?lang=...` — `results[]` + `stack_notes[]`.
- Invalid `lang` → `uz` fallback.
- Missing slug → `404 project_not_found`.
- No DB → `503 db_unavailable`; frontend falls back to static content.
- CORS `*` only on public read-only endpoints (`/profile`, `/projects`, `/projects/{slug}`, `/health`, `/status`); `/form_container/submit`, `/admin/*`, `/bot/webhook`, `/bot/health`, `/websites` send **no** CORS header.
- No HSTS over HTTP; HTTPS sends `Strict-Transport-Security: max-age=31536000`.

### 7.5. Contact form and admin inbox

- Form `POST /api.php/form_container/submit` (`id=contact`, `form_contact[ed-f-1..4]`, `lang`, empty `website`).
- New row in `contact_messages`.
- Admin panel **Messages** — count/list/read/unread/delete.
- Honeypot → `400`; invalid email → `422`; 6th request (10 min) → `429`; 11th global-IP → `429`.
- No DB → `503`; frontend `mailto:` fallback.
- **`bot_notify_admins()`** runs after a successful save — a Telegram message to every active admin. Bot API/DB failure keeps the form at `200` and logs to `error_log`.

### 7.6. Admin auth — web

- `/api.php/admin/config` → `enabled:true` (production).
- Telegram callback → challenge cookie → email OTP (log driver locally) → session.
- Wrong OTP 5 times → `429 too_many_attempts` on the 6th.
- Expired challenge (11 min) → `401 challenge_expired`.
- Expired session (12 h) → `401 auth_required`.
- Missing CSRF header → `403 csrf_failed`.
- `Origin: null` / `Origin: evil` / header-less CLI → `403 origin_rejected`; header-less CLI with `X-Requested-With: XMLHttpRequest` passes.
- Admin responses carry no CORS header.
- `POST` logout clears the session.

### 7.7. RBAC (2026-10-23)

**Setup:** test DB, 2 admins — `A` (`role='super'`), `B` (`role='admin'`), both `is_active=1`.

| # | Test | Expected |
|---|---|---|
| 7.7.1 | `GET /api.php/admin/session` (A) | `admin.role='super'` |
| 7.7.2 | `GET /api.php/admin/session` (B) | `admin.role='admin'` |
| 7.7.3 | A dashboard → **Admins** tab | visible |
| 7.7.4 | B dashboard → **Admins** tab | **hidden** |
| 7.7.5 | `GET /api.php/admin/admins` (A) | `200` |
| 7.7.6 | `GET /api.php/admin/admins` (B) | `403 forbidden` |
| 7.7.7 | `POST /api.php/admin/admins` (B) | `403 forbidden` |
| 7.7.8 | `DELETE /api.php/admin/admins/{A}` (B) | `403 forbidden` |
| 7.7.9 | `POST /api.php/admin/admins` (A, role=`admin`) | `201`, `role='admin'` |
| 7.7.10 | `POST /api.php/admin/admins` (A, role=`super`) | `201`, `role='super'` |
| 7.7.11 | `POST /api.php/admin/admins` (A, role=`owner`) | `422 validation_failed`, `errors.role` |
| 7.7.12 | A sets own `is_active=0` | `422 cannot_deactivate_self` |
| 7.7.13 | A deletes self | `422 cannot_delete_self` |
| 7.7.14 | A sets own `role='admin'` (no other super) | `422 last_super_admin` |
| 7.7.15 | A sets own `role='admin'` (other active super exists) | `200` |
| 7.7.16 | Delete the last active super | `422 last_super_admin` |
| 7.7.17 | Deactivate the last active super | `422 last_super_admin` |
| 7.7.18 | Last 5 `admin_audit_log` rows | `admin_created/updated/deleted` + `role` |
| 7.7.19 | B `GET/PUT /api.php/admin/profile`, `POST /api.php/admin/projects`, `GET /api.php/admin/messages`, `POST /api.php/admin/sitemap` | all allowed (`200`/`201`) |

**Session/role invalidation (critical):**

| # | Test | Expected |
|---|---|---|
| 7.7.20 | A login (super), B login (admin) | both sessions active |
| 7.7.21 | A → B `role='super'` | B `/admins` → `200` immediately |
| 7.7.22 | A → B `role='admin'` | B `/admins` → `403 forbidden` immediately |
| 7.7.23 | A → B `is_active=0` | B next request → `401 auth_required` immediately |
| 7.7.24 | A → B `is_active=1` | B old session stays invalid; re-login required |
| 7.7.25 | A → B `DELETE` | B session → `401` |

### 7.8. Bot multi-admin (2026-10-23)

**Setup:** test DB, valid `bot.webhook_secret`, 2 active admins — `A` (`role='super'`), `B` (`role='admin'`), `C` (`is_active=0`). Fake update script:

```bash
send_update() {
  local uid=$1; local tg=$2; local text=$3
  curl -sS -X POST "http://127.0.0.1:8000/api.php/bot/webhook" \
    -H "X-Telegram-Bot-Api-Secret-Token: <secret>" \
    -H "Content-Type: application/json" \
    --data "{\"update_id\":$uid,\"message\":{\"message_id\":$uid,\"from\":{\"id\":$tg},\"chat\":{\"id\":$tg,\"type\":\"private\"},\"text\":\"$text\"}}"
}
```

| # | Test | Expected |
|---|---|---|
| 7.8.1 | `GET /api.php/bot/webhook` | `405`, `Allow: POST` |
| 7.8.1a | `GET /api.php/bot/health` | `200`, `{configured,webhook_secret,imap,database}` — only booleans, no token/secret/password |
| 7.8.1b | `POST /api.php/bot/health` | `405`, `Allow: GET` |
| 7.8.1c | DB down + `/bot/health` | `200`, `database:false`; the endpoint has no side effects (no Telegram alert); `production-ops.sh cron` reports it once |
| 7.8.2 | `POST` without secret | `403` |
| 7.8.3 | `POST` with wrong secret | `403` |
| 7.8.4 | `POST` correct secret, `from.id=999` (unknown) | `200 'ok'`, **no** Telegram message |
| 7.8.5 | `POST` correct secret, C (`is_active=0`) | `200 'ok'`, **silently ignored** |
| 7.8.6 | `POST` correct secret, A, `/help` | `200 'ok'`, help text |
| 7.8.7 | `POST` correct secret, B, `/whoami` | `200 'ok'`, name + telegram_id + email |
| 7.8.8 | `POST` with `chat.type='group'` | `200 'ok'`, silently ignored |
| 7.8.9 | `POST` with `chat.id != from.id` | `200 'ok'`, silently ignored |
| 7.8.10 | Body > 131072 bytes | `413` |
| 7.8.11 | Same `update_id` twice | 1st `200` (action), 2nd `200` (no action); exactly 1 `bot_updates_seen` row |
| 7.8.12 | `bot_rate_limit_ok(333333,'send',10)` 11th call | `false` |
| 7.8.13 | A → B `is_active=0` | ✅ |
| 7.8.14 | B `/help` | `200 'ok'`, **silently ignored** |
| 7.8.15 | A → B `is_active=1` | ✅ |
| 7.8.16 | B `/help` | reply is sent |
| 7.8.17 | A → B `DELETE` | ✅ |
| 7.8.18 | B `/help` | **silently ignored** |
| 7.8.19 | Empty `admin_users` + `config.telegram_id` sends `/help` | bootstrap reply; 1 row with `role='super'` |

**Bot commands (active admin):**

| # | Command | Expected |
|---|---|---|
| 7.8.20 | `/whoami` | name + telegram_id + email |
| 7.8.21 | `/messages` (empty inbox) | "Contact inbox bo‘sh." |
| 7.8.22 | Contact form → `/messages` | `#id` in the list |
| 7.8.23 | `/message <id>` | full message; `is_read=1` in DB |
| 7.8.24 | `/unread` | only `is_read=0` |
| 7.8.25 | `/reply <id> \| test` | email sent; `bot_message_reply` in `admin_audit_log` |
| 7.8.26 | `/reply <id>` (no pipe) | format error |
| 7.8.27 | `/reply 999999 \| test` | "Xabar yoki email topilmadi." |
| 7.8.28 | `/stats` | `unread` + `total` + `active admins` |
| 7.8.29 | `/inbox` (IMAP not configured) | "Email qutisiga ulanib bo‘lmadi." |
| 7.8.30 | `/send email@example.com \| M \| B` | email sent; `bot_email_sent` in `admin_audit_log` |
| 7.8.31 | `/send not-an-email \| M \| B` | `Invalid email address` |
| 7.8.32 | Unknown text | `/help` reply |

### 7.9. Migrations and idempotency

| # | Test | Expected |
|---|---|---|
| 7.9.1 | `migrate-bot-guards.sql` once | OK; 2 tables created |
| 7.9.2 | `migrate-bot-guards.sql` twice | OK (idempotent) |
| 7.9.3 | `migrate-admin-role.sql` once (legacy DB) | OK; `role` column added; at least 1 `super` |
| 7.9.4 | `migrate-admin-role.sql` twice | OK (idempotent); still one promoted super, no duplicate column |
| 7.9.5 | Fresh `schema.sql` | `role VARCHAR(20) NOT NULL DEFAULT 'admin'` |
| 7.9.6 | First login on a legacy DB (no role) | safety net sets `role='super'` |
| 7.9.7 | Empty `admin_users`, login with config ID | bootstrap `role='super'` |
| 7.9.8 | `migrate-project-sections.sql` twice (legacy DB without columns) | OK (idempotent); both columns present |
| 7.9.9 | `migrate-contact-messages.sql` twice (index dropped first) | OK (idempotent); `idx_contact_messages_ip` present |

### 7.10. Apache (`.htaccess`)

- `/de/`, `/nope` → real 404 + `404.html`.
- `/{locale}/projects/<admin-slug>/` → `200` (view fallback rewrite).
- `/api.php/admin/...` PATH_INFO is not swallowed by the rewrite.
- `config.local.php`, `github_keys.txt`, `backend/`, `database/` → `403`.
- `/api.php/health` → `200`.
- `OPTIONS /api.php/profile` → `Access-Control-Allow-Origin: *`.
- `OPTIONS /api.php/form_container/submit` → **no** CORS.
- `POST /api.php/admin/logout` with `Origin: evil` → `403 origin_rejected`.
- HTTPS → `Strict-Transport-Security: max-age=31536000`; HTTP → no header.

### 7.11. Frontend headless

- `/uz/`, `/en/`, `/ru/`, `/uz/projects/`, a known detail, `/admin/`, authenticated `/admin/dashboard/` — no console/CSP/request-failure or hydration errors.

### 7.12. Diff

- `git diff --check`
- `git diff --stat`
- Full diff review before commit.

## 8. Roadmap

Order matters: correctness and production safety before optional features.

| Phase | Goal | Status / items |
|---|---|---|
| 0 | Repo hygiene | Completed: migration and docs are committed and pushed to `origin/main`. Remove or ignore the empty `README` and `CodeAnalyzer_v2.py` only with owner approval |
| 1 | Production readiness | Completed: cPanel DB + least privilege, `config.local.php`, schema/seed, mailboxes/forwarder, HTTPS admin/OTP test, bot webhook/IMAP/send. Remaining: external DKIM/DMARC + Exim signing, real BotFather widget test, full redeploy |
| 2 | Content operations | Completed: contact inbox, native contact form and extra-admin UI with `super`/`admin` roles. Remaining: add a rebuild/publish hook so admin edits reach static HTML crawlers |
| 3 | SEO and routing | Completed: auto-generate `sitemap.xml` for new slugs and real 404. Remaining: improve generic detail metadata/hreflang |
| 4 | Maintenance | Idempotent migration runner, media upload, legacy cleanup, remove unused legacy assets once safe |
| — | Completed | CV downloads; 15-URL sitemap; canonical/hreflang; React home/listing; MySQL content API; Telegram + OTP admin; 9 detail pages migrated; Apache fallback for new slugs; contact inbox; sitemap generator; admin-user manager; native contact form; real 404; bot multi-admin bridge with web inbox; RBAC with last-super guard; CI RBAC/bot-guard checks |

Owner decisions are required for locale parity (`en/*`), media upload and legacy cleanup.

## 9. Migration history

**Done 2026-10-20/21:**

- CV downloads (PDF primary + TXT) per locale.
- `sitemap.xml` with 3 homes + 3 listings + 9 details.
- Canonical + hreflang on all real pages.
- Next.js homepage and listing pilot built and verified.
- MySQL content API + seed created and tested.
- `/uz/projects/` and `/ru/projects/` 403 gap closed in `out/`.
- Admin panel with Telegram HMAC + email OTP, secure sessions, CSRF, rate limits, audit log and 3-language CRUD.
- 9 detail pages moved to React, with `results`/`stack_notes`, admin fields and seed/migration.
- Apache fallback for admin-created slugs; tested without a rebuild and in headless Chromium.
- Native contact form with honeypot and IP limit; `contact_messages` inbox with read/unread/delete.
- Automatic sitemap regeneration and admin-user CRUD with self/last-admin protection.
- Real `404.html` handling in Apache; SPA catch-all removed.

**Production deployment 2026-10-22:**

- cPanel DB `host9590_portfolio`, schema/seed, least-privilege user.
- `config.local.php` with rotated `APP_SECRET`, DB/mailbox/bot secrets; mode 600.
- `admin@` OTP/inbox, `no-reply@` send, `contact@` → `admin@` forwarder; Port25 SPF pass, DKIM none, DMARC `p=none`.
- Telegram bot webhook with secret, commands and IMAP bridge; fake-update tests for `/help`, `/inbox`, `/read`, `/send` passed.
- API/static CSP/HSTS/frame/referrer/COOP/CORP headers; `X-Powered-By` removed; headless browser checks passed.
- Full tar deploy completed; SSH key passphrase rotated after setup exposure (new value stored outside the repo, mode 600).
- Outstanding: external DKIM/DMARC and Exim signing; real Telegram Login/widget.

**Security hardening 2026-10-22 (post-deploy, needs redeploy):**

- `config.local.php` removed from the build artifact; CI and local checks assert its absence.
- CORS narrowed to public read-only JSON; HSTS sent only over HTTPS; `display_errors=0` + `log_errors`.
- Admin origin check tightened (strict scheme/host/port, `Origin: null` rejected, `X-Requested-With` fallback).
- Bot `update_id` idempotency and command rate limits; `database/migrate-bot-guards.sql` added and tested twice.
- Legacy Google Maps API keys redacted from tracked `en/*` and `webcard` files; they remain in git history, so owner rotation is required.
- Owner-only remain: GitHub PAT revoke, `APP_SECRET`/bot token rotation, OTP move to `admin@`, and Google Maps key rotation/restriction.

**RBAC and bot multi-admin 2026-10-23:**

- `admin_users.role` (`super`/`admin`) via idempotent `database/migrate-admin-role.sql`, bootstrap `super`, first-login safety net and `idx_admin_users_role`.
- `/api.php/admin/admins*` is super-only (`403 forbidden`); the last active super admin cannot be deleted, deactivated or demoted (`422 last_super_admin`); role changes take effect on the next request because sessions join `admin_users`.
- Admin dashboard hides the **Admins** tab from plain admins and exposes role selection/badges to super admins.
- Bot bridge resolves permissions from `admin_users.is_active=1`, keeps per-admin rate limits, writes `admin_audit_log` rows, and adds `/whoami`, `/messages`, `/message`, `/unread`, `/reply`, `/stats`.
- New contact submissions notify up to 10 active admins through Telegram without blocking the form.
- `tests/bot-guard-test.php` covers pure helpers, `bot_find_admin`, per-admin rate limits, `update_id` idempotency and `bot_audit`; CI imports all migrations, verifies RBAC/bot guards statically and builds the export.

**Consistency and hardening 2026-10-24:**

- Every migration is idempotent now: `migrate-project-sections.sql` gates each `ALTER` with `information_schema`, and `migrate-contact-messages.sql` adds `idx_contact_messages_ip (ip_hash, created_at)` on old and new databases. CI drops the columns/index, imports the files twice and asserts success.
- `production-ops.sh migrate` no longer warns about a one-time script; `verify` also checks bot `webhook_secret` and `database`, and the 5-minute monitor reports unconfigured bot, missing secret and DB outage once per incident (state files), clearing the alert on recovery.
- OTP and bot email share one `admin_mail_send()` path (mail/SMTP/Resend/Brevo/log) with `Reply-To` support; `bot/health` is side-effect free and never triggers an alert.
- Admin/contact validation counts UTF-8 characters for `VARCHAR` limits, so Uzbek/Russian content is neither rejected nor truncated; `TEXT` fields stay byte-limited.
- `.htaccess` sends HSTS only on HTTPS (or `X-Forwarded-Proto: https`) responses; PHP already did.
- `tests/bot-guard-test.php` now covers the shared mailer, OTP log marker, UTF-8 length helpers and an end-to-end Cyrillic contact submission (47 checks). The HTTP integration suite covers 118 checks.

**Remaining:**

- External DKIM/DMARC and Exim signing are blocked on Eskiz DNS/WHM access; real BotFather Login and a real Telegram admin update still need owner testing.
- Static fallback requires a rebuild for crawlers to see admin-edited copy.
- Generic detail metadata/canonical is corrected only after JS runs.
- Media upload, `en/*` locale parity and legacy cleanup are not implemented.
- Email notification for contact messages is not wired; the `contact@` forwarder and admin inbox are the source of truth.

## 10. Hosting appendix (shared cPanel)

This section is reference material for the hosting account; only the PHP/static parts apply to this portfolio.

### PHP projects

1. cPanel → **Domains** → **Create A New Domain**.
2. Enter the subdomain and set **Document Root** to the project's public folder.
3. Route requests through `.htaccess` to `index.php` if the project uses a front controller.

### Python/Node projects (other subdomains)

- Python: cPanel **Setup Python App** with Passenger, `passenger_wsgi.py` and a webhook-based bot; no long-running polling process.
- Node: cPanel **Setup Node.js App** with the newest available runtime; apps must use the Passenger-provided port.
- These runtimes are **not** used by this portfolio. The portfolio frontend is static and the backend is PHP.

### Shared hosting limits

- No long-running processes; use webhooks and Cron Jobs.
- CPU/RAM limits can suspend an app temporarily.
- AutoSSL issues certificates per subdomain; monitor **SSL/TLS Status**.
- JetBackup 5 is available; enable regular backups.

## 11. Final guidance

Keep the site stable, multilingual and production-friendly. Frontend changes belong in `app/` and `components/`, shared styling in `css/portfolio.css`, and backend changes in `api.php` / `backend/` with PHP 7.1 syntax. Do not add a Node runtime or a second server. Keep the bot private: webhook secret + numeric admin allowlist, no OTP/credentials in Telegram. Verify with `npm run check`, `npm run build`, the content API curls and a browser preview before deploying. Update `AGENTS.md`, `tz.md` and this file together whenever architecture, routes, schema, deploy steps or security behavior changes.
