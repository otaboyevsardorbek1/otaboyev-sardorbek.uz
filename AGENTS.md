# AGENTS.md

`otaboyev-sardorbek.uz` — Sardorbek Otaboyevning 3 tilli portfolio sayti. Frontend — repo ildizidagi Next.js (App Router, static export). Bosh sahifa, loyihalar ro‘yxati va 9 ta project detail sahifa React’da; detail sahifalar build vaqtidagi statik fallback bilan ochiladi va content API’dan yangilanadi. Backend — PHP `api.php` + MySQL content API, Telegram + email OTP bilan himoyalangan `/admin/` panel va cPanel IMAP/email bilan ishlaydigan shaxsiy Telegram bot ko‘prigi.

Oxirgi audit: **2026-10-22**. Joriy ish va backlog — [tz.md](tz.md), uzoq muddatli reja — [tz_master.md](tz_master.md).

## Komandalar

| Vazifa | Komanda |
|---|---|
| Dependency o‘rnatish | `npm install` (yoki `npm ci`) |
| Next dev server | `npm run dev` — API yo‘q; frontend statik fallback bilan ishlaydi |
| Static export | `npm run build` → `out/`; postbuild `files/`, `js/`, `css/`, `favicon.svg`, `api.php`, `backend/`, `.htaccess`, `sitemap.xml` ni ko‘chiradi. `config.local.php` **ko‘chirilmaydi**; deployda web rootga qo‘lda `600` bilan qo‘yiladi, lokal preview skriptlari uni `out/` ga nusxalaydi |
| CI | `.github/workflows/ci.yml` — push/PR’da `npm ci`, `npm run check`, statik RBAC/bot guard tekshiruvlari, MySQL 8.0 servisida migratsiyalar (bot guards va admin role ikki marta), `tests/bot-guard-test.php`, `npm run build` va export artefakti tekshiriladi |
| Lokal preview | `./run_local.sh` (`APP_ENV=local`, default `127.0.0.1:8000`); to‘xtatish `./stop_local.sh` |
| Server preview | `./run_server.sh` (`APP_ENV=production`, default `127.0.0.1:8081`); to‘xtatish `./stop_server.sh` |
| Production ops | `./scripts/production-ops.sh {migrate|build|deploy|cron|logs|maildns|verify|all}` — serverda cPanel user sifatida; deploy snapshot olib `config.local.php` va `.well-known/` ni saqlaydi, `cron` kunlik backup + 5 daqiqalik health monitor, `logs` user-level logrotate, `maildns` SPF/DKIM/DMARC, `verify` jonli HTTP tekshiruvlari |
| Export preview (manual) | `APP_ENV=local SITE_URL=http://127.0.0.1:8000 DB_SOCKET=... DB_NAME=... DB_USER=... DB_PASS=... php -S 127.0.0.1:8000 -t out` |
| Bot guard testlari | `DB_HOST=127.0.0.1 DB_NAME=test_portfolio DB_USER=root DB_PASS='' php tests/bot-guard-test.php` (yoki `DB_SOCKET=...`); Telegram’ga chiqmaydi |
| Sintaksis tekshiruvi | `npm run check` — `php -l api.php`, backend fayllari, `scripts/php-server-router.php`, `tests/bot-guard-test.php`, `node --check js/portfolio.js`, run/stop skriptlari va `scripts/*.sh` `bash -n`; JSX ni `npm run build` tekshiradi |
| API health | `curl -s http://127.0.0.1:8000/api.php/health` |
| Content API | `curl -s "http://127.0.0.1:8000/api.php/profile?lang=uz"` · `/api.php/projects?lang=uz` · `/api.php/projects/gitinsight?lang=uz` |
| Admin config | `curl -s http://127.0.0.1:8000/api.php/admin/config` |
| Tokenli endpoint | `curl -H "X-Api-Token: $API_TOKEN" http://127.0.0.1:8000/api.php/websites` (lokalda `APP_ENV=local` bilan dev token ham ishlaydi) |
| DB (yangi) | `mysql -u USER -p DB_NAME < database/schema.sql` → `mysql -u USER -p DB_NAME < database/seed.sql` |
| DB (eski) | Avval `database/migrate-project-sections.sql`, keyin `database/migrate-contact-messages.sql`, `database/migrate-bot-guards.sql` va `database/migrate-admin-role.sql` (idempotent); `database/seed.sql` faqat standart kontentni qayta yozish kerak bo‘lsa import qilinadi (mavjud admin kontenti o‘chadi) |
| CV PDF | `pandoc files/sardorbek-otaboyev-cv-<lang>.txt -o files/sardorbek-otaboyev-cv-<lang>.pdf -V geometry:margin=2cm --pdf-engine=xelatex -V mainfont="DejaVu Sans Mono"` |

