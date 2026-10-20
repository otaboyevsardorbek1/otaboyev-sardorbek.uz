# AGENTS.md

Ushbu fayl loyihada ishlaydigan AI agentlar uchun qo'llanma. Har bir satr repo'da tekshirilgan — generic maslahat yo'q.

## Project overview

`otaboyev-sardorbek.uz` — Otaboyev Sardorbek (Backend Developer & AI Automation Specialist) ning shaxsiy portfolio sayti. **3 tilda** (uz / en / ru) xizmat ko'rsatadi. Statik frontend (`index.html` + `css/` + `js/`) va bitta PHP backend proxy (`api.php`) dan iborat. **Framework va build jarayoni yo'q** — vanilla HTML/CSS/JS.

Sayt ikki qismdan iborat:
1. **Asosiy portfolio** — `revamp.css` + `revamp.js` (qo'lda yozilgan, `.revamp-` prefiksli)
2. **Sitejet runtime** — `webcard/`, `custom.*.css`, `custom.*.js`, `api.php` (avto-generatsiya qilingan, **tegmaslik**)

## Setup

```bash
cd /home/host9590/public_html   # serverda
# yoki
cd ~/Desktop/otaboyev-sardorbek.uz   # lokalda

# Lokal test (PHP proxy + statik fayllar)
php -S localhost:8000
# → http://localhost:8000/uz/ , /en/ , /ru/
```

**Serverga kirish:** `ssh otaboyev` (SSH kalit passphrase so'raladi).

## Commands

| Vazifa | Buyruq |
|---|---|
| Lokal serverni ishga tushirish | `php -S localhost:8000` |
| Bitta tilni brauzerda ochish | `xdg-open http://localhost:8000/uz/` |
| Deploy (serverga) | `ssh otaboyev` → `cd /home/host9590/public_html && git pull` |
| Repo holati | `git status && git log --oneline -5` |
| Yangi faylni qo'shish | `git add <fayl> && git commit -m "<message>" && git push` |
| CV PDF qayta yaratish | `pandoc files/sardorbek-otaboyev-cv-<lang>.txt -o files/sardorbek-otaboyev-cv-<lang>.pdf -V geometry:margin=2cm --pdf-engine=xelatex -V mainfont="DejaVu Sans Mono"` |
| HTML validatsiya | `tidy -errors -q uz/index.html 2>&1 \| head -30` |
| CSS/JS hajmini tekshirish | `wc -c css/revamp.css js/revamp.js` |

## Verification order

Har qanday o'zgarishdan keyin **shu tartibda** tekshirish:

```bash
# 1. Sintaksis
php -l api.php                    # faqat PHP o'zgartirilgan bo'lsa
node --check js/revamp.js         # agar Node.js bor bo'lsa

# 2. Lokal serverda ochish
php -S localhost:8000
# Brauzer: /uz/, /en/, /ru/ — 3 til ham ishlashi kerak

# 3. Konsolda xato yo'qligini tekshirish (F12 → Console)
#    - 404 (yo'q fayl)
#    - CORS xatosi
#    - JS runtime xatosi

# 4. Git diff
git diff --stat
```

**Test yoki lint pipeline yo'q.** Har bir o'zgarish qo'lda brauzerda tekshiriladi.

## Architecture

```
otaboyev-sardorbek.uz/
├── index.html              # root redirect → /uz/ (387 bayt)
├── api.php                 # ⚠️ Sitejet backend proxy (TEGMASLIK)
├── .htaccess               # rewrite/redirect qoidalari (TEGMASLIK)
├── sitemap.xml             # 3 til uchun URL'lar
├── README.md               # loyiha qo'llanmasi
├── github_keys.txt         # ⚠️ maxfiy — commit qilinmasin
│
├── uz/ en/ ru/             # 3 til versiyalari
│   ├── index.html          # bosh sahifa (8 section)
│   └── projects/
│       ├── index.html      # loyihalar ro'yxati (filter bilan)
│       ├── gitinsight/     # alohida project sahifasi (SAQLANADI)
│       ├── service-platform/
│       └── telegram-ops/
│
├── css/
│   ├── custom.261016194537.css   # ⚠️ Sitejet (TEGMASLIK)
│   ├── portfolio.css             # eski custom (ixtiyoriy)
│   └── revamp.css                # ⭐ yangi premium dizayn
│
├── js/
│   ├── custom.261016193506.js    # ⚠️ Sitejet (TEGMASLIK)
│   ├── portfolio.js              # eski custom (ixtiyoriy)
│   └── revamp.js                 # ⭐ yangi interaktivlik
│
├── bundles/flag-icon-css/        # 600+ bayroq SVG (TEGMASLIK)
├── images/{0,1024,1920}/         # rasm variantlari (Sitejet)
├── g/fonts/{roboto,ubuntu}/      # mahalliy shriftlar (TEGMASLIK)
├── files/                        # CV fayllar (3 til × TXT+PDF)
└── webcard/                      # ⚠️ Sitejet runtime (TEGMASLIK)
```

- **Entry point:** `index.html` (root redirect) → `uz/index.html`
- **Yagona backend:** `api.php` (Sitejet proxy, forma yuborish, image proxy)
- **Build yo'q:** barcha fayllar to'g'ridan-to'g'ri brauzerga yuboriladi

## Gotchas

### 🔴 Hech qachon o'zgartirmang

- **`api.php`** — bu Sitejet bilan integratsiya qilingan proxy. Form yuborish, image proxy, `X-Api-Token` header handling shu yerda. Buzilsa — sayt ishlamaydi.
- **`.htaccess`** — barcha URL rewrite qoidalari. Noto'g'ri tahrirlash → 500 xato yoki redirect loop.
- **`css/custom.*.css`, `js/custom.*.js`** — Sitejet avto-generatsiya qiladi. Qo'lda o'zgartirilsa, keyingi Sitejet eksportida yo'qoladi.
- **`webcard/`** — Sitejet runtime (bundle'lar). Tegilsa — sayt buziladi.
- **`bundles/flag-icon-css/`** — 600+ bayroq SVG. O'chirmang, til almashtirgichda ishlatiladi.

### 🟡 Konflikt oldini olish

- **`revamp.css`** dagi barcha selektorlar **`.revamp-` prefiksi** bilan boshlanishi kerak (masalan `.revamp-hero`, `.revamp-card`). Aks holda Sitejet uslublariga ta'sir qiladi.
- **`revamp.js`** IIFE pattern'da yozilgan (`(function(){...})()`). Global o'zgaruvchilar chiqarmang.
- **CSS prioritet tartibi:** `custom.*.css` → `portfolio.css` → `revamp.css` (oxirgi ustunlik oladi). Yangi qoidalar faqat `revamp.css` ga qo'shilsin.

### 🟡 Ko'p tillilik

- 3 tilni **bir xil** yangilash kerak. Bittasini o'zgartirib, boshqasini eski qoldirmang — SEO buziladi.
- Har bir `index.html` da `<html lang="uz|en|ru">` to'g'ri bo'lishi kerak.
- `hreflang` linklari uch tilda ham mavjud bo'lsin (`uz`, `en`, `ru`, `x-default`).

### 🟡 Forma yuborish

- `contact form` → `/api.php/contact` ga POST qiladi.
- Agar backend ishlamasa, JS `mailto:` ga fallback qiladi (`js/revamp.js` da).
- Sitejet form config `api.php` orqali olinadi — `id` maydoni bilan.

### 🟡 CV fayllar

- `files/` papkasida 3 tilda `sardorbek-otaboyev-cv-{en,ru,uz}.txt` va `.pdf`
- Eski `sardorbek-otaboyev-cv.pdf` va `sardorbek-otaboyev-cv.tx` (noto'g'ri nom) — o'chirilgan/backup qilingan bo'lishi kerak
- PDF yaratish: `pandoc ... -V mainfont="DejaVu Sans Mono"` (Kirill va lotin apostroflari uchun majburiy)

## Conventions

- **Commit xabarlari:** ingliz tilida, `feat:`, `fix:`, `docs:`, `chore:` prefikslari bilan (Conventional Commits).
- **Yangi sahifa qo'shilsa** — `sitemap.xml` ga ham 3 tilda qo'shilsin.
- **Yangi matn qo'shilsa** — 3 tilda yozilsin.
- **CSS:** BEM uslubi (`.revamp-card__title`, `.revamp-btn--primary`).
- **JS:** IIFE + `'use strict'`, `IntersectionObserver` bilan animatsiyalar.
- **Rasmlar:** `loading="lazy"`, `decoding="async"`, `width`/`height` atributlari bilan.
- **Shriftlar:** Google Fonts — `Inter` + `JetBrains Mono`. Mahalliy `g/fonts/` (Roboto, Ubuntu) — Sitejet uchun, o'chirmang.
- **Icons:** `https://cdn.simpleicons.org/<slug>/<hex>` orqali (masalan `/go/00ADD8`).

## References

| Fayl | Nima uchun |
|---|---|
| `README.md` | Umumiy loyiha tavsifi, deploy qadamlari |
| `.gitignore` | `webcard/`, `github_keys.txt`, `custom.*` — git'dan tashqarida |
| `sitemap.xml` | 3 til URL xaritasi (SEO) |
| `css/revamp.css` | Yangi premium dizayn (`.revamp-` prefiks) |
| `js/revamp.js` | Interaktivlik (theme, filter, GitHub API, form) |
| `files/sardorbek-otaboyev-cv-{en,ru,uz}.txt` | CV manba (PDF qayta yaratish uchun) |
| `images/{0,1024,1920}/` | Sitejet responsive rasm variantlari |

## Eslatma — agent uchun

Agar topshiriq **"sayt dizaynini yangila"**, **"yangi section qo'sh"**, yoki **"animatsiya qo'sh"** bo'lsa:
- Faqat `css/revamp.css` va `js/revamp.js` ga o'zgartirish kiriting.
- `uz/`, `en/`, `ru/` dagi `index.html` larni ham 3 tilda yangilang.
- Hech qachon `api.php`, `.htaccess`, `webcard/`, `custom.*` ga tegmang.

Agar topshiriq **"CV yangila"** bo'lsa:
- `files/sardorbek-otaboyev-cv-{en,ru,uz}.txt` ni yangilang.
- So'ngra `pandoc` bilan `.pdf` qayta yarating.
- 3 tilda ham izchil bo'lsin.

Agar topshiriq **"yangi til qo'sh"** bo'lsa (masalan `ar/`):
- `uz/` ni nusxalab `ar/` yarating, matnlarni tarjima qiling.
- `sitemap.xml` ga qo'shing.
- `hreflang` linklarini barcha 4 tilga yangilang.
- `lang-switch` bayroqchasini qo'shing.

**Har bir o'zgarishdan keyin:** `php -S localhost:8000` da 3 tilni brauzerda ochib, JS konsol xatolarini tekshiring.
