import { notFound } from 'next/navigation';
import ProjectDetail from '../../../../../components/ProjectDetail';
import { getProjectDetailLabels, LOCALES, SITE_URL } from '../../../../../content/locales';

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
  const labels = getProjectDetailLabels(locale);
  const title = `${labels.detailTitle} | Sardorbek Otaboyev`;
  const image = ogImage(locale, title, labels.detailDescription);

  return {
    title,
    description: labels.detailDescription,
    alternates: {
      canonical: `${SITE_URL}/${locale}/projects/`,
    },
    openGraph: {
      title,
      description: labels.detailDescription,
      type: 'article',
      url: `${SITE_URL}/${locale}/projects/`,
      siteName: 'Sardorbek Otaboyev',
      locale: OG_LOCALES[locale],
      images: [image],
    },
    twitter: {
      card: 'summary_large_image',
      title,
      description: labels.detailDescription,
      images: [image.url],
    },
  };
}

export default async function ProjectDetailFallbackPage({ params }) {
  const { locale } = await params;
  if (!LOCALES.includes(locale)) notFound();

  return <ProjectDetail locale={locale} labels={getProjectDetailLabels(locale)} />;
}