**Deploy:** cPanel’da MySQL DB yaratiladi, `config.local.php` yoziladi (yoki `DB_*` env beriladi), `npm ci && npm run build`, so‘ng `out/` tarkibi web rootga ko‘chiriladi. `out/` ichida `api.php`, `backend/`, `.htaccess`, `files/`, `sitemap.xml` bor — **`config.local.php` yo‘q**. Uni web rootga alohida `chmod 600` bilan qo‘ying (build artefaktiga hech qachon qo‘shilmaydi). Mavjud bazaga `database/migrate-bot-guards.sql` va `database/migrate-admin-role.sql` bir marta import qilinadi (ikkalasi ham idempotent). Production uchun HTTPS, haqiqiy DB, `APP_SECRET`, Telegram bot/ID va email sozlamalari majburiy. Web rootdagi `sitemap.xml` PHP jarayoni yoza olishi kerak (admin proyekt saqlaganda avtomatik yangilanadi); yozib bo‘lmasa admin panelida ogohlantirish chiqadi.

**Muhim:** PHP built-in server `.htaccess` ni o‘qimaydi. Rewrite/redirect xatti-harakatini Apache’da alohida tekshir. Apache’da `/api.php/admin/...` route’lari ishlashi, `config.local.php`/`github_keys.txt`/`backend/`/`database/` 403 bo‘lishi, project detail URL’lari 200 va noma’lum URL’lar `404.html` bilan 404 bo‘lishi tekshirilgan. Nginx uchun `.htaccess` o‘rniga ekvivalent `try_files`/rewrite va `error_page 404 /404.html` kerak.

## Arxitektura

### Frontend (Next.js App Router, repo ildizi)

- `app/(redirect)/page.js` — `/` dan `navigator.language` bo‘yicha `/{uz,en,ru}/` ga client redirect.
- `app/(site)/[locale]/layout.js` — `<html lang>`; `LOCALES` va `generateStaticParams`.
- `app/(site)/[locale]/page.js` — metadata, canonical/hreflang, bosh sahifa.
- `app/(site)/[locale]/projects/page.js` — uz/en/ru loyihalar ro‘yxati.
- `app/(site)/[locale]/projects/[slug]/page.js` — 9 ta ma’lum project detail sahifa; `generateStaticParams`, canonical/hreflang va OG metadata.
- `app/(site)/[locale]/projects/view/page.js` — admin yaratgan yangi slug uchun client fallback; `.htaccess` shu yerga yo‘naltiradi.
- `components/Home.jsx` — `'use client'`: theme, mobil nav, reveal, tilt, project filter, GitHub fetch, contact form, content API fetch.
- `components/ProjectsPage.jsx` — ro‘yxat sahifasi: theme, mobil nav, kartalar.
- `components/ProjectDetail.jsx` — `'use client'`: statik fallback, API’dan yangilash, loading/404/503 holatlari, title/meta/canonical yangilash.
- `app/admin/` — `/admin/` Telegram login, `/admin/verify/` email OTP, `/admin/dashboard/` protected panel.
- `components/admin/` — API helper, profile editor, project manager, contact inbox va admin-user manager; `css/admin.css` alohida admin styling.
- `content/locales.js` — uz/en/ru matnlari, `projectDetails` fallback va `projectDetailLabels`.
- `css/portfolio.css` — global CSS (Next layoutda import qilinadi).
- `next.config.js` — `output: 'export'`, `trailingSlash: true`, `images.unoptimized`.

### Backend (PHP + MySQL)

