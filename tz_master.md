### 🏗️ 1. Tavsiya etilgan arxitektura

Sizning g'oyangiz to'g'ri – har bir texnologiya va vazifa uchun alohida subdomen ajratish eng toza yondashuvdir. Markaziy boshqaruv paneli esa asosiy domenda (`otaboyev-sardorbek.uz`) joylashadi va barcha tizimlarni boshqaradi.

| Subdomen | Vazifasi | Texnologiya |
|---|---|---|
| **`otaboyev-sardorbek.uz`** | Markaziy boshqaruv paneli (Admin Dashboard) | PHP yoki Node.js (React) |
| **`webapp.otaboyev-sardorbek.uz`** | Asosiy web ilova (foydalanuvchilar interfeysi) | React / React Native (Web) |
| **`api.otaboyev-sardorbek.uz`** | REST/GraphQL API (ma'lumotlar almashinuvi) | FastAPI / Django / Flask / Express |
| **`bot.otaboyev-sardorbek.uz`** | Telegram/Discord botlar (webhook rejimida) | Python (Flask) / Node.js (Express) / PHP |
| **`python.otaboyev-sardorbek.uz`** | Sizning shaxsiy Python tajriba loyihalaringiz | Django / Flask |

> **Muhim:** `apis.domen.uz` o'rniga `api.domen.uz` ishlatish odatiy holdir, lekin bu sizning tanlovingiz.

### ⚙️ 2. Har bir texnologiya uchun sozlash qadamlari

#### 🐘 PHP loyihalar uchun (Markaziy panel)
PHP uchun alohida "Setup" kerak emas. cPanel'da subdomen yaratib, uning **Document Root**'ini PHP loyihangiz papkasiga yo'naltirsangiz kifoya.

1.  cPanel → **Domains** → **Create A New Domain**.
2.  Subdomen nomini kiriting (masalan, `panel`).
3.  **Document Root** maydonida PHP loyihangizning `public` papkasini ko'rsating (masalan, `panel.otaboyev-sardorbek.uz` uchun `/home/host9590/panel/public`).
4.  `.htaccess` fayli orqali barcha so'rovlarni `index.php` ga yo'naltiring.

#### 🐍 Python loyihalar uchun (API, Bot)
Bu yerda cPanel'ning **"Setup Python App"** moduli ishlatiladi, u avtomatik virtual muhit yaratadi va ilovani **Passenger** orqali ishga tushiradi.

1.  cPanel → **Software** → **Setup Python App** → **Create Application**.
2.  **Python version:** 3.11 yoki 3.12 ni tanlang.
3.  **Application root:** Loyiha papkangiz (masalan, `api_app`). Bu papka `public_html` dan tashqarida bo'lishi kerak.
4.  **Application URL:** `api.otaboyev-sardorbek.uz` (subdomenni oldindan yaratishingiz shart emas, bu yerda tanlanadi).
5.  **Application startup file:** `passenger_wsgi.py`.
6.  **Application Entry point:** `application`.
7.  **Create** tugmasini bosing. cPanel virtual muhitni yaratadi.
8.  Loyiha fayllarini yuklang va `requirements.txt` asosida **"Run Pip Install"** tugmasini bosing.

**`passenger_wsgi.py` fayli namunasi (Flask uchun):**
```python
import sys, os
sys.path.insert(0, os.path.dirname(__file__))
from app import app as application
```
**Flask ilovangizda** (masalan, `app.py`) `application` o'zgaruvchisini eksport qilishingiz kerak:
```python
from flask import Flask
app = Flask(__name__)
application = app  # Passenger uchun muhim
```

**Webhook bot uchun muhim eslatma:** Botni Flask kabi web-freymvorkda yozib, Telegram'dan keladigan so'rovlarni qabul qiluvchi endpoint yaratasiz. `setWebhook` orqali `https://bot.otaboyev-sardorbek.uz/bot` manzilini ko'rsatasiz. **Muhim:** Kodda port ko'rsatmang, Passenger buni o'zi boshqaradi.

#### 🟢 Node.js loyihalar uchun (Web App, API)
Bu ham xuddi Python kabi **"Setup Node.js App"** orqali amalga oshiriladi.

1.  cPanel → **Software** → **Setup Node.js App** → **Create Application**.
2.  **Node.js version:** mavjud eng so'nggi versiyani tanlang (masalan, 20 yoki 22).
3.  **Application mode:** `Production`.
4.  **Application root:** Loyiha papkangiz (masalan, `webapp_node`).
5.  **Application URL:** `webapp.otaboyev-sardorbek.uz`.
6.  **Application startup file:** `app.js` yoki `server.js`.
7.  **Create** tugmasini bosing.

**Express.js uchun muhim:** Ilovangiz `app.listen(3000)` kabi qattiq kodlangan portdan **foydalanmasligi** kerak. Buning o'rniga Passenger taqdim etgan portni tinglashi kerak:
```javascript
const port = process.env.PORT || 3000;
app.listen(port, () => { ... });
```
Fayllarni yuklagach, **"Run NPM Install"** tugmasini bosib, bog'liqliklarni o'rnating.

### 🎛️ 3. Markaziy boshqaruv paneli g'oyasi

Asosiy domeningiz (`otaboyev-sardorbek.uz`) ostidagi panel orqali barcha tizimlarni boshqarish uchun **cPanel UAPI** (Universal API) dan foydalanishingiz mumkin. Bu sizga dasturiy ravishda subdomenlar yaratish, ma'lumotlar bazalarini boshqarish va boshqa vazifalarni bajarish imkonini beradi.

**Misol: PHP orqali yangi subdomen yaratish**
```php
<?php
$cpanel_host = 'panel2.eskiz.uz';
$cpanel_user = 'host9590';
$api_token = 'SIZNING_API_TOKENINGIZ'; // cPanel → Security → Manage API Tokens orqali oling

$subdomain = 'yangi-loyiha';
$root_domain = 'otaboyev-sardorbek.uz';
$dir = '/home/host9590/yangi-loyiha/public';

$url = "https://{$cpanel_host}:2083/execute/SubDomain/addsubdomain?domain={$subdomain}&rootdomain={$root_domain}&dir={$dir}";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: cpanel {$cpanel_user}:{$api_token}"]);
$response = curl_exec($ch);
curl_close($ch);

echo $response;
?>
```
Bu kod orqali panelni ishlab chiqsangiz, foydalanuvchilaringiz panel orqali o'zlariga yangi subdomen va loyiha yaratishi mumkin bo'ladi.

### ⚠️ 4. Shared hostingning cheklovlari va muhim eslatmalar

Bu muhitda ba'zi narsalarni hisobga olish kerak:

*   **Doimiy jarayonlar (Polling botlar) ishlamaydi:** `while True` tsikli bilan ishlaydigan botlar Passenger tomonidan to'xtatiladi. Shared hosting uzoq muddatli jarayonlar uchun mo'ljallanmagan. **Yechim:** Botlaringizni **faqat webhook rejimida** yozing.
*   **Fon vazifalari uchun Cron Jobs:** Agar ma'lum vaqtlarda bajariladigan vazifalar bo'lsa (masalan, har soatda ma'lumotlarni yangilash), cPanel'ning **Cron Jobs** bo'limidan foydalanishingiz mumkin.
*   **Resurs limitlari:** Har bir hisobning CPU va RAM limitlari bor. Agar ilovangiz ushbu limitlardan oshsa, vaqtincha ishlamay qolishi mumkin.
*   **SSL sertifikatlari:** cPanel'ning **AutoSSL** xususiyati har bir subdomen uchun avtomatik ravishda bepul Let's Encrypt sertifikatini o'rnatadi. **SSL/TLS Status** bo'limida buni tekshirib turing.
*   **Zaxira nusxalash:** cPanel'da **JetBackup 5** mavjud. Muntazam avtomatik zaxira nusxalashni yoqing.
