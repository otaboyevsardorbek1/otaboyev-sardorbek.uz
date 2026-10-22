# Xavfsizlik — admin panel va content API

Bu hujjat `otaboyev-sardorbek.uz` saytining admin autentifikatsiyasi, PHP API, cPanel deploy va shaxsiy Telegram bot xavfsizligini tavsiflaydi. Kod `backend/admin.php`, `backend/bot.php`, `backend/config.php`, `backend/mailer.php`, `api.php` va `.htaccess` da.

## Auth modeli

1. Admin Telegram Login Widget orqali tasdiqlanadi.
2. PHP Telegram `hash` ni bot token bilan HMAC-SHA256 orqali tekshiradi va `auth_date` 5 daqiqadan oshmaganini nazorat qiladi.
3. Faqat `ADMIN_TELEGRAM_ID` (bootstrap) yoki `admin_users` jadvalidagi faol admin ruxsat etiladi.
4. Admin emailiga 6 xonali bir martalik kod yuboriladi. Kod 10 daqiqa amal qiladi, 5 urinishdan keyin bekor qilinadi.
5. Kod tasdiqlangach HttpOnly session cookie o‘rnatiladi. Barcha o‘zgartirish so‘rovlari `X-CSRF-Token` talab qiladi.

Admin panel manzillari: `/admin/`, `/admin/verify/`, `/admin/dashboard/`.

Bot bridge alohida: `POST /api.php/bot/webhook` Telegram `X-Telegram-Bot-Api-Secret-Token` va numeric admin ID’ni tekshiradi; faqat `message` update’lari. `/send` emailni cPanel `mail()` bilan yuboradi, `/inbox` va `/read` esa IMAP orqali `admin@` qutisini o‘qiydi. Bot OTP, bot token, app secret yoki mailbox parolini Telegram’ga chiqarmaydi.

## Top 20 himoya nazorati

| # | Xavf | Himoya |
|---|---|---|
| 1 | SQL injection | Barcha so‘rovlar PDO prepared statement; jadval nomi faqat allowlist orqali. Production DB user faqat `SELECT, INSERT, UPDATE, DELETE` huquqiga ega. |
| 2 | Stored / reflected XSS | React matnni escape qiladi; legacy JS `innerHTML` o‘rniga `textContent`/DOM ishlatadi; admin API faqat JSON qaytaradi; legacy form email summary HTML escape qilinadi. |
| 3 | CSRF | Session tokenidan HMAC CSRF token; `X-CSRF-Token`; `Origin`/`Referer` scheme, host va port bo‘yicha qat’iy tekshiriladi. |
| 4 | Session fixation | Login muvaffaqiyatida 32 baytli yangi random session token; eski challenge bir martalik. |
| 5 | Cookie o‘g‘irlash | `HttpOnly`, HTTPS’da `Secure`, session `SameSite=Strict`, challenge `SameSite=Lax`, `Path=/`. |
| 6 | Brute force | 6 xonali kod, 5 urinish, 10 daqiqa TTL; 15 daqiqada 3 ta kod va 5 ta Telegram start limiti. |
| 7 | OTP replay | Kod hash HMAC bilan saqlanadi; challenge `consumed_at` bilan bir martalik; yangi kod eskisini bekor qiladi. |
| 8 | Telegram soxta callback | Bot token asosidagi HMAC tekshiruvi; `hash_equals`; `auth_date` oynasi; faqat raqamli Telegram ID. |
| 9 | Ruxsatsiz admin | `admin_users.is_active` + Telegram allowlist; begona Telegram akkaunt `unauthorized` ga yo‘naltiriladi. |
| 10 | IDOR | Har bir admin endpoint session talab qiladi; project ID faqat autentifikatsiyadan keyin ishlatiladi. |
| 11 | CORS suiiste’moli | Admin, bot, form va token endpointlarga `Access-Control-Allow-Origin` berilmaydi; wildcard `*` faqat public read-only JSON endpointlarda (`profile`, `projects`, `health`, `status`); cookie ishlatilmaydi. |
| 12 | Clickjacking | `.htaccess`: `X-Frame-Options: DENY` va CSP `frame-ancestors 'none'`. |
| 13 | MIME sniffing / browser policy | `X-Content-Type-Options: nosniff`, CSP, `Referrer-Policy`, `Permissions-Policy`, COOP/CORP va `X-Permitted-Cross-Domain-Policies`; API’da `X-Powered-By` olib tashlangan. |
| 14 | HTTPS downgrade | `.htaccess` HTTP’ni canonical HTTPS hostga 301 qiladi (localhost/127.0.0.1 bundan mustasno); `Strict-Transport-Security: max-age=31536000` (api.php faqat HTTPS’da yuboradi); cPanel AutoSSL/Force HTTPS ham yoqilgan. |
| 15 | Secret leakage | `config.local.php`, `.env` va `github_keys.txt` gitignored; `.htaccess` ularni, source papkalarni, `.sql`/`.log`/`.md` va nested hidden fayllarni HTTP’dan to‘sadi; bot token, webhook secret, IMAP paroli va app secret hech qachon frontendga chiqmaydi. |
| 16 | Email header injection | Email, From, Reply-To va subject qiymatlari `\r`, `\n`, `\0` dan tozalanadi; recipient `FILTER_VALIDATE_EMAIL` bilan tekshiriladi; legacy email HTML va CSV formula injection’dan himoyalangan. |
| 17 | Open redirect / XSS | Telegram callback faqat qat’iy `/admin/verify/` yoki `/admin/?error=...` manziliga yo‘naltiradi; legacy form redirect faqat `http(s)` yoki relative path qabul qiladi. |
| 18 | Error / stack trace leakage | API faqat `{status,message,code}` qaytaradi; batafsil xato `error_log` ga yoziladi. |
| 19 | Audit yo‘qligi | `admin_auth_events` va `admin_audit_log`: login, logout, profile/project o‘zgarishlari, IP hash. |
| 20 | Eski sessiya / challenge to‘planishi | Muddati o‘tgan session/challenge va 7 kundan eski auth eventlar opportunistik tozalanadi. |

Qo‘shimcha: PHP 7.1 sintaksisi saqlanadi, `api.php` `display_errors=0` va `log_errors` ni majburiy qiladi, MySQL foydalanuvchisiga faqat kerakli bazaga ruxsat beriladi, `database/` va `backend/` HTTP’dan yopiladi. Production PHP 8.1+ tavsiya etiladi.

## Telegram bot bridge

- Faqat `POST /api.php/bot/webhook`; GET `405`, secret yo‘q/noto‘g‘ri `403`.
- Telegram `secret_token` bilan `X-Telegram-Bot-Api-Secret-Token` solishtiriladi (`hash_equals`); secret kamida 32 belgi.
- Update’dagi `from.id` va `chat.id` `admin.telegram_id`ga teng bo‘lishi shart; boshqa foydalanuvchi e’tiborsiz qoldiriladi.
- `/inbox` va `/read` faqat `admin@otaboyev-sardorbek.uz` IMAP qutisini o‘qiydi; `/send` `no-reply@` orqali cPanel `mail()` bilan yuboradi.
- Har bir `update_id` `bot_updates_seen`da bir marta qayd etiladi; Telegram retry’da `/send` takrorlanmaydi. `/inbox` 10/min, `/read` 20/min, `/send` 10/min (`bot_rate_limit`).
- Bot token, OTP, app secret, IMAP/SMTP parollari javob yoki logga chiqarilmaydi. Webhook URL public, ammo secret va allowlist bo‘lmasa amal bajarilmaydi.
- BotFather’da command’lar `setMyCommands` bilan ro‘yxatga olingan; webhook `allowed_updates=["message"]`.