- `api.php` — request routing, native contact form, token check, Sitejet proxy va content/admin include.
- `backend/config.php` — DB, admin va mail sozlamalarini env yoki gitignored `config.local.php` dan oladi.
- `backend/contact.php` — contact form validatsiyasi, IP hash, rate limit, `contact_messages`ga saqlash va native handler.
- `backend/content.php` — public content API.
- `backend/admin.php` — admin auth, session, CSRF, rate limit, audit, profile/project CRUD, inbox, sitemap va admin-user CRUD.
- `backend/bot.php` — shaxsiy Telegram email ko‘prigi: webhook secret tekshiruvi, `admin_users` bo‘yicha ruxsat (`is_active=1`), IMAP inbox o‘qish, cPanel `mail()` bilan email yuborish va contact inbox. `update_id` idempotentligi (`bot_updates_seen`) va per-admin command limitlari (`bot_rate_limit`) bor; jadval bo‘lmasa bot ishlashda davom etadi. Faqat `/api.php/bot/webhook` orqali ishlaydi; public UI yoki CORS yo‘q.
- `backend/mailer.php` — `mail`, SMTP, Resend, Brevo va lokal `log` driver.
- `database/schema.sql`, `database/seed.sql`, `database/migrate-project-sections.sql`, `database/migrate-contact-messages.sql`, `database/migrate-bot-guards.sql`, `database/migrate-admin-role.sql` — sxema, seed va eski baza migratsiyalari.

### Legacy va Sitejet qismi

- `{uz,en,ru}/projects/<slug>/index.html` (9 sahifa) repo’da tarixiy ma’lumot sifatida qoladi, lekin `out/` ga ko‘chirilmaydi va servis qilinmaydi.
- `js/portfolio.js` `out/js` ga ko‘chiriladi, lekin faol React sahifalar undan foydalanmaydi; u faqat tarixiy legacy HTML uchun.
- `css/custom.261016194537.css`, `webcard/`, `bundles/`, `g/fonts/`, `images/`, `en/{portfolio,tech-stack,...}` — tegilmagan Sitejet artefaktlari. Bosh sahifalardan link yo‘q.

### `.htaccess`

- Locale root rewrite, `api.php` PATH_INFO exclusion, config/backend/database deny va `ErrorDocument 404 /404.html`.
- Ma’lum project detail/listing papkalari `index.html` bilan `DirectoryIndex` orqali beriladi.
- `out/` da `index.html` bo‘lmagan project slug `/{locale}/projects/view/?slug=...` ga rewrite qilinadi; per-slug statik rewrite qo‘shmang (loop).
- Boshqa noma’lum URL haqiqiy HTTP 404 va `404.html` oladi; SPA catch-all yo‘q.
- `database/` va `backend/` HTTP’dan yopilgan; admin URL’lariga `X-Robots-Tag: noindex, nofollow`.
- Static javoblarga CSP (`script-src` Telegram widget uchun), HSTS (`max-age=31536000`, shared hosting uchun `includeSubDomains`/`preload`siz), `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, COOP/CORP va `X-Permitted-Cross-Domain-Policies` qo‘shiladi. API ham PHP orqali shu headerlarni beradi; `X-Powered-By` olib tashlangan. `api.php` wildcard CORS’ni faqat public read-only JSON endpointlarda (`/profile`, `/projects`, `/projects/{slug}`, `/health`, `/status`) beradi; `.htaccess` `admin|bot|form_container|websites` javoblaridan CORS headerlarini himoya qatlami sifatida o‘chiradi.

## Content API (`api.php` + MySQL)

Envelope: `{status:"ok",data:...}` yoki `{status:"error",message,code}`.

- `GET /api.php/health` (yoki `/status`) — `{status:"ok"}`.
- `GET /api.php/profile?lang=uz|en|ru` — `{name,role,bio,quick_text,location,email,github,telegram,telegram_handle,avatar_url}`.
- `GET /api.php/projects?lang=...` — `[{slug,category,tag,title,summary,tech[],repo_url,demo_url,detail_url}]`; faqat `is_active=1`.
- `GET /api.php/projects/{slug}?lang=...` — qo‘shimcha `description`, `results[]`, `stack_notes[]`.
- Noto‘g‘ri `lang` → `uz`. Topilmagan slug → `404 {status:"error",code:"project_not_found"}`.
- DB sozlanmagan yoki ulanmagan → `503 {status:"error",code:"db_unavailable"}`. Frontend statik fallback’ga qaytadi.
- `GET /api.php/websites` — `X-Api-Token` yoki `Authorization: Bearer`; noto‘g‘ri bo‘lsa 401.
- `POST /api.php/bot/webhook` — shaxsiy Telegram bot webhook’i; `X-Telegram-Bot-Api-Secret-Token` va faqat `message` update’lari. Ruxsat `admin_users.is_active=1` dan olinadi (jadval bo‘sh yoki yo‘q bo‘lsa `admin.telegram_id` bootstrap). `/help`, `/whoami`, `/messages`, `/unread`, `/message <id>`, `/reply <id> | <matn>`, `/stats`, `/inbox`, `/read <uid>`, `/send <to> | <subject> | <body>`; OTP yoki bot token Telegram’ga yuborilmaydi.
- `POST /api.php/form_container/submit` — contact form, token talab qilmaydi; `id=contact` native handler orqali `contact_messages`ga saqlanadi (validatsiya, `website` honeypot va IP bo‘yicha 10 daqiqada 5 ta limit). Boshqa `id`lar `form_<id>[ed-f-N]` Sitejet contractiga uzatiladi.
- Boshqa yo‘llar `https://api.sitehub.io` ga proxy; `/images*` → `https://inter-cdn.com`.
- Token tartibi: `API_TOKEN` → `SITEHUB_API_TOKEN` → `OTABOYEV_API_TOKEN`; `otaboyev-dev-token` fallback faqat `APP_ENV=local|development|test` bo‘lganda. Agar `/websites` ishlatilsa, productionda haqiqiy env token majburiy; hozircha token yo‘q va endpoint ataylab `401`.
- `api.php` va `backend/*.php` “Compatible with PHP 7.1” — arrow fn, typed properties va boshqa 7.4+ sintaksisni qo‘shmang.

