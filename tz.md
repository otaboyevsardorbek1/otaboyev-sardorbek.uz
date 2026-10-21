# Task brief — current state, backlog and acceptance

> **Source of truth for architecture and rules:** [AGENTS.md](AGENTS.md).
> **Long-term plan and hosting notes:** [tz_master.md](tz_master.md).
> This file is the current operational brief: what is true today, what is blocked, what comes next.

## Snapshot — 2026-10-21

- Frontend: Next.js App Router at the repo root, static export to `out/`; home, listing and 9 project detail pages are React.
- Detail pages: build-time fallback from `content/locales.js`, then refresh from `/api.php/projects/{slug}?lang=...`; title, meta description and canonical are updated client-side.
- Admin-created projects: `.htaccess` rewrites unknown `/{locale}/projects/<slug>/` URLs to `/{locale}/projects/view/?slug=...`; the client fetches the API and updates canonical.
- Backend: PHP `api.php` + `backend/*.php`; MySQL content API, native contact form with admin inbox, Sitejet proxy and protected `/admin/` CRUD.
- Auth: Telegram Login Widget → email OTP → session with CSRF; admin routes are noindex and have no wildcard CORS. The bot, `SITE_URL`, admin Telegram ID and `APP_SECRET` are set in gitignored `config.local.php`; DB and email delivery still need production verification.
- DB: `database/schema.sql` + `database/seed.sql` seed 1 profile, 3 projects, 9 translations and an empty contact inbox; existing databases need `database/migrate-project-sections.sql` and the idempotent `database/migrate-contact-messages.sql`. Re-importing `seed.sql` resets content and overwrites admin edits.
- SEO: `sitemap.xml` starts with 15 real URLs and is regenerated after every admin project create/update/delete (or from the admin button); canonical/hreflang exists on 3 homes, 3 listings and 9 detail pages.
- Routing: unknown URLs now return a real HTTP 404 with `out/404.html`; the admin-created slug fallback is unchanged.
- Last verified: `npm run check` and `npm run build` pass; API returns 200/404/429/503 as expected; contact inbox, sitemap generator, admin-user CRUD, Apache 404 and generic detail fallback work.

### Audit conclusions

The implemented architecture is coherent: static Next pages, a PHP-only runtime backend, a DB-backed content API and a working two-step admin login. No blocking defect was found in the audited page/API/auth paths. The main risks are operational, not architectural:

- the Telegram bot/domain/admin ID and app secret are configured locally, but cPanel DB credentials and email DNS/verification are still missing;
- the migration and documentation are committed and pushed to `origin/main`;
- the static detail fallback is still not crawler-fresh after admin edits;
- contact messages now persist in MySQL and are available in the admin inbox; email notification remains unverified.

The backlog below turns those findings into ordered work.

### Repository state (do not lose this context)

The detail-page migration and documentation rework were committed and pushed to `origin/main` on 2026-10-21 with `feat: migrate portfolio to Next.js with DB-backed content and admin` and `docs: align architecture, task brief and security guides`. Only unrelated files (`CodeAnalyzer_v2.py` and the empty `README`) remain untracked on purpose. Review `git status` and `git diff` before the next commit.

## Active work

The contact inbox, automatic sitemap, admin-user manager, native contact form and real 404 are implemented and verified locally. Commit/push this change set, then move to production configuration.

## Baseline completed