## Email autentifikatsiyasi holati (2026-10-22)

- SPF external DNS’da mavjud va Port25 tekshiruvi `pass` berdi.
- DKIM external DNS’da yo‘q; Exim hozir outbound xatni imzolamaydi. cPanel `install_dkim_private_keys` 2048/1024-bit kalitlar bilan `RSA.xs: OpenSSL error: unsupported` qaytardi; root/WHM yoki provider aralashuvi kerak.
- DMARC external `v=DMARC1; p=none;`; karantin/reject’ga o‘tkazilmagan.
- `admin@` OTP va inbox, `no-reply@` yuborish, `contact@` → `admin@` forwarder’i cPanel’da ishlaydi. Bu **email autentifikatsiyasi to‘liq yakunlangan degani emas**.

## API token

`/api.php/websites` `X-Api-Token` yoki `Authorization: Bearer` talab qiladi. Agar endpoint ishlatilsa, productionda `API_TOKEN` (yoki `SITEHUB_API_TOKEN`/`OTABOYEV_API_TOKEN`) o‘rnatilishi shart. Hozircha endpoint ishlatilmaydi va token yo‘q; shu sababli u ataylab `401` qaytaradi. Kodda faqat `APP_ENV=local`, `development` yoki `test` bo‘lganda `otaboyev-dev-token` fallback’i ishlaydi; productionda public dev token qabul qilinmaydi.

## Production sozlash tartibi

1. **HTTPS**: cPanel AutoSSL yoqilgan va `https://otaboyev-sardorbek.uz` ishlayotganini tekshiring.
2. **Telegram**:
   - BotFather’da bot yarating.
   - `/setdomain otaboyev-sardorbek.uz` (protokolsiz, faqat domen).
   - Bot token va username’ni `config.local.php` ga yozing.
   - Admin Telegram ID’ni `@userinfobot` orqali aniqlang.
3. **Email**:
   - cPanel’da `admin@otaboyev-sardorbek.uz` (OTP/inbox) va `no-reply@otaboyev-sardorbek.uz` (yuborish) mailbox’larini yarating; `contact@...` → `admin@...` forwarder’ini qo‘shing.
   - Email Deliverability bo‘limida SPF, DKIM, DMARC holatini tekshiring. Hozirgi serverda SPF `pass`, DKIM `none`, DMARC `p=none`; DKIM signing uchun root/WHM yoki provider amali kerak.
   - `ADMIN_MAIL_DRIVER=mail` (cPanel local) yoki `smtp`/`resend`/`brevo` tanlang.
4. **App secret**: `php -r "echo bin2hex(random_bytes(32));"` bilan 64 belgili hex yarating.
5. **MySQL**: yangi baza uchun `schema.sql` va `seed.sql` import qiling; mavjud bazada avval `database/migrate-project-sections.sql`, `database/migrate-contact-messages.sql` va `database/migrate-bot-guards.sql` ni qo‘llang. `seed.sql` mavjud admin tahrirlarini bosib ketishini unutmang. Importdan keyin DB user’ga faqat `SELECT, INSERT, UPDATE, DELETE` bering; migratsiya uchun `CREATE`/`ALTER`/`DROP` ni vaqtincha qo‘shing.
6. **Config**: `config.local.example.php` dan `config.local.php` yarating, lekin uni git’ga qo‘shmang. `/websites` endpointidan foydalansangiz, hosting environment’ida `API_TOKEN` ni ham o‘rnating.
7. **Deploy**: `out/` tarkibini web rootga ko‘chiring; `backend/`, `config.local.php`, `.htaccess` joyida bo‘lsin; `github_keys.txt` web rootga ko‘chirilmasin.
8. **Tekshirish**: `/admin/` ni HTTPS’da oching, Telegram widget’ni bosing, email kodni kiriting, profile va project saqlang.

## cPanel checklist

- [x] PHP 7.1+ (productionda 8.x), `pdo_mysql`, `curl`, `openssl`, `mbstring`, `imap` yoqilgan.
- [x] `mod_rewrite`, `mod_headers` faol; `.htaccess` `AllowOverride All`.
- [x] `config.local.php` fayl ruxsati `600`, web root egasi cPanel user.
- [x] `backend/` va `database/` HTTP’dan 403 (`.htaccess` qoidalari).
- [x] `APP_ENV=production`; `ADMIN_MAIL_DRIVER=log` ishlatilmaydi.
- [ ] `API_TOKEN` o‘rnatilgan yoki endpoint ataylab `401` (hozir productionda token yo‘q; `/api.php/websites` 401).
- [x] `github_keys.txt` web rootda emas yoki `.htaccess` bilan 403.
- [ ] Telegram BotFather domeni aynan `otaboyev-sardorbek.uz` (real Login Widget testi kutilmoqda).
- [x] `admin@` va `no-reply@` mailbox’lari, `contact@` forwarder’i; SPF `pass`.
- [ ] DKIM external DNS’da va Exim’da imzolash; DMARC `p=none` (provider/WHM amali kerak).
- [x] MySQL `admin_users` jadvalida faqat kerakli admin bor; DB user least-privilege.
- [x] HTTPS majburiy; HSTS, CSP, `X-Frame-Options: DENY` va boshqa headerlar javobda ko‘rinadi.
- [x] `out/` build’ida `config.local.php` **yo‘q**; web rootga qo‘lda `600` bilan qo‘yiladi.
- [x] `api.php` `display_errors=0`, `log_errors` yoqilgan.
- [x] Telegram bot webhook secret + numeric admin ID allowlist; `pending_update_count=0`.
- [x] Bot `update_id` idempotency va `/inbox`/`/read`/`/send` rate limit (`bot_updates_seen`, `bot_rate_limit`).
- [ ] Logrotate: `bot.log` va PHP error log 10 MB (cPanel).
- [ ] Kunlik DB backup + tashqi nusxa (cPanel/S3).
- [ ] Monitoring/alerting: admin login fail, webhook secret fail, DB/IMAP fail (tashqi monitor yoki `alert_admin()`).
- [x] Bot IMAP `panel2.eskiz.uz:993/imap/ssl`; cPanel `mail()` orqali `/send` yetkazilgan.
- [ ] Haqiqiy Telegram admin xabari bilan inbound webhook testi (fake update bilan sinalgan).
- [x] SSH key passphrase rotated 2026-10-22; new value repo tashqarisida, mode 600.

## Incident response

Agar admin session yoki bot token oshkor bo‘lsa:

1. BotFather’da `/revoke` bilan tokenni almashtiring; `setWebhook`ni yangi `secret_token` bilan qayta o‘rnating.
2. `APP_SECRET` va `bot.webhook_secret` ni yangilang — barcha session va challenge bekor bo‘ladi.
3. `admin_sessions`, `admin_login_challenges` ni tozalang.
4. `admin_audit_log` va `admin_auth_events` ni tekshiring.
5. Email parolini, IMAP parolini va cPanel’ga kirishni almashtiring.
6. `config.local.php` ni qayta yozing; eski faylni o‘chiring.
7. DKIM kaliti oshkor bo‘lgan deb hisoblansa, DKIM’ni disable qilib, provider/WHM orqali yangi kalit generatsiya qiling; eski `default._domainkey` yozuvini qoldirmang.
8. SSH kalit passphrase’i oshkor bo‘lgan bo‘lsa, kalitni almashtiring yoki passphrase’ni rotate qiling.
=================================================================================================

# 🔒 Yangilangan `SECURITY.md` — `otaboyev-sardorbek.uz`

> **Versiya:** 3.1
> **Oxirgi yangilanish:** 2026-10-22
> **Asos:** loyihaning haqiqiy `project_structure.json` tarkibi, `backend/*.php`, `api.php`, `config.local.php`, `.htaccess` va deploy skriptlari
> **Ko‘rib chiqish davri:** har 90 kunda

---

## ⚠️ 0. TEZKOR OGOHLANTIRISH — 2026-10-22 HOLATI

Quyidagi zaifliklar `project_structure.json` dan **haqiqiy kod va maxfiy qiymatlar** orqali aniqlangan. C-5...C-7 kodda tuzatilgan; C-1...C-4 owner/GitHub/BotFather/cPanel amalini kutmoqda:

| # | Zaiflik | Fayl / joy | Darhol amal |
|---|---------|-----------|-------------|
| **C-1** | **GitHub PAT ochiq** | `github_keys.txt` → `<redacted>` | **Owner:** https://github.com/settings/tokens → **Revoke** → yangi token yaratib, **repo tashqarisiga** `chmod 600` bilan saqlang. Fayl 600, gitignored, build/deploy’ga kirmaydi |
| **C-2** | **`APP_SECRET` ochiq** | `config.local.php` → `<redacted>` | **Owner:** `php -r "echo bin2hex(random_bytes(32));"` bilan yangilang; barcha session/challenge bekor bo‘ladi. Fayl 600, gitignored, build’ga kirmaydi |
| **C-3** | **Bot token ochiq** | `config.local.php` → `<redacted>` | **Owner:** BotFather → `/revoke` → yangi token → `setWebhook` yangi `secret_token` bilan |
| **C-4** | **Admin email Gmail’da** | `config.local.php` → `<redacted>` | **Owner:** `ADMIN_EMAIL` ni `admin@otaboyev-sardorbek.uz` ga ko‘chirish; Gmail akkauntni zaxira sifatida saqlash |
| **C-5** | **`config.local.php` `out/` ga nusxalanadi** | `scripts/copy-static.mjs` | ✅ **Fixed 2026-10-22:** build nusxalamaydi; `run_local.sh`/`run_server.sh` preview uchun `600` bilan nusxalaydi; CI `out/config.local.php` yo‘qligini tekshiradi |
| **C-6** | **CORS `*` form endpoint’da** | `api.php` | ✅ **Fixed 2026-10-22:** wildcard CORS faqat `/profile`, `/projects`, `/projects/{slug}`, `/health`, `/status`; form/admin/bot/token endpointlarda CORS yo‘q |
| **C-7** | **HSTS HTTP’da ham yuboriladi** | `api.php` | ✅ **Fixed 2026-10-22:** HSTS faqat HTTPS aniqlanganda yuboriladi; HTTP’da yo‘q |

> **2026-10-22 holati:** C-5, C-6, C-7 kodda tuzatildi va test qilindi. C-1, C-2, C-3, C-4 — faqat owner/GitHub/BotFather/cPanel orqali bajariladi; kod ularni o‘zi bekor qila olmaydi.

---

## 1. Auth modeli (haqiqiy holat)

Loyihaning `backend/admin.php` va `backend/config.php` bo‘yicha real oqim:

```
Telegram widget → /api.php/admin/auth/telegram?hash=...
   ↓ (HMAC-SHA256, auth_date ≤ 300s)
   ↓ Telegram ID allowlist (admin_users.telegram_id yoki ADMIN_TELEGRAM_ID)
   ↓ challenge cookie (SameSite=Lax, 32-baytli random token)
admin_login_challenges'ga hash yoziladi (HMAC-SHA256(challenge_token:code, APP_SECRET))
   ↓ 6 xonali OTP → admin@otaboyev-sardorbek.uz (mail driver)
POST /api.php/admin/auth/verify { code } (CSRF yo‘q — challenge o‘zi token)
   ↓ 5 urinish, 10 daqiqa TTL, bir martalik (consumed_at)
session cookie (HttpOnly, Secure, SameSite=Strict, 43200s)
   ↓ Har bir mutatsiya: X-CSRF-Token = HMAC-SHA256('csrf:'+session_token, APP_SECRET)
   ↓ Origin/Referer scheme+host+port tekshiruvi
OK
```

**Muhim kuzatishlar (`backend/admin.php`):**
- `admin_telegram_validate()` — `hash_equals()` bilan HMAC (✅ to‘g‘ri)
- `admin_create_challenge()` — HMAC-hashed `code_hash` (✅)
- `admin_handle_verify()` — `FOR UPDATE` bilan challenge qulflanadi (✅ race-condition himoyasi bor)
- `admin_rate_count()` — `admin_login_challenges` va `admin_auth_events` dan (✅ limit ishlaydi)
- ⚠️ `admin_check_origin()` — agar `Origin` ham, `Referer` ham bo‘lmasa, **ruxsat beradi** (faqat CSRF ga tayanadi). CSRF himoyasi kuchli, lekin defense-in-depth nuqtai nazaridan `Origin: null` va bo‘sh holatni ham rad etish kerak.

---

## 2. Xavfsizlik nazoratlari (30 ta — real kodga mos)

### 2.1. Kod-darajadagi nazoratlar (haqiqiy holat)