### Ma’lumotlar bazasi

- Jadvallar: `profiles`, `profile_translations`, `projects`, `project_translations`, `contact_messages`, `admin_users`, `admin_login_challenges`, `admin_sessions`, `admin_auth_events`, `admin_audit_log`, `bot_updates_seen`, `bot_rate_limit`.
- `tech`, `results`, `stack_notes` — JSON matn (`TEXT`), PHP’da `json_decode`. `contact_messages.payload` — form maydonlari va attachment metadata JSON’i.
- `database/seed.sql` — 1 profil, 3 loyiha va 9 tarjima (uz/en/ru).
- Yangi o‘rnatish `schema.sql` dan oladi; eski bazaga `database/migrate-project-sections.sql` bir marta, `database/migrate-contact-messages.sql`, `database/migrate-bot-guards.sql` va `database/migrate-admin-role.sql` esa idempotent tarzda qo‘llanadi. `seed.sql` faqat standart kontentni tiklash uchun qayta import qilinadi — u mavjud admin tahrirlarini bosib ketadi. `migrate-project-sections.sql` idempotent emas.
- `database/schema.sql`, `database/seed.sql`, `database/migrate-project-sections.sql`, `database/migrate-contact-messages.sql`, `database/migrate-bot-guards.sql` va `database/migrate-admin-role.sql` versiyalanadi (`*.sql` ignore’dan istisno). `database/` web’dan `.htaccess` bilan yopilgan; `out/` ga ko‘chirilmaydi.
- Production DB user’ga faqat `SELECT, INSERT, UPDATE, DELETE` beriladi; `CREATE`/`ALTER`/`DROP` migratsiya vaqtida vaqtincha qo‘shiladi. Hozirgi baza `host9590_portfolio` shu least-privilege bilan ishlaydi.

## Admin auth va xavfsizlik

