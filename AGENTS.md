# AGENTS.md

`otaboyev-sardorbek.uz` — Sardorbek Otaboyevning 3 tilli portfolio sayti. Frontend — repo ildizidagi Next.js (App Router, static export). Bosh sahifa, loyihalar ro‘yxati va 9 ta project detail sahifa React’da; detail sahifalar build vaqtidagi statik fallback bilan ochiladi va content API’dan yangilanadi. Backend — PHP `api.php` + MySQL content API va Telegram + email OTP bilan himoyalangan `/admin/` panel.

Oxirgi audit: **2026-10-21**. Joriy ish va backlog — [tz.md](tz.md), uzoq muddatli reja — [tz_master.md](tz_master.md).

## Komandalar

| Vazifa | Komanda |
|---|---|
| Dependency o‘rnatish | `npm install` (yoki `npm ci`) |
| Next dev server | `npm run dev` — API yo‘q; frontend statik fallback bilan ishlaydi |
| Static export | `npm run build` → `out/`; postbuild `files/`, `js/`, `css/`, `favicon.svg`, `api.php`, `backend/`, mavjud bo‘lsa `config.local.php`, `.htaccess`, `sitemap.xml` ni ko‘chiradi |
| CI | `.github/workflows/ci.yml` — push/PR’da `npm ci`, `npm run check`, `npm run build` va export fayllarini tekshiradi |
| Lokal preview | `./run_local.sh` (`APP_ENV=local`, default `127.0.0.1:8000`); to‘xtatish `./stop_local.sh` |
| Server preview | `./run_server.sh` (`APP_ENV=production`, default `127.0.0.1:8081`); to‘xtatish `./stop_server.sh` |
| Export preview (manual) | `APP_ENV=local SITE_URL=http://127.0.0.1:8000 DB_SOCKET=... DB_NAME=... DB_USER=... DB_PASS=... php -S 127.0.0.1:8000 -t out` |
| Sintaksis tekshiruvi | `npm run check` — `php -l api.php`, backend fayllari, `scripts/php-server-router.php`, `node --check js/portfolio.js`; JSX ni `npm run build` tekshiradi |
| API health | `curl -s http://127.0.0.1:8000/api.php/health` |
| Content API | `curl -s "http://127.0.0.1:8000/api.php/profile?lang=uz"` · `/api.php/projects?lang=uz` · `/api.php/projects/gitinsight?lang=uz` |
| Admin config | `curl -s http://127.0.0.1:8000/api.php/admin/config` |
| Tokenli endpoint | `curl -H "X-Api-Token: $API_TOKEN" http://127.0.0.1:8000/api.php/websites` (lokalda `APP_ENV=local` bilan dev token ham ishlaydi) |
| DB (yangi) | `mysql -u USER -p DB_NAME < database/schema.sql` → `mysql -u USER -p DB_NAME < database/seed.sql` |
| DB (eski) | Avval `database/migrate-project-sections.sql`, keyin `database/migrate-contact-messages.sql`; `database/seed.sql` faqat standart kontentni qayta yozish kerak bo‘lsa import qilinadi (mavjud admin kontenti o‘chadi) |
| CV PDF | `pandoc files/sardorbek-otaboyev-cv-<lang>.txt -o files/sardorbek-otaboyev-cv-<lang>.pdf -V geometry:margin=2cm --pdf-engine=xelatex -V mainfont="DejaVu Sans Mono"` |

**Deploy:** cPanel’da MySQL DB yaratiladi, `config.local.php` yoziladi (yoki `DB_*` env beriladi), `npm ci && npm run build`, so‘ng `out/` tarkibi web rootga ko‘chiriladi. `out/` ichida `api.php`, `backend/`, `.htaccess`, `files/`, `sitemap.xml` bor. Production uchun HTTPS, haqiqiy DB, `APP_SECRET`, Telegram bot/ID va email sozlamalari majburiy. Web rootdagi `sitemap.xml` PHP jarayoni yoza olishi kerak (admin proyekt saqlaganda avtomatik yangilanadi); yozib bo‘lmasa admin panelida ogohlantirish chiqadi.

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
- `backend/mailer.php` — `mail`, SMTP, Resend, Brevo va lokal `log` driver.
- `database/schema.sql`, `database/seed.sql`, `database/migrate-project-sections.sql`, `database/migrate-contact-messages.sql` — sxema, seed va eski baza migratsiyalari.

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

## Content API (`api.php` + MySQL)

Envelope: `{status:"ok",data:...}` yoki `{status:"error",message,code}`.

