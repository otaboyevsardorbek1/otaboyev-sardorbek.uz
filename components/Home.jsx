'use client';

import { useEffect, useRef, useState } from 'react';

const GITHUB_USER = 'otaboyevsardorbek1';
const THEME_KEY = 'portfolio-theme';
const BLOCKED_REPO_TOKENS = ['porn', 'xxx', 'adult', 'template', 'demo', 'example', 'archive', 'trash', 'test', 'copy'];

function sanitizeRepoName(value) {
  return String(value || '')
    .replace(/[_-]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function formatRepoLabel(value) {
  const cleaned = sanitizeRepoName(value);
  if (!cleaned) return 'Project';
  return cleaned.replace(/\b\w/g, (char) => char.toUpperCase());
}

function isSafeRepo(repo) {
  if (!repo) return false;
  const text = `${repo.name || ''} ${repo.description || ''}`.toLowerCase();
  return !BLOCKED_REPO_TOKENS.some((token) => text.includes(token));
}

function Counter({ target, suffix }) {
  const [value, setValue] = useState(0);

  useEffect(() => {
    const duration = 900;
    const start = performance.now();
    let frame;

    const tick = (now) => {
      const progress = Math.min((now - start) / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3);
      setValue(Math.round(target * eased));
      if (progress < 1) {
        frame = requestAnimationFrame(tick);
      } else {
        setValue(target);
      }
    };

    frame = requestAnimationFrame(tick);
    return () => cancelAnimationFrame(frame);
  }, [target]);

  return (
    <strong data-target={target} data-suffix={suffix}>
      {value}
      {suffix}
    </strong>
  );
}

export default function Home({ locale, copy }) {
  const rootRef = useRef(null);
  const headerRef = useRef(null);

  const [theme, setTheme] = useState('dark');
  const [menuOpen, setMenuOpen] = useState(false);
  const [filter, setFilter] = useState('all');
  const [status, setStatus] = useState({ type: '', message: '' });
  const [submitting, setSubmitting] = useState(false);
  const [avatar, setAvatar] = useState('');
  const [repos, setRepos] = useState(copy.github.fallbackRepos);
  const [projects, setProjects] = useState(copy.projects.items);
  const [siteProfile, setSiteProfile] = useState({
    email: 'otaboyevsardorbek1@gmail.com',
    github: 'https://github.com/otaboyevsardorbek1',
    telegram: 'https://t.me/prodevuzoff',
    telegram_handle: '@prodevuzoff',
    quick_text: copy.contact.quickText,
  });
  const [profile, setProfile] = useState({
    login: copy.github.defaultLogin,
    bio: copy.github.loadingBio,
    repos: 0,
    followers: 0,
    following: 0,
  });
  const year = new Date().getFullYear();

  useEffect(() => {
    const controller = new AbortController();
    const headers = { Accept: 'application/json' };
    const options = { headers, signal: controller.signal, credentials: 'same-origin' };

    Promise.allSettled([
      fetch(`/api.php/profile?lang=${locale}`, options).then((response) =>
        response.ok ? response.json() : null
      ),
      fetch(`/api.php/projects?lang=${locale}`, options).then((response) =>
        response.ok ? response.json() : null
      ),
    ]).then((results) => {
      if (controller.signal.aborted) return;

      const profileResult = results[0];
      if (
        profileResult.status === 'fulfilled' &&
        profileResult.value &&
        profileResult.value.status === 'ok' &&
        profileResult.value.data
      ) {
        setSiteProfile((current) => ({ ...current, ...profileResult.value.data }));
      }

      const projectsResult = results[1];
      if (
        projectsResult.status === 'fulfilled' &&
        projectsResult.value &&
        projectsResult.value.status === 'ok' &&
        Array.isArray(projectsResult.value.data)
      ) {
        const items = projectsResult.value.data
          .filter((item) => item && item.slug && item.title)
          .map((item) => ({
            slug: item.slug,
            category: item.category,
            tag: item.tag,
            title: item.title,
            text: item.summary,
            tech: Array.isArray(item.tech) ? item.tech : [],
            detailUrl: item.detail_url,
          }));

        if (items.length) {
          setProjects(items);
        }
      }
    });

    return () => controller.abort();
  }, [locale]);

  useEffect(() => {
    let saved = null;
    try {
      saved = window.localStorage?.getItem(THEME_KEY);
    } catch (error) {
      saved = null;
    }
    setTheme(saved === 'light' ? 'light' : 'dark');
  }, []);

  useEffect(() => {
    document.body.classList.toggle('light-theme', theme === 'light');
    document.body.setAttribute('data-theme', theme);
    try {
      window.localStorage?.setItem(THEME_KEY, theme);
    } catch (error) {
      // Ignore storage exceptions in private mode.
    }
  }, [theme]);

  useEffect(() => {
    const header = headerRef.current;
    if (!header) return undefined;

    const sync = () => header.classList.toggle('scrolled', window.scrollY > 16);
    sync();
    window.addEventListener('scroll', sync, { passive: true });
    return () => window.removeEventListener('scroll', sync);
  }, []);

  useEffect(() => {
    const root = rootRef.current;
    if (!root) return undefined;

    const nodes = root.querySelectorAll(
      '.section-head, .feature-card, .project-card, .feature-item, .contact-card, .github-card, .text-card, .hero-card, .hero-meta, .cta-row, .stat-box'
    );
    if (!nodes.length) return undefined;

    nodes.forEach((node) => node.classList.add('reveal'));

    if (!('IntersectionObserver' in window)) {
      nodes.forEach((node) => node.classList.add('is-visible'));
      return undefined;
    }

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.12, rootMargin: '0px 0px -30px 0px' }
    );

    nodes.forEach((node) => {
      const rect = node.getBoundingClientRect();
      if (rect.top < window.innerHeight && rect.bottom > 0) {
        node.classList.add('is-visible');
      } else {
        observer.observe(node);
      }
    });

    return () => observer.disconnect();
  }, []);

  useEffect(() => {
    const root = rootRef.current;
    if (!root) return undefined;

    const targets = root.querySelectorAll('.hero-card, .project-card, .feature-card, .github-card');
    const handlers = [];

    targets.forEach((target) => {
      const onMove = (event) => {
        const rect = target.getBoundingClientRect();
        const x = (event.clientX - rect.left) / rect.width;
        const y = (event.clientY - rect.top) / rect.height;
        const rotateY = (x - 0.5) * 8;
        const rotateX = (0.5 - y) * 8;
        target.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-4px)`;
      };
      const onLeave = () => {
        target.style.transform = '';
      };
      target.addEventListener('pointermove', onMove);
      target.addEventListener('pointerleave', onLeave);
      handlers.push(() => {
        target.removeEventListener('pointermove', onMove);
        target.removeEventListener('pointerleave', onLeave);
      });
    });

    return () => handlers.forEach((cleanup) => cleanup());
  }, []);

  useEffect(() => {
    const controller = new AbortController();
    const headers = {
      Accept: 'application/vnd.github+json',
      'X-GitHub-Api-Version': '2022-11-28',
    };

    fetch(`https://api.github.com/users/${GITHUB_USER}`, { headers, signal: controller.signal })
      .then((response) => {
        if (!response.ok) throw new Error('GitHub profile request failed');
        return response.json();
      })
      .then((data) => {
        setProfile({
          login: data.login || copy.github.defaultLogin,
          bio: data.bio || copy.github.defaultBio,
          repos: data.public_repos || 0,
          followers: data.followers || 0,
          following: data.following || 0,
        });
        if (data.avatar_url) {
          setAvatar(data.avatar_url);
        }
      })
      .catch(() => {
        setProfile({
          login: copy.github.defaultLogin,
          bio: copy.github.defaultBio,
          repos: '20+',
          followers: 0,
          following: 0,
        });
      });

    fetch(`https://api.github.com/users/${GITHUB_USER}/repos?sort=updated&per_page=100`, {
      headers,
      signal: controller.signal,
    })
      .then((response) => {
        if (!response.ok) throw new Error('GitHub repo request failed');
        return response.json();
      })
      .then((data) => {
        const cleaned = (Array.isArray(data) ? data : [])
          .filter(isSafeRepo)
          .sort((a, b) => (b.stargazers_count || 0) - (a.stargazers_count || 0))
          .slice(0, 6);
        setRepos(cleaned.length ? cleaned : copy.github.fallbackRepos);
      })
      .catch(() => {
        setRepos(copy.github.fallbackRepos);
      });

    return () => controller.abort();
  }, [copy.github]);

  const handleSubmit = (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const data = new FormData(form);
    data.set('id', form.dataset.formId || 'contact');

    setSubmitting(true);
    setStatus({ type: '', message: copy.contact.sending });

    fetch(form.action || '/api.php/form_container/submit', {
      method: 'POST',
      body: data,
      headers: {
        Accept: 'text/html,application/json',
      },
      credentials: 'same-origin',
    })
      .then(async (response) => {
        const text = await response.text();
        if (!response.ok) throw new Error(text || 'Request failed');
        return text;
      })
      .then(() => {
        setStatus({ type: 'success', message: copy.contact.success });
        form.reset();
      })
      .catch(() => {
        const values = new FormData(form);
        const name = String(values.get('form_contact[ed-f-1]') || '').trim() || 'There';
        const email = String(values.get('form_contact[ed-f-2]') || '').trim() || 'contact@otaboyev-sardorbek.uz';
        const subject = String(values.get('form_contact[ed-f-3]') || '').trim() || 'Project inquiry';
        const message = String(values.get('form_contact[ed-f-4]') || '').trim() || 'Hello';
        const mailto = `mailto:contact@otaboyev-sardorbek.uz?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(`Name: ${name}\nEmail: ${email}\n\n${message}`)}`;

        setStatus({ type: 'error', message: copy.contact.fallback });
        window.location.href = mailto;
      })
      .finally(() => setSubmitting(false));
  };

  const languages = ['uz', 'ru', 'en'];

  return (
    <div ref={rootRef}>
      <header className="site-header" ref={headerRef}>
        <div className="container navbar">
          <a className="brand" href="#top" aria-label={copy.a11y.home}>
            <span className="brand-mark">S</span>
            <span>otaboyev-sardorbek.uz</span>
          </a>

          <nav className={`nav-links${menuOpen ? ' open' : ''}`} aria-label={copy.a11y.nav}>
            {copy.nav.map((item, index) => (
              <a
                key={item.href}
                className={index === 0 ? 'active' : undefined}
                href={item.href}
                onClick={() => setMenuOpen(false)}
              >
                {item.label}
              </a>
            ))}
          </nav>

          <div className="lang-switcher" aria-label={copy.a11y.language}>
            {languages.map((lang) => (
              <a
                key={lang}
                href={`/${lang}/`}
                className={lang === locale ? 'active' : undefined}
                aria-current={lang === locale ? 'page' : undefined}
              >
                {lang.toUpperCase()}
              </a>
            ))}
          </div>

          <button
            className="theme-toggle"
            type="button"
            aria-label={copy.a11y.theme}
            onClick={() => setTheme(theme === 'light' ? 'dark' : 'light')}
          >
            <span className="theme-toggle-icon">☀️</span>
          </button>

          <button
            className="mobile-toggle"
            type="button"
            aria-label={copy.a11y.menu}
            aria-expanded={menuOpen}
            onClick={() => setMenuOpen((open) => !open)}
          >
            {copy.a11y.menu}
          </button>
        </div>
      </header>

      <main id="top">
        <section className="hero">
          <div className="container hero-grid">
            <div>
              <span className="kicker">{copy.hero.kicker}</span>
              <h1>
                {copy.hero.titleBefore}
                <span className="gradient">{copy.hero.titleHighlight}</span>
                {copy.hero.titleAfter}
              </h1>
              <p className="subtitle">{copy.hero.subtitle}</p>

              <div className="cta-row">
                <a className="btn btn-primary" href="#contact">
                  {copy.hero.primaryCta}
                </a>
                <a className="btn btn-secondary" href="#projects">
                  {copy.hero.secondaryCta}
                </a>
              </div>

              <div className="hero-meta">
                <span className="meta-pill">Python</span>
                <span className="meta-pill">Go</span>
                <span className="meta-pill">Docker</span>
                <span className="meta-pill">FastAPI</span>
              </div>
            </div>

            <div className="hero-card">
              <div className="profile-panel">
                <div className={`avatar${avatar ? ' has-image' : ''}`}>
                  {avatar ? <img src={avatar} alt={`${profile.login} avatar`} /> : null}
                  <div className="initials">SO</div>
                </div>
                <div className="profile-stats">
                  {copy.hero.stats.map((stat) => (
                    <div className="stat-box" key={stat.label}>
                      <Counter target={stat.target} suffix={stat.suffix} />
                      <span>{stat.label}</span>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>
        </section>

        <section id="about" className="section">
          <div className="container">
            <div className="section-head">
              <div>
                <div className="section-tag">{copy.about.tag}</div>
                <h2>{copy.about.title}</h2>
              </div>
            </div>

            <div className="about-grid">
              <div className="text-card">
                <p>{copy.about.text}</p>
              </div>

              <div className="feature-list">
                {copy.about.items.map((item) => (
                  <div className="feature-item" key={item.title}>
                    <div className="icon">{item.icon}</div>
                    <div>
                      <h3>{item.title}</h3>
                      <p>{item.text}</p>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </section>

        <section id="services" className="section">
          <div className="container">
            <div className="section-head">
              <div>
                <div className="section-tag">{copy.services.tag}</div>
                <h2>{copy.services.title}</h2>
              </div>
            </div>

            <div className="services-grid">
              {copy.services.items.map((item) => (
                <article className="feature-card" key={item.title}>
                  <h3>{item.title}</h3>
                  <p>{item.text}</p>
                </article>
              ))}
            </div>
          </div>
        </section>

        <section id="projects" className="section">
          <div className="container">
            <div className="section-head">
              <div>
                <div className="section-tag">{copy.projects.tag}</div>
                <h2>{copy.projects.title}</h2>
              </div>
              <a className="btn btn-secondary" href={`/${locale}/projects/`}>
                {copy.projects.allProjects}
              </a>
            </div>

            <div className="project-filters" aria-label={copy.projects.filtersLabel}>
              {copy.projects.filters.map((item) => (
                <button
                  key={item.id}
                  className={`filter-btn${filter === item.id ? ' active' : ''}`}
                  type="button"
                  onClick={() => setFilter(item.id)}
                >
                  {item.label}
                </button>
              ))}
            </div>

            <div className="projects-grid">
              {projects.map((item) => (
                <article
                  className="project-card"
                  data-category={item.category}
                  key={item.slug}
                  style={{ display: filter === 'all' || item.category === filter ? undefined : 'none' }}
                >
                  <span className="project-tag">{item.tag}</span>
                  <h3>{item.title}</h3>
                  <p>{item.text}</p>
                  <div className="tech-list">
                    {item.tech.map((tech) => (
                      <span key={tech}>{tech}</span>
                    ))}
                  </div>
                  <div className="project-actions">
                    <a className="detail-link" href={item.detailUrl || `/${locale}/projects/${item.slug}/`}>
                      {copy.projects.detailLabel}
                    </a>
                  </div>
                </article>
              ))}
            </div>
          </div>
        </section>

        <section id="github" className="section">
          <div className="container">
            <div className="github-card">
              <div className="section-head">
                <div>
                  <div className="section-tag">{copy.github.tag}</div>
                  <h2>{copy.github.title}</h2>
                </div>
              </div>

              <div className="github-main">
                <div className="github-pill">
                  <p className="github-label">{copy.github.loginLabel}</p>
                  <h3 className="github-login">{profile.login}</h3>
                  <p className="github-bio">{profile.bio}</p>
                </div>
                <div className="mini-stat">
                  <strong>{profile.repos}</strong>
                  <span>{copy.github.repos}</span>
                </div>
                <div className="mini-stat">
                  <strong>{profile.followers}</strong>
                  <span>{copy.github.followers}</span>
                </div>
                <div className="mini-stat">
                  <strong>{profile.following}</strong>
                  <span>{copy.github.following}</span>
                </div>
              </div>

              <div className="repo-list">
                {repos.map((repo) => (
                  <div className="repo-item" key={repo.name}>
                    <strong>{formatRepoLabel(repo.name)}</strong>
                    <div className="repo-meta">
                      ⭐ {repo.stargazers_count || 0} • {repo.language || 'Code'}
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </section>

        <section id="contact" className="section">
          <div className="container">
            <div className="section-head">
              <div>
                <div className="section-tag">{copy.contact.tag}</div>
                <h2>{copy.contact.title}</h2>
              </div>
            </div>

            <div className="contact-grid">
              <div className="contact-card">
                <h3>{copy.contact.cardTitle}</h3>
                <p>{copy.contact.cardText}</p>

                <form
                  id="contact-form"
                  className="contact-form"
                  data-form-id="contact"
                  action="/api.php/form_container/submit"
                  onSubmit={handleSubmit}
                >
                  <input type="hidden" name="id" value="contact" />
                  <div className="form-row">
                    <label>
                      {copy.contact.name}
                      <input
                        id="name"
                        type="text"
                        name="form_contact[ed-f-1]"
                        placeholder={copy.contact.namePlaceholder}
                        required
                      />
                    </label>
                    <label>
                      {copy.contact.email}
                      <input id="email" type="email" name="form_contact[ed-f-2]" placeholder="you@example.com" required />
                    </label>
                  </div>

                  <label>
                    {copy.contact.subject}
                    <input
                      id="subject"
                      type="text"
                      name="form_contact[ed-f-3]"
                      placeholder={copy.contact.subjectPlaceholder}
                      required
                    />
                  </label>

                  <label>
                    {copy.contact.message}
                    <textarea
                      id="message"
                      name="form_contact[ed-f-4]"
                      placeholder={copy.contact.messagePlaceholder}
                      required
                    />
                  </label>

                  <button type="submit" className="btn btn-primary" disabled={submitting}>
                    {submitting ? copy.contact.sending : copy.contact.submit}
                  </button>
                  <div className={`form-status ${status.type}`} aria-live="polite">
                    {status.message}
                  </div>
                </form>

                <div className="cv-actions">
                  <a
                    className="btn btn-secondary cv-button"
                    href={`/files/sardorbek-otaboyev-cv-${locale}.pdf`}
                    download
                  >
                    {copy.contact.cvPdf}
                  </a>
                  <a className="cv-alt-link" href={`/files/sardorbek-otaboyev-cv-${locale}.txt`} download>
                    {copy.contact.cvTxt}
                  </a>
                </div>

                <div className="contact-links">
                  <a className="contact-link" href={`mailto:${siteProfile.email}`}>
                    <strong>Email:</strong> {siteProfile.email}
                  </a>
                  <a
                    className="contact-link"
                    href={siteProfile.github}
                    target="_blank"
                    rel="noreferrer"
                  >
                    <strong>GitHub:</strong> {siteProfile.github.replace(/^https?:\/\//, '')}
                  </a>
                  <a className="contact-link" href={siteProfile.telegram} target="_blank" rel="noreferrer">
                    <strong>Telegram:</strong> {siteProfile.telegram_handle}
                  </a>
                </div>
              </div>

              <div className="contact-card">
                <h3>{copy.contact.quickTitle}</h3>
                <p>{siteProfile.quick_text || copy.contact.quickText}</p>
              </div>
            </div>
          </div>
        </section>
      </main>

      <footer className="site-footer">
        <div className="container footer-inner">
          <span>
            © {year} {copy.footer.name}
          </span>
          <span>{copy.footer.note}</span>
        </div>
      </footer>
    </div>
  );
}