- Flow: Telegram Login Widget → HMAC + `auth_date` tekshiruvi → challenge cookie → emailga 6 xonali OTP → session cookie.
- Rollar: `admin_users.role` — `super` (boshqa adminlarni boshqaradi) yoki `admin` (faqat kontent/inbox). `/api.php/admin/admins` endpoint’i `super` talab qiladi (`403 forbidden`). Oxirgi faol super adminni o‘chirish/deaktiv qilish yoki rolni pasaytirish `422 last_super_admin` bilan bloklanadi. Rol o‘zgarishi darhol kuchga kiradi, chunki `admin_lookup_session()` har safar `admin_users` ga JOIN qiladi.
- `backend/admin.php` OTP’ni HMAC bilan hashlaydi; 6 raqam, 10 daqiqa, 5 urinish; challenge va session muddatlari cheklangan.
- Cookie’lar: `admin_session` HttpOnly, HTTPS’da Secure, `SameSite=Strict`; `admin_challenge` HttpOnly, `SameSite=Lax`.
- Mutation’larda `X-CSRF-Token` majburiy; rate limit, `admin_auth_events` va `admin_audit_log` mavjud. `admin_check_origin()` `Origin`/`Referer`ni `site_url` bilan strict host/scheme/port bo‘yicha solishtiradi, `Origin: null`ni rad etadi, ikkala header bo‘lmasa `X-Requested-With: XMLHttpRequest` talab qiladi.
- `api.php` `display_errors`ni har doim `0` qiladi va xatolarni `log_errors` orqali server logiga yozadi; `APP_ENV` qiymati stack trace’ni qayta yoqmaydi.
- `APP_SECRET` aniq 64 hex bo‘lmasa, email driver sozlanmagan bo‘lsa, production `SITE_URL` HTTPS bo‘lmasa yoki Telegram bot token/username yoki numeric admin ID noto‘g‘ri bo‘lsa auth o‘chiq (`enabled:false`).
- Bot token, app secret yoki OTP’ni public JSON/logga chiqarmang. Admin response’larda wildcard CORS yo‘q.
- Admin route’lar: `GET /api.php/admin/config`, `GET /api.php/admin/auth/telegram`, `GET /api.php/admin/auth/status`, `POST /api.php/admin/auth/verify`, `POST /api.php/admin/auth/resend`, `GET /api.php/admin/session[?optional=1]`, `POST /api.php/admin/logout`, `GET/PUT /api.php/admin/profile`, `GET/POST /api.php/admin/projects`, `GET/PUT/DELETE /api.php/admin/projects/{id}`, `GET /api.php/admin/messages[?count=1|unread=1]`, `GET/PUT/DELETE /api.php/admin/messages/{id}`, `POST /api.php/admin/sitemap`, `GET/POST /api.php/admin/admins`, `GET/PUT/DELETE /api.php/admin/admins/{id}`.
- Inbox/sitemap mutation’lari session + CSRF talab qiladi. Admin-user endpoint’lari (`/api.php/admin/admins*`) `super` rolini talab qiladi. O‘z admin hisobini o‘chirish/deaktiv qilish, oxirgi faol adminni olib tashlash va oxirgi faol super adminni o‘chirish/deaktiv qilish/rolini pasaytirish `422` bilan bloklanadi; sitemap yozilmasa `500 sitemap_write_failed`.
- Admin project slug’i `view` bo‘lishi mumkin emas — u fallback route bilan to‘qnashadi.
- Bot ko‘prigi alohida shaxsiy kanal: webhook secret, `admin_users.is_active=1` ruxsati (jadval bo‘sh/yo‘q bo‘lsa `admin.telegram_id` bootstrap) va Telegram `secret_token` bilan himoyalangan; web panelda o‘chirilgan admin keyingi xabardan boshlab botdan foydalana olmaydi. Bot token, OTP va email parollari Telegram’ga chiqmaydi. Contact formasi saqlangandan keyin faol adminlarga (ko‘pi bilan 10 ta) best-effort Telegram xabar yuboriladi. cPanel IMAP hosti `panel2.eskiz.uz:993/imap/ssl`; `mail.otaboyev-sardorbek.uz` sertifikat hostname’iga mos kelmaydi.
- Production cPanel’da `admin@otaboyev-sardorbek.uz` (OTP/inbox) va `no-reply@...` (yuborish) mailbox’lari, `contact@...` → `admin@...` forwarder’i ishlatiladi. `admin.phone` faqat config’da; hozircha public UI yoki DB maydoni emas.

## Til almashtirish — birga o‘zgaradi

