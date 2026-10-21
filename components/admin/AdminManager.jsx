'use client';

import { useEffect, useState } from 'react';
import { adminApi, errorMessage } from './api';

function AdminEditor({ admin, onCancel, onSaved }) {
  const [form, setForm] = useState(() => ({
    telegram_id: admin ? String(admin.telegram_id || '') : '',
    email: admin ? admin.email || '' : '',
    display_name: admin ? admin.display_name || '' : '',
    is_active: admin ? Boolean(admin.is_active) : true,
  }));
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const isNew = !admin || !admin.id;

  function setField(key, value) {
    setForm((current) => ({ ...current, [key]: value }));
  }

  async function submit(event) {
    event.preventDefault();
    setSaving(true);
    setError('');
    try {
      const payload = {
        ...form,
        is_active: form.is_active ? 1 : 0,
      };
      const saved = isNew
        ? await adminApi('/admins', { method: 'POST', body: payload })
        : await adminApi(`/admins/${admin.id}`, { method: 'PUT', body: payload });
      onSaved(saved);
    } catch (failure) {
      setError(errorMessage(failure));
    } finally {
      setSaving(false);
    }
  }

  return (
    <form className="admin-panel" onSubmit={submit}>
      <div className="admin-panel-head">
        <div>
          <h2>{isNew ? 'Yangi admin' : `Tahrirlash: ${admin.telegram_id}`}</h2>
          <p>Telegram ID va OTP email manzilini kiriting. Admin Telegram orqali kirganda shu emailga kod yuboriladi.</p>
        </div>
        <button className="admin-btn admin-btn-ghost" type="button" onClick={onCancel}>Bekor qilish</button>
      </div>

      <div className="admin-form-grid">
        <label>
          <span>Telegram ID</span>
          <input
            value={form.telegram_id}
            onChange={(event) => setField('telegram_id', event.target.value.replace(/[^0-9]/g, ''))}
            inputMode="numeric"
            maxLength={19}
            placeholder="123456789"
            required
          />
          <small className="admin-form-note">@userinfobot yoki Telegram profil IDsi.</small>
        </label>
        <label>
          <span>Email</span>
          <input
            type="email"
            value={form.email}
            onChange={(event) => setField('email', event.target.value)}
            maxLength={190}
            placeholder="admin@example.com"
            required
          />
        </label>
        <label>
          <span>Ko‘rsatiladigan ism</span>
          <input
            value={form.display_name}
            onChange={(event) => setField('display_name', event.target.value)}
            maxLength={120}
            placeholder="Sardorbek"
          />
        </label>
        <label className="admin-checkbox">
          <input type="checkbox" checked={form.is_active} onChange={(event) => setField('is_active', event.target.checked)} />
          <span>Faol</span>
        </label>
      </div>

      {error && <div className="admin-alert admin-alert-error">{error}</div>}

      <div className="admin-form-actions">
        <button className="admin-btn admin-btn-primary" type="submit" disabled={saving}>
          {saving ? 'Saqlanmoqda...' : isNew ? 'Admin qo‘shish' : 'Adminni saqlash'}
        </button>
      </div>
    </form>
  );
}

export default function AdminManager({ currentAdminId, notify }) {
  const [admins, setAdmins] = useState([]);
  const [editing, setEditing] = useState(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(0);
  const [error, setError] = useState('');

  async function load() {
    setLoading(true);
    setError('');
    try {
      const data = await adminApi('/admins');
      setAdmins(data || []);
    } catch (failure) {
      setError(errorMessage(failure));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    let active = true;
    async function boot() {
      try {
        const data = await adminApi('/admins');
        if (!active) return;
        setAdmins(data || []);
      } catch (failure) {
        if (!active) return;
        setError(errorMessage(failure));
      } finally {
        if (active) setLoading(false);
      }
    }
    boot();
    return () => {
      active = false;
    };
  }, []);

  async function remove(admin) {
    if (busy) return;
    if (!window.confirm(`${admin.telegram_id} adminini o‘chirasizmi? Bu amalni qaytarib bo‘lmaydi.`)) return;
    setBusy(admin.id);
    setError('');
    try {
      await adminApi(`/admins/${admin.id}`, { method: 'DELETE' });
      await load();
      notify('Admin o‘chirildi.');
    } catch (failure) {
      setError(errorMessage(failure));
    } finally {
      setBusy(0);
    }
  }

  if (editing) {
    return (
      <AdminEditor
        key={editing === 'new' ? 'new' : editing.id}
        admin={editing === 'new' ? null : editing}
        onCancel={() => setEditing(null)}
        onSaved={async () => {
          await load();
          setEditing(null);
          notify('Admin saqlandi.');
        }}
      />
    );
  }

  return (
    <section className="admin-panel">
      <div className="admin-panel-head">
        <div>
          <h2>Adminlar</h2>
          <p>{admins.length} ta admin. Faqat faol adminlar Telegram + OTP orqali kira oladi.</p>
        </div>
        <button className="admin-btn admin-btn-primary" type="button" onClick={() => setEditing('new')}>+ Yangi admin</button>
      </div>

      {error && <div className="admin-alert admin-alert-error">{error}</div>}

      {loading ? (
        <div className="admin-empty">Yuklanmoqda...</div>
      ) : admins.length === 0 ? (
        <div className="admin-empty">Admin yo‘q.</div>
      ) : (
        <div className="admin-table-wrap">
          <table className="admin-table">
            <thead>
              <tr>
                <th>Telegram ID</th>
                <th>Email</th>
                <th>Ism</th>
                <th>Holat</th>
                <th>Oxirgi kirish</th>
                <th aria-label="Amallar" />
              </tr>
            </thead>
            <tbody>
              {admins.map((admin) => (
                <tr key={admin.id}>
                  <td><code>{admin.telegram_id}</code></td>
                  <td>{admin.email}</td>
                  <td>{admin.display_name || '—'}</td>
                  <td>
                    <span className={`admin-state ${admin.is_active ? 'on' : 'off'}`}>
                      {admin.is_active ? 'Faol' : 'O‘chirilgan'}
                    </span>
                  </td>
                  <td>{admin.last_login_at || '—'}</td>
                  <td className="admin-row-actions">
                    <button className="admin-btn admin-btn-small" type="button" onClick={() => setEditing(admin)}>Tahrirlash</button>
                    <button
                      className="admin-btn admin-btn-small admin-btn-danger"
                      type="button"
                      disabled={busy === admin.id || Number(admin.id) === Number(currentAdminId)}
                      onClick={() => remove(admin)}
                    >
                      {busy === admin.id ? '...' : 'O‘chirish'}
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
