'use client';

import { useEffect, useState } from 'react';
import { adminApi, adminUpload, errorMessage } from './api';

const LOCALES = [
  { id: 'uz', label: 'O‘zbekcha' },
  { id: 'en', label: 'English' },
  { id: 'ru', label: 'Русский' },
];

function emptyTranslations() {
  return {
    uz: { role: '', bio: '', quick_text: '', location: '' },
    en: { role: '', bio: '', quick_text: '', location: '' },
    ru: { role: '', bio: '', quick_text: '', location: '' },
  };
}

function normalize(profile) {
  const translations = emptyTranslations();
  LOCALES.forEach(({ id }) => {
    if (profile.translations && profile.translations[id]) {
      translations[id] = { ...translations[id], ...profile.translations[id] };
    }
  });
  return {
    name: profile.name || '',
    email: profile.email || '',
    github: profile.github || '',
    telegram: profile.telegram || '',
    telegram_handle: profile.telegram_handle || '',
    avatar_url: profile.avatar_url || '',
    translations,
  };
}

export default function ProfileEditor({ profile, onSaved }) {
  const [form, setForm] = useState(() => normalize(profile));
  const [locale, setLocale] = useState('uz');
  const [saving, setSaving] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState('');
  const [saved, setSaved] = useState(false);

  useEffect(() => {
    setForm(normalize(profile));
  }, [profile]);

  function setField(key, value) {
    setForm((current) => ({ ...current, [key]: value }));
    setSaved(false);
  }

  function setTranslation(key, value) {
    setForm((current) => ({
      ...current,
      translations: {
        ...current.translations,
        [locale]: { ...current.translations[locale], [key]: value },
      },
    }));
    setSaved(false);
  }

  async function uploadAvatar(event) {
    const file = event.target.files && event.target.files[0];
    event.target.value = '';
    if (!file) return;
    setUploading(true);
    setError('');
    try {
      const data = await adminUpload(file);
      if (data && data.url) {
        setField('avatar_url', data.url);
        setSaved(false);
      }
    } catch (failure) {
      setError(errorMessage(failure));
    } finally {
      setUploading(false);
    }
  }

  async function submit(event) {    event.preventDefault();
    setSaving(true);
    setError('');
    setSaved(false);
    try {
      const updated = await adminApi('/profile', { method: 'PUT', body: form });
      setForm(normalize(updated));
      onSaved(updated);
      setSaved(true);
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
          <h2>Asosiy ma’lumotlar</h2>
          <p>Bu maydonlar barcha tillarda bir xil ishlatiladi.</p>
        </div>
        <span className="admin-required-note">3 til majburiy</span>
      </div>

      <div className="admin-form-grid">
        <label>
          <span>Ism</span>
          <input value={form.name} onChange={(event) => setField('name', event.target.value)} maxLength={120} required />
        </label>
        <label>
          <span>Email</span>
          <input type="email" value={form.email} onChange={(event) => setField('email', event.target.value)} maxLength={190} required />
        </label>
        <label>
          <span>GitHub URL</span>
          <input type="url" value={form.github} onChange={(event) => setField('github', event.target.value)} maxLength={190} placeholder="https://github.com/..." />
        </label>
        <label>
          <span>Telegram URL</span>
          <input type="url" value={form.telegram} onChange={(event) => setField('telegram', event.target.value)} maxLength={190} placeholder="https://t.me/..." />
        </label>
        <label>
          <span>Telegram username</span>
          <input value={form.telegram_handle} onChange={(event) => setField('telegram_handle', event.target.value)} maxLength={100} placeholder="@username" />
        </label>
        <label>
          <span>Avatar URL</span>
          <input type="url" value={form.avatar_url} onChange={(event) => setField('avatar_url', event.target.value)} maxLength={255} placeholder="https://..." />
          <span className="admin-file-row">
            <input type="file" accept="image/jpeg,image/png,image/webp" onChange={uploadAvatar} disabled={uploading} />
            <small className="admin-form-note">{uploading ? 'Yuklanmoqda...' : 'JPEG/PNG/WebP, 2 MB gacha'}</small>
          </span>
        </label>
      </div>

      <div className="admin-subhead">
        <div>
          <h2>Tillar</h2>
          <p>Har bir til uchun matnlarni alohida to‘ldiring.</p>
        </div>
        <div className="admin-locale-tabs" role="tablist" aria-label="Til tanlash">
          {LOCALES.map((item) => (
            <button
              key={item.id}
              type="button"
              className={locale === item.id ? 'active' : ''}
              onClick={() => setLocale(item.id)}
            >
              {item.id.toUpperCase()}
              <small>{item.label}</small>
            </button>
          ))}
        </div>
      </div>

      <div className="admin-form-grid">
        <label>
          <span>Rol / lavozim</span>
          <input value={translation.role} onChange={(event) => setTranslation('role', event.target.value)} maxLength={160} required />
        </label>
        <label>
          <span>Joylashuv</span>
          <input value={translation.location} onChange={(event) => setTranslation('location', event.target.value)} maxLength={120} required />
        </label>
        <label className="admin-span-2">
          <span>Qisqa matn (contact / card)</span>
          <input value={translation.quick_text} onChange={(event) => setTranslation('quick_text', event.target.value)} maxLength={255} required />
        </label>
        <label className="admin-span-2">
          <span>Bio</span>
          <textarea value={translation.bio} onChange={(event) => setTranslation('bio', event.target.value)} rows={6} maxLength={5000} required />
        </label>
      </div>

      {error && <div className="admin-alert admin-alert-error">{error}</div>}
      {saved && <div className="admin-alert admin-alert-ok">Profil saqlandi.</div>}

      <div className="admin-form-actions">
        <button className="admin-btn admin-btn-primary" type="submit" disabled={saving}>
          {saving ? 'Saqlanmoqda...' : 'Profilni saqlash'}
        </button>
      </div>
    </form>
  );
}