1. `content/locales.js` — barcha matnlar, `projectDetails` va `projectDetailLabels`.
2. `app/(redirect)/page.js` dagi `SUPPORTED`.
3. `content/locales.js` dagi `LOCALES` (layout/page `generateStaticParams` shundan oladi).
4. `.htaccess` locale, API exclusion, 404 va project fallback qoidalari.
5. `sitemap.xml` — default 15 URL (3 home + 3 listing + 9 detail); admin proyekt qo‘shilganda/o‘chirilganda avtomatik qayta generatsiya qilinadi.
6. `canonical` + `hreflang` — 3 home + 3 listing + 9 detail.
7. `components/ProjectDetail.jsx` dagi `.lang-switcher` va `[slug]/page.js` metadata.
8. `database/seed.sql` tarjimalari (`profile_translations`, `project_translations`).

Matn o‘zgarsa — 3 tilda ham. `id`, `class`, `name`, `data-*` tarjima qilinmaydi.

## Tekshirish tartibi

1. `npm run check`.
2. Test DB (schema + seed + `migrate-bot-guards.sql`) bilan `php tests/bot-guard-test.php` — `bot_find_admin`, per-admin rate limit, `update_id` idempotentligi va `bot_audit`.
3. `npm run build` — xatosiz export + postbuild copy; `out/` ichida `config.local.php`, `config.local.js`, `github_keys.txt`, `server_keys.txt` **yo‘qligi** va `404.html`, admin sahifalari, `.htaccess`, `api.php` borligi tekshiriladi (CI ham shuni tekshiradi).
4. Test DB bilan: `APP_ENV=local SITE_URL=http://127.0.0.1:8000 DB_SOCKET=... DB_NAME=... DB_USER=... DB_PASS=... ./run_local.sh`; to‘xtatish `./stop_local.sh`. Skriptlar PHP built-in server ishlatadi, `.htaccess` qo‘llanmaydi; lokal preview `config.local.php` ni `out/` ga `600` bilan nusxalaydi.
5. `/uz/`, `/en/`, `/ru/` + bitta ma’lum detail; Apache’da admin yaratgan slug `/uz/projects/<slug>/` fallback’i; F12 → Console/Network’da kutilmagan 404, CORS yoki hydration xatosi yo‘qligi.
6. API: profile/projects/detail (uz/en/ru, invalid lang, 404 slug, DB yo‘q → 503); `OPTIONS`/GET public read endpointlarda `Access-Control-Allow-Origin: *`, form/admin/bot/token endpointlarda CORS header yo‘qligi; HTTP’da HSTS yo‘qligi.
7. Contact formani yuborib ko‘r (Network’da `/api.php/form_container/submit`); DB’da `contact_messages` yozuvi, admin panelda xabar, `website` honeypot, invalid email → `422`, 5 ta yuborishdan keyin `429`, 10 tadan keyin global IP limit `429`.
8. Admin: `/admin/` config `enabled`; Telegram callback → challenge cookie → email kod (lokalda `log`) → session; noto‘g‘ri OTP 5 marta → 6-chida `429`; expired challenge/session; `Origin: null` yoki header’siz mutation → `403`, `X-Requested-With: XMLHttpRequest` bilan header’siz CLI → o‘tadi; noto‘g‘ri CSRF → `403`; admin CORS header yo‘qligi; dashboard headless xatosiz; inbox read/unread/delete; admin qo‘shish/tahrirlash/o‘chirish va self/last-admin himoyasi; sitemap yangilash.
9. RBAC: oddiy admin `/api.php/admin/admins*` → `403 forbidden`; super admin CRUD; noto‘g‘ri rol → `422`; oxirgi faol superni o‘chirish/deaktiv qilish/demote → `422 last_super_admin`; rol o‘zgarishi keyingi so‘rovda darhol kuchga kirishi; `admin_audit_log` da `role`.
10. Bot: webhook secret yo‘q/noto‘g‘ri → `403`; GET → `405`; to‘g‘ri secret + ruxsatsiz yoki `is_active=0` user → `200` va Telegram xabari yuborilmasligi; bir xil `update_id` ikkinchi marta kelsa qayta ishlanmasligi (`bot_updates_seen`da bitta qator); `bot_rate_limit_ok(<tg_id>, 'send', 10)` 11-cha chaqiriqda `false`; faol admin bilan `/help`, `/whoami`, `/messages`, `/message <id>`, `/reply <id> | matn`, `/stats`, `/inbox`, `/read <uid>`, `/send ...`; contact formasi faol adminlarga Telegram xabar yuborishi; yuborilgan email `admin@` mailbox’iga tushishi; `getWebhookInfo` `pending_update_count=0`.
11. `database/migrate-bot-guards.sql` va `database/migrate-admin-role.sql` ni ikki marta import qilib idempotentligini tekshir; `admin_users.role` ustuni va `idx_admin_users_role` indeksi borligini tasdiqla.
12. Apache’da `/de/` va `/nope` → `404` + `404.html`; `/api.php/health` → 200; `config.local.php`, `github_keys.txt`, `backend/`, `database/` → 403.
13. `git diff --check` va `git diff --stat`.

