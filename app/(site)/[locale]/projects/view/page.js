import { notFound } from 'next/navigation';
import ProjectDetail from '../../../../../components/ProjectDetail';
import { getProjectDetailLabels, LOCALES, SITE_URL } from '../../../../../content/locales';

const OG_LOCALES = { uz: 'uz_UZ', en: 'en_US', ru: 'ru_RU' };

export async function generateMetadata({ params }) {
  const { locale } = await params;
  if (!LOCALES.includes(locale)) return {};
  const labels = getProjectDetailLabels(locale);

  return {
    title: `${labels.detailTitle} | Sardorbek Otaboyev`,
    description: labels.detailDescription,
    alternates: {
      canonical: `${SITE_URL}/${locale}/projects/`,
    },
    openGraph: {
      title: `${labels.detailTitle} | Sardorbek Otaboyev`,
      description: labels.detailDescription,
      type: 'article',
      url: `${SITE_URL}/${locale}/projects/`,
      siteName: 'Sardorbek Otaboyev',
      locale: OG_LOCALES[locale],
    },
  };
}

export default async function ProjectDetailFallbackPage({ params }) {
  const { locale } = await params;
  if (!LOCALES.includes(locale)) notFound();

  return <ProjectDetail locale={locale} labels={getProjectDetailLabels(locale)} />;
}