| # | Xavf | Himoya (kodda) | Fayl |
|---|------|----------------|------|
| 1 | SQLi | `PDO::prepare` + `execute(array)`, `ATTR_EMULATE_PREPARES => false` | `backend/config.php:content_db()` |
| 2 | XSS | `htmlspecialchars(..., ENT_QUOTES \| ENT_SUBSTITUTE, 'UTF-8')` | `api.php:api_escape_html()`, `backend/admin.php` javoblari JSON |
| 3 | CSRF | `admin_require_csrf()` — `hash_equals()` | `backend/admin.php` |
| 4 | Session fixation | `admin_create_session()` har safar yangi `admin_token(32)` | `backend/admin.php` |
| 5 | Cookie | `HttpOnly; Secure; SameSite=Strict` (session), `SameSite=Lax` (challenge) | `backend/admin.php:admin_set_cookie()` |
| 6 | Brute force | `ADMIN_RATE_MAX_CHALLENGES=3`, `ADMIN_RATE_MAX_STARTS=5`, `ADMIN_RATE_WINDOW_MINUTES=15` | `backend/admin.php` |
| 7 | OTP replay | `consumed_at` bilan bir martalik; `attempts` bilan 5 limit | `backend/admin.php:admin_handle_verify()` |
| 8 | Telegram soxta callback | `hash_equals()` + `auth_date` oynasi | `backend/admin.php:admin_telegram_validate()` |
| 9 | Ruxsatsiz admin | `admin_users.is_active=1` + `telegram_id` allowlist | `backend/admin.php:admin_find_or_bootstrap_user()` |
| 10 | IDOR | Har bir `admin_*_item()` funksiyasi `admin_require_session()` chaqiradi | `backend/admin.php` |
| 11 | CORS | Admin va bot endpoint’larida ACAO **yo‘q** | `api.php` |
| 12 | Clickjacking | `X-Frame-Options: DENY` + CSP `frame-ancestors 'none'` | `api.php` + `.htaccess` |
| 13 | MIME sniffing | `X-Content-Type-Options: nosniff` | `api.php` |
| 14 | HTTPS downgrade | HSTS **faqat HTTPS’da** (`api.php`) va Apache `.htaccess` da; HTTP’da yuborilmaydi | `api.php` + `.htaccess` |
| 15 | Secret leakage | `config.local.php` build’ga **kirmaydi**; `600`, gitignored; CI tekshiradi | `scripts/copy-static.mjs`, CI |
| 16 | Email header injection | `api_clean_header()` — `\r\n\0` olib tashlaydi | `api.php`, `backend/mailer.php` |
| 17 | Open redirect | Faqat `http(s)` yoki `/` bilan boshlanuvchi xavfsiz relative | `api.php:handleFormSubmission()` |
| 18 | Error leakage | `display_errors=0`, API javobida stack trace yo‘q | `api.php` |
| 19 | Audit | `admin_audit_log`, `admin_auth_events` (IP **hash**, PII emas) | `backend/admin.php` |
| 20 | Eski sessiya | `admin_cleanup()` — `random_int(1,10) === 1` bo‘lsa ishlaydi | `backend/admin.php` |

### 2.2. `bot.php` uchun (haqiqiy holat)

| # | Xavf | Holat | Izoh |
|---|------|-------|------|
| 21 | `stdClass` cast xatosi | ✅ **Tuzatilgan** | `$row = is_array($overview) && isset($overview[0]) ? (array) $overview[0] : array();` |
| 22 | IMAP resurs leak | ✅ **Tuzatilgan** | `bot_imap_close($inbox)` har doim chaqiriladi |
| 23 | IMAP SSL | ✅ **To‘g‘ri** | `$flags = $config['imap_ssl'] ? '/imap/ssl' : '/imap/notls';` — `/novalidate-cert` **yo‘q** |
| 24 | IMAP UID ishlatish | ✅ **To‘g‘ri** | `imap_uid()` + `imap_msgno()` |
| 25 | Webhook secret | ✅ | `hash_equals()` + `strlen >= 32` |
| 26 | Admin ID allowlist | ✅ | `$chatId !== $config['admin_id']` → `return` |
| 27 | Faqat `message` update | ✅ | `if (!isset($update['message'])) return;` |
| 28 | Body size limit | ✅ | `strlen($raw) > 131072` → 413 |
| 29 | **Rate limiting** | ❌ **Yo‘q** | `/send`, `/inbox` uchun per-minute limit yo‘q |
| 30 | **Update idempotency** | ❌ **Yo‘q** | Telegram retry → ikki marta email yuborilishi mumkin |
| 31 | **`/send` rate** | ❌ **Yo‘q** | Admin kompromis bo‘lsa — spam vositasi |
| 32 | **`bot_clean_body()` regex** | ⚠️ | `preg_replace('/[^\P{C}\n\t]+/u', ...)` — noto‘g‘ri pattern bo‘lsa `null` qaytarishi mumkin; test kerak |

---

## 3. Fayl va papka tuzilishi + ruxsatlar (haqiqiy)

`project_structure.json` bo‘yicha:

```
otaboyev-sardorbek.uz/
├── api.php                        644  ← public
├── .htaccess                      644  ← Apache qoidalari
├── next.config.js                 644
├── package.json                   644
├── AGENTS.md / tz.md / tz_master.md  644
│
├── backend/                       755  ⛔ HTTP'dan yopilishi shart
│   ├── admin.php                  644
│   ├── bot.php                    644
│   ├── config.php                 644
│   ├── config.local.php           600  🔒 (secrets) — repo tashqarisida
│   ├── mailer.php                 644
│   ├── contact.php                644
│   ├── content.php                644
│   └── .htaccess                  644  # Deny from all
│
├── database/                      755  ⛔ HTTP'dan yopilishi shart
│   ├── schema.sql                 644
│   ├── seed.sql                   644
│   ├── migrate-project-sections.sql  644
│   ├── migrate-contact-messages.sql  644
│   └── migrate-bot-guards.sql      644  # idempotent
│
├── github_keys.txt                600  🚨 Revoke qiling, keyin o‘chiring
├── config.local.php               600  (web rootga qo‘lda; build’ga kirmaydi)
│
├── out/                           755  → web rootga ko‘chiriladi
│   ├── api.php
│   ├── backend/                   ⚠️ config.local.php **bu yerda emas**
│   └── (config.local.php yo‘q — CI tekshiradi)
│
├── app/(site)/[locale]/...        Next.js sahifalari
├── components/                    React
├── components/admin/              Admin UI
├── content/locales.js
├── scripts/copy-static.mjs        🚨 config.local.php nusxalaydi
└── .github/workflows/ci.yml
```

### 3.1. Ruxsatlar jadvali

| Fayl / papka | Ruxsat | Egasi |
|--------------|--------|-------|
| `backend/config.local.php` | `600` | cPanel user |
| `.env` (agar bo‘lsa) | `600` | cPanel user |
| `github_keys.txt` | `600` (yoki **o‘chirilgan**) | cPanel user |
| `backend/*.php` | `644` | cPanel user |
| `backend/` papka | `755` | cPanel user |
| `*.log` | `640` | cPanel user |
| `database/` papka | `755` | cPanel user |
| `out/` papka | `755` | cPanel user |

---

## 4. `api.php` — CORS va header tahlili

### 4.1. CORS — 2026-10-22 holati

**Eski holat (audit paytida):** `/admin`, `/websites`, `/bot/webhook`dan tashqari barcha yo‘llarga `Access-Control-Allow-Origin: *` berilardi; `/form_container/submit` ham CORS `*` olardi.

**Joriy holat (tuzatilgan):** wildcard CORS faqat public read-only JSON endpoint’larda beriladi:

```php
$publicOnly = in_array($requestPath, ['/profile', '/projects', '/health', '/status'], true)
    || preg_match('#^/projects/[a-z0-9-]+/?$#', $requestPath);

if ($publicOnly) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-Api-Token, Authorization');
}
```

