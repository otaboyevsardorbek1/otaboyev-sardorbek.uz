import { notFound } from 'next/navigation';
import ProjectsPage from '../../../../components/ProjectsPage';
import { getContent, LOCALES, SITE_URL } from '../../../../content/locales';

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
  const title = `${copy.projects.title} | Sardorbek Otaboyev`;
  const image = ogImage(locale, title, copy.projects.listIntro);

  return {
    title,
    description: copy.projects.listIntro,
    alternates: {
      canonical: `${SITE_URL}/${locale}/projects/`,
      languages: {
        uz: `${SITE_URL}/uz/projects/`,
        en: `${SITE_URL}/en/projects/`,
        ru: `${SITE_URL}/ru/projects/`,
        'x-default': `${SITE_URL}/uz/projects/`,
      },
    },
    openGraph: {
      title,
      description: copy.projects.listIntro,
      type: 'website',
      url: `${SITE_URL}/${locale}/projects/`,
      siteName: 'Sardorbek Otaboyev',
      locale: OG_LOCALES[locale],
      images: [image],
    },
    twitter: {
      card: 'summary_large_image',
      title,
      description: copy.projects.listIntro,
      images: [image.url],
    },
  };
}

export default async function ProjectsIndex({ params }) {
  const { locale } = await params;
  if (!LOCALES.includes(locale)) notFound();

  return <ProjectsPage locale={locale} copy={getContent(locale)} />;
}
