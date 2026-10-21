# Sardorbek Otaboyev Portfolio

Bu loyiha Sardorbek Otaboyev uchun yaratilgan ko‘p tilli portfolio veb-saytidir. Frontend Next.js (App Router) bilan yig‘ilib, statik `out/` ko‘rinishida deploy qilinadi; PHP `api.php` form yuborish, tokenli endpointlar va MySQL’ga asoslangan content API’ni ta’minlaydi.

Loyiha tuzilishi:
- `app/` — Next.js App Router: `/` til redirect, `/{uz,en,ru}` bosh sahifalari, loyihalar ro‘yxati va 9 project detail sahifa
- `components/Home.jsx`, `components/ProjectsPage.jsx` — React bosh sahifa va ro‘yxat interaktivligi
- `components/ProjectDetail.jsx` — detail sahifa: statik fallback + content API’dan yangilash
- `content/locales.js` — uz/en/ru matnlari, project detail fallback va SEO label’lari
- `css/portfolio.css` — umumiy dizayn; `js/portfolio.js` — faqat legacy sahifalar uchun JS
- `{uz,ru,en}/projects/*` — tarixiy legacy detail nusxalari; `out/` ga ko‘chirilmaydi, sayt React route’larini ishlatadi
- `backend/` — DB config, content API (`profile`, `projects`), contact inbox (`contact.php`), admin auth (`admin.php`) va email (`mailer.php`)
- `app/admin/`, `components/admin/`, `css/admin.css` — Telegram + email OTP bilan himoyalangan admin panel; inbox, loyihalar, adminlar
- `database/` — MySQL `schema.sql`, `seed.sql` va migratsiyalar
- `api.php` — PHP API: native contact form, tokenli endpoint, content route’lar, Sitejet proxy
- `scripts/copy-static.mjs` — `next build`dan keyin `out/` ni to‘ldiradi
- `.htaccess` — Apache routing, cache va xavfsizlik qoidalari

## Xususiyatlar
- Ko‘p tilli portfolio sahifalari
- Loyiha detail sahifalari
- GitHub profil ma’lumotlarini yuklash
- Contact form, MySQL inbox, honeypot/rate limit va fallback email ishlashi
- Telegram Login Widget + email OTP asosidagi 2 bosqichli admin panel
- Profil va loyihalarni uch tilda tahrirlash (CRUD), audit log va CSRF himoyasi
- Inbox (read/unread/delete), adminlar boshqaruvi va avtomatik `sitemap.xml`
- Haqiqiy 404 sahifa va Apache/Nginx routing
- Responsive premium dark glassmorphism dizayn
- Lokalizatsiya va sahifalar orasidagi navigatsiya
- SEO va static sahifa qulayligi

## Qo‘llab quvvatlanayotgan tillar
- O‘zbekcha: `/uz/`
- Inglizcha: `/en/`
- Ruscha: `/ru/`

## Mahalliy ishlash

### 1. Frontend build
```bash
npm ci
npm run build
```
Natija `out/` papkasida: 3 bosh sahifa, 3 loyihalar ro‘yxati, 9 project detail sahifa, 3 admin sahifa, CSS/JS, CV fayllar, `api.php`, `backend/` va mavjud bo‘lsa `config.local.php`. Project detail sahifalar Next.js tomonidan generatsiya qilinadi; admin yaratgan yangi slug Apache rewrite orqali `/projects/view/?slug=...` fallback’ida ochiladi.

### 2. MySQL (content API uchun)
cPanel yoki lokal MySQL’da baza yarating va sxemani import qiling:

```bash
mysql -u USER -p DB_NAME < database/schema.sql
mysql -u USER -p DB_NAME < database/seed.sql
# Eski (project sections qo‘shilishidan oldingi) baza uchun avval:
# mysql -u USER -p DB_NAME < database/migrate-project-sections.sql
# Contact inbox jadvali uchun (idempotent):
# mysql -u USER -p DB_NAME < database/migrate-contact-messages.sql
# seed.sql faqat standart kontentni tiklash uchun qayta import qilinadi;
# u mavjud admin tahrirlarini bosib ketadi.
```

Sozlamalarni `config.local.example.php` dan `config.local.php` ga ko‘chirib to‘ldiring (fayl gitignored), yoki `DB_*` environment variable’larini bering:

```bash
export DB_HOST=localhost
export DB_NAME=portfolio
export DB_USER=portfolio
export DB_PASS=secret
# ixtiyoriy: export DB_SOCKET=/run/mysqld/mysqld.sock
```