- CV downloads: PDF primary + TXT fallback, per locale.
- `sitemap.xml` with 15 real URLs; canonical + hreflang on homes, listings and details.
- Next.js homepage: static export, theme toggle, mobile nav, reveal, tilt, project filter, GitHub fetch, contact form, content API fallback.
- React listing pages at `/{uz,en,ru}/projects/` with theme persistence, mobile nav, language switcher and project cards.
- MySQL schema/seed and content API tested for uz/en/ru, invalid `lang`, missing slug and DB 503.
- Contact form posts to the public endpoint without sending an unused API token; `id=contact` now validates natively, stores in `contact_messages`, rejects the `website` honeypot and rate-limits 5 messages per IP per 10 minutes.
- Admin inbox: unread count, list/detail, mark read/unread and delete; stored payload keeps all submitted fields and attachment metadata.
- Automatic `sitemap.xml` regeneration after project create/update/delete plus a manual “Sitemapni yangilash” button.
- Admin-user manager: list/add/edit/delete extra admins with self-delete, self-deactivate and last-active-admin protection.
- Real 404: `.htaccess` uses `ErrorDocument 404 /404.html`; the SPA catch-all was removed.
- GitHub Actions workflow runs `npm ci`, PHP/JS lint, static build and export-file checks on push/PR.
- Project detail migration: React routes, `ProjectDetail.jsx`, API refresh, `results`/`stack_notes`, admin fields, seed/migration SQL and Apache fallback for new slugs.
- Admin panel: Telegram HMAC + email OTP, hashed one-time codes, HttpOnly/Secure/SameSite cookies, CSRF, rate limits, audit log and 3-language profile/project CRUD.
- Apache checks: admin PATH_INFO is not caught by the fallback, `backend/`/`database/`/`config.local.php` are denied, and known + admin-created detail URLs return 200.

## Backlog

| Prio | Problem | Evidence / where | Next action |
|---|---|---|---|
| P1 | cPanel DB credentials and email delivery are not configured | `config.local.php` / env; `backend/config.php` | Create the cPanel DB; import schema/seed for a fresh DB or run the migration for an existing one; fill the DB credentials in gitignored `config.local.php`; verify the `mail`/SMTP path and BotFather `/setdomain`; test `/admin/` on HTTPS |
| P2 | `en/*` legacy Sitejet pages have no uz/ru counterparts | `en/portfolio`, `en/tech-stack`, … | Owner decision: keep and translate, or remove from the repo/sitemap |
| P3 | Detail fallback is build-time HTML; crawlers may see stale copy after admin edits | `components/ProjectDetail.jsx`, `[slug]/page.js` | Accept for now or add a production rebuild/publish hook |
| P3 | Generic detail route serves listing metadata/canonical until JS runs | `app/(site)/[locale]/projects/view/page.js` | Add static/generic metadata strategy or accept the JS-only correction |
| P3 | `database/migrate-project-sections.sql` is a one-time, non-idempotent migration | `database/migrate-project-sections.sql` | Add an idempotent migration runner if the schema changes again |
| P3 | Media upload (avatar/project images) is not implemented | `components/admin/ProfileEditor.jsx`, `backend/admin.php` | Add validated upload storage only with owner approval; keep `avatar_url` as the fallback |
| P3 | Legacy HTML, `js/portfolio.js`, empty `README` and `CodeAnalyzer_v2.py` create noise | repo root / legacy pages | Remove or archive only with owner approval; never edit Sitejet artifacts casually |

Anything that changes visible content, metadata or links must be applied to `uz`, `en` and `ru` in the same change.

## Typical tasks

### Content change

The **runtime source** is the admin panel + MySQL. For a code or fresh-install change:

1. Update the relevant `content/locales.js` fallback in all three locales.
2. Mirror the same text in `database/seed.sql`.
3. If production already has the row, update it through `/admin/` or a controlled SQL import.
4. Rebuild and verify the DB API returns the same text; remember that the static HTML only updates after a rebuild.

### Project or CV update

Update `files/sardorbek-otaboyev-cv-{uz,ru,en}.txt`, regenerate the PDFs with pandoc, then check every download link in all locales. Project copy lives in `content/locales.js`, `database/seed.sql` and the admin panel; detail routes are `/{locale}/projects/[slug]/`.

### Admin change

Keep the two-step flow (Telegram widget → email OTP), the `{status,data}` / `{status,error,message,code}` envelope, `X-CSRF-Token` on mutations and admin CORS separation. Never expose bot token, app secret, admin email or OTP codes in public JSON or logs. Test wrong OTP, expired challenge/session, CSRF failure and rate limits after every auth change. Do not add auth bypasses, hardcoded codes or public CRUD endpoints.

