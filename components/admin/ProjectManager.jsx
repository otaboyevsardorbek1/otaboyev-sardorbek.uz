'use client';

import { useState } from 'react';
import { adminApi, errorMessage } from './api';

const LOCALES = [
  { id: 'uz', label: 'O‘zbekcha' },
  { id: 'en', label: 'English' },
  { id: 'ru', label: 'Русский' },
];

function emptyTranslations() {
  return {
    uz: { tag: '', title: '', summary: '', description: '', tech: '', results: '', stack_notes: '' },
    en: { tag: '', title: '', summary: '', description: '', tech: '', results: '', stack_notes: '' },
    ru: { tag: '', title: '', summary: '', description: '', tech: '', results: '', stack_notes: '' },
  };
}

function normalize(project) {
  const translations = emptyTranslations();
  LOCALES.forEach(({ id }) => {
    const row = project && project.translations ? project.translations[id] : null;
    if (row) {
      translations[id] = {
        tag: row.tag || '',
        title: row.title || '',
        summary: row.summary || '',
        description: row.description || '',
        tech: Array.isArray(row.tech) ? row.tech.join(', ') : (row.tech || ''),
        results: Array.isArray(row.results) ? row.results.join('\n') : (row.results || ''),
        stack_notes: Array.isArray(row.stack_notes) ? row.stack_notes.join('\n') : (row.stack_notes || ''),
      };
    }
  });

  return {
    slug: project ? project.slug || '' : '',
    category: project ? project.category || '' : '',
    repo_url: project ? project.repo_url || '' : '',
    demo_url: project ? project.demo_url || '' : '',
    sort_order: project ? Number(project.sort_order || 0) : 0,
    is_active: project ? Boolean(project.is_active) : true,
    translations,
  };
}

function ProjectEditor({ project, onCancel, onSaved }) {
  const [form, setForm] = useState(() => normalize(project));
  const [locale, setLocale] = useState('uz');
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const isNew = !project || !project.id;

  function setField(key, value) {
    setForm((current) => ({ ...current, [key]: value }));
  }

  function setTranslation(key, value) {
    setForm((current) => ({
      ...current,
      translations: {
        ...current.translations,
        [locale]: { ...current.translations[locale], [key]: value },
      },
    }));
  }

  async function submit(event) {
    event.preventDefault();
    setSaving(true);
    setError('');
    const payload = {
      ...form,
      sort_order: Number(form.sort_order) || 0,
      is_active: form.is_active ? 1 : 0,
      translations: Object.fromEntries(
        LOCALES.map(({ id }) => [
          id,
          {
            ...form.translations[id],
            tech: form.translations[id].tech.split(',').map((item) => item.trim()).filter(Boolean),
            results: form.translations[id].results.split('\n').map((item) => item.trim()).filter(Boolean),
            stack_notes: form.translations[id].stack_notes.split('\n').map((item) => item.trim()).filter(Boolean),
          },
        ])
      ),
    };

    try {
      const saved = isNew
        ? await adminApi('/projects', { method: 'POST', body: payload })
        : await adminApi(`/projects/${project.id}`, { method: 'PUT', body: payload });
      onSaved(saved);
    } catch (failure) {
      setError(errorMessage(failure));
    } finally {
      setSaving(false);
    }
  }

  const translation = form.translations[locale];

  return (
    <form className="admin-panel" onSubmit={submit}>
      <div className="admin-panel-head">
        <div>
          <h2>{isNew ? 'Yangi loyiha' : `Tahrirlash: ${project.slug}`}</h2>
          <p>Slug, kategoriya va uch tildagi kontentni to‘ldiring.</p>
        </div>
        <button className="admin-btn admin-btn-ghost" type="button" onClick={onCancel}>Bekor qilish</button>
      </div>

      <div className="admin-form-grid">
        <label>
          <span>Slug</span>
          <input value={form.slug} onChange={(event) => setField('slug', event.target.value.toLowerCase())} maxLength={80} placeholder="my-project" required />
        </label>
        <label>
          <span>Kategoriya</span>
          <input value={form.category} onChange={(event) => setField('category', event.target.value.toLowerCase())} maxLength={30} placeholder="api" required />
        </label>
        <label>
          <span>Repo URL</span>
          <input type="url" value={form.repo_url} onChange={(event) => setField('repo_url', event.target.value)} maxLength={255} placeholder="https://github.com/..." />
        </label>
        <label>
          <span>Demo URL</span>
          <input type="url" value={form.demo_url} onChange={(event) => setField('demo_url', event.target.value)} maxLength={255} placeholder="https://..." />
        </label>
        <label>
          <span>Tartib</span>
          <input type="number" value={form.sort_order} onChange={(event) => setField('sort_order', event.target.value)} min={-32768} max={32767} />
        </label>
        <label className="admin-checkbox">
          <input type="checkbox" checked={form.is_active} onChange={(event) => setField('is_active', event.target.checked)} />
          <span>Saytda ko‘rinadi</span>
        </label>
      </div>

      <div className="admin-subhead">
        <div>
          <h2>Tillar</h2>
          <p>Har bir til uchun alohida kontent.</p>
        </div>
        <div className="admin-locale-tabs" role="tablist" aria-label="Loyiha tili">
          {LOCALES.map((item) => (
            <button key={item.id} type="button" className={locale === item.id ? 'active' : ''} onClick={() => setLocale(item.id)}>
              {item.id.toUpperCase()}
              <small>{item.label}</small>
            </button>
          ))}
        </div>
      </div>

      <div className="admin-form-grid">
        <label>
          <span>Tag</span>
          <input value={translation.tag} onChange={(event) => setTranslation('tag', event.target.value)} maxLength={40} required />
        </label>
        <label>
          <span>Sarlavha</span>
          <input value={translation.title} onChange={(event) => setTranslation('title', event.target.value)} maxLength={160} required />
        </label>
        <label className="admin-span-2">
          <span>Qisqa tavsif</span>
          <textarea value={translation.summary} onChange={(event) => setTranslation('summary', event.target.value)} rows={3} maxLength={2000} required />
        </label>
        <label className="admin-span-2">
          <span>To‘liq tavsif</span>
          <textarea value={translation.description} onChange={(event) => setTranslation('description', event.target.value)} rows={6} maxLength={20000} />
          <small className="admin-form-note">Bu tavsif va quyidagi bo‘limlar loyiha detail sahifasida API orqali ko‘rinadi.</small>
        </label>
        <label className="admin-span-2">
          <span>Texnologiyalar (vergul bilan)</span>
          <input value={translation.tech} onChange={(event) => setTranslation('tech', event.target.value)} placeholder="React, Next.js, PHP" required />
        </label>
        <label className="admin-span-2">
          <span>Natijalar (har bir qatorda bittasi)</span>
          <textarea value={translation.results} onChange={(event) => setTranslation('results', event.target.value)} rows={4} placeholder={"Natija 1\nNatija 2"} />
        </label>
        <label className="admin-span-2">
          <span>Texnologiyalar tafsiloti (har bir qatorda bittasi)</span>
          <textarea value={translation.stack_notes} onChange={(event) => setTranslation('stack_notes', event.target.value)} rows={4} placeholder={"Frontend: React + Vite\nData: GitHub API"} />
        </label>
      </div>

      {error && <div className="admin-alert admin-alert-error">{error}</div>}

      <div className="admin-form-actions">
        <button className="admin-btn admin-btn-primary" type="submit" disabled={saving}>
          {saving ? 'Saqlanmoqda...' : isNew ? 'Loyiha qo‘shish' : 'Loyihani saqlash'}
        </button>
      </div>
    </form>
  );
}