### 3. Preview
```bash
APP_ENV=local SITE_URL=http://127.0.0.1:8000 php -S 127.0.0.1:8000 -t out
```

Keyin quyidagi manzillarga kiring:
- http://127.0.0.1:8000/ (tilni avtomatik aniqlaydi)
- http://127.0.0.1:8000/uz/
- http://127.0.0.1:8000/en/
- http://127.0.0.1:8000/ru/
- http://127.0.0.1:8000/uz/projects/gitinsight/

`npm run dev` faqat frontend uchun qulay; unda PHP API yo‘q, shuning uchun bosh sahifa statik fallback bilan ishlaydi. API’ni sinash uchun yuqoridagi `php -S ... -t out` ishlatiladi.

Admin API’ni lokalda sinash uchun `APP_ENV=local`, `SITE_URL=http://127.0.0.1:8000`, `APP_SECRET` (64 hex), `TELEGRAM_BOT_TOKEN`, `TELEGRAM_BOT_USERNAME`, `ADMIN_TELEGRAM_ID`, `ADMIN_EMAIL` va `ADMIN_MAIL_DRIVER=log` ni ham bering. Telegram widget lokal HTTP’da ishlamaydi; callback’ni test ma’lumoti bilan `curl` orqali yoki production HTTPS’da sinang. Email kod lokalda `ADMIN_MAIL_LOG` fayliga yoziladi.

### API tekshiruvi
```bash
curl -s "http://127.0.0.1:8000/api.php/profile?lang=uz"
curl -s "http://127.0.0.1:8000/api.php/projects?lang=en"
curl -s "http://127.0.0.1:8000/api.php/projects/gitinsight?lang=ru"
```

## Admin panel (Telegram + email OTP)

Admin panel `/admin/` da. Kirish ikki bosqichli:

1. Telegram Login Widget admin Telegram akkauntini tasdiqlaydi.
2. Admin emailiga 6 xonali kod yuboriladi; kod 10 daqiqa amal qiladi va 5 urinishdan keyin bekor qilinadi.

Panelda profil (ism, email, GitHub, Telegram, avatar va uch tildagi role/bio/quick_text/location), loyihalar (slug, kategoriya, havolalar, tartib, faollik va uch tildagi tag/title/summary/description/tech/results/stack_notes), kelgan xabarlar va qo‘shimcha adminlar boshqariladi. `results` va `stack_notes` har bir qatorda bitta element sifatida kiritiladi. O‘zgarishlar `profiles`, `profile_translations`, `projects`, `project_translations`, `contact_messages` va `admin_users` jadvallariga yoziladi; loyiha o‘zgarishi `sitemap.xml` ni avtomatik yangilaydi va detail sahifada API fetch’dan keyin ko‘rinadi.

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

### 3. Config

