'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { adminApi, errorMessage, setCsrfToken } from './api';
import ProfileEditor from './ProfileEditor';
import ProjectManager from './ProjectManager';
import MessageInbox from './MessageInbox';
import AdminManager from './AdminManager';

export default function AdminDashboard() {
  const router = useRouter();
  const [phase, setPhase] = useState('loading');
  const [admin, setAdmin] = useState(null);
  const [profile, setProfile] = useState(null);
  const [projects, setProjects] = useState([]);
  const [unread, setUnread] = useState(0);
  const [tab, setTab] = useState('profile');
  const [error, setError] = useState('');
  const [toast, setToast] = useState('');

  useEffect(() => {
    let active = true;

    async function boot() {
      try {
        const session = await adminApi('/session');
        if (!active) return;
        setCsrfToken(session.csrf_token);
        setAdmin(session.admin);
        const [profileData, projectData, messageCount] = await Promise.all([
          adminApi('/profile'),
          adminApi('/projects'),
          adminApi('/messages?count=1').catch(() => null),
        ]);
        if (!active) return;
        setProfile(profileData);
        setProjects(projectData || []);
        setUnread(messageCount ? messageCount.unread || 0 : 0);
        setPhase('ready');
      } catch (failure) {
        if (!active) return;
        if (failure.status === 401) {
          router.replace('/admin/');
          return;
        }
        setError(errorMessage(failure));
        setPhase('error');
      }
    }

    boot();
    return () => {
      active = false;
    };
  }, [router]);

  useEffect(() => {
    if (!toast) return;
    const timer = window.setTimeout(() => setToast(''), 3500);
    return () => window.clearTimeout(timer);
  }, [toast]);

  async function refreshProjects() {
    const data = await adminApi('/projects');
    setProjects(data || []);
  }

  async function logout() {
    try {
      await adminApi('/logout', { method: 'POST' });
    } catch (failure) {
      if (failure.status !== 401) {
        setToast(errorMessage(failure));
      }
    } finally {
      setCsrfToken('');
      router.replace('/admin/');
    }
  }

  if (phase === 'loading') {
    return (
      <main className="admin-loading-page">
        <span className="admin-spinner" aria-hidden="true" />
        Admin panel yuklanmoqda...
      </main>
    );
  }

  if (phase === 'error') {
    return (
      <main className="admin-loading-page">
        <div className="admin-alert admin-alert-error">{error}</div>
        <a className="admin-btn admin-btn-primary" href="/admin/">Qayta kirish</a>
      </main>
    );
  }

  return (
    <div className="admin-shell">
      <aside className="admin-sidebar">
        <a className="admin-brand" href="/uz/">
          <span className="admin-brand-mark">S</span>
          <span>admin</span>
        </a>

        <nav className="admin-nav" aria-label="Admin navigatsiya">
          <button className={tab === 'profile' ? 'active' : ''} type="button" onClick={() => setTab('profile')}>
            <span>01</span> Profil
          </button>
          <button className={tab === 'projects' ? 'active' : ''} type="button" onClick={() => setTab('projects')}>
            <span>02</span> Loyihalar
          </button>
          <button className={tab === 'messages' ? 'active' : ''} type="button" onClick={() => setTab('messages')}>
            <span>03</span> Xabarlar
            {unread > 0 && <em className="admin-nav-badge">{unread}</em>}
          </button>
          <button className={tab === 'admins' ? 'active' : ''} type="button" onClick={() => setTab('admins')}>
            <span>04</span> Adminlar
          </button>
        </nav>

        <div className="admin-sidebar-footer">
          <a className="admin-site-link" href="/uz/" target="_blank" rel="noreferrer">Saytni ochish ↗</a>
          <div className="admin-who">
            <strong>{admin.display_name || admin.telegram_username || 'Admin'}</strong>
            <span>{admin.email}</span>
            <span>@{admin.telegram_username || admin.telegram_id}</span>
          </div>
          <button className="admin-btn admin-btn-ghost admin-btn-block" type="button" onClick={logout}>Chiqish</button>
        </div>
      </aside>

      <main className="admin-main">
        <header className="admin-topbar">
          <div>
            <span className="admin-kicker">Content manager</span>
            <h1>
              {tab === 'profile' && 'Profil ma’lumotlari'}
              {tab === 'projects' && 'Loyihalar boshqaruvi'}
              {tab === 'messages' && 'Kelgan xabarlar'}
              {tab === 'admins' && 'Adminlar boshqaruvi'}
            </h1>
          </div>
          <span className="admin-session-pill">Session faol</span>
        </header>

        {toast && <div className="admin-toast">{toast}</div>}

        {tab === 'profile' && profile && (
          <ProfileEditor
            profile={profile}
            onSaved={(updated) => {
              setProfile(updated);
              setToast('Profil saqlandi.');
            }}
          />
        )}

        {tab === 'projects' && (
          <ProjectManager
            projects={projects}
            onRefresh={refreshProjects}
            notify={setToast}
          />
        )}

        {tab === 'messages' && (
          <MessageInbox notify={setToast} onUnreadChange={setUnread} />
        )}

        {tab === 'admins' && (
          <AdminManager currentAdminId={admin.id} notify={setToast} />
        )}
      </main>
    </div>
  );
}