export default function ProjectManager({ projects, onRefresh, notify }) {
  const [editing, setEditing] = useState(null);
  const [deleting, setDeleting] = useState(0);
  const [syncing, setSyncing] = useState(false);
  const [error, setError] = useState('');

  async function syncSitemap() {
    if (syncing) return;
    setSyncing(true);
    setError('');
    try {
      const result = await adminApi('/sitemap', { method: 'POST' });
      notify(`Sitemap yangilandi: ${result.urls} URL.`);
    } catch (failure) {
      setError(errorMessage(failure));
    } finally {
      setSyncing(false);
    }
  }

  async function remove(project) {
    if (deleting) return;
    if (!window.confirm(`"${project.slug}" loyihasini o‘chirasizmi? Bu amalni qaytarib bo‘lmaydi.`)) return;
    setDeleting(project.id);
    setError('');
    try {
      await adminApi(`/projects/${project.id}`, { method: 'DELETE' });
      await onRefresh();
      notify('Loyiha o‘chirildi.');
    } catch (failure) {
      setError(errorMessage(failure));
    } finally {
      setDeleting(0);
    }
  }

  if (editing) {
    return (
      <ProjectEditor
        key={editing === 'new' ? 'new' : editing.id}
        project={editing === 'new' ? null : editing}
        onCancel={() => setEditing(null)}
        onSaved={async () => {
          await onRefresh();
          setEditing(null);
          notify('Loyiha saqlandi.');
        }}
      />
    );
  }

  return (
    <section className="admin-panel">
      <div className="admin-panel-head">
        <div>
          <h2>Loyihalar</h2>
          <p>{projects.length} ta loyiha. O‘zgarishlar saytda API orqali ko‘rinadi.</p>
        </div>
        <div className="admin-row-actions">
          <button className="admin-btn admin-btn-ghost" type="button" onClick={syncSitemap} disabled={syncing}>
            {syncing ? 'Yangilanmoqda...' : 'Sitemapni yangilash'}
          </button>
          <button className="admin-btn admin-btn-primary" type="button" onClick={() => setEditing('new')}>+ Yangi loyiha</button>
        </div>
      </div>

      {error && <div className="admin-alert admin-alert-error">{error}</div>}

      {projects.length === 0 ? (
        <div className="admin-empty">Hozircha loyiha yo‘q. Birinchi loyihani qo‘shing.</div>
      ) : (
        <div className="admin-table-wrap">
          <table className="admin-table">
            <thead>
              <tr>
                <th>Loyiha</th>
                <th>Kategoriya</th>
                <th>Tartib</th>
                <th>Holat</th>
                <th aria-label="Amallar" />
              </tr>
            </thead>
            <tbody>
              {projects.map((project) => (
                <tr key={project.id}>
                  <td>
                    <strong>{project.translations?.uz?.title || project.slug}</strong>
                    <span>/{project.slug}/</span>
                  </td>
                  <td><code>{project.category}</code></td>
                  <td>{project.sort_order}</td>
                  <td>
                    <span className={`admin-state ${project.is_active ? 'on' : 'off'}`}>
                      {project.is_active ? 'Faol' : 'Yashirin'}
                    </span>
                  </td>
                  <td className="admin-row-actions">
                    <button className="admin-btn admin-btn-small" type="button" onClick={() => setEditing(project)}>Tahrirlash</button>
                    <button className="admin-btn admin-btn-small admin-btn-danger" type="button" disabled={deleting === project.id} onClick={() => remove(project)}>
                      {deleting === project.id ? '...' : 'O‘chirish'}
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  );
}
