-- Seed data for the portfolio content API.
-- Usage:
--   mysql -u USER -p DB_NAME < database/seed.sql
-- Safe to run repeatedly: rows are updated by primary key / unique slug.

SET NAMES utf8mb4;

INSERT INTO profiles (id, name, email, github, telegram, telegram_handle, avatar_url)
VALUES (
  1,
  'Sardorbek Otaboyev',
  'otaboyevsardorbek1@gmail.com',
  'https://github.com/otaboyevsardorbek1',
  'https://t.me/prodevuzoff',
  '@prodevuzoff',
  NULL
)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  email = VALUES(email),
  github = VALUES(github),
  telegram = VALUES(telegram),
  telegram_handle = VALUES(telegram_handle),
  avatar_url = VALUES(avatar_url);

INSERT INTO profile_translations (profile_id, lang, role, bio, quick_text, location)
VALUES
  (
    1,
    'uz',
    'Backend Developer • AI Solutions',
    'Men Sardorbek Otaboyev, backend dasturchiman. AI integratsiyasi, Telegram botlar, oshkormas REST API, avtomatlashtirish va dasturchilar uchun vositalar ustida ishlayman.',
    'O''zbekiston · Masofadan ishlashga tayyor · Open to Work · Mahsulotga yo''naltirilgan backend muhandisi.',
    'O''zbekiston'
  ),
  (
    1,
    'en',
    'Backend Developer • AI Solutions',
    'I’m Sardorbek Otaboyev, a backend developer focused on AI integrations, Telegram bots, scalable REST APIs, automation, and developer tools that make products faster and more reliable.',
    'Uzbekistan · Remote-friendly · Open to Work · Backend-first engineer with product focus.',
    'Uzbekistan'
  ),
  (
    1,
    'ru',
    'Backend-разработчик • AI-решения',
    'Я — Сардорбек Отабоев, backend-разработчик, который строит AI-интеграции, Telegram-ботов, масштабируемые REST API, автоматизацию и инструменты для разработчиков.',
    'Узбекистан · Работа удаленно · Open to Work · Backend-инженер с продуктовым подходом.',
    'Узбекистан'
  )
ON DUPLICATE KEY UPDATE
  role = VALUES(role),
  bio = VALUES(bio),
  quick_text = VALUES(quick_text),
  location = VALUES(location);

INSERT INTO projects (id, slug, category, repo_url, demo_url, sort_order, is_active)
VALUES
  (1, 'gitinsight', 'ai', NULL, NULL, 1, 1),
  (2, 'telegram-ops', 'bots', NULL, NULL, 2, 1),
  (3, 'service-platform', 'api', NULL, NULL, 3, 1)
ON DUPLICATE KEY UPDATE
  slug = VALUES(slug),
  category = VALUES(category),
  repo_url = VALUES(repo_url),
  demo_url = VALUES(demo_url),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active);