`/form_container/submit`, `/admin/*`, `/bot/webhook`, `/websites` CORS header qaytarmaydi. `.htaccess` qo‘shimcha himoya qatlami sifatida shu yo‘llardan CORS headerlarini o‘chiradi.

### 4.2. HSTS — 2026-10-22 holati

**Joriy kod:** HSTS faqat HTTPS aniqlanganda yuboriladi; shared hosting uchun `includeSubDomains`/`preload` qo‘shilmagan:

```php
if (api_is_https()) {
    header('Strict-Transport-Security: max-age=31536000');
}
```

HTTP’da HSTS header yo‘qligi test qilindi. `.htaccess` static javoblarga HSTS qo‘shadi; `includeSubDomains` ataylab yo‘q.

### 4.3. `Content-Security-Policy` — API uchun tor

```php
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
```

Bu **API javoblari uchun to‘g‘ri**. Ammo HTML sahifalarda bu CSP sizning `.htaccess` da boshqacha bo‘lishi kerak — Telegram widget uchun `script-src https://telegram.org` ruxsat etilishi shart.

---

## 5. DB maydonlari (haqiqiy `schema.sql` asosida)

### 5.1. `admin_users`
| Maydon | Tur | Izoh |
|--------|-----|------|
| `id` | INT UNSIGNED PK | AUTO_INCREMENT |
| `telegram_id` | BIGINT UNSIGNED UNIQUE | |
| `email` | VARCHAR(190) | OTP manzil |
| `display_name` | VARCHAR(120) NULL | |
| `telegram_username` | VARCHAR(64) NULL | |
| `is_active` | TINYINT(1) DEFAULT 1 | |
| `last_login_at` | DATETIME NULL | |
| `created_at` / `updated_at` | DATETIME | |

### 5.2. `admin_login_challenges`
| Maydon | Tur | Izoh |
|--------|-----|------|
| `id` | BIGINT UNSIGNED PK | |
| `admin_id` | INT UNSIGNED FK → admin_users | ON DELETE CASCADE |
| `challenge_hash` | VARCHAR(64) UNIQUE | HMAC('challenge:'+token) |
| `code_hash` | VARCHAR(64) | HMAC('otp:'+token+':'+code) |
| `email` | VARCHAR(190) | snapshot |
| `attempts` | TINYINT UNSIGNED DEFAULT 0 | |
| `expires_at` | DATETIME | +600s |
| `consumed_at` | DATETIME NULL | bir martalik |
| `ip_hash` | VARCHAR(64) NULL | |
| `created_at` | DATETIME | |

### 5.3. `admin_sessions`
| Maydon | Tur | Izoh |
|--------|-----|------|
| `id` | BIGINT UNSIGNED PK | |
| `admin_id` | INT UNSIGNED FK | |
| `token_hash` | VARCHAR(64) UNIQUE | HMAC('session:'+token) |
| `expires_at` | DATETIME | +43200s (12h) |
| `last_seen_at` | DATETIME | sliding |
| `ip_hash` / `user_agent` | VARCHAR | |

### 5.4. `admin_auth_events`
| Maydon | Tur | Izoh |
|--------|-----|------|
| `event_type` | VARCHAR(40) | `challenge_created`, `login_success`, `telegram_rejected_*`, `otp_failed`, `challenge_rate_limited` |
| `telegram_id` | BIGINT UNSIGNED NULL | |
| `ip_hash` | VARCHAR(64) NULL | |
| `created_at` | DATETIME | 7 kundan eski tozalanadi |

### 5.5. `admin_audit_log`
| Maydon | Tur | Izoh |
|--------|-----|------|
| `admin_id` | INT UNSIGNED NULL FK | ON DELETE SET NULL |
| `action` | VARCHAR(48) | `login`, `logout`, `profile_updated`, `project_created`, `message_read`, `admin_created` ... |
| `entity` / `entity_id` | VARCHAR | nishon |
| `ip_hash` | VARCHAR(64) | |
| `details` | TEXT NULL | JSON |

### 5.6. `contact_messages` (rate limit uchun asos)
| Maydon | Tur |
|--------|-----|
| `form_id` / `locale` / `name` / `email` / `subject` / `message` | VARCHAR / TEXT |
| `payload` | MEDIUMTEXT (JSON) |
| `ip_hash` | VARCHAR(64) |
| `is_read` | TINYINT(1) |
| `created_at` | DATETIME |

**Rate limit query:**
```php
WHERE form_id = :form_id AND ip_hash = :ip_hash
  AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE) -- limit 5
```

### 5.7. Bot guard jadvallari (2026-10-22 da qo‘shildi)

`database/schema.sql` va `database/migrate-bot-guards.sql` da mavjud:

```sql
-- bot_updates_seen: Telegram retry idempotency
CREATE TABLE IF NOT EXISTS bot_updates_seen (
  update_id BIGINT UNSIGNED NOT NULL,
  seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (update_id),
  KEY idx_seen_at (seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- bot_rate_limit: /send, /inbox uchun per-admin limit
CREATE TABLE IF NOT EXISTS bot_rate_limit (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id INT UNSIGNED NOT NULL,
  command VARCHAR(32) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_admin_cmd_time (admin_id, command, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Tozalash (cron yoki opportunistik):
```sql
DELETE FROM bot_updates_seen WHERE seen_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
DELETE FROM bot_rate_limit   WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 DAY);
```

---

## 6. Amalga oshirilgan himoya (2026-10-22)

> Quyidagi kod misollari audit tavsiyasi sifatida yozilgan va 2026-10-22 da `backend/bot.php`, `api.php`, `scripts/copy-static.mjs` va `backend/admin.php` ga kiritildi. Aniq joriy implementatsiya uchun shu fayllarga qarang.

### 6.1. `backend/bot.php` — rate limit

```php
function bot_rate_limit_ok(int $adminId, string $command, int $maxPerMin = 10): bool {
    try {
        $pdo = content_db();
    } catch (Exception $e) {
        return true; // DB yo‘q — botni bloklamaymiz, lekin log
    }
    $st = $pdo->prepare(
        "INSERT INTO bot_rate_limit (admin_id, command, created_at)
         SELECT ?, ?, NOW() FROM DUAL
         WHERE (SELECT COUNT(*) FROM bot_rate_limit
                WHERE admin_id = ? AND command = ?
                  AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)) < ?"
    );
    $st->execute([$adminId, $command, $adminId, $command, $maxPerMin]);
    return $st->rowCount() === 1;
}
```

### 6.2. `backend/bot.php` — update idempotency

```php
function bot_is_duplicate_update(int $updateId): bool {
    try {
        $pdo = content_db();
    } catch (Exception $e) {
        return false;
    }
    $st = $pdo->prepare(
        "INSERT IGNORE INTO bot_updates_seen (update_id, seen_at) VALUES (?, NOW())"
    );
    $st->execute([$updateId]);
    return $st->rowCount() === 0; // 0 → allaqachon ko‘rilgan
}
```

`bot_handle_webhook()` ichida:
```php
if (isset($update['update_id']) && bot_is_duplicate_update((int)$update['update_id'])) {
    http_response_code(200); echo 'ok'; exit; // takroriy
}
```

### 6.3. `backend/bot.php` — `bot_clean_body()` tuzatish

```php
function bot_clean_body($value, $max)
{
    $value = str_replace(["\r\n", "\r", "\0"], "\n", (string) $value);
    if (function_exists('mb_check_encoding') && !mb_check_encoding($value, 'UTF-8')) {
        $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }
    // faqat boshqaruv belgilarini olib tashlash (\n, \t saqlanadi)
    $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
    if (is_string($cleaned)) {
        $value = $cleaned;
    }
    if ($max > 0 && strlen($value) > $max) {
        $value = mb_strcut($value, 0, $max, 'UTF-8') . "\n…";
    }
    return trim($value);
}
```

> 2026-10-22: aynan shu versiya `backend/bot.php` ga kiritildi va UTF-8 kesish test qilindi.

### 6.4. `api.php` — form uchun origin tekshiruv

```php
if ($requestPath === '/form_container/submit') {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowed = ['https://otaboyev-sardorbek.uz', 'https://www.otaboyev-sardorbek.uz'];
    if ($origin !== '' && !in_array($origin, $allowed, true)) {
        http_response_code(403); exit;
    }
    // Origin header bo‘lmasa — server-to-server, ruxsat (lekin honeypot + IP limit bor)
}
```

> Bu **ixtiyoriy qo‘shimcha**; hozirgi kodda form `website` honeypot, email/uzunlik validatsiyasi, 10 daqiqada 5 ta va IP bo‘yicha 10 ta limit bilan himoyalangan. Origin tekshiruvi faqat admin mutation’larida majburiy.

### 6.5. `scripts/copy-static.mjs` — `config.local.php` ni olib tashlash

```js
// scripts/copy-static.mjs (audit paytida — xavfli)
const copies = [
  // ...
  ['config.local.php', 'config.local.php'], // ❌ out/ ga nusxalanadi
];