GitHub bo‘limi tashqi `api.github.com` ga bog‘liq (tokensiz, rate limit bor) — offline fallback xato emas.

## Ma’lum cheklovlar

- Detail sahifalarning statik HTML’i build vaqtidagi fallback; admin tahrirlari API fetch’dan keyin ko‘rinadi. SEO/crawler uchun to‘liq yangi matn olishda rebuild kerak.
- Admin yaratgan yangi slug Apache’da `.htaccess` orqali `/projects/view/?slug=...` ga internal rewrite qilinadi; PHP built-in preview router esa 302 bilan shu fallback’ga yo‘naltiradi (ikkalasi ham client fetch’dan keyin ishlaydi).
- Generic detail route’ning statik metadata/canonical’i listing’ga ishora qiladi; client JS keyin haqiqiy URL’ga yangilaydi.
- `{uz,en,ru}/projects/<slug>/index.html` legacy nusxalari repo’da qoladi, lekin `out/` da servis qilinmaydi; ularni tahrirlash saytga ta’sir qilmaydi.
- `en/*` legacy Sitejet sahifalari uz/ru muqobilisiz; `out/` ga ko‘chirilmaydi va sitemap’da yo‘q. Ulardagi canonical domeni xato (`otaboyevsardorbek.uz`).
- Contact form native handler DB’ga yozadi; DB yoki `contact_messages` jadvali bo‘lmasa forma `503` qaytaradi va frontend `mailto` fallback’ga o‘tadi. Contact form email yubormaydi; `contact@` forwarder’i va admin inbox asosiy manba.
- Media upload (avatar/loyiha rasmlari) va `en/*` legacy sahifalarini tarjima qilish hali yo‘q — owner qarori kerak.
- Admin panel UI matnlari hozircha o‘zbekcha; sayt matnlari uz/en/ru.
- 2026-10-22 holat: cPanel MySQL (`host9590_portfolio`), `config.local.php`, mailboxes (`admin@`, `no-reply@`), `contact@` forwarder, Telegram bot (`@otaboyev_sardorbek_uz_bot`), webhook secret va HTTPS production ishlaydi. API/admin/bot smoke testlari o‘tgan.
- Email: SPF external DNS’da `pass`; DKIM external DNS’da yo‘q va Exim hozir imzolamaydi; DMARC external `v=DMARC1; p=none;`. cPanel/WHM yoki Eskiz DNS’ga kirish bo‘lmasa DKIM/DMARC’ni yakunlash mumkin emas — provider amali kerak. Port25 hisoboti `SPF pass`, `DKIM none` ko‘rsatgan.
- Telegram Login Widget lokal HTTP’da ishlamaydi; BotFather’da `/setdomain otaboyev-sardorbek.uz` bajarilgani va haqiqiy Telegram Login testi hali tasdiqlanmagan. Bot webhook’i fake update bilan sinalgan; haqiqiy admin xabari alohida yuborilishi kerak.
- 2026-10-22 holat: migration kodi va hujjatlar `origin/main` ga push qilindi. `out/` gitignored. `CodeAnalyzer_v2.py` va bo‘sh `README` ataylab commit qilinmagan — ular repo ishiga aloqasi yo‘q.
- `github_keys.txt` — maxfiy va `.gitignore` da, mode `600`. Kod uni o‘qimaydi, build uni `out/` ga ko‘chirmaydi va `.htaccess`/router HTTP’dan to‘sadi. Token egasi tomonidan GitHub’da revoke qilinishi shart (kod bilan hal qilinmaydi). O‘qimang, commit qilmang. Bo‘sh `README` va `CodeAnalyzer_v2.py` repo ishiga aloqasi yo‘q.
- Legacy `en/*` sahifalari va `webcard/static/app.bundle.*.js` ichidagi Google Maps API kalitlari 2026-10-22 da `REDACTED_GOOGLE_MAPS_KEY` bilan almashtirildi; ular `out/` ga ko‘chirilmaydi. Kalitlar git tarixida qolgani uchun Google Cloud’da rotate/restrict qilinishi kerak.

