'use client';

let csrfToken = '';

export function setCsrfToken(value) {
  csrfToken = typeof value === 'string' ? value : '';
}

export function getCsrfToken() {
  return csrfToken;
}

export async function adminApi(path, options = {}) {
  const headers = new Headers(options.headers || {});
  let body = options.body;

  if (body && typeof body !== 'string') {
    headers.set('Content-Type', 'application/json');
    body = JSON.stringify(body);
  }

  if (csrfToken) {
    headers.set('X-CSRF-Token', csrfToken);
  }
  headers.set('X-Requested-With', 'XMLHttpRequest');

  const response = await fetch(`/api.php/admin${path}`, {
    credentials: 'same-origin',
    cache: 'no-store',
    ...options,
    headers,
    body,
  });

  let payload = null;
  try {
    payload = await response.json();
  } catch (error) {
    payload = null;
  }

  if (!response.ok) {
    const failure = new Error((payload && payload.message) || 'Request failed');
    failure.status = response.status;
    failure.code = payload && payload.code;
    failure.errors = (payload && payload.errors) || null;
    throw failure;
  }

  return payload ? payload.data : null;
}

export function errorMessage(error) {
  if (!error) return 'Xatolik yuz berdi.';
  if (error.errors && typeof error.errors === 'object') {
    const first = Object.keys(error.errors)[0];
    if (first) return error.errors[first];
  }
  if (error.code === 'auth_required') return 'Sessiya tugagan. Qayta kiring.';
  if (error.code === 'csrf_failed') return 'Xavfsizlik tokeni eskirgan. Sahifani yangilang.';
  if (error.code === 'rate_limited') return 'Juda ko‘p urinish. Keyinroq qayta urinib ko‘ring.';
  if (error.code === 'email_failed') return 'Kod yuborilmadi. Email sozlamalarini tekshiring.';
  if (error.code === 'challenge_expired' || error.code === 'challenge_required') return 'Kod muddati tugagan. Telegram orqali qayta kiring.';
  if (error.code === 'invalid_code') return 'Kod noto‘g‘ri.';
  if (error.code === 'too_many_attempts') return 'Urinishlar tugadi. Telegram orqali qayta kiring.';
  if (error.code === 'sitemap_write_failed') return 'Sitemap yozilmadi. Fayl ruxsatlarini tekshiring.';
  if (error.code === 'cannot_delete_self') return 'O‘z admin hisobingizni o‘chira olmaysiz.';
  if (error.code === 'cannot_deactivate_self') return 'O‘z admin hisobingizni deaktiv qila olmaysiz.';
  if (error.code === 'last_admin') return 'Kamida bitta faol admin qolishi kerak.';
  if (error.code === 'last_super_admin') return 'Kamida bitta faol super admin qolishi kerak.';
  if (error.code === 'forbidden') return 'Bu amal uchun super admin huquqi kerak.';
  if (error.code === 'admin_exists') return 'Bu Telegram ID allaqachon admin.';
  if (error.code === 'message_not_found') return 'Xabar topilmadi.';
  return error.message || 'Xatolik yuz berdi.';
}
