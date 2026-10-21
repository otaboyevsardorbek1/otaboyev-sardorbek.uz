export const SITE_URL = 'https://otaboyev-sardorbek.uz';
export const LOCALES = ['uz', 'en', 'ru'];

export const content = {
  uz: {
    meta: {
      title: 'Sardorbek Otaboyev | Backend Developer',
      description:
        'Sardorbek Otaboyevning portfeli — backend dasturchi, API, AI xizmatlari, Telegram botlar va dasturchilar uchun vositalar yaratadi.',
      ogTitle: 'Sardorbek Otaboyev | Backend Developer',
      ogDescription:
        'Ishonchli API, AI integratsiyalari va zamonaviy mahsulotlar uchun texnologik yechimlar yarataman.',
    },
    a11y: {
      home: 'Bosh sahifa',
      nav: 'Asosiy navigatsiya',
      language: 'Til tanlash',
      theme: 'Rang rejimini almashtirish',
      menu: 'Menyu ochish',
    },
    nav: [
      { href: '#top', label: 'Bosh sahifa' },
      { href: '#about', label: 'Men haqimda' },
      { href: '#services', label: 'Xizmatlar' },
      { href: '#projects', label: 'Loyihalar' },
      { href: '#contact', label: "Bog'lanish" },
    ],
    hero: {
      kicker: 'Backend Developer • AI Solutions',
      titleBefore: 'Men zamonaviy mahsulotlar uchun ',
      titleHighlight: 'ishga turgan tizimlar',
      titleAfter: ' yarataman.',
      subtitle:
        "Men Sardorbek Otaboyev, backend dasturchiman. AI integratsiyasi, Telegram botlar, oshkormas REST API, avtomatlashtirish va dasturchilar uchun vositalar ustida ishlayman.",
      primaryCta: "Bog'lanish",
      secondaryCta: "Loyihalarni ko'rish",
      stats: [
        { target: 5, suffix: '+', label: 'yil' },
        { target: 20, suffix: '+', label: 'loyihalar' },
        { target: 99, suffix: '%', label: 'diqqat' },
      ],
    },
    about: {
      tag: 'Men haqimda',
      title: 'Mahsulot nuqtayi nazariga ega dasturchi.',
      text:
        "Men g'oyalarni toza va qulay tizimlarga aylantirishni yaxshi ko'raman. Backend arxitekturasi, avtomatlashtirish, Telegram va AI integratsiyalari, SaaS vositalari va tezkor jamoalar uchun APIlar ustida ishlayman. Kod sifati, aniq muloqot va masshtabga mo'ljallangan yechimlar meni qiziqtiradi.",
      items: [
        { icon: '⚙️', title: 'Tizim dizayni', text: 'API, async vazifalar va biznes logikasi uchun arxitektura.' },
        { icon: '🤖', title: 'AI va avtomatlashtirish', text: 'AI agentlari, MCP xizmatlari va intellektual ish jarayonlari.' },
        { icon: '🚀', title: 'Yetkazib berish', text: 'Docker, deploy ishlari va monitoring bilan tayyor ishlab turuvchi yechimlar.' },
      ],
    },
    services: {
      tag: 'Xizmatlar',
      title: 'Nima yarataman.',
      items: [
        { title: 'Backend ishlab chiqish', text: "REST API, orqa fonda vazifalar, mikroservislar va ma'lumotlar bazasi bilan ishlash." },
        { title: 'AI vositalar va botlar', text: 'Telegram botlar, AI assistentlar, MCP serverlar va ish jarayonlarini avtomatlashtirish.' },
        { title: 'DevOps va deploy', text: "Docker, CI/CD, muhit sozlamalari va ishlab chiqish vaziyatida monitoring." },
      ],
    },
    projects: {
      tag: 'Loyihalar',
      title: 'Tanlangan ishlar.',
      listIntro:
        "Har bir loyiha haqida batafsil ma'lumot, natijalar va ishlatilgan texnologiyalar.",
      backHome: 'Bosh sahifaga qaytish',
      allProjects: 'Barcha loyihalar',
      filtersLabel: 'Loyiha filtrlari',
      filters: [
        { id: 'all', label: 'Barchasi' },
        { id: 'ai', label: 'AI' },
        { id: 'bots', label: 'Botlar' },
        { id: 'api', label: 'API' },
      ],
      detailLabel: 'Batafsil',
      items: [
        {
          slug: 'gitinsight',
          category: 'ai',
          tag: 'AI',
          title: 'GitInsight Dashboard',
          text: "GitHub profillari uchun analitika platformasi: faoliyat grafigi va repozitoriyalar statistikasi.",
          tech: ['React', 'Vite', 'GitHub API'],
        },
        {
          slug: 'telegram-ops',
          category: 'bots',
          tag: 'Bots',
          title: 'Telegram Operations',
          text: "Bildirishnomalar, to'lovlar, vazifalar va jamoa avtomatlashtirish uchun biznes botlari.",
          tech: ['Python', 'Telegram API', 'PostgreSQL'],
        },
        {
          slug: 'service-platform',
          category: 'api',
          tag: 'API',
          title: 'Service Platform',
          text: "Mahsulot jamoalari uchun yuqori unumli backend xizmatlari, xavfsiz API va qulay ishlash.",
          tech: ['FastAPI', 'Go', 'Docker'],
        },
      ],
    },
    github: {
      tag: 'GitHub',
      title: 'Jonli profil ko‘rsatkichlari',
      loginLabel: 'Login',
      loading: 'loading...',
      loadingBio: 'Profil bio yuklanmoqda...',
      defaultLogin: 'otaboyevsardorbek1',
      defaultBio: 'Backend-first engineer building reliable software.',
      repos: 'Repos',
      followers: 'Followers',
      following: 'Following',
      fallbackRepos: [
        { name: 'Backend Systems', language: 'Python', stargazers_count: 12 },
        { name: 'AI Workflows', language: 'Go', stargazers_count: 8 },
        { name: 'Automation Tools', language: 'TypeScript', stargazers_count: 6 },
        { name: 'DevOps Setup', language: 'Docker', stargazers_count: 4 },
      ],
    },
    contact: {
      tag: "Bog'lanish",
      title: 'Birgalikda qiymatli yechim yarataylik.',
      cardTitle: 'Yangi ishlar uchun tayyorman',
      cardText:
        "Agar sizga API, avtomatlashtirish, AI funksiyalar yoki mahsulot ishlab chiqish uchun ishonchli backend dasturchi kerak bo'lsa, men yordam berishga tayyorman.",
      name: 'Ismingiz',
      namePlaceholder: 'Ismingiz',
      email: 'Email',
      subject: 'Mavzu',
      subjectPlaceholder: "Loyiha bo'yicha so'rov",
      message: 'Xabar',
      messagePlaceholder: "G'oyangiz haqida yozing...",
      submit: 'Xabar yuborish',
      sending: 'Yuborilmoqda...',
      success: 'Xabar muvaffaqiyatli yuborildi.',
      fallback:
        "Backend bilan aloqa yo'q; email ilovasi tayyor xabar bilan ochilmoqda.",
      cvPdf: 'CV yuklab olish (PDF)',
      cvTxt: 'TXT',
      quickTitle: 'Qisqacha',
      quickText:
        "O'zbekiston · Masofadan ishlashga tayyor · Open to Work · Mahsulotga yo'naltirilgan backend muhandisi.",
    },
    footer: {
      name: 'Sardorbek Otaboyev',
      note: 'Modern backend mahsulotlari uchun yaratilgan.',
    },
  },

  en: {
    meta: {
      title: 'Sardorbek Otaboyev | Backend Developer',
      description:
        'Portfolio of Sardorbek Otaboyev — backend developer building APIs, AI integrations, Telegram bots and developer tools.',
      ogTitle: 'Sardorbek Otaboyev | Backend Developer',
      ogDescription:
        'Reliable APIs, AI integrations and engineering solutions for modern products.',
    },
    a11y: {
      home: 'Home',
      nav: 'Main navigation',
      language: 'Language selector',
      theme: 'Toggle color mode',
      menu: 'Open menu',
    },
    nav: [
      { href: '#top', label: 'Home' },
      { href: '#about', label: 'About' },
      { href: '#services', label: 'Services' },
      { href: '#projects', label: 'Projects' },
      { href: '#contact', label: 'Contact' },
    ],
    hero: {
      kicker: 'Backend Developer • AI Solutions',
      titleBefore: 'I build ',
      titleHighlight: 'reliable systems',
      titleAfter: ' for modern products.',
      subtitle:
        'I’m Sardorbek Otaboyev, a backend developer focused on AI integrations, Telegram bots, scalable REST APIs, automation, and developer tools that make products faster and more reliable.',
      primaryCta: 'Let’s talk',
      secondaryCta: 'View projects',
      stats: [
        { target: 5, suffix: '+', label: 'Years' },
        { target: 20, suffix: '+', label: 'Projects' },
        { target: 99, suffix: '%', label: 'Focus' },
      ],
    },
    about: {
      tag: 'About',
      title: 'Developer with product mindset.',
      text:
        'I enjoy turning ideas into clean, maintainable systems. My work includes backend architecture, automation workflows, Telegram and AI integrations, SaaS tooling, and API design for fast-moving teams. I care about code quality, clear communication, and solutions that scale.',
      items: [
        { icon: '⚙️', title: 'System design', text: 'Architecture for APIs, async jobs, and stable business logic.' },
        { icon: '🤖', title: 'AI & automation', text: 'Integrations with AI agents, MCP tools, and intelligent workflows.' },
        { icon: '🚀', title: 'Delivery', text: 'Production-ready delivery with Docker, deployment workflows, and monitoring.' },
      ],
    },
    services: {
      tag: 'Services',
      title: 'What I can build.',
      items: [
        { title: 'Backend development', text: 'REST APIs, background jobs, microservices, and database-driven business logic.' },
        { title: 'AI tools & bots', text: 'Telegram bots, AI assistants, MCP servers, and workflow automation systems.' },
        { title: 'DevOps & deployment', text: 'Docker setup, CI/CD, environment configuration, and production monitoring.' },
      ],
    },
    projects: {
      tag: 'Projects',
      title: 'Selected work.',
      listIntro: 'Details, outcomes and technology choices for each selected project.',
      backHome: 'Back to home',
      allProjects: 'All projects',
      filtersLabel: 'Project filters',
      filters: [
        { id: 'all', label: 'All' },
        { id: 'ai', label: 'AI' },
        { id: 'bots', label: 'Bots' },
        { id: 'api', label: 'API' },
      ],
      detailLabel: 'View detail',
      items: [
        {
          slug: 'gitinsight',
          category: 'ai',
          tag: 'AI',
          title: 'GitInsight Dashboard',
          text: 'Analytics platform for GitHub profiles with activity charts, repo insights, and performance metrics.',
          tech: ['React', 'Vite', 'GitHub API'],
        },
        {
          slug: 'telegram-ops',
          category: 'bots',
          tag: 'Bots',
          title: 'Telegram Operations',
          text: 'Business bot systems for notifications, payments, workflows, and team automation across channels.',
          tech: ['Python', 'Telegram API', 'PostgreSQL'],
        },
        {
          slug: 'service-platform',
          category: 'api',
          tag: 'API',
          title: 'Service Platform',
          text: 'High-performance backend services for product teams with secure APIs and clean developer ergonomics.',
          tech: ['FastAPI', 'Go', 'Docker'],
        },
      ],
    },
    github: {
      tag: 'GitHub',
      title: 'Live profile metrics',
      loginLabel: 'Username',
      loading: 'loading...',
      loadingBio: 'Loading profile bio...',
      defaultLogin: 'otaboyevsardorbek1',
      defaultBio: 'Backend-first engineer building reliable software.',
      repos: 'Repos',
      followers: 'Followers',
      following: 'Following',
      fallbackRepos: [
        { name: 'Backend Systems', language: 'Python', stargazers_count: 12 },
        { name: 'AI Workflows', language: 'Go', stargazers_count: 8 },
        { name: 'Automation Tools', language: 'TypeScript', stargazers_count: 6 },
        { name: 'DevOps Setup', language: 'Docker', stargazers_count: 4 },
      ],
    },
    contact: {
      tag: 'Contact',
      title: 'Let’s build something valuable.',
      cardTitle: 'Available for new work',
      cardText:
        'If you need a dependable backend developer for APIs, automation, AI features, or product engineering, I’m ready to help.',
      name: 'Full name',
      namePlaceholder: 'Your name',
      email: 'Email',
      subject: 'Subject',
      subjectPlaceholder: 'Project inquiry',
      message: 'Message',
      messagePlaceholder: 'Tell me about your idea...',
      submit: 'Send message',
      sending: 'Sending...',
      success: 'Message sent successfully.',
      fallback:
        'Connection to the backend is unavailable; your email app is opening with a prepared message.',
      cvPdf: 'Download CV (PDF)',
      cvTxt: 'TXT',
      quickTitle: 'Quick profile',
      quickText: 'Uzbekistan · Remote-friendly · Open to Work · Backend-first engineer with product focus.',
    },
    footer: {
      name: 'Sardorbek Otaboyev',
      note: 'Built for modern backend products.',
    },
  },

  ru: {
    meta: {
      title: 'Sardorbek Otaboyev | Backend Developer',
      description:
        'Портфолио Сардорбека Отабоева — backend-разработчика: API, AI-интеграции, Telegram-боты и инструменты для разработчиков.',
      ogTitle: 'Sardorbek Otaboyev | Backend Developer',
      ogDescription:
        'Надежные API, AI-интеграции и инженерные решения для современных продуктов.',
    },
    a11y: {
      home: 'Главная',
      nav: 'Основная навигация',
      language: 'Выбор языка',
      theme: 'Переключить тему',
      menu: 'Открыть меню',
    },
    nav: [
      { href: '#top', label: 'Главная' },
      { href: '#about', label: 'Обо мне' },
      { href: '#services', label: 'Услуги' },
      { href: '#projects', label: 'Проекты' },
      { href: '#contact', label: 'Контакты' },
    ],
    hero: {
      kicker: 'Backend Developer • AI Solutions',
      titleBefore: 'Я создаю ',
      titleHighlight: 'надежные системы',
      titleAfter: ' для бизнеса.',
      subtitle:
        'Я — Сардорбек Отабоев, backend-разработчик, который строит AI-интеграции, Telegram-ботов, масштабируемые REST API, автоматизацию и инструменты для разработчиков.',
      primaryCta: 'Связаться',
      secondaryCta: 'Портфолио',
      stats: [
        { target: 5, suffix: '+', label: 'лет' },
        { target: 20, suffix: '+', label: 'проектов' },
        { target: 99, suffix: '%', label: 'внимания' },
      ],
    },
    about: {
      tag: 'Обо мне',
      title: 'Разработчик с продуктовым мышлением.',
      text:
        'Мне нравится превращать идеи в чистые и поддерживаемые системы. Я работаю над backend-архитектурой, автоматизацией, Telegram и AI-интеграциями, SaaS-инструментами и API для быстрорастущих команд. Для меня важны качество кода, ясная коммуникация и решения, которые масштабируются.',
      items: [
        { icon: '⚙️', title: 'Архитектура', text: 'API, async задачи, бизнес-логика и стабильная системная работа.' },
        { icon: '🤖', title: 'AI и автоматизация', text: 'Интеграции с AI-агентами, MCP-сервисами и рабочими процессами.' },
        { icon: '🚀', title: 'Релизы', text: 'Готовые к продакшен решения с Docker, деплоем и мониторингом.' },
      ],
    },
    services: {
      tag: 'Услуги',
      title: 'Что я умею делать.',
      items: [
        { title: 'Backend-разработка', text: 'REST API, фоновые задачи, микросервисы и серверная логика для бизнеса.' },
        { title: 'AI-сервисы и боты', text: 'Telegram-боты, AI-ассистенты, MCP-серверы и автоматизация процессов.' },
        { title: 'DevOps и деплой', text: 'Docker, CI/CD, инфраструктура и стабильный запуск продакшн-сервисов.' },
      ],
    },
    projects: {
      tag: 'Проекты',
      title: 'Избранные решения.',
      listIntro: 'Подробности, результаты и технологии каждого избранного проекта.',
      backHome: 'Вернуться на главную',
      allProjects: 'Все проекты',
      filtersLabel: 'Фильтры проектов',
      filters: [
        { id: 'all', label: 'Все' },
        { id: 'ai', label: 'AI' },
        { id: 'bots', label: 'Боты' },
        { id: 'api', label: 'API' },
      ],
      detailLabel: 'Подробнее',
      items: [
        {
          slug: 'gitinsight',
          category: 'ai',
          tag: 'AI',
          title: 'GitInsight Dashboard',
          text: 'Аналитическая платформа для GitHub-профилей с графиками активности и метриками репозиториев.',
          tech: ['React', 'Vite', 'GitHub API'],
        },
        {
          slug: 'telegram-ops',
          category: 'bots',
          tag: 'Bots',
          title: 'Telegram Operations',
          text: 'Системы бизнес-ботов для уведомлений, платежей, автоматизации и внутренних процессов.',
          tech: ['Python', 'Telegram API', 'PostgreSQL'],
        },
        {
          slug: 'service-platform',
          category: 'api',
          tag: 'API',
          title: 'Service Platform',
          text: 'Высокопроизводительные backend-сервисы для продуктовых команд с безопасными API и удобной разработкой.',
          tech: ['FastAPI', 'Go', 'Docker'],
        },
      ],
    },
    github: {
      tag: 'GitHub',
      title: 'Профиль в реальном времени',
      loginLabel: 'Логин',
      loading: 'loading...',
      loadingBio: 'Загрузка биографии...',
      defaultLogin: 'otaboyevsardorbek1',
      defaultBio: 'Backend-first engineer building reliable software.',
      repos: 'Repos',
      followers: 'Followers',
      following: 'Following',
      fallbackRepos: [
        { name: 'Backend Systems', language: 'Python', stargazers_count: 12 },
        { name: 'AI Workflows', language: 'Go', stargazers_count: 8 },
        { name: 'Automation Tools', language: 'TypeScript', stargazers_count: 6 },
        { name: 'DevOps Setup', language: 'Docker', stargazers_count: 4 },
      ],
    },
    contact: {
      tag: 'Контакты',
      title: 'Давайте создадим полезный продукт.',
      cardTitle: 'Готов к новым задачам',
      cardText:
        'Если вам нужен надежный backend-разработчик для API, автоматизации, AI-функций или продуктовой инженерии, я готов помочь.',
      name: 'Имя',
      namePlaceholder: 'Ваше имя',
      email: 'Email',
      subject: 'Тема',
      subjectPlaceholder: 'Запрос по проекту',
      message: 'Сообщение',
      messagePlaceholder: 'Расскажите о вашей идее...',
      submit: 'Отправить сообщение',
      sending: 'Отправка...',
      success: 'Сообщение успешно отправлено.',
      fallback:
        'Связь с backend недоступна; открывается почтовое приложение с готовым сообщением.',
      cvPdf: 'Скачать CV (PDF)',
      cvTxt: 'TXT',
      quickTitle: 'Кратко',
      quickText: 'Узбекистан · Работа удаленно · Open to Work · Backend-инженер с продуктовым подходом.',
    },
    footer: {
      name: 'Сардорбек Отабоев',
      note: 'Создано для современных backend-продуктов.',
    },
  },
};

