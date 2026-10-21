import '../../../css/portfolio.css';
import { LOCALES, SITE_URL } from '../../../content/locales';

export const metadata = {
  metadataBase: new URL(SITE_URL),
  icons: { icon: '/favicon.svg' },
};

export const viewport = {
  width: 'device-width',
  initialScale: 1,
};

export function generateStaticParams() {
  return LOCALES.map((locale) => ({ locale }));
}

export default async function LocaleLayout({ children, params }) {
  const { locale } = await params;

  return (
    <html lang={locale}>
      <body>
        <noscript>
          <style>{'.reveal{opacity:1 !important;transform:none !important}'}</style>
        </noscript>
        {children}
      </body>
    </html>
  );
}
