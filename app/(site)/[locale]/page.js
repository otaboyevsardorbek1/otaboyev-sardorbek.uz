import { notFound } from 'next/navigation';
import Home from '../../../components/Home';
import { getContent, LOCALES, SITE_URL } from '../../../content/locales';

const OG_LOCALES = { uz: 'uz_UZ', en: 'en_US', ru: 'ru_RU' };

export async function generateMetadata({ params }) {
  const { locale } = await params;
  if (!LOCALES.includes(locale)) return {};
  const copy = getContent(locale);

  return {
    title: copy.meta.title,
    description: copy.meta.description,
    alternates: {
      canonical: `${SITE_URL}/${locale}/`,
      languages: {
        uz: `${SITE_URL}/uz/`,
        en: `${SITE_URL}/en/`,
        ru: `${SITE_URL}/ru/`,
        'x-default': `${SITE_URL}/`,
      },
    },
    openGraph: {
      title: copy.meta.ogTitle,
      description: copy.meta.ogDescription,
      type: 'website',
      url: `${SITE_URL}/${locale}/`,
      siteName: 'Sardorbek Otaboyev',
      locale: OG_LOCALES[locale],
    },
  };
}

export default async function LocaleHome({ params }) {
  const { locale } = await params;
  if (!LOCALES.includes(locale)) notFound();

  return <Home locale={locale} copy={getContent(locale)} />;
}
