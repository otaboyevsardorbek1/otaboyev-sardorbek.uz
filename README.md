# Sardorbek Otaboyev Portfolio

Bu loyiha Sardorbek Otaboyev uchun yaratilgan ko‘p tilli portfolio veb-saytidir. U statik frontend, mahalliy tillangan sahifalar va PHP API qatlamidan iborat bo‘lib, form yuborish, token bilan himoyalangan endpointlar va sayt metadata ishlashini ta’minlaydi.

Loyiha tuzilishi:
- `index.html` — asosiy root sahifa, tilni avtomatik aniqlash
- `uz/`, `ru/`, `en/` — har bir til uchun bosh sahifa
- `uz/projects/*`, `ru/projects/*`, `en/projects/*` — loyiha tafsilotlari sahifalari
- `css/portfolio.css` — umumiy premium dizayn uslubi
- `js/portfolio.js` — interaktivlar, GitHub ma’lumotlari, form handler, mobil navigatsiya
- `api.php` — PHP API, form yuborish va himoyalangan endpointlar
- `.htaccess` — Apache routing, cash va xavfsizlik qoidalari

## Xususiyatlar
- Ko‘p tilli portfolio sahifalari
- Loyiha detail sahifalari
- GitHub profil ma’lumotlarini yuklash
- Contact form va fallback email ishlashi
- Responsive premium dark glassmorphism dizayn
- Lokalizatsiya va sahifalar orasidagi navigatsiya
- SEO va static sahifa qulayligi

## Qo‘llab quvvatlanayotgan tillar
- O‘zbekcha: `/uz/`
- Inglizcha: `/en/`
- Ruscha: `/ru/`

## Mahalliy ishlash

### Variant 1: PHP built-in server
Proyekt rootida quyidagilarni bajarish kerak:

```bash
php -S 127.0.0.1:8000 -t .
```

Keyin quyidagi manzillarga kiring:
- http://127.0.0.1:8000/
- http://127.0.0.1:8000/en/
- http://127.0.0.1:8000/uz/
- http://127.0.0.1:8000/ru/

### Variant 2: Python HTTP server
```bash
python3 -m http.server 8000
```

Bu tezkor preview uchun qulay, lekin `api.php` bilan ishlash yaxshi natija berishi uchun PHP built-in server afzalroqdir.

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

    location / {
        try_files $uri $uri/ /index.html;
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

Sinovdan so‘ng:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

## Serverga deploy qilish

Repozni web rootga klonlash:

```bash
sudo mkdir -p /var/www/otaboyev-sardorbek.uz
cd /var/www/otaboyev-sardorbek.uz
sudo git clone https://github.com/otaboyevsardorbek1/otaboyev-sardorbek.uz.git .
```

Agar repo mavjud bo‘lsa:

```bash
git pull origin main
```

Ruxsat berish:

```bash
sudo chown -R www-data:www-data /var/www/otaboyev-sardorbek.uz
sudo find /var/www/otaboyev-sardorbek.uz -type d -exec chmod 755 {} \;
sudo find /var/www/otaboyev-sardorbek.uz -type f -exec chmod 644 {} \;
```

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

Agar hech biri belgilanmagan bo‘lsa, lokal fallback:

```bash
otaboyev-dev-token
```

Productionda bu fallback ishlatilmasligi kerak, haqiqiy secret ishlatilishi lozim.

## .htaccess funksiyasi

Loyihada root `.htaccess` fayli mavjud bo‘lib, quyidagilarni boshqaradi:
- hidden fayllarga kirishni cheklash
- locale redirect`lar`
- project detail page routing
- cache headerlar
- HTML fallback
- xavfsizlik headerlari

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

Shuningdek, `.htaccess` yoqilgan va Apache `mod_rewrite` faol bo‘lishi kerak.

### CSS/JS yuklanmay qolsa
Asset yo‘llari sahifa chuqurligiga mos bo‘lishi kerak. Loyiha umumiy assetlar:
- `/css/portfolio.css`
- `/js/portfolio.js`

### API invalid token qaytarsa
To‘g‘ri headerdan foydalaning:

```bash
curl -H "X-Api-Token: YOUR_TOKEN" http://127.0.0.1:8000/api.php/websites
```

Agar token yo‘q bo‘lsa yoki noto‘g‘ri bo‘lsa, API 401 JSON javob qaytaradi.

## Loyiha egaligi

Bu loyiha Sardorbek Otaboyevga tegishli shaxsiy portfolio sayti bo‘lib, uning professional veb-ko‘rinishi, dizayn portfoliosi va ma’lumotlar saqlash uchun ishlab chiqilgan.

## Texnik muallif

Sardorbek Otaboyev

## Bog‘lanish

- Email: contact@otaboyev-sardorbek.uz
- GitHub: https://github.com/otaboyevsardorbek1
- Telegram: https://t.me/otaboyevsardorbek

## Xulosa

Bu loyiha zamonaviy, ko‘p tilli, statik-first portfolio sayt bo‘lib, premium dizayn, loyiha detail sahifalari, GitHub integratsiyasi va PHP backend bilan jihozlangan. Lokal preview, GitHub deploy, Apache/Nginx hosting va xavfsiz API token validation uchun tayyor holatda ishlab chiqilgan.
