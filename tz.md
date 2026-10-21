# Task brief — current state, backlog and acceptance

> **Source of truth for architecture and rules:** [AGENTS.md](AGENTS.md).
> **Long-term plan and hosting notes:** [tz_master.md](tz_master.md).
> This file is the current operational brief: what is true today, what is blocked, what comes next.

## Snapshot — 2026-10-22

- Frontend: Next.js App Router at the repo root, static export to `out/`; home, listing and 9 project detail pages are React.
- Detail pages: build-time fallback from `content/locales.js`, then refresh from `/api.php/projects/{slug}?lang=...`; title, meta description and canonical are updated client-side.
- Admin-created projects: `.htaccess` rewrites unknown `/{locale}/projects/<slug>/` URLs to `/{locale}/projects/view/?slug=...`; the client fetches the API and updates canonical.
- Backend: PHP `api.php` + `backend/*.php`; MySQL content API, native contact form with admin inbox, Sitejet proxy and protected `/admin/` CRUD.
- Auth: Telegram Login Widget → email OTP → session with CSRF; admin routes are noindex and have no wildcard CORS. Production `config.local.php`, cPanel DB, mailboxes, bot, `SITE_URL`, admin Telegram ID and `APP_SECRET` are configured and smoke-tested.
- Bot: private `backend/bot.php` webhook bridge with a secret header and numeric-admin allowlist; `/help`, `/inbox`, `/read` and `/send` work against cPanel IMAP/`mail()`. Real inbound Telegram message from the admin still needs a manual test.
- DB: `host9590_portfolio` with `SELECT, INSERT, UPDATE, DELETE` only; schema/seed applied. `database/schema.sql` + `database/seed.sql` seed 1 profile, 3 projects, 9 translations and an empty contact inbox; re-importing `seed.sql` resets admin edits.
- Email: cPanel `admin@` (OTP/inbox), `no-reply@` (send) and `contact@` → `admin@` forwarder work. SPF passes; external DKIM is absent, Exim does not sign, and DMARC is still `p=none`; provider/WHM action is required.
- SEO: `sitemap.xml` starts with 15 real URLs and is regenerated after every admin project create/update/delete (or from the admin button); canonical/hreflang exists on 3 homes, 3 listings and 9 detail pages.
- Routing/security: unknown URLs return a real HTTP 404 with `out/404.html`; CSP/HSTS/frame/referrer/COOP/CORP headers are live; `/config.local.php`, `/backend/*` and `/database/*` return 403.
- Last verified: `npm run check`, build, live API 200/401/404, admin OTP/CSRF/inbox/delete, contact insert/honeypot/rate logic, bot webhook security and fake `/help`/`/inbox`/`/read`/`/send`, Apache 404, and headless browser CSP/hydration checks on uz/en/ru/listing/detail/admin/dashboard.

### Audit conclusions

The implemented architecture is coherent: static Next pages, a PHP-only runtime backend, a DB-backed content API, a private Telegram bot bridge and a working two-step admin login. No blocking defect was found in the audited page/API/auth/bot paths. The remaining risks are operational, not architectural:

- external DNS is managed by Eskiz, not the cPanel zone: DKIM cannot be published and DMARC remains `p=none`; Exim does not sign outbound mail;
- BotFather `/setdomain` and a real Telegram Login/update need a manual check with the owner’s account;
- the static detail fallback is still not crawler-fresh after admin edits;
- contact messages persist in MySQL and appear in the admin inbox; contact email notification is not wired (the inbox is the source of truth);
- SSH key/passphrase rotation and a final production redeploy remain.

The backlog below turns those findings into ordered work.

### Repository state (do not lose this context)

The live deployment is built from `/home/host9590/apps/otaboyev-sardorbek.uz` and published to `/home/host9590/public_html`; the last code commit before this documentation pass was `a88999d fix: harden API responses and IMAP message parsing`. Only unrelated `CodeAnalyzer_v2.py` remains untracked on purpose. Review `git status` and `git diff` before the next commit.

## Active work

Production DB/mailbox/bot/admin configuration is complete and smoke-tested. Remaining work: commit the current documentation/deploy notes, rebuild `out/` on Node 22, perform a full tar deploy while preserving `config.local.php` and `.well-known/`, then re-run live API/admin/contact/bot checks. DNS DKIM/DMARC and a real Telegram Login test are blocked on provider/owner action.

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
| P1 | DKIM is absent and DMARC is `p=none`; Exim does not sign | external DNS (`ns1/ns2.eskiz.uz`), cPanel/WHM; Port25 report | Publish a fresh `default._domainkey` TXT and set DMARC to `p=quarantine` once provider/WHM access exists; enable Exim DKIM signing; rerun Port25 |
| P1 | BotFather domain and a real Telegram admin update are not verified | BotFather; `@otaboyev_sardorbek_uz_bot`; `/admin/` widget | Run `/setdomain otaboyev-sardorbek.uz`, log in with the real Telegram account, and send one real `/help`/`/inbox` message to the bot |
| P1 | Full deploy after the latest fixes is pending | `/home/host9590/apps/.../out`, `/home/host9590/public_html` | Build with Node 22 and tar-deploy `out/` excluding `config.local.php`; preserve `.well-known/`; rerun live checks |
| P2 | SSH key passphrase was exposed during setup | local `~/.ssh/id_rsa` | Rotate the passphrase or replace the key with the owner |
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
8. Bot: `POST /api.php/bot/webhook` without/wrong secret → 403; GET → 405; fake update from the configured numeric admin ID runs `/help`, `/inbox`, `/read` and `/send`; `/send` mail lands in `admin@`; `getWebhookInfo` shows `pending_update_count=0`.
9. Apache: known detail URL, admin-created slug fallback, denied `backend/`/`database/`/`config.local.php`, `/de/` and `/nope` → real 404, `/api.php/health` → 200.
10. Headless browser: `/uz/`, `/en/`, `/ru/`, `/uz/projects/`, a known detail, `/admin/` and authenticated `/admin/dashboard/` have no console/CSP/request-failure or hydration errors.
11. `git diff --check`, `git diff --stat`, and review the complete diff before committing.

## Acceptance criteria

- Targeted page or feature works locally in all three locales.
- `npm run check` and `npm run build` pass.
- Content API returns correct data or a clean `db_unavailable` 503; the frontend falls back without breaking.
- No unintended 404s, CORS errors, hydration warnings or JS console errors. A 404 from a deleted/inactive project and a 503 from a missing DB are expected and handled.
- Admin flow: Telegram callback → challenge cookie → code verify → session → profile/project save → inbox/read/delete → admin add/edit/delete → sitemap → logout; admin responses have no wildcard CORS and use HttpOnly/Secure/SameSite cookies.
- No locale, route or SEO regression; change follows the existing structure and styles.
- Diff is limited to the files needed for the task; `config.local.php`, `github_keys.txt`, `.env` and `out/` are not committed.

## Next actions

1. Commit/push this documentation and security-state update.
2. Rebuild on the server with Node 22, tar-deploy `out/` preserving `config.local.php` and `.well-known/`, and rerun live API/admin/contact/bot checks.
3. Publish DKIM and tighten DMARC once Eskiz DNS or WHM access is available; enable Exim DKIM signing and rerun Port25.
4. Run the real Telegram Login flow and send one real admin message to the bot.
5. Rotate the SSH key passphrase and decide the remaining owner questions (`en/*` legacy pages, media upload).
