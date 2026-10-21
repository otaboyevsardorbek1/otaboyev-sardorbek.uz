'use client';

import { useEffect, useState } from 'react';
import { adminApi, errorMessage } from './api';

function formatDate(value) {
  if (!value) return '';
  const date = new Date(String(value).replace(' ', 'T'));
  if (Number.isNaN(date.getTime())) return String(value);
  return date.toLocaleString('uz-UZ', {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function senderName(message) {
  return message.name || message.email || 'Noma’lum yuboruvchi';
}

export default function MessageInbox({ notify, onUnreadChange }) {
  const [items, setItems] = useState([]);
  const [unread, setUnread] = useState(0);
  const [total, setTotal] = useState(0);
  const [selected, setSelected] = useState(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  async function load() {
    setLoading(true);
    setError('');
    try {
      const data = await adminApi('/messages');
      setItems(data.items || []);
      setUnread(data.unread || 0);
      setTotal(data.total || 0);
      if (onUnreadChange) onUnreadChange(data.unread || 0);
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
        const data = await adminApi('/messages');
        if (!active) return;
        setItems(data.items || []);
        setUnread(data.unread || 0);
        setTotal(data.total || 0);
        if (onUnreadChange) onUnreadChange(data.unread || 0);
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
  }, [onUnreadChange]);

  async function openMessage(item) {
    setError('');
    try {
      const message = await adminApi(`/messages/${item.id}`);
      setSelected(message);
      if (!message.is_read) {
        const updated = await adminApi(`/messages/${message.id}`, {
          method: 'PUT',
          body: { is_read: true },
        });
        setSelected(updated);
        setItems((current) => current.map((row) => (row.id === updated.id ? { ...row, is_read: true } : row)));
        const nextUnread = Math.max(0, unread - 1);
        setUnread(nextUnread);
        if (onUnreadChange) onUnreadChange(nextUnread);
      }
    } catch (failure) {
      setError(errorMessage(failure));
    }
  }

  async function toggleRead() {
    if (!selected || busy) return;
    setBusy(true);
    setError('');
    try {
      const updated = await adminApi(`/messages/${selected.id}`, {
        method: 'PUT',
        body: { is_read: !selected.is_read },
      });
      setSelected(updated);
      setItems((current) => current.map((row) => (row.id === updated.id ? { ...row, is_read: updated.is_read } : row)));
      const nextUnread = Math.max(0, unread + (updated.is_read ? -1 : 1));
      setUnread(nextUnread);
      if (onUnreadChange) onUnreadChange(nextUnread);
      notify(updated.is_read ? 'Xabar o‘qilgan deb belgilandi.' : 'Xabar o‘qilmagan deb belgilandi.');
    } catch (failure) {
      setError(errorMessage(failure));
    } finally {
      setBusy(false);
    }
  }

  async function removeMessage() {
    if (!selected || busy) return;
    if (!window.confirm('Bu xabarni o‘chirasizmi? Bu amalni qaytarib bo‘lmaydi.')) return;
    setBusy(true);
    setError('');
    try {
      await adminApi(`/messages/${selected.id}`, { method: 'DELETE' });
      const wasUnread = !selected.is_read;
      setSelected(null);
      setTotal((value) => Math.max(0, value - 1));
      if (wasUnread) {
        const nextUnread = Math.max(0, unread - 1);
        setUnread(nextUnread);
        if (onUnreadChange) onUnreadChange(nextUnread);
      }
      await load();
      notify('Xabar o‘chirildi.');
    } catch (failure) {
      setError(errorMessage(failure));
    } finally {
      setBusy(false);
    }
  }

  const payloadFields = selected && selected.payload && selected.payload.fields
    ? Object.entries(selected.payload.fields)
    : [];
  const attachments = selected && selected.payload && Array.isArray(selected.payload.attachments)
    ? selected.payload.attachments
    : [];

  return (
    <section className="admin-panel">
      <div className="admin-panel-head">
        <div>
          <h2>Xabarlar</h2>
          <p>{unread} ta o‘qilmagan, jami {total} ta xabar.</p>
        </div>
        <button className="admin-btn admin-btn-ghost" type="button" onClick={load} disabled={loading}>
          {loading ? 'Yuklanmoqda...' : 'Yangilash'}
        </button>
      </div>

      {error && <div className="admin-alert admin-alert-error">{error}</div>}

      <div className="admin-inbox-layout">
        <div>
          {items.length === 0 ? (
            <div className="admin-empty">Hozircha xabar yo‘q.</div>
          ) : (
            <div className="admin-table-wrap">
              <table className="admin-table admin-message-table">
                <thead>
                  <tr>
                    <th>Holat</th>
                    <th>Yuboruvchi</th>
                    <th>Mavzu</th>
                    <th>Sana</th>
                  </tr>
                </thead>
                <tbody>
                  {items.map((message) => (
                    <tr
                      key={message.id}
                      className={`${message.is_read ? '' : 'unread'} ${selected && selected.id === message.id ? 'selected' : ''}`}
                      onClick={() => openMessage(message)}
                    >
                      <td>
                        <span className={`admin-message-dot ${message.is_read ? 'read' : 'unread'}`} aria-label={message.is_read ? 'O‘qilgan' : 'O‘qilmagan'} />
                      </td>
                      <td>
                        <strong>{senderName(message)}</strong>
                        <span>{message.email || 'Email yo‘q'}</span>
                      </td>
                      <td>
                        <strong>{message.subject || 'Mavzusiz'}</strong>
                        <span>{message.preview || ''}</span>
                      </td>
                      <td>{formatDate(message.created_at)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>

        {selected ? (
          <article className="admin-message-detail">
            <div className="admin-message-detail-head">
              <div>
                <span className="admin-kicker">{selected.locale ? selected.locale.toUpperCase() : 'CONTACT'}</span>
                <h3>{selected.subject || 'Mavzusiz xabar'}</h3>
                <p>{senderName(selected)} {selected.email ? `· ${selected.email}` : ''}</p>
                <small>{formatDate(selected.created_at)}</small>
              </div>
              <button className="admin-btn admin-btn-small admin-btn-ghost" type="button" onClick={() => setSelected(null)}>Yopish</button>
            </div>

            <div className="admin-message-body">
              {selected.message ? <p>{selected.message}</p> : <p className="admin-muted-text">Xabar matni yo‘q.</p>}
            </div>

            {payloadFields.length > 0 && (
              <dl className="admin-message-fields">
                {payloadFields.map(([label, value]) => (
                  <div key={label}>
                    <dt>{label}</dt>
                    <dd>{String(value)}</dd>
                  </div>
                ))}
              </dl>
            )}

            {attachments.length > 0 && (
              <div className="admin-message-attachments">
                <strong>Ilovalar</strong>
                <ul>
                  {attachments.map((attachment, index) => (
                    <li key={`${attachment.name}-${index}`}>
                      {attachment.name || 'Fayl'} <span>{attachment.type || ''}</span>
                    </li>
                  ))}
                </ul>
              </div>
            )}

            <div className="admin-form-actions admin-message-actions">
              <button className="admin-btn admin-btn-small" type="button" onClick={toggleRead} disabled={busy}>
                {selected.is_read ? 'O‘qilmagan qilish' : 'O‘qilgan qilish'}
              </button>
              <button className="admin-btn admin-btn-small admin-btn-danger" type="button" onClick={removeMessage} disabled={busy}>
                O‘chirish
              </button>
            </div>
          </article>
        ) : (
          <div className="admin-message-placeholder">
            <span>✉</span>
            <p>Xabarni ochish uchun ro‘yxatdan birini tanlang.</p>
          </div>
        )}
      </div>
    </section>
  );
}