INSERT INTO project_translations (project_id, lang, tag, title, summary, description, tech, results, stack_notes)
VALUES
  (
    1,
    'uz',
    'AI Analytics',
    'GitInsight Dashboard',
    'GitHub faoliyati va ishlab chiqish ko‘rsatkichlari uchun analitika paneli.',
    'Bu mahsulot jamoalarga GitHub faolligi, repo ishtiroki va har bir dasturchining ishlash tezligini yanada aniq ko‘rish uchun ishlab chiqilgan. Asosiy e’tibor xom ma’lumotlar bilan emas, balki tushunarli ko‘rsatkichlar va amaliy insights bilan ishlashga qaratilgan.',
    '["React","Vite","GitHub API","Charts"]',
    '["Ommaviy GitHub faolligi va repozitoriyalar ma''lumotlarini yig‘ish","Faoliyatni solishtirish uchun zamonaviy dashboard yaratish","Texnik ko‘rsatkichlarni kuzatish UX va qulayligini yaxshilash"]',
    '["Frontend: React + Vite","Ma’lumotlar: GitHub API integratsiyasi","UI: premium analytics komponentlari"]'
  ),
  (
    1,
    'en',
    'AI Analytics',
    'GitInsight Dashboard',
    'GitHub activity intelligence and performance visibility for developers and teams.',
    'This product was designed to give teams a clearer view of GitHub contribution trends, repository engagement, and individual coding rhythm over time. The experience emphasized clean reporting and actionable signals instead of raw data dumps.',
    '["React","Vite","GitHub API","Charts"]',
    '["Aggregated public GitHub activity and repository metadata","Built a polished dashboard for comparing contributor momentum","Improved UX for monitoring internal or personal engineering signals"]',
    '["Frontend: React + Vite","Data layer: GitHub API integration","UI structure: premium analytics dashboard components"]'
  ),
  (
    1,
    'ru',
    'AI Analytics',
    'GitInsight Dashboard',
    'Аналитика GitHub активности и видимость метрик для разработчиков и команд.',
    'Продукт был создан, чтобы дать командам более ясное представление о тенденциях активности GitHub, вовлеченности репозиториев и темпе разработки каждого участника. Акцент делался на понятной аналитике и полезных сигналах, а не на сырых данных.',
    '["React","Vite","GitHub API","Charts"]',
    '["Сбор публичной активности и метаданных репозиториев","Создан удобный дашборд для сравнения активности участников","Улучшен UX для отслеживания инженерных показателей"]',
    '["Frontend: React + Vite","Данные: интеграция с GitHub API","UI: premium analytics components"]'
  ),
  (
    2,
    'uz',
    'Automation',
    'Telegram Operations',
    'Bildirishnomalar, to‘lovlar va ichki aloqa jarayonlarini avtomatlashtirish.',
    'Bu tizim biznes operatsiyalarini Telegram orqali tezkor va ishonchli tarzda boshqarish uchun ishlab chiqilgan. Rutin ishlarni avtomatlashtirib, jamoalar o‘rtasidagi koordinatsiyani soddalashtirishga yordam berdi.',
    '["Python","Telegram API","PostgreSQL","Queues"]',
    '["Foydalanuvchi bildirishnomalari va holat yangilanishlarini avtomatlashtirish","To‘lov va status hodisalarini real vaqtda xabar bilan bog‘lash","Qo‘llab-quvvatlash va operatsiyalar jamoalari ustidagi qo‘l mehnatini kamaytirish"]',
    '["Backend: Python xizmatlari","Messaging: Telegram bot API","Storage: PostgreSQL va event queue"]'
  ),
  (
    2,
    'en',
    'Automation',
    'Telegram Operations',
    'Operational automations for messaging, alerts, payments, and internal communication flows.',
    'This workflow system was created to streamline business communication and operational workflows using Telegram as a lightweight but reliable user interface. It helped reduce repetitive admin work while keeping the response experience fast and transparent.',
    '["Python","Telegram API","PostgreSQL","Queues"]',
    '["Automated user notifications and operations updates","Connected payment and status events to real-time messaging","Reduced manual coordination across support and operations teams"]',
    '["Backend: Python services","Messaging: Telegram bot APIs","Storage: PostgreSQL and event queues"]'
  ),
  (
    2,
    'ru',
    'Automation',
    'Telegram Operations',
    'Автоматизация процессов уведомлений, оплаты и внутренней коммуникации через Telegram.',
    'Эта система была создана для автоматизации коммуникации и операционных процессов через Telegram как простой и надежный интерфейс для пользователей. Она помогла сократить рутинную работу без потери скорости и прозрачности взаимодействия.',
    '["Python","Telegram API","PostgreSQL","Queues"]',
    '["Автоматизация уведомлений и обновлений статусов","Интеграция событий оплаты и статусов в реальном времени","Снижение ручной нагрузки между командами поддержки и операций"]',
    '["Backend: Python services","Messaging: Telegram bot APIs","Storage: PostgreSQL and event queues"]'
  ),
  (
    3,
    'uz',
    'Backend Platforms',
    'Service Platform',
    'Xavfsiz, kengaytiriladigan API va modern mahsulot jamoalari uchun platforma yechimlari.',
    'Bu loyiha yuqori unumli va xavfsiz platforma xizmatlarini yaratishga qaratilgan bo‘lib, ular tez kengaytirilishi va qulay ishlashini saqlab qolishi kerak edi. Backend arxitekturasi jamoalarga yangi funksiyalarni tezroq yetkazib berishga yordam berdi va operatsion murakkablikni boshqarishni osonlashtirdi.',
    '["FastAPI","Go","Docker","APIs"]',
    '["Ichki va tashqi mahsulotlar uchun qulay API shartnomalari","Yaxshiroq servis chegaralari va backend abstraksiyalari","Containerized yetkazib berish orqali ishonchlilikni oshirish"]',
    '["API layer: FastAPI","Service layer: Go-based components","Delivery: Docker, CI/CD, container-based deployment"]'
  ),
  (
    3,
    'en',
    'Backend Platforms',
    'Service Platform',
    'Secure, scalable APIs and operational systems for modern product teams.',
    'This project focused on delivering reliable platform services that were easy to extend without sacrificing performance or security. The backend architecture allowed teams to ship features faster while keeping operational complexity manageable.',
    '["FastAPI","Go","Docker","APIs"]',
    '["Seamless API contracts for internal and external product usage","More maintainable backend abstractions and service boundaries","Improved deployment reliability through containerized delivery"]',
    '["API layer: FastAPI","Service layer: Go-based components","Delivery: Docker, CI/CD, container-based deployment"]'
  ),
  (
    3,
    'ru',
    'Backend Platforms',
    'Service Platform',
    'Безопасные, масштабируемые API и инфраструктура для современных продуктовых команд.',
    'Этот проект был направлен на создание надежных платформенных сервисов, которые легко расширяются без потери производительности и безопасности. Архитектура бэкенда позволяла быстрее выпускать новые функции и поддерживать управляемую сложность эксплуатации.',
    '["FastAPI","Go","Docker","APIs"]',
    '["Удобные API-контракты для внутренних и внешних решений","Более поддерживаемые границы сервисов и абстракции","Повышенная надежность доставки через контейнеризацию"]',
    '["API layer: FastAPI","Service layer: Go-based components","Delivery: Docker, CI/CD, container-based deployment"]'
  )
ON DUPLICATE KEY UPDATE
  tag = VALUES(tag),
  title = VALUES(title),
  summary = VALUES(summary),
  description = VALUES(description),
  tech = VALUES(tech),
  results = VALUES(results),
  stack_notes = VALUES(stack_notes);