- `GET /api.php/health` (yoki `/status`) — `{status:"ok"}`.
- `GET /api.php/profile?lang=uz|en|ru` — `{name,role,bio,quick_text,location,email,github,telegram,telegram_handle,avatar_url}`.
- `GET /api.php/projects?lang=...` — `[{slug,category,tag,title,summary,tech[],repo_url,demo_url,detail_url}]`; faqat `is_active=1`.
- `GET /api.php/projects/{slug}?lang=...` — qo‘shimcha `description`, `results[]`, `stack_notes[]`.
- Noto‘g‘ri `lang` → `uz`. Topilmagan slug → `404 {status:"error",code:"project_not_found"}`.
- DB sozlanmagan yoki ulanmagan → `503 {status:"error",code:"db_unavailable"}`. Frontend statik fallback’ga qaytadi.
- `GET /api.php/websites` — `X-Api-Token` yoki `Authorization: Bearer`; noto‘g‘ri bo‘lsa 401.
- `POST /api.php/form_container/submit` — contact form, token talab qilmaydi; `id=contact` native handler orqali `contact_messages`ga saqlanadi (validatsiya, `website` honeypot va IP bo‘yicha 10 daqiqada 5 ta limit). Boshqa `id`lar `form_<id>[ed-f-N]` Sitejet contractiga uzatiladi.
- Boshqa yo‘llar `https://api.sitehub.io` ga proxy; `/images*` → `https://inter-cdn.com`.
- Token tartibi: `API_TOKEN` → `SITEHUB_API_TOKEN` → `OTABOYEV_API_TOKEN`; `otaboyev-dev-token` fallback faqat `APP_ENV=local|development|test` bo‘lganda, productionda esa haqiqiy env token majburiy.
- `api.php` va `backend/*.php` “Compatible with PHP 7.1” — arrow fn, typed properties va boshqa 7.4+ sintaksisni qo‘shmang.

### Ma’lumotlar bazasi

- Jadvallar: `profiles`, `profile_translations`, `projects`, `project_translations`, `contact_messages`, `admin_users`, `admin_login_challenges`, `admin_sessions`, `admin_auth_events`, `admin_audit_log`.
- `tech`, `results`, `stack_notes` — JSON matn (`TEXT`), PHP’da `json_decode`. `contact_messages.payload` — form maydonlari va attachment metadata JSON’i.
- `database/seed.sql` — 1 profil, 3 loyiha va 9 tarjima (uz/en/ru).
- Yangi o‘rnatish `schema.sql` dan oladi; eski bazaga `database/migrate-project-sections.sql` bir marta, `database/migrate-contact-messages.sql` esa idempotent tarzda qo‘llanadi. `seed.sql` faqat standart kontentni tiklash uchun qayta import qilinadi — u mavjud admin tahrirlarini bosib ketadi. `migrate-project-sections.sql` idempotent emas.
- `database/schema.sql`, `database/seed.sql`, `database/migrate-project-sections.sql` va `database/migrate-contact-messages.sql` versiyalanadi (`*.sql` ignore’dan istisno). `database/` web’dan `.htaccess` bilan yopilgan; `out/` ga ko‘chirilmaydi.

## Admin auth va xavfsizlik

- Flow: Telegram Login Widget → HMAC + `auth_date` tekshiruvi → challenge cookie → emailga 6 xonali OTP → session cookie.
- `backend/admin.php` OTP’ni HMAC bilan hashlaydi; 6 raqam, 10 daqiqa, 5 urinish; challenge va session muddatlari cheklangan.
- Cookie’lar: `admin_session` HttpOnly, HTTPS’da Secure, `SameSite=Strict`; `admin_challenge` HttpOnly, `SameSite=Lax`.
- Mutation’larda `X-CSRF-Token` majburiy; rate limit, `admin_auth_events` va `admin_audit_log` mavjud.
- `APP_SECRET` aniq 64 hex bo‘lmasa, email driver sozlanmagan bo‘lsa, production `SITE_URL` HTTPS bo‘lmasa yoki Telegram bot token/username yoki numeric admin ID noto‘g‘ri bo‘lsa auth o‘chiq (`enabled:false`).
- Bot token, app secret yoki OTP’ni public JSON/logga chiqarmang. Admin response’larda wildcard CORS yo‘q.
- Admin route’lar: `GET /api.php/admin/config`, `GET /api.php/admin/auth/telegram`, `GET /api.php/admin/auth/status`, `POST /api.php/admin/auth/verify`, `POST /api.php/admin/auth/resend`, `GET /api.php/admin/session[?optional=1]`, `POST /api.php/admin/logout`, `GET/PUT /api.php/admin/profile`, `GET/POST /api.php/admin/projects`, `GET/PUT/DELETE /api.php/admin/projects/{id}`, `GET /api.php/admin/messages[?count=1|unread=1]`, `GET/PUT/DELETE /api.php/admin/messages/{id}`, `POST /api.php/admin/sitemap`, `GET/POST /api.php/admin/admins`, `GET/PUT/DELETE /api.php/admin/admins/{id}`.
- Inbox/sitemap/admin-user mutation’lari session + CSRF talab qiladi. O‘z admin hisobini o‘chirish/deaktiv qilish va oxirgi faol adminni olib tashlash `422` bilan bloklanadi; sitemap yozilmasa `500 sitemap_write_failed`.
- Admin project slug’i `view` bo‘lishi mumkin emas — u fallback route bilan to‘qnashadi.

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
2. `npm run build` — xatosiz export + postbuild copy.
3. Test DB bilan: `APP_ENV=local SITE_URL=http://127.0.0.1:8000 DB_SOCKET=... DB_NAME=... DB_USER=... DB_PASS=... ./run_local.sh`; to‘xtatish `./stop_local.sh`. Skriptlar PHP built-in server ishlatadi, `.htaccess` qo‘llanmaydi.
4. `/uz/`, `/en/`, `/ru/` + bitta ma’lum detail; Apache’da admin yaratgan slug `/uz/projects/<slug>/` fallback’i; F12 → Console/Network’da kutilmagan 404, CORS yoki hydration xatosi yo‘qligi.
5. API: profile/projects/detail (uz/en/ru, invalid lang, 404 slug, DB yo‘q → 503).
6. Contact formani yuborib ko‘r (Network’da `/api.php/form_container/submit`); DB’da `contact_messages` yozuvi, admin panelda xabar, `website` honeypot va 6-chi so‘rovda `429` tekshiriladi.
7. Admin: `/admin/` config `enabled`; Telegram callback → challenge cookie → email kod (lokalda `log`) → session; noto‘g‘ri OTP, expired challenge/session, CSRF yo‘qligi, admin CORS header yo‘qligi; dashboard headless xatosiz; inbox read/unread/delete; admin qo‘shish/tahrirlash/o‘chirish va self/last-admin himoyasi; sitemap yangilash.
8. Apache’da `/de/` va `/nope` → `404` + `404.html`; `/api.php/health` → 200.
9. `git diff --check` va `git diff --stat`.