`config.local.example.php` dan `config.local.php` yarating va to‘ldiring:

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
        'telegram_id' => '123456789',
        'email' => 'admin@otaboyev-sardorbek.uz',
    ),
    'mail' => array(
        'driver' => 'mail',
        'from' => 'no-reply@otaboyev-sardorbek.uz',
        'from_name' => 'Sardorbek Otaboyev',
    ),
);
```
`config.local.php` mavjud bo‘lsa, `npm run build` uni `out/config.local.php` ga ham ko‘chiradi (fayl `chmod 600` bo‘lsin).

Secret yaratish:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

`APP_SECRET` aniq 64 hex belgidan iborat bo‘lmasa, email driver to‘liq sozlanmasa yoki productionda `SITE_URL` HTTPS bo‘lmasa admin auth o‘chirilgan holatda qoladi. Cloudflare yoki boshqa reverse proxy HTTPS’ni tugatsa, `ADMIN_TRUST_PROXY=1` qo‘ying; aks holda session cookie `Secure` belgisisiz qolishi mumkin. `config.local.php` gitignored va `.htaccess` bilan HTTP’dan yopilgan.

### 4. Sxema va login

```bash
mysql -u USER -p DB_NAME < database/schema.sql
mysql -u USER -p DB_NAME < database/seed.sql
# Eski (project sections qo‘shilishidan oldingi) baza uchun avval:
# mysql -u USER -p DB_NAME < database/migrate-project-sections.sql
# Contact inbox jadvali uchun (idempotent):
# mysql -u USER -p DB_NAME < database/migrate-contact-messages.sql
# seed.sql faqat standart kontentni tiklash uchun qayta import qilinadi;
# u mavjud admin tahrirlarini bosib ketadi.
```

So‘ng `/admin/` ni oching. Birinchi muvaffaqiyatli Telegram callback `ADMIN_TELEGRAM_ID` asosida `admin_users` yozuvini yaratadi. Keyingi adminlarni `/admin/dashboard/` → **Adminlar** bo‘limidan qo‘shish/tahrirlash/o‘chirish mumkin; Telegram ID va OTP email to‘g‘ri bo‘lsa ular ham kira oladi. O‘z hisobingizni o‘chirish/deaktiv qilish va oxirgi faol adminni olib tashlash bloklangan.

### Admin API

| Endpoint | Maqsad |
|---|---|
| `GET /api.php/admin/config` | public: `enabled`, bot username, auth URL |
| `GET /api.php/admin/auth/telegram` | Telegram widget callback (302) |
| `GET /api.php/admin/auth/status` | challenge email maskasi va TTL |
| `POST /api.php/admin/auth/verify` | `{code}` tekshirish |
| `POST /api.php/admin/auth/resend` | yangi kod yuborish |
| `GET /api.php/admin/session` | session + CSRF token |
| `POST /api.php/admin/logout` | sessionni yopish |
| `GET/PUT /api.php/admin/profile` | profil o‘qish/saqlash |
| `GET/POST /api.php/admin/projects` | ro‘yxat / yangi loyiha; saqlashda sitemap avtomatik yangilanadi |
| `GET/PUT/DELETE /api.php/admin/projects/{id}` | bitta loyiha |
| `GET /api.php/admin/messages[?count=1\|unread=1]` | inbox count/ro‘yxat |
| `GET/PUT/DELETE /api.php/admin/messages/{id}` | xabar / read-unread / o‘chirish |
| `POST /api.php/admin/sitemap` | `sitemap.xml` ni qayta generatsiya qilish |
| `GET/POST /api.php/admin/admins` | adminlar ro‘yxati / yangi admin |
| `GET/PUT/DELETE /api.php/admin/admins/{id}` | adminni o‘qish/tahrirlash/o‘chirish |

`POST`, `PUT`, `DELETE` so‘rovlarida `X-CSRF-Token` header majburiy. Admin endpointlar CORS header qaytarmaydi; ular faqat same-origin uchun. Sitemap fayli web rootda PHP yoza oladigan bo‘lishi kerak; aks holda `500 sitemap_write_failed` qaytadi.

### Xavfsizlik

To‘liq nazorat ro‘yxati: [SECURITY.md](SECURITY.md). Qisqasi: Telegram HMAC, `auth_date` oynasi, hashed OTP, HttpOnly/Secure/SameSite cookie, CSRF, rate limit, audit log, PDO prepared statement, `.htaccess` CSP/HSTS, `config.local.php` himoyasi.

## Production server sozlamasi

### Apache
Vhost faylini yaratish:

```apache
<VirtualHost *:80>
    ServerName otaboyev-sardorbek.uz
    ServerAlias www.otaboyev-sardorbek.uz
    DocumentRoot /var/www/otaboyev-sardorbek.uz

    <Directory /var/www/otaboyev-sardorbek.uz>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/otaboyev-error.log
    CustomLog ${APACHE_LOG_DIR}/otaboyev-access.log combined
