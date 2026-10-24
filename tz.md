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
- Bot: private `backend/bot.php` webhook bridge with a secret header; access follows `admin_users.is_active` (bootstrap `admin.telegram_id` only while the table is empty or missing), limits are per Telegram ID and bot actions are audited. Commands: `/help`, `/whoami`, `/messages`, `/unread`, `/message`, `/reply`, `/stats`, `/inbox`, `/read`, `/send`; the contact form pings active admins in Telegram. Real inbound Telegram message from the admin still needs a manual test.
- DB: `host9590_portfolio` with `SELECT, INSERT, UPDATE, DELETE` only; schema/seed applied. `database/schema.sql` + `database/seed.sql` seed 1 profile, 3 projects, 9 translations and an empty contact inbox; re-importing `seed.sql` resets admin edits. `admin_users.role` (`super`/`admin`) is added by the idempotent `database/migrate-admin-role.sql`; `super` manages admins, `admin` only content/inbox.
- Email: cPanel `admin@` (OTP/inbox), `no-reply@` (send) and `contact@` → `admin@` forwarder work. SPF passes; external DKIM is absent, Exim does not sign, and DMARC is still `p=none`; provider/WHM action is required.
- SEO: `sitemap.xml` starts with 15 real URLs and is regenerated after every admin project create/update/delete (or from the admin button); canonical/hreflang exists on 3 homes, 3 listings and 9 detail pages.
- Routing/security: unknown URLs return a real HTTP 404 with `out/404.html`; CSP/HSTS/frame/referrer/COOP/CORP headers are live; `/config.local.php`, `/backend/*` and `/database/*` return 403.
- Hardening (2026-10-22): wildcard CORS only on public read-only JSON (`profile`, `projects`, `health`, `status`); HSTS only over HTTPS; `config.local.php` is no longer copied into `out/`; `display_errors=0` + `log_errors`; admin `Origin`/`Referer` is compared strictly (host/scheme/port), `Origin: null` is rejected, and header-less CLI mutations require `X-Requested-With: XMLHttpRequest`; bot `update_id` idempotency and command rate limits are implemented.
- Bot guards: `bot_updates_seen` and `bot_rate_limit` are in `database/schema.sql` and the idempotent `database/migrate-bot-guards.sql`; if the tables are absent the bot still works but the guards are disabled.
- Last verified (local, 2026-10-22): `npm run check`, static build, secret-free `out/`, profile/projects/detail uz/en/ru, invalid lang, 404 slug, DB 503, contact honeypot/invalid/5-per-10-minute/10-global limits, admin challenge → OTP → session, origin/CSRF rejection, OTP lockout, inbox CRUD, self-delete/deactivate guards, sitemap update, bot secret rejection and duplicate `update_id`, migration idempotency.

### Audit conclusions

The implemented architecture is coherent: static Next pages, a PHP-only runtime backend, a DB-backed content API, a private Telegram bot bridge and a working two-step admin login. No blocking defect was found in the audited page/API/auth/bot paths. The remaining risks are operational, not architectural:

- external DNS is managed by Eskiz, not the cPanel zone: DKIM cannot be published and DMARC remains `p=none`; Exim does not sign outbound mail;
- BotFather `/setdomain` and a real Telegram Login/update need a manual check with the owner’s account;
- the static detail fallback is still not crawler-fresh after admin edits;
- contact messages persist in MySQL and appear in the admin inbox; the contact form pings active admins in Telegram, but contact email delivery is still not wired (the inbox is the source of truth);
- SSH key passphrase was rotated on 2026-10-22; the new value is stored outside the repo (mode 600);
- owner-only rotations remain: GitHub PAT in `github_keys.txt`, `APP_SECRET`, Telegram bot token, the Gmail admin OTP address, and the legacy Google Maps API keys that were redacted from the working tree but remain in git history.

The backlog below turns those findings into ordered work.

### Repository state (do not lose this context)

The live deployment is built from `/home/host9590/apps/otaboyev-sardorbek.uz` and published to `/home/host9590/public_html`; the last code commit before this documentation pass was `a88999d fix: harden API responses and IMAP message parsing`. Only unrelated `CodeAnalyzer_v2.py` remains untracked on purpose. Review `git status` and `git diff` before the next commit.

## Active work

Production DB/mailbox/bot/admin configuration is complete and smoke-tested. Remaining work: commit the current security-hardening and documentation changes, import `database/migrate-bot-guards.sql` into production, rebuild `out/` on Node 22, perform a full tar deploy while preserving `config.local.php` and `.well-known/`, then re-run live API/admin/contact/bot checks. The owner still has to revoke/rotate the leaked secrets, move OTP to `admin@`, rotate the legacy Google Maps keys, and complete DKIM/DMARC plus a real Telegram Login test.

## Baseline completed

- CV downloads: PDF primary + TXT fallback, per locale.
- `sitemap.xml` with 15 real URLs; canonical + hreflang on homes, listings and details.
- Next.js homepage: static export, theme toggle, mobile nav, reveal, tilt, project filter, GitHub fetch, contact form, content API fallback.
- React listing pages at `/{uz,en,ru}/projects/` with theme persistence, mobile nav, language switcher and project cards.
- MySQL schema/seed and content API tested for uz/en/ru, invalid `lang`, missing slug and DB 503.
- Contact form posts to the public endpoint without sending an unused API token; `id=contact` now validates natively, stores in `contact_messages`, rejects the `website` honeypot and rate-limits 5 messages per IP per 10 minutes.
- Admin inbox: unread count, list/detail, mark read/unread and delete; stored payload keeps all submitted fields and attachment metadata.
- Automatic `sitemap.xml` regeneration after project create/update/delete plus a manual “Sitemapni yangilash” button.
- Admin-user manager: list/add/edit/delete extra admins with `super`/`admin` roles, self-delete, self-deactivate, last-active-admin and last-active-super-admin protection; `/api.php/admin/admins*` is super-only.
- Real 404: `.htaccess` uses `ErrorDocument 404 /404.html`; the SPA catch-all was removed.
- GitHub Actions workflow runs `npm ci`, PHP/JS lint, static build and export-file checks on push/PR.
- Project detail migration: React routes, `ProjectDetail.jsx`, API refresh, `results`/`stack_notes`, admin fields, seed/migration SQL and Apache fallback for new slugs.
- Admin panel: Telegram HMAC + email OTP, hashed one-time codes, HttpOnly/Secure/SameSite cookies, CSRF, strict origin check, rate limits, audit log and 3-language profile/project CRUD.
- Security hardening: API CORS limited to public read-only JSON, HSTS only on HTTPS, `display_errors=0` + `log_errors`, no `config.local.php` in the build artifact, bot idempotency/rate-limit tables, and legacy Google Maps keys redacted from tracked files.
- Apache checks: admin PATH_INFO is not caught by the fallback, `backend/`/`database/`/`config.local.php` are denied, and known + admin-created detail URLs return 200.

## Backlog

| Prio | Problem | Evidence / where | Next action |
|---|---|---|---|
| P1 | DKIM is absent and DMARC is `p=none`; Exim does not sign | external DNS (`ns1/ns2.eskiz.uz`), cPanel/WHM; Port25 report | Publish a fresh `default._domainkey` TXT and set DMARC to `p=quarantine` once provider/WHM access exists; enable Exim DKIM signing; rerun Port25 |
| P1 | BotFather domain and a real Telegram admin update are not verified | BotFather; `@otaboyev_sardorbek_uz_bot`; `/admin/` widget | Run `/setdomain otaboyev-sardorbek.uz`, log in with the real Telegram account, and send one real `/help`/`/inbox` message to the bot |
| P1 | Full deploy after the latest fixes is pending | `/home/host9590/apps/.../out`, `/home/host9590/public_html` | Build with Node 22 and tar-deploy `out/` excluding `config.local.php`; preserve `.well-known/`; rerun live checks |
| P1 | Owner-only secret rotations are still pending | GitHub PAT (`github_keys.txt`), `config.local.php` (`APP_SECRET`, bot token, Gmail OTP), legacy Google Maps keys | Revoke the GitHub PAT; regenerate `APP_SECRET` and bot token; move OTP to `admin@`; rotate/restrict the Google Maps keys in Google Cloud |
| P1 | New bot guard tables must be imported in production | `database/migrate-bot-guards.sql` | Run the idempotent migration once against `host9590_portfolio`; verify `bot_updates_seen` and `bot_rate_limit` exist |
| P3 | SSH key passphrase was exposed during setup | local `~/.ssh/id_rsa` | Completed 2026-10-22: passphrase rotated; new value stored outside the repo with mode 600 |
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
2. `npm run build`; confirm `out/` has 3 homes, 3 listings, 9 known details, 3 generic `view` routes, the 3 admin pages, `.htaccess`, `api.php`, `404.html`; confirm `out/config.local.php`, `out/config.local.js` and `out/github_keys.txt` are absent.
3. Preview with DB env: `DB_SOCKET=... DB_NAME=... DB_USER=... DB_PASS=... ./run_local.sh` (or the equivalent `php -S` command).
4. `/uz/`, `/en/`, `/ru/` + one known detail + one generic detail; no unintended 404, CORS or hydration errors.
5. API: profile/projects/detail for all locales, invalid `lang`, missing slug (404), DB unavailable (503); public reads return `Access-Control-Allow-Origin: *`, form/admin/bot/token responses do not; HTTP has no HSTS.
6. Contact form: network request to `/api.php/form_container/submit` succeeds; row appears in `contact_messages`; honeypot → 400, invalid email → 422, the 6th request from the same IP within 10 minutes returns 429, and the 10-row global IP guard returns 429.
7. Admin: `/admin/` config `enabled`; Telegram callback → challenge cookie → email code (`log` locally) → session; wrong OTP and 6th-attempt lockout, expired challenge/session, missing CSRF, `Origin: null`, evil origin and header-less mutation → 403; `X-Requested-With` fallback passes origin; inbox count/list/read/delete; admin add/edit/delete with self/last-admin guards; sitemap button returns `{updated:true,urls:N}`.
8. Bot: `POST /api.php/bot/webhook` without/wrong secret → 403; GET → 405; fake update from the configured numeric admin ID runs `/help`, `/inbox`, `/read` and `/send`; `/send` mail lands in `admin@`; duplicate `update_id` is processed once; `bot_rate_limit_ok('send', 10)` returns false on the 11th call; `getWebhookInfo` shows `pending_update_count=0`.
9. Import `database/migrate-bot-guards.sql` twice; the second run must succeed and both `bot_updates_seen` and `bot_rate_limit` must exist.
10. Apache: known detail URL, admin-created slug fallback, denied `backend/`/`database/`/`config.local.php`, `/de/` and `/nope` → real 404, `/api.php/health` → 200.
11. Headless browser: `/uz/`, `/en/`, `/ru/`, `/uz/projects/`, a known detail, `/admin/` and authenticated `/admin/dashboard/` have no console/CSP/request-failure or hydration errors.
12. `git diff --check`, `git diff --stat`, and review the complete diff before committing.

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
5. Decide the remaining owner questions (`en/*` legacy pages, media upload).


# Tahlil

JSON strukturangizni ko‘rib chiqdim. Hozirgi holat:

- **Web admin**: `backend/admin.php` — `admin_users` jadvali orqali ko‘p admin, `is_active` bilan boshqariladi, session `is_active=1` bo‘lmasa avtomatik bekor bo‘ladi ✅
- **Bot**: `backend/bot.php` — **faqat bitta** `config['telegram_id']` ga bog‘langan, `admin_users` jadvalini **umuman tekshirmaydi** ❌
- Botdagi `/inbox` va `/read` — bu **IMAP email** (admin@ mailbox), web inbox (`contact_messages`) emas

Ya’ni siz xohlagan “adminlar botdan ham foydalanadi, adminlik olinsa bot ishlamaydi” logikasi **hozir yo‘q**.

---

# 1. Asosiy tavsiya — Botni multi-admin qilish

## 1.1. `backend/bot.php` da o‘zgartirish

**Hozir:**
```php
if ($chatId === '' || $fromId === '' || $chatId !== $config['admin_id'] || $fromId !== $config['admin_id']) {
    return;
}
```

**Bo‘lishi kerak:** `admin_users` dan tekshirish (cache bilan):

```php
function bot_find_admin($telegramId)
{
    static $cache = array();
    $telegramId = (string) $telegramId;
    if (array_key_exists($telegramId, $cache)) {
        return $cache[$telegramId];
    }

    $pdo = bot_db();
    if (!$pdo) {
        // DB yo‘q — config’dagi bootstrap ID bilan ishlaymiz
        $config = bot_config();
        $cache[$telegramId] = ($telegramId === $config['admin_id'])
            ? array('id' => 0, 'telegram_id' => $telegramId, 'is_active' => 1)
            : null;
        return $cache[$telegramId];
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT id, telegram_id, email, display_name, telegram_username, is_active
             FROM admin_users WHERE telegram_id = :tg LIMIT 1'
        );
        $stmt->execute(array(':tg' => $telegramId));
        $row = $stmt->fetch();
        $cache[$telegramId] = ($row && (int) $row['is_active'] === 1) ? $row : null;
        return $cache[$telegramId];
    } catch (Exception $e) {
        error_log('bot admin lookup failed: ' . $e->getMessage());
        return null;
    }
}
```

`bot_handle_update()` da:

```php
$chatId = isset($chat['id']) ? (string) $chat['id'] : '';
$fromId = isset($from['id']) ? (string) $from['id'] : '';

if ($chatId === '' || $fromId === '') return;
if (isset($chat['type']) && $chat['type'] !== 'private') return;

$admin = bot_find_admin($fromId);
if (!$admin) {
    // xohishingizga ko‘ra: jim ignore YOKI umumiy javob
    // bot_send_message($chatId, 'Bu akkaunt admin sifatida faol emas.');
    return;
}
```

> ✅ Natijada: web admin panelda `is_active=0` qilinsa yoki o‘chirilsa — bot **darhol** ishlamaydi. DB yo‘q bo‘lsa eski bootstrap `telegram_id` fallback ishlaydi.

## 1.2. Rate limit’ni per-admin qilish

`bot_rate_limit_ok()` hozir `config['admin_id']` ni ishlatadi. Imzoni o‘zgartiring:

```php
// eski: function bot_rate_limit_ok($command, $maxPerMinute)
function bot_rate_limit_ok($adminId, $command, $maxPerMinute)
{
    $adminId = (int) $adminId;
    if ($adminId < 1) return true; // bootstrap holat
    // ... qolgani o‘sha-o‘sha, faqat :admin_id sifatida shu $adminId
}
```

Chaqirishda: `bot_rate_limit_ok((int) $admin['id'], 'inbox', 10)`.

## 1.3. `/whoami` komandasi

Botga qo‘shing:
```php
if ($text === '/whoami' || strpos($text, '/whoami@') === 0) {
    bot_send_message($chatId, 'Admin: ' . ($admin['display_name'] ?: $admin['telegram_id'])
        . "\nEmail: " . $admin['email']);
    return;
}
```

---

# 2. Botga web inbox (`contact_messages`) qo‘shish

Siz “habarlar va messglarga ega bo‘lishi kerak” degansiz — bu IMAP emas, **contact form xabarlari** bo‘lishi mumkin. Yangi komandalar:

| Komanda | Vazifa |
|---|---|
| `/messages` | oxirgi 10 ta `contact_messages` (unread birinchi) |
| `/message <id>` | to‘liq xabar, avtomatik `is_read=1` |
| `/unread` | faqat `is_read=0` |
| `/reply <id> \| matn` | foydalanuvchiga email javob (`admin_mail_send_smtp` yoki `mail()`) |
| `/stats` | `unread`, `total`, `active_admins` soni |

Kod skeleti:

```php
if (preg_match('#^/messages(?:@\w+)?$#', $text)) {
    if (!bot_rate_limit_ok((int) $admin['id'], 'messages', 20)) {
        bot_send_message($chatId, 'Juda ko‘p so‘rov.');
        return;
    }
    $pdo = bot_db();
    if (!$pdo) { bot_send_message($chatId, 'DB mavjud emas.'); return; }
    $rows = $pdo->query(
        'SELECT id, name, email, subject, is_read, created_at
         FROM contact_messages ORDER BY is_read ASC, created_at DESC LIMIT 10'
    )->fetchAll();
    if (!$rows) { bot_send_message($chatId, 'Xabarlar yo‘q.'); return; }
    $lines = array('Contact inbox:');
    foreach ($rows as $r) {
        $lines[] = ($r['is_read'] ? '  ' : '● ') . '#' . $r['id'] . ' — '
            . ($r['subject'] ?: '(mavzusiz)') . "\n   "
            . ($r['name'] ?: $r['email']) . ' · ' . $r['created_at'];
    }
    bot_send_message($chatId, implode("\n\n", $lines));
    return;
}
```

`/reply`:
```php
if (preg_match('#^/reply(?:@\w+)?\s+(\d+)\s*\|(.+)$#s', $text, $m)) {
    // ... message id = (int)$m[1], matn = trim($m[2])
    // emailni bazadan olib admin_mail_send_smtp orqali yuborish
    // audit logga yozish
}
```

---

# 3. Contact formaga kelganda adminlarga Telegram xabar

`backend/contact.php` → `contact_native_submit()` oxirida:

```php
$id = contact_store_message($formConfig, 'contact', $data, array());
if ($id) {
    require_once __DIR__ . '/bot.php';       // yoki alohida notifier fayl
    bot_notify_admins(
        "📩 Yangi xabar #$id\n"
        . "Kimdan: $name <$email>\n"
        . "Mavzu: $subject\n\n"
        . mb_substr($message, 0, 500, 'UTF-8')
    );
}
```

`backend/bot.php` da:

```php
function bot_notify_admins($text)
{
    $pdo = bot_db();
    if (!$pdo) return false;
    try {
        $rows = $pdo->query('SELECT telegram_id FROM admin_users WHERE is_active = 1')->fetchAll();
        foreach ($rows as $r) {
            bot_send_message((string) $r['telegram_id'], $text);
        }
        return true;
    } catch (Exception $e) {
        error_log('bot notify failed: ' . $e->getMessage());
        return false;
    }
}
```

> ⚠️ Muhim: bu **contact formaning muvaffaqiyatini bloklamasligi** kerak — `try/catch` bilan yumshoq.

---

# 4. Admin role/ruxsat darajasi (ixtiyoriy, lekin tavsiya)

Hozir barcha adminlar teng — bu xavfli (yangi admin boshqa adminni o‘chira oladi). Taklif:

```sql
ALTER TABLE admin_users
  ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'admin' AFTER is_active,
  ADD KEY idx_admin_role (role);
-- 'super' — boshqa adminlarni boshqara oladi
-- 'admin' — faqat content + o‘z xabarlari
-- 'viewer' — faqat o‘qish (sizda hozir kerak emas, lekin kelajakda)
```

`backend/admin.php` da:
- `admin_handle_admins()` va `admin_handle_admin_item()` — faqat `role='super'` uchun
- Self-delete/self-deactivate va last-super-admin himoyalari allaqachon bor, lekin endi **last super admin** ni qo‘shing

`components/admin/AdminManager.jsx` da `role` select qo‘shiladi.

---

# 5. Botdagi qo‘shimcha yaxshilanishlar

## 5.1. `setMyCommands` — yangilangan ro‘yxat
```
/start, /help, /whoami, /messages, /message <id>, /reply <id> | matn,
/inbox (email), /read <uid>, /send <email> | mavzu | matn, /stats
```

## 5.2. Bot audit log
`admin_audit_log` dan foydalaning (`entity='bot'`):
```php
function bot_audit($pdo, $adminId, $action, $details = null)
{
    if (!$pdo || !$adminId) return;
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO admin_audit_log (admin_id, action, entity, ip_hash, details)
             VALUES (:aid, :a, :e, NULL, :d)'
        );
        $stmt->execute(array(
            ':aid' => $adminId, ':a' => $action, ':e' => 'bot',
            ':d'   => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
        ));
    } catch (Exception $e) { /* silent */ }
}
```

## 5.3. Webhook uchun health endpoint
`api.php` da `/bot/health` (token yo‘q, lekin **GET** va config bor-yo‘qligini qaytaradi) — monitoring uchun.

## 5.4. `pending_update_count` monitoring
`alert_admin()` funksiyasi (SECURITY.md da tavsiya qilingan) — bot API fail bo‘lsa, admin’ga xabar.

## 5.5. `/send` uchun Reply-To
Hozir `bot_send_email()` `From: no-reply@` ishlatadi. Javob kelganda `admin@` ga tushishi uchun `Reply-To: admin@otaboyev-sardorbek.uz` qo‘shing.

## 5.6. `/stats`
```php
$pdo->query('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0')->fetchColumn();
$pdo->query('SELECT COUNT(*) FROM admin_users WHERE is_active = 1')->fetchColumn();
```

---

# 6. Web admin panelda tegishli o‘zgarishlar

`components/admin/AdminManager.jsx`:
- `role` ustuni va select
- `is_active` toggle’i **darhol** botga ta’sir qilishini izohda yozing (“Web’da o‘chirilsa bot ham ishlamaydi”)
- O‘chirishda confirmationda: “Bu admin botdan ham foydalana olmaydi”

`backend/admin.php` — `admin_handle_admin_item()` DELETE/PUT da:
```php
if (!$admin['is_active'] && $current['is_active'] && admin_active_count($pdo, $id) === 0) {
    admin_fail('Kamida bitta faol admin qolishi kerak', 'last_admin', 422);
}
```
(bu allaqachon bor — saqlang).

> Session’lar `admin_lookup_session` da `a.is_active = 1` tekshirilgani uchun **avtomatik** bekor bo‘ladi ✅ — bu juda yaxshi, o‘zgartirmang.

---

# 7. Migratsiya (idempotent qiling)

`database/migrate-bot-multi-admin.sql`:

```sql
-- Bot multi-admin + role
ALTER TABLE admin_users
  ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'admin' AFTER is_active;

-- Bootstrap super admin (ixtiyoriy, birinchi marta)
-- UPDATE admin_users SET role='super' WHERE telegram_id = '<SIZNING_ID>';
```

MySQL 5.7 da `ADD COLUMN IF NOT EXISTS` yo‘q — shuning uchun stored procedure bilan yoki bir marta qo‘lda ishga tushiring va `AGENTS.md` ga yozib qo‘ying.

---

# 8. Xavfsizlik eslatmalari

1. **Bot token va webhook secret hech qachon** Telegram xabariga chiqmasin (hozir yaxshi ✅).
2. **Ruxsatsiz userga javob bermang** — `return` qiling (xabar yubormang), aks holda botning mavjudligini leak qiladi.
3. **`/reply` da emailni qayta validatsiya qiling** — `filter_var(..., FILTER_VALIDATE_EMAIL)`.
4. **Xabar matnini `bot_clean_body()` bilan tozalang** va `mb_substr` bilan kesing.
5. **`chat.id === from.id`** tekshiruvi qolsin — group chatdan foydalanishni bloklaydi (hozir `chat['type'] !== 'private'` bor, uni saqlang).
6. **DB yo‘q bo‘lsa bot jim ishlamasin** — `bot_db()` `null` qaytarsa rate limit `true` qaytaradi; bu ochiq eshik. Log yozib, `bot_notify_admins()` orqali xabar yuborish kerak.

---

# 9. Prioritet bo‘yicha tartib

| # | Ish | Effort | Ta’sir |
|---|---|---|---|
| 1 | `bot_find_admin()` + `bot_handle_update` ni DB’ga o‘tkazish | Kichik | 🔴 Kritik |
| 2 | Rate limit’ni per-admin qilish | Kichik | 🔴 Kritik |
| 3 | `/messages`, `/message <id>`, `/unread` | O‘rta | 🟠 Yuqori |
| 4 | Contact formada `bot_notify_admins()` | Kichik | 🟠 Yuqori |
| 5 | `/whoami`, `/stats`, `/help` yangilash | Kichik | 🟡 Foydali |
| 6 | `role` ustuni + super admin guard | O‘rta | 🟡 Xavfsizlik |
| 7 | Bot audit log | Kichik | 🟡 Xavfsizlik |
| 8 | `/reply` + `Reply-To` header | O‘rta | 🟡 Foydali |
| 9 | `setMyCommands` skript | Kichik | 🟢 Qulaylik |

---

# `backend/bot.php` — to‘liq diff

Quyida `backend/bot.php` uchun kerakli o‘zgarishlar. Har bir funksiya alohida — `git apply` yoki qo‘lda ko‘chirish uchun qulay.

> **Eslatma:** diff PHP 7.1 sintaksisiga mos. `bot.php` faqat `bot_handle_webhook()` orqali ishga tushadi, shuning uchun funksiyalar tartibi muhim emas, lekin `bot_find_admin`, `bot_audit`, `bot_web_*` larni qo‘shish joyini izohda ko‘rsatdim.

---

## 1. `bot_config()` — o‘zgartirmang, lekin yangi `'bootstrap_id'` kerak

`admin_config()` dan `telegram_id` ni bootstrap fallback uchun olamiz. `bot_config()` da o‘zgartirish yo‘q.

---

## 2. YANGI: `bot_find_admin()` — `bot_db()` dan keyin qo‘shing

```php
// +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// + NEW: bot_find_admin() — admin_users jadvalidan faol adminni izlaydi.
// + Bootstrap fallback: config'dagi telegram_id, agar DB yoki jadval bo'lmasa.
// +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
function bot_find_admin($telegramId)
{
    static $cache = array();
    $telegramId = (string) $telegramId;
    if ($telegramId === '') {
        return null;
    }
    if (array_key_exists($telegramId, $cache)) {
        return $cache[$telegramId];
    }

    $config = bot_config();
    $pdo = bot_db();

    if (!$pdo) {
        // DB yo‘q — faqat bootstrap ID ishlaydi.
        $cache[$telegramId] = ($telegramId === $config['admin_id'])
            ? array('id' => 0, 'telegram_id' => $telegramId, 'email' => '', 'display_name' => null, 'is_active' => 1)
            : null;
        return $cache[$telegramId];
    }

    try {
        $statement = $pdo->prepare(
            'SELECT id, telegram_id, email, display_name, telegram_username, is_active
             FROM admin_users WHERE telegram_id = :tg LIMIT 1'
        );
        $statement->execute(array(':tg' => $telegramId));
        $row = $statement->fetch();

        if ($row && (int) $row['is_active'] === 1) {
            $cache[$telegramId] = $row;
        } else {
            // Adminlar jadvali mavjud, lekin bu ID faol emas.
            // Bootstrap fallback faqat jadval bo'sh bo'lsa ishlaydi.
            $count = (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
            if ($count === 0 && $telegramId === $config['admin_id']) {
                $cache[$telegramId] = array(
                    'id' => 0,
                    'telegram_id' => $telegramId,
                    'email' => $config['from'],
                    'display_name' => null,
                    'is_active' => 1,
                );
            } else {
                $cache[$telegramId] = null;
            }
        }
        return $cache[$telegramId];
    } catch (Exception $error) {
        error_log('bot admin lookup failed: ' . $error->getMessage());
        // Jadval hali yaratilmagan — bootstrap rejim.
        if ($telegramId === $config['admin_id']) {
            $cache[$telegramId] = array(
                'id' => 0,
                'telegram_id' => $telegramId,
                'email' => $config['from'],
                'display_name' => null,
                'is_active' => 1,
            );
            return $cache[$telegramId];
        }
        $cache[$telegramId] = null;
        return null;
    }
}
```

---

## 3. YANGI: `bot_audit()` — `bot_find_admin()` dan keyin qo‘shing

```php
// +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// + NEW: bot_audit() — admin_audit_log ga yozadi. Admin row bo'lmasa
// + (bootstrap) admin_id = NULL qilib qo'yiladi (FK SET NULL bilan mos).
// +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
function bot_audit($admin, $action, $details = null)
{
    $pdo = bot_db();
    if (!$pdo || !is_array($admin)) {
        return;
    }
    $adminId = isset($admin['id']) ? (int) $admin['id'] : 0;
    try {
        $statement = $pdo->prepare(
            'INSERT INTO admin_audit_log (admin_id, action, entity, ip_hash, details)
             VALUES (:aid, :a, :e, NULL, :d)'
        );
        $statement->execute(array(
            ':aid' => $adminId > 0 ? $adminId : null,
            ':a' => (string) $action,
            ':e' => 'bot',
            ':d' => $details === null ? null
                : json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ));
    } catch (Exception $error) {
        error_log('bot audit failed: ' . $error->getMessage());
    }
}
```

---

## 4. `bot_rate_limit_ok()` — ALMASHTIRING

```diff
-function bot_rate_limit_ok($command, $maxPerMinute)
+function bot_rate_limit_ok($telegramId, $command, $maxPerMinute)
 {
-    $config = bot_config();
-    $adminId = (int) $config['admin_id'];
-    if ($adminId < 1) {
-        return false;
-    }
+    $telegramId = (int) $telegramId;
+    if ($telegramId < 1) {
+        // Bootstrap config always has a numeric telegram_id, so this
+        // is only hit for malformed calls. Fail open.
+        return true;
+    }
     $pdo = bot_db();
     if (!$pdo) {
         return true;
     }

     try {
         $statement = $pdo->prepare(
             'SELECT COUNT(*) FROM bot_rate_limit
              WHERE admin_id = :admin_id AND command = :command
                AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE)'
         );
-        $statement->execute(array(':admin_id' => $adminId, ':command' => (string) $command));
+        $statement->execute(array(':admin_id' => $telegramId, ':command' => (string) $command));
         if ((int) $statement->fetchColumn() >= (int) $maxPerMinute) {
             return false;
         }

         $insert = $pdo->prepare('INSERT INTO bot_rate_limit (admin_id, command, created_at) VALUES (:admin_id, :command, UTC_TIMESTAMP())');
-        $insert->execute(array(':admin_id' => $adminId, ':command' => (string) $command));
+        $insert->execute(array(':admin_id' => $telegramId, ':command' => (string) $command));

         if (random_int(1, 50) === 1) {
             $pdo->exec('DELETE FROM bot_rate_limit WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)');
             $pdo->exec('DELETE FROM bot_updates_seen WHERE seen_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)');
         }

         return true;
     } catch (Exception $error) {
         error_log('bot rate limit unavailable: ' . $error->getMessage());
         return true;
     }
 }
```

> **Kalit**: `bot_rate_limit.admin_id` — bu **telegram_id** (BIGINT UNSIGNED, shu uchun ishlaydi). `admin_users.id` emas — chunki bootstrap rejimida DB row yo‘q, lekin limit kerak.

---

## 5. YANGI: `bot_notify_admins()` — `bot_send_message()` dan keyin qo‘shing

```php
// +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// + NEW: bot_notify_admins() — barcha faol adminlarga xabar yuboradi.
// + Contact formada, admin CRUD eventlarda chaqiriladi. Best-effort:
// + bir admin fail bo'lsa boshqalarga davom etadi.
// +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
function bot_notify_admins($text)
{
    $pdo = bot_db();
    if (!$pdo) {
        return 0;
    }
    $sent = 0;
    try {
        $rows = $pdo->query(
            'SELECT telegram_id FROM admin_users WHERE is_active = 1'
        )->fetchAll();
        foreach ($rows as $row) {
            $tgId = isset($row['telegram_id']) ? (string) $row['telegram_id'] : '';
            if ($tgId !== '' && bot_send_message($tgId, $text)) {
                $sent++;
            }
        }
    } catch (Exception $error) {
        error_log('bot notify admins failed: ' . $error->getMessage());
    }
    return $sent;
}
```

---

## 6. `bot_send_email()` — `Reply-To` qo‘shish (ixtiyoriy 4-parametr)

```diff
-function bot_send_email($to, $subject, $body)
+function bot_send_email($to, $subject, $body, $replyTo = '')
 {
     $config = bot_config();
     $to = bot_clean_header($to, 320);
     $subject = bot_clean_header($subject, 200);
     $body = bot_clean_body($body, 10000);
     $from = bot_clean_header($config['from'], 320);
     $fromName = bot_clean_header($config['from_name'], 120);
+    $replyTo = bot_clean_header($replyTo, 320);
+    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL) === false) {
+        $replyTo = '';
+    }

     if (filter_var($to, FILTER_VALIDATE_EMAIL) === false || filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
         return array(false, 'Invalid email address');
     }
     if ($subject === '' || $body === '') {
         return array(false, 'Subject and body are required');
     }

     if ($config['driver'] === 'smtp') {
         $mail = admin_config()['mail'];
         $html = '<pre style="white-space:pre-wrap;font-family:Arial,sans-serif">' . htmlspecialchars($body, ENT_QUOTES, 'UTF-8') . '</pre>';
-        return admin_mail_send_smtp($mail, $to, $subject, $body, $html);
+        // NOTE: admin_mail_send_smtp() Reply-To qo'shmaydi. Reply-To
+        // kerak bo'lsa, `mail` driver ishlatilsin.
+        return admin_mail_send_smtp($mail, $to, $subject, $body, $html);
     }

     $headers = 'From: ' . ($fromName !== '' ? $fromName . ' <' . $from . '>' : $from) . "\r\n"
         . 'Reply-To: ' . ($replyTo !== '' ? $replyTo : $from) . "\r\n"
         . 'MIME-Version: 1.0' . "\r\n"
         . 'Content-Type: text/plain; charset=UTF-8' . "\r\n"
         . 'Content-Transfer-Encoding: 8bit' . "\r\n";
     $sent = @mail($to, $subject, $body, $headers, '-f ' . $from);

     return $sent ? array(true, '') : array(false, 'Mail service rejected the message');
 }
```