export const projectDetails = {
  "uz": {
    "gitinsight": {
      "tag": "AI Analytics",
      "title": "GitInsight Dashboard",
      "summary": "GitHub faoliyati va ishlab chiqish ko‘rsatkichlari uchun analitika paneli.",
      "tech": [
        "React",
        "Vite",
        "GitHub API",
        "Charts"
      ],
      "description": "Bu mahsulot jamoalarga GitHub faolligi, repo ishtiroki va har bir dasturchining ishlash tezligini yanada aniq ko‘rish uchun ishlab chiqilgan. Asosiy e’tibor xom ma’lumotlar bilan emas, balki tushunarli ko‘rsatkichlar va amaliy insights bilan ishlashga qaratilgan.",
      "results": [
        "Ommaviy GitHub faolligi va repozitoriyalar ma'lumotlarini yig‘ish",
        "Faoliyatni solishtirish uchun zamonaviy dashboard yaratish",
        "Texnik ko‘rsatkichlarni kuzatish UX va qulayligini yaxshilash"
      ],
      "stack_notes": [
        "Frontend: React + Vite",
        "Ma’lumotlar: GitHub API integratsiyasi",
        "UI: premium analytics komponentlari"
      ]
    },
    "service-platform": {
      "tag": "Backend Platforms",
      "title": "Service Platform",
      "summary": "Xavfsiz, kengaytiriladigan API va modern mahsulot jamoalari uchun platforma yechimlari.",
      "tech": [
        "FastAPI",
        "Go",
        "Docker",
        "APIs"
      ],
      "description": "Bu loyiha yuqori unumli va xavfsiz platforma xizmatlarini yaratishga qaratilgan bo‘lib, ular tez kengaytirilishi va qulay ishlashini saqlab qolishi kerak edi. Backend arxitekturasi jamoalarga yangi funksiyalarni tezroq yetkazib berishga yordam berdi va operatsion murakkablikni boshqarishni osonlashtirdi.",
      "results": [
        "Ichki va tashqi mahsulotlar uchun qulay API shartnomalari",
        "Yaxshiroq servis chegaralari va backend abstraksiyalari",
        "Containerized yetkazib berish orqali ishonchlilikni oshirish"
      ],
      "stack_notes": [
        "API layer: FastAPI",
        "Service layer: Go-based components",
        "Delivery: Docker, CI/CD, container-based deployment"
      ]
    },
    "telegram-ops": {
      "tag": "Automation",
      "title": "Telegram Operations",
      "summary": "Bildirishnomalar, to‘lovlar va ichki aloqa jarayonlarini avtomatlashtirish.",
      "tech": [
        "Python",
        "Telegram API",
        "PostgreSQL",
        "Queues"
      ],
      "description": "Bu tizim biznes operatsiyalarini Telegram orqali tezkor va ishonchli tarzda boshqarish uchun ishlab chiqilgan. Rutin ishlarni avtomatlashtirib, jamoalar o‘rtasidagi koordinatsiyani soddalashtirishga yordam berdi.",
      "results": [
        "Foydalanuvchi bildirishnomalari va holat yangilanishlarini avtomatlashtirish",
        "To‘lov va status hodisalarini real vaqtda xabar bilan bog‘lash",
        "Qo‘llab-quvvatlash va operatsiyalar jamoalari ustidagi qo‘l mehnatini kamaytirish"
      ],
      "stack_notes": [
        "Backend: Python xizmatlari",
        "Messaging: Telegram bot API",
        "Storage: PostgreSQL va event queue"
      ]
    }
  },
  "en": {
    "gitinsight": {
      "tag": "AI Analytics",
      "title": "GitInsight Dashboard",
      "summary": "GitHub activity intelligence and performance visibility for developers and teams.",
      "tech": [
        "React",
        "Vite",
        "GitHub API",
        "Charts"
      ],
      "description": "This product was designed to give teams a clearer view of GitHub contribution trends, repository engagement, and individual coding rhythm over time. The experience emphasized clean reporting and actionable signals instead of raw data dumps.",
      "results": [
        "Aggregated public GitHub activity and repository metadata",
        "Built a polished dashboard for comparing contributor momentum",
        "Improved UX for monitoring internal or personal engineering signals"
      ],
      "stack_notes": [
        "Frontend: React + Vite",
        "Data layer: GitHub API integration",
        "UI structure: premium analytics dashboard components"
      ]
    },
    "service-platform": {
      "tag": "Backend Platforms",
      "title": "Service Platform",
      "summary": "Secure, scalable APIs and operational systems for modern product teams.",
      "tech": [
        "FastAPI",
        "Go",
        "Docker",
        "APIs"
      ],
      "description": "This project focused on delivering reliable platform services that were easy to extend without sacrificing performance or security. The backend architecture allowed teams to ship features faster while keeping operational complexity manageable.",
      "results": [
        "Seamless API contracts for internal and external product usage",
        "More maintainable backend abstractions and service boundaries",
        "Improved deployment reliability through containerized delivery"
      ],
      "stack_notes": [
        "API layer: FastAPI",
        "Service layer: Go-based components",
        "Delivery: Docker, CI/CD, container-based deployment"
      ]
    },
    "telegram-ops": {
      "tag": "Automation",
      "title": "Telegram Operations",
      "summary": "Operational automations for messaging, alerts, payments, and internal communication flows.",
      "tech": [
        "Python",
        "Telegram API",
        "PostgreSQL",
        "Queues"
      ],
      "description": "This workflow system was created to streamline business communication and operational workflows using Telegram as a lightweight but reliable user interface. It helped reduce repetitive admin work while keeping the response experience fast and transparent.",
      "results": [
        "Automated user notifications and operations updates",
        "Connected payment and status events to real-time messaging",
        "Reduced manual coordination across support and operations teams"
      ],
      "stack_notes": [
        "Backend: Python services",
        "Messaging: Telegram bot APIs",
        "Storage: PostgreSQL and event queues"
      ]
    }
  },
  "ru": {
    "gitinsight": {
      "tag": "AI Analytics",
      "title": "GitInsight Dashboard",
      "summary": "Аналитика GitHub активности и видимость метрик для разработчиков и команд.",
      "tech": [
        "React",
        "Vite",
        "GitHub API",
        "Charts"
      ],
      "description": "Продукт был создан, чтобы дать командам более ясное представление о тенденциях активности GitHub, вовлеченности репозиториев и темпе разработки каждого участника. Акцент делался на понятной аналитике и полезных сигналах, а не на сырых данных.",
      "results": [
        "Сбор публичной активности и метаданных репозиториев",
        "Создан удобный дашборд для сравнения активности участников",
        "Улучшен UX для отслеживания инженерных показателей"
      ],
      "stack_notes": [
        "Frontend: React + Vite",
        "Данные: интеграция с GitHub API",
        "UI: premium analytics components"
      ]
    },
    "service-platform": {
      "tag": "Backend Platforms",
      "title": "Service Platform",
      "summary": "Безопасные, масштабируемые API и инфраструктура для современных продуктовых команд.",
      "tech": [
        "FastAPI",
        "Go",
        "Docker",
        "APIs"
      ],
      "description": "Этот проект был направлен на создание надежных платформенных сервисов, которые легко расширяются без потери производительности и безопасности. Архитектура бэкенда позволяла быстрее выпускать новые функции и поддерживать управляемую сложность эксплуатации.",
      "results": [
        "Удобные API-контракты для внутренних и внешних решений",
        "Более поддерживаемые границы сервисов и абстракции",
        "Повышенная надежность доставки через контейнеризацию"
      ],
      "stack_notes": [
        "API layer: FastAPI",
        "Service layer: Go-based components",
        "Delivery: Docker, CI/CD, container-based deployment"
      ]
    },
    "telegram-ops": {
      "tag": "Automation",
      "title": "Telegram Operations",
      "summary": "Автоматизация процессов уведомлений, оплаты и внутренней коммуникации через Telegram.",
      "tech": [
        "Python",
        "Telegram API",
        "PostgreSQL",
        "Queues"
      ],
      "description": "Эта система была создана для автоматизации коммуникации и операционных процессов через Telegram как простой и надежный интерфейс для пользователей. Она помогла сократить рутинную работу без потери скорости и прозрачности взаимодействия.",
      "results": [
        "Автоматизация уведомлений и обновлений статусов",
        "Интеграция событий оплаты и статусов в реальном времени",
        "Снижение ручной нагрузки между командами поддержки и операций"
      ],
      "stack_notes": [
        "Backend: Python services",
        "Messaging: Telegram bot APIs",
        "Storage: PostgreSQL and event queues"
      ]
    }
  }
};