// scripts/copy-static.mjs (2026-10-22 — tuzatilgan)
const copies = [
  ['files', 'files'],
  ['js', 'js'],
  ['css', 'css'],
  ['favicon.svg', 'favicon.svg'],
  ['api.php', 'api.php'],
  ['backend', 'backend'],
  // ['config.local.php', 'config.local.php'], // ❌ olib tashlandi
  ['.htaccess', '.htaccess'],
  ['sitemap.xml', 'sitemap.xml'],
];
```

**Sabab:** `config.local.php` — `backend/config.php` da `dirname(__DIR__) . '/config.local.php'` orqali o‘qiladi. Agar `backend/` `out/backend/` ga ko‘chsa, `config.local.php` **`out/` da** bo‘lishi kerak (deploy vaqtida qo‘lda qo‘yiladi, build bilan emas). `.htaccess` uni to‘sishi kerak, lekin **defense-in-depth** — faylni umuman `out/` ga solmaslik.

**Yechim:** deploy skriptida:
```bash
# tar-deploy (README da tasvirlangan)
tar -C out --exclude='config.local.php' -cf - . | tar -C /home/host9590/public_html -xf -
# keyin:
cp /home/host9590/apps/otaboyev-sardorbek.uz/config.local.php /home/host9590/public_html/config.local.php
chmod 600 /home/host9590/public_html/config.local.php
```

### 6.6. `admin.php` — origin tekshiruvni kuchaytirish

```php
function admin_check_origin()
{
    $config = admin_config();
    $expected = admin_origin_parts($config['site_url']);
    if ($expected === null) {
        admin_fail('Cross-origin request rejected', 'origin_rejected', 403);
    }

    $origin  = $_SERVER['HTTP_ORIGIN']  ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';

    // Origin: null — bir necha brauzerlarda "no-cors" / sandboxed
    if ($origin === 'null') {
        admin_fail('Cross-origin request rejected', 'origin_rejected', 403);
    }

    $candidate = $origin !== '' ? $origin : $referer;
    if ($candidate === '') {
        // CSRF token majburiy, lekin qo‘shimcha himoya: same-site fetch header
        // X-Requested-With bo‘lmasa — rad etish (ixtiyoriy, lekin tavsiya)
        if (empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            admin_fail('Cross-origin request rejected', 'origin_rejected', 403);
        }
        return;
    }

    $actual = admin_origin_parts($candidate);
    if ($actual === null
        || $actual['host']   !== $expected['host']
        || $actual['scheme'] !== $expected['scheme']
        || $actual['port']   !== $expected['port']) {
        admin_fail('Cross-origin request rejected', 'origin_rejected', 403);
    }
}
```

---

## 7. Test rejasi (haqiqiy endpoint’lar asosida)

### 7.1. Auth testlari (2026-10-22 lokal, test DB)
- [x] `GET /api.php/admin/config` → `{enabled: true|false}`
- [x] `GET /api.php/admin/auth/telegram` noto‘g‘ri `hash` → `/?error=telegram`
- [x] `auth_date` 5 daqiqadan eski → `/?error=expired`
- [ ] `admin_users.is_active=0` → `/?error=unauthorized` (owner)
- [ ] 15 daqiqada 4-chi challenge → `/?error=rate` (owner)
- [x] `POST /api.php/admin/auth/verify` noto‘g‘ri kod → `401 invalid_code`; 6-chi urinishda `429 too_many_attempts`
- [ ] OTP `expires_at` o‘tgan → `401 challenge_expired` (owner)
- [ ] Bir OTP ikki marta → ikkinchisi `401` (owner)
- [x] `POST /api.php/admin/profile` CSRF header **siz** → `403 csrf_failed`
- [x] `Origin: https://evil.com` bilan POST → `403 origin_rejected`
- [x] `Origin: null` bilan POST → `403 origin_rejected`
- [x] Header’siz CLI POST → `403`; `X-Requested-With: XMLHttpRequest` bilan → o‘tadi

### 7.2. Bot testlari (2026-10-22 lokal, test DB)
- [x] `GET /api.php/bot/webhook` → `405`
- [x] `POST` secret **siz** → `403`
- [x] `POST` secret noto‘g‘ri → `403`
- [x] `POST` `secret_token` to‘g‘ri, lekin `from.id != admin_id` → `200 ok` lekin javob yo‘q
- [x] Bir `update_id` ikki marta → ikkinchisi `200 ok` lekin amal bajarilmaydi (`bot_updates_seen`da bitta qator)
- [x] `bot_rate_limit_ok('selftest', 2)` → `true, true, false`
- [ ] Body 128 KB dan katta → `413` (owner)
- [ ] `/inbox` bo‘sh quti → `Inbox bo‘sh.` matni (owner/IMAP)

### 7.3. IMAP testlari
- [ ] Bo‘sh quti → `[]` (xato yo‘q)
- [ ] 1 xat → `stdClass` → `(array)` cast ishlaydi
- [ ] 1000 xat → faqat oxirgi 5 ta (yoki `limit`)
- [ ] `imap_open` fail → `error_log('bot imap connection failed')` + user-friendly javob
- [ ] Har chaqiruvdan keyin `imap_close` (leak yo‘q)

