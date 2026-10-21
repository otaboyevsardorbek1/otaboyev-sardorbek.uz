'use client';

import { useEffect, useRef, useState } from 'react';
import { useRouter } from 'next/navigation';
import { adminApi, setCsrfToken } from '../../components/admin/api';

const ERROR_TEXT = {
  config: 'Admin auth hali sozlanmagan. config.local.php yoki env’dagi Telegram va email sozlamalarini to‘ldiring.',
  telegram: 'Telegram tasdiqlash ma’lumoti noto‘g‘ri. Qayta urinib ko‘ring.',
  expired: 'Telegram tasdiqlash muddati tugagan. Qayta kiring.',
  unauthorized: 'Bu Telegram akkaunt admin sifatida ruxsat etilmagan.',
  rate: 'Juda ko‘p urinish. 15 daqiqadan keyin qayta urinib ko‘ring.',
  email: 'Kod yuborilmadi. Email xizmati sozlamalarini tekshiring.',
};

export default function AdminLoginPage() {
  const router = useRouter();
  const widgetRef = useRef(null);
  const [config, setConfig] = useState(null);
  const [phase, setPhase] = useState('loading');
  const [error, setError] = useState('');

  useEffect(() => {
    let active = true;
    const params = new URLSearchParams(window.location.search);
    const errorCode = params.get('error');
    if (errorCode) setError(ERROR_TEXT[errorCode] || 'Kirish amalga oshmadi.');

    Promise.allSettled([adminApi('/config'), adminApi('/session?optional=1')]).then(([configResult, sessionResult]) => {
      if (!active) return;
      if (sessionResult.status === 'fulfilled' && sessionResult.value.authenticated) {
        setCsrfToken(sessionResult.value.csrf_token);
        router.replace('/admin/dashboard/');
        return;
      }

      if (configResult.status === 'fulfilled') {
        setConfig(configResult.value);
        setPhase(configResult.value.enabled ? 'ready' : 'disabled');
      } else {
        setPhase('error');
        setError('Admin API javob bermadi. PHP va MySQL sozlamalarini tekshiring.');
      }
    });

    return () => {
      active = false;
    };
  }, [router]);

  useEffect(() => {
    if (phase !== 'ready' || !config || !widgetRef.current) return;
    const container = widgetRef.current;
    container.innerHTML = '';

    const script = document.createElement('script');
    script.async = true;
    script.src = 'https://telegram.org/js/telegram-widget.js?22';
    script.setAttribute('data-telegram-login', config.bot_username);
    script.setAttribute('data-size', 'large');
    script.setAttribute('data-radius', '12');
    script.setAttribute('data-userpic', 'false');
    script.setAttribute('data-lang', 'uz');
    script.setAttribute('data-request-access', 'write');
    script.setAttribute('data-auth-url', config.auth_url);
    script.onerror = () => setError('Telegram widget yuklanmadi. Internet yoki domen sozlamasini tekshiring.');
    container.appendChild(script);

    return () => {
      container.innerHTML = '';
    };
  }, [phase, config]);

  return (
    <main className="admin-auth-page">
      <section className="admin-auth-card">
        <a className="admin-brand" href="/uz/">
          <span className="admin-brand-mark">S</span>
          <span>otaboyev-sardorbek.uz</span>
        </a>

        <div className="admin-auth-heading">
          <span className="admin-kicker">Private area</span>
          <h1>Admin panel</h1>
          <p>Telegram akkauntingiz bilan tasdiqlang. Keyin admin emailiga yuborilgan 6 xonali kodni kiritasiz.</p>
        </div>

        {phase === 'loading' && (
          <div className="admin-status">
            <span className="admin-spinner" aria-hidden="true" />
            Tekshirilmoqda...
          </div>
        )}

        {phase === 'disabled' && (
          <div className="admin-alert admin-alert-warn">
            <strong>Auth sozlanmagan.</strong>
            <p>Serverda <code>TELEGRAM_BOT_TOKEN</code>, <code>TELEGRAM_BOT_USERNAME</code>, <code>ADMIN_TELEGRAM_ID</code>, <code>ADMIN_EMAIL</code> va <code>APP_SECRET</code> ni kiriting.</p>
          </div>
        )}

        {phase === 'error' && (
          <div className="admin-alert admin-alert-error">{error}</div>
        )}

        {phase === 'ready' && (
          <>
            <div className="admin-widget" ref={widgetRef} />
            <p className="admin-auth-hint">
              Telegram widget ishlamasa, <a href={`https://t.me/${config.bot_username}`} target="_blank" rel="noreferrer">@{config.bot_username}</a> orqali botni oching.
            </p>
          </>
        )}

        {error && phase !== 'error' && <div className="admin-alert admin-alert-error">{error}</div>}

        <div className="admin-auth-security">
          <span>2 bosqichli kirish</span>
          <span>HttpOnly session</span>
          <span>CSRF himoyasi</span>
        </div>
      </section>
    </main>
  );
}
