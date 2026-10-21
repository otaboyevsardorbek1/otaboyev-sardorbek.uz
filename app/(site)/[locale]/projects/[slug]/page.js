import { notFound } from 'next/navigation';
import ProjectDetail from '../../../../../components/ProjectDetail';
import {
  getProjectDetail,
  getProjectDetailLabels,
  LOCALES,
  projectDetails,
  SITE_URL,
} from '../../../../../content/locales';

const OG_LOCALES = { uz: 'uz_UZ', en: 'en_US', ru: 'ru_RU' };

export const dynamicParams = false;

export function generateStaticParams() {
  return LOCALES.flatMap((locale) =>
    Object.keys(projectDetails[locale] || {}).map((slug) => ({ locale, slug }))
  );
}

export async function generateMetadata({ params }) {
  const { locale, slug } = await params;
  if (!LOCALES.includes(locale)) return {};
  const project = getProjectDetail(locale, slug);
  if (!project) return {};

  const path = `${SITE_URL}/${locale}/projects/${slug}/`;

  return {
    title: `${project.title} | Sardorbek Otaboyev`,
    description: project.summary,
    alternates: {
      canonical: path,
      languages: {
        uz: `${SITE_URL}/uz/projects/${slug}/`,
        en: `${SITE_URL}/en/projects/${slug}/`,
        ru: `${SITE_URL}/ru/projects/${slug}/`,
        'x-default': `${SITE_URL}/uz/projects/${slug}/`,
      },
    },
    openGraph: {
      title: `${project.title} | Sardorbek Otaboyev`,
      description: project.summary,
      type: 'article',
      url: path,
      siteName: 'Sardorbek Otaboyev',
      locale: OG_LOCALES[locale],
    },
  };
}

export default async function ProjectDetailPage({ params }) {
  const { locale, slug } = await params;
  if (!LOCALES.includes(locale)) notFound();
  const project = getProjectDetail(locale, slug);
  if (!project) notFound();

  return (
    <ProjectDetail
      locale={locale}
      slug={slug}
      fallback={project}
      labels={getProjectDetailLabels(locale)}
    />
  );
}