GitHub bo‘limi tashqi `api.github.com` ga bog‘liq (tokensiz, rate limit bor) — offline fallback xato emas.

## Ma’lum cheklovlar

- Detail sahifalarning statik HTML’i build vaqtidagi fallback; admin tahrirlari API fetch’dan keyin ko‘rinadi. SEO/crawler uchun to‘liq yangi matn olishda rebuild kerak.
- Admin yaratgan yangi slug faqat Apache rewrite bilan `/projects/view/?slug=...` orqali ishlaydi; PHP built-in server bu URL’ni 404 qaytaradi.
- Generic detail route’ning statik metadata/canonical’i listing’ga ishora qiladi; client JS keyin haqiqiy URL’ga yangilaydi.
- `{uz,en,ru}/projects/<slug>/index.html` legacy nusxalari repo’da qoladi, lekin `out/` da servis qilinmaydi; ularni tahrirlash saytga ta’sir qilmaydi.
- `en/*` legacy Sitejet sahifalari uz/ru muqobilisiz; `out/` ga ko‘chirilmaydi va sitemap’da yo‘q. Ulardagi canonical domeni xato (`otaboyevsardorbek.uz`).
- Contact form native handler DB’ga yozadi; DB yoki `contact_messages` jadvali bo‘lmasa forma `503` qaytaradi va frontend `mailto` fallback’ga o‘tadi. Email yuborish hali alohida tekshirilmagan; inbox asosiy manba.
- Media upload (avatar/loyiha rasmlari) va `en/*` legacy sahifalarini tarjima qilish hali yo‘q — owner qarori kerak.
- Admin panel UI matnlari hozircha o‘zbekcha; sayt matnlari uz/en/ru.
- 2026-10-21 holat: Telegram bot (`@otaboyev_sardorbek_uz_bot`), `SITE_URL`, admin Telegram ID va `APP_SECRET` gitignored `config.local.php` da sozlangan. cPanel DB credential’lari va email yetkazib berish hali tekshirilmagan; DB bo‘lmasa content API 503, admin CRUD ishlamaydi.
- Telegram Login Widget lokal HTTP’da ishlamaydi; BotFather’da `/setdomain otaboyev-sardorbek.uz` bajarilgani va HTTPS production testi kerak.
- 2026-10-21 holat: migration kodi va hujjatlar `origin/main` ga push qilindi. `out/` gitignored. `CodeAnalyzer_v2.py` va bo‘sh `README` ataylab commit qilinmagan — ular repo ishiga aloqasi yo‘q.
- `github_keys.txt` — maxfiy va `.gitignore` da. O‘qimang, commit qilmang. Bo‘sh `README` va `CodeAnalyzer_v2.py` repo ishiga aloqasi yo‘q.

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
| `database/schema.sql`, `database/seed.sql`, `database/migrate-project-sections.sql`, `database/migrate-contact-messages.sql` | MySQL sxema, seed va eski baza migratsiyalari |
| `backend/config.php`, `backend/content.php`, `backend/contact.php` | content API, DB sozlamalari va contact inbox |
| `backend/admin.php`, `backend/mailer.php` | admin auth/session/CRUD/inbox/sitemap/admins; email delivery |
| `app/admin/`, `components/admin/`, `css/admin.css` | admin UI, inbox va admin-user manager |
| `SECURITY.md` | top 20 xavfsizlik nazorati va cPanel checklist |
| `css/portfolio.css`, `js/portfolio.js` | umumiy CSS; legacy JS |
| `files/sardorbek-otaboyev-cv-{uz,ru,en}.txt` | CV manba (PDF pandoc bilan) |