### API change

Preserve the envelope and the existing form contract: `POST /api.php/form_container/submit` with `id` and `form_<id>[ed-f-N]`. `id=contact` is handled natively by `backend/contact.php` (validation, honeypot, rate limit, `contact_messages`); other IDs keep the Sitejet metadata/contract from `https://api.sitehub.io`. The token endpoint is `GET /api.php/websites` via `X-Api-Token` or `Authorization: Bearer`. Keep PHP 7.1 syntax in `api.php` and `backend/*.php`.

### Adding a project

- **Known/static project:** add the slug and all three translations to `content/locales.js` (`projectDetails`) and `database/seed.sql`, add the URL to `sitemap.xml`, then rebuild. The route is generated by `generateStaticParams`.
- **Admin-only project:** create it in `/admin/`; it appears in the listing after the API fetch and uses `/projects/view/?slug=...` through Apache. The slug `view` is reserved. `sitemap.xml` is regenerated automatically (or via the admin button); the static detail HTML and crawler-visible copy still need a rebuild.

### Documentation change

When architecture, routes, schema, deploy steps or security behavior change, update all three files together: `AGENTS.md` (canonical rules), `tz.md` (current state/backlog) and `tz_master.md` (long-term plan/contracts).

## Verification checklist

1. `npm run check`
2. `npm run build`; confirm `out/` has 3 homes, 3 listings, 9 known details, 3 generic `view` routes and the 3 admin pages.
3. Preview with DB env: `DB_SOCKET=... DB_NAME=... DB_USER=... DB_PASS=... php -S 127.0.0.1:8000 -t out`.
4. `/uz/`, `/en/`, `/ru/` + one known detail + one generic detail; no unintended 404, CORS or hydration errors.
5. API: profile/projects/detail for all locales, invalid `lang`, missing slug (404), DB unavailable (503).
6. Contact form: network request to `/api.php/form_container/submit` succeeds; row appears in `contact_messages`; honeypot is rejected; the 6th request from the same IP within 10 minutes returns 429.
7. Admin: `/admin/` config `enabled`; Telegram callback → challenge cookie → email code (`log` locally) → session; wrong OTP, expired challenge/session, missing CSRF and admin CORS checks; inbox count/list/read/delete; admin add/edit/delete with self/last-admin guards; sitemap button returns `{updated:true,urls:N}`.
8. Apache: known detail URL, admin-created slug fallback, denied `backend/`/`database/`/`config.local.php`, `/de/` and `/nope` → real 404, `/api.php/health` → 200.
9. `git diff --check`, `git diff --stat`, and review the complete diff before committing.

## Acceptance criteria

- Targeted page or feature works locally in all three locales.
- `npm run check` and `npm run build` pass.
- Content API returns correct data or a clean `db_unavailable` 503; the frontend falls back without breaking.
- No unintended 404s, CORS errors, hydration warnings or JS console errors. A 404 from a deleted/inactive project and a 503 from a missing DB are expected and handled.
- Admin flow: Telegram callback → challenge cookie → code verify → session → profile/project save → inbox/read/delete → admin add/edit/delete → sitemap → logout; admin responses have no wildcard CORS and use HttpOnly/Secure/SameSite cookies.
- No locale, route or SEO regression; change follows the existing structure and styles.
- Diff is limited to the files needed for the task; `config.local.php`, `github_keys.txt`, `.env` and `out/` are not committed.

## Next actions

1. Commit/push the inbox, sitemap, admin-user, native contact and 404 work.
2. Fill the cPanel DB credentials in `config.local.php`, import schema/seed (or run both migrations on an existing DB), verify email delivery and BotFather `/setdomain`, deploy `out/`, and test `/admin/` on HTTPS.
3. Decide the remaining owner questions: `en/*` legacy pages and media upload.
4. Re-run `npm run check && npm run build` after any final edit.
