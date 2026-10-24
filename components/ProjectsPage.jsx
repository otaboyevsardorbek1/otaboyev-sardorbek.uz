'use client';

import { useEffect, useState } from 'react';

const THEME_KEY = 'portfolio-theme';
const LANGUAGES = ['uz', 'ru', 'en'];

export default function ProjectsPage({ locale, copy }) {
  const [theme, setTheme] = useState('dark');
  const [menuOpen, setMenuOpen] = useState(false);
  const [items, setItems] = useState(copy.projects.items);

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
    const controller = new AbortController();

    fetch(`/api.php/projects?lang=${locale}`, {
      headers: { Accept: 'application/json' },
      signal: controller.signal,
      credentials: 'same-origin',
    })
      .then((response) => (response.ok ? response.json() : null))
      .then((payload) => {
        if (controller.signal.aborted || !payload || payload.status !== 'ok' || !Array.isArray(payload.data)) {
          return;
        }

        const nextItems = payload.data
          .filter((item) => item && item.slug && item.title)
          .map((item) => ({
            slug: item.slug,
            category: item.category,
            tag: item.tag,
            title: item.title,
            text: item.summary,
            tech: Array.isArray(item.tech) ? item.tech : [],
          }));

        if (nextItems.length) {
          setItems(nextItems);
        }
      })
      .catch(() => {
        // Keep the static fallback when the API is unavailable.
      });

    return () => controller.abort();
  }, [locale]);

  const year = new Date().getFullYear();

  return (
    <div>
      <header className="site-header">
        <div className="container navbar">
          <a className="brand" href={`/${locale}/`} aria-label={copy.a11y.home}>
            <span className="brand-mark">S</span>
            <span>otaboyev-sardorbek.uz</span>
          </a>

          <nav className={`nav-links${menuOpen ? ' open' : ''}`} aria-label={copy.a11y.nav}>
            <a href={`/${locale}/`} onClick={() => setMenuOpen(false)}>
              {copy.nav[0].label}
            </a>
            <a
              className="active"
              href={`/${locale}/projects/`}
              aria-current="page"
              onClick={() => setMenuOpen(false)}
            >
              {copy.nav[3].label}
            </a>
            <a href={`/${locale}/#contact`} onClick={() => setMenuOpen(false)}>
              {copy.nav[4].label}
            </a>
          </nav>

          <div className="lang-switcher" aria-label={copy.a11y.language}>
            {LANGUAGES.map((lang) => (
              <a
                key={lang}
                href={`/${lang}/projects/`}
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

      <main>
        <section className="section">
          <div className="container">
            <div className="section-head">
              <div>
                <div className="section-tag">{copy.projects.tag}</div>
                <h1>{copy.projects.title}</h1>
                <p className="subtitle">{copy.projects.listIntro}</p>
              </div>
              <a className="btn btn-secondary" href={`/${locale}/`}>
                {copy.projects.backHome}
              </a>
            </div>

            <div className="projects-grid">
              {items.map((item) => (
                <article className="project-card" data-category={item.category} key={item.slug}>
                  <span className="project-tag">{item.tag}</span>
                  <h2>{item.title}</h2>
                  <p>{item.text}</p>
                  <div className="tech-list">
                    {item.tech.map((tech) => (
                      <span key={tech}>{tech}</span>
                    ))}
                  </div>
                  <div className="project-actions">
                    <a className="detail-link" href={`/${locale}/projects/${item.slug}/`}>
                      {copy.projects.detailLabel}
                    </a>
                  </div>
                </article>
              ))}
            </div>
          </div>
        </section>
      </main>

      <footer className="site-footer">
        <div className="container footer-inner">
          <span>
            © {year} {copy.footer.name}
          </span>
          <span className="footer-links">
            <span>{copy.footer.note}</span>
            <a href={`/${locale}/privacy/`}>{copy.footer.privacy}</a>
          </span>
        </div>
      </footer>
    </div>
  );
}
