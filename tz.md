# Premium Portfolio Revamp — Final Master Brief

Bu fayl loyiha holatini yanada mukammal, aniq va professional tarzda yozib berish uchun ishlatiladi. U mavjud portfolio tizimining real strukturasini, texnik talablarini, ishlab chiqilgan funksionallikni va yakuniy holatini bir joyda jamlaydi.

## 1. Loyihaning maqsadi

Sardorbek Otaboyev uchun yaratilgan ko‘p tilli portfolio saytni professional, premium va produktsion darajaga olib chiqish kerak. Sayt quyidagilarni ta’minlashi lozim:

- premium developer portfolio hissi
- 3 tilni qo‘llab quvvatlash: uz, ru, en
- statik frontend + PHP backend integratsiyasi
- tez, responsiv, SEO-friendly va clean kod
- GitHub real ma’lumotlarini ko‘rsatish
- contact form, CV va portfolio CTA
- dark/light theme, glassmorphism, geometric hover va soft motion
- API token validatsiyasi va form ishlashi
- production-ready Apache/Nginx konfiguratsiyasi

## 2. Hozirgi real loyiha holati

Loyiha amalda ishlab turgan va final polish bosqichida bo‘lib, quyidagi funksiyalar mavjud:

- root `/` sahifasi til aniqlab mos lokallashgan sahifaga yo‘naltiradi
- `/uz/`, `/ru/`, `/en/` sahifalari mavjud va ishlaydi
- headerda language switcher va theme toggle ishlaydi
- hero, about, services, projects, GitHub va contact sectionlari mavjud
- project filterlar, counter animations, reveal animations va mobile nav ishlaydi
- GitHub API orqali avatar va statlar yuklanadi
- contact form AJAX submit ishlaydi, backend ishlamasa mailto fallback ishlaydi
- CSS va JS premium dark/light visual system bilan ishlaydi
- lokal serverdan HTTP 200 OK javob qaytaradilar

## 3. Asosiy loyiha strukturasi

- `index.html` — root redirect va til aniqlash
- `api.php` — php API va protected endpointlar
- `.htaccess` — routing, rewrite va security headers
- `uz/`, `ru/`, `en/` — lokalizatsiya sahifalari
- `css/portfolio.css` — umumiy premium dizayn
- `js/portfolio.js` — interaktivlar, theme, filters, counters, GitHub va form logika
- `README.md` — deploy va ishlash ko‘rsatmalari
- `files/sardorbek-otaboyev-cv.txt` — CV fayl
- `favicon.svg` — browser branding

## 4. Asosiy texnik talablar

### Frontend
- HTML5 + CSS3 + vanilla JavaScript
- framework ishlatilmasligi kerak
- mobile-first, responsive, accessible
- all sections must remain stable across screen sizes

### Multilingual system
- `/uz/`, `/ru/`, `/en/` sahifalari ishlashi kerak
- root `/` avtomatik tilni aniqlab mos sahifaga yo‘naltirishi kerak
- barcha nav, CTA, labels, project links va meta bilgilar tilga mos bo‘lishi kerak

### API va backend
- `api.php` ishlashi va endpointga X-Api-Token tekshiruvi kerak
- qabul qilinadigan format:
  - `X-Api-Token: <token>`
- muhit o‘zgaruvchilari:
  - `API_TOKEN`
  - `SITEHUB_API_TOKEN`
  - `OTABOYEV_API_TOKEN`
- agar barchasi bo‘lmasa, faqat lokal dev fallback ishlatilsin
- contact form va backend interactionlar fallback bilan ishlashi kerak

### Deployment
- lokal preview uchun PHP built-in server yoki Python HTTP server ishlatilishi mumkin
- production uchun Apache/Nginx tayyor bo‘lishi kerak
- `.htaccess` rewrite qoidalari, cache headers va security headers mavjud bo‘lishi kerak
- production server config uchun real hosting credentials yo‘qligidan tashqari, kod tarafidan holat tayyor

## 5. Dizayn tizimi

### Ranglar
- dark mode default
- light mode alternate
- accent colors: indigo, violet, cyan
- premium effectlar: glassmorphism, blurred glow, subtle shadows

### Visual style
- geometric modern layout
- rounded cards and soft borders
- floating background blobs
- hover lift, glow, soft motion
- airy spacing and premium section rhythm
- polished backend-developer brand identity

### Theme behavior
- dark/light toggle bo‘lishi kerak
- state localStorage orqali saqlanishi kerak
- smooth transitionlar ishlashi kerak
- incorrect theme flash bo‘lmasligi kerak
- toggle icon va brand mood yanada professional ko‘rinishga ega bo‘lishi kerak

## 6. Sectionlar va UI komponentlari

### Header
- sticky navbar
- round logo / badge style
- nav links: Home / About / Services / Projects / Stack / Contact
- language switcher (UZ / EN / RU)
- theme toggle
- mobile hamburger menu
- mobile menu close on nav click

