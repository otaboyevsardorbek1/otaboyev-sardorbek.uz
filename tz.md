# Task brief — current state, backlog and acceptance

> **Source of truth for architecture and rules:** [AGENTS.md](AGENTS.md).
> **Long-term plan and hosting notes:** [tz_master.md](tz_master.md).
> This file is the current operational brief: what is true today, what is blocked, what comes next.

## Snapshot — 2026-10-21

- Frontend: Next.js App Router at the repo root, static export to `out/`; home, listing and 9 project detail pages are React.
- Detail pages: build-time fallback from `content/locales.js`, then refresh from `/api.php/projects/{slug}?lang=...`; title, meta description and canonical are updated client-side.
- Admin-created projects: `.htaccess` rewrites unknown `/{locale}/projects/<slug>/` URLs to `/{locale}/projects/view/?slug=...`; the client fetches the API and updates canonical.
- Backend: PHP `api.php` + `backend/*.php`; MySQL content API, contact form, Sitejet proxy and protected `/admin/` CRUD.
- Auth: Telegram Login Widget → email OTP → session with CSRF; admin routes are noindex and have no wildcard CORS. The bot, `SITE_URL`, admin Telegram ID and `APP_SECRET` are set in gitignored `config.local.php`; DB and email delivery still need production verification.
- DB: `database/schema.sql` + `database/seed.sql` seed 1 profile, 3 projects and 9 translations; existing databases need `database/migrate-project-sections.sql`. Re-importing `seed.sql` resets content and overwrites admin edits.
- SEO: `sitemap.xml` has the 15 real URLs; canonical/hreflang exists on 3 homes, 3 listings and 9 detail pages.
- Last verified: `npm run check` and `npm run build` pass; API returns 200/404/503 as expected; admin CRUD and headless UI work; Apache serves the generic detail fallback.

### Audit conclusions

The implemented architecture is coherent: static Next pages, a PHP-only runtime backend, a DB-backed content API and a working two-step admin login. No blocking defect was found in the audited page/API/auth paths. The main risks are operational, not architectural:

- the Telegram bot/domain/admin ID and app secret are configured locally, but cPanel DB credentials and email DNS/verification are still missing;
- the migration and documentation are committed and pushed to `origin/main`;
- admin-created slugs are outside the sitemap and the static detail fallback is not crawler-fresh;
- contact messages have no storage/inbox yet.

The backlog below turns those findings into ordered work.

### Repository state (do not lose this context)

The detail-page migration and documentation rework were committed and pushed to `origin/main` on 2026-10-21 with `feat: migrate portfolio to Next.js with DB-backed content and admin` and `docs: align architecture, task brief and security guides`. Only unrelated files (`CodeAnalyzer_v2.py` and the empty `README`) remain untracked on purpose. Review `git status` and `git diff` before the next commit.

## Active work

None. Migration and documentation are committed and pushed. The next action is production configuration.

## Baseline completed

- CV downloads: PDF primary + TXT fallback, per locale.
- `sitemap.xml` with 15 real URLs; canonical + hreflang on homes, listings and details.
- Next.js homepage: static export, theme toggle, mobile nav, reveal, tilt, project filter, GitHub fetch, contact form, content API fallback.
- React listing pages at `/{uz,en,ru}/projects/` with theme persistence, mobile nav, language switcher and project cards.
- MySQL schema/seed and content API tested for uz/en/ru, invalid `lang`, missing slug and DB 503.
- Contact form posts to the public endpoint without sending an unused API token.
- GitHub Actions workflow runs `npm ci`, PHP/JS lint, static build and export-file checks on push/PR.
- Project detail migration: React routes, `ProjectDetail.jsx`, API refresh, `results`/`stack_notes`, admin fields, seed/migration SQL and Apache fallback for new slugs.
- Admin panel: Telegram HMAC + email OTP, hashed one-time codes, HttpOnly/Secure/SameSite cookies, CSRF, rate limits, audit log and 3-language profile/project CRUD.
- Apache checks: admin PATH_INFO is not caught by the fallback, `backend/`/`database/`/`config.local.php` are denied, and known + admin-created detail URLs return 200.

## Backlog

