'use client';

import { useEffect } from 'react';

const SUPPORTED = ['uz', 'ru', 'en'];

export default function RootPage() {
  useEffect(() => {
    const detected = (navigator.languages || [navigator.language || ''])
      .map((lang) => (lang || '').toLowerCase().substring(0, 2));
    const match = SUPPORTED.find((lang) => detected.includes(lang));
    window.location.replace('/' + (match || 'uz') + '/');
  }, []);

  return (
    <main>
      <p>
        <a href="/uz/">O‘zbekcha</a> · <a href="/en/">English</a> · <a href="/ru/">Русский</a>
      </p>
    </main>
  );
}
