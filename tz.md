## 7️⃣ Brauzerda tekshirish (asosiy test)

Brauzerda quyidagi URL'larni oching:

| URL | Kutilayotgan natija |
|---|---|
| https://otaboyev-sardorbek.uz/files/sardorbek-otaboyev-cv-en.pdf | PDF ochiladi (EN) |
| https://otaboyev-sardorbek.uz/files/sardorbek-otaboyev-cv-ru.pdf | PDF ochiladi (RU) |
| https://otaboyev-sardorbek.uz/files/sardorbek-otaboyev-cv-uz.pdf | PDF ochiladi (UZ) |
| https://otaboyev-sardorbek.uz/files/sardorbek-otaboyev-cv-en.txt | Matn ochiladi |

Yoki terminaldan:

```bash
curl -I https://otaboyev-sardorbek.uz/files/sardorbek-otaboyev-cv-en.pdf
# Kutilayotgan: HTTP/1.1 200 OK, Content-Type: application/pdf

curl -I https://otaboyev-sardorbek.uz/files/sardorbek-otaboyev-cv-uz.pdf
# Kutilayotgan: HTTP/1.1 200 OK
```

---

## 8️⃣ Portfolio'ga havolalarni qo'shish

`index.html` da **Contact** section'da quyidagilarni qo'shing:

```html
<a href="/files/sardorbek-otaboyev-cv-en.pdf" target="_blank" class="contact-link">
  <span class="contact-link-icon">🇬🇧</span>
  <div class="contact-link-body">
    <span class="contact-link-label">Resume (English)</span>
    <span class="contact-link-value">Download PDF</span>
  </div>
</a>

<a href="/files/sardorbek-otaboyev-cv-ru.pdf" target="_blank" class="contact-link">
  <span class="contact-link-icon">🇷🇺</span>
  <div class="contact-link-body">
    <span class="contact-link-label">Резюме (Русский)</span>
    <span class="contact-link-value">Скачать PDF</span>
  </div>
</a>

<a href="/files/sardorbek-otaboyev-cv-uz.pdf" target="_blank" class="contact-link">
  <span class="contact-link-icon">🇺🇿</span>
  <div class="contact-link-body">
    <span class="contact-link-label">Rezyume (O'zbekcha)</span>
    <span class="contact-link-value">Yuklab olish</span>
  </div>
</a>
```

---

## 9️⃣ Git commit (agar `files/` repo ichida bo'lsa)

```bash
cd /home/host9590/public_html
git status

# Yangi fayllarni qo'shish
git add files/sardorbek-otaboyev-cv-*.{txt,pdf}
git commit -m "feat: add multi-language CV (EN/RU/UZ) in TXT and PDF"
git push origin main
```

**Agar `files/` `.gitignore`'da bo'lsa:**
```bash
cat .gitignore | grep files
```
Agar `files/` ignore qilingan bo'lsa — serverga to'g'ridan-to'g'ri `scp` bilan yuklash yetarli, git kerak emas.

---

## 📋 Yakuniy holat jadvali

| # | Qadam | Buyruq | Holat |
|---|---|---|---|
| 1 | UZ PDF yaratish | ✅ Bajarildi | ✅ |
| 2 | 3 PDF'ni ochib tekshirish | `xdg-open *.pdf` | ⏳ **Hozir** |
| 3 | Eski `.tx` va `.pdf` tozalash | `mv ... ~/Desktop/cv-backup/` | ⏳ |
| 4 | Serverga yuklash | `scp ... otaboyev:` | ⏳ |
| 5 | Serverda tekshirish | `ssh otaboyev; ls -lh` | ⏳ |
| 6 | Brauzerda tekshirish | `curl -I https://...` | ⏳ |
| 7 | HTML'ga havolalar qo'shish | Edit `index.html` | ⏳ |

---

## 🚀 Bir buyruqda: tozalash + yuklash

Agar barcha PDF'lar to'g'ri ko'rinsa, quyidagi ketma-ketlikni ishlating:

```bash
cd ~/Desktop/otaboyev-sardorbek.uz/files && \
mkdir -p ~/Desktop/cv-backup && \
mv sardorbek-otaboyev-cv.pdf ~/Desktop/cv-backup/ 2>/dev/null; \
mv sardorbek-otaboyev-cv.tx ~/Desktop/cv-backup/ 2>/dev/null; \
echo "=== Mahalliy fayllar ===" && \
ls -lh && \
echo "=== Serverga yuklash ===" && \
scp sardorbek-otaboyev-cv-*.{txt,pdf} \
    otaboyev:/home/host9590/public_html/files/ && \
echo "✅ Serverga yuklandi!"
```

---
