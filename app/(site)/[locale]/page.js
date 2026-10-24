import { notFound } from 'next/navigation';
import Home from '../../../components/Home';
import JsonLd from '../../../components/JsonLd';
import { getContent, LOCALES, SITE_URL } from '../../../content/locales';

const OG_LOCALES = { uz: 'uz_UZ', en: 'en_US', ru: 'ru_RU' };

function ogImage(locale, title, description) {
  return {
    url: `${SITE_URL}/og/og-${locale}.png`,
    width: 1200,
    height: 630,
    alt: title,
    type: 'image/png',
  };
}

export async function generateMetadata({ params }) {
  const { locale } = await params;
  if (!LOCALES.includes(locale)) return {};
  const copy = getContent(locale);
  const image = ogImage(locale, copy.meta.ogTitle, copy.meta.ogDescription);

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
      images: [image],
    },
    twitter: {
      card: 'summary_large_image',
      title: copy.meta.ogTitle,
      description: copy.meta.ogDescription,
      images: [image.url],
    },
  };
}

export default async function LocaleHome({ params }) {
  const { locale } = await params;
  if (!LOCALES.includes(locale)) notFound();

  const person = {
    '@type': 'Person',
    '@id': `${SITE_URL}/#person`,
    name: 'Sardorbek Otaboyev',
    url: SITE_URL,
    jobTitle: 'Backend Developer',
    email: 'otaboyevsardorbek1@gmail.com',
    sameAs: [
      'https://github.com/otaboyevsardorbek1',
      'https://t.me/prodevuzoff',
    ],
  };
  const jsonLd = {
    '@context': 'https://schema.org',
    '@graph': [
      person,
      {
        '@type': 'WebSite',
        '@id': `${SITE_URL}/#website`,
        url: SITE_URL,
        name: 'Sardorbek Otaboyev',
        inLanguage: ['uz', 'en', 'ru'],
        publisher: { '@id': `${SITE_URL}/#person` },
      },
    ],
  };

  return (
    <>
      <JsonLd data={jsonLd} />
      <Home locale={locale} copy={getContent(locale)} />
    </>
  );
}
