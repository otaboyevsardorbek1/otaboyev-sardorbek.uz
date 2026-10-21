'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { adminApi, errorMessage } from '../../../components/admin/api';

export default function AdminVerifyPage() {
  const router = useRouter();
  const [state, setState] = useState({ phase: 'loading', email: '', expiresIn: 0, attempts: 0 });
  const [code, setCode] = useState('');
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [resending, setResending] = useState(false);
  const [cooldown, setCooldown] = useState(0);

  useEffect(() => {
    let active = true;
    adminApi('/auth/status')
      .then((data) => {
        if (!active) return;
        setState({ phase: 'ready', email: data.email, expiresIn: data.expires_in, attempts: data.attempts_remaining });
      })
      .catch((failure) => {
        if (!active) return;
        if (failure.status === 401) {
          router.replace('/admin/');
          return;
        }
        setState((current) => ({ ...current, phase: 'ready' }));
        setError(errorMessage(failure));
      });

    return () => {
      active = false;
    };
  }, [router]);

  useEffect(() => {
    if (cooldown <= 0) return;
    const timer = window.setInterval(() => setCooldown((value) => Math.max(0, value - 1)), 1000);
    return () => window.clearInterval(timer);
  }, [cooldown]);

  async function submit(event) {
    event.preventDefault();
    if (submitting) return;
    setError('');
    setNotice('');
    setSubmitting(true);
    try {
      await adminApi('/auth/verify', { method: 'POST', body: { code } });
      router.replace('/admin/dashboard/');
    } catch (failure) {
      setError(errorMessage(failure));
      setCode('');
      if (failure.code === 'challenge_expired' || failure.code === 'too_many_attempts') {
        window.setTimeout(() => router.replace('/admin/'), 1200);
      }
    } finally {
      setSubmitting(false);
    }
  }

  async function resend() {
    if (resending || cooldown > 0) return;
    setResending(true);
    setError('');
    setNotice('');
    try {
      const data = await adminApi('/auth/resend', { method: 'POST' });
      setState((current) => ({ ...current, phase: 'ready', email: data.email, expiresIn: data.expires_in }));
      setCooldown(60);
      setNotice('Yangi kod yuborildi.');
    } catch (failure) {
      setError(errorMessage(failure));
      if (failure.status === 401) window.setTimeout(() => router.replace('/admin/'), 1200);
    } finally {
      setResending(false);
    }
  }

  return (
    <main className="admin-auth-page">
      <section className="admin-auth-card">
        <a className="admin-brand" href="/uz/">
          <span className="admin-brand-mark">S</span>
          <span>otaboyev-sardorbek.uz</span>
        </a>

        <div className="admin-auth-heading">
          <span className="admin-kicker">Step 2 / 2</span>
          <h1>Email kod</h1>
          <p>
            Kod <strong>{state.email || 'admin email'}</strong> manziliga yuborildi. 10 daqiqa ichida kiriting.
          </p>
        </div>

        {state.phase === 'loading' && (
          <div className="admin-status">
            <span className="admin-spinner" aria-hidden="true" />
            Tekshirilmoqda...
          </div>
        )}

        {state.phase !== 'loading' && (
          <form className="admin-code-form" onSubmit={submit}>
            <label htmlFor="admin-code">6 xonali kod</label>
            <input
              id="admin-code"
              className="admin-code-input"
              type="text"
              inputMode="numeric"
              autoComplete="one-time-code"
              pattern="[0-9]{6}"
              maxLength={6}
              value={code}
              onChange={(event) => setCode(event.target.value.replace(/\D/g, '').slice(0, 6))}
              autoFocus
              required
            />
            <button className="admin-btn admin-btn-primary admin-btn-block" type="submit" disabled={submitting || code.length !== 6}>
              {submitting ? 'Tekshirilmoqda...' : 'Panelga kirish'}
            </button>
          </form>
        )}

        {error && <div className="admin-alert admin-alert-error">{error}</div>}
        {notice && <div className="admin-alert admin-alert-ok">{notice}</div>}

        <div className="admin-auth-actions">
          <button className="admin-link-button" type="button" onClick={resend} disabled={resending || cooldown > 0}>
            {resending ? 'Yuborilmoqda...' : cooldown > 0 ? `Qayta yuborish (${cooldown}s)` : 'Kodni qayta yuborish'}
          </button>
          <a className="admin-link-button" href="/admin/">Boshidan boshlash</a>
        </div>

        <div className="admin-auth-security">
          <span>Kod bir marta ishlaydi</span>
          <span>5 urinish</span>
          <span>10 daqiqa</span>
        </div>
      </section>
    </main>
  );
}