</VirtualHost>
```

Keyin:

```bash
sudo a2enmod rewrite headers
sudo a2ensite otaboyev-sardorbek-uz.conf
sudo systemctl restart apache2
```

HTTPS uchun:

```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d otaboyev-sardorbek.uz -d www.otaboyev-sardorbek.uz
```

### Nginx
Misol konfiguratsiya:

```nginx
server {
    listen 80;
    server_name otaboyev-sardorbek.uz www.otaboyev-sardorbek.uz;
    root /var/www/otaboyev-sardorbek.uz;
    index index.html index.php;

    error_page 404 /404.html;

    location / {
        try_files $uri $uri/ =404;
    }

    # Admin yaratgan, statik papkasi yo‘q project slug’lari
    location ~ ^/(uz|en|ru)/projects/([a-z0-9-]+)/$ {
        try_files $uri $uri/ @project_view;
    }

    location @project_view {
        rewrite ^/(uz|en|ru)/projects/([a-z0-9-]+)/$ /$1/projects/view/?slug=$2 last;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~* \.(css|js|png|jpg|jpeg|gif|svg|webp|ico|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    location ~* \.html$ {
        add_header Cache-Control "no-cache, no-store, must-revalidate";
    }
}
```

**Muhim:** Nginx `.htaccess` ni o‘qimaydi. Ma’lum detail URL’lari `try_files $uri $uri/ =404;` bilan ishlaydi; admin yaratgan yangi slug uchun `/{locale}/projects/view/?slug=...` ga equivalent rewrite qo‘shilishi kerak.

Sinovdan so‘ng:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

## Serverga deploy qilish

### Build va fayllarni ko‘chirish
Repo’ni serverda yoki lokalda yig‘ing:

```bash
npm ci
npm run build
```

`out/` tarkibini web rootga ko‘chiring (`rsync`, cPanel File Manager yoki CI orqali). Web rootda `config.local.php` (yoki `DB_*` env) va MySQL baza bo‘lishi kerak. `database/` papkasini web rootga ochmang — u faqat migratsiya uchun.

### cPanel deploy

1. cPanel → **MySQL Databases**: baza va foydalanuvchi yarating, foydalanuvchiga bazaga ruxsat bering.
2. phpMyAdmin → **Import**: yangi baza uchun `database/schema.sql`, keyin `database/seed.sql`; eski bazada avval `database/migrate-project-sections.sql`, keyin `database/migrate-contact-messages.sql` ni import qiling. `seed.sql` mavjud admin tahrirlarini bosib ketadi.
3. `config.local.example.php` dan `config.local.php` yarating; DB, Telegram, email va `app_secret` ni to‘ldiring.
4. Lokalda yoki Node mavjud muhitda `npm ci && npm run build`.
5. `out/` ichidagi barcha fayllarni cPanel **File Manager** orqali `public_html/` ga yuklang. `out/` papkasining o‘zini emas, ichidagi `api.php`, `backend/`, `.htaccess`, `index.html`, `admin/`, `uz/`, `en/`, `ru/`, `_next/`, `files/` ni yuklang.
6. `config.local.php` ni web rootga qo‘ying va ruxsatini `600` qiling. Web rootdagi `sitemap.xml` PHP jarayoni yoza olishi kerak (fayl egasi web user bo‘lsin; papka yozish huquqini saqlang), aks holda admin panelida sitemap yangilash `500` qaytaradi.
7. cPanel → **SSL/TLS Status**: AutoSSL faol bo‘lsin; HTTPS majburiy bo‘lsin.
8. `https://otaboyev-sardorbek.uz/admin/` ni ochib, Telegram va email kod bilan tekshiring.

Agar cPanel’da Node bo‘lmasa, buildni lokalda bajaring va faqat `out/` ni yuklang. `database/` va `src` fayllarni web rootga ochmang.

**Muhim:** cPanel PHP versiyasi 7.1+ bo‘lsin; `pdo_mysql`, `curl`, `openssl` kengaytmalari yoqilgan bo‘lsin. `.htaccess` uchun `mod_rewrite` va `mod_headers` kerak.

### Repo klonlash (ixtiyoriy)
```bash
sudo mkdir -p /var/www/otaboyev-sardorbek.uz
cd /var/www/otaboyev-sardorbek.uz
sudo git clone https://github.com/otaboyevsardorbek1/otaboyev-sardorbek.uz.git .
```

Agar repo mavjud bo‘lsa:

```bash
git pull origin main
npm ci && npm run build
```

Deploy qilishda web rootga repo ildizini emas, `out/` tarkibini ko‘chiring.

Ruxsat berish:

```bash
sudo chown -R www-data:www-data /var/www/otaboyev-sardorbek.uz
sudo find /var/www/otaboyev-sardorbek.uz -type d -exec chmod 755 {} \;
sudo find /var/www/otaboyev-sardorbek.uz -type f -exec chmod 644 {} \;
```

## Content API (MySQL)

`api.php` quyidagi public GET endpointlarni taqdim etadi:

| Endpoint | Maqsad |
|---|---|
| `/api.php/profile?lang=uz` | profil ma’lumotlari: name, role, bio, quick_text, location, email, GitHub, Telegram |
| `/api.php/projects?lang=uz` | loyiha kartalari: slug, category, tag, title, summary, tech, links |
| `/api.php/projects/{slug}?lang=uz` | loyiha tafsiloti: `description`, `results[]`, `stack_notes[]` |
| `POST /api.php/form_container/submit` | `id=contact` native contact form; boshqa ID’lar Sitejet contracti |
| `/api.php/health` | `{"status":"ok"}` |
| `/api.php/websites` | tokenli endpoint |

Contact form `id=contact` bilan `form_contact[ed-f-1..4]` (ism, email, mavzu, xabar), `lang` va bo‘sh `website` honeypot yuboradi. Xabar `contact_messages` jadvaliga yoziladi; IP bo‘yicha 10 daqiqada 5 tadan ko‘p yuborilsa `429`, DB yo‘q bo‘lsa `503` qaytadi (frontend `mailto` fallback’ga o‘tadi). Admin panelda **Xabarlar** bo‘limidan o‘qish, read/unread va o‘chirish mumkin.

Javob formati:

```json
{ "status": "ok", "data": ... }
{ "status": "error", "message": "...", "code": "db_unavailable" }
```

Muhim holatlar:
- noto‘g‘ri `lang` → `uz` ga fallback
- topilmagan slug → `404 project_not_found`
- DB sozlanmagan yoki ulanmagan → `503 db_unavailable` (frontend statik fallback’ga qaytadi)

## API token sozlamalari

PHP backend `/api.php/websites` endpointiga valid token talab qiladi.

Qabul qilinadigan header:

```bash
X-Api-Token: YOUR_TOKEN
```

Productionda quyidagi environment variable laridan birini sozlash kerak:

```bash
export API_TOKEN="YOUR_TOKEN"
# yoki
export SITEHUB_API_TOKEN="YOUR_TOKEN"
# yoki
export OTABOYEV_API_TOKEN="YOUR_TOKEN"
```

Agar hech biri belgilanmagan bo‘lsa, fallback faqat `APP_ENV=local`, `development` yoki `test` bo‘lganda ishlaydi:

```bash
export APP_ENV=local
export API_TOKEN="otaboyev-dev-token"
```

`APP_ENV=production` (yoki `APP_ENV` umuman yo‘q) bo‘lsa, public dev token qabul qilinmaydi va token sozlanmagan endpoint `401` qaytaradi. Productionda haqiqiy secret ishlatilishi shart.

## .htaccess funksiyasi

Loyihada root `.htaccess` fayli mavjud bo‘lib, quyidagilarni boshqaradi:
- HTTP’ni canonical HTTPS hostga 301 qilish (lokal `localhost`/`127.0.0.1` bundan mustasno)
- hidden fayllar, `config.local.php`, `github_keys.txt`, `backend/`, `database/`, source papkalar, `.sql`/`.log`/`.md` va nested hidden fayllarga HTTP kirishni cheklash
- locale redirect`lar`
- project detail/listing papkalari uchun `DirectoryIndex`; statik sahifasi yo‘q admin slug’lari uchun `/projects/view/?slug=...` fallback rewrite’i (per-slug statik rewrite qo‘shilmaydi, loop bo‘lmaydi)
- cache headerlar
- `ErrorDocument 404 /404.html` orqali haqiqiy 404
- xavfsizlik headerlari (CSP, HSTS, nosniff, frame-ancestors)
- `/api.php/...` PATH_INFO route’larini himoyalash

Bu Apache hosting uchun mo‘ljallangan.

## Git workflow

Repo yangilanishi:

```bash
git status
git add .
git commit -m "Deploy update"
git push origin main
```

## SSH deployment haqida

Production serverga kirish uchun SSH sozlamalari kerak bo‘ladi. Agar SSH mavjud bo‘lmasa yoki key noto‘g‘ri bo‘lsa, serverni masofadan yangilash mumkin bo‘lmaydi. Odatdagi jarayon:

```bash
ssh-keygen -t ed25519 -C "otaboyev"
cat ~/.ssh/id_ed25519.pub
```

Key public qismini serverdagi `~/.ssh/authorized_keys` fayliga qo‘shing.

## Muammolarni bartaraf qilish

### 404 project sahifalarida
Route to‘g‘ri ekanligiga ishonch hosil qiling:
- `/uz/projects/gitinsight/`
- `/ru/projects/gitinsight/`
- `/en/projects/gitinsight/`

Shuningdek, `.htaccess` yoqilgan va Apache `mod_rewrite` faol bo‘lishi kerak. Project detail papkasida `index.html` borligini tekshiring; per-slug rewrite qo‘shmang, aks holda Apache internal redirect loop beradi.

### Contact form 503/429 qaytarsa
- `503`: DB yoki `contact_messages` jadvali yo‘q. `config.local.php` ni va `database/migrate-contact-messages.sql` importini tekshiring; frontend bu holatda `mailto` fallback’ga o‘tadi.
- `429`: bitta IP’dan 10 daqiqada 5 tadan ko‘p xabar yuborilgan; birozdan keyin qayta urinib ko‘ring.
- Xabar DB’ga tushsa, `/admin/dashboard/` → **Xabarlar** bo‘limida ko‘rinadi. Honeypot (`website`) to‘ldirilgan bo‘lsa so‘rov rad etiladi.

### CSS/JS yuklanmay qolsa
Asset yo‘llari sahifa chuqurligiga mos bo‘lishi kerak. Loyiha umumiy assetlar:
- `/css/portfolio.css`
- `/js/portfolio.js`

### Admin panelga kirib bo‘lmasa
- `/api.php/admin/config` da `enabled` `true` ekanini tekshiring.
- `APP_SECRET` aynan 64 hex belgi bo‘lsin; `TELEGRAM_BOT_USERNAME` `@` siz bo‘lsin.
- BotFather’da `/setdomain otaboyev-sardorbek.uz` bajarilgan va sayt HTTPS’da ochilgan bo‘lsin.
- `ADMIN_TELEGRAM_ID` `@userinfobot` dagi ID bilan bir xil bo‘lsin.
- Email kelmasa: cPanel mailbox, spam papkasi, SPF/DKIM/DMARC va `ADMIN_MAIL_FROM` domenini tekshiring. Lokalda `ADMIN_MAIL_DRIVER=log` va `ADMIN_MAIL_LOG` ishlatiladi; productionda `log` ishlamaydi.
- `db_unavailable` bo‘lsa `config.local.php` va MySQL importini tekshiring.

### Admin API 401/403 qaytarsa
- 401: session cookie yo‘q yoki muddati tugagan; `/admin/` dan qayta kiring.
- 403 `csrf_failed`: sahifani yangilang; `X-CSRF-Token` session endpointidan olinadi.
- 403 `origin_rejected`: so‘rov boshqa domendan yuborilgan; `SITE_URL` ni tekshiring.

### API invalid token qaytarsa
To‘g‘ri headerdan foydalaning:

```bash
curl -H "X-Api-Token: YOUR_TOKEN" http://127.0.0.1:8000/api.php/websites
```

Agar token yo‘q bo‘lsa yoki noto‘g‘ri bo‘lsa, API 401 JSON javob qaytaradi.

### Content API 503 qaytarsa
`config.local.php` yoki `DB_*` env to‘ldirilganini, MySQL bazasi va jadvallar mavjudligini tekshiring. Sxema/seed:

```bash
mysql -u USER -p DB_NAME < database/schema.sql
mysql -u USER -p DB_NAME < database/seed.sql
# Eski (project sections qo‘shilishidan oldingi) baza uchun avval:
# mysql -u USER -p DB_NAME < database/migrate-project-sections.sql
# Contact inbox jadvali uchun (idempotent):
# mysql -u USER -p DB_NAME < database/migrate-contact-messages.sql
# seed.sql faqat standart kontentni tiklash uchun qayta import qilinadi;
# u mavjud admin tahrirlarini bosib ketadi.
```

### Bosh sahifa API’siz ishlayaptimi?
Ha. `components/Home.jsx` API javobini olmasa `content/locales.js` dagi statik ma’lumotlardan foydalanadi. Bu build vaqtida ham, MySQL vaqtincha ishlamaganda ham sahifa buzilmasligini ta’minlaydi.

## Loyiha egaligi

Bu loyiha Sardorbek Otaboyevga tegishli shaxsiy portfolio sayti bo‘lib, uning professional veb-ko‘rinishi, dizayn portfoliosi va ma’lumotlar saqlash uchun ishlab chiqilgan.

## Texnik muallif

Sardorbek Otaboyev

## Bog‘lanish

- Email: contact@otaboyev-sardorbek.uz
- GitHub: https://github.com/otaboyevsardorbek1
- Telegram: https://t.me/otaboyevsardorbek

## Xulosa

Bu loyiha zamonaviy, ko‘p tilli portfolio sayti: Next.js statik frontend, React bosh sahifa, ro‘yxat va project detail sahifalar, MySQL content API va PHP backend. Telegram + email OTP bilan himoyalangan admin panel profil va loyihalarni uch tilda tahrirlaydi. Lokal preview, Apache/Nginx/cPanel hosting, xavfsiz API token validation va audit log uchun tayyor.