---

## 7. `bot_help_text()` — ALMASHTIRING

```php
function bot_help_text()
{
    return "Admin email & inbox bridge\n\n"
        . "/whoami — joriy admin ma’lumoti\n"
        . "/messages — oxirgi 10 contact xabar\n"
        . "/unread — faqat o‘qilmagan xabarlar\n"
        . "/message <id> — contact xabarni o‘qish\n"
        . "/reply <id> | matn — foydalanuvchiga email javob\n"
        . "/stats — statistika\n\n"
        . "/inbox — oxirgi 5 email (IMAP)\n"
        . "/read <uid> — emailni o‘qish\n"
        . "/send <email> | <mavzu> | <matn> — email yuborish\n"
        . "/help — yordam\n\n"
        . "Faqat faol adminlar foydalana oladi (web panelda boshqariladi).";
}
```

---

## 8. YANGI: `bot_web_inbox_list()`, `bot_web_message_read()` — `bot_handle_update()` dan oldin qo‘shing

```php
// +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// + NEW: contact_messages (web inbox) yordamchilari.
// +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
function bot_web_inbox_list($limit, $unreadOnly)
{
    $pdo = bot_db();
    if (!$pdo) {
        return null;
    }
    $limit = max(1, min(20, (int) $limit));
    $where = $unreadOnly ? ' WHERE is_read = 0' : '';
    try {
        $statement = $pdo->query(
            'SELECT id, name, email, subject, is_read, created_at
             FROM contact_messages' . $where . '
             ORDER BY is_read ASC, created_at DESC
             LIMIT ' . $limit
        );
        return $statement->fetchAll();
    } catch (Exception $error) {
        error_log('bot web inbox list failed: ' . $error->getMessage());
        return null;
    }
}

function bot_web_message_read($id)
{
    $pdo = bot_db();
    if (!$pdo) {
        return null;
    }
    $id = (int) $id;
    if ($id < 1) {
        return null;
    }
    try {
        $statement = $pdo->prepare(
            'SELECT id, form_id, locale, name, email, subject, message, is_read, created_at
             FROM contact_messages WHERE id = :id LIMIT 1'
        );
        $statement->execute(array(':id' => $id));
        $row = $statement->fetch();
        if (!$row) {
            return null;
        }
        if ((int) $row['is_read'] === 0) {
            $update = $pdo->prepare('UPDATE contact_messages SET is_read = 1 WHERE id = :id');
            $update->execute(array(':id' => $id));
            $row['is_read'] = 1;
        }
        return $row;
    } catch (Exception $error) {
        error_log('bot web message read failed: ' . $error->getMessage());
        return null;
    }
}
```

---

## 9. `bot_handle_update()` — TO‘LIQ ALMASHTIRING (asosiy diff)

```php
function bot_handle_update(array $update)
{
    if (!isset($update['message']) || !is_array($update['message'])) {
        return;
    }

    $message = $update['message'];
    $chat = isset($message['chat']) && is_array($message['chat']) ? $message['chat'] : array();
    $from = isset($message['from']) && is_array($message['from']) ? $message['from'] : array();
    $chatId = isset($chat['id']) ? (string) $chat['id'] : '';
    $fromId = isset($from['id']) ? (string) $from['id'] : '';

    if ($chatId === '' || $fromId === '') {
        return;
    }
    if (isset($chat['type']) && $chat['type'] !== 'private') {
        return;
    }
    if ($chatId !== $fromId) {
        return;
    }

    // Faqat faol adminlar. Web panelda is_active=0 yoki o'chirilsa —
    // keyingi xabar bilan bot ishlamaydi (bir so'rovga cache faqat
    // shu request davomida yashaydi).
    $admin = bot_find_admin($fromId);
    if (!$admin) {
        return;
    }

    $text = isset($message['text']) && is_scalar($message['text']) ? trim((string) $message['text']) : '';
    if ($text === '' || strlen($text) > 12000) {
        bot_send_message($chatId, 'Matn bo‘sh yoki juda uzun.');
        return;
    }

    $tgId = (int) $admin['telegram_id'];

    // --- /start, /help -------------------------------------------------
    if ($text === '/start' || $text === '/help'
        || strpos($text, '/start@') === 0 || strpos($text, '/help@') === 0) {
        bot_send_message($chatId, bot_help_text());
        return;
    }

    // --- /whoami -------------------------------------------------------
    if ($text === '/whoami' || strpos($text, '/whoami@') === 0) {
        $name = isset($admin['display_name']) && $admin['display_name']
            ? $admin['display_name'] : 'Admin';
        $email = isset($admin['email']) ? $admin['email'] : '';
        bot_send_message($chatId,
            $name . "\nTelegram ID: " . $admin['telegram_id'] . "\nEmail: " . $email);
        return;
    }

    // --- /messages (contact inbox) -------------------------------------
    if ($text === '/messages' || $text === '/msgs' || strpos($text, '/messages@') === 0) {
        if (!bot_rate_limit_ok($tgId, 'messages', 20)) {
            bot_send_message($chatId, 'Juda ko‘p so‘rov. Bir daqiqadan keyin qayta urinib ko‘ring.');
            return;
        }
        $rows = bot_web_inbox_list(10, false);
        if ($rows === null) {
            bot_send_message($chatId, 'Xabarlar bazasi mavjud emas.');
            return;
        }
        if (count($rows) === 0) {
            bot_send_message($chatId, 'Contact inbox bo‘sh.');
            return;
        }
        $lines = array('Contact inbox (oxirgi ' . count($rows) . '):');
        foreach ($rows as $row) {
            $lines[] = ((int) $row['is_read'] === 0 ? '● ' : '  ')
                . '#' . $row['id'] . ' — ' . ($row['subject'] ?: '(mavzusiz)') . "\n   "
                . ($row['name'] ?: $row['email']) . ' · ' . $row['created_at'];
        }
        $lines[] = "Ochish uchun: /message <id>";
        bot_send_message($chatId, implode("\n\n", $lines));
        return;
    }

    // --- /unread -------------------------------------------------------
    if ($text === '/unread' || strpos($text, '/unread@') === 0) {
        if (!bot_rate_limit_ok($tgId, 'unread', 20)) {
            bot_send_message($chatId, 'Juda ko‘p so‘rov.');
            return;
        }
        $rows = bot_web_inbox_list(10, true);
        if ($rows === null) {
            bot_send_message($chatId, 'Xabarlar bazasi mavjud emas.');
            return;
        }
        if (count($rows) === 0) {
            bot_send_message($chatId, 'O‘qilmagan xabar yo‘q.');
            return;
        }
        $lines = array('O‘qilmagan xabarlar (' . count($rows) . '):');
        foreach ($rows as $row) {
            $lines[] = '#' . $row['id'] . ' — ' . ($row['subject'] ?: '(mavzusiz)') . "\n   "
                . ($row['name'] ?: $row['email']) . ' · ' . $row['created_at'];
        }
        bot_send_message($chatId, implode("\n\n", $lines));
        return;
    }

    // --- /message <id> -------------------------------------------------
    if (preg_match('#^/message(?:@[A-Za-z0-9_]+)?\s+([0-9]{1,12})$#', $text, $matches)) {
        if (!bot_rate_limit_ok($tgId, 'message', 30)) {
            bot_send_message($chatId, 'Juda ko‘p so‘rov.');
            return;
        }
        $row = bot_web_message_read((int) $matches[1]);
        if (!$row) {
            bot_send_message($chatId, 'Xabar topilmadi.');
            return;
        }
        bot_send_message($chatId,
            'Xabar #' . $row['id'] . "\n"
            . 'Kimdan: ' . ($row['name'] ?: '(ism yo‘q)') . ' <' . ($row['email'] ?: '-') . ">\n"
            . 'Mavzu: ' . ($row['subject'] ?: '(mavzusiz)') . "\n"
            . 'Sana: ' . $row['created_at'] . "\n"
            . 'Locale: ' . ($row['locale'] ?: '-') . "\n\n"
            . ($row['message'] ?: '(matn yo‘q)') . "\n\n"
            . 'Javob: /reply ' . $row['id'] . ' | <matn>');
        bot_audit($admin, 'bot_message_read', array('message_id' => (int) $row['id']));
        return;
    }

    // --- /reply <id> | matn -------------------------------------------
    if (preg_match('#^/reply(?:@[A-Za-z0-9_]+)?\s+([0-9]{1,12})\s*\|\s*(.+)$#s', $text, $matches)) {
        if (!bot_rate_limit_ok($tgId, 'reply', 10)) {
            bot_send_message($chatId, 'Juda ko‘p email javob so‘rovi.');
            return;
        }
        $id = (int) $matches[1];
        $body = trim((string) $matches[2]);
        if (strlen($body) < 1 || strlen($body) > 5000) {
            bot_send_message($chatId, 'Javob matni 1–5000 belgi oralig‘ida bo‘lishi kerak.');
            return;
        }
        $pdo = bot_db();
        if (!$pdo) {
            bot_send_message($chatId, 'Baza mavjud emas.');
            return;
        }
        try {
            $statement = $pdo->prepare(
                'SELECT id, email, subject FROM contact_messages WHERE id = :id LIMIT 1'
            );
            $statement->execute(array(':id' => $id));
            $original = $statement->fetch();
        } catch (Exception $error) {
            error_log('bot reply lookup failed: ' . $error->getMessage());
            $original = null;
        }
        if (!$original || !$original['email']) {
            bot_send_message($chatId, 'Xabar yoki email topilmadi.');
            return;
        }
        $replyToEmail = isset($admin['email']) ? (string) $admin['email'] : '';
        $subject = 'Re: ' . ($original['subject'] ?: 'Your message');
        $result = bot_send_email($original['email'], $subject, $body, $replyToEmail);
        if ($result[0]) {
            bot_send_message($chatId, 'Javob yuborildi: ' . $original['email']);
            bot_audit($admin, 'bot_message_reply', array(
                'message_id' => $id,
                'to' => $original['email'],
            ));
        } else {
            bot_send_message($chatId, 'Javob yuborilmadi: ' . $result[1]);
        }
        return;
    }

    // --- /stats -------------------------------------------------------
    if ($text === '/stats' || strpos($text, '/stats@') === 0) {
        $pdo = bot_db();
        if (!$pdo) {
            bot_send_message($chatId, 'Baza mavjud emas.');
            return;
        }
        try {
            $unread = (int) $pdo->query('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0')->fetchColumn();
            $total = (int) $pdo->query('SELECT COUNT(*) FROM contact_messages')->fetchColumn();
            $activeAdmins = (int) $pdo->query('SELECT COUNT(*) FROM admin_users WHERE is_active = 1')->fetchColumn();
            bot_send_message($chatId,
                "Statistika:\n"
                . "O‘qilmagan xabar: $unread\n"
                . "Jami xabar: $total\n"
                . "Faol adminlar: $activeAdmins");
        } catch (Exception $error) {
            error_log('bot stats failed: ' . $error->getMessage());
            bot_send_message($chatId, 'Statistika olinmadi.');
        }
        return;
    }

    // --- /inbox (IMAP email) ------------------------------------------
    if ($text === '/inbox' || $text === '/mail' || strpos($text, '/inbox@') === 0) {
        if (!bot_rate_limit_ok($tgId, 'inbox', 10)) {
            bot_send_message($chatId, 'Juda ko‘p so‘rov. Bir daqiqadan keyin qayta urinib ko‘ring.');
            return;
        }
        $inbox = bot_imap_open();
        if (!$inbox) {
            bot_send_message($chatId, 'Email qutisiga ulanib bo‘lmadi. Keyinroq urinib ko‘ring.');
            return;
        }
        $rows = bot_mail_list($inbox, 5);
        bot_imap_close($inbox);
        if (count($rows) === 0) {
            bot_send_message($chatId, 'Inbox bo‘sh.');
            return;
        }
        $lines = array('Inbox — oxirgi xatlar:');
        foreach ($rows as $row) {
            $lines[] = '#' . $row['uid'] . ' ' . ($row['seen'] ? '' : '● ') . $row['subject'] . "\n"
                . '   ' . $row['from'] . ' · ' . $row['date'];
        }
        bot_send_message($chatId, implode("\n\n", $lines));
        return;
    }

    // --- /read <uid> (IMAP) -------------------------------------------
    if (preg_match('#^/read(?:@[A-Za-z0-9_]+)?\s+([0-9]{1,12})$#', $text, $matches)) {
        if (!bot_rate_limit_ok($tgId, 'read', 20)) {
            bot_send_message($chatId, 'Juda ko‘p so‘rov. Bir daqiqadan keyin qayta urinib ko‘ring.');
            return;
        }
        $inbox = bot_imap_open();
        if (!$inbox) {
            bot_send_message($chatId, 'Email qutisiga ulanib bo‘lmadi.');
            return;
        }
        $mail = bot_mail_read($inbox, (int) $matches[1]);
        bot_imap_close($inbox);
        if (!$mail) {
            bot_send_message($chatId, 'Xat topilmadi yoki uid noto‘g‘ri.');
            return;
        }
        bot_send_message($chatId, 'Kimdan: ' . $mail['from'] . "\n"
            . 'Mavzu: ' . $mail['subject'] . "\n"
            . 'Sana: ' . $mail['date'] . "\n\n"
            . $mail['body']);
        return;
    }

    // --- /send <email> | mavzu | matn (IMAP) --------------------------
    if (preg_match('#^/send(?:@[A-Za-z0-9_]+)?\s+(.+)$#s', $text, $matches)) {
        if (!bot_rate_limit_ok($tgId, 'send', 10)) {
            bot_send_message($chatId, 'Juda ko‘p email so‘rovi. Bir daqiqadan keyin qayta urinib ko‘ring.');
            return;
        }
        $parts = explode('|', $matches[1], 3);
        if (count($parts) < 3) {
            bot_send_message($chatId, 'Format: /send email@example.com | Mavzu | Matn');
            return;
        }
        $result = bot_send_email(trim($parts[0]), trim($parts[1]), trim($parts[2]));
        if ($result[0]) {
            bot_send_message($chatId, 'Email yuborildi: ' . bot_clean_header(trim($parts[0]), 320));
            bot_audit($admin, 'bot_email_sent', array('to' => trim($parts[0])));
        } else {
            bot_send_message($chatId, 'Email yuborilmadi: ' . $result[1]);
        }
        return;
    }

    bot_send_message($chatId, bot_help_text());
}
```

**O‘zgarishlar tahlili:**
- `$config['admin_id']` ga hard-code tekshiruv **olib tashlandi** → `bot_find_admin()`
- Har bir `bot_rate_limit_ok(...)` chaqirig‘iga `$tgId` qo‘shildi
- Yangi: `/whoami`, `/messages`, `/unread`, `/message`, `/reply`, `/stats`
- Barcha yangi handlerlar uchun `bot_audit()` chaqiriladi
- Eski `/inbox`, `/read`, `/send` — o‘sha-o‘sha, faqat `$tgId` bilan

---

## 10. `bot_handle_webhook()` — o‘zgartirmang ✅

Webhook autentifikatsiyasi (secret header) o‘zgartirilmaydi. Faqat `bot_handle_update()` ichidagi ruxsat logikasi o‘zgardi.

---

## 11. Bog‘langan: `backend/contact.php` — `bot_notify_admins()` chaqirish

`contact_native_submit()` oxirida (muvaffaqiyatli `contact_store_message()` dan keyin):

```diff
     if (!contact_store_message($formConfig, 'contact', $data, array())) {
         return array(false, 'Message could not be saved. Please try again or email directly.', 503);
     }

+    // Best-effort: barcha faol adminlarga Telegram xabar. Contact form
+    // natijasiga ta'sir qilmaydi.
+    if (is_file(__DIR__ . '/bot.php')) {
+        require_once __DIR__ . '/bot.php';
+        bot_notify_admins(
+            "📩 Yangi xabar\n"
+            . "Kimdan: $name <$email>\n"
+            . "Mavzu: $subject\n\n"
+            . (function_exists('mb_substr') ? mb_substr($message, 0, 500, 'UTF-8') : substr($message, 0, 500))
+        );
+    }
+
     return array(true, 'Message sent successfully.', 200);
 }
```

> ⚠️ `require_once` bir marta — keyingi chaqiriqlarda `bot_notify_admins()` allaqachon aniqlangan bo‘ladi.

---

## 12. Migration (idempotent)

`database/migrate-bot-multi-admin.sql` — **yangi fayl**:

```sql
-- Ensure contact_messages has all columns used by the bot /messages command.
-- Idempotent: MySQL 5.7 doesn't support ADD COLUMN IF NOT EXISTS directly,
-- so run once and check with SHOW COLUMNS.
--
-- The bot relies on:
--   contact_messages(id, form_id, locale, name, email, subject, message,
--                    payload, ip_hash, user_agent, is_read, created_at)
--   admin_users(id, telegram_id, email, display_name, telegram_username,
--               is_active, last_login_at, created_at)
--   admin_audit_log(admin_id, action, entity, ip_hash, details)
--   bot_rate_limit(admin_id, command, created_at)
--   bot_updates_seen(update_id, seen_at)
--
-- All of these already exist in database/schema.sql and
-- database/migrate-bot-guards.sql. This file is a no-op sanity check.

SELECT 'bot multi-admin migration: no schema change required' AS status;
```

Agar qo‘shimcha `admin_users.role` kerak bo‘lsa (keyingi bosqich):

```sql
ALTER TABLE admin_users
  ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'admin' AFTER is_active;
```

---

## 13. `setMyCommands` skript

`scripts/set-bot-commands.sh` — **yangi fayl**:

```bash
#!/usr/bin/env bash
# Register bot commands in BotFather. Run once after deploy.
set -euo pipefail
BOT_TOKEN="${BOT_TOKEN:?BOT_TOKEN is required}"

curl -sS "https://api.telegram.org/bot${BOT_TOKEN}/setMyCommands" \
  -H 'Content-Type: application/json' \
  --data '{
    "commands": [
      {"command": "start",    "description": "Boshlash"},
      {"command": "help",     "description": "Yordam"},
      {"command": "whoami",   "description": "Joriy admin"},
      {"command": "messages", "description": "Contact inbox (10 ta)"},
      {"command": "unread",   "description": "O‘qilmagan xabarlar"},
      {"command": "message",  "description": "Xabarni o‘qish (id)"},
      {"command": "reply",    "description": "Email javob (id | matn)"},
      {"command": "stats",    "description": "Statistika"},
      {"command": "inbox",    "description": "IMAP email inbox"},
      {"command": "read",     "description": "Emailni o‘qish (uid)"},
      {"command": "send",     "description": "Email yuborish"}
    ]
  }'
```

---

## 14. Post-deploy checklist

| # | Tekshiruv | Kutilgan |
|---|---|---|
| 1 | `php -l backend/bot.php` | OK |
| 2 | `php -l backend/contact.php` | OK |
| 3 | Web panelda 2-admin qo‘shing, ikkalasi `is_active=1` | ✅ |
| 4 | Botga 1-admin bilan `/whoami` | ism + email |
| 5 | Web panelda 2-adminni `is_active=0` qiling | ✅ |
| 6 | 2-admin botga `/help` yuborsin | **javob yo‘q** (silent drop) |
| 7 | 1-admin `/messages` | contact inbox ro‘yxati |
| 8 | 1-admin `/message 1` | to‘liq xabar, `is_read` DB’da 1 bo‘ladi |
| 9 | 1-admin `/reply 1 \| sinov` | email foydalanuvchiga ketadi, `admin_audit_log` da yozuv |
| 10 | 1-admin `/stats` | unread/total/active sonlari |
| 11 | Contact forma yuboring | barcha faol adminlarga Telegram xabar |
| 12 | `bot_rate_limit_ok(<tg>, 'reply', 10)` 11-chi chaqiriq | `false` |
| 13 | Web panelda 1-adminni ham o‘chirsangiz | `/help` silent drop (last-admin guard bloklaydi) |
| 14 | DB’ni vaqtincha uzib, bootstrap `telegram_id` bilan `/help` | ishlaydi (fallback) |
| 15 | `git diff --check` va `git diff --stat` | faqat `backend/bot.php`, `backend/contact.php`, `scripts/set-bot-commands.sh` |

---

## 15. Hujjat yangilash

- `AGENTS.md` — “Bot multi-admin: `bot_find_admin()`, `/whoami`, `/messages`, `/message`, `/reply`, `/stats`; rate limit per-telegram_id”
- `SECURITY.md` — “Bot faqat `admin_users.is_active=1` bo‘lsa ishlaydi; `is_active=0` darhol kuchga kiradi”
- `README.md` — Bot komandalar ro‘yxati


# `backend/admin.php` + `components/admin/AdminManager.jsx` — `role` maydoni

Bu bosqichda `super` (boshqaruvchi) va `admin` (kontent) rollari qo‘shiladi. **Super** — boshqa adminlarni boshqara oladi. **Admin** — faqat profil/loyiha/inbox CRUD, adminlar tabini ko‘rmaydi.

> **Muhim**: `admin_lookup_session()` har safar `admin_users` ga JOIN qiladi, shuning uchun **rol o‘zgarsa darhol kuchga kiradi** — sessiyani bekor qilish shart emas. Faqat `is_active=0` bo‘lsa session avtomatik o‘chadi (bu allaqachon ishlaydi).

---

## 1. Migration — `database/migrate-admin-role.sql` (yangi fayl)

```sql
-- Add admin role (super/admin) to an existing database.
-- One-time (MySQL 5.7 doesn't support ADD COLUMN IF NOT EXISTS).
-- Usage:
--   mysql -u USER -p DB_NAME < database/migrate-admin-role.sql
--
-- After running, promote the owner to super ONCE:
--   mysql -u USER -p DB_NAME -e "UPDATE admin_users SET role='super' WHERE telegram_id='<OWNER_ID>';"
--
-- The bot auto-bootstraps the config telegram_id as super if the table is
-- empty (see backend/admin.php:admin_find_or_bootstrap_user()).

SET NAMES utf8mb4;

ALTER TABLE admin_users
  ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'admin' AFTER is_active,
  ADD KEY idx_admin_users_role (role);

-- Safety net: promote the earliest admin to super so the panel isn't locked
-- out when the owner logs in with a different Telegram ID than expected.
UPDATE admin_users
   SET role = 'super'
 WHERE id = (SELECT id FROM (SELECT MIN(id) AS id FROM admin_users) AS first_admin)
   AND NOT EXISTS (SELECT 1 FROM admin_users WHERE role = 'super');
```

**`database/schema.sql`** — yangi o‘rnatishlar uchun (Create Table ichida):

```diff
 CREATE TABLE IF NOT EXISTS admin_users (
   id INT UNSIGNED NOT NULL AUTO_INCREMENT,
   telegram_id BIGINT UNSIGNED NOT NULL,
   email VARCHAR(190) NOT NULL,
   display_name VARCHAR(120) DEFAULT NULL,
   telegram_username VARCHAR(64) DEFAULT NULL,
   is_active TINYINT(1) NOT NULL DEFAULT 1,
+  role VARCHAR(20) NOT NULL DEFAULT 'admin',
   last_login_at DATETIME DEFAULT NULL,
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   PRIMARY KEY (id),
   UNIQUE KEY uniq_admin_users_telegram (telegram_id),
-  KEY idx_admin_users_active (is_active)
+  KEY idx_admin_users_active (is_active),
+  KEY idx_admin_users_role (role)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 2. `backend/admin.php` — diff (ketma-ket hunks)

### 2.1. `admin_lookup_session()` — `role` ni qo‘shish

```diff
 function admin_lookup_session($pdo, $touch)
 {
     $token = admin_cookie(ADMIN_SESSION_COOKIE);
     if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
         return null;
     }

     $statement = $pdo->prepare(
-        'SELECT s.id AS session_id, s.admin_id, s.expires_at, s.last_seen_at,
-                a.telegram_id, a.email, a.display_name, a.telegram_username, a.last_login_at
+        'SELECT s.id AS session_id, s.admin_id, s.expires_at, s.last_seen_at,
+                a.telegram_id, a.email, a.display_name, a.telegram_username,
+                a.role, a.last_login_at
          FROM admin_sessions s
          INNER JOIN admin_users a ON a.id = s.admin_id
          WHERE s.token_hash = :token_hash
            AND s.expires_at > UTC_TIMESTAMP()
            AND a.is_active = 1
          LIMIT 1'
     );
     $statement->execute(array(':token_hash' => admin_session_hash($token)));
     $row = $statement->fetch();
     if (!$row) {
         return null;
     }
```

### 2.2. YANGI: `admin_require_super()` va `admin_has_other_active_super()` — `admin_require_csrf()` dan keyin qo‘shing

```php
// +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// + NEW: role guards.
// +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
function admin_require_super($session)
{
    $role = isset($session['row']['role']) ? (string) $session['row']['role'] : '';
    if ($role !== 'super') {
        admin_fail('Super admin permission required', 'forbidden', 403);
    }
}

function admin_has_other_active_super($pdo, $exceptId)
{
    $statement = $pdo->prepare(
        "SELECT COUNT(*) FROM admin_users
         WHERE is_active = 1 AND role = 'super' AND id <> :id"
    );
    $statement->execute(array(':id' => (int) $exceptId));
    return (int) $statement->fetchColumn() > 0;
}
```

### 2.3. `admin_find_or_bootstrap_user()` — bootstrap INSERT ga `role='super'`

```diff
     $displayName = trim($telegramUser['first_name'] . ' ' . $telegramUser['last_name']);
     $insert = $pdo->prepare(
-        'INSERT INTO admin_users (telegram_id, email, display_name, telegram_username, is_active)
-         VALUES (:telegram_id, :email, :display_name, :username, 1)'
+        'INSERT INTO admin_users (telegram_id, email, display_name, telegram_username, is_active, role)
+         VALUES (:telegram_id, :email, :display_name, :username, 1, :role)'
     );
     try {
         $insert->execute(array(
             ':telegram_id' => $telegramUser['id'],
             ':email' => $config['email'],
             ':display_name' => $displayName !== '' ? $displayName : null,
             ':username' => $telegramUser['username'] !== '' ? $telegramUser['username'] : null,
+            ':role' => 'super',
         ));
     } catch (PDOException $error) {
         // Another callback may have created the same allowlisted admin.
         if ($error->getCode() !== '23000') {
             throw $error;
         }
     }

-    $statement->execute(array(':telegram_id' => $telegramUser['id']));
+    $statement->execute(array(':telegram_id' => $telegramUser['id']));
     return $statement->fetch();
 }
```

> **Eslatma**: `admin_find_or_bootstrap_user()` SELECT allaqachon `SELECT id, telegram_id, ...` edi. Endi `role` qo‘shing:

```diff
     $statement = $pdo->prepare(
-        'SELECT id, telegram_id, email, display_name, telegram_username, is_active, last_login_at
+        'SELECT id, telegram_id, email, display_name, telegram_username, is_active, role, last_login_at
          FROM admin_users WHERE telegram_id = :telegram_id LIMIT 1'
     );
```

Va shu funksiya oxirida, bootstrap chaqiruvida, agar jadvaldagi **barcha** adminlar oddiy `admin` bo‘lib qolgan bo‘lsa (migration eski qaytarilgan holat), config telegram_id ni super qilish safety net:

```diff
     $statement->execute(array(':telegram_id' => $telegramUser['id']));
-    return $statement->fetch();
+    $row = $statement->fetch();
+
+    // Safety net: if a pre-role migration left no super admin, promote
+    // the configured bootstrap ID on first login.
+    if ($row && $row['role'] !== 'super'
+        && (string) $row['telegram_id'] === (string) $config['telegram_id']) {
+        try {
+            $superCount = (int) $pdo->query("SELECT COUNT(*) FROM admin_users WHERE is_active = 1 AND role = 'super'")->fetchColumn();
+            if ($superCount === 0) {
+                $promote = $pdo->prepare("UPDATE admin_users SET role = 'super' WHERE id = :id");
+                $promote->execute(array(':id' => $row['id']));
+                $row['role'] = 'super';
+            }
+        } catch (Exception $error) {
+            error_log('admin bootstrap promote failed: ' . $error->getMessage());
+        }
+    }
+    return $row;
 }
```

### 2.4. `admin_handle_session()` — javobga `role` qo‘shish

```diff
     admin_respond(array(
         'authenticated' => true,
         'admin' => array(
             'id' => (int) $session['row']['admin_id'],
             'telegram_id' => $session['row']['telegram_id'],
             'email' => $session['row']['email'],
             'display_name' => $session['row']['display_name'],
             'telegram_username' => $session['row']['telegram_username'],
+            'role' => $session['row']['role'],
             'last_login_at' => $session['row']['last_login_at'],
         ),
         'csrf_token' => admin_csrf_token($session['token']),
         'expires_at' => $session['row']['expires_at'],
     ), 200);
```

### 2.5. `admin_validate_admin()` — `role` validatsiyasi

```diff
 function admin_validate_admin($input)
 {
     $errors = array();
     $telegramId = admin_text(isset($input['telegram_id']) ? $input['telegram_id'] : '', 20, false);
     if ($telegramId === null || !preg_match('/^[0-9]{5,19}$/', $telegramId)) {
         $errors['telegram_id'] = 'Telegram ID must contain 5-19 digits.';
     }
     $email = admin_text(isset($input['email']) ? $input['email'] : '', 190, false);
     if ($email === null || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
         $errors['email'] = 'A valid email is required.';
     }
     $displayName = admin_text(isset($input['display_name']) ? $input['display_name'] : '', 120, true);
     if ($displayName === null) {
         $errors['display_name'] = 'Display name is too long.';
     }
     $isActive = !empty($input['is_active']) && (string) $input['is_active'] !== '0';
+    $role = admin_text(isset($input['role']) ? $input['role'] : 'admin', 20, false);
+    if ($role === null || !in_array($role, array('super', 'admin'), true)) {
+        $errors['role'] = 'Role super yoki admin bo‘lishi kerak.';
+    }

     if ($errors) {
         return array(null, $errors);
     }
     return array(array(
         'telegram_id' => $telegramId,
         'email' => $email,
         'display_name' => $displayName === '' ? null : $displayName,
         'is_active' => $isActive ? 1 : 0,
+        'role' => $role,
     ), array());
 }