### Hero section
- strong headline, gradient text, premium label
- 2 CTA: view projects, contact me
- tech badges row
- floating blurred orbs background
- subtle grid pattern
- mouse parallax only on hover-capable devices
- backend-focused value proposition

### About section
- text + profile image area
- professional summary
- 4 stats cards with count-up animation
- experience, technologies, projects, platform metrics

### Services section
- 4 cards: Backend, Mobile, Frontend, DevOps
- icon + title + short description
- hover lift and glow

### Projects section
- filter buttons: All / Web / Mobile / API / DevOps / Open Source
- project cards with cover image, status badge, tags, CTA
- gradient hover border and lift effect
- case study / live labels
- project detail links to localized project pages

### Stack section
- backend / frontend / mobile / devops tech groups
- clear pills and categories
- full-stack and mobile developer identity must be visible

### GitHub section
- real GitHub avatar fetched from profile
- profile stats loaded dynamically
- fallback mode if fetch fails
- repo list filtered to avoid irrelevant noisy public repos

### Contact section
- glassmorphism form styling
- validation on fields
- success flash animation after submit
- mailto fallback when backend is unavailable
- clean CV download and social links

### Footer
- social links, copyright, language switcher
- minimal premium finishing touch

## 7. JavaScript funksionalligi

`js/portfolio.js` ichida quyidagilar ishlashi kerak:

- dark/light theme switcher
- mobile nav toggle
- counter animations on scroll
- project filter behavior
- reveal animations for sections and cards
- GitHub avatar and stats fetch
- contact form success/error handling
- smooth hover motion and pointer polish
- accessibility-friendly focus states
- safe localStorage handling for restricted browsers
- menu closing logic after nav item selection

Talablar:
- syntax must be valid
- no broken selectors
- no console-breaking code
- event listeners should gracefully handle missing elements

## 8. CSS talablar

`css/portfolio.css` ichida quyidagilar bo‘lishi kerak:

- CSS variables for theme
- premium card styles
- responsive layouts
- glassmorphism form blocks
- floating blob motion
- hover transforms and highlights
- `prefers-reduced-motion` support
- consistent typography scale and spacing
- background gradients and grid overlays for premium look

## 9. SEO va branding

- title, description, canonical, OG, Twitter tags
- hreflang uz/en/ru
- favicon and browser icon set correctly
- social cards must look clean and professional
- project and profile content should read like a credible developer portfolio

## 10. Quality bar

Loyiha qabul qilinishining asosiy mezonlari:

- barcha sahifalar ishlashi kerak
- root redirect ishlashi kerak
- uz/ru/en routes and nav links correct
- project and contact pages not broken
- CSS and JS paths valid
- GitHub avatar renders
- dark/light toggle works
- form success state visible
- local preview returns HTTP 200
- console errors minimal/none in normal use
- mobile menu and interactions remain stable

## 11. Constraints

- `api.php` core logicini buzmaslik kerak
- `.htaccess` muhim routing qoidalarini saqlash kerak
- SiteJet-generated fayllarni bexos o‘chirmaslik kerak
- static-first architektura saqlanishi kerak
- heavy libraries va framework ishlatilmasligi kerak
- premium polish bo‘lsada, performance va stability ustun bo‘lishi kerak

## 12. Final target result

Yakun natija bo‘lishi kerak:

- premium, modern, personal portfolio with strong developer identity
- dark default aesthetic with polished light mode
- GitHub avatar and live profile feel
- rich project cards and smooth micro-interactions
- multilingual, responsive, production-aware structure
- stable local preview and clean final polish
- good enough for presentation, client review, and deployment preparation

## 13. Acceptance checklist

- [x] `/` redirect works
- [x] `/uz/`, `/ru/`, `/en/` render correctly
- [x] theme toggle works
- [x] project filters work
- [x] counter animations trigger
- [x] floating blobs and motion feel premium but subtle
- [x] contact form success state works
- [x] GitHub avatar displays
- [x] favicon and metadata are correct
- [x] API token validation works
- [x] `.htaccess` routing works
- [x] local preview loads without broken assets
- [x] README and deployment docs are complete

## 14. Final instruction for implementation

Bu loyiha shunchaki "guzal sayt" emas, balki professional developer portfolioni asosiy maqsad qilib olgan, stabil, responsiv, premium va real-world ready sayt bo‘lishi kerak. Barcha qo‘shimchalar, animatsiyalar va texnik talablar loyihaning mavjud strukturasi bilan muvofiq bo‘lishi kerak. Dasturiy yechimlar tez, omonatli, clean va maintainable bo‘lishi kerak.

Asosiy tamoyil:
- premium design + stability
- polished visuals + correct functionality
- modern portfolio identity + real project constraints
- production readiness + local working flow

Hozirgi holat: loyiha real production-ready portfolio tayyorgarlik bosqichiga yetdi. Kod va UI ishlayapti, lokal preview ishlayapti, GitHub va formlar ishlayapti, va final premium polish uchun kerakli asosiy darboqlar tayyor.

This file is the final master brief for the portfolio update cycle.