| Prio | Problem | Evidence / where | Next action |
|---|---|---|---|
| P1 | cPanel DB credentials and email delivery are not configured | `config.local.php` / env; `backend/config.php` | Create the cPanel DB; import schema/seed for a fresh DB or run the migration for an existing one; fill the DB credentials in gitignored `config.local.php`; verify the `mail`/SMTP path and BotFather `/setdomain`; test `/admin/` on HTTPS |
| P2 | Contact messages are not stored in MySQL and there is no admin inbox | `api.php` form handler | Add `contact_messages` and a read-only admin inbox without breaking the Sitejet form contract; owner decision required |
| P2 | `en/*` legacy Sitejet pages have no uz/ru counterparts | `en/portfolio`, `en/tech-stack`, … | Owner decision: keep and translate, or remove from the repo/sitemap |
| P2 | Only the bootstrap admin exists; extra admins need a manual DB insert | `admin_users` | Decide whether to add a small admin-management UI |
| P3 | Admin-created slugs are not added to `sitemap.xml` automatically | `sitemap.xml`, admin projects | Add a sitemap generator or a rebuild/publish step when URLs change |
| P3 | Detail fallback is build-time HTML; crawlers may see stale copy after admin edits | `components/ProjectDetail.jsx`, `[slug]/page.js` | Accept for now or add a production rebuild/publish hook |
| P3 | Generic detail route serves listing metadata/canonical until JS runs | `app/(site)/[locale]/projects/view/page.js` | Add static/generic metadata strategy or accept the JS-only correction |
| P3 | Unknown URLs go to `/index.html` instead of a real 404 | `.htaccess` | Accept the SPA-like fallback or add a real 404 page |
| P3 | `database/migrate-project-sections.sql` is a one-time, non-idempotent migration | `database/migrate-project-sections.sql` | Add an idempotent migration runner if the schema changes again |
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

Preserve the envelope and the existing form contract: `POST /api.php/form_container/submit` with `id` and `form_<id>[ed-f-N]`. Form metadata comes from `https://api.sitehub.io`, not this repo. The token endpoint is `GET /api.php/websites` via `X-Api-Token` or `Authorization: Bearer`. Keep PHP 7.1 syntax in `api.php` and `backend/*.php`.

### Adding a project

- **Known/static project:** add the slug and all three translations to `content/locales.js` (`projectDetails`) and `database/seed.sql`, add the URL to `sitemap.xml`, then rebuild. The route is generated by `generateStaticParams`.
- **Admin-only project:** create it in `/admin/`; it appears in the listing after the API fetch and uses `/projects/view/?slug=...` through Apache. The slug `view` is reserved. Add it to the sitemap manually if SEO matters.

### Documentation change

When architecture, routes, schema, deploy steps or security behavior change, update all three files together: `AGENTS.md` (canonical rules), `tz.md` (current state/backlog) and `tz_master.md` (long-term plan/contracts).

## Verification checklist

1. `npm run check`
2. `npm run build`; confirm `out/` has 3 homes, 3 listings, 9 known details, 3 generic `view` routes and the 3 admin pages.
3. Preview with DB env: `DB_SOCKET=... DB_NAME=... DB_USER=... DB_PASS=... php -S 127.0.0.1:8000 -t out`.
4. `/uz/`, `/en/`, `/ru/` + one known detail + one generic detail; no unintended 404, CORS or hydration errors.
5. API: profile/projects/detail for all locales, invalid `lang`, missing slug (404), DB unavailable (503).
6. Contact form: network request to `/api.php/form_container/submit` succeeds.
7. Admin: `/admin/` config `enabled`; Telegram callback → challenge cookie → email code (`log` locally) → session; wrong OTP, expired challenge/session, missing CSRF and admin CORS checks.
8. Apache: known detail URL, admin-created slug fallback, denied `backend/`/`database/`/`config.local.php`, admin PATH_INFO not swallowed by the catch-all.
9. `git diff --check`, `git diff --stat`, and review the complete diff before committing.

## Acceptance criteria

- Targeted page or feature works locally in all three locales.
- `npm run check` and `npm run build` pass.
- Content API returns correct data or a clean `db_unavailable` 503; the frontend falls back without breaking.
- No unintended 404s, CORS errors, hydration warnings or JS console errors. A 404 from a deleted/inactive project and a 503 from a missing DB are expected and handled.
- Admin flow: Telegram callback → challenge cookie → code verify → session → profile/project save → logout; admin responses have no wildcard CORS and use HttpOnly/Secure/SameSite cookies.
- No locale, route or SEO regression; change follows the existing structure and styles.
- Diff is limited to the files needed for the task.

## Next actions

1. Fill the cPanel DB credentials in `config.local.php`, import schema/seed (or run the migration on an existing DB), verify email delivery and BotFather `/setdomain`, deploy `out/`, and test `/admin/` on HTTPS.
2. Decide the P2 owner questions: contact inbox, extra admins and the `en/*` legacy pages.
3. Re-run `npm run check && npm run build` after any final edit.