```

### 2.6. `admin_handle_admins()` — super guard + `role`

```diff
 function admin_handle_admins($pdo)
 {
     $method = admin_require_method(array('GET', 'POST'));
     $session = admin_require_session($pdo);
+    admin_require_super($session);
     if ($method === 'GET') {
         $statement = $pdo->query(
-            'SELECT id, telegram_id, email, display_name, telegram_username, is_active, last_login_at, created_at
+            'SELECT id, telegram_id, email, display_name, telegram_username, is_active, role, last_login_at, created_at
              FROM admin_users ORDER BY id ASC'
         );
         $items = array();
         foreach ($statement->fetchAll() as $row) {
             $items[] = admin_admin_data($row);
         }
         admin_respond($items, 200);
     }

     admin_require_csrf($session);
     $validated = admin_validate_admin(admin_json_input());
     if ($validated[1]) {
         admin_headers();
         http_response_code(422);
         echo json_encode(array('status' => 'error', 'message' => 'Validation failed', 'code' => 'validation_failed', 'errors' => $validated[1]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
         exit;
     }
     $admin = $validated[0];

     try {
         $statement = $pdo->prepare(
-            'INSERT INTO admin_users (telegram_id, email, display_name, is_active)
-             VALUES (:telegram_id, :email, :display_name, :is_active)'
+            'INSERT INTO admin_users (telegram_id, email, display_name, is_active, role)
+             VALUES (:telegram_id, :email, :display_name, :is_active, :role)'
         );
         $statement->execute(array(
             ':telegram_id' => $admin['telegram_id'],
             ':email' => $admin['email'],
             ':display_name' => $admin['display_name'],
             ':is_active' => $admin['is_active'],
+            ':role' => $admin['role'],
         ));
         $id = (int) $pdo->lastInsertId();
-        admin_audit($pdo, $session['row']['admin_id'], 'admin_created', 'admin_user', $id, array('telegram_id' => $admin['telegram_id']));
-        $row = $pdo->prepare('SELECT id, telegram_id, email, display_name, telegram_username, is_active, last_login_at, created_at FROM admin_users WHERE id = :id');
+        admin_audit($pdo, $session['row']['admin_id'], 'admin_created', 'admin_user', $id, array(
+            'telegram_id' => $admin['telegram_id'],
+            'role' => $admin['role'],
+        ));
+        $row = $pdo->prepare('SELECT id, telegram_id, email, display_name, telegram_username, is_active, role, last_login_at, created_at FROM admin_users WHERE id = :id');
         $row->execute(array(':id' => $id));
         admin_respond(admin_admin_data($row->fetch()), 201);
     } catch (PDOException $error) {
         if ($error->getCode() === '23000') {
             admin_fail('This Telegram ID is already an admin', 'admin_exists', 409);
         }
         error_log('admin create error: ' . $error->getMessage());
         admin_fail('Admin could not be created', 'save_failed', 500);
     } catch (Exception $error) {
         error_log('admin create error: ' . $error->getMessage());
         admin_fail('Admin could not be created', 'save_failed', 500);
     }
 }
```

### 2.7. `admin_handle_admin_item()` — super guard + `role` + last-super himoyasi

```diff
 function admin_handle_admin_item($pdo, $id)
 {
     $method = admin_require_method(array('GET', 'PUT', 'DELETE'));
     $session = admin_require_session($pdo);
-    $statement = $pdo->prepare('SELECT id, telegram_id, email, display_name, telegram_username, is_active, last_login_at, created_at FROM admin_users WHERE id = :id LIMIT 1');
+    admin_require_super($session);
+    $statement = $pdo->prepare('SELECT id, telegram_id, email, display_name, telegram_username, is_active, role, last_login_at, created_at FROM admin_users WHERE id = :id LIMIT 1');
     $statement->execute(array(':id' => (int) $id));
     $row = $statement->fetch();
     if (!$row) {
         admin_fail('Admin not found', 'admin_not_found', 404);
     }
     $current = admin_admin_data($row);

     if ($method === 'GET') {
         admin_respond($current, 200);
     }

     admin_require_csrf($session);
     if ($method === 'DELETE') {
         if ((int) $id === (int) $session['row']['admin_id']) {
             admin_fail('You cannot delete your own account', 'cannot_delete_self', 422);
         }
         if ($current['is_active'] && admin_active_count($pdo, $id) === 0) {
             admin_fail('At least one active admin is required', 'last_admin', 422);
         }
+        // NEW: never delete the last active super admin.
+        if ($current['role'] === 'super' && !admin_has_other_active_super($pdo, $id)) {
+            admin_fail('At least one active super admin is required', 'last_super_admin', 422);
+        }
         $delete = $pdo->prepare('DELETE FROM admin_users WHERE id = :id');
         $delete->execute(array(':id' => (int) $id));
-        admin_audit($pdo, $session['row']['admin_id'], 'admin_deleted', 'admin_user', $id, array('telegram_id' => $current['telegram_id']));
+        admin_audit($pdo, $session['row']['admin_id'], 'admin_deleted', 'admin_user', $id, array(
+            'telegram_id' => $current['telegram_id'],
+            'role' => $current['role'],
+        ));
         admin_respond(array('deleted' => true, 'id' => (int) $id), 200);
     }

     $validated = admin_validate_admin(admin_json_input());
     if ($validated[1]) {
         admin_headers();
         http_response_code(422);
         echo json_encode(array('status' => 'error', 'message' => 'Validation failed', 'code' => 'validation_failed', 'errors' => $validated[1]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
         exit;
     }
     $admin = $validated[0];
     if ((int) $id === (int) $session['row']['admin_id'] && !$admin['is_active']) {
         admin_fail('You cannot deactivate your own account', 'cannot_deactivate_self', 422);
     }
     if (!$admin['is_active'] && $current['is_active'] && admin_active_count($pdo, $id) === 0) {
         admin_fail('At least one active admin is required', 'last_admin', 422);
     }
+    // NEW: never demote/deactivate the last active super admin (including self).
+    if ($current['role'] === 'super'
+        && (!$admin['is_active'] || $admin['role'] !== 'super')
+        && !admin_has_other_active_super($pdo, $id)) {
+        admin_fail('At least one active super admin is required', 'last_super_admin', 422);
+    }

     try {
         $update = $pdo->prepare(
-            'UPDATE admin_users SET telegram_id = :telegram_id, email = :email, display_name = :display_name, is_active = :is_active WHERE id = :id'
+            'UPDATE admin_users SET telegram_id = :telegram_id, email = :email, display_name = :display_name, is_active = :is_active, role = :role WHERE id = :id'
         );
         $update->execute(array(
             ':telegram_id' => $admin['telegram_id'],
             ':email' => $admin['email'],
             ':display_name' => $admin['display_name'],
             ':is_active' => $admin['is_active'],
+            ':role' => $admin['role'],
             ':id' => (int) $id,
         ));
-        admin_audit($pdo, $session['row']['admin_id'], 'admin_updated', 'admin_user', $id, array('telegram_id' => $admin['telegram_id'], 'is_active' => $admin['is_active']));
-        $fresh = $pdo->prepare('SELECT id, telegram_id, email, display_name, telegram_username, is_active, last_login_at, created_at FROM admin_users WHERE id = :id');
+        admin_audit($pdo, $session['row']['admin_id'], 'admin_updated', 'admin_user', $id, array(
+            'telegram_id' => $admin['telegram_id'],
+            'is_active' => $admin['is_active'],
+            'role' => $admin['role'],
+        ));
+        $fresh = $pdo->prepare('SELECT id, telegram_id, email, display_name, telegram_username, is_active, role, last_login_at, created_at FROM admin_users WHERE id = :id');
         $fresh->execute(array(':id' => (int) $id));
         admin_respond(admin_admin_data($fresh->fetch()), 200);
     } catch (PDOException $error) {
         ...
```

### 2.8. `admin_admin_data()` — o‘zgarishsiz (role pass-through)

```php
function admin_admin_data($row)
{
    $row['id'] = (int) $row['id'];
    $row['telegram_id'] = (string) $row['telegram_id'];
    $row['is_active'] = (int) $row['is_active'] === 1;
    // role is a plain VARCHAR; passed through as-is.
    return $row;
}
```

---

## 3. `components/admin/api.js` — yangi error code’lar

```diff
   if (error.code === 'cannot_delete_self') return 'O‘z admin hisobingizni o‘chira olmaysiz.';
   if (error.code === 'cannot_deactivate_self') return 'O‘z admin hisobingizni deaktiv qila olmaysiz.';
   if (error.code === 'last_admin') return 'Kamida bitta faol admin qolishi kerak.';
+  if (error.code === 'last_super_admin') return 'Kamida bitta faol super admin qolishi kerak.';
+  if (error.code === 'forbidden') return 'Bu amal uchun super admin huquqi kerak.';
   if (error.code === 'admin_exists') return 'Bu Telegram ID allaqachon admin.';
```

---

## 4. `components/admin/Dashboard.jsx` — `role` ni uzatish + tabni yashirish

```diff
   const [admin, setAdmin] = useState(null);
   const [profile, setProfile] = useState(null);
   const [projects, setProjects] = useState([]);
   const [unread, setUnread] = useState(0);
   const [tab, setTab] = useState('profile');
   const [error, setError] = useState('');
   const [toast, setToast] = useState('');
+  const isSuper = admin && admin.role === 'super';
@@
-          <button className={tab === 'admins' ? 'active' : ''} type="button" onClick={() => setTab('admins')}>
-            <span>04</span> Adminlar
-          </button>
+          {isSuper && (
+            <button className={tab === 'admins' ? 'active' : ''} type="button" onClick={() => setTab('admins')}>
+              <span>04</span> Adminlar
+            </button>
+          )}
@@
-        {tab === 'admins' && (
+        {tab === 'admins' && isSuper && (
           <AdminManager currentAdminId={admin.id} notify={setToast} />
         )}
```

`isSuper` bo‘lmagan admin `Adminlar` tabini ko‘rmaydi; agar u avvalgi sessiyada `tab='admins'` bo‘lgan bo‘lsa, keyingi login’da `isSuper` false bo‘lgani uchun tab ko‘rinmaydi va UI `null` render qiladi — buni oldini olish uchun `useEffect` bilan `tab` ni `profile` ga qaytarish:

```diff
   useEffect(() => {
     if (!toast) return;
     const timer = window.setTimeout(() => setToast(''), 3500);
     return () => window.clearTimeout(timer);
   }, [toast]);

+  useEffect(() => {
+    if (tab === 'admins' && !isSuper) {
+      setTab('profile');
+    }
+  }, [tab, isSuper]);
```

---

## 5. `components/admin/AdminManager.jsx` — to‘liq yangi fayl

```jsx
'use client';

import { useEffect, useState } from 'react';
import { adminApi, errorMessage } from './api';

const ROLES = [
  { id: 'super', label: 'Super admin', hint: 'Adminlarni boshqara oladi' },
  { id: 'admin', label: 'Admin', hint: 'Faqat kontent va inbox' },
];

function AdminEditor({ admin, onCancel, onSaved }) {
  const [form, setForm] = useState(() => ({
    telegram_id: admin ? String(admin.telegram_id || '') : '',
    email: admin ? admin.email || '' : '',
    display_name: admin ? admin.display_name || '' : '',
    is_active: admin ? Boolean(admin.is_active) : true,
    role: admin ? admin.role || 'admin' : 'admin',
  }));
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const isNew = !admin || !admin.id;

  function setField(key, value) {
    setForm((current) => ({ ...current, [key]: value }));
  }

  async function submit(event) {
    event.preventDefault();
    setSaving(true);
    setError('');
    try {
      const payload = {
        ...form,
        is_active: form.is_active ? 1 : 0,
      };
      const saved = isNew
        ? await adminApi('/admins', { method: 'POST', body: payload })
        : await adminApi(`/admins/${admin.id}`, { method: 'PUT', body: payload });
      onSaved(saved);
    } catch (failure) {
      setError(errorMessage(failure));
    } finally {
      setSaving(false);
    }
  }

  return (
    <form className="admin-panel" onSubmit={submit}>
      <div className="admin-panel-head">
        <div>
          <h2>{isNew ? 'Yangi admin' : `Tahrirlash: ${admin.telegram_id}`}</h2>
          <p>Telegram ID, OTP email manzili va rolni kiriting. Admin Telegram orqali kirganda shu emailga kod yuboriladi.</p>
        </div>
        <button className="admin-btn admin-btn-ghost" type="button" onClick={onCancel}>Bekor qilish</button>
      </div>

      <div className="admin-form-grid">
        <label>
          <span>Telegram ID</span>
          <input
            value={form.telegram_id}
            onChange={(event) => setField('telegram_id', event.target.value.replace(/[^0-9]/g, ''))}
            inputMode="numeric"
            maxLength={19}
            placeholder="123456789"
            required
          />
          <small className="admin-form-note">@userinfobot yoki Telegram profil IDsi.</small>
        </label>
        <label>
          <span>Email</span>
          <input
            type="email"
            value={form.email}
            onChange={(event) => setField('email', event.target.value)}
            maxLength={190}
            placeholder="admin@example.com"
            required
          />
        </label>
        <label>
          <span>Ko‘rsatiladigan ism</span>
          <input
            value={form.display_name}
            onChange={(event) => setField('display_name', event.target.value)}
            maxLength={120}
            placeholder="Sardorbek"
          />
        </label>
        <label>
          <span>Rol</span>
          <select value={form.role} onChange={(event) => setField('role', event.target.value)} required>
            {ROLES.map((role) => (
              <option key={role.id} value={role.id}>{role.label}</option>
            ))}
          </select>
          <small className="admin-form-note">
            {ROLES.find((role) => role.id === form.role)?.hint}
          </small>
        </label>
        <label className="admin-checkbox">
          <input type="checkbox" checked={form.is_active} onChange={(event) => setField('is_active', event.target.checked)} />
          <span>Faol (bot + panel)</span>
        </label>
      </div>

      <div className="admin-form-note">
        <strong>Diqqat:</strong> faolsizlantirilsa admin web panelga ham, botga ham kira olmaydi.
        Kamida bitta faol super admin qolishi shart.
      </div>

      {error && <div className="admin-alert admin-alert-error">{error}</div>}

      <div className="admin-form-actions">
        <button className="admin-btn admin-btn-primary" type="submit" disabled={saving}>
          {saving ? 'Saqlanmoqda...' : isNew ? 'Admin qo‘shish' : 'Adminni saqlash'}
        </button>
      </div>
    </form>
  );
}

export default function AdminManager({ currentAdminId, notify }) {
  const [admins, setAdmins] = useState([]);
  const [editing, setEditing] = useState(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(0);
  const [error, setError] = useState('');

  async function load() {
    setLoading(true);
    setError('');
    try {
      const data = await adminApi('/admins');
      setAdmins(data || []);
    } catch (failure) {
      setError(errorMessage(failure));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    let active = true;
    async function boot() {
      try {
        const data = await adminApi('/admins');
        if (!active) return;
        setAdmins(data || []);
      } catch (failure) {
        if (!active) return;
        setError(errorMessage(failure));
      } finally {
        if (active) setLoading(false);
      }
    }
    boot();
    return () => {
      active = false;
    };
  }, []);

  async function remove(admin) {
    if (busy) return;
    if (Number(admin.id) === Number(currentAdminId)) return;
    if (!window.confirm(`${admin.telegram_id} adminini o‘chirasizmi? Bu amalni qaytarib bo‘lmaydi va bot ham ishlamaydi.`)) return;
    setBusy(admin.id);
    setError('');
    try {
      await adminApi(`/admins/${admin.id}`, { method: 'DELETE' });
      await load();
      notify('Admin o‘chirildi.');
    } catch (failure) {
      setError(errorMessage(failure));
    } finally {
      setBusy(0);
    }
  }

  if (editing) {
    return (
      <AdminEditor
        key={editing === 'new' ? 'new' : editing.id}
        admin={editing === 'new' ? null : editing}
        onCancel={() => setEditing(null)}
        onSaved={async () => {
          await load();
          setEditing(null);
          notify('Admin saqlandi.');
        }}
      />
    );
  }

  const activeSupers = admins.filter((row) => row.role === 'super' && row.is_active).length;

  return (
    <section className="admin-panel">
      <div className="admin-panel-head">
        <div>
          <h2>Adminlar</h2>
          <p>
            {admins.length} ta admin, {activeSupers} ta faol super.
            Faqat faol adminlar Telegram + OTP orqali kira oladi; bot ham shu ro‘yxatga bo‘ysunadi.
          </p>
        </div>
        <button className="admin-btn admin-btn-primary" type="button" onClick={() => setEditing('new')}>+ Yangi admin</button>
      </div>

      {error && <div className="admin-alert admin-alert-error">{error}</div>}

      {loading ? (
        <div className="admin-empty">Yuklanmoqda...</div>
      ) : admins.length === 0 ? (
        <div className="admin-empty">Admin yo‘q.</div>
      ) : (
        <div className="admin-table-wrap">
          <table className="admin-table">
            <thead>
              <tr>
                <th>Telegram ID</th>
                <th>Email</th>
                <th>Ism</th>
                <th>Rol</th>
                <th>Holat</th>
                <th>Oxirgi kirish</th>
                <th aria-label="Amallar" />
              </tr>
            </thead>
            <tbody>
              {admins.map((admin) => {
                const isSelf = Number(admin.id) === Number(currentAdminId);
                const isLastSuper = admin.role === 'super' && admin.is_active && activeSupers <= 1;
                return (
                  <tr key={admin.id}>
                    <td><code>{admin.telegram_id}</code></td>
                    <td>{admin.email}</td>
                    <td>{admin.display_name || '—'}</td>
                    <td>
                      <span className={`admin-state ${admin.role === 'super' ? 'on' : ''}`}>
                        {admin.role === 'super' ? 'Super' : 'Admin'}
                      </span>
                    </td>
                    <td>
                      <span className={`admin-state ${admin.is_active ? 'on' : 'off'}`}>
                        {admin.is_active ? 'Faol' : 'O‘chirilgan'}
                      </span>
                    </td>
                    <td>{admin.last_login_at || '—'}</td>
                    <td className="admin-row-actions">
                      <button className="admin-btn admin-btn-small" type="button" onClick={() => setEditing(admin)}>Tahrirlash</button>
                      <button
                        className="admin-btn admin-btn-small admin-btn-danger"
                        type="button"
                        disabled={busy === admin.id || isSelf || isLastSuper}
                        title={isSelf ? 'O‘zingizni o‘chira olmaysiz' : isLastSuper ? 'Oxirgi faol super admin' : ''}
                        onClick={() => remove(admin)}
                      >
                        {busy === admin.id ? '...' : 'O‘chirish'}
                      </button>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </section>
  );
}
```

**Asosiy farqlar:**
- `role` select (Super / Admin), hint bilan
- `admin-state` bilan Super/Admin badge
- **`isLastSuper`** bo‘lsa o‘chirish tugmasi disabled
- Self-delete tugmasi allaqachon disabled (`isSelf`)
- `Deaktiv` izohi: “bot ham ishlamaydi” ogohlantirishi

---

## 6. `AGENTS.md` / `SECURITY.md` / `README.md` — hujjat yangilash

**`AGENTS.md`** — “Admin auth” bo‘limiga qo‘shing:

```markdown
- Rollar: `admin_users.role` — `super` (boshqa adminlarni boshqaradi) yoki `admin` (faqat kontent/inbox). `/api.php/admin/admins` endpoint'i `super` talab qiladi (`403 forbidden`). Oxirgi faol super adminni o'chirish/deaktiv qilish `422 last_super_admin` bilan bloklanadi. Rol o'zgarishi darhol kuchga kiradi — `admin_lookup_session()` har safar `admin_users` ga JOIN qiladi.
```

**`SECURITY.md`** — “Auth modeli” ga:

```markdown
- **RBAC**: `super` vs `admin`. `super` — `/admins` CRUD, `admin` — content + inbox. `admin_require_super($session)` mutatsiyalardan oldin chaqiriladi. Last-super guard `admin_has_other_active_super()`.
```

**`README.md`** — Admin panel bo‘limiga:

```markdown
Adminlar ikki rolda: **Super** (boshqa adminlarni qo'shadi/o'chiradi, rolni o'zgartiradi) va **Admin** (faqat profil, loyiha, inbox). Oxirgi faol super adminni o'chirib/deaktiv qilib bo'lmaydi.
```

---

## 7. Post-deploy checklist (role uchun)

| # | Tekshiruv | Kutilgan |
|---|---|---|
| 1 | `php -l backend/admin.php` | OK |
| 2 | `mysql ... < database/migrate-admin-role.sql` | bajarildi |
| 3 | `mysql -e "SHOW COLUMNS FROM admin_users LIKE 'role'"` | `role VARCHAR(20) default 'admin'` |
| 4 | Owner login → `/api.php/admin/session` | `admin.role = 'super'` |
| 5 | Dashboard → **Adminlar** tab ko‘rinadi | ✅ |
| 6 | Yangi admin qo‘shish (role=admin) | ✅ |
| 7 | Yangi admin login → **Adminlar** tab **yo‘q** | ✅ |
| 8 | Yangi admin `curl /api.php/admin/admins` | `403 forbidden` |
| 9 | Super admin o‘zini `admin`ga tushirishga urinish | `422 last_super_admin` |
| 10 | Super admin boshqa super qo‘shib, o‘zini `admin`ga tushirish | ✅ ruxsat |
| 11 | Oxirgi super adminni o‘chirishga urinish (boshqa super) | `422 last_super_admin` |
| 12 | Oxirgi super adminni o‘chirish (o‘zi) | `422 cannot_delete_self` (avvalroq) |
| 13 | Admin `is_active=0` → botda `/help` | **jim ignore** |
| 14 | `admin_audit_log` da `admin_created/updated/deleted` + `role` | ✅ |
| 15 | `git diff --check` | toza |

---

## 8. Umumiy tartib — endi qaysi fayllar o‘zgardimi

| Fayl | Holati |
|---|---|
| `backend/bot.php` | avvalgi diff (multi-admin) |
| `backend/contact.php` | `bot_notify_admins()` chaqirig‘i |
| **`backend/admin.php`** | role CRUD + guards |
| **`database/schema.sql`** | `role` ustuni |
| **`database/migrate-admin-role.sql`** | yangi fayl |
| **`components/admin/AdminManager.jsx`** | to‘liq yangilandi |
| **`components/admin/Dashboard.jsx`** | `isSuper` + tab yashirish |
| **`components/admin/api.js`** | yangi error code’lar |
| **`AGENTS.md` / `SECURITY.md` / `README.md`** | hujjat |

Barcha o‘zgarishlar `git add` → `npm run check` → `npm run build` → tar-deploy → `database/migrate-admin-role.sql` → owner login testi → bot smoke test tartibida bajarilishi kerak.

# `SECURITY.md` — yangilangan bo‘limlar

Quyida `SECURITY.md` ning **“Auth modeli”** va **“Test rejasi”** bo‘limlari to‘liq qayta yozilgan. Mavjud boshqa bo‘limlarga (xavfsizlik nazoratlari, fayl ruxsatlari, incident response) tegilmagan — ularni o‘zgartirmasdan saqlang. Faqat ikkita bo‘lim almashtiriladi.

> **Asos:** 2026-10-23 holati — `admin_users.role` (`super`/`admin`), `bot_find_admin()` multi-admin ruxsat, `bot_notify_admins()`, `admin_require_super()` va `last_super_admin` guard joriy etilgan.

---

## `## Auth modeli` — to‘liq yangi mazmun

````markdown
## Auth modeli (2026-10-23 holati)

### 1.1. Umumiy oqim

```
[Foydalanuvchi] → /admin/
   ↓ (Telegram Login Widget, HTTPS, BotFather /setdomain)
GET /api.php/admin/auth/telegram?hash=...&auth_date=...&id=...
   ↓ HMAC-SHA256 (bot token) + hash_equals() + auth_date ≤ 300s
   ↓ admin_users.telegram_id allowlist (is_active = 1)
   ↓ Agar jadval bo'sh va id == config.telegram_id → bootstrap INSERT (role='super')
   ↓ Rate limit: 15 daqiqada 3 challenge, 5 start
INSERT admin_login_challenges (challenge_hash, code_hash=HMAC(otp), expires_at=+600s)
   ↓ admin_send_login_code() → emailga 6 xonali kod (10 daqiqa amal qiladi)
POST /api.php/admin/auth/verify { code }   (CSRF yo'q — challenge cookie o'zi token)
   ↓ FOR UPDATE lock, attempts ≤ 5, consumed_at bir martalik
INSERT admin_sessions (token_hash = HMAC(session), expires_at = +43200s)
   ↓ admin_session cookie: HttpOnly, Secure, SameSite=Strict, Path=/
   ↓ admin_challenge cookie tozalanadi
GET /api.php/admin/session
   ↓ admin_lookup_session(): JOIN admin_users, a.is_active=1
   ↓ javob: {admin:{id, telegram_id, email, display_name, role}, csrf_token, expires_at}
POST/PUT/DELETE /api.php/admin/*
   ↓ X-CSRF-Token (HMAC('csrf:'+session_token, APP_SECRET)) majburiy
   ↓ Origin/Referer strict scheme+host+port; Origin:null rad; header'siz CLI → X-Requested-With: XMLHttpRequest
   ↓ (faqat /admins* endpoint uchun) admin_require_super($session)
OK
```

**Muhim:** `admin_lookup_session()` **har bir so‘rovda** `admin_users` jadvaliga JOIN qiladi va `a.is_active = 1` ni tekshiradi. Demak:

- `is_active = 0` qilinishi **darhol** session’ni bekor qiladi (keyingi so‘rov 401 `auth_required`).
- `role` o‘zgarishi **darhol** kuchga kiradi — super’dan admin’ga tushirilgan foydalanuvchi keyingi so‘rovda `/admins` da `403 forbidden` oladi.
- Sessiya `admin_sessions` da qolsa ham, `admin_users` dan uziladi.

### 1.2. Rollar

`admin_users.role VARCHAR(20) NOT NULL DEFAULT 'admin'` — qiymat `super` yoki `admin`.

| Rol | Kim | Web admin | Bot | `/api.php/admin/admins*` | Oxirgi bo‘lishi mumkin |
|---|---|---|---|---|---|
| **`super`** | Owner, texnik admin | Barcha tablar | Barcha komandalar | GET/POST/PUT/DELETE | Kamida 1 ta faol super **doim** qolishi shart |
| **`admin`** | Content operator, yordamchi | Profil, loyiha, inbox (Adminlar tab ko‘rinmaydi) | Barcha komandalar | **`403 forbidden`** | — |

**Bot ikkala rolga ham bir xil ruxsat beradi** — `bot_find_admin()` faqat `is_active=1` ni tekshiradi, `role` ni emas. Sabab: bot — email bridge; unga qo‘shimcha role check qo‘shilsa, adminlar foydalana olmaydi. Super/admin farqi faqat **web boshqaruv** uchun.

### 1.3. Bootstrap va super admin kafolati

1. `admin_users` **bo‘sh** bo‘lsa va foydalanuvchi `config.telegram_id` bilan kelsa — `admin_find_or_bootstrap_user()` `INSERT ... role='super'` qiladi. Bu birinchi super admin.
2. `database/migrate-admin-role.sql` eski bazada:
   - `role` ustunini `'admin'` default bilan qo‘shadi.
   - Eng kichik `id` li adminni **bir marta** `role='super'` qilib belgilaydi (faqat hech qanday super bo‘lmasa).
3. **Safety net** (`admin_find_or_bootstrap_user()` oxirida): agar config telegram_id bilan kirgan foydalanuvchi `role != 'super'` bo‘lsa va bazada **birorta faol super yo‘q** bo‘lsa — shu foydalanuvchi avtomatik `super` ga ko‘tariladi.
   - Natijada migration’dan keyin panelga qulflanib qolish holati bo‘lmaydi.
   - Audit logga `role` o‘zgarishi yozilmaydi (chunki `admin_audit_log` dan oldin sodir bo‘ladi); buni `error_log()` orqali kuzatish kerak.

### 1.4. Guard funksiyalari

| Guard | Fayl | Kod | Natija |
|---|---|---|---|
| CSRF | `backend/admin.php` | `admin_require_csrf()` | `403 csrf_failed` |
| Origin | `backend/admin.php` | `admin_check_origin()` | `403 origin_rejected` |
| Auth | `backend/admin.php` | `admin_require_session()` | `401 auth_required` |
| **Super** | `backend/admin.php` | `admin_require_super($session)` | `403 forbidden` |
| **Self-delete** | `backend/admin.php` | `admin_handle_admin_item()` | `422 cannot_delete_self` |
| **Self-deactivate** | `backend/admin.php` | `admin_handle_admin_item()` | `422 cannot_deactivate_self` |
| **Last admin** | `backend/admin.php` | `admin_active_count($pdo, $id)` | `422 last_admin` |
| **Last super** | `backend/admin.php` | `admin_has_other_active_super($pdo, $id)` | `422 last_super_admin` |

**`last_super_admin` sharti:**
```php
if ($current['role'] === 'super'
    && (!$admin['is_active'] || $admin['role'] !== 'super')
    && !admin_has_other_active_super($pdo, $id)) {
    admin_fail('At least one active super admin is required', 'last_super_admin', 422);
}
```

Ya’ni: super’ni **deaktiv** qilish, **admin’ga tushirish** yoki **o‘chirish** — agar bu oxirgi faol super bo‘lsa, bloklanadi.

### 1.5. Cookie’lar

| Cookie | HttpOnly | Secure | SameSite | Max-Age | Path |
|---|---|---|---|---|---|
| `admin_session` | ✅ | HTTPS da | `Strict` | `session_ttl` (default 43200s) | `/` |
| `admin_challenge` | ✅ | HTTPS da | `Lax` | `challenge_ttl` (default 600s) | `/` |

`Secure` faqat `admin_is_https()` rost bo‘lganda qo‘shiladi. `trust_proxy` config bilan reverse proxy orqasida `X-Forwarded-Proto: https` ga ishonish mumkin.

### 1.6. CSRF token

- Formula: `HMAC-SHA256('csrf:' + session_token, APP_SECRET)`.
- Session endpoint (`GET /api.php/admin/session`) qaytaradi.
- Har bir mutatsiyada `X-CSRF-Token` header majburiy.
- `admin_require_csrf()` **avval** `admin_check_origin()` ni chaqiradi — origin noto‘g‘ri bo‘lsa CSRF tekshirilmasdan `403 origin_rejected`.

### 1.7. Session tekshiruvi

- `admin_lookup_session()` har so‘rovda `admin_sessions JOIN admin_users` — `is_active=1` va `expires_at > UTC_TIMESTAMP()`.
- Sliding window: `last_seen_at` yangilanadi, lekin `expires_at` **uzaytirilmaydi** (qat’iy 12 soat).
- Tokenlar HMAC bilan saqlanadi (`HMAC('session:'+token)`) — DB sizib chiqsa ham raw token yo‘q.
- `admin_cleanup()` opportunistik: `random_int(1,10) === 1` bo‘lsa, 1 kundan eski session/challenge va 7 kundan eski `admin_auth_events` o‘chiriladi.

### 1.8. Rate limitlar (auth)

| Amal | Limit | Oyna | Manba |
|---|---|---|---|
| Challenge yaratish (admin uchun) | 3 | 15 daqiqa | `admin_login_challenges` COUNT |
| Telegram start (telegram_id uchun) | 5 | 15 daqiqa | `admin_auth_events` `challenge_created` |
| OTP urinish | 5 | challenge TTL (10 daqiqa) | `admin_login_challenges.attempts` |
| OTP qayta yuborish | 3 challenge/15 daq | 15 daqiqa | `admin_create_challenge()` |

### 1.9. Email OTP

- 6 xonali (`random_int(100000, 999999)`).
- `code_hash = HMAC('otp:' + challenge_token + ':' + code, APP_SECRET)` — DB’da raw kod yo‘q.
- `consumed_at` bir martalik: `UPDATE ... WHERE consumed_at IS NULL` `rowCount() === 1` tekshiriladi.
- `attempts` **FOR UPDATE** lock bilan inkrement — race condition yo‘q.
- Yangi challenge eski challenge’larni `consumed_at` bilan bekor qiladi.
- Kodni **log’ga yozmaydi**; `log` driver faqat `APP_ENV != production` da ishlaydi.

### 1.10. Telegram Login Widget

- `admin_telegram_validate()` — `hash_equals(HMAC-SHA256(data_string, SHA256(bot_token)))`.
- `auth_date` oynasi: `now - 300 ≤ auth_date ≤ now + 30`.
- `id` faqat raqamlar, 5–20 belgi.
- BotFather’da `/setdomain otaboyev-sardorbek.uz` bo‘lmaguncha widget HTTPS’da ishlamaydi.
- Widget **lokal HTTP’da ishlamaydi** — faqat production yoki real HTTPS tunnel orqali.

### 1.11. Bot autentifikatsiyasi (yangi)

Bot endi **multi-admin**:

```
POST /api.php/bot/webhook
   ↓ X-Telegram-Bot-Api-Secret-Token == config.bot.webhook_secret (≥32 belgi, hash_equals)
   ↓ Raw body ≤ 131072 bayt (aks holda 413)
   ↓ JSON decode
   ↓ update_id > 0 → bot_is_duplicate_update()
       INSERT IGNORE INTO bot_updates_seen (update_id, seen_at=NOW)
       rowCount() === 0 → takroriy, 200 'ok' qaytariladi va chiqiladi
   ↓ message.from.id == message.chat.id (private chat)
   ↓ bot_find_admin(message.from.id):
       JOIN admin_users WHERE telegram_id = :tg AND is_active = 1
       topilmasa → fallback: config.telegram_id (faqat jadval bo'sh yoki yo'q)
       topilmasa → **jim ignore** (200 'ok', Telegram'ga javob yo'q)
   ↓ bot_rate_limit_ok(telegram_id, command, maxPerMinute):
       bot_rate_limit jadvalida 1 daqiqada COUNT < limit
       muvaffaqiyatli bo'lsa INSERT; aks holda false
   ↓ handler (/help, /whoami, /messages, /unread, /message, /reply, /stats,
              /inbox, /read, /send)
   ↓ bot_audit($admin, action, details) → admin_audit_log
   ↓ 200 'ok'
```

**Asosiy farqlar eski holatdan:**

1. **`config.telegram_id` ga hard-code yo‘q** — `admin_users` dan olinadi.
2. **`is_active = 0`** qilingan admin **darhol** botdan foydalana olmaydi (keyingi xabar bilan).
3. **`role` botni cheklamaydi** — super ham, admin ham bir xil foydalanadi.
4. **Rate limit per-telegram_id** (`bot_rate_limit.admin_id` = telegram_id, `admin_users.id` emas) — bootstrap rejimida ham ishlaydi.
5. **Idempotent `update_id`** — Telegram retry’da `/send` ikki marta bajarilmaydi.
6. **`bot_audit()`** — har bir `message_read`, `message_reply`, `email_sent` `admin_audit_log` da (`entity='bot'`).
7. **Ruxsatsiz userga javob yo‘q** — botning mavjudligi leak bo‘lmaydi.
8. **Group chat’lar rad etiladi** — `chat.type != 'private'`.
9. **`chat.id === from.id`** tekshiriladi — chalg‘ituvchi update’lar rad etiladi.

### 1.12. Bot command ruxsatlari

| Komanda | Ruxsat | Rate limit (1 daqiqa) |
|---|---|---|
| `/start`, `/help` | Har qanday faol admin | yo‘q |
| `/whoami` | Har qanday faol admin | yo‘q |
| `/messages`, `/msgs` | Har qanday faol admin | 20 |
| `/unread` | Har qanday faol admin | 20 |
| `/message <id>` | Har qanday faol admin | 30 |
| `/reply <id> \| <matn>` | Har qanday faol admin | 10 |
| `/stats` | Har qanday faol admin | yo‘q |
| `/inbox`, `/mail` (IMAP) | Har qanday faol admin | 10 |
| `/read <uid>` (IMAP) | Har qanday faol admin | 20 |
| `/send <email> \| <mavzu> \| <matn>` | Har qanday faol admin | 10 |

Boshqa matn — `/help` qaytaradi. `strlen > 12000` — rad.

### 1.13. Contact formadan adminlarga xabar (yangi)

`contact_native_submit()` oxirida (faqat muvaffaqiyatli `contact_store_message()` dan keyin):

```php
require_once __DIR__ . '/bot.php';
bot_notify_admins(
    "📩 Yangi xabar\nKimdan: $name <$email>\nMavzu: $subject\n\n"
    . mb_substr($message, 0, 500, 'UTF-8')
);
```

- `bot_notify_admins()` — `admin_users WHERE is_active=1` barcha telegram_id ga xabar yuboradi.
- **Best-effort**: bot API fail bo‘lsa contact forma muvaffaqiyatli tugaydi (`try/catch`, `error_log`).
- DB yo‘q bo‘lsa jim o‘tadi.
- Adminlarning umumiy chatiga emas, **har biriga alohida** yuboradi.

### 1.14. Audit

`admin_audit_log` da yoziladi:

| Amal | Manba | `entity` | `details` |
|---|---|---|---|
| `login`, `logout` | `admin.php` | `admin` | — |
| `login_code_resent` | `admin.php` | `admin` | — |
| `profile_updated` | `admin.php` | `profile` | — |
| `project_created/updated/deleted` | `admin.php` | `project` | `slug` |
| `message_read/unread/deleted` | `admin.php` | `contact_message` | — |
| `sitemap_generated` | `admin.php` | `sitemap` | `urls` |
| `admin_created/updated/deleted` | `admin.php` | `admin_user` | `telegram_id`, `role`, `is_active` |
| `bot_message_read` | `bot.php` | `bot` | `message_id` |
| `bot_message_reply` | `bot.php` | `bot` | `message_id`, `to` |
| `bot_email_sent` | `bot.php` | `bot` | `to` |

IP **hash** (HMAC `APP_SECRET`) — PII emas. Admin ID FK `ON DELETE SET NULL` — admin o‘chirilsa tarix qoladi.

### 1.15. Auth’ni o‘chiruvchi holatlar (`enabled: false`)

`admin_config()['enabled']` false bo‘ladi, agar:

- `SITE_URL` HTTPS emas (production’da).
- `app_secret` 64 hex emas.
- `mail` driver sozlanmagan yoki `production` da `log` driver.
- `telegram_bot_token` formati noto‘g‘ri.
- `telegram_bot_username` formati noto‘g‘ri.
- `telegram_id` 5–20 raqam emas.
- `email` `FILTER_VALIDATE_EMAIL` fail.

Natijada: `/admin/` da “Auth sozlanmagan” ko‘rsatiladi va login ishlamaydi. **Bu silent fail emas** — UI ko‘rsatadi.

### 1.16. Hujjat va kod mosligi

| Nima | Manba |
|---|---|
| Rollar, guard funksiyalari | `backend/admin.php` |
| Bot auth + rate limit | `backend/bot.php` |
| Bootstrap/super safety net | `admin_find_or_bootstrap_user()` |
| Migration | `database/migrate-admin-role.sql` |
| Bot guard jadvallari | `database/migrate-bot-guards.sql` |
| Error code xaritasi | `components/admin/api.js` |
````

---

## `## Test rejasi` — to‘liq yangi mazmun

````markdown
## Test rejasi (2026-10-23)

> Har bir test **mustaqil** bajarilishi mumkin. Lokal: `./run_local.sh` (`APP_ENV=local`, `127.0.0.1:8000`).
> Production smoke: real HTTPS, real DB, real bot. `X-Api-Token`, `TELEGRAM_BOT_TOKEN`, `APP_SECRET` **hech qachon** commit/log/echo qilinmaydi.

### 2.1. Tayyorgarlik

**Preconditions:**

```bash
# DB
mysql -u USER -p DB_NAME < database/schema.sql
mysql -u USER -p DB_NAME < database/seed.sql
mysql -u USER -p DB_NAME < database/migrate-admin-role.sql    # faqat eski baza
mysql -u USER -p DB_NAME < database/migrate-bot-guards.sql    # idempotent

# Sanity
mysql -u USER -p DB_NAME -e "SHOW COLUMNS FROM admin_users LIKE 'role';"
# kutilgan: role VARCHAR(20) NOT NULL DEFAULT 'admin'

mysql -u USER -p DB_NAME -e "SELECT id, telegram_id, role, is_active FROM admin_users;"
# kutilgan: kamida 1 ta super, is_active=1
```

**Test adminlar:**

| # | telegram_id | role | is_active | Maqsad |
|---|---|---|---|---|
| A | (owner) | super | 1 | Asosiy |
| B | (yangi) | admin | 1 | Cheklangan |
| C | (yangi) | admin | 0 | Faolsiz |
| D | (yangi) | super | 1 | Ikkinchi super (last-super testi uchun vaqtincha) |

**Lokal `config.local.php`** (gitignored, `600`):

```php
'admin' => array(
    'site_url' => 'http://127.0.0.1:8000',
    'app_env'  => 'local',
    'app_secret' => '<64-hex>',
    'telegram_bot_token' => '<format-valid>',
    'telegram_bot_username' => 'test_bot',
    'telegram_id' => '<owner-id>',
    'email' => 'owner@example.test',
),
'mail' => array('driver' => 'log', 'log_file' => '/tmp/otaboyev-mail.log', 'from' => 'no-reply@example.test'),
'bot' => array(
    'webhook_secret' => str_repeat('a', 32),
    'imap_host' => '', 'imap_port' => 993,
    'imap_user' => '', 'imap_pass' => '', 'imap_ssl' => true,
),
```

### 2.2. Web auth — asosiy oqim

| # | Test | Kutilgan |
|---|---|---|
| 2.2.1 | `GET /api.php/admin/config` | `{enabled:true, bot_username, auth_url}` |
| 2.2.2 | `/admin/` ni ochish | Telegram widget render bo‘ladi |
| 2.2.3 | Widget bilan login (A) | `302 /admin/verify/` + `admin_challenge` cookie |
| 2.2.4 | OTP email (`log` driver) `/tmp/otaboyev-mail.log` | 6 raqamli kod |
| 2.2.5 | `POST /api.php/admin/auth/verify {code}` | `200 {data:{redirect:'/admin/dashboard/'}}` + `admin_session` cookie |
| 2.2.6 | `GET /api.php/admin/session` | `admin.role='super'` |
| 2.2.7 | `GET /api.php/admin/session?optional=1` (session yo‘q) | `200 {authenticated:false}` |
| 2.2.8 | `GET /api.php/admin/session` (session yo‘q) | `401 auth_required` |
| 2.2.9 | `POST /api.php/admin/logout` (CSRF bilan) | `200` + `admin_session` tozalanadi |
| 2.2.10 | `POST /api.php/admin/logout` (CSRF siz) | `403 csrf_failed` |

### 2.3. Web auth — salbiy testlar

| # | Test | Kutilgan |
|---|---|---|
| 2.3.1 | `GET /admin/auth/telegram?hash=bad` | `302 /admin/?error=telegram` |
| 2.3.2 | `auth_date` 400s oldin | `302 /admin/?error=expired` |
| 2.3.3 | Ruxsatsiz telegram_id | `302 /admin/?error=unauthorized` |
| 2.3.4 | 15 daq ichida 4-chi challenge | `302 /admin/?error=rate` |
| 2.3.5 | Noto‘g‘ri OTP kod | `401 invalid_code`, `attempts` +1 |
| 2.3.6 | 5 marta noto‘g‘ri OTP → 6-chi urinish | `429 too_many_attempts`, challenge `consumed_at` |
| 2.3.7 | Muddati o‘tgan challenge (11 daq) | `401 challenge_expired` |
| 2.3.8 | Bir OTP ikki marta | 2-chi `401 challenge_expired` yoki `invalid_code` |
| 2.3.9 | `Origin: null` bilan `POST` | `403 origin_rejected` |
| 2.3.10 | `Origin: https://evil.com` bilan `POST` | `403 origin_rejected` |
| 2.3.11 | Header’siz CLI `POST` (CSRF token bilan) | `403 origin_rejected` |
| 2.3.12 | Header’siz CLI `POST` + `X-Requested-With: XMLHttpRequest` + CSRF | `200` |
| 2.3.13 | `admin_challenge` cookieni o‘chirib verify | `401 challenge_expired` |
| 2.3.14 | `admin_session` cookieni o‘chirib sessiya | `401 auth_required` |

### 2.4. Role va ruxsatlar — super

| # | Test | Kutilgan |
|---|---|---|
| 2.4.1 | A (super) bilan dashboard ochish | **Adminlar** tab ko‘rinadi |
| 2.4.2 | `GET /api.php/admin/admins` (super) | `200 [...]` har birida `role` |
| 2.4.3 | `POST /api.php/admin/admins` (yangi admin, role=admin) | `201`, `role='admin'` |
| 2.4.4 | `POST /api.php/admin/admins` (yangi super) | `201`, `role='super'` |
| 2.4.5 | `PUT /api.php/admin/admins/{id}` (admin → super) | `200`, `role='super'` |
| 2.4.6 | `DELETE /api.php/admin/admins/{id}` (boshqa admin) | `200` |
| 2.4.7 | `POST /api.php/admin/admins` (noto‘g‘ri role=`owner`) | `422 validation_failed`, `errors.role` |
| 2.4.8 | A o‘zini `is_active=0` qilish | `422 cannot_deactivate_self` |
| 2.4.9 | A o‘zini o‘chirish | `422 cannot_delete_self` |
| 2.4.10 | A o‘zini `role='admin'` ga tushirish (boshqa super yo‘q) | `422 last_super_admin` |
| 2.4.11 | A o‘zini `role='admin'` ga tushirish (D super faol) | `200` |
| 2.4.12 | D’ni o‘chirish (oxirgi faol super) | `422 last_super_admin` |
| 2.4.13 | D’ni deaktiv qilish (oxirgi faol super) | `422 last_super_admin` |
| 2.4.14 | `admin_audit_log` oxirgi 5 yozuv | `admin_created/updated/deleted` `role` bilan |

### 2.5. Role va ruxsatlar — admin

| # | Test | Kutilgan |
|---|---|---|
| 2.5.1 | B (admin) bilan login | `200`, `admin.role='admin'` |
| 2.5.2 | B dashboard → **Adminlar** tab | **Ko‘rinmaydi** |
| 2.5.3 | B `GET /api.php/admin/admins` | `403 forbidden` |
| 2.5.4 | B `POST /api.php/admin/admins` (o‘ziga super) | `403 forbidden` |
| 2.5.5 | B `DELETE /api.php/admin/admins/{A}` | `403 forbidden` |
| 2.5.6 | B `GET /api.php/admin/profile` | `200` (ruxsat) |
| 2.5.7 | B `PUT /api.php/admin/profile` (CSRF bilan) | `200` (ruxsat) |
| 2.5.8 | B `POST /api.php/admin/projects` | `201` (ruxsat) |
| 2.5.9 | B `GET /api.php/admin/messages` | `200` (ruxsat) |
| 2.5.10 | B `POST /api.php/admin/sitemap` | `200` (ruxsat) |

### 2.6. Session va role invalidation (eng kritik)

| # | Test | Kutilgan |
|---|---|---|
| 2.6.1 | A login (super), B login (admin) | ikkala session faol |
| 2.6.2 | A → B ni `role='super'` ga ko‘tarish | B keyingi so‘rovda `/admins` ga `200` (darhol) |
| 2.6.3 | A → B ni `role='admin'` ga tushirish | B keyingi so‘rovda `/admins` ga `403` (darhol) |
| 2.6.4 | A → B ni `is_active=0` qilish | B keyingi so‘rovda `401 auth_required` (darhol) |
| 2.6.5 | A → B ni qayta `is_active=1` qilish | B eski session bilan **ishlamaydi**; qayta login kerak |
| 2.6.6 | A → B ni `DELETE` | B session `401` |

> **Kalit**: `admin_lookup_session()` har so‘rovda JOIN qiladi, shuning uchun invalidation **darhol**. Buni test qilish **shart**.

### 2.7. Bot — asosiy oqim

**Fake update skript** (lokal, Telegram’siz):

```bash
send_update() {
  local uid=$1; local tg=$2; local text=$3
  curl -sS -X POST "http://127.0.0.1:8000/api.php/bot/webhook" \
    -H "X-Telegram-Bot-Api-Secret-Token: aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa" \
    -H "Content-Type: application/json" \
    --data "{
      \"update_id\": $uid,
      \"message\": {
        \"message_id\": $uid,
        \"from\": {\"id\": $tg},
        \"chat\": {\"id\": $tg, \"type\": \"private\"},
        \"text\": \"$text\"
      }
    }"
}
```

| # | Test | Kutilgan |
|---|---|---|
| 2.7.1 | `GET /api.php/bot/webhook` | `405`, `Allow: POST` |
| 2.7.2 | `POST` secretsiz | `403` |
| 2.7.3 | `POST` noto‘g‘ri secret | `403` |
| 2.7.4 | `POST` secret to‘g‘ri, `from.id != admin_id` | `200 ok`, Telegram’ga hech narsa yuborilmaydi |
| 2.7.5 | `POST` secret to‘g‘ri, `from.id == admin_id`, `/help` | `200 ok`, `bot_help_text()` qaytadi |
| 2.7.6 | `POST` secret to‘g‘ri, `chat.type='group'` | `200 ok`, jim ignore |
| 2.7.7 | `POST` secret to‘g‘ri, `chat.id != from.id` | `200 ok`, jim ignore |
| 2.7.8 | Body > 131072 bayt | `413` |
| 2.7.9 | Bir xil `update_id` ikki marta | 1-chi `200`, 2-chi `200` lekin **amal bajarilmaydi**; `bot_updates_seen` da 1 qator |
| 2.7.10 | `bot_rate_limit_ok(<tg>, 'reply', 10)` 11-chi chaqiriq | `false` |
| 2.7.11 | `admin_audit_log` oxirgi yozuv | `entity='bot'` bo‘lgan yozuv |

### 2.8. Bot — multi-admin invalidation

| # | Test | Kutilgan |
|---|---|---|
| 2.8.1 | B (admin, faol) `/help` | `200 ok`, javob keladi |
| 2.8.2 | A → B ni `is_active=0` | ✅ |
| 2.8.3 | B `/help` (keyingi update) | `200 ok`, **javob yo‘q** (silent) |
| 2.8.4 | A → B ni `is_active=1` | ✅ |
| 2.8.5 | B `/help` | yana javob keladi |
| 2.8.6 | A → B `DELETE` | ✅ |
| 2.8.7 | B `/help` | **jim ignore** |
| 2.8.8 | D (2-super, faol) `/whoami` | `200`, `Super` rolida ham ishlaydi |
| 2.8.9 | `admin_users` bo‘sh + `config.telegram_id` bilan `/help` | bootstrap: javob keladi, `admin_users` da 1 row `role='super'` |

### 2.9. Bot — komandalar (faol admin)

| # | Komanda | Kutilgan |
|---|---|---|
| 2.9.1 | `/whoami` | `ism` + `telegram_id` + `email` |
| 2.9.2 | `/messages` (bo‘sh inbox) | `Contact inbox bo'sh.` |
| 2.9.3 | Contact formadan xabar yuborib, `/messages` | ro‘yxatda `#id` |
| 2.9.4 | `/message <id>` | to‘liq xabar; DB’da `is_read=1` |
| 2.9.5 | `/unread` (barchasi o‘qilgan) | `O'qilmagan xabar yo'q.` |
| 2.9.6 | Contact forma → `/unread` | 1+ xabar |
| 2.9.7 | `/reply <id> \| test javob` | `200`, foydalanuvchiga email ketadi (`log` driver → log fayl) |
| 2.9.8 | `/reply 999999 \| test` (yo‘q id) | `Xabar yoki email topilmadi.` |
| 2.9.9 | `/reply <id>` (pipe’siz) | `Format: /reply <id> \| <matn>` |
| 2.9.10 | `/stats` | `unread`, `total`, `faol adminlar` |
| 2.9.11 | `/inbox` (IMAP sozlanmagan) | `Email qutisiga ulanib bo'lmadi.` |
| 2.9.12 | `/send email@example.com \| Mavzu \| Matn` | `Email yuborildi: ...`; `admin_audit_log` da `bot_email_sent` |
| 2.9.13 | `/send not-an-email \| M \| B` | `Email yuborilmadi: Invalid email address` |
| 2.9.14 | Noma’lum komanda `/foo` | `/help` qaytadi |

### 2.10. Contact forma → adminlarga Telegram

| # | Test | Kutilgan |
|---|---|---|
| 2.10.1 | Contact forma (valid) | `200`, `contact_messages` da 1 row |
| 2.10.2 | A va B (ikkalasi faol) | **ikkalasiga** Telegram xabar (fake update logida 2 ta `sendMessage`) |
| 2.10.3 | B `is_active=0` qilingan | faqat A ga xabar |
| 2.10.4 | DB uzilgan | forma `503`; `bot_notify_admins()` jim fail (log) |
| 2.10.5 | Bot API fail (fake) | forma `200`; log’da `bot notify admins failed` |
| 2.10.6 | `website` honeypot to‘ldirilgan | `400`, Telegram xabar **yo‘q** |

### 2.11. Contact forma — xavfsizlik

| # | Test | Kutilgan |
|---|---|---|
| 2.11.1 | `form_contact[ed-f-2]='bad'` | `422` |
| 2.11.2 | `name` bo‘sh | `422` |
| 2.11.3 | `message` 60001 bayt | `422` |
| 2.11.4 | 5 ta xabar (10 daqiqa) | 6-chi `429` |
| 2.11.5 | 10 ta xabar (10 daqiqa, har xil form_id) | 11-chi `429` |
| 2.11.6 | `id=contact` bo‘lmagan form_id | Sitejet kontraktiga uzatiladi, rate limit baribir ishlaydi |

### 2.12. Bot — audit va loglar

| # | Test | Kutilgan |
|---|---|---|
| 2.12.1 | `/message 1` → `admin_audit_log` | `action='bot_message_read'`, `entity='bot'`, `details.message_id=1` |
| 2.12.2 | `/reply 1 \| test` | `action='bot_message_reply'`, `details.to` |
| 2.12.3 | `/send ...` | `action='bot_email_sent'`, `details.to` |
| 2.12.4 | Bot token logda | **yo‘q** |
| 2.12.5 | Webhook secret logda | **yo‘q** |
| 2.12.6 | OTP kod logda (production) | **yo‘q** |

### 2.13. Migration va idempotentlik

| # | Test | Kutilgan |
|---|---|---|
| 2.13.1 | `migrate-admin-role.sql` 1-marta | OK, `role` ustuni qo‘shildi |
| 2.13.2 | `migrate-admin-role.sql` 2-marta | **Xato**: `Duplicate column name 'role'` — bu kutilgan, idempotent emas |
| 2.13.3 | `migrate-bot-guards.sql` 1-marta | OK |
| 2.13.4 | `migrate-bot-guards.sql` 2-marta | OK (idempotent) |
| 2.13.5 | Eski bazada (role ustuni yo‘q) bootstrap login | A → `role='super'` avtomatik |
| 2.13.6 | Barcha adminlar `role='admin'`, config ID bilan login | safety net → `role='super'` |

### 2.14. Apache vs PHP built-in farqlari

`.htaccess` PHP built-in server’da **ishlamaydi**. Shuning uchun quyidagilar **faqat Apache’da** tekshiriladi:

| # | Test | Kutilgan |
|---|---|---|
| 2.14.1 | `GET /de/` | `404` + `404.html` |
| 2.14.2 | `GET /nope` | `404` + `404.html` |
| 2.14.3 | `GET /uz/projects/<admin-slug>/` | `200` (fallback rewrite) |
| 2.14.4 | `GET /config.local.php` | `403` |
| 2.14.5 | `GET /github_keys.txt` | `403` |
| 2.14.6 | `GET /backend/config.php` | `403` |
| 2.14.7 | `GET /database/schema.sql` | `403` |
| 2.14.8 | `GET /api.php/health` | `200 {"status":"ok"}` |
| 2.14.9 | `GET /api.php/admin/...` | `X-Robots-Tag: noindex` |
| 2.14.10 | `OPTIONS /api.php/profile` | `Access-Control-Allow-Origin: *` |
| 2.14.11 | `OPTIONS /api.php/form_container/submit` | **CORS header yo‘q** |
| 2.14.12 | `POST /api.php/admin/logout` `Origin: evil` | `403 origin_rejected`, CORS header yo‘q |
| 2.14.13 | `GET /api.php/profile` HTTP (HTTPS emas) | `Strict-Transport-Security` **yo‘q** |
| 2.14.14 | `GET /uz/` HTTPS | `Strict-Transport-Security: max-age=31536000` |

### 2.15. Build va artefakt tekshiruvi

```bash
npm run check
# kutilgan: barcha php -l, node --check, bash -n OK

npm run build
# kutilgan: xatosiz static export

test -f out/.htaccess && test -f out/api.php && test -f out/404.html && echo "OK"
test ! -f out/config.local.php && test ! -f out/config.local.js && test ! -f out/github_keys.txt && echo "secrets-free OK"
test -d out/backend && test -f out/backend/admin.php && test -f out/backend/bot.php && echo "backend OK"
test -d out/uz out/en out/ru && echo "locales OK"
test -d out/admin out/admin/verify out/admin/dashboard && echo "admin pages OK"
```

| # | Test | Kutilgan |
|---|---|---|
| 2.15.1 | `npm run check` | `0` exit |
| 2.15.2 | `npm run build` | `out/` yaratildi |
| 2.15.3 | `out/config.local.php` mavjudligi | **yo‘q** |
| 2.15.4 | `out/github_keys.txt` mavjudligi | **yo‘q** |
| 2.15.5 | `out/404.html` | mavjud |
| 2.15.6 | `out/.htaccess` | mavjud |
| 2.15.7 | CI (`push`/`PR`) | `npm ci`, `check`, `build`, artefakt tekshiruvi o‘tadi |

### 2.16. Production smoke (deploy’dan keyin, real muhit)

| # | Test | Kutilgan |
|---|---|---|
| 2.16.1 | `curl -sS https://otaboyev-sardorbek.uz/api.php/health` | `{"status":"ok"}` |
| 2.16.2 | `curl -sS https://otaboyev-sardorbek.uz/api.php/profile?lang=uz` | `200`, valid JSON |
| 2.16.3 | `/api.php/projects?lang=en` | `200`, massiv |
| 2.16.4 | `/api.php/projects/gitinsight?lang=ru` | `200`, `results[]` va `stack_notes[]` |
| 2.16.5 | `/uz/`, `/en/`, `/ru/` ochish | console/network xatosiz |
| 2.16.6 | `/admin/` → real Telegram Login | `302 /admin/verify/` |
| 2.16.7 | Real OTP email → login | session + `role='super'` |
| 2.16.8 | Super admin → yangi admin (role=admin) | saqlandi |
| 2.16.9 | Yangi admin bilan real login | `/admin/dashboard/`; Adminlar tab **yo‘q** |
| 2.16.10 | Yangi admin `GET /api.php/admin/admins` | `403 forbidden` |
| 2.16.11 | Contact forma (real) | `200`; A va B’ga Telegram xabar |
| 2.16.12 | Bot: A dan `/whoami`, `/messages`, `/stats` | javob keladi |
| 2.16.13 | Bot: B `is_active=0` → `/help` | **jim ignore** |
| 2.16.14 | `getWebhookInfo` | `pending_update_count=0` |
| 2.16.15 | `admin_audit_log` production DB’da | oxirgi yozuvlar to‘g‘ri `role` bilan |

### 2.17. Regression

| # | Test | Kutilgan |
|---|---|---|
| 2.17.1 | `/uz/` → til switcher → `/en/` | to‘g‘ri o‘tadi |
| 2.17.2 | `/en/projects/` → bitta karta → detail | fallback + API refresh |
| 2.17.3 | Admin yaratgan slug Apache’da | `200`, `view/?slug=...` |
| 2.17.4 | Contact forma honeypot | `400` |
| 2.17.5 | DB uzilgan → `/api.php/profile` | `503 db_unavailable`; sahifa statik fallback |
| 2.17.6 | Session `expires_at` o‘tgach | `401 auth_required` |
| 2.17.7 | CSP header HTML’da | `script-src` Telegram widget ruxsat beradi |
| 2.17.8 | `git diff --check` | toza |
| 2.17.9 | `git status` | faqat kutilgan fayllar |

### 2.18. Acceptance checklist

Deploy/merge’dan oldin:

- [ ] `npm run check` — `0`
- [ ] `npm run build` — `out/` toza, secret-free
- [ ] 2.2.1–2.2.10 (web auth asosiy) — ✅
- [ ] 2.3.1–2.3.14 (web auth salbiy) — ✅
- [ ] 2.4.1–2.4.14 (super ruxsat) — ✅
- [ ] 2.5.1–2.5.10 (admin ruxsat) — ✅
- [ ] **2.6.1–2.6.6 (invalidation) — ✅** (kritik)
- [ ] 2.7.1–2.7.11 (bot asosiy) — ✅
- [ ] **2.8.1–2.8.9 (bot multi-admin invalidation) — ✅** (kritik)
- [ ] 2.9.1–2.9.14 (bot komandalar) — ✅
- [ ] 2.10.1–2.10.6 (contact → Telegram) — ✅
- [ ] 2.11.1–2.11.6 (contact xavfsizlik) — ✅
- [ ] 2.12.1–2.12.6 (audit/loglar) — ✅
- [ ] 2.13.1–2.13.6 (migration) — ✅
- [ ] 2.14.1–2.14.14 (Apache) — ✅
- [ ] 2.15.1–2.15.7 (build/CI) — ✅
- [ ] 2.16.1–2.16.15 (production smoke) — ✅ (owner imzosi bilan)
- [ ] 2.17.1–2.17.9 (regression) — ✅
- [ ] `AGENTS.md`, `tz.md`, `tz_master.md`, `SECURITY.md`, `README.md` — yangilangan

### 2.19. Nima test qilinmaydi (operator mas’uliyati)

Quyidagilar **avtomatlashtirilmagan**, owner/operator qo‘lda bajaradi:

- BotFather `/setdomain otaboyev-sardorbek.uz` — real Telegram Login widget.
- Real Telegram akkaunt bilan `/help`, `/inbox`, `/read`, `/send` — IMAP va `mail()` haqiqiy yetkazilishi.
- DKIM imzolash va DMARC `p=quarantine` — Eskiz DNS yoki WHM orqali (blocker: external DNS).
- GitHub PAT revoke — https://github.com/settings/tokens.
- Google Maps API key rotate/restrict — Google Cloud Console.
- `APP_SECRET` va bot token rotation — `config.local.php` + BotFather `/revoke`.
- Logrotate, backup, monitoring — cPanel/ops.
````

---

## Qo‘shimcha: `SECURITY.md` ning boshqa bo‘limlariga kichik o‘zgarishlar

Yuqoridagi ikkita bo‘limni almashtirsangiz, quyidagi joylarni ham yangilash kerak (kichik teglar):

**“Top 20 himoya nazorati” jadvalida:**

```diff
-| 4 | Session fixation | Login muvaffaqiyatida 32 baytli yangi random session token; eski challenge bir martalik. |
+| 4 | Session fixation | Login muvaffaqiyatida 32 baytli yangi random session token; eski challenge bir martalik. Rol/is_active o'zgarishi keyingi so'rovda darhol kuchga kiradi (JOIN `admin_users`). |
```

```diff
 | 9 | Ruxsatsiz admin | `admin_users.is_active` + Telegram allowlist; begona Telegram akkaunt `unauthorized` ga yo‘naltiriladi. |
+| 9a | **Ruxsatsiz rol** | `admin_users.role` (`super`/`admin`). `admin_require_super($session)` mutatsiyalardan oldin; oxirgi faol super `422 last_super_admin` bilan himoyalanadi. |
```

**“cPanel checklist” ga:**

```diff
 - [x] MySQL `admin_users` jadvalida faqat kerakli admin bor; DB user least-privilege.
+- [x] `admin_users.role` (`super`/`admin`); kamida 1 faol super; `migrate-admin-role.sql` qo‘llangan.
+- [x] Bot `admin_users.is_active` ni dynamic tekshiradi (`bot_find_admin()`); `config.telegram_id` faqat bootstrap.
```

**“Zaifliklar reyestri” ga:**

```diff
 | V-22 | Bot faqat config telegram_id ga bog‘langan edi | ✅ **Fixed** | 2026-10-23 | `bot_find_admin()` `admin_users` dan is_active=1 ni tekshiradi |
+| V-23 | Web adminlar orasida rol farqi yo‘q edi | ✅ **Fixed** | 2026-10-23 | `role` (`super`/`admin`), `admin_require_super()`, `last_super_admin` guard |
+| V-24 | Contact formadan adminlarga real-time signal yo‘q edi | ✅ **Fixed** | 2026-10-23 | `bot_notify_admins()` har faol adminga Telegram xabar |
+| V-25 | Bot `update_id` idempotent emas edi | ✅ **Fixed** | 2026-10-22 | `bot_updates_seen` + `bot_is_duplicate_update()` |
```

**“O‘zgartirishlar tarixi” ga:**

```diff
 | **3.1** | **2026-10-22** | ... |
+| **3.2** | **2026-10-23** | **RBAC (`super`/`admin`) va bot multi-admin:** `admin_users.role`, `admin_require_super()`, `last_super_admin` guard, `bot_find_admin()`, `bot_notify_admins()`, yangi bot komandalar (`/whoami`, `/messages`, `/unread`, `/message`, `/reply`, `/stats`), `migrate-admin-role.sql`, `SECURITY.md` Auth modeli va Test rejasi qayta yozildi |
```

---

## Tekshirish

```bash
# SECURITY.md ni yangilaganingizdan keyin
grep -n "## Auth modeli" SECURITY.md
grep -n "## Test rejasi" SECURITY.md

# ikkalasi ham 1 martadan ko'rinishi kerak
# va ular orasidagi eski mazmun to'liq almashtirilganini vizual tekshiring

# Hujjat va kod mosligi:
grep -n "admin_require_super" backend/admin.php
grep -n "bot_find_admin" backend/bot.php
grep -n "last_super_admin" backend/admin.php components/admin/api.js
```
# `AGENTS.md` va `tz.md` — yangilangan bo‘limlar

Quyida `AGENTS.md` uchun ikkita bo‘lim (yangi `Arxitektura → Admin auth` va yangilangan `Admin auth va xavfsizlik`) hamda `tz.md` uchun `Backlog` (va unga bog‘liq Snapshot/Active work/Baseline/Next actions) mavjud. Uslub mavjud hujjat bilan bir xil — qisqa, jadval/bullet, texnik atamalar ingliz tilida.

---

## 1. `AGENTS.md` — `Arxitektura → Admin auth` (YANGI bo‘lim)

Bu bo‘limni `## Arxitektura` ostidagi `### Backend (PHP + MySQL)` dan keyin, `### Legacy va Sitejet qismi` dan oldin qo‘shing.

````markdown
### Admin auth (web + bot)

- **Web flow:** `/admin/` Telegram Login Widget → `backend/admin.php:admin_handle_telegram_callback()` HMAC (bot token) + `auth_date ≤ 300s` → `admin_users.telegram_id` allowlist (`is_active = 1`) → `challenge` cookie + email OTP (6 raqam, HMAC-hashed, 10 daqiqa, 5 urinish) → `admin_session` cookie (HttpOnly, Secure, SameSite=Strict, 12 soat). Mutatsiyalarda `X-CSRF-Token` va `admin_check_origin()` majburiy.
- **Rollar (`admin_users.role`):** `super` — boshqa adminlarni CRUD qiladi (`/api.php/admin/admins*`); `admin` — faqat profil/loyiha/inbox/sitemap. `admin_require_super($session)` `admin_handle_admins()` va `admin_handle_admin_item()` boshida chaqiriladi; super bo‘lmagan admin `403 forbidden` oladi. Adminlar tab UI’da `Dashboard.jsx:isSuper` bilan yashiriladi.
- **Super himoyasi:** oxirgi faol super’ni o‘chirish/deaktiv qilish/`admin`ga tushirish `422 last_super_admin` bilan bloklanadi (`admin_has_other_active_super($pdo, $id)`). `self-delete`/`self-deactivate` avvalroq `422 cannot_delete_self`/`cannot_deactivate_self` bilan bloklanadi.
- **Session invalidation:** `admin_lookup_session()` har so‘rovda `admin_sessions JOIN admin_users` — `a.is_active = 1` shart. `is_active=0`, `role` o‘zgarishi yoki `DELETE` — keyingi so‘rovda darhol `401 auth_required` / `403 forbidden`. Sessiya `expires_at` uzaytirilmaydi (sliding `last_seen_at` faqat audit).
- **Bootstrap:** `admin_users` bo‘sh bo‘lsa va `config.telegram_id` bilan kelinsa — `admin_find_or_bootstrap_user()` `role='super'` bilan birinchi adminni yaratadi. `admin_find_or_bootstrap_user()` oxirida **safety net**: config ID `role != 'super'` va bazada faol super yo‘q bo‘lsa — avtomatik `super` ga ko‘tariladi (migration’dan keyin qulflanishni oldini oladi).
- **Bot multi-admin:** `backend/bot.php:bot_find_admin($telegramId)` `admin_users.telegram_id` + `is_active = 1` ni tekshiradi. `config.telegram_id` faqat **bootstrap fallback** (jadval bo‘sh yoki DB yo‘q). Ruxsatsiz user uchun `200 'ok'` jim ignore — bot mavjudligi leak bo‘lmaydi. `role` botni cheklamaydi — super ham, admin ham bir xil `/help`, `/messages`, `/reply`, `/inbox`, `/send` ishlatadi.
- **Bot komandalar (2026-10-23):** `/start`, `/help`, `/whoami`, `/messages`, `/unread`, `/message <id>`, `/reply <id> | <matn>`, `/stats` (contact inbox); `/inbox`, `/read <uid>`, `/send <email> | <mavzu> | <matn>` (IMAP email). `setMyCommands` — `scripts/set-bot-commands.sh` orqali.
- **Bot rate limit:** `bot_rate_limit_ok($telegramId, $command, $maxPerMinute)` — `bot_rate_limit` jadvalida 1 daqiqada `COUNT < limit`; `telegram_id` bo‘yicha (bootstrap rejimida ham ishlaydi, `admin_users.id` emas). Limitlar: `/messages` 20/min, `/unread` 20/min, `/message` 30/min, `/reply` 10/min, `/inbox` 10/min, `/read` 20/min, `/send` 10/min.
- **Bot idempotency:** `update_id` `bot_updates_seen` da bir marta (`INSERT IGNORE`, `rowCount() === 0` → takroriy `200 'ok'`). Telegram retry’da `/send` ikki marta bajarilmaydi.
- **Contact → Telegram:** `backend/contact.php:contact_native_submit()` muvaffaqiyatli `contact_store_message()` dan keyin `bot_notify_admins()` chaqiradi — barcha `is_active=1` adminlarga xabar. **Best-effort**: bot API yoki DB fail bo‘lsa forma natijasiga ta’sir qilmaydi (`try/catch` + `error_log`).
- **Bot audit:** `bot_audit($admin, $action, $details)` — `/message` (read), `/reply`, `/send` `admin_audit_log` ga `entity='bot'` bilan yoziladi. Bootstrap holatda `admin_id = NULL`.
- **Guard jadvallari:** `bot_updates_seen`, `bot_rate_limit` — `database/migrate-bot-guards.sql` (idempotent). Jadval bo‘lmasa bot ishlaydi, lekin guard’lar o‘chiq (fail-open), `error_log()` ga yoziladi.
````

---

## 2. `AGENTS.md` — `## Admin auth va xavfsizlik` (yangilangan)

Bu bo‘limni to‘liq almashtiring. Eski mazmun asos qilib olinadi, yangi role/bot/contact elementlar qo‘shiladi.

````markdown
## Admin auth va xavfsizlik

- Flow: Telegram Login Widget → HMAC + `auth_date` tekshiruvi → challenge cookie → emailga 6 xonali OTP → session cookie.
- **Rollar:** `admin_users.role` — `super` (admin CRUD) yoki `admin` (content + inbox). `admin_require_super($session)` `POST/PUT/DELETE /api.php/admin/admins*` da majburiy. `Dashboard.jsx` super bo‘lmagan admin uchun “Adminlar” tab’ini yashiradi.
- **Super/self himoyasi:** oxirgi faol super’ni o‘chirish/deaktiv qilish/`admin`ga tushirish `422 last_super_admin`; o‘z hisobini o‘chirish `422 cannot_delete_self`; o‘zini deaktiv `422 cannot_deactivate_self`; oxirgi faol admin `422 last_admin`.
- `backend/admin.php` OTP’ni HMAC bilan hashlaydi; 6 raqam, 10 daqiqa, 5 urinish; challenge va session muddatlari cheklangan.
- Cookie’lar: `admin_session` HttpOnly, HTTPS’da Secure, `SameSite=Strict`; `admin_challenge` HttpOnly, `SameSite=Lax`. Rol/is_active o‘zgarishi keyingi so‘rovda darhol kuchga kiradi (`admin_lookup_session()` `admin_users` ga JOIN).
- Mutation’larda `X-CSRF-Token` majburiy; rate limit, `admin_auth_events` va `admin_audit_log` mavjud. `admin_check_origin()` `Origin`/`Referer`ni `site_url` bilan strict host/scheme/port bo‘yicha solishtiradi, `Origin: null`ni rad etadi, ikkala header bo‘lmasa `X-Requested-With: XMLHttpRequest` talab qiladi.
- `api.php` `display_errors`ni har doim `0` qiladi va xatolarni `log_errors` orqali server logiga yozadi; `APP_ENV` qiymati stack trace’ni qayta yoqmaydi.
- `APP_SECRET` aniq 64 hex bo‘lmasa, email driver sozlanmagan bo‘lsa, production `SITE_URL` HTTPS bo‘lmasa yoki Telegram bot token/username yoki numeric admin ID noto‘g‘ri bo‘lsa auth o‘chiq (`enabled:false`). `/admin/` UI buni “Auth sozlanmagan” sifatida ko‘rsatadi.
- **Bootstrap va safety net:** `admin_users` bo‘sh bo‘lsa birinchi login `config.telegram_id` `role='super'` bilan yaratiladi. `database/migrate-admin-role.sql` eng kichik `id` adminni bir marta super qiladi. `admin_find_or_bootstrap_user()` oxirida — config ID `role != 'super'` va faol super yo‘q bo‘lsa, avtomatik `super` ga ko‘tariladi.
- **Bot bridge (private multi-admin):** faqat `POST /api.php/bot/webhook`; `X-Telegram-Bot-Api-Secret-Token` (`hash_equals`, `strlen ≥ 32`) + `message.from.id == message.chat.id` + `chat.type == 'private'` + `bot_find_admin(from.id)` (`admin_users.telegram_id AND is_active = 1`). `config.telegram_id` faqat bootstrap. Ruxsatsiz user `200 'ok'` jim ignore. `role` botni cheklamaydi.
- **Bot guard’lar:** `bot_updates_seen` idempotency (`update_id` bir marta), `bot_rate_limit` (`/messages` 20, `/unread` 20, `/message` 30, `/reply` 10, `/inbox` 10, `/read` 20, `/send` 10 — 1 daqiqa). `admin_audit_log` da `entity='bot'` yozuvlari (`bot_message_read`, `bot_message_reply`, `bot_email_sent`). Bot token, OTP, app secret, IMAP/SMTP paroli hech qachon Telegram’ga yoki logga chiqmaydi.
- **Contact → admin Telegram:** `contact_native_submit()` → `bot_notify_admins($text)` — barcha faol adminlarga xabar; best-effort (forma muvaffaqiyatiga ta’sir qilmaydi). `website` honeypot va IP rate limit (5/10 daqiqa, 10 global) saqlanadi.
- Bot token, app secret yoki OTP’ni public JSON/logga chiqarmang. Admin response’larda wildcard CORS yo‘q; `entity='bot'` audit yozuvlarida `admin_id = NULL` faqat bootstrap holatda.
- Admin route’lar: `GET /api.php/admin/config`, `GET /api.php/admin/auth/telegram`, `GET /api.php/admin/auth/status`, `POST /api.php/admin/auth/verify`, `POST /api.php/admin/auth/resend`, `GET /api.php/admin/session[?optional=1]`, `POST /api.php/admin/logout`, `GET/PUT /api.php/admin/profile`, `GET/POST /api.php/admin/projects`, `GET/PUT/DELETE /api.php/admin/projects/{id}`, `GET /api.php/admin/messages[?count=1|unread=1]`, `GET/PUT/DELETE /api.php/admin/messages/{id}`, `POST /api.php/admin/sitemap`, `GET/POST /api.php/admin/admins`, `GET/PUT/DELETE /api.php/admin/admins/{id}`.
- `/admin/admins*` — super only. `admin_validate_admin()` `role in ('super','admin')` validatsiyasi. Inbox/sitemap/admin-user mutation’lari session + CSRF talab qiladi. Admin project slug’i `view` bo‘lishi mumkin emas (fallback route bilan to‘qnashadi). Sitemap yozilmasa `500 sitemap_write_failed`.
- **Error code xaritasi** (`components/admin/api.js`): `auth_required`, `csrf_failed`, `origin_rejected`, `forbidden` (rol), `cannot_delete_self`, `cannot_deactivate_self`, `last_admin`, `last_super_admin`, `admin_exists`, `admin_not_found`, `sitemap_write_failed`, `challenge_expired`, `too_many_attempts`, `invalid_code`, `email_failed`.
- Production cPanel’da `admin@otaboyev-sardorbek.uz` (OTP/inbox) va `no-reply@...` (yuborish) mailbox’lari, `contact@...` → `admin@...` forwarder’i ishlatiladi. `admin.phone` faqat config’da.
````

---

## 3. `tz.md` — `## Snapshot` (kichik yangilash)

Mavjud snapshot’ga faqat 2-3 bullet qo‘shiladi:

```diff
 - Auth: Telegram Login Widget → email OTP → session with CSRF; admin routes are noindex and have no wildcard CORS. Production `config.local.php`, cPanel DB, mailboxes, bot, `SITE_URL`, admin Telegram ID and `APP_SECRET` are configured and smoke-tested.
+- **RBAC (2026-10-23):** `admin_users.role` — `super` (admin CRUD) va `admin` (content + inbox). `admin_require_super()` `/api.php/admin/admins*` da; oxirgi faol super `422 last_super_admin`. Rol/is_active o‘zgarishi keyingi so‘rovda darhol kuchga kiradi (`admin_lookup_session()` JOIN).
 - Bot: private `backend/bot.php` webhook bridge with a secret header and numeric-admin allowlist; `/help`, `/inbox`, `/read` and `/send` work against cPanel IMAP/`mail()`. Real inbound Telegram message from the admin still needs a manual test.
+- **Bot multi-admin (2026-10-23):** `bot_find_admin()` `admin_users.telegram_id` + `is_active=1` ni tekshiradi; `config.telegram_id` faqat bootstrap. Yangi komandalar: `/whoami`, `/messages`, `/unread`, `/message <id>`, `/reply <id> | <matn>`, `/stats`. `contact_native_submit()` → `bot_notify_admins()` har faol adminga xabar (best-effort).
```

---

## 4. `tz.md` — `## Active work` (yangilangan)

```markdown
## Active work

Production DB/mailbox/bot/admin configuration is complete and smoke-tested. **2026-10-23 RBAC + bot multi-admin** joriy etilgan; hozirgi ish:

1. **Deploy:** `database/migrate-admin-role.sql` production DB’ga bir marta import; `npm ci && npm run build` Node 22’da; tar-deploy `config.local.php` va `.well-known/` saqlab; super-admin role (`SELECT id, telegram_id, role, is_active FROM admin_users;`) tekshirish.
2. **Bot smoke (real):** `scripts/set-bot-commands.sh` bilan `setMyCommands`; real Telegram admin akkauntidan `/whoami`, `/messages`, `/stats`; ikkinchi admin `is_active=0` → `/help` jim ignore tekshiruvi.
3. **Contact notify:** real forma → A va B (ikkalasi faol) Telegram xabar oladi; B o‘chirilgach faqat A.
4. **Owner rotation:** GitHub PAT revoke, `APP_SECRET` rotate, bot token `/revoke` + `setWebhook` yangi secret, OTP `admin@`ga ko‘chirish, Google Maps key rotate/restrict.
5. **DKIM/DMARC:** Eskiz DNS/WHM orqali (blocker).

Owner hali bajarishi kerak: yuqoridagi rotationlar, real BotFather Login testi, DKIM/DMARC.
```

---

## 5. `tz.md` — `## Baseline completed` (qo‘shimcha)

Mavjud ro‘yxatga quyidagilarni qo‘shing (oxiriga):

```diff
 - Apache checks: admin PATH_INFO is not caught by the fallback, `backend/`/`database/`/`config.local.php` are denied, and known + admin-created detail URLs return 200.
+- **RBAC (2026-10-23):** `admin_users.role` (`super`/`admin`), `admin_require_super()`, `last_super_admin` guard, `Dashboard.jsx:isSuper` (Adminlar tab yashirin), `AdminManager.jsx` role select + last-super disable, `components/admin/api.js` yangi error code’lar. Migration: `database/migrate-admin-role.sql` (non-idempotent, bir marta).
+- **Bot multi-admin (2026-10-23):** `bot_find_admin()`, `bot_notify_admins()`, `bot_audit()`, `bot_rate_limit_ok($telegramId, ...)`, yangi komandalar (`/whoami`, `/messages`, `/unread`, `/message`, `/reply`, `/stats`), `scripts/set-bot-commands.sh`. `contact_native_submit()` → `bot_notify_admins()`.
```

---

## 6. `tz.md` — `## Backlog` (to‘liq yangilangan jadval)

Bu jadvalni almashtiring. Yangi P1/P2/P3 itemlar 2026-10-23 RBAC/multi-admin bilan bog‘liq.

````markdown
## Backlog

| Prio | Problem | Evidence / where | Next action |
|---|---|---|---|
| P1 | **RBAC migration production’ga qo‘llanmagan** | `database/migrate-admin-role.sql`; `admin_users.role` production DB’da yo‘q | Migration’ni bir marta import; `SHOW COLUMNS FROM admin_users LIKE 'role'` bilan tekshirish; super-admin role `SELECT id, telegram_id, role, is_active` bilan tasdiqlash |
| P1 | **Bot multi-admin deploy qilinmagan** | `backend/bot.php:bot_find_admin()`, `bot_notify_admins()`; `scripts/set-bot-commands.sh` | `setMyCommands` ishga tushirish; real Telegram admin akkauntidan `/whoami`/`/messages`/`/stats`; ikkinchi admin `is_active=0` → `/help` jim ignore; contact forma → barcha faol adminlarga xabar |
| P1 | **Owner-only secret rotations** | GitHub PAT (`github_keys.txt`), `APP_SECRET` + bot token (`config.local.php`), Gmail OTP `admin@`ga, legacy Google Maps keys | Revoke/rotate: GitHub settings/tokens; `php -r "echo bin2hex(random_bytes(32));"`; BotFather `/revoke` + `setWebhook` yangi `secret_token`; Google Cloud Console key restrict |
| P1 | **DKIM yo‘q, DMARC `p=none`; Exim imzolamaydi** | external DNS (`ns1/ns2.eskiz.uz`), cPanel/WHM; Port25 `SPF pass`, `DKIM none` | `default._domainkey` TXT + DMARC `p=quarantine`; Exim DKIM signing; Port25 qayta tekshirish. **Blocker:** Eskiz DNS/WHM access |
| P1 | **BotFather `/setdomain` va real Telegram Login tasdiqlanmagan** | `@otaboyev_sardorbek_uz_bot`; `/admin/` widget | `/setdomain otaboyev-sardorbek.uz`; real Telegram akkaunt bilan login + challenge + OTP; botga bir real `/help`/`/inbox` |
| P1 | **Full deploy (RBAC + bot multi-admin) kutilmoqda** | `/home/host9590/apps/...`, `/home/host9590/public_html` | Node 22 build; `migrate-admin-role.sql` + `migrate-bot-guards.sql` import; tar-deploy `config.local.php` va `.well-known/` saqlab; `api.php/health`, `/admin/config`, `/admin/` login, bot webhook smoke |
| P2 | **Contact formadan adminlarga email notification yo‘q** | `contact_native_submit()` → `bot_notify_admins()` (Telegram) ishlaydi, lekin admin inbox’ga email signal yo‘q | Ixtiyoriy: `contact_store_message()` dan keyin `admin_mail_send_smtp()` bilan admin emailga qisqa signal (agar owner xohlasa); hozircha Telegram yetarli |
| P2 | **`en/*` legacy Sitejet sahifalari uz/ru muqobilisiz** | `en/portfolio`, `en/tech-stack`, `en/projects`, `en/contact-me`, `en/subpage`, `en/legal-notice`, `en/privacy` | Owner qarori: saqlash+tarjima yoki repo’dan o‘chirish. `out/` ga ko‘chirilmaydi; sitemap’da yo‘q; canonical xato domen |
| P2 | **Detail statik fallback crawler uchun eskirgan** | `components/ProjectDetail.jsx`, `[slug]/page.js`; admin tahrirlari API fetch’dan keyin ko‘rinadi | Admin saqlashdan keyin rebuild/publish hook; yoki ISR; hozircha accept |
| P3 | **Bot `/reply` SMTP driver bilan `Reply-To` qo‘shmaydi** | `backend/bot.php:bot_send_email()` `admin_mail_send_smtp()` orqali; `Reply-To` yo‘q | `mail` driver ishlatilsin yoki `admin_mail_send_smtp()` ga `$replyTo` parametri qo‘shilsin (alohida task) |
| P3 | **Bot `setMyCommands` avtomatlashtirilmagan** | `scripts/set-bot-commands.sh` qo‘lda bajariladi | CI yoki deploy skriptga qo‘shish (ixtiyoriy) |
| P3 | **`database/migrate-admin-role.sql` non-idempotent** | `ALTER TABLE ... ADD COLUMN role` | Idempotent migration runner yoki stored procedure; hozircha `SHOW COLUMNS` bilan qo‘lda tekshirish |
| P3 | **`database/migrate-project-sections.sql` non-idempotent** | `database/migrate-project-sections.sql` | Idempotent runner |
| P3 | **Generic detail route metadata/canonical JS bilan tuzatiladi** | `app/(site)/[locale]/projects/view/page.js` | Static metadata strategy yoki accept |
| P3 | **Media upload yo‘q** | `components/admin/ProfileEditor.jsx`, `backend/admin.php` | Owner approval + validatsiyalangan upload; `avatar_url` fallback saqlanadi |
| P3 | **Legacy HTML, `js/portfolio.js`, empty README, `CodeAnalyzer_v2.py`** | repo root | Owner approval bilan o‘chirish/arxivlash |
| P3 | **SSH key passphrase exposure** | `~/.ssh/id_rsa` | 2026-10-22: rotate qilingan; yangi qiymat repo tashqarisida `600` |
| P3 | **`alert_admin()` monitoring yo‘q** | `SECURITY.md` §8 | Telegram signal: unauthorized login, OTP lockout, webhook secret fail, DB/IMAP fail, yangi admin, sitemap fail |

Anything that changes visible content, metadata or links must be applied to `uz`, `en` and `ru` in the same change. `admin_users.role` faqat `super`/`admin` — yangi rol qo‘shish uchun `admin_validate_admin()` va `AdminManager.jsx:ROLES` ikkalasi yangilanadi.
````

---

## 7. `tz.md` — `## Next actions` (yangilangan)

```markdown
## Next actions

1. Commit/push RBAC + bot multi-admin diff (`backend/admin.php`, `backend/bot.php`, `backend/contact.php`, `database/schema.sql`, `database/migrate-admin-role.sql`, `components/admin/AdminManager.jsx`, `components/admin/Dashboard.jsx`, `components/admin/api.js`, `scripts/set-bot-commands.sh`, hujjatlar).
2. `migrate-admin-role.sql` production DB’ga bir marta import; `admin_users` da kamida 1 faol super tasdiqlash.
3. Node 22 build; tar-deploy; `api.php/health`, `/admin/config`, `/admin/` real login, yangi admin bilan `403 forbidden`, oxirgi super himoyasi smoke.
4. `scripts/set-bot-commands.sh` ishlatib `setMyCommands`; real Telegram admin akkauntidan `/whoami`, `/messages`, `/stats`; `is_active=0` admin `/help` jim ignore; contact forma → barcha faol adminlarga xabar.
5. Owner rotationlari: GitHub PAT revoke, `APP_SECRET` rotate, bot token `/revoke` + `setWebhook` yangi secret, Gmail → `admin@`, Google Maps key rotate/restrict.
6. `webhook_secret` rotate + `setWebhook` yangilash (owner).
7. DKIM/DMARC — Eskiz DNS/WHM access bo‘lgach; Port25 qayta tekshirish.
8. Real BotFather Telegram Login testi + real Telegram admin update.
9. Owner qarori: `en/*` legacy parity, media upload, `alert_admin()` monitoring.
```

---

## 8. Kod ↔ hujjat mosligi (o‘zingiz tekshirib ko‘ring)

Quyidagi buyruqlar hujjat va kod o‘rtasidagi moslikni tasdiqlaydi:

```bash
# AGENTS.md → kodda mavjud guardlar
grep -n "admin_require_super"    backend/admin.php AGENTS.md
grep -n "last_super_admin"       backend/admin.php components/admin/api.js AGENTS.md
grep -n "bot_find_admin"         backend/bot.php  AGENTS.md
grep -n "bot_notify_admins"      backend/bot.php  backend/contact.php AGENTS.md
grep -n "bot_rate_limit_ok"      backend/bot.php  AGENTS.md
grep -n "bot_audit"              backend/bot.php  AGENTS.md

# tz.md → migration va backlog
grep -n "migrate-admin-role"     tz.md AGENTS.md README.md
grep -n "migrate-bot-guards"     tz.md AGENTS.md README.md

# Hujjat izchilligi
grep -n "super" AGENTS.md tz.md SECURITY.md | grep -i "role\|super admin"
```

Kutilgan: har bir guard/komanda `AGENTS.md` da va (backlog/next actions sifatida) `tz.md` da uchraydi; `SECURITY.md` bilan `super`/`last_super_admin` nomlari mos keladi.

---

## 9. Commit xabarlari (Conventional Commits)

Bu diff uchun tavsiya etilgan commit’lar (ajratish mumkin):

```
feat(admin): add super/admin roles with last-super guard
feat(bot): multi-admin authorization via admin_users.is_active
feat(bot): add /whoami, /messages, /unread, /message, /reply, /stats
feat(contact): notify active admins via Telegram on new messages
chore(db): add migrate-admin-role.sql and update schema.sql
docs(agents,tz,security): document RBAC and bot multi-admin
```

---
# `tz_master.md` — RBAC va bot multi-admin yangilanishlari

Quyida `tz_master.md` uchun zarur bo‘lgan barcha bo‘limlar. Uslub mavjud hujjat bilan bir xil — qisqa, aniq, jadval/kod bilan. Faqat tegishli joylar almashtiriladi, qolgan qismlar o‘zgartirilmaydi.

---

## 1. `## 2. Current system` → `### Backend` — qo‘shimcha

Mavjud `### Backend` ro‘yxatiga quyidagi bullet’larni qo‘shing (aynan `backend/admin.php` va `backend/bot.php` yonida):

```diff
 - `api.php` — request routing, native contact form, token check, Sitejet proxy and content/admin includes.
 - `backend/config.php` — DB, admin and mail settings from env or gitignored `config.local.php`.
 - `backend/contact.php` — contact validation, IP hash, rate limit, inbox persistence and native `id=contact` handler.
 - `backend/content.php` — public JSON content API.
-- `backend/admin.php` — Telegram + email OTP auth, sessions, CSRF, rate limits, audit log, profile/project CRUD, inbox, sitemap and admin-user management.
-- `backend/bot.php` — private Telegram email bridge: webhook secret + numeric admin allowlist, IMAP inbox/read and cPanel `mail()` send; `update_id` idempotency and command rate limits; only `/api.php/bot/webhook`.
+- `backend/admin.php` — Telegram + email OTP auth, sessions, CSRF, rate limits, audit log, profile/project CRUD, inbox, sitemap and admin-user management. **RBAC:** `admin_users.role` (`super`/`admin`), `admin_require_super()`, `last_super_admin` guard; role/is_active o‘zgarishi keyingi so‘rovda darhol kuchga kiradi (JOIN `admin_users`).
+- `backend/bot.php` — private Telegram **multi-admin** email & inbox bridge: webhook secret + `bot_find_admin()` (`admin_users.telegram_id AND is_active = 1`), IMAP inbox/read, cPanel `mail()` send, contact inbox (`/messages`, `/unread`, `/message`, `/reply`, `/stats`); `update_id` idempotency (`bot_updates_seen`) va per-telegram_id rate limit (`bot_rate_limit`); `bot_audit()` orqali `admin_audit_log` (`entity='bot'`); `bot_notify_admins()` yangi contact xabari haqida barcha faol adminlarga signal beradi. Only `/api.php/bot/webhook`.
 - `backend/mailer.php` — `mail`, SMTP, Resend, Brevo and local `log` drivers.
-- `database/schema.sql`, `database/seed.sql`, `database/migrate-project-sections.sql`, `database/migrate-contact-messages.sql`, `database/migrate-bot-guards.sql`.
+- `database/schema.sql`, `database/seed.sql`, `database/migrate-project-sections.sql`, `database/migrate-contact-messages.sql`, `database/migrate-bot-guards.sql`, `database/migrate-admin-role.sql`.
```

---

## 2. `## 3. Data model` — jadvalni yangilash

### 2.1. Jadval ro‘yxatiga qo‘shimcha va `admin_users` ni kengaytirish

**Jadval (mavjud):**

```diff
 | Table | Purpose |
 |---|---|
 | `profiles` | single profile row (`id=1`) with name, email, GitHub, Telegram, avatar |
 | `profile_translations` | `role`, `bio`, `quick_text`, `location` per locale |
 | `projects` | slug, category, repo/demo URLs, sort order, active flag |
 | `project_translations` | tag, title, summary, description, tech, results, stack notes per locale |
 | `contact_messages` | contact inbox: form id, locale, sender, subject, message, JSON payload, hashed IP, read flag |
-| `admin_users` | bootstrap/active admins |
+| `admin_users` | bootstrap/active admins; `is_active` va `role` (`super`/`admin`) |
 | `admin_login_challenges` | hashed OTP challenge, attempts, expiry |
 | `admin_sessions` | hashed session tokens, expiry, last seen |
 | `admin_auth_events` | login/auth event log |
 | `admin_audit_log` | content mutation audit log |
+| `bot_updates_seen` | Telegram `update_id` idempotency (`INSERT IGNORE`, 7 kun TTL) |
+| `bot_rate_limit` | per-telegram_id command limit (`/messages` 20, `/unread` 20, `/message` 30, `/reply` 10, `/inbox` 10, `/read` 20, `/send` 10 per minute; 1 kun TTL) |
```

**Fresh install / migration matni (mavjud):**

```diff
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
+mysql -u USER -p DB_NAME < database/migrate-admin-role.sql
 ```

 `migrate-project-sections.sql` adds `results` and `stack_notes`; it is a one-time, non-idempotent script. `migrate-contact-messages.sql` and `migrate-bot-guards.sql` use `CREATE TABLE IF NOT EXISTS` and are safe to run again. Re-import `database/seed.sql` **only** when resetting to the default content — it overwrites existing admin edits.
+
+`migrate-admin-role.sql` adds `admin_users.role VARCHAR(20) NOT NULL DEFAULT 'admin'` va `idx_admin_users_role`; bir marta bajariladi (MySQL 5.7 `ADD COLUMN IF NOT EXISTS` ni qo‘llamaydi). Migration oxirida eng kichik `id` adminni **bir marta** `role='super'` qilib belgilaydi (faqat hech qanday super bo‘lmasa). Eski bazada `SELECT id, telegram_id, role, is_active FROM admin_users;` bilan kamida 1 faol super borligi tasdiqlanadi.
```

### 2.2. Yangi: `admin_users` maydonlar jadvali

`## 3. Data model` oxiriga qo‘shing:

```markdown
### `admin_users` (RBAC, 2026-10-23)

| Column | Type | Notes |
|---|---|---|
| `id` | `INT UNSIGNED AUTO_INCREMENT` | PK |
| `telegram_id` | `BIGINT UNSIGNED UNIQUE` | Login widget `id` + bot `from.id` |
| `email` | `VARCHAR(190)` | OTP recipient |
| `display_name` | `VARCHAR(120) NULL` | UI display |
| `telegram_username` | `VARCHAR(64) NULL` | Audit/UI |
| `is_active` | `TINYINT(1) DEFAULT 1` | `0` → web `401 auth_required`, bot jim ignore |
| **`role`** | **`VARCHAR(20) NOT NULL DEFAULT 'admin'`** | **`super` (admin CRUD) yoki `admin` (content + inbox)** |
| `last_login_at` | `DATETIME NULL` | Informational |
| `created_at`, `updated_at` | `DATETIME` | |

**Qoidalar:**
- Kamida **1 ta faol `super`** doim qolishi shart — `admin_has_other_active_super($pdo, $id)` va `422 last_super_admin` bilan himoyalangan.
- Oxirgi super’ni **deaktiv qilish**, **`admin`ga tushirish** yoki **o‘chirish** — bloklanadi.
- `self-delete` → `422 cannot_delete_self`; `self-deactivate` → `422 cannot_deactivate_self`.
- `POST/PUT/DELETE /api.php/admin/admins*` faqat `super` uchun — `admin_require_super($session)` `403 forbidden`.
- `Dashboard.jsx:isSuper` super bo‘lmagan admin uchun “Adminlar” tab’ini yashiradi; `AdminManager.jsx` oxirgi faol super o‘chirish tugmasini `disabled` qiladi.
```

---

## 3. `## 4. API contract` → `### Admin` — super-only belgisi va error code’lar

**Admin jadvali (mavjud) — oxirgi 4 qator `super` belgilanadi:**

```diff
 | GET/POST | `/api.php/admin/admins` | list / create extra admins |
-| GET/PUT/DELETE | `/api.php/admin/admins/{id}` | read / update / delete; self and last-admin guards |
+| GET/POST | `/api.php/admin/admins` | **super only**; list / create; `role` (`super`/`admin`) |
+| GET/PUT/DELETE | `/api.php/admin/admins/{id}` | **super only**; read / update / delete; self, last-admin va **last-super** guards |
```

**`X-CSRF-Token` bullet (mavjud) — kengaytiriladi:**

```diff
-`X-CSRF-Token` is required on mutations. Admin responses must not use wildcard CORS. The slug `view` is reserved for the fallback route. Sitemap generation runs automatically after project mutations and fails soft there; the manual endpoint returns `500 sitemap_write_failed` if the web root is not writable.
+`X-CSRF-Token` is required on mutations. Admin responses must not use wildcard CORS. The slug `view` is reserved for the fallback route. Sitemap generation runs automatically after project mutations and fails soft there; the manual endpoint returns `500 sitemap_write_failed` if the web root is not writable.
+
+**RBAC:** `/api.php/admin/admins*` `admin_require_super($session)` talab qiladi — `role != 'super'` bo‘lsa `403 forbidden`. Oxirgi faol super’ni `admin`ga tushirish/deaktiv/o‘chirish `422 last_super_admin`. `role` qiymatlari faqat `super` va `admin`; boshqa qiymat `422 validation_failed` (`errors.role`). Error code’lar: `forbidden`, `last_super_admin`, `cannot_delete_self`, `cannot_deactivate_self`, `last_admin`, `admin_exists`, `admin_not_found`.
```

**Public endpoint’ga bot multi-admin haqida eslatma (`POST /api.php/bot/webhook` yonida):**

```diff
 | POST | `/api.php/bot/webhook` | private Telegram bot bridge: `X-Telegram-Bot-Api-Secret-Token`, numeric admin ID, `message` updates only |
```

→ o‘rniga:

```diff
-| POST | `/api.php/bot/webhook` | private Telegram bot bridge: `X-Telegram-Bot-Api-Secret-Token`, numeric admin ID, `message` updates only |
+| POST | `/api.php/bot/webhook` | private Telegram **multi-admin** bridge: `X-Telegram-Bot-Api-Secret-Token` (`hash_equals`, ≥32 belgi), `message.from.id == message.chat.id`, `chat.type == 'private'`, `bot_find_admin(from.id)` (`admin_users.telegram_id AND is_active = 1`); `config.telegram_id` faqat bootstrap. Komandalar: `/start`, `/help`, `/whoami`, `/messages`, `/unread`, `/message <id>`, `/reply <id> \| <matn>`, `/stats`, `/inbox`, `/read <uid>`, `/send <email> \| <mavzu> \| <matn>`. `update_id` idempotent (`bot_updates_seen`); rate limit per-telegram_id (`bot_rate_limit`); `entity='bot'` audit. |
```

---

## 4. `## 5. Admin security` — to‘liq yangilangan

Bu bo‘limni almashtiring:

```markdown
## 5. Admin security

- Telegram Login Widget callback: HMAC-SHA256 with the bot token, `auth_date` window, allowed `ADMIN_TELEGRAM_ID` or active `admin_users` row.
- **RBAC:** `admin_users.role` — `super` (admin CRUD) yoki `admin` (content + inbox). `admin_require_super($session)` `POST/PUT/DELETE /api.php/admin/admins*` da `403 forbidden` bilan majburiy. `Dashboard.jsx:isSuper` “Adminlar” tab’ini yashiradi; `AdminManager.jsx` oxirgi faol super’ni `disabled` qiladi.
- **Super/self himoyasi:** oxirgi faol super’ni o‘chirish/deaktiv/`admin`ga tushirish `422 last_super_admin`; o‘zini o‘chirish `422 cannot_delete_self`; o‘zini deaktiv `422 cannot_deactivate_self`; oxirgi faol admin `422 last_admin`. Guard funksiyalari: `admin_has_other_active_super($pdo, $id)`, `admin_active_count($pdo, $id)`.
- **Session invalidation:** `admin_lookup_session()` har so‘rovda `admin_sessions JOIN admin_users` — `a.is_active = 1` shart. `is_active=0`, `role` o‘zgarishi yoki `DELETE` — keyingi so‘rovda darhol `401 auth_required` / `403 forbidden`. Sessiya `expires_at` uzaytirilmaydi (sliding `last_seen_at` faqat audit).
- **Bootstrap va safety net:** `admin_users` bo‘sh bo‘lsa `admin_find_or_bootstrap_user()` `role='super'` bilan birinchi adminni yaratadi. `migrate-admin-role.sql` eng kichik `id` adminni bir marta super qiladi. Safety net: config ID `role != 'super'` va faol super yo‘q bo‘lsa, avtomatik `super` ga ko‘tariladi.
- OTP: 6 digits, HMAC-hashed, one-time, 10 minutes, 5 attempts.
- Cookies: `admin_session` HttpOnly, Secure on HTTPS, `SameSite=Strict`; `admin_challenge` HttpOnly, `SameSite=Lax`.
- CSRF: HMAC token bound to the session; required on POST/PUT/DELETE. `Origin`/`Referer` must match `site_url` by scheme, host and port; `Origin: null` is rejected; if both headers are absent (CLI), `X-Requested-With: XMLHttpRequest` is required.
- Rate limits: challenge and start attempts per time window; auth events recorded.
- Audit: profile/project, inbox read/delete, sitemap, admin-user mutations, **va bot harakatlari** (`entity='bot'`: `bot_message_read`, `bot_message_reply`, `bot_email_sent`) written to `admin_audit_log`. IP hash (HMAC `APP_SECRET`), PII emas.
- Auth is disabled unless all required settings are valid: HTTPS production URL, mail driver, Telegram bot token/username, numeric admin ID, valid admin email and a 64-hex app secret.
- **Bot bridge (private multi-admin):** `X-Telegram-Bot-Api-Secret-Token` (`hash_equals`, `strlen ≥ 32`) + `message.from.id == message.chat.id` + `chat.type == 'private'` + `bot_find_admin(from.id)` (`admin_users.telegram_id AND is_active = 1`). `config.telegram_id` faqat bootstrap fallback. Ruxsatsiz user `200 'ok'` jim ignore — bot mavjudligi leak bo‘lmaydi. `role` botni cheklamaydi.
- **Bot guard’lar:** `bot_updates_seen` idempotency (`update_id` bir marta); `bot_rate_limit` per-telegram_id (`/messages` 20, `/unread` 20, `/message` 30, `/reply` 10, `/inbox` 10, `/read` 20, `/send` 10 per minute). Jadval bo‘lmasa bot ishlaydi, guard’lar fail-open (`error_log`).
- **Contact → admin Telegram:** `contact_native_submit()` → `bot_notify_admins()` — barcha `is_active=1` adminlarga xabar; **best-effort** (forma natijasiga ta’sir qilmaydi; `try/catch` + `error_log`).
- Never expose bot token, app secret, OTP, webhook secret, IMAP password or real admin email in public JSON or logs.
- Production DB user has only `SELECT, INSERT, UPDATE, DELETE`; DDL is granted temporarily for migrations.
```

---

## 5. `## 7. QA` — RBAC va bot multi-admin testlari

Mavjud `Verify:` ro‘yxatiga qo‘shimcha qatorlar:

```diff
 Verify:

 - `/uz/`, `/en/`, `/ru/` load without console/network/hydration errors.
 - `out/` contains `.htaccess`, `api.php`, `404.html`, admin pages; `out/config.local.php`, `out/config.local.js` and `out/github_keys.txt` are absent.
 - One known detail per locale, its language switcher and API refresh; one admin-created slug through Apache.
 - Content API: profile/projects/detail for all locales, invalid `lang`, missing slug and `db_unavailable`; CORS `*` only on public reads; no HSTS over HTTP.
 - Admin: config `enabled`; Telegram callback → challenge cookie → email code → session; wrong OTP + lockout, expired challenge/session, missing CSRF, `Origin: null`/evil origin/header-less rejection, CORS separation and logout.
 - Admin profile/project create→update→delete; confirm public API and 3-language validation.
 - Contact form request/response in the network tab; `contact_messages` row; inbox count/list/read/delete; honeypot, invalid email, per-IP 5/10-minute and 10-row global limits.
 - Admin-user add/edit/delete with self/last-admin guards; sitemap button and automatic regeneration after project save/delete.
 - Bot: missing/wrong secret → 403, GET → 405, valid secret + unauthorized user → 200 without action, duplicate `update_id` processed once, rate limit returns false on the 11th call.
 - `database/migrate-bot-guards.sql` imported twice successfully; both bot tables exist.
 - Apache: real 404 for `/de/`/`/nope`, generic detail fallback, denied backend/config paths.
 - CV links resolve; `.htaccess` rewrites tested on Apache.
 - `git diff --check` and a full diff review.
+
+**RBAC (2026-10-23):**
+
+- `mysql -u USER -p DB -e "SHOW COLUMNS FROM admin_users LIKE 'role';"` → `role VARCHAR(20) NOT NULL DEFAULT 'admin'`; kamida 1 faol super.
+- Super admin: `GET/POST/PUT/DELETE /api.php/admin/admins*` → 200/201; `admin.role='super'`.
+- Admin: `/api.php/admin/admins` → `403 forbidden`; dashboard’da “Adminlar” tab yo‘q.
+- Oxirgi faol super’ni o‘chirish/deaktiv/`admin`ga tushirish → `422 last_super_admin`.
+- Boshqa super faol bo‘lsa, o‘zini `admin`ga tushirish → 200.
+- Self-delete → `422 cannot_delete_self`; self-deactivate → `422 cannot_deactivate_self`.
+- **Session invalidation:** A → B `is_active=0` → B keyingi so‘rovda `401`; A → B `role='admin'` → B keyingi `/admins` so‘rovda `403`.
+- `admin_audit_log` da `admin_created/updated/deleted` + `role` yozuvi; `entity='bot'` yozuvlari.
+
+**Bot multi-admin (2026-10-23):**
+
+- `bot_find_admin()`: `admin_users.telegram_id` + `is_active=1`; `config.telegram_id` faqat bootstrap.
+- Faol B `/help` → javob; A → B `is_active=0` → B `/help` **jim ignore**; A → B `is_active=1` → javob qayta keladi; A → B `DELETE` → jim ignore.
+- `/whoami` → ism + telegram_id + email; `/messages`, `/unread`, `/message <id>`, `/reply <id> \| <matn>`, `/stats` ishlaydi; `bot_audit()` yozadi.
+- Bir xil `update_id` ikki marta → 2-chi `200 'ok'` amal bajarmaydi; `bot_updates_seen` 1 qator.
+- `bot_rate_limit_ok($tg, 'reply', 10)` 11-chi chaqiriq → `false`.
+- Contact forma → A va B (ikkalasi faol) Telegram xabar; B `is_active=0` → faqat A; bot API fail → forma `200` + `error_log`.
+- `scripts/set-bot-commands.sh` → `setMyCommands` javobi `{"ok":true}`.
```

---

## 6. `## 8. Roadmap` — Phase 2, 3 yangilari

**Phase 2 (Content operations) — completed bullet’ga qo‘shish:**

```diff
-| 2 | Content operations | Completed: contact inbox, native contact form and extra-admin UI. Remaining: add a rebuild/publish hook so admin edits reach static HTML crawlers |
+| 2 | Content operations | Completed: contact inbox, native contact form, extra-admin UI, **RBAC (`super`/`admin`) and last-super guard**, **contact → all active admins Telegram notification via `bot_notify_admins()`**. Remaining: rebuild/publish hook so admin edits reach static HTML crawlers |
```

**Phase 3 (SEO and routing) — o‘zgartirmang** (RBAC ta’sir qilmaydi).

**Yangi Phase 2.5 qatori (RBAC/multi-admin) — Roadmap jadvaliga qo‘shing:**

```diff
 | Phase | Goal | Status / items |
 |---|---|---|
 | 0 | Repo hygiene | Completed: ... |
 | 1 | Production readiness | Completed: ... |
+| **2a** | **Admin RBAC + bot multi-admin** | **Completed 2026-10-23:** `admin_users.role` (`super`/`admin`), `admin_require_super()`, `last_super_admin` guard, `bot_find_admin()`, `bot_notify_admins()`, `bot_audit()`, yangi bot komandalar (`/whoami`, `/messages`, `/unread`, `/message`, `/reply`, `/stats`), `migrate-admin-role.sql`. **Remaining:** production deploy, `setMyCommands`, real Telegram admin test, DKIM/DMARC (blocker) |
 | 2 | Content operations | Completed: ... |
 | 3 | SEO and routing | Completed: ... |
 | 4 | Maintenance | Idempotent migration runner, media upload, legacy cleanup, remove unused legacy assets once safe |
 | — | Completed | CV downloads; 15-URL sitemap; ...; real 404 |
```

**Roadmap `— Completed` qatoriga qo‘shimcha:**

```diff
-| — | Completed | CV downloads; 15-URL sitemap; canonical/hreflang; React home/listing; MySQL content API; Telegram + OTP admin; 9 detail pages migrated; Apache fallback for new slugs; contact inbox; sitemap generator; admin-user manager; native contact form; real 404 |
+| — | Completed | CV downloads; 15-URL sitemap; canonical/hreflang; React home/listing; MySQL content API; Telegram + OTP admin; **RBAC (`super`/`admin`) + `last_super_admin`**; **bot multi-admin (`bot_find_admin`, `bot_notify_admins`, `bot_audit`, `/whoami`, `/messages`, `/unread`, `/message`, `/reply`, `/stats`)**; 9 detail pages migrated; Apache fallback for new slugs; contact inbox; sitemap generator; admin-user manager; native contact form; real 404 |
```

---

## 7. `## 9. Migration history` — yangi 2026-10-23 bo‘lim

Mavjud `**Security hardening 2026-10-22 (post-deploy, needs redeploy):**` blokidan **keyin** qo‘shing:

```markdown
**RBAC + bot multi-admin 2026-10-23 (post-deploy):**

- `admin_users.role` (`super`/`admin`) + `idx_admin_users_role` — `database/schema.sql` yangilandi, `database/migrate-admin-role.sql` qo‘shildi (non-idempotent, bir marta bajariladi).
- `backend/admin.php`: `admin_require_super()`, `admin_has_other_active_super()`, `admin_find_or_bootstrap_user()` da `role='super'` va safety net, `/admin/admins*` super-only, `422 last_super_admin` guard, `admin_validate_admin()` `role in ('super','admin')`, `admin_handle_session()` javobida `role`.
- `components/admin/Dashboard.jsx`: `isSuper` — “Adminlar” tab super bo‘lmagan admin uchun yashirin; `tab='admins'` bo‘lsa `profile`ga qaytish.
- `components/admin/AdminManager.jsx`: `role` select, super/admin badge, oxirgi faol super’ni o‘chirish `disabled`, deaktiv ogohlantirishi (“bot ham ishlamaydi”).
- `components/admin/api.js`: `forbidden`, `last_super_admin` error message’lari.
- `backend/bot.php`: `bot_find_admin()` (`admin_users` + `is_active=1`, `config.telegram_id` bootstrap), `bot_audit()`, `bot_notify_admins()`, `bot_rate_limit_ok($telegramId, ...)` per-telegram_id, `bot_send_email(..., $replyTo)` (ixtiyoriy), yangi komandalar (`/whoami`, `/messages`, `/unread`, `/message`, `/reply`, `/stats`), yangilangan `bot_help_text()`. `role` botni cheklamaydi; ruxsatsiz user jim ignore.
- `backend/contact.php`: `contact_native_submit()` → `bot_notify_admins()` (best-effort).
- `database/schema.sql`: `bot_updates_seen`, `bot_rate_limit` (avval `migrate-bot-guards.sql` orqali edi).
- `scripts/set-bot-commands.sh`: `setMyCommands` ro‘yxati.
- `AGENTS.md`, `tz.md`, `tz_master.md`, `SECURITY.md`, `README.md`: RBAC, bot multi-admin, test rejasi va migration tarixi yangilandi.

**Deploy tartibi (2026-10-23):**

1. `database/migrate-admin-role.sql` production DB’ga bir marta import; `SHOW COLUMNS FROM admin_users LIKE 'role'` bilan tekshirish.
2. Node 22 build (`source /opt/alt/alt-nodejs22/enable`); tar-deploy `config.local.php` va `.well-known/` saqlab.
3. `scripts/set-bot-commands.sh` bilan `setMyCommands`; `getWebhookInfo` `pending_update_count=0`.
4. Smoke: `/api.php/health`, `/admin/config`, real Telegram Login, yangi admin → `403 forbidden`, last-super guard, contact forma → Telegram, bot `/whoami`/`/messages`/`/stats`, `is_active=0` admin `/help` jim ignore.
5. Audit: `SELECT action, entity, details FROM admin_audit_log ORDER BY id DESC LIMIT 10;` — `admin_created/updated/deleted` + `entity='bot'` yozuvlari.

**Remaining:**

- Real Telegram Login widget (BotFather `/setdomain`) va real bot admin update’i — owner.
- DKIM/DMARC — Eskiz DNS/WHM access (blocker).
- Owner rotation: GitHub PAT, `APP_SECRET`, bot token, Gmail → `admin@`, Google Maps keys.
- `setMyCommands` avtomatlashtirish (CI/deploy skript) — kelajakda.
```

---

## 8. `## 11. Final guidance` — qo‘shimcha

Mavjud oxirgi paragrafga qo‘shing:

```diff
-Keep the site stable, multilingual and production-friendly. Frontend changes belong in `app/` and `components/`, shared styling in `css/portfolio.css`, and backend changes in `api.php` / `backend/` with PHP 7.1 syntax. Do not add a Node runtime or a second server. Keep the bot private: webhook secret + numeric admin allowlist, no OTP/credentials in Telegram. Verify with `npm run check`, `npm run build`, the content API curls and a browser preview before deploying. Update `AGENTS.md`, `tz.md` and this file together whenever architecture, routes, schema, deploy steps or security behavior changes.
+Keep the site stable, multilingual and production-friendly. Frontend changes belong in `app/` and `components/`, shared styling in `css/portfolio.css`, and backend changes in `api.php` / `backend/` with PHP 7.1 syntax. Do not add a Node runtime or a second server. Keep the bot private: webhook secret + numeric admin allowlist, no OTP/credentials in Telegram. **RBAC:** `admin_users.role` faqat `super`/`admin` — yangi rol qo‘shish uchun `admin_validate_admin()`, `components/admin/AdminManager.jsx:ROLES`, `admin_require_super()` chaqiruvlari va `last_super_admin` guard’i **birga** yangilanadi. **Bot multi-admin:** `bot_find_admin()` `admin_users.is_active` ni har so‘rovda tekshiradi (cache request-scope); `config.telegram_id` faqat bootstrap. `bot_rate_limit.admin_id` — bu `telegram_id`, `admin_users.id` emas. Verify with `npm run check`, `npm run build`, the content API curls and a browser preview before deploying. Update `AGENTS.md`, `tz.md` and this file together whenever architecture, routes, schema, deploy steps or security behavior changes.
```

---

## 9. Kod ↔ hujjat mosligi (yakuniy tekshiruv)

Uch hujjat (`AGENTS.md`, `tz.md`, `tz_master.md`) va kod o‘rtasidagi izchillikni tasdiqlash:

```bash
# Har bir guard/komanda 3 hujjatda ham uchraydi
for term in admin_require_super last_super_admin bot_find_admin bot_notify_admins bot_rate_limit_ok bot_audit; do
  echo "== $term =="
  for f in AGENTS.md tz.md tz_master.md SECURITY.md; do
    printf "%-14s " "$f"; grep -c "$term" "$f" 2>/dev/null || echo 0
  done
done

# Migration
grep -n "migrate-admin-role" AGENTS.md tz.md tz_master.md README.md
grep -n "migrate-bot-guards" AGENTS.md tz.md tz_master.md README.md

# Role qiymatlari
grep -nE "'super'|\"super\"" backend/admin.php components/admin/AdminManager.jsx components/admin/Dashboard.jsx | head -30

# Kod
grep -n "admin_require_super\|last_super_admin" backend/admin.php
grep -n "bot_find_admin\|bot_notify_admins\|bot_audit\|bot_rate_limit_ok" backend/bot.php backend/contact.php
```

Kutilgan: har bir atama uchala hujjatda va kodda uchraydi; `role` qiymati faqat `super`/`admin`; `bot_rate_limit.admin_id` `telegram_id` sifatida ishlatilgan.

---

## 10. Commit xabarlari (Conventional Commits)

```bash
docs(agents,tz,master,security): document RBAC and bot multi-admin (2026-10-23)

- AGENTS.md: new "Admin auth (web + bot)" section; rewritten "Admin auth va xavfsizlik"
- tz.md: snapshot/active work/baseline/backlog/next actions updated
- tz_master.md: data model admin_users.role, API contract super-only, admin security,
  QA, roadmap Phase 2a, migration history 2026-10-23, final guidance
- SECURITY.md: rewritten "Auth modeli" and "Test rejasi" (3.2)
```

---

## 11. Yakuniy tartib — uch hujjat + kod birga

Endi to‘liq o‘zgarishlar daraxti:

| Hujjat | Yangilangan bo‘limlar |
|---|---|
| `AGENTS.md` | **Yangi:** `Arxitektura → Admin auth (web + bot)`. **Yangilangan:** `Admin auth va xavfsizlik`. **Qo‘shimcha:** `Qoidalar` ga RBAC/multi-admin qisqacha (quyida) |
| `tz.md` | **Snapshot** (RBAC + bot multi-admin bullet), **Active work** (deploy + smoke), **Baseline completed** (2 bullet), **Backlog** (yangi P1/P2/P3 itemlar), **Next actions** (9 qadam) |
| `tz_master.md` | **§2 Backend** (admin/bot bullet), **§3 Data model** (`admin_users.role` + migration), **§4 API** (super-only + bot webhook), **§5 Admin security** (to‘liq), **§7 QA** (RBAC + bot tests), **§8 Roadmap** (Phase 2a), **§9 Migration history** (2026-10-23), **§11 Final guidance** |
| `SECURITY.md` | **§1 Auth modeli** (1.1–1.16), **§2 Test rejasi** (2.1–2.19), **Top 20** (#9a), **cPanel checklist**, **Zaifliklar reyestri** (V-22…V-25), **§13 Tarix** (3.2) |

### `AGENTS.md` `## Qoidalar` — qisqa qo‘shimcha

```diff
 - Admin auth’ni bypass qiladigan dev flag, hardcoded OTP yoki public CRUD endpoint qo‘shmang. CSRF headerini olib tashlamang.
+- **RBAC:** yangi admin CRUD endpoint faqat `admin_require_super($session)` bilan; `admin_users.role` faqat `super`/`admin`; oxirgi faol super’ni himoya qiluvchi `last_super_admin` guard’i saqlanadi.
+- **Bot:** `bot_find_admin()` `admin_users.is_active=1` ni har so‘rovda tekshiradi; `config.telegram_id` faqat bootstrap fallback. `bot_rate_limit.admin_id` — `telegram_id` (BIGINT), `admin_users.id` emas. `bot_audit()` `entity='bot'` bilan yozadi.
```

---

# `README.md` — admin/bot bo‘limlarini yangilash

Quyida `README.md` ning tegishli bo‘limlari to‘liq yangilangan holda. Mavjud uslub saqlangan (o‘zbekcha matn + inglizcha texnik atamalar, jadval, kod bloklari, `backticks`).

---

## 1. `## Xususiyatlar` — yangilangan ro‘yxat

Mavjud ro‘yxatni quyidagiga almashtiring (yoki tegishli qatorlarni yangilang):

```markdown
## Xususiyatlar

- Ko‘p tilli portfolio sahifalari (uz/en/ru)
- Loyiha detail sahifalari
- GitHub profil ma’lumotlarini yuklash
- Contact form, MySQL inbox, honeypot/rate limit va fallback email ishlashi
- **Telegram Login Widget + email OTP asosidagi 2 bosqichli admin panel**
- **RBAC:** `super` (boshqa adminlarni CRUD qiladi) va `admin` (faqat profil/loyiha/inbox) rollari; oxirgi faol super himoyasi (`last_super_admin`)
- **Shaxsiy Telegram bot (multi-admin):** IMAP inbox, xat o‘qish, contact xabarlar (`/messages`, `/message`, `/reply`) va cPanel orqali email yuborish (webhook secret + `admin_users.is_active` allowlist)
- **Contact formadan barcha faol adminlarga real-time Telegram signal (`bot_notify_admins()`)**
- Profil va loyihalarni uch tilda tahrirlash (CRUD), audit log va CSRF himoyasi
- Inbox (read/unread/delete), adminlar boshqaruvi va avtomatik `sitemap.xml`
- CSP/HSTS/frame/referrer/COOP/CORP xavfsizlik headerlari; `api.php` HSTS’ni faqat HTTPS’da yuboradi; API `X-Powered-By` olib tashlangan
- Haqiqiy 404 sahifa va Apache/Nginx routing
- Responsive premium dark glassmorphism dizayn
- Lokalizatsiya va sahifalar orasidagi navigatsiya
- SEO va static sahifa qulayligi
```

---

## 2. `## Admin panel (Telegram + email OTP)` — to‘liq yangilangan bo‘lim

Mavjud bo‘limni almashtiring:

````markdown
## Admin panel (Telegram + email OTP)

Admin panel `/admin/` da. Kirish ikki bosqichli:

1. Telegram Login Widget admin Telegram akkauntini tasdiqlaydi (HMAC + `auth_date`).
2. Admin emailiga 6 xonali kod yuboriladi; kod 10 daqiqa amal qiladi va 5 urinishdan keyin bekor qilinadi.

Panelda profil (ism, email, GitHub, Telegram, avatar va uch tildagi role/bio/quick_text/location), loyihalar (slug, kategoriya, havolalar, tartib, faollik va uch tildagi tag/title/summary/description/tech/results/stack_notes), kelgan xabarlar va qo‘shimcha adminlar boshqariladi. `results` va `stack_notes` har bir qatorda bitta element sifatida kiritiladi. O‘zgarishlar `profiles`, `profile_translations`, `projects`, `project_translations`, `contact_messages` va `admin_users` jadvallariga yoziladi; loyiha o‘zgarishi `sitemap.xml` ni avtomatik yangilaydi va detail sahifada API fetch’dan keyin ko‘rinadi.

### Rollar (RBAC)

`admin_users.role` — `super` yoki `admin`. Default `admin`. Adminlar tab’ida faqat `super` boshqa adminlarni ko‘radi va boshqaradi.

| Rol | Web admin panel | Bot | `/api.php/admin/admins*` |
|---|---|---|---|
| **`super`** | Barcha tablar (Profil, Loyihalar, Xabarlar, Adminlar) | Barcha komandalar | GET/POST/PUT/DELETE |
| **`admin`** | Profil, Loyihalar, Xabarlar (Adminlar tab **ko‘rinmaydi**) | Barcha komandalar | **`403 forbidden`** |

**Qoidalar:**

- Kamida **1 ta faol `super`** doim qolishi shart — oxirgi faol super’ni o‘chirish, deaktiv qilish yoki `admin`ga tushirish `422 last_super_admin` bilan bloklanadi.
- O‘z hisobini o‘chirish → `422 cannot_delete_self`; o‘zini deaktiv → `422 cannot_deactivate_self`.
- `is_active = 0` qilingan admin **keyingi so‘rovda** web panelga ham, botga ham kira olmaydi (`admin_lookup_session()` va `bot_find_admin()` `admin_users` ga JOIN).
- `role` o‘zgarishi **darhol** kuchga kiradi — sessiyani bekor qilish shart emas.

### 1. BotFather

1. Telegram’da `@BotFather` ga `/newbot` yuboring.
2. Bot nomi va username’ini oling (username `bot` bilan tugaydi).
3. `/setdomain` → `otaboyev-sardorbek.uz` (protokolsiz, `https://` va slash’siz).
4. Bot tokenini hech qayerda oshkor qilmang.

Admin Telegram ID’ni `@userinfobot` orqali oling. Login widget faqat HTTPS va BotFather’da tasdiqlangan domenda ishlaydi.

### 2. Email

cPanel’da `no-reply@otaboyev-sardorbek.uz` mailbox yarating va **Email Deliverability** bo‘limida SPF, DKIM, DMARC ni yoqib tekshiring.

Eng tez variantlar:

- `ADMIN_MAIL_DRIVER=mail` — cPanel local sendmail; qo‘shimcha sozlash kam.
- `ADMIN_MAIL_DRIVER=smtp` — cPanel mailbox orqali SMTP; `SMTP_HOST`, `SMTP_PORT`, `SMTP_SECURE`, `SMTP_USER`, `SMTP_PASS`.
- `ADMIN_MAIL_DRIVER=resend` yoki `brevo` — tashqi API; domen DNS’da tasdiqlangan bo‘lishi kerak.

`ADMIN_MAIL_FROM` cPanel’dagi haqiqiy mailbox bo‘lsin. `log` driver faqat lokal test uchun; productionda avtomatik o‘chiriladi.

Productionda `contact@otaboyev-sardorbek.uz` → `admin@otaboyev-sardorbek.uz` forwarder’i ham yaratilgan; contact form API’siz qolganda fallback shu manzilga yuboradi.

**Email autentifikatsiyasi (2026-10-23):** cPanel `mail()` ishlaydi va Port25 tekshiruvi SPF’ni `pass` deb tasdiqladi. External DNS’da `default._domainkey` yo‘q, Exim hozir DKIM imzolamaydi, DMARC esa `v=DMARC1; p=none;`. DKIM/DMARC’ni yakunlash uchun Eskiz DNS yoki WHM/cPanel root darajasidagi amal kerak; buni “tayyor” deb hisoblamang.

### 3. Telegram bot email bridge (multi-admin)

`backend/bot.php` private webhook rejimida ishlaydi. **Har bir xabar `admin_users` jadvali orqali** tekshiriladi: `bot_find_admin($from_id)` → `admin_users WHERE telegram_id = :tg AND is_active = 1`. `config.local.php` dagi `admin.telegram_id` faqat **bootstrap fallback** — jadval bo‘sh yoki DB vaqtincha ishlamasa. Bot token, webhook secret yoki OTP Telegram’ga yuborilmaydi.

Har bir `update_id` `bot_updates_seen`da bir marta qayd etiladi (Telegram retry’da `/send` ikki marta bajarilmaydi). Rate limitlar per-`telegram_id` (`bot_rate_limit`): `/messages` 20/min, `/unread` 20/min, `/message` 30/min, `/reply` 10/min, `/inbox` 10/min, `/read` 20/min, `/send` 10/min. Bu jadvallar `database/migrate-bot-guards.sql` bilan qo‘shiladi; jadval bo‘lmasa bot ishlashda davom etadi, lekin guard’lar o‘chiq qoladi (`error_log`).

#### Bot komandalar

| Komanda | Vazifa |
|---|---|
| `/start`, `/help` | Yordam matni |
| `/whoami` | Joriy admin: ism + `telegram_id` + email |
| `/messages` | Contact inbox (oxirgi 10 ta, unread birinchi) |
| `/unread` | Faqat o‘qilmagan contact xabarlar |
| `/message <id>` | Contact xabarni o‘qish; `is_read=1` bo‘ladi |
| `/reply <id> \| <matn>` | Contact xabar egasiga email javob |
| `/stats` | `unread`, `total`, `faol adminlar` soni |
| `/inbox` (yoki `/mail`) | IMAP orqali oxirgi 5 email (cPanel `admin@` qutisi) |
| `/read <uid>` | IMAP xatni o‘qish (uid) |
| `/send <email> \| <mavzu> \| <matn>` | cPanel `mail()` bilan email yuborish |

Noma’lum matn — `/help` qaytaradi. Ruxsatsiz Telegram akkaunt — **jim ignore** (`200 'ok'`, javob yo‘q — bot mavjudligi leak bo‘lmaydi). Group chat — rad etiladi (`chat.type != 'private'`).

#### Contact formadan adminlarga signal

`backend/contact.php:contact_native_submit()` muvaffaqiyatli saqlashdan keyin `bot_notify_admins()` chaqiradi — **barcha faol adminlarga** (`admin_users WHERE is_active = 1`) Telegram xabar yuboriladi. Best-effort: bot API yoki DB fail bo‘lsa forma natijasiga ta’sir qilmaydi (`try/catch` + `error_log`).

#### 1. Config

`config.local.example.php` dan `config.local.php` yarating va to‘ldiring (gitignored, mode `600`):

```php
<?php
return array(
    'host' => 'localhost',
    'name' => 'cpaneluser_portfolio',
    'user' => 'cpaneluser_portfolio',
    'pass' => 'DB_PASSWORD',
    'admin' => array(
        'site_url' => 'https://otaboyev-sardorbek.uz',
        'app_env' => 'production',
        'app_secret' => '64_BELGILI_HEX_SECRET',
        'telegram_bot_token' => '123456789:AA...',
        'telegram_bot_username' => 'YourAdminBot',
        'telegram_id' => '123456789',  // faqat bootstrap
        'email' => 'admin@otaboyev-sardorbek.uz',
        'phone' => '+998900000000',
    ),
    'bot' => array(
        'webhook_secret' => 'AT_LEAST_32_RANDOM_CHARACTERS',
        'imap_host' => 'panel2.eskiz.uz',
        'imap_port' => 993,
        'imap_user' => 'admin@otaboyev-sardorbek.uz',
        'imap_pass' => 'MAILBOX_PASSWORD',
        'imap_ssl' => true,
    ),
    'mail' => array(
        'driver' => 'mail',
        'from' => 'no-reply@otaboyev-sardorbek.uz',
        'from_name' => 'Sardorbek Otaboyev',
    ),
);
```

`npm run build` `config.local.php` ni `out/` ga **ko‘chirmaydi** — u build artefaktida bo‘lmasligi kerak. Lokal preview uchun `./run_local.sh` va `./run_server.sh` uni `out/` ga `600` bilan nusxalaydi; productionda esa web rootga qo‘lda `chmod 600` bilan joylashtiring. Fayl gitignored va `.htaccess` bilan HTTP’dan yopilgan.

Secret yaratish:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

#### 2. Webhook va `setMyCommands`

Webhook’ni o‘rnating (token va secret’ni shell tarixiga yozmang):

```bash
curl -sS "https://api.telegram.org/bot${BOT_TOKEN}/setWebhook" \
  -d "url=https://otaboyev-sardorbek.uz/api.php/bot/webhook" \
  -d "secret_token=${WEBHOOK_SECRET}" \
  -d 'allowed_updates=["message"]'
curl -sS "https://api.telegram.org/bot${BOT_TOKEN}/getWebhookInfo"
```

Bot komandalar ro‘yxatini e’lon qilish (yoki `scripts/set-bot-commands.sh` orqali):

```bash
curl -sS "https://api.telegram.org/bot${BOT_TOKEN}/setMyCommands" \
  -H 'Content-Type: application/json' \
  --data '{
    "commands": [
      {"command":"start",    "description":"Boshlash"},
      {"command":"help",     "description":"Yordam"},
      {"command":"whoami",   "description":"Joriy admin ma’lumoti"},
      {"command":"messages", "description":"Contact inbox (10 ta)"},
      {"command":"unread",   "description":"O‘qilmagan xabarlar"},
      {"command":"message",  "description":"Xabarni o‘qish (id)"},
      {"command":"reply",    "description":"Email javob (id | matn)"},
      {"command":"stats",    "description":"Statistika"},
      {"command":"inbox",    "description":"IMAP email inbox"},
      {"command":"read",     "description":"Emailni o‘qish (uid)"},
      {"command":"send",     "description":"Email yuborish"}
    ]
  }'
```

Yoki repo’dagi skript:

```bash
BOT_TOKEN="..." ./scripts/set-bot-commands.sh
```

#### 3. cPanel IMAP

cPanel IMAP hosti `panel2.eskiz.uz:993/imap/ssl` ishlatiladi. `mail.otaboyev-sardorbek.uz` sertifikat hostname’iga mos kelmaydi. `bot.imap_host`, `bot.imap_port`, `bot.imap_user`, `bot.imap_pass`, `bot.imap_ssl` ni to‘ldiring.

#### 4. Birinchi login va super admin

`/admin/` ni birinchi marta ochganingizda `admin_find_or_bootstrap_user()` `config.telegram_id` asosida `admin_users` ga `role='super'` bilan yozadi. Keyingi adminlarni **Adminlar** tab’idan qo‘shishingiz mumkin; ular `role='admin'` (default) yoki `role='super'`.

Birinchi super adminni tekshirish:

```sql
SELECT id, telegram_id, email, role, is_active FROM admin_users;
```

Agar bazada super yo‘q bo‘lsa, migration yoki safety net avtomatik ko‘taradi. Qo‘lda:

```sql
UPDATE admin_users SET role='super' WHERE telegram_id='<OWNER_ID>';
```

#### 5. Xavfsizlik eslatmalari

- Bot token, `APP_SECRET`, OTP, webhook secret, IMAP paroli hech qachon public JSON, log yoki Telegram xabarida chiqmaydi.
- Ruxsatsiz Telegram akkauntga **javob yo‘q** — bot mavjudligi leak bo‘lmaydi.
- `bot_audit()` har bir `/message`, `/reply`, `/send` ni `admin_audit_log` ga `entity='bot'` bilan yozadi.
- `is_active=0` qilingan admin botga **jim ignore** — bu darhol kuchga kiradi.
- Bot faqat private chatda ishlaydi.
````

---

## 3. `## Admin API` jadvali — super-only belgisi

Mavjud jadvalni yangilang:

```diff
 | Endpoint | Maqsad |
 |---|---|
 | `GET /api.php/admin/config` | public: `enabled`, bot username, auth URL |
 | `GET /api.php/admin/auth/telegram` | Telegram widget callback (302) |
 | `GET /api.php/admin/auth/status` | challenge email maskasi va TTL |
 | `POST /api.php/admin/auth/verify` | `{code}` tekshirish |
 | `POST /api.php/admin/auth/resend` | yangi kod yuborish |
 | `GET /api.php/admin/session` | session + CSRF token; `?optional=1` → `{authenticated:false}` |
 | `POST /api.php/admin/logout` | sessionni yopish |
 | `GET/PUT /api.php/admin/profile` | profil o‘qish/saqlash |
 | `GET/POST /api.php/admin/projects` | ro‘yxat / yangi loyiha; saqlashda sitemap avtomatik yangilanadi |
 | `GET/PUT/DELETE /api.php/admin/projects/{id}` | bitta loyiha |
 | `GET /api.php/admin/messages[?count=1\|unread=1]` | inbox count/ro‘yxat |
 | `GET/PUT/DELETE /api.php/admin/messages/{id}` | xabar / read-unread / o‘chirish |
 | `POST /api.php/admin/sitemap` | `sitemap.xml` ni qayta generatsiya qilish |
-| `GET/POST /api.php/admin/admins` | adminlar ro‘yxati / yangi admin |
-| `GET/PUT/DELETE /api.php/admin/admins/{id}` | adminni o‘qish/tahrirlash/o‘chirish |
+| `GET/POST /api.php/admin/admins` | **super only** — adminlar ro‘yxati / yangi admin (`role` bilan) |
+| `GET/PUT/DELETE /api.php/admin/admins/{id}` | **super only** — adminni o‘qish/tahrirlash/o‘chirish; self va last-super guard |
```

Jadval ostidagi matnga qo‘shimcha:

```diff
 `POST`, `PUT`, `DELETE` so‘rovlarida `X-CSRF-Token` header majburiy. Admin endpointlar CORS header qaytarmaydi; ular faqat same-origin uchun. `Origin`/`Referer` `site_url` bilan strict host/scheme/port bo‘yicha solishtiriladi; `Origin: null` rad etiladi. Ikkala header bo‘lmasa (CLI) `X-Requested-With: XMLHttpRequest` talab qilinadi. Sitemap fayli web rootda PHP yoza oladigan bo‘lishi kerak; aks holda `500 sitemap_write_failed` qaytadi.
+
+**RBAC:** `/api.php/admin/admins*` `admin_require_super($session)` talab qiladi — `role != 'super'` bo‘lsa `403 forbidden`. Oxirgi faol super’ni o‘chirish/deaktiv/`admin`ga tushirish `422 last_super_admin`. `self-delete` → `422 cannot_delete_self`, `self-deactivate` → `422 cannot_deactivate_self`. `role` qiymatlari faqat `super`/`admin` — boshqa qiymat `422 validation_failed`.
```

---

## 4. `### 5. Sxema va login` — migration qo‘shish

Mavjud `mysql ... < database/schema.sql` bloklariga `migrate-admin-role.sql` qo‘shing:

```diff
 ```bash
 mysql -u USER -p DB_NAME < database/schema.sql
 mysql -u USER -p DB_NAME < database/seed.sql
 # Eski (project sections qo‘shilishidan oldingi) baza uchun avval:
 # mysql -u USER -p DB_NAME < database/migrate-project-sections.sql
 # Contact inbox jadvali uchun (idempotent):
 # mysql -u USER -p DB_NAME < database/migrate-contact-messages.sql
 # Telegram bot guard jadvallari uchun (idempotent):
 # mysql -u USER -p DB_NAME < database/migrate-bot-guards.sql
+# RBAC (super/admin) uchun (bir marta; MySQL 5.7 ADD COLUMN IF NOT EXISTS yo‘q):
+# mysql -u USER -p DB_NAME < database/migrate-admin-role.sql
 # seed.sql faqat standart kontentni tiklash uchun qayta import qilinadi;
 # u mavjud admin tahrirlarini bosib ketadi.
 ```

 So‘ng `/admin/` ni oching. Birinchi muvaffaqiyatli Telegram callback `ADMIN_TELEGRAM_ID` asosida `admin_users` yozuvini yaratadi. Keyingi adminlarni `/admin/dashboard/` → **Adminlar** bo‘limidan qo‘shish/tahrirlash/o‘chirish mumkin; Telegram ID va OTP email to‘g‘ri bo‘lsa ular ham kira oladi. O‘z hisobingizni o‘chirish/deaktiv qilish va oxirgi faol adminni olib tashlash bloklangan.
+
+**Rollar:** birinchi admin `role='super'`. Qo‘shimcha adminlar `role='admin'` (default) yoki `role='super'`. Super bo‘lmagan admin **Adminlar** tab’ini ko‘rmaydi va `/api.php/admin/admins*` da `403 forbidden` oladi. Oxirgi faol super’ni o‘chirish/deaktiv/`admin`ga tushirish `422 last_super_admin` bilan bloklanadi. Rol o‘zgarishi keyingi so‘rovda darhol kuchga kiradi.
```

---

## 5. `## Production server sozlamasi` → `### cPanel deploy` — migration qadami

Mavjud deploy qadamlariga qo‘shimcha qator:

```diff
 ### cPanel deploy

 1. cPanel → **MySQL Databases**: baza va foydalanuvchi yarating, foydalanuvchiga bazaga ruxsat bering.
 2. phpMyAdmin → **Import**: yangi baza uchun `database/schema.sql`, keyin `database/seed.sql`; eski bazada avval `database/migrate-project-sections.sql`, keyin `database/migrate-contact-messages.sql` va `database/migrate-bot-guards.sql` (idempotent) ni import qiling. `seed.sql` mavjud admin tahrirlarini bosib ketadi.
+3. **RBAC migration (bir marta):** `database/migrate-admin-role.sql` import qiling. Bu `admin_users.role VARCHAR(20) NOT NULL DEFAULT 'admin'` qo‘shadi va eng kichik `id` adminni bir marta `super` qiladi. Keyin:
+   ```sql
+   SHOW COLUMNS FROM admin_users LIKE 'role';
+   SELECT id, telegram_id, role, is_active FROM admin_users;
+   ```
+   kamida 1 faol super bo‘lishi shart.
 3. `config.local.example.php` dan `config.local.php` yarating; DB, Telegram, email va `app_secret` ni to‘ldiring.
 4. Lokalda yoki Node mavjud muhitda `npm ci && npm run build`.
 5. `out/` ichidagi barcha fayllarni cPanel **File Manager** orqali `public_html/` ga yuklang. `out/` papkasining o‘zini emas, ichidagi `api.php`, `backend/`, `.htaccess`, `index.html`, `admin/`, `uz/`, `en/`, `ru/`, `_next/`, `files/` ni yuklang.
 6. `config.local.php` ni web rootga qo‘ying va ruxsatini `600` qiling. Web rootdagi `sitemap.xml` PHP jarayoni yoza olishi kerak (fayl egasi web user bo‘lsin; papka yozish huquqini saqlang), aks holda admin panelida sitemap yangilash `500` qaytaradi.
 7. cPanel → **SSL/TLS Status**: AutoSSL faol bo‘lsin; HTTPS majburiy bo‘lsin.
 8. `https://otaboyev-sardorbek.uz/admin/` ni ochib, Telegram va email kod bilan tekshiring.
+9. **Bot komandalar:** `scripts/set-bot-commands.sh` yoki qo‘lda `setMyCommands` (yuqoridagi “Telegram bot email bridge” bo‘limiga qarang). `getWebhookInfo` `pending_update_count=0` bo‘lsin.
+10. **RBAC smoke:** super admin bilan yangi `admin` qo‘shing; yangi admin bilan login → **Adminlar** tab yo‘qligini; `/api.php/admin/admins` → `403 forbidden`; oxirgi faol super’ni o‘chirish → `422 last_super_admin`; `is_active=0` admin botga `/help` → jim ignore.
```

> **Eslatma:** raqamlashni tartibga soling (`3.` ikki marta bo‘lmasin — yuqoridagi diff’da `3.` ni `2a.` yoki keyingi raqamga o‘tkazing).

---

## 6. `### Serverga deploy qilish` — `tar` deploy buyrug‘ini yangilash

Mavjud `tar` deploy blokiga migration eslatmasi:

```diff
 Hozirgi production cPanel’da source repo `/home/host9590/apps/otaboyev-sardorbek.uz`, web root esa `/home/host9590/public_html`. Serverda Node 22 `source /opt/alt/alt-nodejs22/enable` bilan yoqiladi. `rsync` mavjud emas; deploy `tar` bilan bajariladi va web rootdagi `config.local.php` hamda `.well-known/` saqlanadi:

 ```bash
 cd /home/host9590/apps/otaboyev-sardorbek.uz
 git pull --ff-only origin main
 source /opt/alt/alt-nodejs22/enable
 npm ci && npm run build
 tar -C out --exclude='config.local.php' -cf - . | tar -C /home/host9590/public_html -xf -
 ```

-Deploydan keyin `/api.php/health`, `/uz/`, `/admin/config` va login oqimini tekshiring.
+**RBAC migration (bir marta, deploy’dan oldin yoki keyin):**
+
+```bash
+mysql -u USER -p DB_NAME < database/migrate-admin-role.sql
+mysql -u USER -p DB_NAME -e "SHOW COLUMNS FROM admin_users LIKE 'role';"
+mysql -u USER -p DB_NAME -e "SELECT id, telegram_id, role, is_active FROM admin_users;"
+```
+
+**Bot komandalar (deploy’dan keyin):**
+
+```bash
+source /home/host9590/apps/otaboyev-sardorbek.uz/config.local.php  # yoki BOT_TOKEN ni env orqali bering
+BOT_TOKEN="..." ./scripts/set-bot-commands.sh
+```
+
+Deploydan keyin `/api.php/health`, `/uz/`, `/admin/config`, real Telegram login va bot smoke (`/whoami`, `/messages`, `/stats`) ni tekshiring.
```

---

## 7. `## Muammolarni bartaraf qilish` — yangi kichik bo‘limlar

Mavjud bo‘limga `### Telegram bot javob bermasa` yonida yangi 3 ta bo‘lim qo‘shing:

````markdown
### Bot javob bermayapti (multi-admin)

- **Bot umuman javob bermayapti:**
  - `SELECT telegram_id, is_active, role FROM admin_users;` — foydalanuvchi `is_active = 1` bo‘lishi shart. `is_active = 0` bo‘lsa bot **jim ignore** qiladi (bu xavfsizlik xususiyati, xato emas).
  - `config.local.php` dagi `admin.telegram_id` faqat **bootstrap fallback** — jadval bo‘sh yoki DB yo‘q bo‘lsagina ishlaydi. Jadvallar to‘ldirilgandan keyin bu ID faqat birinchi super bo‘lib qoladi.
- **`/send` yoki `/reply` ishlamayapti:**
  - Rate limit: `/send` 10/min, `/reply` 10/min. 11-chi chaqiriq `false` qaytaradi → “Juda ko‘p email so‘rovi” javobi. Bir daqiqa kuting.
  - `backend/bot.php:bot_send_email()` `mail` yoki SMTP driver ishlatadi. `ADMIN_MAIL_DRIVER=log` productionda **ishlamaydi** — `mail` yoki `smtp` qo‘ying.
- **`/inbox` / `/read` ishlamayapti:**
  - `bot.imap_host=panel2.eskiz.uz`, port `993`, `imap_ssl=true`, `bot.imap_pass` cPanel’dagi parol bilan bir xil bo‘lsin.
  - `php -m | grep imap` — PHP `imap` kengaytmasi yoqilgan bo‘lsin.
- **Bir xil `/send` ikki marta bajarilmoqda:**
  - `bot_updates_seen` jadvali mavjudligini tekshiring (`SHOW TABLES LIKE 'bot_updates_seen';`). Bo‘lmasa `database/migrate-bot-guards.sql` import qiling.
- **`/whoami`, `/messages` “Not found” qaytaradi:**
  - Kod yangilanganini tekshiring: `grep -n "bot_find_admin\|/whoami" backend/bot.php`.
  - `bot_find_admin()` `admin_users` ga bog‘liq; `is_active=1` va `telegram_id` to‘g‘ri bo‘lsin.

### “403 forbidden” admin panelida

- `/api.php/admin/admins` yoki `/admins/{id}` da `403 forbidden`:
  - Siz `role='admin'` siz. **Adminlar** tab super adminlar uchun; boshqa adminlarni faqat super boshqaradi.
  - `SELECT role FROM admin_users WHERE telegram_id='<SIZNING_ID>';` bilan tasdiqlang.
  - Super bo‘lish kerak bo‘lsa: boshqa super admin sizni `role='super'` ga ko‘tarsin yoki `UPDATE admin_users SET role='super' WHERE telegram_id='<SIZNING_ID>';` (owner qo‘lda).
- `/api.php/admin/admins` da `422 last_super_admin`:
  - Oxirgi faol super’ni o‘chirish/deaktiv/`admin`ga tushirish bloklangan. Avval boshqa super qo‘shing, keyin o‘zgartirish kiriting.

### “401 auth_required” kutilmaganda

- `is_active = 0` qilingan bo‘lsangiz, **keyingi so‘rovda** sessiya bekor bo‘ladi. Qayta login kerak.
- Super admin sizni `role='admin'` ga tushirgan bo‘lsa, `/admins` `403 forbidden` qaytaradi; sessiya yashaydi.
- Session `expires_at` o‘tgan (12 soat) — qayta login.
- Cookie’ni o‘chirib ko‘ring, so‘ng `/admin/` dan qayta kiring.
````

---

## 8. `### Admin panelga kirib bo‘lmasa` — RBAC qo‘shimchasi

Mavjud ro‘yxatga:

```diff
 ### Admin panelga kirib bo‘lmasa
 - `/api.php/admin/config` da `enabled` `true` ekanini tekshiring.
 - `APP_SECRET` aynan 64 hex belgi bo‘lsin; `TELEGRAM_BOT_USERNAME` `@` siz bo‘lsin.
 - BotFather’da `/setdomain otaboyev-sardorbek.uz` bajarilgan va sayt HTTPS’da ochilgan bo‘lsin.
 - `ADMIN_TELEGRAM_ID` `@userinfobot` dagi ID bilan bir xil bo‘lsin.
 - Email kelmasa: cPanel mailbox, spam papkasi, SPF/DKIM/DMARC va `ADMIN_MAIL_FROM` domenini tekshiring. Lokalda `ADMIN_MAIL_DRIVER=log` va `ADMIN_MAIL_LOG` ishlatiladi; productionda `log` ishlamaydi.
 - `db_unavailable` bo‘lsa `config.local.php` va MySQL importini tekshiring.
+- **`role` ustuni yo‘q:** `SHOW COLUMNS FROM admin_users LIKE 'role';` bo‘sh bo‘lsa `database/migrate-admin-role.sql` import qiling. Kod `role` ni kutadi; migration’siz `admin_handle_admins()` xato berishi mumkin.
+- **`enabled: false` va `admin_users` bo‘sh:** birinchi login `config.telegram_id` asosida `role='super'` bilan yaratiladi. Agar `config.telegram_id` bo‘sh yoki noto‘g‘ri bo‘lsa, bootstrap ishlamaydi.
+- **`admin_users` da super yo‘q:** kod safety net birinchi login’da `config.telegram_id` ni avtomatik `super` qiladi; agar bu ishlamasa, qo‘lda `UPDATE admin_users SET role='super' WHERE telegram_id='<OWNER_ID>';`.
```

---

## 9. `## Contact form / Content API` — `bot_notify_admins` eslatmasi

Mavjud `Contact form `id=contact` ...` paragrafiga qo‘shimcha:

```diff
-Contact form `id=contact` bilan `form_contact[ed-f-1..4]` (ism, email, mavzu, xabar), `lang` va bo‘sh `website` honeypot yuboradi. Xabar `contact_messages` jadvaliga yoziladi; IP bo‘yicha 10 daqiqada 5 tadan ko‘p yuborilsa `429`, DB yo‘q bo‘lsa `503` qaytadi (frontend `mailto` fallback’ga o‘tadi). Admin panelda **Xabarlar** bo‘limidan o‘qish, read/unread va o‘chirish mumkin.
+Contact form `id=contact` bilan `form_contact[ed-f-1..4]` (ism, email, mavzu, xabar), `lang` va bo‘sh `website` honeypot yuboradi. Xabar `contact_messages` jadvaliga yoziladi; IP bo‘yicha 10 daqiqada 5 tadan ko‘p yuborilsa `429`, DB yo‘q bo‘lsa `503` qaytadi (frontend `mailto` fallback’ga o‘tadi). **Muvaffaqiyatli saqlashdan keyin `bot_notify_admins()` barcha faol adminlarga Telegram xabar yuboradi** (best-effort; bot API/DB fail bo‘lsa forma natijasiga ta’sir qilmaydi). Admin panelda **Xabarlar** bo‘limidan o‘qish, read/unread va o‘chirish mumkin; botda `/messages`, `/message <id>`, `/reply <id> | <matn>` orqali.
```

---

## 10. `## Git workflow` / `## SSH deployment` — o‘zgartirmang

Bu bo‘limlarga tegish shart emas.

---

## 11. Kod ↔ README mosligi

Yakuniy tekshiruv:

```bash
# Har bir komanda README da
for c in /whoami /messages /unread /message /reply /stats /inbox /read /send /help; do
  printf "%-10s " "$c"; grep -c "$c" README.md
done

# Migration
grep -n "migrate-admin-role" README.md
grep -n "migrate-bot-guards" README.md

# setMyCommands
grep -n "setMyCommands" README.md

# RBAC
grep -nE "last_super_admin|admin_require_super|super only|role='super'" README.md

# Kod bilan solishtirish
grep -n "bot_find_admin\|bot_notify_admins\|bot_audit" backend/bot.php
grep -n "admin_require_super\|last_super_admin" backend/admin.php
```

Kutilgan: README’da har bir komanda, `migrate-admin-role.sql`, `setMyCommands`, `last_super_admin` uchraydi.

---

## 12. Commit xabari

```bash
docs(readme): document RBAC, bot multi-admin commands and role migration

- "Admin panel" bo'limiga rollar jadvali (super/admin) va qoidalar
- "Telegram bot email bridge" bo'limiga multi-admin, yangi komandalar,
  setMyCommands, contact → Telegram signal
- "Admin API" jadvalida super-only belgisi
- cPanel deploy va tar-deploy qadamlariga migrate-admin-role.sql
- "Muammolarni bartaraf qilish" da bot/403/401 uchun yangi kichik bo'limlar
```

---

## 13. Yakuniy holat — barcha hujjatlar

Endi 5 ta hujjat birga yangilangan:

| Hujjat | Yangilangan qismlar |
|---|---|
| `AGENTS.md` | `Arxitektura → Admin auth (web + bot)`, `Admin auth va xavfsizlik`, `Qoidalar` (RBAC/bot) |
| `tz.md` | Snapshot, Active work, Baseline completed, Backlog (yangi P1/P2/P3), Next actions |
| `tz_master.md` | §2 Backend, §3 Data model (`admin_users.role`), §4 API, §5 Admin security, §7 QA, §8 Roadmap (Phase 2a), §9 Migration history, §11 Final guidance |
| `SECURITY.md` | §1 Auth modeli (1.1–1.16), §2 Test rejasi (2.1–2.19), Top 20 (#9a), cPanel checklist, Zaifliklar reyestri (V-22…V-25), §13 Tarix (3.2) |
| `README.md` | Xususiyatlar, Admin panel (rollar + bot), Admin API, cPanel deploy, tar-deploy, Muammolarni bartaraf qilish, Contact form eslatmasi |

**Commit tartibi (Conventional Commits, alohida yoki birlashtirilgan):**

```
feat(admin): add super/admin roles with last-super guard
feat(bot): multi-admin authorization and new /messages commands
feat(contact): notify active admins via Telegram on new messages
chore(db): add migrate-admin-role.sql and update schema.sql
docs(agents,tz,master,security,readme): document RBAC and bot multi-admin
```

# `tz_master.md` §6–§7 + CI workflow + `tests/bot-guard-test.php`

Quyida beshta blok:

1. **`migrate-admin-role.sql` tuzatish** (oldindan yuborilgan migratsiyaning oxirgi `UPDATE` MySQL 5.7’da errno 1093 berishi mumkin — xavfsizroq shakl)
2. **`tz_master.md` §6 Deployment model** — to‘liq yangilangan
3. **`tz_master.md` §7 QA** — to‘liq yangilangan (RBAC + bot multi-admin)
4. **`.github/workflows/ci.yml`** — to‘liq yangi workflow
5. **`tests/bot-guard-test.php`** — yangi fayl (bot guard unit testlari)

---

## 0. FYI — `migrate-admin-role.sql` oxirgi `UPDATE` tuzatish

Oldingi migratsiyada oxirgi `UPDATE` subquery bilan yozilgan edi:

```sql
UPDATE admin_users
   SET role = 'super'
 WHERE id = (SELECT id FROM (SELECT MIN(id) AS id FROM admin_users) AS first_admin)
   AND NOT EXISTS (SELECT 1 FROM admin_users WHERE role = 'super');
```

Bu MySQL 5.7’da **errno 1093** (“You can't specify target table for update in FROM clause”) berishi mumkin. Xavfsizroq shakl — user variable bilan:

```diff
--- database/migrate-admin-role.sql
+++ database/migrate-admin-role.sql
@@
 ALTER TABLE admin_users
   ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'admin' AFTER is_active,
   ADD KEY idx_admin_users_role (role);

--- Safety net: promote the earliest admin to super so the panel isn't locked
--- out when the owner logs in with a different Telegram ID than expected.
-UPDATE admin_users
-   SET role = 'super'
- WHERE id = (SELECT id FROM (SELECT MIN(id) AS id FROM admin_users) AS first_admin)
-   AND NOT EXISTS (SELECT 1 FROM admin_users WHERE role = 'super');
+-- Safety net: promote the earliest admin to super so the panel isn't locked
+-- out when the owner logs in with a different Telegram ID than expected.
+-- Split into variables to avoid MySQL errno 1093 (can't update from same
+-- subquery). Safe on MySQL 5.7 / MariaDB 10.2+.
+SET @has_super := (SELECT COUNT(*) FROM admin_users WHERE role = 'super');
+SET @first_admin := (SELECT MIN(id) FROM admin_users);
+UPDATE admin_users
+   SET role = 'super'
+ WHERE id = @first_admin
+   AND @has_super = 0;
```

Bu o‘zgarish CI’da `mysql < file` sifatida ishlatiladi. User variable’lar `mysql` CLI’da skript davomida saqlanadi.

---

## 1. `tz_master.md` — `## 6. Deployment model` (to‘liq yangilangan)

Mavjud bo‘limni almashtiring:

````markdown
## 6. Deployment model

### 6.1. Runtime

- **Build artifact:** `out/` published to the web root.
- **Runtime:** PHP + MySQL only; no Passenger/Node runtime.
- **Primary server:** Apache with `.htaccess` (`mod_rewrite`, `mod_headers`).
- **Nginx:** `.htaccess` is ignored; equivalent `try_files`/rewrite is required for the generic detail fallback, plus `error_page 404 /404.html` for real 404s.
- **Build:** `npm ci && npm run build` locally or on the server; copy `out/` to the web root. The build never copies `config.local.php`; `run_local.sh`/`run_server.sh` copy it into `out/` with mode `600` for preview only, and production places it in the web root manually.

### 6.2. Database migrations — order

Migrations are one-time and applied to the production DB **before** the code that depends on them is deployed. Order is fixed:

| # | File | Idempotent | Maqsad |
|---|---|---|---|
| 1 | `database/schema.sql` | ✅ `CREATE TABLE IF NOT EXISTS` | Fresh install (yangi baza) |
| 2 | `database/seed.sql` | ✅ `INSERT ... ON DUPLICATE KEY UPDATE` | Demo content (1 profil, 3 loyiha, 9 tarjima) — **production’da faqat bir marta, admin tahrirlaridan oldin** |
| 3 | `database/migrate-project-sections.sql` | ❌ (bir marta) | `project_translations.results` + `stack_notes` |
| 4 | `database/migrate-contact-messages.sql` | ✅ | `contact_messages` jadvali |
| 5 | `database/migrate-bot-guards.sql` | ✅ | `bot_updates_seen`, `bot_rate_limit` |
| 6 | `database/migrate-admin-role.sql` | ❌ (bir marta; `ADD COLUMN`) | `admin_users.role` + `idx_admin_users_role` + birinchi adminni `super` qilish |

Fresh install (yangi baza):

```bash
mysql -u USER -p DB_NAME < database/schema.sql
mysql -u USER -p DB_NAME < database/seed.sql
mysql -u USER -p DB_NAME < database/migrate-bot-guards.sql
```

Existing DB (schema’dan oldin yaratilgan):

```bash
mysql -u USER -p DB_NAME < database/migrate-project-sections.sql
mysql -u USER -p DB_NAME < database/migrate-contact-messages.sql
mysql -u USER -p DB_NAME < database/migrate-bot-guards.sql
mysql -u USER -p DB_NAME < database/migrate-admin-role.sql
```

**Verification (deploy oldidan va keyin):**

```sql
-- RBAC column
SHOW COLUMNS FROM admin_users LIKE 'role';
-- kutilgan: role | varchar(20) | NO | | admin |

-- Kamida 1 faol super
SELECT id, telegram_id, role, is_active FROM admin_users;
-- kutilgan: kamida 1 qatorda role='super', is_active=1

-- Bot guard jadvallari
SHOW TABLES LIKE 'bot_updates_seen';
SHOW TABLES LIKE 'bot_rate_limit';
```

`migrate-admin-role.sql` **non-idempotent** — ikkinchi marta ishga tushirilsa `Duplicate column name 'role'` xatosi beradi. CI buni ataylab tekshiradi (quyida).

### 6.3. Production deploy — tartib

Current production: source `/home/host9590/apps/otaboyev-sardorbek.uz`, web root `/home/host9590/public_html`, Node 22 via `/opt/alt/alt-nodejs22/enable`; deploy with `tar` because `rsync` is absent, preserving `config.local.php` and `.well-known/`.

**Qadamlar:**

1. **Migration (production DB):**

   ```bash
   cd /home/host9590/apps/otaboyev-sardorbek.uz
   git pull --ff-only origin main

   mysql -u USER -p DB_NAME < database/migrate-bot-guards.sql       # idempotent
   mysql -u USER -p DB_NAME < database/migrate-admin-role.sql       # bir marta

   mysql -u USER -p DB_NAME -e "SHOW COLUMNS FROM admin_users LIKE 'role';"
   mysql -u USER -p DB_NAME -e "SELECT id, telegram_id, role, is_active FROM admin_users;"
   ```

   Kamida 1 faol `super` bo‘lishi shart. Agar migratsiya `super` yarata olmagan bo‘lsa (masalan, `admin_users` bo‘sh edi), birinchi login safety net’i `config.telegram_id` asosida `super` yaratadi.

2. **Build (server yoki lokal):**

   ```bash
   source /opt/alt/alt-nodejs22/enable
   npm ci
   npm run build
   ```

   `out/` ichida `config.local.php`, `config.local.js`, `github_keys.txt` **yo‘qligi** tasdiqlanadi (CI va `copy-static.mjs` ham buni tekshiradi).

3. **Tar-deploy** (existing `config.local.php` va `.well-known/` saqlanadi):

   ```bash
   tar -C out --exclude='config.local.php' -cf - . | tar -C /home/host9590/public_html -xf -
   ```

4. **`config.local.php`** web rootda `chmod 600` bilan qo‘lda. `out/` ichida bo‘lmasligi kerak.

5. **`setMyCommands`** (bot komandalar ro‘yxati):

   ```bash
   cd /home/host9590/apps/otaboyev-sardorbek.uz
   BOT_TOKEN="$(php -r "require 'config.local.php'; echo (require 'config.local.php')['admin']['telegram_bot_token'];")" \
     ./scripts/set-bot-commands.sh
   ```

   Yoki `curl` orqali qo‘lda (yuqoridagi “setMyCommands” bo‘limi — README’da). `getWebhookInfo` `pending_update_count=0` bo‘lishi kerak.

6. **Post-deploy smoke (RBAC + bot):**

   ```bash
   curl -sS https://otaboyev-sardorbek.uz/api.php/health              # {"status":"ok"}
   curl -sS https://otaboyev-sardorbek.uz/api.php/admin/config        # enabled=true
   # Super admin bilan /admin/ → Telegram → OTP → dashboard; admin.role = 'super'
   # Yangi admin (role=admin) qo‘shib, real login:
   curl -sS -H "Cookie: admin_session=..." https://otaboyev-sardorbek.uz/api.php/admin/admins  # 403 forbidden
   # Oxirgi faol super’ni o‘chirishga urinish → 422 last_super_admin
   # is_active=0 admin botda /help → jim ignore
   # Contact forma → barcha faol adminlarga Telegram xabar
   ```

7. **Audit tekshirish:**

   ```sql
   SELECT action, entity, details, created_at
     FROM admin_audit_log
    ORDER BY id DESC
    LIMIT 20;
   -- admin_created / admin_updated / admin_deleted + role
   -- entity='bot' uchun bot_message_read / bot_message_reply / bot_email_sent
   ```

### 6.4. Environment groups

| Group | Keys |
|---|---|
| DB | `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, optional `DB_SOCKET` |
| Admin | `SITE_URL`/`APP_URL`, `APP_ENV`, `APP_SECRET`/`ADMIN_APP_SECRET`, `TELEGRAM_BOT_TOKEN`, `TELEGRAM_BOT_USERNAME`, `ADMIN_TELEGRAM_ID`, `ADMIN_EMAIL`, `ADMIN_PHONE`, TTL/attempt settings |
| Bot/IMAP | `bot.webhook_secret`, `bot.imap_host` (`panel2.eskiz.uz`), `bot.imap_port` (`993`), `bot.imap_user`, `bot.imap_pass`, `bot.imap_ssl` |
| Mail | `ADMIN_MAIL_DRIVER`/`MAIL_DRIVER`, `ADMIN_MAIL_FROM`, `ADMIN_MAIL_FROM_NAME`, log file, Resend/Brevo keys, SMTP host/port/secure/user/pass |
| Tokens | `API_TOKEN`, `SITEHUB_API_TOKEN`, `OTABOYEV_API_TOKEN` |

DB config: gitignored `config.local.php` or `DB_*` environment variables. Production DB `host9590_portfolio` is least-privilege (`SELECT, INSERT, UPDATE, DELETE`; DDL temporarily for migrations).

Production requires HTTPS, a real DB, Telegram bot/ID, email credentials, bot webhook secret and `APP_SECRET`.

### 6.5. CI

CI exists in `.github/workflows/ci.yml` and runs on every push/PR:

1. `npm ci`
2. `npm run check` (PHP/JS/Bash lint)
3. Static RBAC checks (`admin_require_super`, `last_super_admin`, `bot_find_admin`, `bot_notify_admins`, migration files, `setMyCommands`)
4. MySQL service: import `schema.sql` + `seed.sql` + idempotent `migrate-bot-guards.sql` (twice) + `migrate-admin-role.sql` on a legacy-shaped DB
5. `tests/bot-guard-test.php` (real DB; no Telegram)
6. `npm run build` + `out/` artefakt secret-free check

Full workflow in `.github/workflows/ci.yml`. Server-only PHP changes must be verified manually with the same steps.
````

---

## 2. `tz_master.md` — `## 7. QA` (to‘liq yangilangan)

Mavjud bo‘limni almashtiring:

````markdown
## 7. QA

### 7.1. Local preview

```bash
npm run check
npm run build
DB_SOCKET=... DB_NAME=... DB_USER=... DB_PASS=... ./run_local.sh
# or: DB_SOCKET=... DB_NAME=... DB_USER=... DB_PASS=... php -S 127.0.0.1:8000 -t out
```

`run_local.sh` `config.local.php` ni `out/` ga `600` bilan nusxalaydi. `.htaccess` PHP built-in server’da ishlamaydi — rewrite/redirect xatti-harakati Apache’da alohida tekshiriladi.

### 7.2. Static export — artefakt

- `out/` contains `.htaccess`, `api.php`, `404.html`, `backend/`, admin pages.
- `out/config.local.php`, `out/config.local.js`, `out/github_keys.txt` **yo‘q**.
- `out/uz/`, `out/en/`, `out/ru/` — 3 home; `out/{uz,en,ru}/projects/` — 3 listing; 9 known detail; 3 generic `view` route.
- CI ham buni tekshiradi.

### 7.3. Frontend va SEO

- `/uz/`, `/en/`, `/ru/` load without console/network/hydration errors.
- One known detail per locale, its language switcher and API refresh.
- One admin-created slug through Apache → `/projects/view/?slug=...` fallback.
- Canonical + hreflang on 3 homes + 3 listings + 9 details.
- CV links resolve (`/files/sardorbek-otaboyev-cv-{uz,en,ru}.{pdf,txt}`).

### 7.4. Content API

- `GET /api.php/profile?lang=uz|en|ru` — valid envelope, `role`, `bio`, `quick_text`, `location`.
- `GET /api.php/projects?lang=...` — massiv, faqat `is_active=1`.
- `GET /api.php/projects/gitinsight?lang=...` — `results[]` + `stack_notes[]`.
- Invalid `lang` → `uz` fallback.
- Missing slug → `404 project_not_found`.
- DB yo‘q → `503 db_unavailable`; frontend statik fallback.
- CORS `*` faqat public read-only (`/profile`, `/projects`, `/projects/{slug}`, `/health`, `/status`); `/form_container/submit`, `/admin/*`, `/bot/webhook`, `/websites` CORS header **yo‘q**.
- HTTP orqali HSTS **yo‘q**; HTTPS orqali `Strict-Transport-Security: max-age=31536000`.

### 7.5. Contact form va admin inbox

- Forma `POST /api.php/form_container/submit` (`id=contact`, `form_contact[ed-f-1..4]`, `lang`, `website` bo‘sh).
- `contact_messages` da yangi qator.
- Admin panelda **Xabarlar** — count/list/read/unread/delete.
- Honeypot → `400`; invalid email → `422`; 6-chi so‘rov (10 daqiqa) → `429`; 11-chi global IP → `429`.
- DB yo‘q → `503`; frontend `mailto:` fallback.
- **`bot_notify_admins()`** muvaffaqiyatli saqlashdan keyin chaqiriladi — barcha faol adminlarga Telegram xabar. Bot API/DB fail → forma `200`, `error_log`.

### 7.6. Admin auth — web

- `/api.php/admin/config` → `enabled:true` (production’da).
- Telegram callback → challenge cookie → email OTP (log driver lokalda) → session.
- Noto‘g‘ri OTP 5 marta → 6-chida `429 too_many_attempts`.
- Expired challenge (11 daqiqa) → `401 challenge_expired`.
- Expired session (12 soat) → `401 auth_required`.
- CSRF header yo‘q → `403 csrf_failed`.
- `Origin: null` / `Origin: evil` / header’siz CLI → `403 origin_rejected`; `X-Requested-With: XMLHttpRequest` bilan header’siz CLI → o‘tadi.
- Admin response’larda CORS header yo‘q.
- Logout `POST` → session tozalanadi.

### 7.7. RBAC (2026-10-23)

**Setup:** test DB, 2 admin — `A` (`role='super'`), `B` (`role='admin'`), ikkalasi `is_active=1`.

| # | Test | Kutilgan |
|---|---|---|
| 7.7.1 | `GET /api.php/admin/session` (A) | `admin.role='super'` |
| 7.7.2 | `GET /api.php/admin/session` (B) | `admin.role='admin'` |
| 7.7.3 | A dashboard → **Adminlar** tab | ko‘rinadi |
| 7.7.4 | B dashboard → **Adminlar** tab | **ko‘rinmaydi** (yashirin) |
| 7.7.5 | `GET /api.php/admin/admins` (A) | `200` |
| 7.7.6 | `GET /api.php/admin/admins` (B) | `403 forbidden` |
| 7.7.7 | `POST /api.php/admin/admins` (B) | `403 forbidden` |
| 7.7.8 | `DELETE /api.php/admin/admins/{A}` (B) | `403 forbidden` |
| 7.7.9 | `POST /api.php/admin/admins` (A, role=`admin`) | `201`, `role='admin'` |
| 7.7.10 | `POST /api.php/admin/admins` (A, role=`super`) | `201`, `role='super'` |
| 7.7.11 | `POST /api.php/admin/admins` (A, role=`owner`) | `422 validation_failed`, `errors.role` |
| 7.7.12 | A o‘zini `is_active=0` | `422 cannot_deactivate_self` |
| 7.7.13 | A o‘zini o‘chirish | `422 cannot_delete_self` |
| 7.7.14 | A o‘zini `role='admin'` (boshqa super yo‘q) | `422 last_super_admin` |
| 7.7.15 | A o‘zini `role='admin'` (B yoki C super faol) | `200` |
| 7.7.16 | Oxirgi faol super’ni `DELETE` | `422 last_super_admin` |
| 7.7.17 | Oxirgi faol super’ni `is_active=0` | `422 last_super_admin` |
| 7.7.18 | `admin_audit_log` oxirgi 5 yozuv | `admin_created/updated/deleted` + `role` |
| 7.7.19 | B `GET /api.php/admin/profile`, `PUT /api.php/admin/profile`, `POST /api.php/admin/projects`, `GET /api.php/admin/messages`, `POST /api.php/admin/sitemap` | hammasi ruxsat (`200`/`201`) |

**Session/role invalidation (kritik):**

| # | Test | Kutilgan |
|---|---|---|
| 7.7.20 | A login (super), B login (admin) | ikkala session faol |
| 7.7.21 | A → B `role='super'` | B keyingi `/admins` → `200` (darhol) |
| 7.7.22 | A → B `role='admin'` | B keyingi `/admins` → `403 forbidden` (darhol) |
| 7.7.23 | A → B `is_active=0` | B keyingi so‘rov → `401 auth_required` (darhol) |
| 7.7.24 | A → B `is_active=1` | B eski session ishlamaydi; qayta login kerak |
| 7.7.25 | A → B `DELETE` | B session `401` |

### 7.8. Bot multi-admin (2026-10-23)

**Setup:** test DB, `bot.webhook_secret` valid, 2 faol admin — `A` (`role='super'`), `B` (`role='admin'`), `C` (`is_active=0`). Fake update skript:

```bash
send_update() {
  local uid=$1; local tg=$2; local text=$3
  curl -sS -X POST "http://127.0.0.1:8000/api.php/bot/webhook" \
    -H "X-Telegram-Bot-Api-Secret-Token: <secret>" \
    -H "Content-Type: application/json" \
    --data "{\"update_id\":$uid,\"message\":{\"message_id\":$uid,\"from\":{\"id\":$tg},\"chat\":{\"id\":$tg,\"type\":\"private\"},\"text\":\"$text\"}}"
}
```

| # | Test | Kutilgan |
|---|---|---|
| 7.8.1 | `GET /api.php/bot/webhook` | `405`, `Allow: POST` |
| 7.8.2 | `POST` secretsiz | `403` |
| 7.8.3 | `POST` noto‘g‘ri secret | `403` |
| 7.8.4 | `POST` secret to‘g‘ri, `from.id=999` (yo‘q) | `200 'ok'`, Telegram’ga **hech narsa** |
| 7.8.5 | `POST` secret to‘g‘ri, C (`is_active=0`) | `200 'ok'`, **jim ignore** |
| 7.8.6 | `POST` secret to‘g‘ri, A, `/help` | `200 'ok'`, yordam matni |
| 7.8.7 | `POST` secret to‘g‘ri, B, `/whoami` | `200 'ok'`, ism + telegram_id + email |
| 7.8.8 | `POST` `chat.type='group'` | `200 'ok'`, jim ignore |
| 7.8.9 | `POST` `chat.id != from.id` | `200 'ok'`, jim ignore |
| 7.8.10 | Body > 131072 bayt | `413` |
| 7.8.11 | Bir xil `update_id` ikki marta | 1-chi `200` (amal), 2-chi `200` (amal **yo‘q**); `bot_updates_seen` 1 qator |
| 7.8.12 | `bot_rate_limit_ok(333333,'send',10)` 11-chi chaqiriq | `false` |
| 7.8.13 | A → B `is_active=0` | ✅ |
| 7.8.14 | B `/help` | `200 'ok'`, **jim ignore** |
| 7.8.15 | A → B `is_active=1` | ✅ |
| 7.8.16 | B `/help` | javob qaytadi |
| 7.8.17 | A → B `DELETE` | ✅ |
| 7.8.18 | B `/help` | **jim ignore** |
| 7.8.19 | `admin_users` bo‘sh + `config.telegram_id` bilan `/help` | bootstrap: javob; `admin_users` da 1 row `role='super'` |

**Bot komandalar (faol admin):**

| # | Komanda | Kutilgan |
|---|---|---|
| 7.8.20 | `/whoami` | ism + telegram_id + email |
| 7.8.21 | `/messages` (bo‘sh inbox) | “Contact inbox bo‘sh.” |
| 7.8.22 | Contact forma → `/messages` | ro‘yxatda `#id` |
| 7.8.23 | `/message <id>` | to‘liq xabar; DB’da `is_read=1` |
| 7.8.24 | `/unread` | faqat `is_read=0` |
| 7.8.25 | `/reply <id> \| test` | email yuboriladi; `admin_audit_log` da `bot_message_reply` |
| 7.8.26 | `/reply <id>` (pipe’siz) | format xatosi |
| 7.8.27 | `/reply 999999 \| test` | “Xabar yoki email topilmadi.” |
| 7.8.28 | `/stats` | `unread` + `total` + `active admins` |
| 7.8.29 | `/inbox` (IMAP sozlanmagan) | “Email qutisiga ulanib bo‘lmadi.” |
| 7.8.30 | `/send email@example.com \| M \| B` | email yuboriladi; `admin_audit_log` da `bot_email_sent` |
| 7.8.31 | `/send not-an-email \| M \| B` | `Invalid email address` |
| 7.8.32 | Noma’lum matn | `/help` qaytadi |

### 7.9. Migration va idempotentlik

| # | Test | Kutilgan |
|---|---|---|
| 7.9.1 | `migrate-bot-guards.sql` 1-marta | OK; 2 jadval yaratiladi |
| 7.9.2 | `migrate-bot-guards.sql` 2-marta | OK (idempotent) |
| 7.9.3 | `migrate-admin-role.sql` 1-marta (legacy DB) | OK; `role` ustuni qo‘shiladi; kamida 1 `super` |
| 7.9.4 | `migrate-admin-role.sql` 2-marta | **Xato:** `Duplicate column name 'role'` (kutilgan, non-idempotent) |
| 7.9.5 | Fresh `schema.sql` | `role VARCHAR(20) NOT NULL DEFAULT 'admin'` |
| 7.9.6 | Eski bazada (role yo‘q) birinchi login | safety net `role='super'` |
| 7.9.7 | `admin_users` bo‘sh, config ID bilan login | bootstrap `role='super'` |

### 7.10. Apache (`.htaccess`)

- `/de/`, `/nope` → real 404 + `404.html`.
- `/{locale}/projects/<admin-slug>/` → `200` (view fallback rewrite).
- `/api.php/admin/...` PATH_INFO fallback ushlanmaydi.
- `config.local.php`, `github_keys.txt`, `backend/`, `database/` → `403`.
- `/api.php/health` → `200`.
- `OPTIONS /api.php/profile` → `Access-Control-Allow-Origin: *`.
- `OPTIONS /api.php/form_container/submit` → CORS **yo‘q**.
- `POST /api.php/admin/logout` `Origin: evil` → `403 origin_rejected`.
- HTTPS → `Strict-Transport-Security: max-age=31536000`; HTTP → header **yo‘q**.

### 7.11. Frontend headless

- `/uz/`, `/en/`, `/ru/`, `/uz/projects/`, known detail, `/admin/`, authenticated `/admin/dashboard/` — no console/CSP/request-failure or hydration errors.

### 7.12. Diff

- `git diff --check`
- `git diff --stat`
- Full diff review before commit.
````

---

## 3. `.github/workflows/ci.yml` — to‘liq yangi workflow

`.github/workflows/ci.yml` ni quyidagiga almashtiring:

```yaml
name: CI

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

concurrency:
  group: ci-${{ github.ref }}
  cancel-in-progress: true

permissions:
  contents: read

jobs:
  lint-and-test:
    name: Lint, schema, RBAC, bot guards, build
    runs-on: ubuntu-latest
    timeout-minutes: 20

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: test_portfolio
          MYSQL_USER: test
          MYSQL_PASSWORD: test
        ports:
          - 3306:3306
        options: >-
          --health-cmd="mysqladmin ping -h 127.0.0.1 -uroot -proot --silent"
          --health-interval=5s
          --health-timeout=5s
          --health-retries=20

    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: pdo_mysql, curl, openssl, mbstring, imap
          coverage: none

      - name: Setup Node
        uses: actions/setup-node@v4
        with:
          node-version: '22'
          cache: 'npm'

      - name: Install npm dependencies
        run: npm ci

      - name: PHP/JS/Bash lint
        run: npm run check

      - name: Static RBAC and bot guards present
        run: |
          set -e
          echo "== Files =="
          test -f database/schema.sql
          test -f database/migrate-bot-guards.sql
          test -f database/migrate-admin-role.sql
          test -f scripts/set-bot-commands.sh
          test -f tests/bot-guard-test.php
          echo "OK: required files present"

          echo "== schema.sql: admin_users.role =="
          grep -q "role VARCHAR(20) NOT NULL DEFAULT 'admin'" database/schema.sql
          grep -q "idx_admin_users_role" database/schema.sql
          echo "OK: role column in schema.sql"

          echo "== backend/admin.php: RBAC guards =="
          grep -q "function admin_require_super" backend/admin.php
          grep -q "function admin_has_other_active_super" backend/admin.php
          grep -q "last_super_admin" backend/admin.php
          grep -q "admin_require_super(\$session)" backend/admin.php
          echo "OK: admin RBAC guards"

          echo "== backend/bot.php: multi-admin =="
          grep -q "function bot_find_admin" backend/bot.php
          grep -q "function bot_notify_admins" backend/bot.php
          grep -q "function bot_audit" backend/bot.php
          grep -q "function bot_rate_limit_ok(\$telegramId" backend/bot.php
          grep -q "bot_find_admin(\$fromId)" backend/bot.php
          echo "OK: bot multi-admin guards"

          echo "== backend/contact.php: bot_notify_admins =="
          grep -q "bot_notify_admins" backend/contact.php
          echo "OK: contact notify"

          echo "== scripts/set-bot-commands.sh =="
          grep -q "setMyCommands" scripts/set-bot-commands.sh
          echo "OK: setMyCommands script"

      - name: Wait for MySQL
        run: |
          for i in $(seq 1 30); do
            if mysqladmin ping -h 127.0.0.1 -utest -ptest --silent; then
              echo "MySQL ready"
              exit 0
            fi
            sleep 2
          done
          echo "MySQL did not become ready" >&2
          exit 1

      - name: Import schema + seed + bot guards
        env:
          MYSQL_PWD: test
        run: |
          mysql -h 127.0.0.1 -utest test_portfolio < database/schema.sql
          mysql -h 127.0.0.1 -utest test_portfolio < database/seed.sql
          mysql -h 127.0.0.1 -utest test_portfolio < database/migrate-bot-guards.sql

      - name: Verify admin_users.role column
        env:
          MYSQL_PWD: test
        run: |
          set -e
          ROW="$(mysql -h 127.0.0.1 -utest test_portfolio -N -e "SHOW COLUMNS FROM admin_users LIKE 'role'")"
          echo "COLUMN: $ROW"
          [ -n "$ROW" ] || { echo "role column missing" >&2; exit 1; }
          echo "$ROW" | grep -qE '^role[[:space:]]+varchar\(20\)'
          DEFAULT="$(mysql -h 127.0.0.1 -utest test_portfolio -N -e "SELECT COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='test_portfolio' AND TABLE_NAME='admin_users' AND COLUMN_NAME='role'")"
          [ "$DEFAULT" = "admin" ] || { echo "role default expected 'admin', got '$DEFAULT'" >&2; exit 1; }
          IDX="$(mysql -h 127.0.0.1 -utest test_portfolio -N -e "SHOW INDEX FROM admin_users WHERE Key_name='idx_admin_users_role'")"
          [ -n "$IDX" ] || { echo "idx_admin_users_role missing" >&2; exit 1; }
          echo "OK: role column and index"

      - name: Bot guards migration is idempotent
        env:
          MYSQL_PWD: test
        run: |
          set -e
          mysql -h 127.0.0.1 -utest test_portfolio < database/migrate-bot-guards.sql
          mysql -h 127.0.0.1 -utest test_portfolio < database/migrate-bot-guards.sql
          mysql -h 127.0.0.1 -utest test_portfolio -e "SHOW TABLES LIKE 'bot_updates_seen';"
          mysql -h 127.0.0.1 -utest test_portfolio -e "SHOW TABLES LIKE 'bot_rate_limit';"
          echo "OK: bot guards idempotent"

      - name: Admin-role migration on legacy-shaped DB
        env:
          MYSQL_PWD: test
        run: |
          set -e
          # 1. Insert a legacy admin (before role column exists in this shape).
          mysql -h 127.0.0.1 -utest test_portfolio -e \
            "INSERT INTO admin_users (telegram_id, email, display_name, is_active) VALUES (111111, 'legacy@example.com', 'Legacy', 1);"

          # 2. Drop role column to simulate a legacy DB.
          mysql -h 127.0.0.1 -utest test_portfolio -e "ALTER TABLE admin_users DROP COLUMN role;"
          mysql -h 127.0.0.1 -utest test_portfolio -N -e "SHOW COLUMNS FROM admin_users LIKE 'role'" | grep -q . \
            && { echo "role column still present after drop" >&2; exit 1; } || true

          # 3. Apply the migration once.
          mysql -h 127.0.0.1 -utest test_portfolio < database/migrate-admin-role.sql

          # 4. Verify role column + at least one super + legacy admin promoted.
          mysql -h 127.0.0.1 -utest test_portfolio -N -e "SHOW COLUMNS FROM admin_users LIKE 'role'" | grep -qE '^role[[:space:]]+varchar\(20\)'
          LEGACY_ROLE="$(mysql -h 127.0.0.1 -utest test_portfolio -N -e "SELECT role FROM admin_users WHERE telegram_id=111111")"
          [ "$LEGACY_ROLE" = "super" ] || { echo "legacy admin not promoted to super: $LEGACY_ROLE" >&2; exit 1; }
          ACTIVE_SUPERS="$(mysql -h 127.0.0.1 -utest test_portfolio -N -e "SELECT COUNT(*) FROM admin_users WHERE role='super' AND is_active=1")"
          [ "$ACTIVE_SUPERS" -ge 1 ] || { echo "no active super" >&2; exit 1; }
          echo "OK: legacy migration path"

      - name: Admin-role migration is non-idempotent (expected failure on 2nd run)
        env:
          MYSQL_PWD: test
        run: |
          set +e
          mysql -h 127.0.0.1 -utest test_portfolio < database/migrate-admin-role.sql 2>err.log
          STATUS=$?
          set -e
          if [ $STATUS -eq 0 ]; then
            echo "ERROR: migrate-admin-role.sql should not be idempotent" >&2
            exit 1
          fi
          grep -qE "Duplicate column name|already exists" err.log || {
            echo "Expected duplicate-column error, got:" >&2
            cat err.log >&2
            exit 1
          }
          echo "OK: non-idempotent as documented"

      - name: Last-super guard SQL
        env:
          MYSQL_PWD: test
        run: |
          set -e
          mysql -h 127.0.0.1 -utest test_portfolio <<'SQL'
          DELETE FROM admin_users;
          INSERT INTO admin_users (telegram_id, email, is_active, role) VALUES
            (201, 'a@x.c', 1, 'super'),
            (202, 'b@x.c', 1, 'super'),
            (203, 'c@x.c', 1, 'admin');
          SQL

          OTHER_A="$(mysql -h 127.0.0.1 -utest test_portfolio -N -e "SELECT COUNT(*) FROM admin_users WHERE is_active=1 AND role='super' AND id <> 1")"
          [ "$OTHER_A" = "1" ] || { echo "expected 1 other super for A, got $OTHER_A" >&2; exit 1; }

          OTHER_B="$(mysql -h 127.0.0.1 -utest test_portfolio -N -e "SELECT COUNT(*) FROM admin_users WHERE is_active=1 AND role='super' AND id <> 2")"
          [ "$OTHER_B" = "1" ] || { echo "expected 1 other super for B, got $OTHER_B" >&2; exit 1; }

          mysql -h 127.0.0.1 -utest test_portfolio -e "UPDATE admin_users SET is_active=0 WHERE id=2"
          LAST_SUPER="$(mysql -h 127.0.0.1 -utest test_portfolio -N -e "SELECT COUNT(*) FROM admin_users WHERE is_active=1 AND role='super' AND id <> 1")"
          [ "$LAST_SUPER" = "0" ] || { echo "expected 0 other supers for the last one, got $LAST_SUPER" >&2; exit 1; }
          echo "OK: last-super SQL guard"

      - name: Bot guard unit tests (real DB, no Telegram)
        env:
          DB_HOST: 127.0.0.1
          DB_PORT: '3306'
          DB_NAME: test_portfolio
          DB_USER: test
          DB_PASS: test
        run: php tests/bot-guard-test.php

      - name: Build static export
        run: npm run build

      - name: Export artifact is secret-free
        run: |
          set -e
          test -f out/api.php
          test -f out/.htaccess
          test -f out/404.html
          test -d out/backend
          test -d out/admin
          test ! -f out/config.local.php
          test ! -f out/config.local.js
          test ! -f out/github_keys.txt
          test -d out/uz
          test -d out/en
          test -d out/ru
          test -d out/uz/projects
          test -d out/en/projects
          test -d out/ru/projects
          echo "OK: export artifact"

      - name: Upload failure artifacts
        if: failure()
        uses: actions/upload-artifact@v4
        with:
          name: ci-failures
          path: |
            err.log
            out/
          retention-days: 3
```

**Eslatma:** `php -l tests/bot-guard-test.php` ham `npm run check` ga qo‘shilishi kerak (pastda kichik `package.json` diff).

---

## 4. `tests/bot-guard-test.php` — yangi fayl

```php
<?php
// Bot guard unit tests. Uses a real test DB; does NOT hit Telegram.
//
// Run locally:
//   DB_HOST=127.0.0.1 DB_NAME=test_portfolio DB_USER=test DB_PASS=test \
//     php tests/bot-guard-test.php
//
// Requires:
//   - database/schema.sql already imported (admin_users.role exists)
//   - database/migrate-bot-guards.sql applied
//   - php extensions: pdo_mysql
//
// Exit code 0 = all pass; 1 = at least one failure.

error_reporting(E_ALL);
ini_set('display_errors', '1');

$host = getenv('DB_HOST') ?: '127.0.0.1';
$name = getenv('DB_NAME') ?: 'test_portfolio';
$user = getenv('DB_USER') ?: 'test';
$pass = getenv('DB_PASS') ?: 'test';

putenv("DB_HOST=$host");
putenv("DB_NAME=$name");
putenv("DB_USER=$user");
putenv("DB_PASS=$pass");

// Minimal admin_config() inputs so backend/bot.php can require config.php
// without failing the `enabled` check.
putenv('APP_ENV=local');
putenv('APP_SECRET=' . str_repeat('a', 64));
putenv('TELEGRAM_BOT_TOKEN=123456789:AAFakeTokenFakeTokenFakeTokenFakeToken');
putenv('TELEGRAM_BOT_USERNAME=test_bot');
putenv('ADMIN_TELEGRAM_ID=123456789');
putenv('ADMIN_EMAIL=test@example.com');
putenv('ADMIN_MAIL_DRIVER=log');
putenv('ADMIN_MAIL_LOG=/tmp/test-mail.log');
putenv('ADMIN_MAIL_FROM=no-reply@example.com');

require_once __DIR__ . '/../backend/bot.php';

$failures = 0;
$total = 0;

function check($label, $ok)
{
    global $failures, $total;
    $total++;
    if ($ok) {
        echo "PASS: $label\n";
    } else {
        echo "FAIL: $label\n";
        $failures++;
    }
}

echo "== Pure helpers ==\n";

check('bot_clean_header strips CR/LF',
    bot_clean_header("a\r\nb\0c", 100) === 'abc');

check('bot_clean_body keeps newlines',
    bot_clean_body("line1\nline2", 100) === "line1\nline2");

$ctrl = bot_clean_body("a\x01b\x02c", 100);
check('bot_clean_body strips control chars',
    strpos($ctrl, "\x01") === false && strpos($ctrl, "\x02") === false && strpos($ctrl, 'abc') !== false);

echo "\n== Database setup ==\n";

try {
    $pdo = content_db();
} catch (Exception $e) {
    echo "FAIL: cannot connect to DB: " . $e->getMessage() . "\n";
    exit(1);
}

$pdo->exec('DELETE FROM bot_updates_seen');
$pdo->exec('DELETE FROM bot_rate_limit');
$pdo->exec('DELETE FROM admin_audit_log');
$pdo->exec('DELETE FROM admin_sessions');
$pdo->exec('DELETE FROM admin_login_challenges');
$pdo->exec('DELETE FROM admin_users');

echo "DB reset OK\n\n== bot_find_admin ==\n";

check('unknown telegram_id -> null',
    bot_find_admin('111111') === null);

$pdo->exec("INSERT INTO admin_users (telegram_id, email, display_name, is_active, role) VALUES (222222, 'inactive@example.com', 'Inactive', 0, 'admin')");
check('inactive admin -> null',
    bot_find_admin('222222') === null);

$pdo->exec("INSERT INTO admin_users (telegram_id, email, display_name, is_active, role) VALUES (333333, 'admin@example.com', 'Admin', 1, 'admin')");
$admin = bot_find_admin('333333');
check('active admin -> row',
    is_array($admin) && (int) $admin['telegram_id'] === 333333 && $admin['role'] === 'admin');

$pdo->exec("INSERT INTO admin_users (telegram_id, email, display_name, is_active, role) VALUES (444444, 'super@example.com', 'Super', 1, 'super')");
$super = bot_find_admin('444444');
check('active super -> row (role is not a bot gate)',
    is_array($super) && (int) $super['telegram_id'] === 444444 && $super['role'] === 'super');

echo "\n== bot_rate_limit_ok (per telegram_id) ==\n";

$pdo->exec('DELETE FROM bot_rate_limit');

check('rate 1/3 allowed', bot_rate_limit_ok(333333, 'selftest', 3) === true);
check('rate 2/3 allowed', bot_rate_limit_ok(333333, 'selftest', 3) === true);
check('rate 3/3 allowed', bot_rate_limit_ok(333333, 'selftest', 3) === true);
check('rate 4/3 blocked', bot_rate_limit_ok(333333, 'selftest', 3) === false);
check('rate limit isolated per telegram_id',
    bot_rate_limit_ok(444444, 'selftest', 3) === true);

echo "\n== bot_is_duplicate_update ==\n";

$pdo->exec('DELETE FROM bot_updates_seen');
check('first update_id not duplicate', bot_is_duplicate_update(1001) === false);
check('same update_id detected as duplicate', bot_is_duplicate_update(1001) === true);
check('new update_id not duplicate', bot_is_duplicate_update(1002) === false);

$seenCount = (int) $pdo->query('SELECT COUNT(*) FROM bot_updates_seen')->fetchColumn();
check('bot_updates_seen has exactly 2 rows', $seenCount === 2);

echo "\n== bot_audit ==\n";

$adminId = (int) $admin['id'];
bot_audit(array('id' => $adminId, 'telegram_id' => '333333'), 'bot_test_action', array('k' => 'v'));

$row = $pdo->query("SELECT admin_id, action, entity, details FROM admin_audit_log ORDER BY id DESC LIMIT 1")->fetch();
check('bot_audit writes admin_id',   $row && (int) $row['admin_id'] === $adminId);
check('bot_audit writes action',     $row && $row['action'] === 'bot_test_action');
check('bot_audit writes entity=bot', $row && $row['entity'] === 'bot');
check('bot_audit writes details JSON', $row && json_decode($row['details'], true) === array('k' => 'v'));

echo "\n== bot_notify_admins (no-op when no telegram_id set) ==\n";

// We can't hit Telegram here. Verify it counts the right number of active
// admins by mocking: if send fails, it returns 0, not an exception.
$sent = bot_notify_admins('test');
check('bot_notify_admins returns int and does not throw', is_int($sent) && $sent >= 0);

echo "\n== Bot config sanity ==\n";

$config = bot_config();
check('webhook_secret is a string',      is_string($config['webhook_secret']));
check('imap_ssl is bool',                is_bool($config['imap_ssl']));
check('admin_id is numeric string',      ctype_digit((string) $config['admin_id']));

echo "\n";
if ($failures === 0) {
    echo "ALL {$total} TESTS PASSED\n";
    exit(0);
}
echo "{$failures} OF {$total} TESTS FAILED\n";
exit(1);
```

---

## 5. `package.json` — kichik `check` diff (CI bilan izchillik)

`npm run check` ga yangi PHP fayl qo‘shing:

```diff
-  "check": "php -l api.php && php -l backend/config.php && php -l backend/content.php && php -l backend/contact.php && php -l backend/admin.php && php -l backend/mailer.php && php -l backend/bot.php && php -l scripts/php-server-router.php && node --check js/portfolio.js && node --check scripts/copy-static.mjs && bash -n run_local.sh && bash -n stop_local.sh && bash -n run_server.sh && bash -n stop_server.sh"
+  "check": "php -l api.php && php -l backend/config.php && php -l backend/content.php && php -l backend/contact.php && php -l backend/admin.php && php -l backend/mailer.php && php -l backend/bot.php && php -l scripts/php-server-router.php && php -l tests/bot-guard-test.php && node --check js/portfolio.js && node --check scripts/copy-static.mjs && bash -n run_local.sh && bash -n stop_local.sh && bash -n run_server.sh && bash -n stop_server.sh && bash -n scripts/set-bot-commands.sh"
```

> `tests/bot-guard-test.php` va `scripts/set-bot-commands.sh` ham lint qilinadi — CI va lokal bir xil.

---

## 6. Commit tartibi

```bash
git add database/migrate-admin-role.sql \
        database/schema.sql \
        backend/admin.php backend/bot.php backend/contact.php \
        components/admin/AdminManager.jsx components/admin/Dashboard.jsx components/admin/api.js \
        tests/bot-guard-test.php \
        scripts/set-bot-commands.sh \
        .github/workflows/ci.yml \
        package.json \
        AGENTS.md tz.md tz_master.md SECURITY.md README.md

git commit -m "feat(admin,bot): add RBAC, multi-admin bot bridge and CI guards"
```

Yoki Conventional Commits bilan bo‘lib:

```
feat(admin): add super/admin roles with last-super guard
feat(bot): multi-admin authorization and new /messages commands
feat(contact): notify active admins via Telegram on new messages
chore(db): add migrate-admin-role.sql and update schema.sql
test(bot): add bot-guard-test.php unit tests
ci: add RBAC and bot-guard checks to .github/workflows/ci.yml
docs(agents,tz,master,security,readme): document RBAC and bot multi-admin
```

---

## 7. Kod ↔ CI ↔ hujjat mosligini lokal tekshirish

Ushbu diffni commit qilishdan oldin lokal tekshirish:

```bash
# 1. Lint
npm run check

# 2. Static CI checks (qo'lda ham ishlaydi)
test -f database/migrate-admin-role.sql
test -f tests/bot-guard-test.php
test -f scripts/set-bot-commands.sh
grep -q "role VARCHAR(20) NOT NULL DEFAULT 'admin'" database/schema.sql
grep -q "admin_require_super" backend/admin.php
grep -q "bot_find_admin" backend/bot.php
grep -q "setMyCommands" scripts/set-bot-commands.sh

# 3. Lokal test DB bilan migration
mysql -u root -e "DROP DATABASE IF EXISTS test_portfolio; CREATE DATABASE test_portfolio;"
mysql -u root test_portfolio < database/schema.sql
mysql -u root test_portfolio < database/seed.sql
mysql -u root test_portfolio < database/migrate-bot-guards.sql

# 4. Bot guard testlari
DB_HOST=127.0.0.1 DB_NAME=test_portfolio DB_USER=root DB_PASS='' \
  php tests/bot-guard-test.php

# 5. Build
npm run build
test -f out/api.php && test ! -f out/config.local.php && echo "artefakt OK"
```

CI bir xil qadamni bajaradi (MySQL 8.0 service bilan).

---

keyingi qadamda scripts/php-server-router.php ni RBAC va bot endpointlar bilan kengaytirib (masalan, /api.php/admin/admins uchun 403 va /api.php/bot/webhook uchun 405 lokal preview’da) yangilangan diffni berishim mumkin — bu lokal run_local.sh/run_server.sh ni Apache bilan