export const projectDetailLabels = {
  "uz": {
    "home": "Bosh sahifa",
    "language": "Tilni tanlash",
    "detailTitle": "Loyiha",
    "detailDescription": "Loyiha tafsilotlari.",
    "about": "Loyiha haqida",
    "results": "Natijalar",
    "stack": "Texnologiyalar",
    "back": "Portfolioga qaytish",
    "notFound": "Loyiha topilmadi.",
    "unavailable": "Loyiha ma’lumotlari vaqtincha mavjud emas.",
    "loading": "Yuklanmoqda..."
  },
  "en": {
    "home": "Home",
    "language": "Choose language",
    "detailTitle": "Project",
    "detailDescription": "Project details.",
    "about": "Project overview",
    "results": "Key outcomes",
    "stack": "Technology stack",
    "back": "Back to portfolio",
    "notFound": "Project not found.",
    "unavailable": "Project details are temporarily unavailable.",
    "loading": "Loading..."
  },
  "ru": {
    "home": "Главная",
    "language": "Выбор языка",
    "detailTitle": "Проект",
    "detailDescription": "Детали проекта.",
    "about": "Обзор проекта",
    "results": "Результаты",
    "stack": "Технологии",
    "back": "Вернуться в портфолио",
    "notFound": "Проект не найден.",
    "unavailable": "Детали проекта временно недоступны.",
    "loading": "Загрузка..."
  }
};

// Keep listing fallbacks aligned with the detail data and database seed.
for (const locale of LOCALES) {
  content[locale].projects.items = content[locale].projects.items.map((item) => {
    const detail = projectDetails[locale][item.slug];
    return detail
      ? { ...item, tag: detail.tag, title: detail.title, text: detail.summary, tech: detail.tech }
      : item;
  });
}

export function getProjectDetail(locale, slug) {
  const localeDetails = projectDetails[locale] || projectDetails.uz;
  return localeDetails[slug] || null;
}

export function getProjectDetailLabels(locale) {
  return projectDetailLabels[locale] || projectDetailLabels.uz;
}


export function getContent(locale) {
  return content[locale] || content.uz;
}
