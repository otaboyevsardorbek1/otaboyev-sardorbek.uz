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
- `backend/admin.php` — Telegram + email OTP auth, sessions, CSRF, rate limits, audit log, profile/project CRUD, inbox, sitemap and admin-user management.
- `backend/bot.php` — private Telegram email bridge: webhook secret + numeric admin allowlist, IMAP inbox/read and cPanel `mail()` send; only `/api.php/bot/webhook`.
- `backend/mailer.php` — `mail`, SMTP, Resend, Brevo and local `log` drivers.
- `database/schema.sql`, `database/seed.sql`, `database/migrate-project-sections.sql`, `database/migrate-contact-messages.sql`.

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
```

`migrate-project-sections.sql` adds `results` and `stack_notes`; it is a one-time, non-idempotent script. `migrate-contact-messages.sql` uses `CREATE TABLE IF NOT EXISTS` and is safe to run again. Re-import `database/seed.sql` **only** when resetting to the default content — it overwrites existing admin edits.

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

- Telegram Login Widget callback: HMAC-SHA256 with the bot token, `auth_date` window, allowed `ADMIN_TELEGRAM_ID` or active `admin_users` row.
- OTP: 6 digits, HMAC-hashed, one-time, 10 minutes, 5 attempts.
- Cookies: `admin_session` HttpOnly, Secure on HTTPS, `SameSite=Strict`; `admin_challenge` HttpOnly, `SameSite=Lax`.
- CSRF: HMAC token bound to the session; required on POST/PUT/DELETE.
- Rate limits: challenge and start attempts per time window; auth events recorded.
- Audit: profile/project, inbox read/delete, sitemap and admin-user mutations written to `admin_audit_log`.
- Auth is disabled unless all required settings are valid: HTTPS production URL, mail driver, Telegram bot token/username, numeric admin ID, valid admin email and a 64-hex app secret.
- Bot bridge: secret token (`hash_equals`) plus numeric `admin.telegram_id`; GET/other updates rejected; no OTP, bot token or mailbox password is sent to Telegram.
- Never expose bot token, app secret, OTP, webhook secret, IMAP password or real admin email in public JSON or logs.
- Production DB user has only `SELECT, INSERT, UPDATE, DELETE`; DDL is granted temporarily for migrations.

## 6. Deployment model

- Build artifact: `out/` published to the web root.
- Runtime: PHP + MySQL only; no Passenger/Node runtime.
- Primary server: Apache with `.htaccess` (`mod_rewrite`, `mod_headers`).
- Nginx: `.htaccess` is ignored; equivalent `try_files`/rewrite is required for the generic detail fallback, plus `error_page 404 /404.html` for real 404s.
- Build locally or on the server: `npm ci && npm run build`; copy `out/` to the web root.
- Current production: source `/home/host9590/apps/otaboyev-sardorbek.uz`, web root `/home/host9590/public_html`, Node 22 via `/opt/alt/alt-nodejs22/enable`; deploy with `tar` because `rsync` is absent, preserving `config.local.php` and `.well-known/`.
- DB config: gitignored `config.local.php` or `DB_*` environment variables. Production DB `host9590_portfolio` is least-privilege.
- Production requires HTTPS, a real DB, Telegram bot/ID, email credentials, bot webhook secret and `APP_SECRET`.
- CI exists in `.github/workflows/ci.yml` (`npm ci`, `npm run check`, `npm run build`); run the same checks manually for server-only PHP changes.

Environment groups:

| Group | Keys |
|---|---|
| DB | `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, optional `DB_SOCKET` |
| Admin | `SITE_URL`/`APP_URL`, `APP_ENV`, `APP_SECRET`/`ADMIN_APP_SECRET`, `TELEGRAM_BOT_TOKEN`, `TELEGRAM_BOT_USERNAME`, `ADMIN_TELEGRAM_ID`, `ADMIN_EMAIL`, `ADMIN_PHONE`, TTL/attempt settings |
| Bot/IMAP | `bot.webhook_secret`, `bot.imap_host` (`panel2.eskiz.uz`), `bot.imap_port` (`993`), `bot.imap_user`, `bot.imap_pass`, `bot.imap_ssl` |
| Mail | `ADMIN_MAIL_DRIVER`/`MAIL_DRIVER`, `ADMIN_MAIL_FROM`, `ADMIN_MAIL_FROM_NAME`, log file, Resend/Brevo keys, SMTP host/port/secure/user/pass |
| Tokens | `API_TOKEN`, `SITEHUB_API_TOKEN`, `OTABOYEV_API_TOKEN` |

## 7. QA

```bash
npm run check
npm run build
DB_SOCKET=... DB_NAME=... DB_USER=... DB_PASS=... php -S 127.0.0.1:8000 -t out
```

Verify:

- `/uz/`, `/en/`, `/ru/` load without console/network/hydration errors.
- One known detail per locale, its language switcher and API refresh; one admin-created slug through Apache.
- Content API: profile/projects/detail for all locales, invalid `lang`, missing slug and `db_unavailable`.
- Admin: config `enabled`; Telegram callback → challenge cookie → email code → session; wrong OTP, expired challenge/session, missing CSRF, CORS separation and logout.
- Admin profile/project create→update→delete; confirm public API and 3-language validation.
- Contact form request/response in the network tab; `contact_messages` row; inbox count/list/read/delete; honeypot and 429 limit.
- Admin-user add/edit/delete with self/last-admin guards; sitemap button and automatic regeneration after project save/delete.
- Apache: real 404 for `/de/`/`/nope`, generic detail fallback, denied backend/config paths.
- CV links resolve; `.htaccess` rewrites tested on Apache.
- `git diff --check` and a full diff review.

## 8. Roadmap

Order matters: correctness and production safety before optional features.

| Phase | Goal | Status / items |
|---|---|---|
| 0 | Repo hygiene | Completed: migration and docs are committed and pushed to `origin/main`. Remove or ignore the empty `README` and `CodeAnalyzer_v2.py` only with owner approval |
| 1 | Production readiness | Completed: cPanel DB + least privilege, `config.local.php`, schema/seed, mailboxes/forwarder, HTTPS admin/OTP test, bot webhook/IMAP/send. Remaining: external DKIM/DMARC + Exim signing, real BotFather widget test, full redeploy |
| 2 | Content operations | Completed: contact inbox, native contact form and extra-admin UI. Remaining: add a rebuild/publish hook so admin edits reach static HTML crawlers |
| 3 | SEO and routing | Completed: auto-generate `sitemap.xml` for new slugs and real 404. Remaining: improve generic detail metadata/hreflang |
| 4 | Maintenance | Idempotent migration runner, media upload, legacy cleanup, remove unused legacy assets once safe |
| — | Completed | CV downloads; 15-URL sitemap; canonical/hreflang; React home/listing; MySQL content API; Telegram + OTP admin; 9 detail pages migrated; Apache fallback for new slugs; contact inbox; sitemap generator; admin-user manager; native contact form; real 404 |

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
- Outstanding: external DKIM/DMARC and Exim signing; real Telegram Login/widget; final full tar deploy.

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
