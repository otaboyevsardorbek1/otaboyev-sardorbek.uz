'use client';

import { useEffect, useState } from 'react';
import { LOCALES } from '../content/locales';

function paragraphs(value) {
  return String(value || '')
    .split(/\n{2,}/)
    .map((item) => item.trim())
    .filter(Boolean);
}

function normalizeProject(raw, fallback) {
  if (!raw) return fallback || null;
  const base = fallback || {};
  return {
    ...base,
    ...raw,
    tech: Array.isArray(raw.tech) ? raw.tech : (base.tech || []),
    results: Array.isArray(raw.results) ? raw.results : (base.results || []),
    stack_notes: Array.isArray(raw.stack_notes) ? raw.stack_notes : (base.stack_notes || []),
  };
}

function DetailHeader({ locale, labels, slug }) {
  const target = slug ? `/${locale}/projects/${encodeURIComponent(slug)}/` : `/${locale}/projects/`;

  return (
    <header className="site-header">
      <div className="container navbar">
        <a className="brand" href={`/${locale}/`} aria-label={labels.home}>
          <span className="brand-mark">S</span>
          <span>otaboyev-sardorbek.uz</span>
        </a>
        <div className="lang-switcher" aria-label={labels.language}>
          {LOCALES.map((item) => (
            <a
              key={item}
              href={slug ? `/${item}/projects/${encodeURIComponent(slug)}/` : target}
              className={item === locale ? 'active' : ''}
              aria-current={item === locale ? 'page' : undefined}
            >
              {item.toUpperCase()}
            </a>
          ))}
        </div>
      </div>
    </header>
  );
}

function DetailFooter() {
  return (
    <footer className="site-footer">
      <div className="container footer-inner">
        <span>© <span suppressHydrationWarning>{new Date().getFullYear()}</span> Sardorbek Otaboyev</span>
      </div>
    </footer>
  );
}

export default function ProjectDetail({ locale, slug = '', fallback = null, labels }) {
  const [project, setProject] = useState(() => normalizeProject(fallback, fallback));
  const [resolvedSlug, setResolvedSlug] = useState(slug);
  const [status, setStatus] = useState(fallback ? 'ready' : 'loading');

  useEffect(() => {
    let active = true;
    const controller = new AbortController();
    setProject(normalizeProject(fallback, fallback));
    setStatus(fallback ? 'ready' : 'loading');

    function resolveSlug() {
      if (slug) return slug;
      const params = new URLSearchParams(window.location.search);
      const querySlug = params.get('slug');
      if (querySlug) return querySlug;
      const parts = window.location.pathname.split('/').filter(Boolean);
      return parts[0] === locale && parts[1] === 'projects' ? parts[2] || '' : '';
    }

    const nextSlug = resolveSlug();
    setResolvedSlug(nextSlug);

    if (!nextSlug) {
      setStatus('notfound');
      return () => controller.abort();
    }

    fetch(`/api.php/projects/${encodeURIComponent(nextSlug)}?lang=${encodeURIComponent(locale)}`, {
      headers: { Accept: 'application/json' },
      cache: 'no-store',
      signal: controller.signal,
    })
      .then((response) => response.json().then((body) => ({ response, body })).catch(() => ({ response, body: null })))
      .then(({ response, body }) => {
        if (!active) return;
        if (response.status === 404) {
          setProject(null);
          setStatus('notfound');
          return;
        }
        if (!response.ok || !body || body.status !== 'ok' || !body.data) {
          if (!fallback) setStatus('error');
          return;
        }
        const nextProject = normalizeProject(body.data, fallback);
        setProject(nextProject);
        setStatus('ready');
        if (typeof document !== 'undefined') {
          document.title = `${nextProject.title} | Sardorbek Otaboyev`;
          const description = document.querySelector('meta[name="description"]');
          if (description) description.setAttribute('content', nextProject.summary || '');
          const canonical = document.querySelector('link[rel="canonical"]');
          if (canonical) canonical.setAttribute('href', `${window.location.origin}${window.location.pathname}`);
        }
      })
      .catch(() => {
        if (active && !fallback) setStatus('error');
      });

    return () => {
      active = false;
      controller.abort();
    };
  }, [fallback, locale, slug]);

  if (!project) {
    const message = status === 'notfound'
      ? labels.notFound
      : status === 'error'
        ? labels.unavailable
        : labels.loading;

    return (
      <>
        <DetailHeader locale={locale} labels={labels} slug={resolvedSlug} />
        <main className="project-detail-page">
          <section className="section">
            <div className="container narrow detail-content">
              <h1>{message}</h1>
              {(status === 'notfound' || status === 'error') && (
                <a className="btn btn-primary" href={`/${locale}/projects/`}>{labels.back}</a>
              )}
            </div>
          </section>
        </main>
        <DetailFooter />
      </>
    );
  }

  const descriptionParts = paragraphs(project.description);

  return (
    <>
      <DetailHeader locale={locale} labels={labels} slug={resolvedSlug || slug} />
      <main className="project-detail-page">
        <section className="section detail-hero">
          <div className="container narrow">
            <span className="section-tag">{project.tag}</span>
            <h1>{project.title}</h1>
            <p>{project.summary}</p>
            {project.tech && project.tech.length > 0 && (
              <div className="tech-list">
                {project.tech.map((item) => <span key={item}>{item}</span>)}
              </div>
            )}
          </div>
        </section>

        <section className="section">
          <div className="container narrow detail-content">
            {descriptionParts.length > 0 && (
              <>
                <h2>{labels.about}</h2>
                {descriptionParts.map((item, index) => (
                  <p key={`description-${index}`}>{item}</p>
                ))}
              </>
            )}

            {project.results && project.results.length > 0 && (
              <>
                <h2>{labels.results}</h2>
                <ul>
                  {project.results.map((item) => <li key={item}>{item}</li>)}
                </ul>
              </>
            )}

            {project.stack_notes && project.stack_notes.length > 0 && (
              <>
                <h2>{labels.stack}</h2>
                <ul>
                  {project.stack_notes.map((item) => <li key={item}>{item}</li>)}
                </ul>
              </>
            )}

            <a className="btn btn-primary" href={`/${locale}/`}>{labels.back}</a>
          </div>
        </section>
      </main>
      <DetailFooter />
    </>
  );
}