## Qoidalar

- Eng kichik diff. Next.js/React’dan boshqa framework yoki bundler qo‘shmang.
- Yangi UI — `app/` va `components/`; umumiy CSS — `css/portfolio.css`. Legacy sahifalarga tegish kerak bo‘lsa, avval sababini yozing.
- `api.php`, backend API va `.htaccess` ni faqat aniq topshiriq bo‘yicha o‘zgartiring; API envelope’ni (`{status,data|message,code}`) saqlang.
- `api.php`/`backend/*.php` PHP 7.1 sintaksisida qolsin.
- `css/custom.*.css`, `webcard/`, `bundles/`, `g/fonts/`, `images/` — Sitejet artefaktlari; tahrirlamang.
- Barcha matn 3 tilda. `id`, `class`, `name`, `data-*` tarjima qilinmaydi.
- Maxfiy fayllar (`github_keys.txt`, `config.local.php`, `.env`) commit qilinmaydi. `database/` va `backend/` ni web rootda HTTP’dan yoping; `.htaccess` buni qiladi.
- Admin auth’ni bypass qiladigan dev flag, hardcoded OTP yoki public CRUD endpoint qo‘shmang. CSRF headerini olib tashlamang.
- Commit xabarlari ingliz tilida, Conventional Commits (`feat:`, `fix:`, `docs:`, `chore:`).
- `AGENTS.md` — yagona instruction fayl. `agents.md` (kichik harfli) faqat shu faylga ishora qiladi.
- Arxitektura, route, DB sxema yoki deploy tartibi o‘zgarsa — shu uch hujjatni (`AGENTS.md`, `tz.md`, `tz_master.md`) birga yangilang.

## Hujjatlar

| Fayl | Nima uchun |
|---|---|
| `README.md` | deploy, Apache/Nginx, API token |
| `tz.md` | joriy holat, backlog, acceptance va keyingi qadamlar |
| `tz_master.md` | uzoq muddatli reja, arxitektura, API kontrakti va hosting |
| `content/locales.js`, `components/Home.jsx`, `components/ProjectsPage.jsx` | bosh sahifa va loyihalar ro‘yxati matni/interaktivligi |
| `components/ProjectDetail.jsx`, `app/(site)/[locale]/projects/[slug]/page.js` | project detail render, API refresh va SEO |
| `database/schema.sql`, `database/seed.sql`, `database/migrate-project-sections.sql`, `database/migrate-contact-messages.sql`, `database/migrate-bot-guards.sql`, `database/migrate-admin-role.sql` | MySQL sxema, seed va eski baza migratsiyalari |
| `backend/config.php`, `backend/content.php`, `backend/contact.php` | content API, DB sozlamalari va contact inbox |
| `backend/admin.php`, `backend/mailer.php` | admin auth/session/CRUD/inbox/sitemap/admins; email delivery |
| `backend/bot.php`, `config.local.example.php` | Telegram email bridge (webhook, IMAP, `/send`), bot/IMAP/phone config |
| `app/admin/`, `components/admin/`, `css/admin.css` | admin UI, inbox va admin-user manager |
| `SECURITY.md` | top 20 xavfsizlik nazorati va cPanel checklist |
| `css/portfolio.css`, `js/portfolio.js` | umumiy CSS; legacy JS |
| `files/sardorbek-otaboyev-cv-{uz,ru,en}.txt` | CV manba (PDF pandoc bilan) |