### 7.4. CORS testlari (2026-10-22 lokal)
- [x] `GET /api.php/profile` `Origin: https://evil.com` bilan → `Access-Control-Allow-Origin: *` (public)
- [x] `POST /api.php/form_container/submit` `Origin: https://evil.com` bilan → **CORS header yo‘q**
- [x] `POST /api.php/admin/logout` `Origin: https://evil.com` bilan → `403 origin_rejected`
- [x] `POST /api.php/bot/webhook` `Origin` bilan → ACAO yo‘q
- [x] `OPTIONS /api.php/form_container/submit` → ACAO yo‘q
- [x] Apache 2.4 + `.htaccess`: `Header unset` mutation endpointlardan PHP qo‘shgan ACAO’ni o‘chiradi; public `/profile`da ACAO `*` qoladi (lokal test)

### 7.5. Konfiguratsiya testlari
- [x] `display_errors=0`, `log_errors=1` `api.php` tomonidan majburiy qilinadi
- [x] `config.local.php` `chmod 600`
- [x] `github_keys.txt` `chmod 600`, gitignored, build/deploy’ga kirmaydi (owner revoke qilishi shart)
- [x] `/backend/config.php` → `403` (router; Apache’da `.htaccess`)
- [x] `/database/schema.sql` → `403`
- [x] `/config.local.php` → `403`
- [ ] `https://otaboyev-sardorbek.uz` `Strict-Transport-Security` sarlavhasi bor (production/HTTPS)
- [ ] `http://otaboyev-sardorbek.uz` → `301 → https://...` (production/Apache)
- [x] `out/config.local.php` **yo‘q** (build + CI testi)

---

## 8. Monitoring va alerting (yangi)

### 8.1. Alert triggerlari

| Hodisa | Trigger | Kanal |
|--------|---------|-------|
| `unauthorized` login | 5 marta / 15 daqiqa | Telegram admin |
| OTP 5 urinish bekor | darhol | Telegram admin |
| Webhook secret noto‘g‘ri | darhol | Telegram admin |
| DB ulanish fail | 3 marta ketma-ket | Telegram admin |
| `imap_open` fail | 3 marta ketma-ket | Telegram admin |
| `429 too_many_requests` bot API | 3 marta / 1 daqiqa | Telegram admin |
| Yangi admin qo‘shildi | darhol | Telegram admin |
| `sitemap_write_failed` | darhol | Telegram admin |

### 8.2. `alert_admin()` — xavfsiz implementatsiya

```php
function alert_admin(string $message): void {
    $res = bot_api('sendMessage', [
        'chat_id' => admin_config()['telegram_id'],
        'text'    => '⚠️ ' . mb_substr($message, 0, 3500, 'UTF-8'),
        'disable_web_page_preview' => true,
    ]);
    if ($res === false) {
        $log = __DIR__ . '/logs/alerts.log';
        @file_put_contents($log, date('c') . ' ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
```

`backend/logs/` papka **`755`** va `.htaccess` bilan `Require all denied` bo‘lishi shart.

---

## 9. Backup va tiklash

| Nima | Chastota | Saqlash | Joy |
|------|----------|---------|-----|
| MySQL (`host9590_portfolio`) | kunlik 03:00 | 14 kun | cPanel + tashqi S3 (shifrlangan) |
| `config.local.php` | har o‘zgarishda | 30 kun | tashqi, **shifrlangan** (GPG yoki age) |
| `out/` build | har deploy’da | 5 versiya | `git tag` yoki artifact storage |
| Log fayllar | haftalik | 4 hafta | cPanel |

**Restore drill:** har chorakda bir marta `config.local.php` + DB dump’dan saytni tiklash mashqi o‘tkaziladi.

---

## 10. Incident response (kengaytirilgan)

### 10.1. GitHub PAT (`github_keys.txt`) oshkor bo‘ldi
1. https://github.com/settings/tokens → token → **Revoke**
2. Yangi `fine-grained` token (faqat kerakli repo, faqat `contents: read`)
3. `config.local.php` ga yozib, `chmod 600` (repo tashqarisida)
4. `github_keys.txt` faylini `shred -u` bilan o‘chirish (SSD’da `blkdiscard` yoki to‘liq disk shifrlash)
5. GitHub audit log’da notanish aktivlikni tekshirish

### 10.2. `APP_SECRET` oshkor bo‘ldi
1. `php -r "echo bin2hex(random_bytes(32));"` → `config.local.php` ga yozish
2. **Barcha session va challenge bekor** — `admin_sessions`, `admin_login_challenges` ni `TRUNCATE`
3. `admin_audit_log` va `admin_auth_events` oxirgi 24 soatni tekshirish
4. Bot `webhook_secret` ni ham yangilash

### 10.3. Bot token oshkor bo‘ldi
1. BotFather → `/revoke` → yangi token
2. `setWebhook` yangi `secret_token` bilan (min 32 belgi)
3. `config.local.php` → `telegram_bot_token` + `bot.webhook_secret`
4. `admin_sessions`, `admin_login_challenges` tozalash

### 10.4. IMAP/email parol oshkor bo‘ldi
1. cPanel → Email Accounts → parolni almashtirish
2. `config.local.php` → `bot.imap_pass`, `mail.smtp_pass`
3. `shred -u config.local.php` va qayta yozish

### 10.5. Umumiy tekshiruv ro‘yxati
- [ ] `admin_auth_events` oxirgi 24 soat
- [ ] `admin_audit_log` oxirgi 24 soat
- [ ] cPanel access log `/home/host9590/logs/`
- [ ] `.htaccess` `git diff` bilan tekshirish
- [ ] `admin_users` da notanish yozuvlar
- [ ] `admin_sessions` da notanish `ip_hash`
- [ ] `bot_updates_seen` (yangi) da notanish `update_id`
- [ ] `github_keys.txt` mavjud emasligi

---

## 11. Zaifliklar reyestri (yangi)

