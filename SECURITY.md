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
| 11 | CORS suiiste’moli | Admin va bot endpointlarga `Access-Control-Allow-Origin` berilmaydi; public API’da `*` qoladi, lekin cookie ishlatilmaydi. |
| 12 | Clickjacking | `.htaccess`: `X-Frame-Options: DENY` va CSP `frame-ancestors 'none'`. |
| 13 | MIME sniffing / browser policy | `X-Content-Type-Options: nosniff`, CSP, `Referrer-Policy`, `Permissions-Policy`, COOP/CORP va `X-Permitted-Cross-Domain-Policies`; API’da `X-Powered-By` olib tashlangan. |
| 14 | HTTPS downgrade | `.htaccess` HTTP’ni canonical HTTPS hostga 301 qiladi (localhost/127.0.0.1 bundan mustasno); `Strict-Transport-Security: max-age=31536000`; cPanel AutoSSL/Force HTTPS ham yoqilgan. |
| 15 | Secret leakage | `config.local.php`, `.env` va `github_keys.txt` gitignored; `.htaccess` ularni, source papkalarni, `.sql`/`.log`/`.md` va nested hidden fayllarni HTTP’dan to‘sadi; bot token, webhook secret, IMAP paroli va app secret hech qachon frontendga chiqmaydi. |
| 16 | Email header injection | Email, From, Reply-To va subject qiymatlari `\r`, `\n`, `\0` dan tozalanadi; recipient `FILTER_VALIDATE_EMAIL` bilan tekshiriladi; legacy email HTML va CSV formula injection’dan himoyalangan. |
| 17 | Open redirect / XSS | Telegram callback faqat qat’iy `/admin/verify/` yoki `/admin/?error=...` manziliga yo‘naltiradi; legacy form redirect faqat `http(s)` yoki relative path qabul qiladi. |
| 18 | Error / stack trace leakage | API faqat `{status,message,code}` qaytaradi; batafsil xato `error_log` ga yoziladi. |
| 19 | Audit yo‘qligi | `admin_auth_events` va `admin_audit_log`: login, logout, profile/project o‘zgarishlari, IP hash. |
| 20 | Eski sessiya / challenge to‘planishi | Muddati o‘tgan session/challenge va 7 kundan eski auth eventlar opportunistik tozalanadi. |

Qo‘shimcha: PHP 7.1 sintaksisi saqlanadi, `display_errors` productionda o‘chirilgan bo‘lishi kerak, MySQL foydalanuvchisiga faqat kerakli bazaga ruxsat beriladi, `database/` va `backend/` HTTP’dan yopiladi.

## Telegram bot bridge

- Faqat `POST /api.php/bot/webhook`; GET `405`, secret yo‘q/noto‘g‘ri `403`.
- Telegram `secret_token` bilan `X-Telegram-Bot-Api-Secret-Token` solishtiriladi (`hash_equals`); secret kamida 32 belgi.
- Update’dagi `from.id` va `chat.id` `admin.telegram_id`ga teng bo‘lishi shart; boshqa foydalanuvchi e’tiborsiz qoldiriladi.
- `/inbox` va `/read` faqat `admin@otaboyev-sardorbek.uz` IMAP qutisini o‘qiydi; `/send` `no-reply@` orqali cPanel `mail()` bilan yuboradi.
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
5. **MySQL**: yangi baza uchun `schema.sql` va `seed.sql` import qiling; mavjud bazada avval `database/migrate-project-sections.sql` ni qo‘llang. `seed.sql` mavjud admin tahrirlarini bosib ketishini unutmang. Importdan keyin DB user’ga faqat `SELECT, INSERT, UPDATE, DELETE` bering; migratsiya uchun `CREATE`/`ALTER`/`DROP` ni vaqtincha qo‘shing.
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
- [x] `out/` ichida `config.local.php` bo‘lsa ham u HTTP’dan olinmaydi.
- [x] Telegram bot webhook secret + numeric admin ID allowlist; `pending_update_count=0`.
- [x] Bot IMAP `panel2.eskiz.uz:993/imap/ssl`; cPanel `mail()` orqali `/send` yetkazilgan.
- [ ] Haqiqiy Telegram admin xabari bilan inbound webhook testi (fake update bilan sinalgan).

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