| ID | Zaiflik | Holat | Tuzatilgan | Izoh |
|----|---------|-------|-----------|------|
| V-01 | `stdClass` massiv sifatida | ✅ Fixed | 2026-10-22 | `(array)` cast, `isset()` |
| V-02 | IMAP `imap_close` yo‘q | ✅ Fixed | 2026-10-22 | `try/finally` |
| V-03 | IMAP SSL `/novalidate-cert` | ✅ Fixed | 2026-10-22 | `/imap/ssl` (cert tekshiriladi) |
| V-04 | **`github_keys.txt` ochiq** | ⚠️ **Owner** | 2026-10-22 | Fayl `600`, gitignored, build/deploy’ga kirmaydi; **PAT revoke** owner’da |
| V-05 | **`config.local.php` ochiq** | ⚠️ **Owner** | 2026-10-22 | `600`, gitignored, build’ga kirmaydi; **secret rotate** owner’da |
| V-06 | **`out/config.local.php` build bilan ketadi** | ✅ **Fixed** | 2026-10-22 | `copy-static.mjs` nusxalamaydi; CI tekshiradi |
| V-07 | **CORS `*` form endpoint** | ✅ **Fixed** | 2026-10-22 | Faqat public read endpointlar |
| V-08 | **HSTS HTTP’da** | ✅ **Fixed** | 2026-10-22 | `api_is_https()` sharti |
| V-09 | `admin_check_origin` bo‘sh header’da ruxsat | ✅ **Fixed** | 2026-10-22 | `Origin: null` rad; `X-Requested-With` fallback |
| V-10 | **Bot `/send` rate limit yo‘q** | ✅ **Fixed** | 2026-10-22 | `bot_rate_limit` + limitlar |
| V-11 | **Bot update idempotency yo‘q** | ✅ **Fixed** | 2026-10-22 | `bot_updates_seen` |
| V-12 | `bot_clean_body` UTF-8 kesish | ✅ **Fixed** | 2026-10-22 | `mb_strcut` + UTF-8 test |
| V-13 | `SameSite=Strict` Telegram widget bilan | ⚠️ **Owner test** | — | Challenge Lax, session Strict; real widget testi owner’da |
| V-14 | DKIM yo‘q | ⚠️ **Owner/provider** | — | WHM/Eskiz DNS |
| V-15 | DMARC `p=none` | ⚠️ **Owner/provider** | — | asta-sekin `p=quarantine` |
| V-16 | PHP 7.1 EOL | ⚠️ **Deploy** | — | Kod 7.1-compatible; production PHP 8.1+ tavsiya |
| V-17 | `display_errors` productionda | ✅ **Fixed** | 2026-10-22 | `api.php` doim `0` + `log_errors` |
| V-18 | Log rotatsiyasi | ⚠️ **Owner/ops** | — | cPanel/logrotate |
| V-19 | Backup rejasi yo‘q | ⚠️ **Owner/ops** | — | kunlik + tashqi nusxa |
| V-20 | Monitoring/alerting yo‘q | ⚠️ **Owner/ops** | — | `alert_admin()` yoki tashqi monitor |
| V-21 | Legacy Google Maps API kalitlari | ✅ **Mitigated** | 2026-10-22 | `en/*` va `webcard` da `REDACTED_...`; Google Cloud’da rotate/restrict owner’da |

---

## 12. cPanel checklist (haqiqiy holat)

- [x] PHP 7.1+ (production 8.x), `pdo_mysql`, `curl`, `openssl`, `mbstring`, `imap` yoqilgan
- [x] `mod_rewrite`, `mod_headers` faol; `.htaccess` `AllowOverride All`
- [x] `config.local.php` ruxsat `600`
- [x] `backend/`, `database/` HTTP’dan 403
- [x] `APP_ENV=production`
- [ ] `API_TOKEN` o‘rnatilgan yoki endpoint ataylab `401`
- [x] `github_keys.txt` web root’da **emas**, mode `600`, gitignored (repo tarixida bor — owner revoke qiladi)
- [ ] BotFather domeni `otaboyev-sardorbek.uz` (real Login Widget testi)
- [x] `admin@` + `no-reply@` mailbox, `contact@` forwarder; SPF `pass`
- [ ] DKIM DNS + Exim imzolash; DMARC `p=none`
- [x] MySQL `admin_users` faqat kerakli admin; DB user least-privilege
- [x] HTTPS majburiy; HSTS, CSP, `X-Frame-Options: DENY`
- [x] `out/config.local.php` deploy’da **yo‘q**; CI tekshiradi
- [x] Telegram webhook secret + numeric admin ID allowlist
- [x] Bot IMAP `panel2.eskiz.uz:993/imap/ssl`; `/send` cPanel `mail()`
- [ ] Haqiqiy Telegram admin xabari bilan inbound webhook testi
- [x] SSH key passphrase rotated 2026-10-22; mode 600, repo tashqarisida
- [x] `api.php` `display_errors=0`, `log_errors` yoqilgan
- [ ] `memory_limit=256M`, `max_execution_time=120` (cPanel)
- [ ] Logrotate sozlangan: `bot.log` 10 MB (cPanel)
- [x] Bot rate limit va idempotency (`bot_rate_limit`, `bot_updates_seen`)
- [x] CORS faqat public read endpointlarda
- [ ] GitHub PAT revoke qilingan (owner)
- [ ] `APP_SECRET` va bot token rotate qilingan (owner)
- [ ] Google Maps API kalitlari Google Cloud’da rotate/restrict qilingan (owner)

---

## 13. O‘zgartirishlar tarixi

| Versiya | Sana | O‘zgarish |
|---------|------|-----------|
| 1.0 | 2026-10-20 | Boshlang‘ich `SECURITY.md` |
| 2.0 | 2026-10-22 | 8 ta yangi nazorat qo‘shildi |
| **3.0** | **2026-10-22** | **Haqiqiy `project_structure.json` asosida qayta yozildi**; 7 ta **kritik** (C-1...C-7) va 20 ta boshqa zaiflik aniqlandi; CORS, HSTS, `config.local.php` build, bot rate limit, idempotency, alerting, backup va test rejasi qo‘shildi; zaifliklar reyestri 15 tadan 20 taga chiqarildi |
| **3.1** | **2026-10-22** | **Kod tuzatishlari va lokal testlar:** C-5/C-6/C-7, V-06...V-12, V-17 fixed; bot guard jadvallari va migratsiya; admin origin strict; `display_errors=0`; legacy Google Maps kalitlari redaksiya qilindi (V-21); test natijalari va owner amallari yangilandi |

---

## 14. Ilova A — P0 holati (2026-10-22)

**Kodda bajarildi va test qilindi:**
1. ✅ `scripts/copy-static.mjs` — `config.local.php` build’ga kirmaydi; CI tekshiradi
2. ✅ Deploy — `config.local.php` web rootga qo‘lda `600` bilan qo‘yiladi
3. ✅ `api.php` CORS — faqat public read-only endpointlar
4. ✅ `api.php` HSTS — faqat HTTPS’da
5. ✅ `api.php` — `display_errors=0`, `log_errors` yoqilgan
6. ✅ `admin_check_origin()` — `Origin: null` rad, strict host/scheme/port, `X-Requested-With` fallback
7. ✅ Bot — `bot_updates_seen` idempotency va `bot_rate_limit`
8. ✅ Legacy Google Maps kalitlari — `REDACTED_GOOGLE_MAPS_KEY`

**Faqat owner/provider bajaradi:**
1. **`github_keys.txt` GitHub PAT’ni revoke qilish** va yangi tokenni repo tashqarisida saqlash
2. **`APP_SECRET`** ni yangilash → `config.local.php` → `chmod 600` → session/challenge tozalash
3. **`telegram_bot_token`** revoke → BotFather yangi token → `setWebhook` yangi `secret_token`
4. **`admin@otaboyev-sardorbek.uz`** ga o‘tish, Gmail’ni zaxiraga olish
5. **Google Maps API kalitlarini** Google Cloud’da rotate/restrict qilish
6. **DKIM/DMARC** — WHM/Eskiz DNS
7. **Haqiqiy Telegram Login Widget** va bot `/help` testi
8. **Logrotate, backup va monitoring** — cPanel/ops

> **Muhim:** C-1...C-3 (PAT, `APP_SECRET`, bot token) owner tomonidan rotate qilinmaguncha to‘liq yopilgan hisoblanmaydi. Kod ularni o‘zi bekor qila olmaydi.